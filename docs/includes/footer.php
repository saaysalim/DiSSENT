<?php
/* "Back to top" on long pages only (not Messaging, which scrolls inside its own box,
   and not short pages such as Login or Register). Without JavaScript it is a normal
   link; with JavaScript it floats in the corner after about 1.5 screens of scrolling
   (assets/JavaScript/app.js). */
$dissent_long_pages = array(
    'faq.php', 'user_guide.php', 'terms.php', 'privacy.php', 'about.php',
    'media_center.php', 'verification.php', 'reports.php', 'dashboard.php', 'admin.php'
);
if (in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), $dissent_long_pages, true)): ?>
<a href="#main-content" class="back-to-top" id="backToTop">Back to top</a>
<?php endif; ?>

<footer>
    <nav class="footer-links" aria-label="Footer">
        <a href="about.php">About</a>
        <a href="contact_us.php">Contact</a>
        <a href="media_center.php">Media Centre</a>
        <a href="faq.php">FAQ</a>
        <a href="user_guide.php">User guide</a>
        <a href="terms.php">Terms of use</a>
        <a href="privacy.php">Privacy notice</a>
    </nav>

    <p>&copy; <?php echo date("Y"); ?> DiSSENT. All rights reserved.</p>
</footer>

<script src="../assets/JavaScript/app.js?v=5"></script>
