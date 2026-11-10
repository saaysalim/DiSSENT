<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

// Always return a predictable JSON response.
$respond = static function ($status, $http_code = 200) {
    http_response_code($http_code);

    echo json_encode(
        array('status' => $status),
        JSON_UNESCAPED_SLASHES
    );

    exit();
};

// This endpoint only reads verification status.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    $respond('unknown', 405);
}

// Return JSON instead of redirecting a polling request to login.php.
$session_user_id = $_SESSION['user_id'] ?? null;

if (
    !is_int($session_user_id) &&
    !is_string($session_user_id)
) {
    $respond('unknown', 401);
}

$user_id = filter_var(
    $session_user_id,
    FILTER_VALIDATE_INT,
    array('options' => array('min_range' => 1))
);

if ($user_id === false) {
    $respond('unknown', 401);
}

// We no longer need to change the session.
// Release its lock before database and Java API requests.
session_write_close();

$envelope_input = $_GET['envelope_id'] ?? '';

if (!is_string($envelope_input)) {
    $respond('unknown', 400);
}

$envelope_id = trim($envelope_input);

if ($envelope_id === '') {
    $respond('unknown');
}

// Reject excessively long values and control characters.
if (
    strlen($envelope_id) > 255 ||
    preg_match('/[\x00-\x1F\x7F]/', $envelope_id)
) {
    $respond('unknown', 400);
}

$stmt = null;
$is_participant = false;

try {
    require_once __DIR__ . '/../backend/db.php';
    require_once __DIR__ . '/../backend/content.php';

    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Database unavailable.');
    }

    // Only allow a sender or recipient to check this envelope.
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id
         FROM messages
         WHERE envelope_id = ?
           AND (sender_id = ? OR receiver_id = ?)
         LIMIT 1"
    );

    if (!$stmt) {
        throw new RuntimeException('Unable to prepare status lookup.');
    }

    if (!mysqli_stmt_bind_param(
        $stmt,
        'sii',
        $envelope_id,
        $user_id,
        $user_id
    )) {
        throw new RuntimeException('Unable to bind status lookup.');
    }

    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Unable to execute status lookup.');
    }

    if (!mysqli_stmt_store_result($stmt)) {
        throw new RuntimeException('Unable to read status lookup.');
    }

    $is_participant = mysqli_stmt_num_rows($stmt) > 0;
} catch (Throwable $exception) {
    // Avoid logging private message details or credentials.
    error_log('DiSSENT: message status database lookup failed.');

    $respond('unknown', 503);
} finally {
    if ($stmt instanceof mysqli_stmt) {
        mysqli_stmt_close($stmt);
    }
}

// Use the same response for a missing message and an inaccessible message.
if (!$is_participant) {
    $respond('unknown');
}

try {
    $result = mc_get_status($envelope_id);

    $status = is_array($result)
        ? ($result['status'] ?? 'unknown')
        : 'unknown';

    $allowed_statuses = array(
        'pending',
        'verified',
        'failed',
        'unknown'
    );

    if (
        !is_string($status) ||
        !in_array($status, $allowed_statuses, true)
    ) {
        $status = 'unknown';
    }
} catch (Throwable $exception) {
    error_log('DiSSENT: message verification service request failed.');

    // Unavailable verification is unknown, not failed verification.
    $status = 'unknown';
}

// Do not expose decryptedText or internal service error messages.
$respond($status);