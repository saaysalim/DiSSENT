<?php
/*
 * Privacy notice (public), based on what the code really stores.
 * Only statements that are true for the current code. Facts still to be added before launch
 * (legal name, contact email, hosting country, email provider, retention period) are listed
 * in README.md (Future Work).
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
    <title>Privacy notice - DiSSENT</title>
    <meta name="description" content="What personal data DiSSENT stores, who can see it, how long it is kept and how to ask about it.">
    <link rel="stylesheet" href="../assets/css/base.css?v=5">
    <link rel="stylesheet" href="../assets/css/info.css?v=2">
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<main id="main-content" tabindex="-1">

<section class="page-hero" aria-labelledby="privacyHeading">
    <h1 id="privacyHeading">Privacy notice</h1>
    <p>What DiSSENT stores about you, who can see it, and what you can do about it.</p>
</section>

<div class="info-page">

    <p class="info-updated">Last updated: 26 November 2026</p>

    <section class="info-card" aria-labelledby="whoHeading">
        <h2 id="whoHeading">Who we are</h2>
        <p>
            DiSSENT is run by the DiSSENT team. You can reach us through the
            <a href="contact_us.php">Contact page</a>.
        </p>
    </section>

    <section class="info-card" aria-labelledby="whatHeading">
        <h2 id="whatHeading">What we store</h2>
        <ul>
            <li><strong>Your account:</strong> full name, email address, professional category, profile picture (if you add one; hidden data such as the GPS location of a phone photo is removed when you upload it), account type (member or administrator), the date you joined and the time you agreed to the Terms of use and this notice. Your password is stored only as a secure hash, never as plain text.</li>
            <li><strong>Your settings:</strong> your language and email notification choices.</li>
            <li><strong>Reports:</strong> title, category, description, location and evidence link (if you give them), one optional PDF, and the administrator's decision and reason.</li>
            <li><strong>Messages:</strong> the text, one optional file, when it was sent and read, and its review status. Location data (EXIF) is removed from images. Hidden details inside PDFs are not removed.</li>
            <li><strong>Contacts:</strong> who you are connected with in Messaging, and the message requests you sent or received with the answer (accepted, declined or blocked). For the request limits, each request is recorded with a hash of the email address you typed, not the address itself; these records are deleted after 8 days.</li>
            <li><strong>Contact form:</strong> your name, email address, subject and message.</li>
            <li><strong>Security records:</strong> failed logins and password-reset requests are stored as a hash of the email address plus the IP address, and deleted after one day. Reset links are sent straight away, stored only as a hash, and work for 60 minutes.</li>
            <li><strong>Administrator log:</strong> every decision about a report or a held message (who decided, when, and why).</li>
        </ul>
    </section>

    <section class="info-card" aria-labelledby="whereHeading">
        <h2 id="whereHeading">Where it is stored</h2>
        <p>
            Everything is stored on the computer that runs DiSSENT: account details, reports and
            messages in a MySQL database, and report PDFs and message files in a private folder that
            the website cannot serve directly.
        </p>
    </section>

    <section class="info-card" aria-labelledby="secureHeading">
        <h2 id="secureHeading">Secure transfer and the blockchain</h2>
        <p>
            When a message is delivered, an encrypted and signed copy is sent between the two
            DiSSENT services. The second service decrypts it to check it and keeps that copy in its
            own database (MongoDB).
        </p>
        <p>
            When a report is approved, a fingerprint (hash) of the report is recorded on a
            Hyperledger Fabric blockchain. The fingerprint is not the report itself, but
            <strong>blockchain records cannot be deleted</strong>.
        </p>
    </section>

    <section class="info-card" aria-labelledby="seeHeading">
        <h2 id="seeHeading">Who can see it</h2>
        <ul>
            <li><strong>Everyone:</strong> approved reports in the Media Centre, with your name and professional category. Your PDF is never public.</li>
            <li><strong>Other members:</strong> only your contacts (people who accepted your message request, or whose request you accepted) see your name and professional category in Messaging, and the messages you send them. A person you send a message request to sees your name, professional category and first message. Your email address is never shown to other members, and there is no list of members to browse.</li>
            <li><strong>Administrators:</strong> reports waiting for review and their PDFs, messages held for review and their files, and Contact form messages.</li>
            <li><strong>People who run the server</strong> could technically read the database, including message text, which is not stored encrypted.</li>
        </ul>
    </section>

    <section class="info-card" aria-labelledby="cookieHeading">
        <h2 id="cookieHeading">Cookies</h2>
        <p>
            DiSSENT uses one cookie, which keeps you logged in. It is deleted when you log out or close
            your browser. There are no advertising or tracking cookies, and the pages do not load
            anything from other websites.
        </p>
    </section>

    <section class="info-card" aria-labelledby="keepHeading">
        <h2 id="keepHeading">How long we keep it</h2>
        <p>
            Your data is kept until you delete your account. Failed-login records are deleted after
            one day, message request records (used for the limits) after 8 days, and password-reset
            links work for 60 minutes after they are sent, then stop working.
        </p>
        <p>
            When you delete your account (Settings, then "Delete my account"), we remove your login,
            settings, unpublished reports with their PDFs, your profile picture, the files you attached
            to messages, your message requests that were not accepted, your password-reset links and your
            Contact form messages. Approved reports stay
            published and the people you messaged keep the text of those conversations; in both places
            your name becomes "Deleted user". Administrator log entries and blockchain records stay.
        </p>
    </section>

    <section class="info-card" aria-labelledby="rightsHeading">
        <h2 id="rightsHeading">Questions and requests</h2>
        <p>
            To ask what we store about you, to correct it or to have it deleted, contact the
            DiSSENT team through the <a href="contact_us.php">Contact page</a>. You can change your
            name, category and picture yourself in My Profile, and delete your account in Settings.
        </p>
        <p>See also the <a href="terms.php">Terms of use</a>.</p>
    </section>

</div>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
