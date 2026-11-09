<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';

require_login();

$user_id = (int) $_SESSION['user_id'];
$max_document_size = 5 * 1024 * 1024;

/*
 * Expected project layout:
 *
 * DiSSENT-clean/
 *   private/report-documents/
 *   docs/
 *     Pages/reports.php
 *
 * The private folder must be outside the PHP server's document root.
 * It is created automatically when the first PDF is uploaded.
 */
$document_directory =
    dirname(__DIR__, 2) . '/private/report-documents';

/* Execute a prepared query and report failures consistently. */
$report_query = static function (
    $sql,
    $types = '',
    array $values = array()
) use ($conn) {
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new RuntimeException('Could not prepare database query.');
    }

    try {
        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$values);
        }

        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('Could not execute database query.');
        }

        return $stmt;
    } catch (Throwable $exception) {
        mysqli_stmt_close($stmt);
        throw $exception;
    }
};

/* Confirm admin permissions using the current database role. */
$is_admin = false;

if (function_exists('content_user_is_admin')) {
    try {
        $is_admin = content_user_is_admin();
    } catch (Throwable $exception) {
        error_log('DiSSENT: report admin permission check failed.');
    }
}

/* Validate that the storage folder is outside the public web folder. */
$private_directory = static function ($create = false) use (
    $document_directory
) {
    if ($create && !is_dir($document_directory)) {
        if (
            !mkdir($document_directory, 0700, true) &&
            !is_dir($document_directory)
        ) {
            throw new RuntimeException(
                'Could not create private document storage.'
            );
        }
    }

    $storage = realpath($document_directory);
    $web_root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');

    if ($storage === false || $web_root === false) {
        throw new RuntimeException(
            'Could not validate the private storage location.'
        );
    }

    $normalize = static function ($path) {
        $path = rtrim(str_replace('\\', '/', $path), '/');

        return DIRECTORY_SEPARATOR === '\\'
            ? strtolower($path)
            : $path;
    };

    $storage_check = $normalize($storage);
    $root_check = $normalize($web_root);

    if (
        $storage_check === $root_check ||
        strpos($storage_check, $root_check . '/') === 0
    ) {
        throw new RuntimeException(
            'Document storage must be outside the public web folder.'
        );
    }

    return $storage;
};

/*
 * Protected PDF download.
 * The browser receives a download, not a public storage URL.
 */
if (isset($_GET['document'])) {
    $document_input = $_GET['document'];

    $document_id = is_string($document_input)
        ? filter_var(
            $document_input,
            FILTER_VALIDATE_INT,
            array('options' => array('min_range' => 1))
        )
        : false;

    if (!$document_id) {
        http_response_code(400);
        exit('Invalid document request.');
    }

    try {
        $stmt = $report_query(
            "SELECT d.original_name, d.stored_name, d.file_size
             FROM report_documents d
             JOIN reports r ON r.id = d.report_id
             WHERE d.id = ?
               AND (r.user_id = ? OR ? = 1)
             LIMIT 1",
            'iii',
            array($document_id, $user_id, $is_admin ? 1 : 0)
        );

        $result = mysqli_stmt_get_result($stmt);
        $document = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$document) {
            http_response_code(404);
            exit('Document not found or access is not permitted.');
        }

        if (
            !preg_match(
                '/\A[a-f0-9]{48}\.pdf\z/',
                $document['stored_name']
            )
        ) {
            throw new RuntimeException('Invalid stored document name.');
        }

        $directory = $private_directory();
        $path = $directory . DIRECTORY_SEPARATOR
            . $document['stored_name'];

        if (
            is_link($path) ||
            !is_file($path) ||
            !is_readable($path)
        ) {
            http_response_code(404);
            exit('The document file is unavailable.');
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Could not open document.');
        }

        session_write_close();

        header('Content-Type: application/pdf');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        header(
            'Content-Disposition: attachment; filename="report-document.pdf"; '
            . "filename*=UTF-8''"
            . rawurlencode($document['original_name'])
        );
        header('Content-Length: ' . filesize($path));

        fpassthru($handle);
        fclose($handle);
        exit();
    } catch (Throwable $exception) {
        error_log(
            'DiSSENT document download: ' . $exception->getMessage()
        );
        http_response_code(500);
        exit('The document could not be downloaded. Please try again later.');
    }
}

$errors = array();
$success = $_SESSION['report_success'] ?? '';
unset($_SESSION['report_success']);

$form = array(
    'title' => '',
    'category' => '',
    'description' => '',
    'location' => '',
    'evidence' => ''
);

/*
 * An owner or admin can open a single report:
 * reports.php?review=3
 */
$review_id = null;

if (isset($_GET['review'])) {
    $review_input = $_GET['review'];

    $review_id = is_string($review_input)
        ? filter_var(
            $review_input,
            FILTER_VALIDATE_INT,
            array('options' => array('min_range' => 1))
        )
        : false;

    if (!$review_id) {
        http_response_code(400);
        exit('Invalid report ID.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /*
     * PHP may discard the entire request when post_max_size is exceeded.
     */
    if (empty($_POST) && empty($_FILES)) {
        $errors[] =
            'The submission was too large or could not be received. '
            . 'Choose a smaller PDF and try again.';
    } else {
        verify_csrf();

        foreach ($form as $field => $value) {
            $input = $_POST[$field] ?? '';

            $form[$field] = is_string($input)
                ? trim($input)
                : '';
        }

        if ($form['title'] === '') {
            $errors[] = 'Please enter a report title.';
        }

        if ($form['category'] === '') {
            $errors[] = 'Please enter a category.';
        }

        if ($form['description'] === '') {
            $errors[] = 'Please describe your report.';
        }

        $limits = array(
            'title' => 255,
            'category' => 100,
            'location' => 255,
            'description' => 60000
        );

        foreach ($limits as $field => $limit) {
            if (strlen($form[$field]) > $limit) {
                $errors[] =
                    'The ' . $field . ' is too long. Please shorten it.';
            }
        }

        if ($form['evidence'] !== '') {
            $scheme = strtolower(
                (string) parse_url($form['evidence'], PHP_URL_SCHEME)
            );

            if (
                strlen($form['evidence']) > 255 ||
                !filter_var($form['evidence'], FILTER_VALIDATE_URL) ||
                !in_array($scheme, array('http', 'https'), true)
            ) {
                $errors[] =
                    'Enter a valid HTTP or HTTPS evidence link '
                    . 'up to 255 characters.';
            }
        }

        $upload = $_FILES['report_document'] ?? null;
        $has_document = false;
        $original_name = '';
        $document_size = 0;

        if ($upload !== null) {
            if (
                !is_array($upload) ||
                !isset(
                    $upload['error'],
                    $upload['name'],
                    $upload['tmp_name']
                ) ||
                !is_int($upload['error']) ||
                !is_string($upload['name']) ||
                !is_string($upload['tmp_name'])
            ) {
                $errors[] = 'The uploaded document is invalid.';
            } elseif ($upload['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($upload['error'] !== UPLOAD_ERR_OK) {
                    $errors[] =
                        'The PDF could not be uploaded. It may exceed '
                        . 'the server upload limit. Try a smaller file.';
                } elseif (!is_uploaded_file($upload['tmp_name'])) {
                    $errors[] = 'The uploaded document could not be validated.';
                } else {
                    $document_size = filesize($upload['tmp_name']);

                    if (
                        $document_size === false ||
                        $document_size < 1 ||
                        $document_size > $max_document_size
                    ) {
                        $errors[] =
                            'Please choose a non-empty PDF no larger than 5 MB.';
                    } else {
                        $original_name = basename(
                            str_replace('\\', '/', $upload['name'])
                        );

                        $original_name = preg_replace(
                            '/[\x00-\x1F\x7F]/',
                            '',
                            $original_name
                        );

                        if (
                            $original_name === '' ||
                            strlen($original_name) > 255 ||
                            preg_match('//u', $original_name) !== 1 ||
                            strtolower(
                                pathinfo($original_name, PATHINFO_EXTENSION)
                            ) !== 'pdf'
                        ) {
                            $errors[] =
                                'Choose a PDF with a shorter, valid filename.';
                        } elseif (!class_exists('finfo')) {
                            $errors[] =
                                'Document validation is unavailable '
                                . 'on the server.';
                        } else {
                            try {
                                $finfo = new finfo(FILEINFO_MIME_TYPE);
                                $mime = $finfo->file($upload['tmp_name']);
                                $signature = file_get_contents(
                                    $upload['tmp_name'],
                                    false,
                                    null,
                                    0,
                                    5
                                );

                                if (
                                    $mime !== 'application/pdf' ||
                                    $signature !== '%PDF-'
                                ) {
                                    $errors[] =
                                        'The selected file is not recognised '
                                        . 'as a PDF.';
                                } else {
                                    $has_document = true;
                                }
                            } catch (Throwable $exception) {
                                $errors[] =
                                    'The document could not be validated.';
                            }
                        }
                    }
                }
            }
        }

        if (empty($errors)) {
            $stored_path = null;
            $transaction_started = false;

            try {
                /*
                 * Validate storage before saving any database records.
                 */
                if ($has_document) {
                    $directory = $private_directory(true);
                    $stored_name = bin2hex(random_bytes(24)) . '.pdf';
                    $stored_path =
                        $directory . DIRECTORY_SEPARATOR . $stored_name;

                    if (
                        !move_uploaded_file(
                            $upload['tmp_name'],
                            $stored_path
                        )
                    ) {
                        throw new RuntimeException(
                            'Could not save uploaded document.'
                        );
                    }
                }

                if (!mysqli_begin_transaction($conn)) {
                    throw new RuntimeException(
                        'Could not start report transaction.'
                    );
                }

                $transaction_started = true;

                $stmt = $report_query(
                    "INSERT INTO reports
                        (user_id, title, category, description,
                         location, evidence, status)
                     VALUES (?, ?, ?, ?, ?, ?, 'Pending')",
                    'isssss',
                    array(
                        $user_id,
                        $form['title'],
                        $form['category'],
                        $form['description'],
                        $form['location'],
                        $form['evidence']
                    )
                );

                $report_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                if ($has_document) {
                    $stmt = $report_query(
                        "INSERT INTO report_documents
                            (report_id, original_name, stored_name, file_size)
                         VALUES (?, ?, ?, ?)",
                        'issi',
                        array(
                            $report_id,
                            $original_name,
                            $stored_name,
                            $document_size
                        )
                    );

                    mysqli_stmt_close($stmt);
                }

                if (!mysqli_commit($conn)) {
                    throw new RuntimeException('Could not commit report.');
                }

                $transaction_started = false;

                $_SESSION['report_success'] =
                    'Report #' . $report_id
                    . ' has been submitted and is pending review.';

                header('Location: reports.php', true, 303);
                exit();
            } catch (Throwable $exception) {
                if ($transaction_started) {
                    try {
                        mysqli_rollback($conn);
                    } catch (Throwable $rollback_exception) {
                        error_log('DiSSENT: report rollback failed.');
                    }
                }

                /*
                 * Preserve a moved file on an uncertain database failure.
                 * It remains private and can be reconciled later.
                 */
                error_log(
                    'DiSSENT report submission: '
                    . $exception->getMessage()
                );

                $errors[] =
                    'We could not confirm that your report was saved. '
                    . 'Check My reports before submitting again.';
            }
        }
    }
}

$reports = array();
$reports_load_failed = false;

try {
    $sql =
        "SELECT r.id, r.title, r.category, r.description,
                r.location, r.evidence, r.status, r.created_at,
                r.rejection_reason,
                d.id AS document_id,
                d.original_name AS document_name,
                d.file_size AS document_size
         FROM reports r
         LEFT JOIN report_documents d ON d.report_id = r.id";

    if ($review_id !== null) {
        $sql .=
            " WHERE r.id = ?
              AND (r.user_id = ? OR ? = 1)";

        $types = 'iii';
        $values = array($review_id, $user_id, $is_admin ? 1 : 0);
    } else {
        $sql .= " WHERE r.user_id = ?";
        $types = 'i';
        $values = array($user_id);
    }

    $sql .= " ORDER BY r.created_at DESC, r.id DESC";

    $stmt = $report_query($sql, $types, $values);
    $result = mysqli_stmt_get_result($stmt);

    if (!$result) {
        throw new RuntimeException('Report results unavailable.');
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $reports[] = $row;
    }

    mysqli_stmt_close($stmt);
} catch (Throwable $exception) {
    error_log('DiSSENT report listing: ' . $exception->getMessage());
    $reports_load_failed = true;
}

$status_labels = array(
    'pending' => 'Pending review',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'verified' => 'Legacy verification status'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reports - DiSSENT</title>

    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/reports.css">
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<section class="reports-hero">
    <h1>Reports Centre</h1>
    <p>Submit a report, attach a document and follow its review status.</p>
</section>

<main class="reports-page">

    <?php if (is_string($success) && $success !== ''): ?>
        <div class="report-notice report-notice-success" role="status">
            <?php echo e($success); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="report-notice report-notice-error" role="alert">
            <strong>Please check your submission:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo e($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <p>If you selected a PDF, select it again before retrying.</p>
        </div>
    <?php endif; ?>

    <?php if ($review_id === null): ?>
        <section class="reports-section" aria-labelledby="submitHeading">
            <div class="report-card">
                <h2 id="submitHeading">Submit a report</h2>
                <p>Your submission will be marked as pending review.</p>

                <form
                    method="POST"
                    action="reports.php"
                    enctype="multipart/form-data"
                    class="report-form"
                >
                    <?php echo csrf_field(); ?>

                    <div class="report-field">
                        <label for="reportTitle">Report title *</label>
                        <input
                            id="reportTitle"
                            type="text"
                            name="title"
                            maxlength="255"
                            value="<?php echo e($form['title']); ?>"
                            required
                        >
                    </div>

                    <div class="report-form-row">
                        <div class="report-field">
                            <label for="reportCategory">Category *</label>
                            <input
                                id="reportCategory"
                                type="text"
                                name="category"
                                maxlength="100"
                                placeholder="For example, human rights"
                                value="<?php echo e($form['category']); ?>"
                                required
                            >
                        </div>

                        <div class="report-field">
                            <label for="reportLocation">
                                Location (optional)
                            </label>
                            <input
                                id="reportLocation"
                                type="text"
                                name="location"
                                maxlength="255"
                                aria-describedby="locationHelp"
                                value="<?php echo e($form['location']); ?>"
                            >
                            <small id="locationHelp" class="report-help">
                                Use a general area if an exact location
                                could identify a confidential source.
                            </small>
                        </div>
                    </div>

                    <div class="report-field">
                        <label for="reportDescription">
                            Report description *
                        </label>
                        <textarea
                            id="reportDescription"
                            name="description"
                            rows="8"
                            required
                        ><?php echo e($form['description']); ?></textarea>
                    </div>

                    <div class="report-field">
                        <label for="reportEvidence">
                            Evidence link (optional)
                        </label>
                        <input
                            id="reportEvidence"
                            type="url"
                            name="evidence"
                            maxlength="255"
                            placeholder="https://example.org/source"
                            aria-describedby="evidenceHelp"
                            value="<?php echo e($form['evidence']); ?>"
                        >
                        <small id="evidenceHelp" class="report-help">
                            Add a supporting web link without passwords
                            or private access tokens.
                        </small>
                    </div>

                    <div class="report-field report-upload">
                        <label for="reportDocument">
                            Supporting document (optional)
                        </label>
                        <input
                            id="reportDocument"
                            type="file"
                            name="report_document"
                            accept=".pdf,application/pdf"
                            aria-describedby="documentHelp"
                        >
                        <small id="documentHelp" class="report-help">
                            One PDF, up to 5 MB. Only you and administrators
                            can download it through this page.
                            Approval does not make the document public.
                        </small>
                    </div>

                    <button type="submit" class="report-submit">
                        Submit for review
                    </button>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <section class="reports-section" aria-labelledby="myReportsHeading">
        <h2 id="myReportsHeading">
            <?php echo $review_id !== null ? 'Report details' : 'My reports'; ?>
        </h2>

        <p>
            <?php echo $review_id !== null
                ? 'Review the report and its supporting document.'
                : 'Reports submitted through your account.'; ?>
        </p>

        <?php if ($review_id !== null): ?>
            <p class="report-navigation">
                <a href="<?php echo $is_admin ? 'admin.php' : 'reports.php'; ?>">
                    <?php echo $is_admin
                        ? 'Back to admin review'
                        : 'Back to my reports'; ?>
                </a>
            </p>
        <?php endif; ?>

        <div class="reports-grid">
            <?php if ($reports_load_failed): ?>
                <div class="report-card report-empty" role="alert">
                    <p>
                        We could not load your reports.
                        Check that the document table has been created.
                    </p>
                </div>

            <?php elseif (empty($reports)): ?>
                <div class="report-card report-empty">
                    <p>
                        <?php echo $review_id !== null
                            ? 'Report not found or access is not permitted.'
                            : 'No reports yet. Submit your first report above.'; ?>
                    </p>
                </div>

            <?php else: ?>
                <?php foreach ($reports as $row): ?>
                    <?php
                    $status_key = strtolower(trim((string) $row['status']));
                    $known_status = isset($status_labels[$status_key]);
                    $status_label = $known_status
                        ? $status_labels[$status_key]
                        : 'Status unavailable';
                    $status_class = $known_status
                        ? 'report-status-' . $status_key
                        : '';

                    $evidence = trim((string) ($row['evidence'] ?? ''));
                    $scheme = strtolower(
                        (string) parse_url($evidence, PHP_URL_SCHEME)
                    );
                    $evidence_is_url =
                        filter_var($evidence, FILTER_VALIDATE_URL) !== false &&
                        in_array($scheme, array('http', 'https'), true);
                    ?>

                    <article class="report-card">
                        <div class="report-card-heading">
                            <h3><?php echo e($row['title']); ?></h3>
                            <span class="report-status <?php echo e($status_class); ?>">
                                <?php echo e($status_label); ?>
                            </span>
                        </div>

                        <p><strong>Report ID:</strong> <?php echo (int) $row['id']; ?></p>

                        <p class="report-description"><?php
                            echo e($row['description']);
                        ?></p>

                        <dl class="report-details">
                            <dt>Category</dt>
                            <dd><?php echo e($row['category']); ?></dd>

                            <dt>Location</dt>
                            <dd><?php echo e($row['location'] ?: 'Not provided'); ?></dd>

                            <dt>Supporting evidence link</dt>
                            <dd>
                                <?php if ($evidence_is_url): ?>
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
                            </dd>

                            <dt>Supporting document</dt>
                            <dd>
                                <?php if ($row['document_id'] !== null): ?>
                                    <a
                                        class="report-document-link"
                                        href="reports.php?document=<?php echo (int) $row['document_id']; ?>"
                                    >
                                        Download PDF:
                                        <?php echo e($row['document_name']); ?>
                                    </a>
                                    <small class="report-help">
                                        <?php echo e(number_format(
                                            (int) $row['document_size'] / 1024,
                                            1
                                        )); ?> KB
                                    </small>
                                <?php else: ?>
                                    No document uploaded.
                                <?php endif; ?>
                            </dd>

                            <?php if (
                                $status_key === 'rejected' &&
                                !empty($row['rejection_reason'])
                            ): ?>
                                <dt>Reason for rejection</dt>
                                <dd class="report-description"><?php
                                    echo e($row['rejection_reason']);
                                ?></dd>
                            <?php endif; ?>

                            <dt>Submitted</dt>
                            <dd><?php echo e($row['created_at']); ?></dd>
                        </dl>

                        <?php if ($status_key === 'approved'): ?>
                            <p class="report-navigation">
                                <a href="verification.php?report_id=<?php echo (int) $row['id']; ?>">
                                    View verification record
                                </a>
                            </p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>