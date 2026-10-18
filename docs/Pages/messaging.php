<?php
session_start();
include("../backend/db.php");
include("../backend/content.php");

require_login();

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT id, fullname, role FROM users WHERE id != ? ORDER BY fullname ASC");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$contacts = mysqli_stmt_get_result($stmt);

$receiver_id = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$receiver = null;

if($receiver_id){
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $receiver_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $receiver = mysqli_fetch_assoc($result);
}

$messages = null;
$last_message_id = 0;

if($receiver){
    $stmt = mysqli_prepare($conn, "
        SELECT * FROM messages
        WHERE (sender_id = ? AND receiver_id = ?)
        OR (sender_id = ? AND receiver_id = ?)
        ORDER BY sent_at ASC
    ");
    mysqli_stmt_bind_param($stmt, 'iiii', $user_id, $receiver_id, $receiver_id, $user_id);
    mysqli_stmt_execute($stmt);
    $messages = mysqli_stmt_get_result($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messaging - DiSSENT</title>
<link rel="stylesheet" href="../assets/css/base.css">
<link rel="stylesheet" href="../assets/css/messaging.css">
</head>

<body>

<?php include("../includes/header.php"); ?>

<section class="message-hero">
    <h1>Secure Messaging</h1>
    <p>Communicate securely with trusted journalists, media organisations and verification partners.</p>
</section>

<section class="message-container">

    <div class="conversation-list">

        <h2>Contacts</h2>

        <input type="text" id="contactSearch" class="contact-search" placeholder="Search by name or role...">

        <div id="contactListItems">
            <?php while($contact = mysqli_fetch_assoc($contacts)){ ?>

                <a href="messaging.php?user=<?php echo (int)$contact['id']; ?>#chatArea"
                class="contact-item <?php echo ($receiver_id == $contact['id']) ? 'active' : ''; ?>"
                data-name="<?php echo strtolower(e($contact['fullname'])); ?>"
                data-role="<?php echo strtolower(e($contact['role'])); ?>">

                    <strong><?php echo e($contact['fullname']); ?></strong>
                    <br>
                    <small><?php echo e($contact['role']); ?></small>

                </a>

            <?php } ?>
        </div>

        <p id="noContactsFound" class="no-message" style="display:none;">No contacts match your search.</p>

    </div>

    <div class="chat-area" id="chatArea" style="scroll-margin-top: 100px;">

        <?php if($receiver){ ?>
            <div class="chat-header">
                <h2><?php echo e($receiver['fullname']); ?></h2>
                <p><?php echo e($receiver['role']); ?></p>
            </div>
        <?php }else{ ?>
            <div class="chat-header">
                <h2>Select a contact</h2>
                <p>Choose a journalist or organisation from the left panel to start messaging.</p>
            </div>
        <?php } ?>

        <div class="messages-box" id="messagesBox">

            <?php if($receiver){ ?>

                <?php if(mysqli_num_rows($messages) > 0){ ?>

                    <?php while($msg = mysqli_fetch_assoc($messages)){
                        $last_message_id = $msg['id'];
                    ?>

                        <?php if($msg['sender_id'] == $user_id){ ?>

                            <div class="sent-message" data-id="<?php echo (int)$msg['id']; ?>"<?php echo !empty($msg['envelope_id']) ? ' data-envelope-id="' . e($msg['envelope_id']) . '"' : ''; ?>>
                                <strong>You</strong>
                                <p><?php echo e($msg['message']); ?></p>
                                <small class="msg-time" data-time="<?php echo e($msg['sent_at']); ?>"><?php echo e(date('d M Y, g:i a', strtotime($msg['sent_at']))); ?></small>
                                <?php if(!empty($msg['envelope_id'])){ ?>
                                    <small class="secure-badge">Secure Transfer: Pending</small>
                                <?php } ?>
                            </div>

                        <?php }else{ ?>

                            <div class="received-message" data-id="<?php echo (int)$msg['id']; ?>"<?php echo !empty($msg['envelope_id']) ? ' data-envelope-id="' . e($msg['envelope_id']) . '"' : ''; ?>>
                                <strong><?php echo e($receiver['fullname']); ?></strong>
                                <p><?php echo e($msg['message']); ?></p>
                                <small class="msg-time" data-time="<?php echo e($msg['sent_at']); ?>"><?php echo e(date('d M Y, g:i a', strtotime($msg['sent_at']))); ?></small>
                                <?php if(!empty($msg['envelope_id'])){ ?>
                                    <small class="secure-badge">Secure Transfer: Pending</small>
                                <?php } ?>
                            </div>

                        <?php } ?>

                    <?php } ?>

                <?php }else{ ?>

                    <div class="no-message">No messages yet. Start the conversation below.</div>

                <?php } ?>

            <?php } ?>

        </div>

        <?php if($receiver){ ?>

            <form action="send_message.php" method="POST" class="message-form" id="messageForm">

                <?php echo csrf_field(); ?>
                <input type="hidden" name="receiver_id" value="<?php echo (int)$receiver_id; ?>">

                <textarea name="message" rows="5" placeholder="Type your secure message here..." required></textarea>

                <button type="submit" class="send-btn">Send Message</button>

            </form>

        <?php } ?>

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

    const notice = document.createElement('p');
    notice.setAttribute('role', 'status');
    search.insertAdjacentElement('afterend', notice);

    function messageBox() {
        return chatArea.querySelector('#messagesBox');
    }

    function lastMessageId() {
        let highest = 0;

        chatArea.querySelectorAll('[data-id]').forEach(function (item) {
            highest = Math.max(highest, Number(item.dataset.id) || 0);
        });

        return highest;
    }

    function scrollMessages() {
        const box = messageBox();
        if (box) box.scrollTop = box.scrollHeight;
    }

    function relativeTime(value) {
        const date = new Date(value.replace(' ', 'T'));
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

            item.style.display = matches ? '' : 'none';
            if (matches) visible++;
        });

        document.getElementById('noContactsFound').style.display =
            visible ? 'none' : 'block';
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

    chatArea.addEventListener('submit', function (event) {
        const form = event.target;
        if (!form.matches('#messageForm')) return;

        if (loading) {
            event.preventDefault();
            return;
        }

        const textarea = form.querySelector('textarea');

        if (!textarea.value.trim()) {
            event.preventDefault();
            textarea.setCustomValidity('Please type a message first.');
            textarea.reportValidity();
        }
    });

    chatArea.addEventListener('input', function (event) {
        if (event.target.matches('textarea[name="message"]')) {
            event.target.setCustomValidity('');
        }
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

    // The PHP endpoint already HTML-escapes the message text.
    function decodeMessage(value) {
        const decoder = document.createElement('textarea');
        decoder.innerHTML = String(value);
        return decoder.value;
    }

    async function pollMessages() {
        if (loading || polling || receiverId <= 0) return;

        polling = true;
        const thisVersion = version;
        const box = messageBox();

        try {
            const response = await fetch(
                'get_new_messages.php?receiver_id=' + receiverId +
                '&last_id=' + lastMessageId(),
                { cache: 'no-store' }
            );

            if (!response.ok) return;
            const messages = await response.json();

            // Ignore replies belonging to a previously selected contact.
            if (thisVersion !== version || loading || !Array.isArray(messages)) return;

            const nearBottom =
                box.scrollHeight - box.scrollTop - box.clientHeight < 100;

            const existingIds = new Set(
                Array.from(box.querySelectorAll('[data-id]'),
                    item => Number(item.dataset.id))
            );

            messages.forEach(function (message) {
                const id = Number(message.id);
                if (!id || existingIds.has(id)) return;

                const item = document.createElement('div');
                item.className = message.is_sender ? 'sent-message' : 'received-message';
                item.dataset.id = id;

                const label = document.createElement('strong');
                label.textContent = message.is_sender
                    ? 'You'
                    : chatArea.querySelector('.chat-header h2').textContent.trim();

                const text = document.createElement('p');
                text.textContent = decodeMessage(message.message);

                const time = document.createElement('small');
                time.className = 'msg-time';
                time.dataset.time = message.sent_at;
                time.textContent = relativeTime(message.sent_at);

                item.append(label, text, time);

                if (message.envelope_id) {
                    item.dataset.envelopeId = message.envelope_id;

                    const badge = document.createElement('small');
                    badge.className = 'secure-badge';
                    badge.textContent = 'Secure Transfer: Pending';
                    item.appendChild(badge);
                }

                const placeholder = box.querySelector('.no-message');
                if (placeholder) placeholder.remove();

                box.appendChild(item);
                existingIds.add(id);
            });

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