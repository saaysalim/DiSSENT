<?php
/*
 * User guide (public): numbered steps for the main tasks.
 * Linked from the footer, the FAQ and the Dashboard (not the top menu).
 * Keep it in step with the pages and with faq_items() in backend/content.php.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';

$guide = array(
    array(
        'id' => 'create-account',
        'title' => 'Create an account',
        'steps' => array(
            'Press "Create account" at the top of any page.',
            'Type your full name and email address.',
            'Choose your professional category. If none fits, choose "Other" and type your own.',
            'Create a password that ticks every rule in the checklist, then type it again.',
            'Press "Register". You go to the Login page, and your account is ready.',
        ),
    ),
    array(
        'id' => 'log-in',
        'title' => 'Log in, log out and forgotten passwords',
        'steps' => array(
            'Press "Login" at the top, type your email and password, and press "Log in".',
            'If you opened a page that needs an account, you go back to it after logging in.',
            'Forgot your password? Press "Forgot password?" and type your email. We send a reset link straight away: check your inbox (and spam folder). The link works for 60 minutes.',
            'After 5 wrong passwords in 15 minutes, wait 15 minutes before trying again.',
            'To log out, press "Logout" in the menu. Always log out on shared devices.',
        ),
    ),
    array(
        'id' => 'profile',
        'title' => 'Your profile',
        'steps' => array(
            'Press your name at the top to open your Profile.',
            'Change your full name, professional category or picture (JPG, PNG or GIF).',
            'Press "Save Changes".',
            'Your email address cannot be changed. To change your password, use Settings.',
        ),
    ),
    array(
        'id' => 'submit-report',
        'title' => 'Submit a report',
        'steps' => array(
            'Open Reports from the menu.',
            'Type a title, choose a category and describe what happened.',
            'Optionally add a general location, a web link and one PDF of up to 5 MB.',
            'Press "Submit for review". The report appears under "My reports" as "Pending review".',
        ),
    ),
    array(
        'id' => 'report-review',
        'title' => 'Follow the review of a report',
        'steps' => array(
            'Open Reports and look under "My reports", or see the latest ones on your Dashboard.',
            '"Pending review": an administrator has not decided yet.',
            '"Approved": the report is published in the Media Centre and its fingerprint is recorded on the blockchain.',
            '"Rejected": the report is not published. The reason is shown under the report.',
        ),
    ),
    array(
        'id' => 'messages',
        'title' => 'Send messages and files',
        'steps' => array(
            'Open Messaging. You only see your contacts: people who accepted your message request, or whose request you accepted. The people you talked to most recently are at the top; a red number shows new messages and new requests.',
            'To add someone, press "New message", type their full email address (for example name@example.com; a name is not enough) and, if you like, a short first message, then press "Send request". You always see the same answer, so nobody can use it to find out who has an account. When they accept, they appear in your contacts.',
            'Requests sent to you appear under "Message requests" with the person\'s name, professional category and first message. Choose Accept, Decline or Block. The sender is not told if you decline or block.',
            'Choose a contact. With many contacts, type part of a name in "Search your contacts". On a phone the chat opens on its own screen; "All contacts" takes you back.',
            'Type your message. You can attach one JPG, PNG, WebP or PDF of up to 5 MB.',
            'Press the send button (paper plane).',
            'Messages with a file or personal details (phone number, email, ID or bank number) wait for an administrator before the other person sees them.',
            'Under each delivered message a badge shows what happened to the encrypted copy: "Secure transfer: pending", "Secure transfer: verified ✓", "Secure transfer: failed" or "Secure transfer: not used (service offline)".',
        ),
    ),
    array(
        'id' => 'verification',
        'title' => 'Check a report on Verification',
        'steps' => array(
            'Open Verification from the menu.',
            'Type a report ID, title or journalist name and press "Find Report".',
            'The result shows whether the report was recorded on the blockchain.',
            '"Chain integrity" shows whether the chain of records is intact.',
        ),
    ),
    array(
        'id' => 'settings',
        'title' => 'Settings and deleting your account',
        'steps' => array(
            'Press "Settings" at the top.',
            'Change password: type your current password and the new one twice. Your other devices are logged out.',
            'Choose a language and email notifications (saved for later; the site is in English for now).',
            'Delete my account: type your password, tick the box and press the button. This cannot be undone.',
        ),
    ),
    array(
        'id' => 'help',
        'title' => 'Getting help',
        'steps' => array(
            'Read the FAQ, which has a search box.',
            'Use the Contact page to send the team a message. We reply by email.',
            'Read the Terms of use and the Privacy notice (links at the bottom of every page).',
        ),
    ),
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User guide - DiSSENT</title>
    <meta name="description" content="Step-by-step help for DiSSENT: creating an account, reports, messages, verification and settings.">
    <link rel="stylesheet" href="../assets/css/base.css?v=5">
    <link rel="stylesheet" href="../assets/css/info.css?v=2">
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<main id="main-content" tabindex="-1">

<section class="page-hero" aria-labelledby="guideHeading">
    <h1 id="guideHeading">User guide</h1>
    <p>Step-by-step help for the main tasks on DiSSENT.</p>
</section>

<div class="info-page">

    <nav class="info-card info-contents" aria-labelledby="guideContentsHeading">
        <h2 id="guideContentsHeading">Contents</h2>
        <ol>
            <?php foreach ($guide as $topic): ?>
                <li><a href="#<?php echo e($topic['id']); ?>"><?php echo e($topic['title']); ?></a></li>
            <?php endforeach; ?>
        </ol>
    </nav>

    <?php foreach ($guide as $number => $topic): ?>
        <section class="info-card" aria-labelledby="<?php echo e($topic['id']); ?>">
            <h2 id="<?php echo e($topic['id']); ?>"><?php echo (int) $number + 1; ?>. <?php echo e($topic['title']); ?></h2>
            <ol>
                <?php foreach ($topic['steps'] as $step): ?>
                    <li><?php echo e($step); ?></li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endforeach; ?>

    <section class="info-card" aria-labelledby="guideMoreHeading">
        <h2 id="guideMoreHeading">Still stuck?</h2>
        <p>Try the <a href="faq.php">FAQ</a> or <a href="contact_us.php">contact the team</a>.</p>
    </section>

</div>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
