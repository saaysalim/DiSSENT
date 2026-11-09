<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';

require_admin();

$admin_id = (int) $_SESSION['user_id'];
$errors = array();
$reason_input = '';
$submitted_id = 0;

$notice = $_SESSION['admin_report_notice'] ?? '';
$notice = is_string($notice) ? $notice : '';
unset($_SESSION['admin_report_notice']);

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

    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>

<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="review-page">

    <h1>Report Review</h1>

    <p>
        Read submissions and decide whether they are suitable for publication.
        Publication approval and blockchain recording are separate.
    </p>

    <?php if ($notice !== ''): ?>
        <div class="review-notice" role="status">
            <?php echo e($notice); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="review-notice review-error" role="alert">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo e($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($load_failed): ?>

        <div class="review-notice review-error" role="alert">
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
            <div class="review-notice">
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

        <h2>Submitted Reports</h2>

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
                    <?php echo e($label); ?><br>

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

    <h2>Recent Admin Activity</h2>

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

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>