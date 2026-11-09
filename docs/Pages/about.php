<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';

$content = platform_content();

$features = isset($content['features']) &&
    is_array($content['features'])
    ? $content['features']
    : array();

$partners = isset($content['partners']) &&
    is_array($content['partners'])
    ? $content['partners']
    : array();

$benefits = array(
    array(
        'title' => 'Cross-Border Collaboration',
        'text' => 'Bring journalists and media organisations together to share ideas and collaborate across countries.'
    ),
    array(
        'title' => 'Support for Displaced Reporters',
        'text' => 'Create opportunities for refugee and displaced journalists to stay connected with media communities while working away from home.'
    ),
    array(
        'title' => 'Clear Review Process',
        'text' => 'Submit reports for administrator review and follow their progress through pending, approved or rejected decisions.'
    ),
    array(
        'title' => 'Information Integrity',
        'text' => 'Use recorded verification information to support checks for changes to report content. These checks complement editorial review.'
    )
);

$is_logged_in = isset($_SESSION['user_id']);

// Partner links are website references, not proof of affiliation.
$organisation_links = array();

foreach ($partners as $partner) {
    if (!is_array($partner)) {
        continue;
    }

    $name = $partner['name'] ?? '';
    $url = $partner['url'] ?? '';

    if (
        !is_string($name) ||
        !is_string($url) ||
        trim($name) === '' ||
        !filter_var($url, FILTER_VALIDATE_URL)
    ) {
        continue;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

    if (!in_array($scheme, array('http', 'https'), true)) {
        continue;
    }

    $organisation_links[] = array(
        'name' => $name,
        'url' => $url
    );
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="Learn about DiSSENT, a platform prototype supporting cross-border journalism, report review and media collaboration."
    >

    <title>About DiSSENT</title>

    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/about.css">
</head>

<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="about-page">

    <section class="about-hero" aria-labelledby="aboutHeading">
        <div class="about-hero-content">
            <h1 id="aboutHeading">About DiSSENT</h1>

            <p>
                Connecting journalists, media organisations and
                cross-border communities through collaboration,
                report sharing and information integrity tools.
            </p>
        </div>
    </section>

    <section class="about-section" aria-labelledby="missionHeading">
        <h2 id="missionHeading">Our Mission</h2>

        <p>
            DiSSENT aims to help journalists and media organisations
            collaborate across borders. This includes refugee and
            displaced reporters who want to maintain professional
            connections and contribute to journalism while living
            away from their home countries.
        </p>

        <p>
            The platform brings together messaging, report submission,
            editorial review and a media centre for approved publications.
            It is currently being developed and tested as a prototype.
        </p>
    </section>

    <section
        class="technology-section"
        aria-labelledby="featuresHeading"
    >
        <h2 id="featuresHeading">What You Can Do</h2>

        <div class="technology-grid">
            <?php foreach ($features as $feature): ?>
                <?php
                if (
                    !is_array($feature) ||
                    !isset(
                        $feature['link'],
                        $feature['title'],
                        $feature['text']
                    ) ||
                    !is_string($feature['link']) ||
                    !is_string($feature['title']) ||
                    !is_string($feature['text'])
                ) {
                    continue;
                }

                // Feature links point to local platform pages.
                $allowed_feature_pages = array(
                    'messaging.php',
                    'reports.php',
                    'verification.php',
                    'media_center.php',
                    'dashboard.php',
                    'contact_us.php'
                );

                if (
                    !in_array(
                        $feature['link'],
                        $allowed_feature_pages,
                        true
                    )
                ) {
                    continue;
                }
                ?>

                <a
                    href="<?php echo e($feature['link']); ?>"
                    class="tech-card"
                >
                    <h3><?php echo e($feature['title']); ?></h3>
                    <p><?php echo e($feature['text']); ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section
        class="benefits-section"
        aria-labelledby="benefitsHeading"
    >
        <h2 id="benefitsHeading">Who DiSSENT Is For</h2>

        <div class="benefits-grid">
            <?php foreach ($benefits as $benefit): ?>
                <div class="benefit-card">
                    <h3><?php echo e($benefit['title']); ?></h3>
                    <p><?php echo e($benefit['text']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section
        class="about-section"
        aria-labelledby="reviewHeading"
    >
        <h2 id="reviewHeading">Editorial Review and Integrity Checks</h2>

        <p>
            Submitted reports start as pending. An administrator reviews
            the content and decides whether to approve or reject it.
            Approval is an editorial decision.
        </p>

        <p>
            Blockchain and cryptographic checks serve a different purpose:
            they can help detect changes to recorded information and verify
            digital signatures. They do not establish whether a report is
            factually true or replace human fact-checking.
        </p>

        <p>
            The prototype uses Hyperledger Fabric, Kafka and backend
            services to support parts of this workflow. Its messaging
            pipeline includes encryption and signature verification,
            but it is not an end-to-end encrypted messaging service.
        </p>
    </section>

    <?php if (!empty($organisation_links)): ?>
        <section
            class="partners-section"
            aria-labelledby="organisationsHeading"
        >
            <h2 id="organisationsHeading">Media Organisations</h2>

            <p class="partners-intro">
                Explore these organisations through their websites.
                Listing a website here does not imply a formal partnership
                or endorsement.
            </p>

            <div class="partner-grid">
                <?php foreach ($organisation_links as $organisation): ?>
                    <a
                        href="<?php echo e($organisation['url']); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="partner-card"
                    >
                        <h3>
                            <?php echo e($organisation['name']); ?>
                        </h3>

                        <p>Visit website — opens in a new tab</p>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section
        class="about-section about-cta"
        aria-labelledby="joinHeading"
    >
        <h2 id="joinHeading">Get Involved</h2>

        <p>
            Explore the media centre, join the platform or contact us
            about collaboration.
        </p>

        <div class="about-actions">
            <?php if ($is_logged_in): ?>
                <a href="dashboard.php" class="btn-primary">
                    Go to Dashboard
                </a>
            <?php else: ?>
                <a href="register.php" class="btn-primary">
                    Get Started
                </a>
            <?php endif; ?>

            <a href="contact_us.php" class="btn-secondary">
                Contact Us
            </a>
        </div>
    </section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>