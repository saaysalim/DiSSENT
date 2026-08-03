<?php
session_start();
include("../backend/db.php");
include("../backend/content.php");

require_login();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$envelope_id = isset($_GET['envelope_id']) ? trim($_GET['envelope_id']) : '';

if($envelope_id === ''){
    echo json_encode(array('status' => 'unknown'));
    exit();
}

// Only report status for envelopes attached to a message the current user actually
// sent or received — otherwise any logged-in user could probe arbitrary envelope ids.
$stmt = mysqli_prepare($conn, "SELECT id FROM messages WHERE envelope_id = ? AND (sender_id = ? OR receiver_id = ?)");
mysqli_stmt_bind_param($stmt, 'sii', $envelope_id, $user_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result) === 0){
    echo json_encode(array('status' => 'unknown'));
    exit();
}

echo json_encode(mc_get_status($envelope_id));
?>
