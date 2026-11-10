<?php
session_start();

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';

$is_logged_in = isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="DiSSENT connects journalists and media teams through report submission, editorial review, messaging and blockchain integrity records."
    >

    <title>DiSSENT - Cross-Border Journalism and Collaboration</title>

    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/home.css">
</head>

<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<main id="main-content">

    <!-- Hero -->
    <section class="page-hero page-hero--alt page-hero--tall" aria-labelledby="homeHeading">
        <div>
            <h1 id="homeHeading">
                Journalism and Collaboration Across Borders
            </h1>

            <p>
                A shared workspace for journalists, including refugee
                and displaced reporters, to submit stories, communicate
                with media teams and follow editorial review.
            </p>

            <div class="page-hero__actions">
                <?php if ($is_logged_in): ?>
                    <a href="dashboard.php" class="btn-primary">
                        Go to Dashboard
                    </a>
                <?php else: ?>
                    <a href="register.php" class="btn-primary">
                        Get Started
                    </a>
                <?php endif; ?>

                <a href="about.php" class="btn-secondary">
                    Learn More
                </a>
            </div>
        </div>
    </section>

    <!-- About -->
    <section class="about-section" aria-labelledby="aboutHeading">
        <h2 id="aboutHeading">About DiSSENT</h2>

        <p>
            DiSSENT brings report submissions, editorial review and
            messaging into one workspace for cross-border media
            collaboration. Its messaging services use encryption and
            digital signatures, while Hyperledger Fabric supports
            public-key and report-integrity records. Editorial approval
            and technical integrity checks serve different purposes:
            a recorded fingerprint does not establish whether a story
            is factually accurate.
        </p>
    </section>

    <!-- Report workflow -->
    <section
        class="workflow-section"
        aria-labelledby="workflowHeading"
    >
        <h2 id="workflowHeading">From Submission to Publication</h2>

        <div class="workflow-container">
            <a href="reports.php" class="workflow-card">
                <h3>1. Submit a Report</h3>
                <p>
                    Add your report, category and optional supporting
                    evidence link.
                </p>
            </a>

            <div class="workflow-arrow" aria-hidden="true">
                →
            </div>

            <a href="reports.php" class="workflow-card">
                <h3>2. Follow the Review</h3>
                <p>
                    An authorised reviewer assesses your submission.
                    Track its status in My Reports.
                </p>
            </a>

            <div class="workflow-arrow" aria-hidden="true">
                →
            </div>

            <a href="media_center.php" class="workflow-card">
                <h3>3. Explore Publications</h3>
                <p>
                    Read reports approved for publication in the
                    Media Centre.
                </p>
            </a>
        </div>
    </section>

    <!-- Platform features -->
    <section class="features" aria-labelledby="featuresHeading">
        <h2 id="featuresHeading">Platform Features</h2>

        <div class="features-container">
            <a href="messaging.php" class="feature-card">
                <h3>Messaging</h3>
                <p>
                    Exchange messages with contacts and view
                    secure-transfer verification results.
                </p>
            </a>

            <a href="verification.php" class="feature-card">
                <h3>Blockchain Integrity</h3>
                <p>
                    Explore report recording references and
                    available integrity checks.
                </p>
            </a>

            <a href="media_center.php" class="feature-card">
                <h3>Media Centre</h3>
                <p>
                    Discover approved publications from contributors
                    across the platform.
                </p>
            </a>

            <a href="reports.php" class="feature-card">
                <h3>Report Management</h3>
                <p>
                    Submit reports and follow pending, approved
                    or rejected review statuses.
                </p>
            </a>
        </div>
    </section>

    <!-- Existing organisation links -->
    <section
        class="partners-section"
        aria-labelledby="organisationsHeading"
    >
        <h2 id="organisationsHeading">Media Organisations</h2>

        <div class="partners">
            <a
                href="https://www.mict-international.org/"
                target="_blank"
                rel="noopener noreferrer"
                class="partner-card"
            >
                <h3>MiCT</h3>
                <p>Visit website — opens in a new tab.</p>
            </a>

            <a
                href="https://www.echo.ie/"
                target="_blank"
                rel="noopener noreferrer"
                class="partner-card"
            >
                <h3>ECHO</h3>
                <p>Visit website — opens in a new tab.</p>
            </a>
        </div>
    </section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>