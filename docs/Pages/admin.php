<?php
session_start();
include('../backend/db.php');
include('../backend/content.php');

require_admin();

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    verify_csrf();

    $report_id = (int)$_POST['report_id'];
    $new_status = $_POST['status'];

    if(in_array($new_status, array('Verified', 'Rejected'))){

        $stmt = mysqli_prepare($conn, "UPDATE reports SET status = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $new_status, $report_id);
        mysqli_stmt_execute($stmt);

        // Log this action for accountability
        $log_stmt = mysqli_prepare($conn, "INSERT INTO admin_actions (admin_id, report_id, action) VALUES (?, ?, ?)");
        $admin_id = $_SESSION['user_id'];
        mysqli_stmt_bind_param($log_stmt, 'iis', $admin_id, $report_id, $new_status);
        mysqli_stmt_execute($log_stmt);

        // Only create a verification block when approving — rejected reports
        // don't need to be part of the tamper-evident published record.
        if($new_status === 'Verified'){
            $report_stmt = mysqli_prepare($conn, "SELECT title, description FROM reports WHERE id = ?");
            mysqli_stmt_bind_param($report_stmt, 'i', $report_id);
            mysqli_stmt_execute($report_stmt);
            $report_result = mysqli_stmt_get_result($report_stmt);
            $report = mysqli_fetch_assoc($report_result);

            if($report){
                $block_hash = bc_add_verification_block($report_id, $report['title'], $report['description']);
                if($block_hash){
                    cache_verification_block($report_id, $block_hash);
                }
            }
        }

        $message = "Report #$report_id marked as $new_status.";
    }
}

// Stats
$total_reports = db_count('reports');
$pending_count = db_count('reports', "status = 'Pending'");
$verified_count = db_count('reports', "status = 'Verified'");
$total_users = db_count('users');

// All reports, most recent first
$reports = db_rows("
    SELECT r.id, r.title, r.category, r.status, r.created_at, u.fullname, u.email
    FROM reports r
    JOIN users u ON r.user_id = u.id
    ORDER BY r.created_at DESC
");

// Recent admin activity log
$activity_log = db_rows("
    SELECT aa.action, aa.action_at, r.title AS report_title, u.fullname AS admin_name
    FROM admin_actions aa
    JOIN reports r ON aa.report_id = r.id
    JOIN users u ON aa.admin_id = u.id
    ORDER BY aa.action_at DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - DiSSENT</title>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

<?php include('../includes/header.php'); ?>

<main class="page-content">

    <h1>Admin Panel</h1>
    <p>Review submitted reports and manage verification status.</p>

    <?php if($message != ""): ?>
        <div style="background:#e3f7e8; padding:10px; margin-bottom:15px; border-radius:5px; font-weight:bold;">
            <?php echo e($message); ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-cards">
        <div class="dash-card">
            <h3>Total Reports</h3>
            <p class="dash-number"><?php echo $total_reports; ?></p>
        </div>
        <div class="dash-card">
            <h3>Pending</h3>
            <p class="dash-number"><?php echo $pending_count; ?></p>
        </div>
        <div class="dash-card">
            <h3>Verified</h3>
            <p class="dash-number"><?php echo $verified_count; ?></p>
        </div>
        <div class="dash-card">
            <h3>Total Users</h3>
            <p class="dash-number"><?php echo $total_users; ?></p>
        </div>
    </div>

    <h2>All Reports</h2>

    <?php if(empty($reports)): ?>
        <p>No reports submitted yet.</p>
    <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Submitted By</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($reports as $r): ?>
                    <tr>
                        <td><?php echo e($r['title']); ?></td>
                        <td><?php echo e($r['category']); ?></td>
                        <td><?php echo e($r['fullname']); ?> (<?php echo e($r['email']); ?>)</td>
                        <td><span class="status-badge status-<?php echo e(strtolower($r['status'])); ?>"><?php echo e($r['status']); ?></span></td>
                        <td><?php echo e(date('d M Y', strtotime($r['created_at']))); ?></td>
                        <td>
                            <?php if($r['status'] === 'Pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="report_id" value="<?php echo (int)$r['id']; ?>">
                                    <button type="submit" name="status" value="Verified" class="btn-primary">Verify</button>
                                    <button type="submit" name="status" value="Rejected" class="btn-secondary">Reject</button>
                                </form>
                            <?php else: ?>
                                <a href="verification.php?report_id=<?php echo (int)$r['id']; ?>">View</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <h2>Recent Admin Activity</h2>

    <?php if(empty($activity_log)): ?>
        <p>No admin actions logged yet.</p>
    <?php else: ?>
        <ul class="updates-list">
            <?php foreach($activity_log as $log): ?>
                <li>
                    <?php echo e($log['admin_name']); ?> marked
                    "<?php echo e($log['report_title']); ?>" as
                    <strong><?php echo e($log['action']); ?></strong>
                    on <?php echo e(date('d M Y, g:i a', strtotime($log['action_at']))); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

</main>

<?php include('../includes/footer.php'); ?>

</body>
</html>