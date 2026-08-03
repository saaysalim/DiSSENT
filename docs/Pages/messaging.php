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

                <a href="messaging.php?user=<?php echo (int)$contact['id']; ?>"
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

    <div class="chat-area">

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
    // ---- Contact search filter ----
    document.getElementById('contactSearch').addEventListener('input', function(){
        var query = this.value.trim().toLowerCase();
        var items = document.querySelectorAll('#contactListItems .contact-item');
        var visibleCount = 0;

        items.forEach(function(item){
            var name = item.getAttribute('data-name');
            var role = item.getAttribute('data-role');

            if(name.indexOf(query) !== -1 || role.indexOf(query) !== -1){
                item.style.display = '';
                visibleCount++;
            }else{
                item.style.display = 'none';
            }
        });

        document.getElementById('noContactsFound').style.display = (visibleCount === 0) ? 'block' : 'none';
    });

    // ---- Relative time formatting ("Just now", "5 minutes ago", etc.) ----
    function formatRelativeTime(dateString){
        var then = new Date(dateString.replace(' ', 'T'));
        var now = new Date();
        var seconds = Math.floor((now - then) / 1000);

        if(seconds < 60) return "Just now";
        if(seconds < 3600) return Math.floor(seconds / 60) + " min ago";
        if(seconds < 86400) return Math.floor(seconds / 3600) + " hr ago";
        if(seconds < 604800) return Math.floor(seconds / 86400) + " day(s) ago";

        // Older than a week — show the actual date
        return then.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
               ', ' + then.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    }

    function updateAllTimestamps(){
        document.querySelectorAll('.msg-time').forEach(function(el){
            el.textContent = formatRelativeTime(el.getAttribute('data-time'));
        });
    }

    updateAllTimestamps();
    setInterval(updateAllTimestamps, 30000); // refresh relative times every 30s

    // ---- Secure Transfer badges: poll message_status.php until each envelope resolves ----
    function pollSecureStatus(){
        document.querySelectorAll('[data-envelope-id]').forEach(function(el){
            if(el.dataset.secureResolved === 'true') return;

            fetch('message_status.php?envelope_id=' + encodeURIComponent(el.getAttribute('data-envelope-id')))
                .then(function(response){ return response.json(); })
                .then(function(status){
                    var badge = el.querySelector('.secure-badge');
                    if(!badge) return;

                    if(status.status === 'verified'){
                        badge.textContent = 'Secure Transfer: Verified ✓';
                        badge.classList.add('secure-verified');
                        el.dataset.secureResolved = 'true';
                    }else if(status.status === 'failed'){
                        badge.textContent = 'Secure Transfer: Failed';
                        badge.classList.add('secure-failed');
                        el.dataset.secureResolved = 'true';
                    }
                })
                .catch(function(err){ console.error('Secure status polling error:', err); });
        });
    }

    pollSecureStatus();
    setInterval(pollSecureStatus, 4000);

    // ---- Near-real-time polling for new messages ----
    var box = document.getElementById('messagesBox');
    var receiverId = <?php echo $receiver_id ? (int)$receiver_id : 0; ?>;
    var lastId = <?php echo (int)$last_message_id; ?>;
    var currentUserName = "You";

    if(box){
        box.scrollTop = box.scrollHeight;
    }

    if(receiverId > 0){

        setInterval(function(){

            fetch('get_new_messages.php?receiver_id=' + receiverId + '&last_id=' + lastId)
                .then(function(response){ return response.json(); })
                .then(function(newMessages){

                    if(newMessages.length === 0) return;

                    newMessages.forEach(function(msg){

                        var div = document.createElement('div');
                        div.className = msg.is_sender ? 'sent-message' : 'received-message';
                        div.setAttribute('data-id', msg.id);
                        if(msg.envelope_id){
                            div.setAttribute('data-envelope-id', msg.envelope_id);
                        }

                        var senderLabel = msg.is_sender ? currentUserName : document.querySelector('.chat-header h2').textContent.trim();

                        div.innerHTML =
                            '<strong>' + senderLabel + '</strong>' +
                            '<p>' + msg.message + '</p>' +
                            '<small class="msg-time" data-time="' + msg.sent_at + '">' + formatRelativeTime(msg.sent_at) + '</small>' +
                            (msg.envelope_id ? '<small class="secure-badge">Secure Transfer: Pending</small>' : '');

                        // Remove "no messages yet" placeholder if present
                        var placeholder = box.querySelector('.no-message');
                        if(placeholder) placeholder.remove();

                        box.appendChild(div);
                        lastId = msg.id;
                    });

                    box.scrollTop = box.scrollHeight;
                    pollSecureStatus();
                })
                .catch(function(err){ console.error('Polling error:', err); });

        }, 4000); // check every 4 seconds

    }

    // ---- Clear textarea after sending (small UX touch) ----
    var form = document.getElementById('messageForm');
    if(form){
        form.addEventListener('submit', function(){
            setTimeout(function(){ form.querySelector('textarea').value = ''; }, 100);
        });
    }
</script>

</body>
</html>