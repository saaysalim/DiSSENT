<?php
/*
 * Sends one message attachment, after checking who is asking.
 * Allowed: the sender; the receiver once the message is delivered; and an
 * administrator who is not part of the conversation, only while it waits for review.
 */
session_start();
require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';
require_once __DIR__ . '/../backend/messages.php';

require_login();

$user_id = (int) $_SESSION['user_id'];
session_write_close();

$file_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
$path = null;
$file = null;

try {
    $rows = $file_id === false || $file_id === null ? array() : messages_rows(
        'SELECT f.stored_name, f.original_name, f.mime, m.sender_id, m.receiver_id, m.status
        FROM message_files f
        JOIN messages m ON m.id = f.message_id
        WHERE f.id = ? AND f.removed_at IS NULL',
        'i',
        array($file_id)
    );

    if ($rows !== array()) {
        $row = $rows[0];
        $is_sender = (int) $row['sender_id'] === $user_id;
        $is_receiver = (int) $row['receiver_id'] === $user_id;

        if (
            $is_sender ||
            ($is_receiver && $row['status'] === 'delivered') ||
            (!$is_receiver && $row['status'] === 'pending_review' && content_user_is_admin())
        ) {
            $file = $row;
            $path = message_file_path($row['stored_name']);
        }
    }
} catch (Throwable $error) {
    error_log('DiSSENT: message file lookup failed: ' . get_class($error));
}

// Same answer for "does not exist" and "not allowed".
if ($path === null || is_link($path) || !is_file($path) || !isset(message_file_types()[$file['mime']])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('This file is not available.');
}

$is_image = strpos($file['mime'], 'image/') === 0;

header('Content-Type: ' . $file['mime']);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cross-Origin-Resource-Policy: same-origin');
header('Referrer-Policy: no-referrer');
header('Cache-Control: private, no-store');
header('Content-Disposition: ' . ($is_image ? 'inline' : 'attachment')
    . '; filename="dissent-file.' . message_file_types()[$file['mime']] . '"; filename*=UTF-8\'\'' . rawurlencode($file['original_name']));

readfile($path);
