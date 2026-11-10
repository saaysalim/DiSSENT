<?php
session_start();

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';

$content = platform_content();

$search_input = $_GET['q'] ?? '';
$search = is_string($search_input) ? trim($search_input) : '';

$search_error = '';
$publications_error = '';
$updates_error = '';

$publications = array();
$updates = array();

if (strlen($search) > 255) {
    $search_error = 'Please use a shorter search, up to 255 characters.';
}

if ($search_error === '') {
    try {
        // Media Centre is the publication view for everyone.
        // Unpublished reports remain in the admin review workspace.
        $publications = get_publications($search, false);
    } catch (Throwable $exception) {
        error_log('DiSSENT: Media Centre publications could not be loaded.');

        $publications_error =
            'Publications could not be loaded. Please try again shortly.';
    }
}

try {
    $updates = get_updates(6);
} catch (Throwable $exception) {
    error_log('DiSSENT: publication updates could not be loaded.');

    $updates_error =
        'Recent publication updates are temporarily unavailable.';
}

$features = $content['features'] ?? array();
$workflow = $content['workflow'] ?? array();
$partners = $content['partners'] ?? array();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Media Centre - DiSSENT</title>

   <link rel="stylesheet" href="../assets/css/base.css">
<link rel="stylesheet" href="../assets/css/home.css">
<link rel="stylesheet" href="../assets/css/media_center.css">
</head>

<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="media-page">

    <section class="media-hero" aria-labelledby="mediaHeading">
        <h1 id="mediaHeading">Media Centre</h1>

        <p>
            Explore approved reports and connect with
            cross-border media teams.
        </p>

        <div class="media-hero-actions">
            <a href="#publications" class="btn-secondary">
                Browse publications
            </a>

            <a href="reports.php" class="btn-secondary">
                Submit a report
            </a>
        </div>
    </section>

    <section
        class="media-search-section"
        aria-label="Search publications"
    >
        <form
            method="GET"
            action="media_center.php#publications"
            class="media-search-box"
            role="search"
        >
            <label for="publicationSearch">
                Search publications
            </label>

            <input
                id="publicationSearch"
                type="search"
                name="q"
                maxlength="255"
                placeholder="Search titles, authors or topics..."
                value="<?php echo e($search); ?>"
            >

            <button type="submit">Search</button>

            <?php if ($search !== ''): ?>
                <a href="media_center.php#publications" class="search-clear">
                    Clear search
                </a>
            <?php endif; ?>
        </form>

        <?php if ($search_error !== ''): ?>
            <p class="media-notice media-notice-error" role="alert">
                <?php echo e($search_error); ?>
            </p>
        <?php endif; ?>
    </section>

    <section
        id="publications"
        class="featured-section"
        aria-labelledby="publicationsHeading"
    >
        <h2 id="publicationsHeading">
            Publications
            <?php if ($search !== '' && $search_error === ''): ?>
                — results for “<?php echo e($search); ?>”
            <?php endif; ?>
        </h2>

        <p class="media-section-intro">
            Reports approved for publication through the review process.
            Approval and technical integrity checks are separate.
        </p>

        <?php if ($publications_error !== ''): ?>

            <div class="media-notice media-notice-error" role="alert">
                <p><?php echo e($publications_error); ?></p>
            </div>

        <?php elseif ($search_error !== ''): ?>

            <p>Shorten your search and try again.</p>

        <?php elseif (empty($publications)): ?>

            <div class="media-empty">
                <?php if ($search !== ''): ?>
                    <h3>No matching publications</h3>
                    <p>Try a different title, author or topic.</p>

                    <a
                        href="media_center.php#publications"
                        class="btn-secondary"
                    >
                        Browse all publications
                    </a>
                <?php else: ?>
                    <h3>No publications yet</h3>
                    <p>
                        Approved reports will appear here.
                        Reports awaiting review are not publicly listed.
                    </p>

                    <a href="reports.php" class="btn-secondary">
                        Submit a report
                    </a>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <p class="publication-count">
                <?php echo count($publications); ?>
                publication<?php echo count($publications) === 1 ? '' : 's'; ?>
                found.
            </p>

            <div class="featured-grid">
                <?php foreach ($publications as $pub): ?>
                    <?php
                    $report_id = (int) $pub['id'];

                    $description = (string) ($pub['description'] ?? '');
                    $block_hash = trim(
                        (string) ($pub['block_hash'] ?? '')
                    );

                    $submitted_at = strtotime(
                        (string) ($pub['created_at'] ?? '')
                    );
                    ?>

                    <article class="featured-card">
                        <span class="status-badge status-approved">
                            Approved for publication
                        </span>

                        <h3><?php echo e($pub['title']); ?></h3>

                        <p class="pub-meta">
                            By <?php echo e($pub['fullname']); ?>

                            <?php if (!empty($pub['organisation'])): ?>
                                &middot;
                                <?php echo e($pub['organisation']); ?>
                            <?php endif; ?>

                            <?php if (!empty($pub['category'])): ?>
                                &middot;
                                <?php echo e($pub['category']); ?>
                            <?php endif; ?>
                        </p>

                        <?php if ($submitted_at !== false): ?>
                            <p class="pub-date">
                                Submitted
                                <time datetime="<?php
                                    echo e(date('Y-m-d', $submitted_at));
                                ?>">
                                    <?php
                                    echo e(date('d M Y', $submitted_at));
                                    ?>
                                </time>
                            </p>
                        <?php endif; ?>

                        <p class="pub-excerpt">
                            <?php echo e(summarize($description)); ?>
                        </p>

                        <details class="publication-details">
                            <summary>Read report</summary>

                            <div class="publication-body">
                                <?php
                                echo nl2br(e($description), false);
                                ?>
                            </div>

                            <?php if (!empty($pub['location'])): ?>
                                <p class="pub-location">
                                    <strong>Reported location:</strong>
                                    <?php echo e($pub['location']); ?>
                                </p>
                            <?php endif; ?>
                        </details>

                        <?php if ($block_hash !== ''): ?>
                            <div class="publication-record">
                                <p class="pub-hash">
                                    <strong>Recording reference:</strong>
                                    <code><?php
                                        echo e(substr($block_hash, 0, 16));
                                    ?>&hellip;</code>
                                </p>

                                <p class="pub-record-note">
                                    A stored recording reference is available.
                                    It does not by itself establish the report’s
                                    factual accuracy or a current integrity result.
                                </p>
                            </div>
                        <?php else: ?>
                            <p class="pub-record-note">
                                No recording reference is currently available.
                            </p>
                        <?php endif; ?>
                    </article>

                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </section>

    <section
        class="news-section"
        aria-labelledby="updatesHeading"
    >
        <h2 id="updatesHeading">Recent publication updates</h2>

        <?php if ($updates_error !== ''): ?>

            <p class="media-notice" role="status">
                <?php echo e($updates_error); ?>
            </p>

        <?php elseif (empty($updates)): ?>

            <p>No publication updates yet.</p>

        <?php else: ?>

            <?php foreach ($updates as $update): ?>
                <div class="news-card">
                    <?php
                    // get_updates() already escapes its dynamic text.
                    echo $update;
                    ?>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </section>

    <section class="features" aria-labelledby="featuresHeading">
        <h2 id="featuresHeading">Platform features</h2>

        <div class="features-container">
            <?php foreach ($features as $feature): ?>
                <a
                    href="<?php echo e($feature['link']); ?>"
                    class="feature-card"
                >
                    <h3><?php echo e($feature['title']); ?></h3>
                    <p><?php echo e($feature['text']); ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section
        class="workflow-section"
        aria-labelledby="workflowHeading"
    >
        <h2 id="workflowHeading">How it works</h2>

        <div class="workflow-container">
            <?php foreach ($workflow as $index => $step): ?>
                <a
                    href="<?php echo e($step['link']); ?>"
                    class="workflow-card"
                >
                    <h3>
                        <?php echo $index + 1; ?>.
                        <?php echo e($step['title']); ?>
                    </h3>

                    <p><?php echo e($step['text']); ?></p>
                </a>

                <?php if ($index < count($workflow) - 1): ?>
                    <div class="workflow-arrow" aria-hidden="true">
                        →
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if (!empty($partners)): ?>
        <section
            class="media-partners"
            aria-labelledby="partnersHeading"
        >
            <h2 id="partnersHeading">Media organisations</h2>

            <div class="partner-grid">
                <?php foreach ($partners as $partner): ?>
                    <?php
                    $partner_url = (string) ($partner['url'] ?? '');
                    $partner_scheme = strtolower(
                        (string) parse_url($partner_url, PHP_URL_SCHEME)
                    );

                    if (
                        !filter_var($partner_url, FILTER_VALIDATE_URL) ||
                        !in_array(
                            $partner_scheme,
                            array('http', 'https'),
                            true
                        )
                    ) {
                        continue;
                    }
                    ?>

                    <a
                        href="<?php echo e($partner_url); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="partner-card"
                    >
                        <h3><?php echo e($partner['name']); ?></h3>
                        <span>Visit website (opens in a new tab)</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>