<?php
session_start();
include("../backend/db.php");
include("../backend/content.php");

require_login();

// Process only POST requests
if($_SERVER["REQUEST_METHOD"] == "POST"){

    verify_csrf();

    $sender_id = $_SESSION['user_id'];
    $receiver_id = intval($_POST['receiver_id']);
    $message = trim($_POST['message']);

    if(!empty($message) && $receiver_id > 0){

        $stmt = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'iis', $sender_id, $receiver_id, $message);
        mysqli_stmt_execute($stmt);

        // Best-effort: also push through the encrypt/sign/publish/verify bridge for the
        // "Secure Transfer" badge. The message itself is already saved above either way —
        // if the Java API is unreachable, delivery still succeeds, just without that badge.
        $inserted_id = mysqli_insert_id($conn);
        $envelope_id = mc_send_secure($sender_id, $receiver_id, $message);

        if($envelope_id){
            $update_stmt = mysqli_prepare($conn, "UPDATE messages SET envelope_id = ? WHERE id = ?");
            mysqli_stmt_bind_param($update_stmt, 'si', $envelope_id, $inserted_id);
            mysqli_stmt_execute($update_stmt);
        }

    }

    header("Location: messaging.php?user=" . $receiver_id);
    exit();

}else{

    header("Location: messaging.php");
    exit();

}
?>