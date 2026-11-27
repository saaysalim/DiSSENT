<?php
session_start();
require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';

require_login();

$user_id = (int) $_SESSION['user_id'];

// Site-wide numbers (fixed SQL, no user input) and this user's own activity.
$stats = null;
$my = array('total' => 0, 'Pending' => 0, 'Approved' => 0, 'Rejected' => 0);
$my_latest = array();
$messages_week = 0;
$messages_any = 0;
$account_role = '';
$dashboard_error = false;

try {
    $stats = array(
        'reports' => db_count('reports'),
        'published' => db_count('reports', "status = 'Approved'"),
        // Moved here from the Verification page (workshop feedback 25/11/2026).
        'pending' => db_count('reports', "status = 'Pending'"),
        'members' => db_count('users', "role <> 'deleted'"),
        'media' => db_count('users', "role <> 'deleted' AND organisation = 'Media Organisation'"),
    );

    $stmt = mysqli_prepare($conn, 'SELECT status, COUNT(*) AS n FROM reports WHERE user_id = ? GROUP BY status');
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $my['total'] += (int) $row['n'];

        if (isset($my[$row['status']])) {
            $my[$row['status']] += (int) $row['n'];
        }
    }

    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, 'SELECT id, title, status, created_at FROM reports WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 3');
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $my_latest = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND status = \'delivered\' AND COALESCE(delivered_at, sent_at) >= NOW() - INTERVAL 7 DAY');
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $messages_week = (int) mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
    mysqli_stmt_close($stmt);

    // Any message sent or received (for the "Getting started" box).
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) FROM messages WHERE sender_id = ? OR receiver_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $user_id, $user_id);
    mysqli_stmt_execute($stmt);
    $messages_any = (int) mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
    mysqli_stmt_close($stmt);

    // Read the role from the database, not an old session value.
    $stmt = mysqli_prepare($conn, 'SELECT role FROM users WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $account_role = (string) (mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? '');
    mysqli_stmt_close($stmt);
} catch (Throwable $error) {
    error_log('DiSSENT: dashboard figures unavailable: ' . get_class($error));
    $dashboard_error = true;
}

$status_labels = array('Pending' => 'Pending review', 'Approved' => 'Approved', 'Rejected' => 'Rejected');

$show_number = static function ($value) {
    return $value === null ? '–' : number_format((int) $value);
};

$unread_total = array_sum(unread_message_counts($user_id));
$show_getting_started = !$dashboard_error && $my['total'] === 0 && $messages_any === 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - DiSSENT</title>
    <meta name="description" content="Your DiSSENT dashboard: site numbers, your reports, your messages and quick links.">

    <link rel="stylesheet" href="../assets/css/base.css?v=5">
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=2">
</head>

<body>

<?php include('../includes/header.php'); ?>

<main id="main-content" tabindex="-1">

<section class="page-hero page-hero--alt" aria-labelledby="dashboardHeading">
    <h1 id="dashboardHeading">
        Welcome, <?php echo e($_SESSION['fullname'] ?? ''); ?>
    </h1>

    <p>Manage your reports, messages and recent updates.</p>
</section>

<?php if ($dashboard_error): ?>
    <div class="dashboard-notice">
        <div class="notice notice--error" role="alert">
            Some dashboard figures are unavailable right now. Please try again later.
        </div>
    </div>
<?php endif; ?>

<?php if ($show_getting_started): ?>
    <section class="getting-started" aria-labelledby="gettingStartedHeading">
        <h2 id="gettingStartedHeading">Getting started</h2>
        <p>Welcome to DiSSENT. Here are four good first steps:</p>
        <ol>
            <li><a href="profile.php">Check your profile</a>: your name, professional category and picture.</li>
            <li><a href="reports.php">Submit your first report</a>. An administrator reviews it before it is published.</li>
            <li><a href="messaging.php">Send a message</a> to another member.</li>
            <li>Read the <a href="user_guide.php">User guide</a> for step-by-step help.</li>
        </ol>
    </section>
<?php endif; ?>

<section class="dashboard-overview" aria-labelledby="acrossHeading">
    <h2 id="acrossHeading" class="dashboard-heading">Across DiSSENT</h2>

    <div class="dashboard-stats">

        <div class="stat-card">
            <p class="stat-number"><?php echo $show_number($stats['reports'] ?? null); ?></p>
            <p>Reports submitted</p>
        </div>

        <div class="stat-card">
            <p class="stat-number"><?php echo $show_number($stats['published'] ?? null); ?></p>
            <p>Published reports</p>
        </div>

        <div class="stat-card">
            <p class="stat-number"><?php echo $show_number($stats['pending'] ?? null); ?></p>
            <p>Pending review</p>
        </div>

        <div class="stat-card">
            <p class="stat-number"><?php echo $show_number($stats['members'] ?? null); ?></p>
            <p>Members</p>
        </div>

        <div class="stat-card">
            <p class="stat-number"><?php echo $show_number($stats['media'] ?? null); ?></p>
            <p>Members from media organisations</p>
        </div>

    </div>
</section>

<section class="activity-section" aria-labelledby="activityHeading">

    <h2 id="activityHeading">Your activity</h2>

    <div class="activity-card">
        <?php if ($my['total'] === 0): ?>
            You have not submitted any reports yet.
            <a href="reports.php">Submit your first report</a>.
        <?php else: ?>
            You have submitted <strong><?php echo (int) $my['total']; ?></strong>
            report<?php echo $my['total'] === 1 ? '' : 's'; ?>:
            <?php echo (int) $my['Pending']; ?> pending review,
            <?php echo (int) $my['Approved']; ?> approved,
            <?php echo (int) $my['Rejected']; ?> rejected.
        <?php endif; ?>
    </div>

    <?php foreach ($my_latest as $report): ?>
        <div class="activity-card">
            <strong><?php echo e($report['title']); ?></strong>
            <span class="activity-meta">
                <?php echo e($status_labels[$report['status']] ?? $report['status']); ?>
                · submitted <?php echo e(date('d M Y', strtotime((string) $report['created_at']))); ?>
            </span>
            <span class="activity-meta"><?php echo e(report_status_explanation($report['status'])); ?></span>
        </div>
    <?php endforeach; ?>

    <div class="activity-card">
        <?php if ($messages_week === 0): ?>
            No new messages in the last 7 days.
        <?php else: ?>
            You received <strong><?php echo $messages_week; ?></strong>
            message<?php echo $messages_week === 1 ? '' : 's'; ?> in the last 7 days.
        <?php endif; ?>
        <?php if ($unread_total > 0): ?>
            <strong><?php echo (int) $unread_total; ?></strong> unread.
        <?php endif; ?>
        <a href="messaging.php">Open Messaging</a>.
    </div>

    <?php if ($account_role !== ''): ?>
        <div class="activity-card">
            <?php if ($account_role === 'admin'): ?>
                You are an <strong>administrator</strong>: you can also review reports and held messages on the <a href="admin.php">Admin page</a>.
            <?php else: ?>
                You have a <strong>member account</strong>: you can submit reports and send messages.
            <?php endif; ?>
        </div>
    <?php endif; ?>

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

        <a href="profile.php" class="action-card">
            My Profile
        </a>

        <a href="settings.php" class="action-card">
            Settings
        </a>

        <a href="user_guide.php" class="action-card">
            User guide
        </a>

    </div>

</section>

</main>

<?php include('../includes/footer.php'); ?>

</body>
</html>
