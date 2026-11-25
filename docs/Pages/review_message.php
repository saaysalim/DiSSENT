<?php
// Saves an administrator's decision about a held message (form on admin.php).
session_start();
require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';
require_once __DIR__ . '/../backend/messages.php';

require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: admin.php', true, 303);
    exit();
}

verify_csrf();

$message_id = filter_var($_POST['message_id'] ?? null, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
$decision = $_POST['decision'] ?? '';
$note = is_string($_POST['note'] ?? null) ? trim($_POST['note']) : '';

if (!$message_id || !in_array($decision, array('approve', 'reject'), true)) {
    $result = array('error', 'Please choose Approve or Reject.');
} elseif (preg_match('//u', $note) !== 1 || mb_strlen($note, 'UTF-8') > 500) {
    $result = array('error', 'Please keep the reason within 500 characters.');
} else {
    try {
        $problem = messages_review($message_id, (int) $_SESSION['user_id'], $decision === 'approve', $note);

        $result = $problem === ''
            ? array('success', $decision === 'approve'
                ? 'Message #' . $message_id . ' was approved and delivered.'
                : 'Message #' . $message_id . ' was rejected. The receiver will not see it.')
            : array('error', $problem);
    } catch (Throwable $error) {
        error_log('DiSSENT: message review failed: ' . get_class($error));
        $result = array('error', 'The decision could not be saved. Please try again.');
    }
}

$_SESSION['message_review_notice'] = $result;
header('Location: admin.php#messageReviews', true, 303);
exit();
