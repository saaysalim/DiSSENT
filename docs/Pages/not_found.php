<?php
/*
 * "Page not found" (404). Apache shows it for every missing address
 * (ErrorDocument in docker/web/dissent-apache.conf), so the address can be
 * anywhere: <base> makes the relative links (header, footer, CSS) work.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';

http_response_code(404);
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="/Pages/">
    <title>Page not found - DiSSENT</title>
    <meta name="description" content="This DiSSENT page could not be found.">
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="../assets/css/base.css?v=5">
    <link rel="stylesheet" href="../assets/css/info.css?v=2">
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<main id="main-content" tabindex="-1">

<div class="info-page">
    <section class="info-card" aria-labelledby="notFoundHeading">
        <h1 id="notFoundHeading" class="info-title">Page not found</h1>
        <p>Sorry, there is no page at this address. It may have moved, or the address may be mistyped.</p>
        <div class="info-actions">
            <a href="home.php">Go to the Home page</a>
            <a href="dashboard.php" class="info-actions__secondary">Go to your Dashboard</a>
        </div>
        <p class="info-updated">Looking for help? Try the <a href="faq.php">FAQ</a> or the <a href="user_guide.php">User guide</a>.</p>
    </section>
</div>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
