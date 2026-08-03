<?php

require_once __DIR__ . '/blockchain_client.php';
require_once __DIR__ . '/messaging_client.php';

// Set to false once real email sending is configured — while true,
// forgot_password.php shows the reset link on-screen instead of emailing it.
define('DEBUG_MODE', true);

function e($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function db_ready(){
    global $conn;
    return isset($conn) && $conn instanceof mysqli;
}

function db_count($table, $where = "1=1"){
    if(!db_ready()){
        return 0;
    }

    global $conn;
    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM $table WHERE $where");

    if(!$result){
        return 0;
    }

    $row = mysqli_fetch_assoc($result);
    return (int)$row['total'];
}

function db_rows($sql){
    if(!db_ready()){
        return array();
    }

    global $conn;
    $result = mysqli_query($conn, $sql);

    if(!$result){
        return array();
    }

    $rows = array();
    while($row = mysqli_fetch_assoc($result)){
        $rows[] = $row;
    }

    return $rows;
}

function platform_content(){
    return array(
        "features" => array(
            array("title" => "Secure Messaging", "text" => "Protected conversations between journalists, media organisations and verification partners.", "link" => "messaging.php"),
            array("title" => "Blockchain Verification", "text" => "Report authenticity, immutable timestamps and transparent status tracking.", "link" => "verification.php"),
            array("title" => "Report Management", "text" => "Submit, review and monitor investigations from one workspace.", "link" => "reports.php"),
            array("title" => "Media Collaboration", "text" => "Coordinate trusted cross-border publishing workflows.", "link" => "media_center.php")
        ),
        "workflow" => array(
            array("title" => "Submit Information", "text" => "Journalists submit reports, evidence links and source context.", "link" => "reports.php"),
            array("title" => "Secure Transfer", "text" => "Partners coordinate through the protected messaging workspace.", "link" => "messaging.php"),
            array("title" => "Verify Evidence", "text" => "Reports are checked, timestamped and prepared for publication.", "link" => "verification.php")
        ),
        "partners" => array(
            array("name" => "MiCT", "text" => "Media in Cooperation and Transition", "url" => "https://www.mict-international.org/"),
            array("name" => "ECHO", "text" => "Ireland media partner", "url" => "https://www.echo.ie/")
        ),
        "benefits" => array(
            array("title" => "Security", "text" => "Protected channels for sensitive journalism workflows."),
            array("title" => "Verification", "text" => "Status tracking and evidence validation for submitted reports."),
            array("title" => "Transparency", "text" => "Clear activity, timestamps and partner accountability."),
            array("title" => "Collaboration", "text" => "A shared workspace for cross-border media teams.")
        )
    );
}

// Real publications, pulled live from the reports table.
// Public visitors only ever see verified reports; pass true to see everything (admin view).
function get_publications($search = "", $show_all_statuses = false){
    if(!db_ready()){
        return array();
    }

    global $conn;

    $sql = "SELECT r.id, r.title, r.category, r.description, r.location, r.evidence,
                r.status, r.created_at,
                u.fullname, u.organisation, vc.block_hash
            FROM reports r
            JOIN users u ON r.user_id = u.id
            LEFT JOIN verification_chain vc ON vc.report_id = r.id
            WHERE 1=1";

    if(!$show_all_statuses){
        $sql .= " AND r.status = 'Verified'";
    }

    if($search !== ""){
        $safe = mysqli_real_escape_string($conn, $search);
        $sql .= " AND (r.title LIKE '%$safe%' OR r.description LIKE '%$safe%' OR r.category LIKE '%$safe%' OR u.fullname LIKE '%$safe%')";
    }

    $sql .= " ORDER BY r.created_at DESC";

    return db_rows($sql);
}
function summarize($text, $length = 160){
    $text = trim(strip_tags($text));
    if(strlen($text) <= $length){
        return $text;
    }
    return substr($text, 0, $length) . '…';
}

function get_updates($limit = 6){
    if(!db_ready()){
        return array();
    }

    $submissions = db_rows("
        SELECT r.title, r.created_at AS event_time, u.fullname,
            'submitted' AS event_type
        FROM reports r
        JOIN users u ON r.user_id = u.id
        ORDER BY r.created_at DESC
        LIMIT $limit
    ");

    $verifications = db_rows("
        SELECT r.title, vc.created_at AS event_time, u.fullname,
            'verified' AS event_type
        FROM verification_chain vc
        JOIN reports r ON vc.report_id = r.id
        JOIN users u ON r.user_id = u.id
        WHERE r.status = 'Verified'
        ORDER BY vc.created_at DESC
        LIMIT $limit
    ");

    $events = array_merge($submissions, $verifications);

    usort($events, function($a, $b){
        return strtotime($b['event_time']) <=> strtotime($a['event_time']);
    });

    $events = array_slice($events, 0, $limit);

    return array_map(function($event){
        if($event['event_type'] === 'verified'){
            return e($event['title']) . ' was verified and published.';
        }
        return 'New report "' . e($event['title']) . '" submitted by ' . e($event['fullname']) . '.';
    }, $events);
}

function filter_items($items, $query){
    $query = strtolower(trim($query));

    if($query === ""){
        return $items;
    }

    return array_values(array_filter($items, function($item) use ($query){
        return strpos(strtolower(implode(" ", $item)), $query) !== false;
    }));
}

function csrf_token(){
    if(empty($_SESSION['csrf_token'])){
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(){
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(){
    if(
        !isset($_POST['csrf_token']) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ){
        die('Your session expired. Please go back and try again.');
    }
}

function require_login(){
    if(!isset($_SESSION['user_id'])){
        header("Location: login.php");
        exit();
    }
}

function require_admin(){
    require_login();
    if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
        header("Location: dashboard.php");
        exit();
    }
}

// Hash-chain verification is now a real Hyperledger Fabric ledger, reached through
// securecomm's REST API (see backend/blockchain_client.php) instead of being simulated
// in MySQL. verification_chain stays as a local read cache so listing pages (media
// center, homepage updates) don't need a live HTTP round trip per row — admin.php
// writes to it right after a successful ledger call, via this helper.
function cache_verification_block($report_id, $block_hash){
    global $conn;
    $stmt = mysqli_prepare($conn, "INSERT INTO verification_chain (report_id, prev_hash, data_hash, block_hash) VALUES (?, '', '', ?)");
    mysqli_stmt_bind_param($stmt, 'is', $report_id, $block_hash);
    mysqli_stmt_execute($stmt);
}

function create_password_reset($email){
    global $conn;

    $token = bin2hex(random_bytes(32));

    $stmt = mysqli_prepare($conn, "INSERT INTO password_resets (email, token) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, 'ss', $email, $token);
    mysqli_stmt_execute($stmt);

    return $token;
}

function get_valid_reset($token){
    global $conn;

    // Expiry is checked in SQL (against MySQL's own NOW()) rather than comparing
    // MySQL's created_at to PHP's clock — PHP and MySQL can run in different
    // timezones (seen locally: PHP in Europe/Berlin, MySQL server in UTC+1), which
    // silently made every reset link look expired immediately.
    $stmt = mysqli_prepare($conn, "SELECT * FROM password_resets WHERE token = ? AND created_at >= NOW() - INTERVAL 60 MINUTE ORDER BY id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    return $row ?: null;
}

function consume_reset_token($token){
    global $conn;
    $stmt = mysqli_prepare($conn, "DELETE FROM password_resets WHERE token = ?");
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
}

// Whole-chain tamper check now runs against the real Fabric ledger. Returns true/false,
// or null if the verification service is unreachable — callers must treat null as
// "unknown", not as "broken".
function get_verification_stats(){
    return array(
        'total_reports' => db_count('reports'),
        'verified_count' => db_count('reports', "status = 'Verified'"),
        'pending_count' => db_count('reports', "status = 'Pending'"),
        'chain_intact' => bc_verify_chain_integrity()
    );
}

// Recent verified reports with their hash-chain block, most recent first
function get_verification_history($limit = 10){
    return db_rows("
        SELECT r.id, r.title, r.category, r.status, u.fullname,
        vc.block_hash, vc.created_at AS verified_at
        FROM verification_chain vc
        JOIN reports r ON vc.report_id = r.id
        JOIN users u ON r.user_id = u.id
        ORDER BY vc.created_at DESC
        LIMIT $limit
    ");
}

// Search a single verified report by ID or title/journalist name
function find_verified_report($query){
    global $conn;

    if(is_numeric($query)){
        $stmt = mysqli_prepare($conn, "
            SELECT r.*, u.fullname, vc.block_hash, vc.prev_hash, vc.data_hash, vc.created_at AS verified_at
            FROM reports r
            JOIN users u ON r.user_id = u.id
            LEFT JOIN verification_chain vc ON vc.report_id = r.id
            WHERE r.id = ? AND r.status = 'Verified'
        ");
        $id = (int)$query;
        mysqli_stmt_bind_param($stmt, 'i', $id);
    }else{
        $safe = '%' . mysqli_real_escape_string($conn, $query) . '%';
        $stmt = mysqli_prepare($conn, "
            SELECT r.*, u.fullname, vc.block_hash, vc.prev_hash, vc.data_hash, vc.created_at AS verified_at
            FROM reports r
            JOIN users u ON r.user_id = u.id
            LEFT JOIN verification_chain vc ON vc.report_id = r.id
            WHERE r.status = 'Verified' AND (r.title LIKE ? OR u.fullname LIKE ?)
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, 'ss', $safe, $safe);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

function get_avatar_html($user){
    if(!empty($user['profile_picture']) && file_exists(__DIR__ . '/../uploads/profile_pictures/' . $user['profile_picture'])){
        return '<img src="../uploads/profile_pictures/' . e($user['profile_picture']) . '" class="profile-avatar-img" alt="Profile picture">';
    }
    return '<div class="profile-avatar">' . e(strtoupper(substr($user['fullname'], 0, 1))) . '</div>';
}

function handle_avatar_upload($file){
    $allowed = array('jpg', 'jpeg', 'png', 'gif');
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if(!in_array($ext, $allowed)){
        return array('error' => 'Only JPG, PNG, or GIF images are allowed.');
    }

    if($file['size'] > 3 * 1024 * 1024){
        return array('error' => 'Image must be smaller than 3MB.');
    }

    $safe_name = 'avatar_' . uniqid() . '.' . $ext;
    $dest_dir = __DIR__ . '/../uploads/profile_pictures/';
    $dest = $dest_dir . $safe_name;

    if(move_uploaded_file($file['tmp_name'], $dest)){
        return array('filename' => $safe_name);
    }

    return array('error' => 'Upload failed. Please try again.');
}
?>