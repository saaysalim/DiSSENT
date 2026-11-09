<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';

/*
 * This page separates:
 * 1. The report's editorial review status.
 * 2. Availability of its verification-service record.
 * 3. The service's chain-integrity result.
 *
 * It does not independently recalculate report or block hashes.
 */

$verification_escape = static function ($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};

$verification_date = static function ($value) {
    if (!is_string($value) || trim($value) === '') {
        return 'Not available';
    }

    $timestamp = strtotime($value);

    return $timestamp === false
        ? 'Not available'
        : date('d M Y, g:i a', $timestamp);
};

/*
 * Support the field names returned by the existing PHP helpers
 * and the Java verification service.
 */
$verification_field = static function (array $record, array $names) {
    foreach ($names as $name) {
        if (
            isset($record[$name]) &&
            is_scalar($record[$name]) &&
            trim((string) $record[$name]) !== ''
        ) {
            return trim((string) $record[$name]);
        }
    }

    return '';
};

$search = '';
$input_error = '';
$search_error = '';
$found_report = null;

$ledger_available = false;
$data_hash = '';
$previous_hash = '';
$block_hash = '';

$stats = null;
$stats_error = false;

$history = array();
$history_error = false;

/* Read and validate search input. */
if (isset($_GET['q'])) {
    if (is_string($_GET['q'])) {
        $search = trim($_GET['q']);
    } else {
        $input_error = 'Please enter a valid search.';
    }
}

/* Support links such as verification.php?report_id=12. */
if (
    $input_error === '' &&
    $search === '' &&
    isset($_GET['report_id'])
) {
    $report_id_input = $_GET['report_id'];

    $report_id = is_string($report_id_input)
        ? filter_var(
            $report_id_input,
            FILTER_VALIDATE_INT,
            array('options' => array('min_range' => 1))
        )
        : false;

    if ($report_id === false) {
        $input_error = 'Please enter a valid report ID.';
    } else {
        $search = (string) $report_id;
    }
}

$search_length = function_exists('mb_strlen')
    ? mb_strlen($search, 'UTF-8')
    : strlen($search);

if ($search_length > 255) {
    $input_error = 'Please keep your search within 255 characters.';
}

/* Find a report that the shared helper permits this visitor to see. */
if ($input_error === '' && $search !== '') {
    try {
        $candidate = find_verified_report($search);

        if (is_array($candidate) && !empty($candidate['id'])) {
            $found_report = $candidate;
        }
    } catch (Throwable $exception) {
        error_log('DiSSENT: verification report lookup failed.');
        $search_error = 'Report search is temporarily unavailable. Please try again.';
    }
}

/*
 * Retrieve the current service record.
 * Never substitute a cached hash when this request fails.
 */
if ($found_report !== null) {
    try {
        $ledger_record = bc_get_verification($found_report['id']);

        if (is_array($ledger_record)) {
            $data_hash = $verification_field(
                $ledger_record,
                array('DataHash', 'dataHash', 'data_hash')
            );

            $previous_hash = $verification_field(
                $ledger_record,
                array('PrevHash', 'prevHash', 'prev_hash')
            );

            $block_hash = $verification_field(
                $ledger_record,
                array('BlockHash', 'blockHash', 'block_hash')
            );

            $ledger_available = $block_hash !== '';
        }
    } catch (Throwable $exception) {
        error_log('DiSSENT: verification service lookup failed.');
    }
}

/* Load summary information independently of the report lookup. */
try {
    $result = get_verification_stats();

    if (!is_array($result)) {
        throw new RuntimeException('Invalid verification statistics.');
    }

    $stats = $result;
} catch (Throwable $exception) {
    error_log('DiSSENT: verification statistics unavailable.');
    $stats_error = true;
}

/* Load locally recorded verification history. */
try {
    $result = get_verification_history(10);

    if (!is_array($result)) {
        throw new RuntimeException('Invalid verification history.');
    }

    $history = array_values(
        array_filter($result, 'is_array')
    );
} catch (Throwable $exception) {
    error_log('DiSSENT: verification history unavailable.');
    $history_error = true;
}

/* Missing statistics are unavailable, not zero. */
$verification_count = static function ($values, $key) {
    if (
        !is_array($values) ||
        !isset($values[$key]) ||
        !is_numeric($values[$key])
    ) {
        return '—';
    }

    return number_format(max(0, (int) $values[$key]));
};

$chain_intact = is_array($stats)
    ? ($stats['chain_intact'] ?? null)
    : null;

if ($chain_intact === true) {
    $chain_icon = '✓';
    $chain_label = 'Check passed';
} elseif ($chain_intact === false) {
    $chain_icon = '!';
    $chain_label = 'Check failed';
} else {
    $chain_icon = '?';
    $chain_label = 'Unavailable';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Report Verification - DiSSENT</title>

    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/verification.css">
</head>

<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="verification-page">

    <section class="verification-hero">
        <h1>Report Verification</h1>

        <p>
            Find a reviewed report, inspect its recorded hashes,
            and check the verification service’s chain-integrity result.
        </p>
    </section>

    <section class="verification-search" aria-labelledby="verificationSearchHeading">

        <h2 id="verificationSearchHeading">Find a Report</h2>

        <p>
            Search by report ID, title, or journalist name.
        </p>

        <form
            method="GET"
            action="verification.php"
            class="verification-form"
            role="search"
            aria-label="Search report verification records"
        >
            <input
                type="search"
                name="q"
                aria-label="Report ID, title, or journalist name"
                placeholder="Report ID, title, or journalist name"
                maxlength="255"
                value="<?php echo $verification_escape($search); ?>"
                required
            >

            <button type="submit">
                Find Report
            </button>
        </form>

    </section>

    <?php if ($input_error !== '' || $search_error !== ''): ?>

        <section class="blockchain-section" aria-label="Search notification">
            <div class="blockchain-card">
                <p role="alert">
                    <?php
                    echo $verification_escape(
                        $input_error !== '' ? $input_error : $search_error
                    );
                    ?>
                </p>
            </div>
        </section>

    <?php elseif ($search !== '' && $found_report === null): ?>

        <section class="blockchain-section" aria-label="Search result">
            <div class="blockchain-card">
                <h2>No Matching Published Report</h2>

                <p>
                    No report available for public verification matched
                    “<?php echo $verification_escape($search); ?>”.
                    Check the report ID or try another search.
                </p>

                <p>
                    Reports awaiting review or restricted from public access
                    may not appear here.
                </p>
            </div>
        </section>

    <?php elseif ($found_report !== null): ?>

        <section class="blockchain-section" aria-labelledby="reportResultHeading">

            <div class="blockchain-card">

                <h2 id="reportResultHeading">
                    <?php
                    echo $verification_escape(
                        $found_report['title'] ?? 'Report'
                    );
                    ?>
                </h2>

                <p>
                    <strong>Report ID:</strong>
                    <?php echo (int) $found_report['id']; ?>
                </p>

                <p>
                    <strong>Submitted by:</strong>
                    <?php
                    echo $verification_escape(
                        $found_report['fullname'] ?? 'Not available'
                    );
                    ?>
                </p>

                <p>
                    <strong>Category:</strong>
                    <?php
                    echo $verification_escape(
                        $found_report['category'] ?? 'Not specified'
                    );
                    ?>
                </p>

                <p>
                    <strong>Editorial status:</strong>
                    <?php
                    echo $verification_escape(
                        $found_report['status'] ?? 'Not available'
                    );
                    ?>
                </p>

                <?php if ($ledger_available): ?>

                    <p>
                        <span class="verified-badge">
                            Verification record retrieved
                        </span>
                    </p>

                    <p>
                        The hashes below were returned by the verification
                        service for this report.
                    </p>

                    <p><strong>Data Hash</strong></p>
                    <div class="hash-box"><?php
                        echo $verification_escape(
                            $data_hash !== '' ? $data_hash : 'Not provided by the service.'
                        );
                    ?></div>

                    <p><strong>Previous Block Hash</strong></p>
                    <div class="hash-box"><?php
                        echo $verification_escape(
                            $previous_hash !== ''
                                ? $previous_hash
                                : 'Not provided by the service.'
                        );
                    ?></div>

                    <p><strong>Block Hash</strong></p>
                    <div class="hash-box"><?php
                        echo $verification_escape($block_hash);
                    ?></div>

                    <p>
                        This page displays the service’s record.
                        It does not independently compare the report’s
                        current text or attachments against these hashes.
                    </p>

                <?php else: ?>

                    <h3>Verification Record Unavailable</h3>

                    <p role="status">
                        The report was found, but its verification record
                        could not be retrieved. The service may be unavailable,
                        or the report may not yet have a recorded entry.
                    </p>

                    <p>
                        This does not establish that the report is invalid
                        or that the chain has failed.
                    </p>

                    <a
                        href="verification.php?report_id=<?php echo (int) $found_report['id']; ?>"
                        class="btn-secondary"
                    >
                        Try Again
                    </a>

                <?php endif; ?>

            </div>

        </section>

    <?php endif; ?>

    <section class="verification-stats" aria-label="Verification overview">

        <div class="verification-card">
            <h2><?php echo $verification_count($stats, 'total_reports'); ?></h2>
            <p>Total Reports</p>
        </div>

        <div class="verification-card">
            <h2><?php echo $verification_count($stats, 'verified_count'); ?></h2>
            <p>Locally Recorded Verifications</p>
        </div>

        <div class="verification-card">
            <h2><?php echo $verification_count($stats, 'pending_count'); ?></h2>
            <p>Pending Review</p>
        </div>

        <div class="verification-card">
            <h2 aria-hidden="true">
                <?php echo $verification_escape($chain_icon); ?>
            </h2>
            <p>
                Chain Integrity:
                <?php echo $verification_escape($chain_label); ?>
            </p>
        </div>

    </section>

    <section class="blockchain-section" aria-labelledby="verificationMeaningHeading">
        <div class="blockchain-card">

            <h2 id="verificationMeaningHeading">What Does Verification Mean?</h2>

            <p>
                An administrator reviews a report before approving publication.
                A recorded hash can support checks for changes to the content
                that was originally recorded.
            </p>

            <p>
                Chain integrity is a separate check reported by the verification
                service. It does not establish whether a report’s claims are true
                or replace editorial review.
            </p>

            <?php if ($stats_error): ?>
                <p role="status">
                    Summary information is temporarily unavailable.
                    Missing figures are shown as a dash.
                </p>
            <?php endif; ?>

        </div>
    </section>

    <section class="verification-history" aria-labelledby="verificationHistoryHeading">

        <h2 id="verificationHistoryHeading">Recent Recorded Verifications</h2>

        <?php if ($history_error): ?>

            <p role="status">
                Verification history is temporarily unavailable.
                Please try again later.
            </p>

        <?php elseif (empty($history)): ?>

            <p>No verification records are available to display yet.</p>

        <?php else: ?>

            <p>
                This history comes from the website’s local records.
                Choose “View Record” to request the current service record.
            </p>

            <table>
                <caption>Recent report verification records</caption>

                <thead>
                    <tr>
                        <th scope="col">Title</th>
                        <th scope="col">Journalist</th>
                        <th scope="col">Category</th>
                        <th scope="col">Local Record Date</th>
                        <th scope="col">Stored Hash Reference</th>
                        <th scope="col">Details</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($history as $item): ?>
                        <?php
                        $history_hash = $verification_field(
                            $item,
                            array('block_hash', 'BlockHash', 'blockHash')
                        );

                        $history_id = (int) ($item['id'] ?? 0);
                        ?>

                        <tr>
                            <td data-label="Title">
                                <?php
                                echo $verification_escape(
                                    $item['title'] ?? 'Untitled report'
                                );
                                ?>
                            </td>

                            <td data-label="Journalist">
                                <?php
                                echo $verification_escape(
                                    $item['fullname'] ?? 'Not available'
                                );
                                ?>
                            </td>

                            <td data-label="Category">
                                <?php
                                echo $verification_escape(
                                    $item['category'] ?? 'Not specified'
                                );
                                ?>
                            </td>

                            <td data-label="Local Record Date">
                                <?php
                                echo $verification_escape(
                                    $verification_date($item['verified_at'] ?? null)
                                );
                                ?>
                            </td>

                            <td data-label="Stored Hash Reference">
                                <?php if ($history_hash !== ''): ?>
                                    <code><?php
                                        echo $verification_escape(
                                            substr($history_hash, 0, 16)
                                        );
                                        echo strlen($history_hash) > 16 ? '…' : '';
                                    ?></code>
                                <?php else: ?>
                                    Not available
                                <?php endif; ?>
                            </td>

                            <td data-label="Details">
                                <?php if ($history_id > 0): ?>
                                    <a href="verification.php?report_id=<?php echo $history_id; ?>">
                                        View Record
                                    </a>
                                <?php else: ?>
                                    Not available
                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>

    </section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>