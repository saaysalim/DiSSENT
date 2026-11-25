<?php
/*
 * Sends a message, with at most one attachment.
 * The chat asks for JSON; without JavaScript it redirects back to the conversation.
 */
session_start();
require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';
require_once __DIR__ . '/../backend/messages.php';

$wants_json = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') === 0;
$receiver_id = is_string($_POST['receiver_id'] ?? null) ? (int) $_POST['receiver_id'] : 0;

$respond = static function ($data, $code = 200) use ($wants_json, &$receiver_id) {
    if ($wants_json) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
        exit();
    }

    if (!empty($data['error'])) {
        $_SESSION['message_error'] = $data['error'];
    }

    header('Location: messaging.php?user=' . $receiver_id . '#chatArea', true, 303);
    exit();
};

if ($wants_json && empty($_SESSION['user_id'])) {
    $respond(array('ok' => false, 'error' => 'Your login has expired. Please log in again.'), 401);
}

require_login();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: messaging.php');
    exit();
}

// PHP throws away the whole form when it is larger than post_max_size.
if ($_POST === array() && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $respond(array('ok' => false, 'error' => 'The file is too large. The limit is 5 MB.'), 413);
}

verify_csrf();

$sender_id = (int) $_SESSION['user_id'];
$text = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';
$file = null;
$saved = null;
$error = '';

try {
    if ($receiver_id <= 0 || $receiver_id === $sender_id) {
        throw new MessageFileProblem('Please choose a contact first.');
    }
    if (preg_match('//u', $text) !== 1) {
        throw new MessageFileProblem('The message contains characters that cannot be sent.');
    }
    if (mb_strlen($text, 'UTF-8') > 5000) {
        throw new MessageFileProblem('Please keep the message within 5,000 characters.');
    }

    $file = message_file_receive($_FILES['attachment'] ?? null);

    if ($text === '' && $file === null) {
        throw new MessageFileProblem('Please type a message or attach a file.');
    }

    $saved = messages_send($sender_id, $receiver_id, $text, $file);
} catch (MessageFileProblem $problem) {
    $error = $problem->getMessage();
} catch (Throwable $problem) {
    // Never log message text.
    error_log('DiSSENT: sending a message failed: ' . get_class($problem));
    $error = 'Your message could not be sent. Please try again.';
}

if ($saved === null) {
    if ($file !== null) {
        message_file_delete($file['stored_name']);
    }

    $respond(array('ok' => false, 'error' => $error), 422);
}

$html = '';

try {
    foreach (messages_visible($sender_id, $receiver_id, 'AND m.id = ?', 'i', array($saved['id'])) as $row) {
        $html = message_html($row, $sender_id, '');
    }
} catch (Throwable $problem) {
    error_log('DiSSENT: showing a sent message failed: ' . get_class($problem));
}

$respond(array('ok' => true, 'id' => $saved['id'], 'held' => $saved['held'], 'html' => $html));
