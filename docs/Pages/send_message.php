<?php
session_start();
include("../backend/db.php");
include("../backend/content.php");

require_login();

// Process only POST requests
if($_SERVER["REQUEST_METHOD"] !== "POST"){
    header("Location: messaging.php");
    exit();
}

verify_csrf();

$sender_id = (int) $_SESSION['user_id'];
$receiver_id = is_string($_POST['receiver_id'] ?? null) ? (int) $_POST['receiver_id'] : 0;
$message = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';

$message_length = function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') : strlen($message);

if($message !== '' && $message_length <= 5000 && $receiver_id > 0 && $receiver_id !== $sender_id){

    try {
        // Only send to an existing, active account (not yourself, not a deleted account).
        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE id = ? AND role <> 'deleted' LIMIT 1");
        mysqli_stmt_bind_param($check, 'i', $receiver_id);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);
        $receiver_ok = mysqli_stmt_num_rows($check) === 1;
        mysqli_stmt_close($check);

        if($receiver_ok){
            $stmt = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'iis', $sender_id, $receiver_id, $message);
            mysqli_stmt_execute($stmt);
            $inserted_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // Best-effort: also push through the encrypt/sign/publish/verify bridge for the
            // "Secure Transfer" badge. The message itself is already saved above either way —
            // if the Java API is unreachable, delivery still succeeds, just without that badge.
            $envelope_id = mc_send_secure($sender_id, $receiver_id, $message);

            if($envelope_id){
                $update_stmt = mysqli_prepare($conn, "UPDATE messages SET envelope_id = ? WHERE id = ?");
                mysqli_stmt_bind_param($update_stmt, 'si', $envelope_id, $inserted_id);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            }
        }
    } catch (Throwable $error) {
        // Never log message text. The conversation page shows what was saved.
        error_log('DiSSENT: sending a message failed: ' . get_class($error));
    }

}

header("Location: messaging.php?user=" . $receiver_id);
exit();
