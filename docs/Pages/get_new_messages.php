<?php
session_start();
include("../backend/db.php");
include("../backend/content.php");

require_login();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$receiver_id = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : 0;
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

if($receiver_id <= 0){
    echo json_encode(array());
    exit();
}

$stmt = mysqli_prepare($conn, "
    SELECT id, sender_id, message, sent_at, envelope_id
    FROM messages
    WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
    AND id > ?
    ORDER BY sent_at ASC
");
mysqli_stmt_bind_param($stmt, 'iiiii', $user_id, $receiver_id, $receiver_id, $user_id, $last_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$new_messages = array();

while($row = mysqli_fetch_assoc($result)){
    $new_messages[] = array(
        'id' => $row['id'],
        'is_sender' => ($row['sender_id'] == $user_id),
        'message' => e($row['message']),
        'sent_at' => $row['sent_at'],
        'envelope_id' => $row['envelope_id']
    );
}

echo json_encode($new_messages);
?>