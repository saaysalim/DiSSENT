<?php
session_start();
include("../backend/db.php");
include("../backend/content.php");
require_once __DIR__ . "/../backend/messages.php";
require_once __DIR__ . "/../backend/contacts.php";

require_login();

$user_id = (int) $_SESSION['user_id'];

// Only the people this member is connected with (an accepted message request, or an
// earlier conversation). Nobody can browse or search all members.
// Newest conversation first; contacts without messages A-Z; deleted accounts last.
$contacts = array();
$requests = array();

try {
    $contacts = contacts_list($user_id);
    $requests = contacts_incoming_requests($user_id);
} catch (Throwable $error) {
    error_log('DiSSENT: contacts could not be loaded: ' . get_class($error));
}

$receiver_id = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$receiver = null;

// A conversation opens only with a contact. Any other id shows nothing, not even a name.
foreach ($contacts as $contact) {
    if ((int) $contact['id'] === $receiver_id && $receiver_id !== $user_id) {
        $receiver = $contact;
    }
}

if ($receiver === null) {
    $receiver_id = 0;
}

// A deleted account's conversation can be read but not replied to.
$receiver_deleted = $receiver !== null && $receiver['role'] === 'deleted';

$messages = array();
$cursor = '';
$load_failed = false;

if($receiver){
    try {
        // Read before the messages, so the first poll misses nothing.
        $cursor = messages_cursor();
        $messages = messages_visible($user_id, $receiver_id);
        // Opening the conversation marks its messages as read.
        messages_mark_read($user_id, $receiver_id);
    } catch (Throwable $error) {
        error_log('DiSSENT: conversation could not be loaded: ' . get_class($error));
        $load_failed = true;
    }
}

// New messages per contact (after marking the open conversation as read).
$unread = unread_message_counts($user_id);

// Error from send_message.php when JavaScript is off.
$message_error = $_SESSION['message_error'] ?? '';
$message_error = is_string($message_error) ? $message_error : '';
unset($_SESSION['message_error']);

// Answer from contact_request.php or contact_respond.php, and the form to correct.
$contact_notice = $_SESSION['contact_notice'] ?? null;
$contact_form = $_SESSION['contact_form'] ?? array();
unset($_SESSION['contact_notice'], $_SESSION['contact_form']);
$contact_notice = is_array($contact_notice) && count($contact_notice) === 2 ? $contact_notice : null;
$contact_form = is_array($contact_form) ? $contact_form : array();
$new_request_open = isset($_GET['new']) || $contact_form !== array() || ($contacts === array() && $requests === array());
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messaging - DiSSENT</title>
<meta name="description" content="Message your DiSSENT contacts. New contacts are added by message request; nobody can browse the list of members.">
<link rel="stylesheet" href="../assets/css/base.css?v=5">
<link rel="stylesheet" href="../assets/css/messaging.css?v=7">
</head>

<body>

<?php include("../includes/header.php"); ?>

<main id="main-content" tabindex="-1" class="messaging-page">

<section class="page-hero page-hero--compact" aria-labelledby="messagingHeading">
    <h1 id="messagingHeading">Secure Messaging</h1>
    <p>Communicate securely with trusted journalists, media organisations and verification partners.</p>
</section>

<?php if ($contact_notice !== null): ?>
    <div class="notice notice--<?php echo $contact_notice[0] === 'success' ? 'success' : 'error'; ?> messaging-notice" role="<?php echo $contact_notice[0] === 'success' ? 'status' : 'alert'; ?>"><?php echo e($contact_notice[1]); ?></div>
<?php endif; ?>

<!-- has-chat: on phones only the chat is shown, with an "All contacts" button. -->
<section class="message-container<?php echo $receiver ? ' has-chat' : ''; ?>" id="messageContainer"
    data-requests="<?php echo count($requests); ?>" data-contacts="<?php echo count($contacts); ?>">

    <div class="conversation-list">

        <!-- New contacts: by exact email address only. The answer is always the same. -->
        <details class="new-request" id="newRequest"<?php echo $new_request_open ? ' open' : ''; ?>>
            <summary>New message</summary>
            <?php $email_error = (string) ($contact_form['email_error'] ?? ''); ?>
            <!-- novalidate: messaging.js shows our own message next to the box instead of the browser's bubble. -->
            <form action="contact_request.php" method="POST" class="new-request__form" id="newRequestForm" novalidate>
                <?php echo csrf_field(); ?>
                <label for="requestEmail">Their email address</label>
                <input type="email" id="requestEmail" name="email" required maxlength="255"
                    autocomplete="off" autocapitalize="off" spellcheck="false" inputmode="email"
                    placeholder="name@example.com"
                    aria-describedby="requestEmailError requestEmailHelp"<?php echo $email_error !== '' ? ' aria-invalid="true"' : ''; ?>
                    value="<?php echo e((string) ($contact_form['email'] ?? '')); ?>">
                <p id="requestEmailError" class="new-request__error" role="alert"<?php echo $email_error !== '' ? '' : ' hidden'; ?>><?php echo e($email_error !== '' ? $email_error : CONTACT_EMAIL_INVALID); ?></p>
                <small id="requestEmailHelp">Type the full address exactly. DiSSENT does not search, suggest or show members.</small>
                <label for="requestMessage">First message (optional)</label>
                <textarea id="requestMessage" name="message" rows="3" maxlength="500"
                    aria-describedby="requestMessageHelp"><?php echo e((string) ($contact_form['message'] ?? '')); ?></textarea>
                <small id="requestMessageHelp">They see your name, professional category and this message, and choose whether to accept. Messages with personal details are checked by an administrator first.</small>
                <button type="submit" class="btn-primary">Send request</button>
            </form>
        </details>

        <?php if ($requests !== array()): ?>
            <section class="message-requests" aria-labelledby="requestsHeading">
                <h2 id="requestsHeading">Message requests <span class="request-count"><?php echo count($requests); ?></span></h2>
                <ul>
                    <?php foreach ($requests as $request):
                        $request_category = trim((string) $request['organisation']) !== '' ? (string) $request['organisation'] : 'Member';
                    ?>
                        <li class="request-item">
                            <strong><?php echo e($request['fullname']); ?></strong>
                            <small><?php echo e($request_category); ?></small>
                            <?php if ($request['message_status'] === 'delivered' && trim((string) $request['message']) !== ''): ?>
                                <p class="request-item__message"><?php echo e($request['message']); ?></p>
                            <?php elseif ($request['message_status'] === 'pending_review'): ?>
                                <p class="request-item__note">Their first message is being checked by an administrator.</p>
                            <?php endif; ?>
                            <form action="contact_respond.php" method="POST" class="request-item__actions">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                <button type="submit" name="decision" value="accept" class="btn-primary">Accept</button>
                                <button type="submit" name="decision" value="decline" class="btn-secondary">Decline</button>
                                <button type="submit" name="decision" value="block" class="request-block">Block</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="request-help">Only after you accept can you message each other. Declining or blocking is never shown to the sender.</p>
            </section>
        <?php endif; ?>

        <h2>Contacts</h2>

        <?php if ($contacts !== array()): ?>
            <label for="contactSearch" class="contact-search__label">Search your contacts</label>
        <?php endif; ?>
        <input type="search" id="contactSearch" class="contact-search" placeholder="Search your contacts by name" autocomplete="off"<?php echo $contacts === array() ? ' hidden' : ''; ?>>

        <?php if ($contacts === array()): ?>
            <p class="no-contacts">You have no contacts yet. To add someone, type their email address under New message.</p>
        <?php endif; ?>

        <div id="contactListItems">
            <?php foreach ($contacts as $contact) {
                // Professional category, not the account type (admin/user).
                $contact_category = $contact['role'] === 'deleted'
                    ? 'Account deleted'
                    : (trim((string) $contact['organisation']) !== '' ? (string) $contact['organisation'] : 'Member');
                $contact_unread = (int) ($unread[(int) $contact['id']] ?? 0);
            ?>

                <a href="messaging.php?user=<?php echo (int)$contact['id']; ?>#chatArea"
                class="contact-item <?php echo ($receiver_id == $contact['id']) ? 'active' : ''; ?>"
                data-id="<?php echo (int) $contact['id']; ?>"
                data-name="<?php echo e(mb_strtolower($contact['fullname'], 'UTF-8')); ?>"
                data-role="<?php echo e(mb_strtolower($contact_category, 'UTF-8')); ?>">

                    <?php echo avatar_small_html($contact['fullname'], $contact['profile_picture'] ?? '', 'contact-avatar'); ?>
                    <span class="contact-item__text">
                        <strong><?php echo e($contact['fullname']); ?></strong>
                        <small><?php echo e($contact_category); ?></small>
                    </span>
                    <span class="contact-unread"<?php echo $contact_unread > 0 ? '' : ' hidden'; ?>><?php echo min($contact_unread, 99); ?><span class="visually-hidden"> new</span></span>

                </a>

            <?php } ?>
        </div>

        <p id="noContactsFound" class="no-message" hidden>No contacts match your search. To add someone new, use their email address under New message.</p>

    </div>

    <div class="chat-area" id="chatArea" data-receiver-id="<?php echo (int) $receiver_id; ?>">

        <!-- Phones only: back to the contact list. -->
        <a class="chat-back" href="messaging.php">&larr; All contacts</a>

        <div class="chat-header">
            <div class="chat-avatar<?php echo $receiver ? ' chat-avatar--person' : ''; ?>" aria-hidden="true">
                <?php if ($receiver): ?>
                    <?php echo avatar_small_html($receiver['fullname'], $receiver['profile_picture'] ?? '', 'chat-photo'); ?>
                <?php else: ?>
                    <svg viewBox="0 0 24 24" width="26" height="26"
                         fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l-3 3V11.5A7.5 7.5 0 0 1 9.5 4h3a7.5 7.5 0 0 1 7.5 7.5Z"></path>
                    </svg>
                <?php endif; ?>
            </div>
            <div class="chat-heading-text">
                <h2><?php echo $receiver ? e($receiver['fullname']) : 'Your conversations'; ?></h2>
                <p><?php
                    if ($receiver_deleted) {
                        echo 'This account has been deleted. You can read your past messages, but you cannot reply.';
                    } elseif ($receiver) {
                        echo trim((string) $receiver['organisation']) !== '' ? e($receiver['organisation']) : 'Member';
                    } elseif ($contacts === array()) {
                        echo 'Your contacts appear here once someone accepts your request, or you accept theirs.';
                    } else {
                        echo 'Choose a contact to start a conversation.';
                    }
                ?></p>
            </div>
        </div>

        <div class="messages-box" id="messagesBox" data-cursor="<?php echo e($cursor); ?>">

            <?php if ($receiver): ?>
                <?php foreach ($messages as $msg): ?>
                    <?php echo message_html($msg, $user_id, $receiver['fullname']); ?>
                <?php endforeach; ?>

                <?php if ($load_failed): ?>
                    <div class="no-message" role="alert">Messages could not be loaded. Please try again later.</div>
                <?php elseif (empty($messages)): ?>
                    <div class="no-message">No messages yet. Start the conversation below.</div>
                <?php endif; ?>
            <?php endif; ?>

        </div>

        <?php if ($message_error !== ''): ?>
            <div class="notice notice--error" role="alert"><?php echo e($message_error); ?></div>
        <?php endif; ?>

        <form
    action="send_message.php"
    method="POST"
    enctype="multipart/form-data"
    class="message-form"
    id="messageForm"
>
    <?php echo csrf_field(); ?>

    <input
        type="hidden"
        name="receiver_id"
        value="<?php echo $receiver ? (int)$receiver_id : 0; ?>"
    >

    <div class="message-composer">
        <textarea
            id="messageText"
            name="message"
            rows="3"
            aria-label="Write a message"
            maxlength="5000"
            placeholder="<?php
                if ($receiver_deleted) {
                    echo 'You cannot reply to a deleted account.';
                } elseif ($receiver) {
                    echo 'Write a message…';
                } else {
                    echo 'Select a contact before writing a message.';
                }
            ?>"
            <?php if (!$receiver || $receiver_deleted): ?>disabled<?php endif; ?>
        ></textarea>

        <?php if ($receiver && !$receiver_deleted): ?>
            <div class="message-attach">
                <label for="messageFile" class="message-attach__label">Attach a file (optional)</label>
                <input
                    type="file"
                    id="messageFile"
                    name="attachment"
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                    aria-describedby="messageFileHelp"
                >
                <small id="messageFileHelp">
                    One JPG, PNG, WebP or PDF, up to 5 MB. Location data is removed from images, but hidden details in PDFs are not.
                    Messages with files or personal details may be read by an administrator before delivery.
                </small>
            </div>
        <?php endif; ?>

        <button
            type="submit"
            class="send-btn composer-send"
            aria-label="Send message"
            title="Send message"
            <?php if (!$receiver || $receiver_deleted): ?>disabled<?php endif; ?>
        >
            <svg
                viewBox="0 0 24 24"
                width="22"
                height="22"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
                focusable="false"
            >
                <path d="m22 2-7 20-4-9-9-4 20-7Z"></path>
                <path d="M22 2 11 13"></path>
            </svg>
        </button>
    </div>

</form>

    </div>

</section>

</main>

<?php include("../includes/footer.php"); ?>

<script src="../assets/JavaScript/messaging.js?v=4"></script>
</body>
</html>