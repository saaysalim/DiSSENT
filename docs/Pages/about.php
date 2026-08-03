<?php
session_start();
include('../backend/db.php');
include('../backend/content.php');

$content = platform_content();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About DiSSENT</title>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/about.css">
</head>
<body>

<?php include('../includes/header.php'); ?>

<section class="about-hero">
    <div class="about-hero-content">
        <h1>About DiSSENT</h1>
        <p>
            A secure cross-border journalism platform enabling trusted
            communication, verified reporting and international media
            collaboration.
        </p>
    </div>
</section>

<section class="about-section">
    <h2>Our Mission</h2>
    <p>
        DiSSENT empowers journalists and media organisations to exchange
        information securely across borders while preserving integrity,
        authenticity and transparency throughout the publishing workflow.
    </p>
</section>

<section class="technology-section">
    <h2>Core Technologies</h2>
    <div class="technology-grid">
        <?php foreach($content['features'] as $feature) { ?>
            <a href="<?php echo e($feature['link']); ?>" class="tech-card">
                <h3><?php echo e($feature['title']); ?></h3>
                <p><?php echo e($feature['text']); ?></p>
            </a>
        <?php } ?>
    </div>
</section>

<section class="benefits-section">
    <h2>Why Choose DiSSENT?</h2>
    <div class="benefits-grid">
        <?php foreach($content['benefits'] as $benefit) { ?>
            <div class="benefit-card">
                <h3><?php echo e($benefit['title']); ?></h3>
                <p><?php echo e($benefit['text']); ?></p>
            </div>
        <?php } ?>
    </div>
</section>

<section class="partners-section">
    <h2>Trusted Partners</h2>
    <div class="partner-grid">
        <?php foreach($content['partners'] as $partner) { ?>
            <a href="<?php echo e($partner['url']); ?>" target="_blank" rel="noopener noreferrer" class="partner-card">
                <h3><?php echo e($partner['name']); ?></h3>
                <p><?php echo e($partner['text']); ?></p>
            </a>
        <?php } ?>
    </div>
</section>

<?php include('../includes/footer.php'); ?>
</body>
</html>