<?php
/*
 * Terms of use (public).
 * Only statements that are true for the current code. Facts still to be added before launch
 * are listed in README.md (Future Work).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of use - DiSSENT</title>
    <meta name="description" content="The terms for using DiSSENT, the platform for cross-border and exiled journalists.">
    <link rel="stylesheet" href="../assets/css/base.css?v=5">
    <link rel="stylesheet" href="../assets/css/info.css?v=2">
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<main id="main-content" tabindex="-1">

<section class="page-hero" aria-labelledby="termsHeading">
    <h1 id="termsHeading">Terms of use</h1>
    <p>The rules for using DiSSENT.</p>
</section>

<div class="info-page">

    <p class="info-updated">Last updated: 26 November 2026</p>

    <section class="info-card" aria-labelledby="aboutTermsHeading">
        <h2 id="aboutTermsHeading">About DiSSENT</h2>
        <p>
            DiSSENT is a platform where journalists working across borders, including journalists
            in exile, submit reports for editorial review, message each other and check that a
            published report has not been changed.
        </p>
        <p>
            We work to keep DiSSENT available and your data safe, but we cannot promise that the
            service will always be available or free of errors. Keep your own copy of anything
            important.
        </p>
        <p>
            By creating an account you agree to these terms. If you do not agree, please do not
            create an account.
        </p>
    </section>

    <section class="info-card" aria-labelledby="accountTermsHeading">
        <h2 id="accountTermsHeading">Your account</h2>
        <ul>
            <li>Use your real name and an email address that you can read.</li>
            <li>Keep your password secret and use it only for DiSSENT. Log out on shared devices.</li>
            <li>Your professional category describes your work. It does not give you extra permissions.</li>
            <li>You can delete your account at any time in Settings.</li>
        </ul>
    </section>

    <section class="info-card" aria-labelledby="contentTermsHeading">
        <h2 id="contentTermsHeading">What you post</h2>
        <ul>
            <li>Only submit reports, files and messages that you have the right to share.</li>
            <li>Protect your sources: use a general area instead of an exact place, and do not include names or private details that could identify someone at risk.</li>
            <li>Do not post anything illegal, threatening, abusive or misleading, and do not send spam or harmful files.</li>
            <li>Do not send passwords, bank details or ID numbers in messages. Messages with personal details are held for an administrator.</li>
        </ul>
    </section>

    <section class="info-card" aria-labelledby="reviewTermsHeading">
        <h2 id="reviewTermsHeading">Review and publication</h2>
        <ul>
            <li>An administrator reviews every report before it is published, and may reject it. You see the reason.</li>
            <li>Messages with a file or personal details are read by an administrator before delivery, and may be rejected.</li>
            <li>An approved report is shown publicly in the Media Centre with your name and professional category. Its fingerprint is recorded on a blockchain, which cannot be deleted.</li>
            <li>Approval is an editorial decision. The blockchain record shows that the text has not changed; it does not prove that the report is true.</li>
        </ul>
    </section>

    <section class="info-card" aria-labelledby="endTermsHeading">
        <h2 id="endTermsHeading">If the rules are broken</h2>
        <p>
            The DiSSENT team may remove content or close an account that breaks these terms.
        </p>
    </section>

    <section class="info-card" aria-labelledby="contactTermsHeading">
        <h2 id="contactTermsHeading">Questions</h2>
        <p>
            Contact the DiSSENT team through the <a href="contact_us.php">Contact page</a>.
            How we handle your data is explained in the <a href="privacy.php">Privacy notice</a>.
        </p>
    </section>

</div>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
