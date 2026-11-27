<?php
/*
 * Accept, decline or block a message request (see backend/contacts.php).
 * The sender is never told about a decline or a block.
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

$contact_id = is_string($_POST['request_id'] ?? null) ? (int) $_POST['request_id'] : 0;
$decision = is_string($_POST['decision'] ?? null) ? $_POST['decision'] : '';

try {
    $result = contacts_respond((int) $_SESSION['user_id'], $contact_id, $decision);
} catch (Throwable $error) {
    error_log('DiSSENT: answering a message request failed: ' . get_class($error));
    $result = array('error' => 'Your answer could not be saved. Please try again.', 'other_id' => 0);
}

if ($result['error'] !== '') {
    $_SESSION['contact_notice'] = array('error', $result['error']);
    header('Location: messaging.php', true, 303);
    exit();
}

$texts = array(
    'accept' => 'Request accepted. You can now message each other.',
    'decline' => 'Request declined. The sender is not told.',
    'block' => 'Blocked. This person cannot send you new requests. They are not told.',
);
$_SESSION['contact_notice'] = array('success', $texts[$decision]);

header('Location: ' . ($decision === 'accept' ? 'messaging.php?user=' . $result['other_id'] . '#chatArea' : 'messaging.php'), true, 303);
exit();
