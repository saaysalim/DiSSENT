<?php
/*
 * "New message": sends a message request to the account with exactly this email address.
 * Always the same answer whether or not the address has an account (see backend/contacts.php).
 */
session_start();
require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';
require_once __DIR__ . '/../backend/contacts.php';

require_login();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: messaging.php');
    exit();
}

verify_csrf();

$email = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';
$text = is_string($_POST['message'] ?? null) ? $_POST['message'] : '';

try {
    $result = contacts_send_request((int) $_SESSION['user_id'], $email, $text);
} catch (Throwable $error) {
    error_log('DiSSENT: message request failed: ' . get_class($error));
    $result = array('ok' => false, 'message' => 'Your request could not be sent. Please try again.');
}

// Not an email address: the error is shown next to the box, not at the top.
$field_error = ($result['field'] ?? '') === 'email' ? $result['message'] : '';
if ($field_error === '') {
    $_SESSION['contact_notice'] = array($result['ok'] ? 'success' : 'error', $result['message']);
}

// Keep what was typed only when the form has to be corrected.
if (!$result['ok'] && $result['message'] !== CONTACT_REQUEST_LIMIT_REPLY) {
    $_SESSION['contact_form'] = array('email' => mb_substr(trim($email), 0, 255, 'UTF-8'), 'message' => mb_substr($text, 0, 500, 'UTF-8'), 'email_error' => $field_error);
}

header('Location: messaging.php' . ($result['ok'] ? '' : '?new=1#newRequest'), true, 303);
exit();
