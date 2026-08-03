<?php
session_start();
include('../backend/db.php');
include('../backend/content.php');

$content = platform_content();
$search = isset($_GET['q']) ? trim($_GET['q']) : "";
$show_all = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

$publications = get_publications($search, $show_all);
$updates = get_updates(6);

$features = $content['features'];
$workflow = $content['workflow'];
$partners = $content['partners'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Media Center - DiSSENT</title>
<link rel="stylesheet" href="../assets/css/base.css">
<link rel="stylesheet" href="../assets/css/home.css">
<link rel="stylesheet" href="../assets/css/media_center.css">
</head>
<body>

<?php include('../includes/header.php'); ?>

<section class="media-hero">
    <h1>Media Center</h1>
    <p>Verified reports, publications and cross-border collaboration from DiSSENT partners.</p>
</section>

<section class="media-search-section">

    <form method="GET" action="media_center.php" class="media-search-box">
        <input type="text" name="q" placeholder="Search publications, journalists, topics..." value="<?php echo e($search); ?>">
        <button type="submit">Search</button>
    </form>

    <?php if($show_all){ ?>
        <p class="admin-note">Admin view: showing all reports regardless of status.</p>
    <?php } ?>

</section>

<section class="featured-section">

    <h2>Publications <?php echo $search !== '' ? '— results for "' . e($search) . '"' : ''; ?></h2>

    <?php if(empty($publications)){ ?>

        <p>No publications found<?php echo $search !== '' ? ' for that search.' : ' yet.'; ?></p>

    <?php }else{ ?>

        <div class="featured-grid">
            <?php foreach($publications as $pub){ ?>
                <div class="featured-card">
                    <span class="status-badge status-<?php echo e(strtolower($pub['status'])); ?>"><?php echo e($pub['status']); ?></span>
                    <h3><?php echo e($pub['title']); ?></h3>
                    <p class="pub-meta">
                        By <?php echo e($pub['fullname']); ?>
                        <?php echo $pub['organisation'] ? ' &middot; ' . e($pub['organisation']) : ''; ?>
                        <?php echo $pub['category'] ? ' &middot; ' . e($pub['category']) : ''; ?>
                        &middot; <?php echo e(date('d M Y', strtotime($pub['created_at']))); ?>
                    </p>
                    <p><?php echo e(summarize($pub['description'])); ?></p>
                    <?php if($pub['location']){ ?>
                        <p class="pub-location">📍 <?php echo e($pub['location']); ?></p>
                    <?php } ?>
                    <?php if($pub['block_hash']){ ?>
                        <p class="pub-hash">Verification: <code><?php echo e(substr($pub['block_hash'], 0, 16)); ?>&hellip;</code></p>
                    <?php } ?>
                    <a href="verification.php?report_id=<?php echo (int)$pub['id']; ?>" class="btn-secondary">View Verification</a>
                </div>
            <?php } ?>
        </div>

    <?php } ?>

</section>

<section class="news-section">

    <h2>Live Updates</h2>

    <?php if(empty($updates)){ ?>

        <p>No activity yet.</p>

    <?php }else{ ?>

        <?php foreach($updates as $update){ ?>
            <div class="news-card"><?php echo $update; ?></div>
        <?php } ?>

    <?php } ?>

</section>

<section class="features">
    <h2>Platform Features</h2>
    <div class="features-container">
        <?php foreach($features as $f){ ?>
            <a href="<?php echo e($f['link']); ?>" class="feature-card">
                <h3><?php echo e($f['title']); ?></h3>
                <p><?php echo e($f['text']); ?></p>
            </a>
        <?php } ?>
    </div>
</section>

<section class="workflow-section">
    <h2>How It Works</h2>
    <div class="workflow-container">
        <?php foreach($workflow as $i => $step){ ?>
            <a href="<?php echo e($step['link']); ?>" class="workflow-card">
                <h3><?php echo ($i + 1) . '. ' . e($step['title']); ?></h3>
                <p><?php echo e($step['text']); ?></p>
            </a>
            <?php if($i < count($workflow) - 1){ ?>
                <div class="workflow-arrow">→</div>
            <?php } ?>
        <?php } ?>
    </div>
</section>

<section class="media-partners">
    <h2>Trusted Partners</h2>
    <div class="partner-grid">
        <?php foreach($partners as $p){ ?>
            <a href="<?php echo e($p['url']); ?>" target="_blank" rel="noopener noreferrer" class="partner-card">
                <h3><?php echo e($p['name']); ?></h3>
                <p><?php echo e($p['text']); ?></p>
            </a>
        <?php } ?>
    </div>
</section>

<?php include('../includes/footer.php'); ?>

</body>
</html>