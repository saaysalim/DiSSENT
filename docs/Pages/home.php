<?php
session_start();
include('../backend/db.php');
include('../backend/content.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DiSSENT - Secure Cross-Border Journalism Platform</title>

    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/home.css">
</head>

<body>

<?php include('../includes/header.php'); ?>

<!-- HERO SECTION -->
<section class="hero">
    <div class="hero-content">

        <h1>Secure Cross-Border Journalism Platform</h1>

        <p>
            Connecting journalists, media organisations and trusted
            partners through secure messaging, hash-chain verification
            and reliable information exchange across borders.
        </p>

        <div class="hero-buttons">
            <?php if(isset($_SESSION['user_id'])){ ?>
                <a href="dashboard.php" class="btn-primary">Go to Dashboard</a>
            <?php }else{ ?>
                <a href="register.php" class="btn-primary">Get Started</a>
            <?php } ?>
            <a href="about.php" class="btn-secondary">Learn More</a>
        </div>

    </div>
</section>

<!-- ABOUT -->
<section class="about-section">
    <h2>About DiSSENT</h2>

    <p>
        DiSSENT is a secure platform designed for journalists,
        media organisations and trusted partners to exchange
        information safely. The platform combines encrypted messaging
        with hash-chain verification to ensure security,
        transparency and authenticity.
    </p>
</section>

<!-- HOW IT WORKS -->
<section class="workflow-section">
    <h2>How It Works</h2>

    <div class="workflow-container">

        <a href="reports.php" class="workflow-card">
            <h3>1. Submit Information</h3>
            <p>Journalists submit reports, evidence files and news content.</p>
        </a>

        <div class="workflow-arrow">→</div>

        <a href="messaging.php" class="workflow-card">
            <h3>2. Secure Transfer</h3>
            <p>Information is securely exchanged through protected messaging.</p>
        </a>

        <div class="workflow-arrow">→</div>

        <a href="verification.php" class="workflow-card">
            <h3>3. Hash-Chain Verification</h3>
            <p>Reports are verified, authenticated and timestamped for integrity.</p>
        </a>

    </div>
</section>

<!-- TRUSTED PARTNERS -->
<section class="partners-section">
    <h2>Trusted Partners</h2>

    <div class="partners">

        <a href="https://www.mict-international.org/" target="_blank" rel="noopener noreferrer" class="partner-card">
            <h3>MiCT</h3>
            <p>Germany Partner</p>
        </a>

        <div class="exchange-arrow">↔</div>

        <a href="https://www.echo.ie/" target="_blank" rel="noopener noreferrer" class="partner-card">
            <h3>ECHO</h3>
            <p>Ireland Partner</p>
        </a>

    </div>
</section>

<!-- PLATFORM FEATURES -->
<section class="features">
    <h2>Platform Features</h2>

    <div class="features-container">

        <a href="messaging.php" class="feature-card">
            <h3>Secure Messaging</h3>
            <p>Encrypted communication between trusted organisations.</p>
        </a>

        <a href="verification.php" class="feature-card">
            <h3>Hash-Chain Verification</h3>
            <p>Authenticity and integrity of information.</p>
        </a>

        <a href="media_center.php" class="feature-card">
            <h3>Cross-Border Collaboration</h3>
            <p>Connect media partners across countries.</p>
        </a>

        <a href="reports.php" class="feature-card">
            <h3>Report Management</h3>
            <p>Track, review and manage submitted reports.</p>
        </a>

    </div>
</section>

<?php include('../includes/footer.php'); ?>

</body>
</html>