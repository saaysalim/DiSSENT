<?php
session_start();
include("../backend/db.php");
include("../backend/content.php");

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

// This is polled by messaging.php: answer with JSON, never a login redirect.
$user_id = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT,
    array('options' => array('min_range' => 1))
);

if ($user_id === false) {
    http_response_code(401);
    echo json_encode(array());
    exit();
}

// The session is not changed here; release its lock for other requests.
session_write_close();

$receiver_id = isset($_GET['receiver_id']) && is_string($_GET['receiver_id']) ? (int) $_GET['receiver_id'] : 0;
$last_id = isset($_GET['last_id']) && is_string($_GET['last_id']) ? max(0, (int) $_GET['last_id']) : 0;

if ($receiver_id <= 0) {
    echo json_encode(array());
    exit();
}

try {
    $stmt = mysqli_prepare($conn, "
        SELECT id, sender_id, message, sent_at, envelope_id
        FROM messages
        WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
        AND id > ?
        ORDER BY sent_at ASC, id ASC
    ");
    mysqli_stmt_bind_param($stmt, 'iiiii', $user_id, $receiver_id, $receiver_id, $user_id, $last_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $new_messages = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $new_messages[] = array(
            'id' => (int) $row['id'],
            'is_sender' => ((int) $row['sender_id'] === $user_id),
            // Escaped here; messaging.php decodes it and inserts it as plain text.
            'message' => e($row['message']),
            'sent_at' => $row['sent_at'],
            'envelope_id' => $row['envelope_id']
        );
    }

    mysqli_stmt_close($stmt);
    echo json_encode($new_messages);
} catch (Throwable $error) {
    error_log('DiSSENT: loading new messages failed: ' . get_class($error));
    http_response_code(503);
    echo json_encode(array());
}
