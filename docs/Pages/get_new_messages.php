<?php
// New and changed messages of one conversation, for the chat page (polled).
session_start();
require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';
require_once __DIR__ . '/../backend/messages.php';
require_once __DIR__ . '/../backend/contacts.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

// Answer with JSON, never a login redirect.
$user_id = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT,
    array('options' => array('min_range' => 1))
);

if ($user_id === false || $user_id === null) {
    http_response_code(401);
    echo json_encode(array('items' => array()));
    exit();
}

// The session is not changed here; release its lock for other requests.
session_write_close();

$receiver_id = is_string($_GET['receiver_id'] ?? null) ? (int) $_GET['receiver_id'] : 0;
$since = is_string($_GET['since'] ?? null) ? $_GET['since'] : '';

// Unread messages per contact, plus waiting message requests (counted in the menu badge).
$counts = static function () use ($user_id) {
    $unread = unread_message_counts($user_id);
    $requests = message_request_count($user_id);

    return array('unread' => (object) $unread, 'requests' => $requests, 'contacts' => contacts_count($user_id), 'unread_total' => array_sum($unread) + $requests);
};

// receiver_id=0: only the unread counts (contact list without an open chat).
if ($receiver_id === 0) {
    echo json_encode(array('items' => array()) + $counts());
    exit();
}

if ($receiver_id < 0 || preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3,6}\z/', $since) !== 1) {
    http_response_code(400);
    echo json_encode(array('items' => array()));
    exit();
}

try {
    // Only a contact's conversation (and their name) can be read.
    if (!contacts_connected($user_id, $receiver_id)) {
        http_response_code(404);
        echo json_encode(array('items' => array()) + $counts());
        exit();
    }

    // Taken before reading, so nothing that changes in between is missed.
    $cursor = messages_cursor();
    $other = messages_rows('SELECT fullname FROM users WHERE id = ?', 'i', array($receiver_id));
    $items = array();

    foreach (messages_visible($user_id, $receiver_id, 'AND m.changed_at >= ?', 's', array($since)) as $row) {
        $items[] = array(
            'id' => (int) $row['id'],
            'rev' => message_rev($row),
            'html' => message_html($row, $user_id, $other[0]['fullname'] ?? ''),
        );
    }

    // The conversation is open, so what arrived is read now.
    messages_mark_read($user_id, $receiver_id);
    echo json_encode(array('cursor' => $cursor, 'items' => $items) + $counts(), JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    error_log('DiSSENT: loading new messages failed: ' . get_class($error));
    http_response_code(503);
    echo json_encode(array('items' => array()));
}
