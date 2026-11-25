<?php
// Shown by require_admin() with a 403 code when a member opens an admin page.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>No permission - DiSSENT</title>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/auth.css?v=5">
</head>
<body>

<?php include __DIR__ . '/header.php'; ?>

<main class="auth-section" aria-labelledby="noPermissionHeading">
    <div class="auth-card">
        <h1 id="noPermissionHeading">You don't have permission to view this page</h1>
        <p class="auth-intro">This page is only for administrators. If you think you should have access, please contact the DiSSENT team.</p>
        <div class="auth-form">
            <a class="auth-submit" href="dashboard.php">Back to Dashboard</a>
        </div>
        <div class="auth-links">
            <p><a href="home.php">Home</a> · <a href="contact_us.php">Contact us</a></p>
        </div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

</body>
</html>
