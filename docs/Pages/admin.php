<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';
require_once __DIR__ . '/../backend/messages.php';

require_admin();

$admin_id = (int) $_SESSION['user_id'];
$errors = array();
$reason_input = '';
$submitted_id = 0;

$notice = $_SESSION['admin_report_notice'] ?? '';
$notice = is_string($notice) ? $notice : '';
unset($_SESSION['admin_report_notice']);

// Result of review_message.php: array(type, text).
$message_notice = $_SESSION['message_review_notice'] ?? null;
unset($_SESSION['message_review_notice']);

/*
 * Execute a prepared statement.
 * The caller closes successful statements after using their results.
 */
$admin_query = static function ($sql, $types = '', array $values = array()) use ($conn) {
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new RuntimeException('Could not prepare database operation.');
    }

    try {
        if ($types !== '') {
            if (!mysqli_stmt_bind_param($stmt, $types, ...$values)) {
                throw new RuntimeException('Could not bind database values.');
            }
        }

        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('Database operation failed.');
        }

        return $stmt;
    } catch (Throwable $exception) {
        mysqli_stmt_close($stmt);
        throw $exception;
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $raw_id = $_POST['report_id'] ?? null;

    $report_id = is_string($raw_id)
        ? filter_var(
            $raw_id,
            FILTER_VALIDATE_INT,
            array('options' => array('min_range' => 1))
        )
        : false;

    $decision = $_POST['decision'] ?? '';
    $decision = is_string($decision) ? $decision : '';

    $reason_input = $_POST['rejection_reason'] ?? '';
    $reason_input = is_string($reason_input)
        ? trim($reason_input)
        : '';

    $submitted_id = $report_id !== false ? (int) $report_id : 0;

    if ($report_id === false) {
        $errors[] = 'Please select a valid report.';
    }

    if (!in_array($decision, array('Approved', 'Rejected'), true)) {
        $errors[] = 'Please choose Approve or Reject.';
    }

    if ($decision === 'Rejected' && $reason_input === '') {
        $errors[] = 'Please explain why the report is being rejected.';
    }

    $reason_length = function_exists('mb_strlen')
        ? mb_strlen($reason_input, 'UTF-8')
        : strlen($reason_input);

    if ($reason_length > 2000) {
        $errors[] = 'Please keep the rejection reason within 2,000 characters.';
    }

    if (empty($errors)) {
        $transaction_started = false;
        $decision_saved = false;
        $was_legacy = false;
        $report = null;

        try {
            if (!mysqli_begin_transaction($conn)) {
                throw new RuntimeException('Could not start review transaction.');
            }

            $transaction_started = true;

            $stmt = $admin_query(
                "SELECT id, title, description, status, blockchain_status
                 FROM reports
                 WHERE id = ?
                 FOR UPDATE",
                'i',
                array($report_id)
            );

            $result = mysqli_stmt_get_result($stmt);

            if (!$result) {
                mysqli_stmt_close($stmt);
                throw new RuntimeException('Report result unavailable.');
            }

            $report = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$report) {
                $errors[] = 'This report no longer exists.';
            } elseif (
                !in_array($report['status'], array('Pending', 'Verified'), true)
            ) {
                $errors[] =
                    'This report already has a current review decision. '
                    . 'Refresh the page to see its status.';
            }

            if (!empty($errors)) {
                mysqli_rollback($conn);
                $transaction_started = false;
            } else {
                $old_status = $report['status'];
                $was_legacy = $old_status === 'Verified';

                $saved_reason = $decision === 'Rejected'
                    ? $reason_input
                    : null;

                /*
                 * Preserve the technical status of older reports.
                 * Editorial approval does not confirm an old blockchain entry.
                 */
                if ($was_legacy) {
                    $ledger_status = trim(
                        (string) ($report['blockchain_status'] ?? '')
                    );

                    if ($ledger_status === '') {
                        $ledger_status = 'Unknown';
                    }
                } else {
                    $ledger_status = $decision === 'Approved'
                        ? 'Pending'
                        : 'Not requested';
                }

                $stmt = $admin_query(
                    "UPDATE reports
                     SET status = ?,
                         rejection_reason = ?,
                         blockchain_status = ?
                     WHERE id = ? AND status = ?",
                    'sssis',
                    array(
                        $decision,
                        $saved_reason,
                        $ledger_status,
                        $report_id,
                        $old_status
                    )
                );

                $changed = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);

                if ($changed !== 1) {
                    throw new RuntimeException('Report changed during review.');
                }

                /*
                 * Save the decision and its audit entry together.
                 * Existing verification_chain rows are not changed or deleted.
                 */
                $stmt = $admin_query(
                    "INSERT INTO admin_actions
                        (admin_id, report_id, action)
                     VALUES (?, ?, ?)",
                    'iis',
                    array($admin_id, $report_id, $decision)
                );

                mysqli_stmt_close($stmt);

                if (!mysqli_commit($conn)) {
                    throw new RuntimeException('Could not save review transaction.');
                }

                $transaction_started = false;
                $decision_saved = true;
            }
        } catch (Throwable $exception) {
            if ($transaction_started) {
                try {
                    mysqli_rollback($conn);
                } catch (Throwable $rollback_exception) {
                    error_log('DiSSENT: admin review rollback failed.');
                }
            }

            error_log(
                'DiSSENT: report review failed for report '
                . (int) $report_id
            );

            $errors[] =
                'The review could not be completed. '
                . 'Check the report status before trying again.';
        }

        if ($decision_saved) {
            $notice = 'Report #' . $report_id . ' '
                . strtolower($decision) . '.';

            if ($was_legacy) {
                $notice .=
                    ' Its previous verification records were preserved.';

                if ($decision === 'Approved') {
                    $notice .=
                        ' The report is now approved for publication. '
                        . 'No new blockchain recording request was sent; '
                        . 'use View verification to check the current service record.';
                }
            } elseif ($decision === 'Approved') {
                /*
                 * Only newly approved Pending reports reach this block.
                 * Remote recording happens after the MySQL decision commits.
                 *
                 * A timeout does not prove that recording failed, so use
                 * "Unconfirmed" rather than "Failed". Do not retry automatically.
                 */
                $ledger_status = 'Unconfirmed';

                try {
                    $block_hash = bc_add_verification_block(
                        $report_id,
                        $report['title'],
                        $report['description']
                    );

                    if (
                        is_string($block_hash) &&
                        trim($block_hash) !== ''
                    ) {
                        $ledger_status = 'Reported recorded';

                        try {
                            cache_verification_block(
                                $report_id,
                                trim($block_hash)
                            );

                            $notice .=
                                ' A recording hash was returned and saved locally.';
                        } catch (Throwable $exception) {
                            error_log(
                                'DiSSENT: verification cache failed for report '
                                . (int) $report_id
                            );

                            $notice .=
                                ' A recording hash was returned, but its local '
                                . 'copy could not be saved.';
                        }
                    } else {
                        $notice .=
                            ' Blockchain recording was not confirmed. '
                            . 'The publication approval is saved.';
                    }
                } catch (Throwable $exception) {
                    error_log(
                        'DiSSENT: blockchain request failed for report '
                        . (int) $report_id
                    );

                    $notice .=
                        ' Blockchain recording was not confirmed. '
                        . 'The publication approval is saved.';
                }

                try {
                    $stmt = $admin_query(
                        "UPDATE reports
                         SET blockchain_status = ?
                         WHERE id = ?
                           AND status = 'Approved'
                           AND blockchain_status = 'Pending'",
                        'si',
                        array($ledger_status, $report_id)
                    );

                    $changed = mysqli_stmt_affected_rows($stmt);
                    mysqli_stmt_close($stmt);

                    if ($changed !== 1) {
                        throw new RuntimeException('Recording status was not updated.');
                    }
                } catch (Throwable $exception) {
                    error_log(
                        'DiSSENT: recording status update failed for report '
                        . (int) $report_id
                    );

                    $notice .=
                        ' The recording-status field could not be updated.';
                }
            }

            $_SESSION['admin_report_notice'] = $notice;

            header('Location: admin.php', true, 303);
            exit();
        }
    }
}

$reports = array();
$activity_log = array();
$load_failed = false;
$activity_failed = false;

$stats = array(
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'legacy_verified' => 0
);

try {
    $reports = db_rows(
        "SELECT
            r.id, r.title, r.category, r.description,
            r.location, r.evidence, r.status,
            r.rejection_reason, r.blockchain_status,
            r.created_at, u.fullname,
            (
                SELECT vc.block_hash
                FROM verification_chain vc
                WHERE vc.report_id = r.id
                  AND vc.block_hash IS NOT NULL
                  AND TRIM(vc.block_hash) <> ''
                ORDER BY vc.created_at DESC, vc.id DESC
                LIMIT 1
            ) AS cached_block_hash
         FROM reports r
         LEFT JOIN users u ON r.user_id = u.id
         ORDER BY r.created_at DESC, r.id DESC"
    );

    foreach ($reports as $row) {
        $stats['total']++;

        switch ($row['status']) {
            case 'Pending':
                $stats['pending']++;
                break;

            case 'Approved':
                $stats['approved']++;
                break;

            case 'Rejected':
                $stats['rejected']++;
                break;

            case 'Verified':
                $stats['legacy_verified']++;
                break;
        }
    }
} catch (Throwable $exception) {
    $load_failed = true;
    error_log('DiSSENT: admin reports could not be loaded.');
}

try {
    $activity_log = db_rows(
        "SELECT
            aa.action, aa.action_at,
            r.title AS report_title,
            u.fullname AS admin_name
         FROM admin_actions aa
         LEFT JOIN reports r ON aa.report_id = r.id
         LEFT JOIN users u ON aa.admin_id = u.id
         ORDER BY aa.action_at DESC
         LIMIT 10"
    );
} catch (Throwable $exception) {
    $activity_failed = true;
    error_log('DiSSENT: admin activity could not be loaded.');
}

// Messages held by the compliance check, oldest first, and the latest decisions.
$held_messages = array();
$held_total = 0;
$message_decisions = array();
$held_failed = false;

try {
    $held_total = db_count('messages', "status = 'pending_review'");
    $held_messages = messages_rows(
        "SELECT m.id, m.sender_id, m.receiver_id, m.message, m.sent_at, m.review_reasons,
                s.fullname AS sender_name, r.fullname AS receiver_name,
                f.id AS file_id, f.original_name, f.mime, f.file_size, f.removed_at
         FROM messages m
         JOIN users s ON s.id = m.sender_id
         JOIN users r ON r.id = m.receiver_id
         LEFT JOIN message_files f ON f.message_id = m.id
         WHERE m.status = 'pending_review'
         ORDER BY m.sent_at ASC, m.id ASC
         LIMIT 50"
    );
    $message_decisions = messages_rows(
        "SELECT v.message_id, v.decision, v.reasons, v.note, v.created_at, u.fullname AS admin_name
         FROM message_reviews v
         LEFT JOIN users u ON u.id = v.reviewer_id
         WHERE v.decision <> 'held'
         ORDER BY v.id DESC
         LIMIT 10"
    );
} catch (Throwable $exception) {
    $held_failed = true;
    error_log('DiSSENT: held messages could not be loaded.');
}

// AI suggestions for the held messages, and how often admins agreed with the AI so far.
$ai_checks = array();
$ai_agreement = null;

foreach ($held_messages as $held_row) {
    $ai_row = messages_ai_check((int) $held_row['id']);
    if ($ai_row !== null) {
        $ai_checks[(int) $held_row['id']] = $ai_row;
    }
}

try {
    $ai_agreement = messages_rows('SELECT COUNT(*) AS decided, COALESCE(SUM(admin_agreed = 1), 0) AS agreed FROM message_ai_checks WHERE admin_agreed IS NOT NULL')[0];
} catch (Throwable $exception) {
    $ai_agreement = null;
}

$contact_messages = array();
$contact_total = 0;
$contact_failed = false;

try {
    $contact_total = db_count('contact_messages');
    $contact_messages = db_rows(
        "SELECT id, full_name, email, subject, message, created_at
         FROM contact_messages
         ORDER BY created_at DESC, id DESC
         LIMIT 50"
    );
} catch (Throwable $exception) {
    $contact_failed = true;
    error_log('DiSSENT: contact messages could not be loaded.');
}

$status_labels = array(
    'Pending' => 'Pending review',
    'Approved' => 'Approved for publication',
    'Rejected' => 'Rejected',
    'Verified' => 'Previous Verified status — publication review needed'
);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Report Review - DiSSENT</title>
    <meta name="description" content="Administrators review reports and held messages on DiSSENT.">

    <link rel="stylesheet" href="../assets/css/base.css?v=5">
    <link rel="stylesheet" href="../assets/css/admin.css?v=3">
</head>

<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<main id="main-content" tabindex="-1">

<section class="page-hero" aria-labelledby="adminHeading">
    <h1 id="adminHeading">Report Review</h1>

    <p>
        Read submissions and decide whether they are suitable for publication.
        Publication approval and blockchain recording are separate.
    </p>
</section>

<div class="review-page">

    <nav class="review-sections" aria-label="Admin sections">
        <ul>
            <li><a href="#submittedReports">Submitted reports</a></li>
            <li><a href="#messageReviews">Messages waiting for review<?php echo $held_total > 0 ? ' (' . (int) $held_total . ')' : ''; ?></a></li>
            <li><a href="#adminActivity">Recent activity</a></li>
            <li><a href="#contactMessages">Contact messages</a></li>
        </ul>
    </nav>

    <?php if ($notice !== ''): ?>
        <div class="notice notice--success" role="status">
            <?php echo e($notice); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="notice notice--error" role="alert">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo e($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($load_failed): ?>

        <div class="notice notice--error" role="alert">
            Reports could not be loaded. Please check the PHP terminal
            for errors and try again.
        </div>

    <?php else: ?>

        <div class="review-stats">
            <div class="review-stat">
                <strong><?php echo $stats['total']; ?></strong>
                Total reports
            </div>

            <div class="review-stat">
                <strong><?php echo $stats['pending']; ?></strong>
                Pending review
            </div>

            <div class="review-stat">
                <strong><?php echo $stats['approved']; ?></strong>
                Approved
            </div>

            <div class="review-stat">
                <strong><?php echo $stats['rejected']; ?></strong>
                Rejected
            </div>
        </div>

        <?php if ($stats['legacy_verified'] > 0): ?>
            <div class="notice">
                <p>
                    <strong>
                        <?php echo $stats['legacy_verified']; ?>
                        older report(s) need a publication decision.
                    </strong>
                </p>

                <p>
                    These reports use the previous “Verified” status.
                    Open each report below, read it, and choose whether
                    to approve publication or reject it.
                    Existing verification records will be preserved.
                </p>
            </div>
        <?php endif; ?>

        <h2 id="submittedReports">Submitted Reports</h2>

        <?php if (empty($reports)): ?>
            <div class="review-card">
                <p>No reports have been submitted yet.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($reports as $r): ?>
            <?php
            $id = (int) $r['id'];

            $label = $status_labels[$r['status']]
                ?? 'Unknown status';

            $reviewable = in_array(
                $r['status'],
                array('Pending', 'Verified'),
                true
            );

            $is_legacy = $r['status'] === 'Verified';

            $evidence = trim((string) ($r['evidence'] ?? ''));

            $scheme = strtolower(
                (string) parse_url($evidence, PHP_URL_SCHEME)
            );

            $valid_link =
                filter_var($evidence, FILTER_VALIDATE_URL) !== false &&
                in_array($scheme, array('http', 'https'), true);

            $cached_hash = trim(
                (string) ($r['cached_block_hash'] ?? '')
            );
            ?>

            <article class="review-card" id="report-<?php echo $id; ?>">

                <h3><?php echo e($r['title']); ?></h3>

                <p>
                    <strong>Report ID:</strong>
                    <?php echo $id; ?><br>

                    <strong>Review:</strong>
                    <span title="<?php echo e(report_status_explanation($r['status'])); ?>"><?php echo e($label); ?></span>
                    <span class="status-help">(<?php echo e(report_status_explanation($r['status'])); ?>)</span><br>

                    <strong>Blockchain recording status:</strong>
                    <?php echo e($r['blockchain_status'] ?: 'Unknown'); ?><br>

                    <strong>Local hash reference:</strong>
                    <?php
                    echo $cached_hash !== ''
                        ? 'Saved — current service record still needs checking'
                        : 'No saved hash reference';
                    ?><br>

                    <strong>Submitted by:</strong>
                    <?php echo e($r['fullname'] ?? 'Unavailable account'); ?><br>

                    <strong>Submitted:</strong>
                    <?php echo e($r['created_at']); ?>
                </p>

                <?php if ($r['status'] === 'Approved'): ?>
                    <p>
                        <a href="verification.php?report_id=<?php echo $id; ?>">
                            View verification
                        </a>
                    </p>
                <?php endif; ?>

                <details <?php echo $submitted_id === $id ? 'open' : ''; ?>>
                    <summary>Read report and review</summary>

                    <p>
                        <strong>Category:</strong>
                        <?php echo e($r['category']); ?><br>

                        <strong>Location:</strong>
                        <?php echo e($r['location'] ?: 'Not provided'); ?>
                    </p>

                    <h4>Report Description</h4>

                    <p class="review-description"><?php
                        echo e($r['description']);
                    ?></p>

                    <h4>Supporting Evidence</h4>

                    <p>
                        <?php if ($valid_link): ?>
                            <a
                                href="<?php echo e($evidence); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >Open evidence link (new tab)</a>
                        <?php elseif ($evidence !== ''): ?>
                            <?php echo e($evidence); ?>
                        <?php else: ?>
                            No evidence link provided.
                        <?php endif; ?>
                    </p>

                    <?php if (!empty($r['rejection_reason'])): ?>
                        <h4>Rejection Reason</h4>

                        <p class="review-description"><?php
                            echo e($r['rejection_reason']);
                        ?></p>
                    <?php endif; ?>

                    <?php if ($reviewable): ?>

                        <?php if ($is_legacy): ?>
                            <p>
                                This is an older report. Approving it will
                                make it eligible for public publication.
                                Its existing hash reference will be preserved,
                                and no new blockchain recording request
                                will be sent.
                            </p>
                        <?php else: ?>
                            <p>
                                Approving this report allows public publication
                                and requests a verification recording.
                                Publication approval remains saved if the
                                recording service is unavailable.
                            </p>
                        <?php endif; ?>

                        <form method="POST" action="admin.php" class="review-form">

                            <?php echo csrf_field(); ?>

                            <input
                                type="hidden"
                                name="report_id"
                                value="<?php echo $id; ?>"
                            >

                            <label for="reason-<?php echo $id; ?>">
                                Reason — required when rejecting
                            </label>

                            <textarea
                                id="reason-<?php echo $id; ?>"
                                name="rejection_reason"
                                maxlength="2000"
                                rows="3"
                            ><?php
                                echo $submitted_id === $id
                                    ? e($reason_input)
                                    : '';
                            ?></textarea>

                                                       <div class="review-buttons">
                                <button
                                    type="submit"
                                    name="decision"
                                    value="Approved"
                                    class="review-button review-approve"
                                >
                                    Approve for publication
                                </button>

                                <button
                                    type="submit"
                                    name="decision"
                                    value="Rejected"
                                    class="review-button review-reject"
                                >
                                    Reject report
                                </button>

                                <a
                                    href="reports.php?review=<?php echo $id; ?>"
                                    class="review-button review-document"
                                >
                                    Open report and document
                                </a>
                            </div>

                        </form>

                    <?php else: ?>

                        <div class="review-buttons">
                            <a
                                href="reports.php?review=<?php echo $id; ?>"
                                class="review-button review-document"
                            >
                                Open report and document
                            </a>
                        </div>

                    <?php endif; ?>

                </details>

            </article>

        <?php endforeach; ?>

    <?php endif; ?>

    <section class="review-messages" id="messageReviews" aria-labelledby="messageReviewsHeading">
        <h2 id="messageReviewsHeading">Messages waiting for review</h2>

        <?php if (is_array($message_notice) && count($message_notice) === 2): ?>
            <div class="notice notice--<?php echo $message_notice[0] === 'success' ? 'success' : 'error'; ?>" role="<?php echo $message_notice[0] === 'success' ? 'status' : 'alert'; ?>">
                <?php echo e($message_notice[1]); ?>
            </div>
        <?php endif; ?>

        <?php if ($held_failed): ?>
            <div class="notice notice--error" role="alert">
                Messages could not be loaded. If this is a new database, run the file in database/migrations/ first (see README).
            </div>
        <?php elseif (empty($held_messages)): ?>
            <p class="review-muted">No messages are waiting for review.</p>
        <?php endif; ?>

        <?php if ($ai_agreement !== null && (int) $ai_agreement['decided'] > 0): ?>
            <p class="review-muted ai-agreement">
                AI suggestions so far: administrators agreed with <?php echo (int) $ai_agreement['agreed']; ?>
                of <?php echo (int) $ai_agreement['decided']; ?> (recorded for the evaluation).
            </p>
        <?php endif; ?>

        <?php if (!$held_failed && !empty($held_messages)): ?>
            <p class="review-muted">
                <?php echo (int) $held_total; ?> waiting, oldest first. The receiver cannot see these messages.
                <?php if ($held_total > count($held_messages)): ?>
                    Showing the first <?php echo count($held_messages); ?>.
                <?php endif; ?>
            </p>

            <?php foreach ($held_messages as $held): ?>
                <?php $own_conversation = in_array($admin_id, array((int) $held['sender_id'], (int) $held['receiver_id']), true); ?>
                <article class="review-card">
                    <h3>Message #<?php echo (int) $held['id']; ?></h3>

                    <p>
                        <strong>From:</strong> <?php echo e($held['sender_name']); ?><br>
                        <strong>To:</strong> <?php echo e($held['receiver_name']); ?><br>
                        <strong>Sent:</strong> <?php echo e(date('d M Y, H:i', strtotime((string) $held['sent_at']))); ?> UTC
                    </p>

                    <p><strong>Why it was held:</strong></p>
                    <ul class="review-reasons">
                        <?php foreach (messages_reasons($held['review_reasons']) as $reason): ?>
                            <li><?php echo e($reason); ?></li>
                        <?php endforeach; ?>
                    </ul>

                    <?php $ai = $ai_checks[(int) $held['id']] ?? null; ?>
                    <?php if ($ai !== null && !$own_conversation): ?>
                        <!-- AI compliance check (ai/README.md): a suggestion only; the admin's decision is final. -->
                        <section class="ai-suggestion<?php echo $ai['status'] === 'ok' ? '' : ' ai-suggestion--failed'; ?>" aria-labelledby="aiSuggestion<?php echo (int) $held['id']; ?>">
                            <h4 id="aiSuggestion<?php echo (int) $held['id']; ?>">AI suggestion &ndash; the admin decides</h4>
                            <?php if ($ai['status'] === 'ok'): ?>
                                <p class="ai-suggestion__summary">
                                    Suggestion: <strong><?php echo $ai['decision'] === 'REVIEW' ? 'Review' : 'Allow'; ?></strong>
                                    &middot; risk: <?php echo e($ai['risk_level']); ?>
                                    &middot; model: <?php echo e($ai['model_name']); ?>
                                    &middot; <?php echo (int) $ai['latency_ms']; ?> ms
                                </p>
                            <?php endif; ?>
                            <ul class="ai-suggestion__reasons">
                                <?php foreach ($ai['reasons'] as $ai_reason): ?>
                                    <li><?php echo e(is_string($ai_reason) ? $ai_reason : ''); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if (!empty($ai['citations'])): ?>
                                <p class="ai-suggestion__label">Cited passages</p>
                                <ul class="ai-citations">
                                    <?php foreach ($ai['citations'] as $citation): ?>
                                        <li>
                                            <strong><?php echo e($citation['passage_id'] ?? ''); ?></strong>
                                            (<?php echo e($citation['country'] ?? ''); ?>, <?php echo e($citation['source'] ?? ''); ?>)
                                            <q><?php echo e($citation['quote'] ?? ''); ?></q>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if (!empty($ai['steps'])): ?>
                                <details class="ai-steps">
                                    <summary>How the AI reached this (<?php echo count($ai['steps']); ?> steps)</summary>
                                    <ol>
                                        <?php foreach ($ai['steps'] as $step): ?>
                                            <li>
                                                <span><strong>Thought:</strong> <?php echo e($step['thought'] ?? ''); ?></span>
                                                <span><strong>Action:</strong> <code><?php echo e($step['action'] ?? ''); ?></code></span>
                                                <span><strong>Observation:</strong> <?php echo e($step['observation'] ?? ''); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ol>
                                </details>
                            <?php endif; ?>
                            <p class="ai-suggestion__note">Your Approve or Reject below is the final decision. Whether it matches the AI is recorded for the evaluation.</p>
                        </section>
                    <?php endif; ?>

                    <?php if ($own_conversation): ?>
                        <div class="notice">You are part of this conversation, so another administrator must decide.</div>
                    <?php else: ?>
                        <?php if ((string) $held['message'] !== ''): ?>
                            <p class="review-message-text"><?php echo e($held['message']); ?></p>
                        <?php endif; ?>

                        <?php if ($held['file_id'] !== null): ?>
                            <div class="review-message-file">
                                <?php echo message_file_html($held['file_id'], $held['original_name'], $held['mime'], (int) $held['file_size'], $held['removed_at']); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="review_message.php" class="review-form">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="message_id" value="<?php echo (int) $held['id']; ?>">

                            <label for="messageNote<?php echo (int) $held['id']; ?>">Reason (optional, the sender sees it if you reject)</label>
                            <textarea id="messageNote<?php echo (int) $held['id']; ?>" name="note" rows="2" maxlength="500"></textarea>

                            <div class="review-buttons">
                                <button type="submit" name="decision" value="approve" class="review-button review-approve">Approve and deliver</button>
                                <button type="submit" name="decision" value="reject" class="review-button review-reject">Reject message</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($message_decisions)): ?>
            <h3>Latest message decisions</h3>
            <ul class="review-activity">
                <?php foreach ($message_decisions as $decision_row): ?>
                    <li>
                        Message #<?php echo (int) $decision_row['message_id']; ?>:
                        <strong><?php echo e(ucfirst($decision_row['decision'])); ?></strong>
                        by <?php echo e($decision_row['admin_name'] ?? 'the system'); ?>
                        on <?php echo e($decision_row['created_at']); ?> UTC.
                        <?php if (messages_reasons($decision_row['reasons']) !== array()): ?>
                            Held because: <?php echo e(implode('; ', messages_reasons($decision_row['reasons']))); ?>.
                        <?php endif; ?>
                        <?php if ((string) $decision_row['note'] !== ''): ?>
                            Note: <?php echo e($decision_row['note']); ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <h2 id="adminActivity">Recent Admin Activity</h2>

    <?php if ($activity_failed): ?>

        <p role="status">
            Admin activity is temporarily unavailable.
        </p>

    <?php elseif (empty($activity_log)): ?>

        <p>No admin decisions have been logged yet.</p>

    <?php else: ?>

        <ul class="review-activity">
            <?php foreach ($activity_log as $log): ?>
                <li>
                    <?php echo e($log['admin_name'] ?? 'Unavailable account'); ?>
                    marked
                    “<?php echo e($log['report_title'] ?? 'Unavailable report'); ?>”
                    as <strong><?php echo e($log['action']); ?></strong>
                    on <?php echo e($log['action_at']); ?>.
                </li>
            <?php endforeach; ?>
        </ul>

    <?php endif; ?>

    <section class="review-contact" id="contactMessages" aria-labelledby="contactMessagesHeading">
        <h2 id="contactMessagesHeading">Contact messages</h2>

        <?php if ($contact_failed): ?>
            <div class="notice notice--error" role="alert">
                Contact messages could not be loaded. Please try again later.
            </div>
        <?php elseif ($contact_total === 0): ?>
            <p class="review-muted">No messages have been sent through the Contact page yet.</p>
        <?php else: ?>
            <p class="review-muted">
                <?php echo (int) $contact_total; ?> message<?php echo $contact_total === 1 ? '' : 's'; ?>, newest first.
                <?php if ($contact_total > count($contact_messages)): ?>
                    Showing the latest <?php echo count($contact_messages); ?>.
                <?php endif; ?>
            </p>

            <div class="review-contact-list">
                <?php foreach ($contact_messages as $contact): ?>
                    <?php
                        $contact_subject = trim((string) $contact['subject']) !== ''
                            ? (string) $contact['subject']
                            : '(No subject)';
                        $contact_time = strtotime((string) $contact['created_at']);
                    ?>
                    <details class="review-contact-item">
                        <summary>
                            <span class="review-contact-subject"><?php echo e($contact_subject); ?></span>
                            <span class="review-contact-meta">
                                <?php echo e($contact['full_name']); ?>
                                · <time datetime="<?php echo e(date('c', $contact_time ?: time())); ?>"><?php echo e($contact_time ? date('d M Y, H:i', $contact_time) : ''); ?></time>
                            </span>
                        </summary>

                        <div class="review-contact-body">
                            <p>
                                <strong>From:</strong>
                                <?php echo e($contact['full_name']); ?>
                                (<a href="mailto:<?php echo e(rawurlencode((string) $contact['email'])); ?>"><?php echo e($contact['email']); ?></a>)
                            </p>
                            <p class="review-contact-text"><?php echo nl2br(e($contact['message'])); ?></p>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</div>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>