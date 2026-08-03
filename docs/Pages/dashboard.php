<?php
session_start();
require_once __DIR__ . '/../backend/content.php';

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - DiSSENT</title>

    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>

<body>

<?php include('../includes/header.php'); ?>

<section class="dashboard-hero">

    <h1>
        Welcome,
        <?php echo e($_SESSION['fullname']); ?>
    </h1>

    <p>
        Role:
        <strong>
            <?php echo e($_SESSION['role']); ?>
        </strong>
    </p>

    <a href="logout.php" class="login-btn">
        Logout
    </a>

</section>

<section class="dashboard-stats">

    <div class="stat-card">
        <h2>245</h2>
        <p>Reports Submitted</p>
    </div>

    <div class="stat-card">
        <h2>198</h2>
        <p>Verified Reports</p>
    </div>

    <div class="stat-card">
        <h2>52</h2>
        <p>Active Journalists</p>
    </div>

    <div class="stat-card">
        <h2>18</h2>
        <p>Media Partners</p>
    </div>

</section>

<section class="activity-section">

    <h2>Recent Activity</h2>

    <div class="activity-card">
        Welcome to DiSSENT,
        <?php echo e($_SESSION['fullname']); ?>
    </div>

    <div class="activity-card">
        Your account role:
        <?php echo e($_SESSION['role']); ?>
    </div>

    <div class="activity-card">
        Registration completed successfully.
    </div>

    <div class="activity-card">
        You are now logged into the platform.
    </div>

</section>

<section class="actions-section">

    <h2>Quick Actions</h2>

    <div class="actions-grid">

        <a href="reports.php" class="action-card">
            View Reports
        </a>

        <a href="messaging.php" class="action-card">
            Send Message
        </a>

        <a href="verification.php" class="action-card">
            Verify Report
        </a>

        <a href="media_center.php" class="action-card">
            Media Centre
        </a>

    </div>

</section>

<?php include('../includes/footer.php'); ?>

</body>
</html>
