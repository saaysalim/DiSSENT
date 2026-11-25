<?php
session_start();
include("../backend/db.php");
include("../backend/content.php");
require_once __DIR__ . "/../backend/messages.php";

require_login();

$user_id = (int) $_SESSION['user_id'];

// Active members, plus deleted accounts you already have a conversation with
// (so you can still read it). Deleted accounts are listed last.
$stmt = mysqli_prepare($conn, "
    SELECT u.id, u.fullname, u.role
    FROM users u
    WHERE u.id != ?
      AND (
          u.role <> 'deleted'
          OR EXISTS (
              SELECT 1 FROM messages m
              WHERE (m.sender_id = u.id AND m.receiver_id = ? AND m.status = 'delivered')
                 OR (m.sender_id = ? AND m.receiver_id = u.id)
          )
      )
    ORDER BY (u.role = 'deleted') ASC, u.fullname ASC
");
mysqli_stmt_bind_param($stmt, 'iii', $user_id, $user_id, $user_id);
mysqli_stmt_execute($stmt);
$contacts = mysqli_stmt_get_result($stmt);

$receiver_id = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$receiver = null;

if($receiver_id > 0 && $receiver_id !== $user_id){
    // Only the columns the page needs (never the password hash).
    $stmt = mysqli_prepare($conn, "SELECT id, fullname, role FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $receiver_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $receiver = mysqli_fetch_assoc($result) ?: null;
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
    } catch (Throwable $error) {
        error_log('DiSSENT: conversation could not be loaded: ' . get_class($error));
        $load_failed = true;
    }
}

// Error from send_message.php when JavaScript is off.
$message_error = $_SESSION['message_error'] ?? '';
$message_error = is_string($message_error) ? $message_error : '';
unset($_SESSION['message_error']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messaging - DiSSENT</title>
<link rel="stylesheet" href="../assets/css/base.css">
<link rel="stylesheet" href="../assets/css/messaging.css?v=2">
</head>

<body>

<?php include("../includes/header.php"); ?>

<section class="page-hero" aria-labelledby="messagingHeading">
    <h1 id="messagingHeading">Secure Messaging</h1>
    <p>Communicate securely with trusted journalists, media organisations and verification partners.</p>
</section>

<section class="message-container">

    <div class="conversation-list">

        <h2>Contacts</h2>

        <label for="contactSearch" class="visually-hidden">Search contacts</label>
        <input type="search" id="contactSearch" class="contact-search" placeholder="Name or role..." autocomplete="off">

        <div id="contactListItems">
            <?php while($contact = mysqli_fetch_assoc($contacts)){ ?>

                <a href="messaging.php?user=<?php echo (int)$contact['id']; ?>#chatArea"
                class="contact-item <?php echo ($receiver_id == $contact['id']) ? 'active' : ''; ?>"
                data-name="<?php echo e(mb_strtolower($contact['fullname'], 'UTF-8')); ?>"
                data-role="<?php echo e(mb_strtolower($contact['role'] === 'deleted' ? 'account deleted' : $contact['role'], 'UTF-8')); ?>">

                    <strong><?php echo e($contact['fullname']); ?></strong>
                    <small><?php echo $contact['role'] === 'deleted' ? 'Account deleted' : e($contact['role']); ?></small>

                </a>

            <?php } ?>
        </div>

        <p id="noContactsFound" class="no-message" hidden>No contacts match your search.</p>

    </div>

    <div class="chat-area" id="chatArea">

        <div class="chat-header">
            <div class="chat-avatar" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="26" height="26"
                     fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l-3 3V11.5A7.5 7.5 0 0 1 9.5 4h3a7.5 7.5 0 0 1 7.5 7.5Z"></path>
                </svg>
            </div>
            <div class="chat-heading-text">
                <h2><?php echo $receiver ? e($receiver['fullname']) : 'Your conversations'; ?></h2>
                <p><?php
                    if ($receiver_deleted) {
                        echo 'This account has been deleted. You can read your past messages, but you cannot reply.';
                    } elseif ($receiver) {
                        echo 'Conversation with ' . e($receiver['fullname']);
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

<?php include("../includes/footer.php"); ?>

<script>
(function () {
    const chatArea = document.getElementById('chatArea');
    const contactList = document.getElementById('contactListItems');
    const search = document.getElementById('contactSearch');

    let receiverId = <?php echo (int)$receiver_id; ?>;
    let version = 0;
    let navigation = null;
    let loading = false;
    let polling = false;
    let sending = false;

    const notice = document.createElement('p');
    notice.setAttribute('role', 'status');
    search.insertAdjacentElement('afterend', notice);

    function messageBox() {
        return chatArea.querySelector('#messagesBox');
    }

    function scrollMessages() {
        const box = messageBox();
        if (box) box.scrollTop = box.scrollHeight;
    }

    function relativeTime(value) {
        const date = new Date(value.replace(' ', 'T') + 'Z');
        if (Number.isNaN(date.getTime())) return value;

        const seconds = Math.max(0, (Date.now() - date.getTime()) / 1000);

        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + ' min ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + ' hr ago';
        if (seconds < 604800) return Math.floor(seconds / 86400) + ' day(s) ago';

        return date.toLocaleString('en-GB');
    }

    function updateTimes() {
        chatArea.querySelectorAll('.msg-time').forEach(function (item) {
            item.textContent = relativeTime(item.dataset.time);
        });
    }

    function markSelectedContact() {
        contactList.querySelectorAll('.contact-item').forEach(function (link) {
            const id = Number(new URL(link.href).searchParams.get('user'));
            link.classList.toggle('active', id === receiverId);
        });
    }

    // Keep contact searching working without reloading.
    search.addEventListener('input', function () {
        const query = search.value.trim().toLowerCase();
        let visible = 0;

        contactList.querySelectorAll('.contact-item').forEach(function (item) {
            const matches =
                item.dataset.name.includes(query) ||
                item.dataset.role.includes(query);

            item.hidden = !matches;
            if (matches) visible++;
        });

        document.getElementById('noContactsFound').hidden = visible > 0;
    });

    // Fetch the selected conversation, then replace only the chat contents.
    async function openConversation(url, addHistory) {
        if (navigation) navigation.abort();

        navigation = new AbortController();
        const thisVersion = ++version;

        loading = true;
        notice.textContent = 'Loading conversation…';
        chatArea.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url.href, {
                signal: navigation.signal,
                cache: 'no-store'
            });

            if (!response.ok) throw new Error('Conversation request failed.');

            const html = await response.text();
            if (thisVersion !== version) return;

            const page = new DOMParser().parseFromString(html, 'text/html');
            const replacement = page.getElementById('chatArea');

            if (!replacement) {
                throw new Error('Could not load the conversation. Your login may have expired.');
            }

            chatArea.innerHTML = replacement.innerHTML;

            const receiverField = chatArea.querySelector('[name="receiver_id"]');
            receiverId = receiverField ? Number(receiverField.value) : 0;

            if (addHistory) {
                history.pushState(null, '', url.pathname + url.search + '#chatArea');
            }

            markSelectedContact();
            updateTimes();
            scrollMessages();
            notice.textContent = '';
        } catch (error) {
            if (error.name !== 'AbortError' && thisVersion === version) {
                notice.textContent = 'Could not switch contacts. Please try again.';
                console.error(error);
            }
        } finally {
            if (thisVersion === version) {
                loading = false;
                chatArea.removeAttribute('aria-busy');
                pollSecureStatus();
            }
        }
    }

    contactList.addEventListener('click', function (event) {
        const link = event.target.closest('a.contact-item');

        // Preserve Ctrl-click and other normal browser shortcuts.
        if (!link || event.button !== 0 || event.ctrlKey ||
            event.metaKey || event.shiftKey || event.altKey) return;

        event.preventDefault();
        openConversation(new URL(link.href), true);
    });

    // Support the browser's Back and Forward buttons.
    window.addEventListener('popstate', function () {
        openConversation(new URL(window.location.href), false);
    });

    // Delegation keeps these handlers working after switching contacts.
    chatArea.addEventListener('keydown', function (event) {
        if (!event.target.matches('textarea[name="message"]')) return;
        if (event.isComposing || event.keyCode === 229) return;

        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();

            if (!event.repeat && !loading) {
                event.target.form.requestSubmit();
            }
        }
    });

    chatArea.addEventListener('submit', async function (event) {
        const form = event.target;
        if (!form.matches('#messageForm')) return;
        event.preventDefault();

        if (loading || sending || receiverId <= 0) return;

        const textarea = form.querySelector('textarea');
        const fileInput = form.querySelector('input[type="file"]');
        const file = fileInput && fileInput.files[0];

        if (!textarea.value.trim() && !file) {
            textarea.setCustomValidity('Please type a message or attach a file.');
            textarea.reportValidity();
            return;
        }
        if (file && file.size > 5 * 1024 * 1024) {
            showFormNotice('The file is too large. The limit is 5 MB.');
            return;
        }

        const body = new FormData(form);
        const sentTo = receiverId;
        const thisVersion = ++version;
        const button = form.querySelector('button[type="submit"]');
        const hadFocus = form.contains(document.activeElement);

        sending = true;
        textarea.readOnly = true;
        button.disabled = true;
        form.setAttribute('aria-busy', 'true');
        showFormNotice('');
        notice.textContent = 'Sending message…';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            });
            const type = response.headers.get('Content-Type') || '';
            if (!type.includes('application/json')) {
                throw new Error('Unexpected reply from the server.');
            }

            const result = await response.json();

            if (!result.ok) {
                notice.textContent = '';
                showFormNotice(result.error || 'Your message could not be sent.');
                return;
            }

            notice.textContent = result.held ? 'Message saved. It is waiting for review.' : '';
            if (thisVersion !== version || receiverId !== sentTo) return;

            applyMessages([{ id: result.id, html: result.html }]);
            form.reset();
            textarea.setCustomValidity('');
            updateTimes();
            scrollMessages();
            if (hadFocus) textarea.focus({ preventScroll: true });
        } catch (error) {
            notice.textContent = '';
            showFormNotice('Could not confirm sending. Check the conversation before trying again; the message may already have been saved.');
            console.error('Send confirmation failed:', error);
        } finally {
            sending = false;
            if (form.isConnected) {
                textarea.readOnly = false;
                button.disabled = false;
                form.removeAttribute('aria-busy');
            }
            pollMessages();
            pollSecureStatus();
        }
    });

    chatArea.addEventListener('input', function (event) {
        const textarea = chatArea.querySelector('textarea[name="message"]');
        if (textarea) textarea.setCustomValidity('');
    });

    async function pollSecureStatus() {
        if (loading) return;

        const thisVersion = version;

        chatArea.querySelectorAll('[data-envelope-id]').forEach(async function (item) {
            if (item.dataset.secureResolved === 'true' ||
                item.dataset.checking === 'true') return;

            item.dataset.checking = 'true';

            try {
                const response = await fetch(
                    'message_status.php?envelope_id=' +
                    encodeURIComponent(item.dataset.envelopeId),
                    { cache: 'no-store' }
                );

                if (!response.ok) return;
                const result = await response.json();

                if (thisVersion !== version || !item.isConnected) return;

                const badge = item.querySelector('.secure-badge');
                if (!badge) return;

                if (result.status === 'verified') {
                    badge.textContent = 'Secure Transfer: Verified ✓';
                    badge.classList.add('secure-verified');
                    item.dataset.secureResolved = 'true';
                } else if (result.status === 'failed') {
                    badge.textContent = 'Secure Transfer: Failed';
                    badge.classList.add('secure-failed');
                    item.dataset.secureResolved = 'true';
                }
            } catch (error) {
                console.error('Status check failed:', error);
            } finally {
                delete item.dataset.checking;
            }
        });
    }

    // Message next to the form (errors only).
    function showFormNotice(text) {
        const form = chatArea.querySelector('#messageForm');
        let box = chatArea.querySelector('.message-form-notice');

        if (!text) {
            if (box) box.remove();
            return;
        }
        if (!box) {
            box = document.createElement('div');
            box.className = 'notice notice--error message-form-notice';
            box.setAttribute('role', 'alert');
            form.insertAdjacentElement('beforebegin', box);
        }
        box.textContent = text;
    }

    // Adds new bubbles and redraws changed ones. The HTML is escaped by PHP (message_html()).
    function applyMessages(items) {
        const box = messageBox();
        if (!box) return;

        items.forEach(function (item) {
            const template = document.createElement('template');
            template.innerHTML = item.html || '';
            const bubble = template.content.firstElementChild;
            if (!bubble) return;

            const existing = box.querySelector('[data-id="' + Number(item.id) + '"]');

            if (existing) {
                if (existing.dataset.rev !== bubble.dataset.rev) existing.replaceWith(bubble);
                return;
            }

            const placeholder = box.querySelector('.no-message');
            if (placeholder) placeholder.remove();
            box.appendChild(bubble);
        });
    }

    async function pollMessages() {
        if (loading || sending || polling || receiverId <= 0) return;

        polling = true;
        const thisVersion = version;
        const box = messageBox();

        try {
            const response = await fetch(
                'get_new_messages.php?receiver_id=' + receiverId +
                '&since=' + encodeURIComponent(box.dataset.cursor || ''),
                { cache: 'no-store' }
            );

            if (!response.ok) return;
            const result = await response.json();

            // Ignore replies belonging to a previously selected contact.
            if (thisVersion !== version || loading || !Array.isArray(result.items)) return;

            const nearBottom =
                box.scrollHeight - box.scrollTop - box.clientHeight < 100;

            applyMessages(result.items);
            box.dataset.cursor = result.cursor;
            updateTimes();

            if (nearBottom) scrollMessages();
            pollSecureStatus();
        } catch (error) {
            console.error('Message check failed:', error);
        } finally {
            polling = false;
        }
    }

    updateTimes();
    scrollMessages();
    pollSecureStatus();

    // Position the conversation on the initial page load.
    if (receiverId > 0) {
        requestAnimationFrame(function () {
            chatArea.scrollIntoView({ behavior: 'auto', block: 'start' });
        });
    }

    setInterval(updateTimes, 30000);
    setInterval(pollMessages, 4000);
    setInterval(pollSecureStatus, 4000);
})();
</script>
</body>
</html>