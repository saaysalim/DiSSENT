<?php

require_once __DIR__ . '/blockchain_client.php';
require_once __DIR__ . '/messaging_client.php';

// Do not display password-reset links on screen.
// Email delivery must be configured separately.
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', false);
}


/* -------------------------------------------------
   Output escaping
------------------------------------------------- */

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/* -------------------------------------------------
   Database helpers
------------------------------------------------- */

function db_ready()
{
    global $conn;

    return isset($conn) && $conn instanceof mysqli;
}


// Use only developer-controlled table names and conditions.
// Never pass raw form or URL input into $where.
function db_count($table, $where = "1=1")
{
    if (!db_ready()) {
        throw new RuntimeException('Database connection unavailable.');
    }

    if (
        !is_string($table) ||
        !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)
    ) {
        throw new InvalidArgumentException('Invalid table name.');
    }

    global $conn;

    $result = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total FROM `$table` WHERE $where"
    );

    if (!$result) {
        throw new RuntimeException('Database count failed.');
    }

    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);

    return (int) $row['total'];
}


// SQL passed here must be developer-controlled.
// Use prepared statements for user-supplied values.
function db_rows($sql)
{
    if (!db_ready()) {
        throw new RuntimeException('Database connection unavailable.');
    }

    global $conn;

    $result = mysqli_query($conn, $sql);

    if (!$result instanceof mysqli_result) {
        throw new RuntimeException('Database query failed.');
    }

    $rows = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_free_result($result);

    return $rows;
}


/* -------------------------------------------------
   Shared platform text
------------------------------------------------- */

function platform_content()
{
    return array(
        'features' => array(
            array(
                'title' => 'Messaging',
                'text' => 'Exchange messages with journalists and media teams, with secure-transfer status tracking.',
                'link' => 'messaging.php'
            ),
            array(
                'title' => 'Blockchain Integrity',
                'text' => 'Record report fingerprints and check integrity separately from editorial approval.',
                'link' => 'verification.php'
            ),
            array(
                'title' => 'Report Management',
                'text' => 'Submit reports and follow editorial review decisions.',
                'link' => 'reports.php'
            ),
            array(
                'title' => 'Media Collaboration',
                'text' => 'Explore approved reports and coordinate cross-border work.',
                'link' => 'media_center.php'
            )
        ),

        'workflow' => array(
            array(
                'title' => 'Submit a Report',
                'text' => 'Provide your report and supporting evidence links.',
                'link' => 'reports.php'
            ),
            array(
                'title' => 'Coordinate with Others',
                'text' => 'Use the messaging workspace to communicate with your contacts.',
                'link' => 'messaging.php'
            ),
            array(
                'title' => 'Review and Publish',
                'text' => 'An authorised reviewer decides whether a report is suitable for publication.',
                'link' => 'media_center.php'
            )
        ),

        // Existing organisation links retained from your original file.
        // Confirm names, links and partnership wording before launch.
        'partners' => array(
            array(
                'name' => 'MiCT',
                'text' => 'Media in Cooperation and Transition',
                'url' => 'https://www.mict-international.org/'
            ),
            array(
                'name' => 'ECHO',
                'text' => 'Ireland media partner',
                'url' => 'https://www.echo.ie/'
            )
        ),

        'benefits' => array(
            array(
                'title' => 'Access Control',
                'text' => 'Separate permissions for reporters and administrators.'
            ),
            array(
                'title' => 'Integrity',
                'text' => 'Technical integrity checks kept separate from editorial decisions.'
            ),
            array(
                'title' => 'Accountability',
                'text' => 'Recorded review actions and report statuses.'
            ),
            array(
                'title' => 'Collaboration',
                'text' => 'A shared workspace for cross-border media teams.'
            )
        )
    );
}


/* -------------------------------------------------
   Authentication and admin permissions
------------------------------------------------- */

function require_login()
{
    $user_id = $_SESSION['user_id'] ?? null;

    if (
        !is_scalar($user_id) ||
        filter_var(
            $user_id,
            FILTER_VALIDATE_INT,
            array('options' => array('min_range' => 1))
        ) === false
    ) {
        header('Location: login.php');
        exit();
    }
}


// Read current permissions instead of trusting an old session role.
function content_user_is_admin()
{
    if (!db_ready()) {
        return false;
    }

    $user_id = $_SESSION['user_id'] ?? null;

    if (
        !is_scalar($user_id) ||
        filter_var(
            $user_id,
            FILTER_VALIDATE_INT,
            array('options' => array('min_range' => 1))
        ) === false
    ) {
        return false;
    }

    global $conn;

    $user_id = (int) $user_id;

    $stmt = mysqli_prepare(
        $conn,
        "SELECT role FROM users WHERE id = ? LIMIT 1"
    );

    if (!$stmt) {
        throw new RuntimeException('Could not check account permissions.');
    }

    mysqli_stmt_bind_param($stmt, 'i', $user_id);

    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Could not check account permissions.');
    }

    $result = mysqli_stmt_get_result($stmt);

    if (!$result) {
        throw new RuntimeException('Account permissions unavailable.');
    }

    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $user && $user['role'] === 'admin';
}


function require_admin()
{
    require_login();

    if (!db_ready()) {
        require_once __DIR__ . '/db.php';
    }

    try {
        $is_admin = content_user_is_admin();
    } catch (Throwable $exception) {
        error_log('DiSSENT: admin permission check failed.');

        http_response_code(503);
        exit('Account permissions could not be checked. Please try again.');
    }

    if (!$is_admin) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}


/* -------------------------------------------------
   CSRF protection
------------------------------------------------- */

function csrf_token()
{
    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token']) ||
        $_SESSION['csrf_token'] === ''
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}


function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="'
        . e(csrf_token())
        . '">';
}


function verify_csrf()
{
    $submitted = $_POST['csrf_token'] ?? null;
    $expected = $_SESSION['csrf_token'] ?? null;

    if (
        !is_string($submitted) ||
        !is_string($expected) ||
        $expected === '' ||
        !hash_equals($expected, $submitted)
    ) {
        http_response_code(403);

        exit(
            'Your form session is invalid or expired. '
            . 'Please reload the page and try again.'
        );
    }
}


/* -------------------------------------------------
   Public reports
------------------------------------------------- */

function get_publications($search = "", $show_all_statuses = false)
{
    if (!db_ready()) {
        throw new RuntimeException('Database connection unavailable.');
    }

    global $conn;

    $can_view_all = $show_all_statuses
        ? content_user_is_admin()
        : false;

    $sql = "
        SELECT
            r.id, r.title, r.category, r.description,
            r.location, r.evidence, r.status, r.created_at,
            u.fullname, u.organisation,
            (
                SELECT vc.block_hash
                FROM verification_chain vc
                WHERE vc.report_id = r.id
                ORDER BY vc.created_at DESC
                LIMIT 1
            ) AS block_hash
        FROM reports r
        JOIN users u ON r.user_id = u.id
    ";

    $conditions = array();

    // Previous Verified records are not automatically republished.
    if (!$can_view_all) {
        $conditions[] = "r.status = 'Approved'";
    }

    $search = is_string($search) ? trim($search) : '';

    if ($search !== '') {
        $conditions[] = "
            (
                r.title LIKE ?
                OR r.description LIKE ?
                OR r.category LIKE ?
                OR u.fullname LIKE ?
            )
        ";
    }

    if (!empty($conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY r.created_at DESC, r.id DESC';

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new RuntimeException('Could not prepare publication lookup.');
    }

    if ($search !== '') {
        $pattern = '%' . $search . '%';

        mysqli_stmt_bind_param(
            $stmt,
            'ssss',
            $pattern,
            $pattern,
            $pattern,
            $pattern
        );
    }

    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Could not load publications.');
    }

    $result = mysqli_stmt_get_result($stmt);

    if (!$result) {
        throw new RuntimeException('Publication results unavailable.');
    }

    $rows = array();

    while ($row = mysqli_fetch_assoc($result)) {
        // Approval of a report does not automatically publish its evidence.
        if (!$can_view_all) {
            $row['evidence'] = '';
        }

        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);

    return $rows;
}


// Public activity only. Personal dashboard activity is separate.
function get_updates($limit = 6)
{
    $limit = max(1, min(50, (int) $limit));

    $rows = db_rows("
        SELECT
            r.id,
            r.title,
            MAX(aa.action_at) AS event_time
        FROM reports r
        JOIN admin_actions aa ON aa.report_id = r.id
        WHERE r.status = 'Approved'
          AND aa.action = 'Approved'
        GROUP BY r.id, r.title
        ORDER BY event_time DESC, r.id DESC
        LIMIT $limit
    ");

    // Keep the original function's escaped-HTML return format.
    return array_map(function ($row) {
        return 'Report “' . e($row['title'])
            . '” was approved for publication.';
    }, $rows);
}


/* -------------------------------------------------
   Text helpers
------------------------------------------------- */

function summarize($text, $length = 160)
{
    $text = trim(strip_tags((string) $text));
    $length = max(1, (int) $length);

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length, 'UTF-8') . '…';
    }

    if (strlen($text) <= $length) {
        return $text;
    }

    return substr($text, 0, $length) . '…';
}


function filter_items($items, $query)
{
    $query = strtolower(trim((string) $query));

    if ($query === '') {
        return $items;
    }

    return array_values(array_filter($items, function ($item) use ($query) {
        $values = array_filter($item, 'is_scalar');
        $text = strtolower(implode(' ', $values));

        return strpos($text, $query) !== false;
    }));
}


/* -------------------------------------------------
   Local verification cache
------------------------------------------------- */

// This is a local cache of a hash reported by the Java service.
// It is not independent proof of a committed Fabric transaction.
function cache_verification_block($report_id, $block_hash)
{
    if (!db_ready()) {
        throw new RuntimeException('Database connection unavailable.');
    }

    if (!is_string($block_hash) || trim($block_hash) === '') {
        throw new InvalidArgumentException('A block hash is required.');
    }

    global $conn;

    $report_id = (int) $report_id;
    $block_hash = trim($block_hash);

    if ($report_id <= 0) {
        throw new InvalidArgumentException('Invalid report ID.');
    }

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO verification_chain
            (report_id, prev_hash, data_hash, block_hash)
         VALUES (?, '', '', ?)"
    );

    if (!$stmt) {
        throw new RuntimeException('Could not prepare verification cache.');
    }

    mysqli_stmt_bind_param($stmt, 'is', $report_id, $block_hash);

    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Could not save verification cache.');
    }

    mysqli_stmt_close($stmt);
}


/* -------------------------------------------------
   Verification listings
------------------------------------------------- */

function get_verification_stats()
{
    $recorded_rows = db_rows("
        SELECT COUNT(DISTINCT r.id) AS total
        FROM reports r
        JOIN verification_chain vc ON vc.report_id = r.id
        WHERE r.status = 'Approved'
          AND vc.block_hash IS NOT NULL
          AND vc.block_hash <> ''
    ");

    $recorded_count = (int) $recorded_rows[0]['total'];

    return array(
        'total_reports' => db_count('reports'),
        'approved_count' => db_count(
            'reports',
            "status = 'Approved'"
        ),
        'pending_count' => db_count(
            'reports',
            "status = 'Pending'"
        ),
        'rejected_count' => db_count(
            'reports',
            "status = 'Rejected'"
        ),
        'recorded_count' => $recorded_count,

        // Compatibility key for existing pages.
        // This counts cached records, not fresh integrity checks.
        'verified_count' => $recorded_count,

        'chain_intact' => bc_verify_chain_integrity()
    );
}


// Approved reports with a local recording hash.
// A cache entry is not a fresh integrity verification.
function get_verification_history($limit = 10)
{
    $limit = max(1, min(100, (int) $limit));

    return db_rows("
        SELECT
            r.id, r.title, r.category, r.status, u.fullname,
            vc.block_hash, vc.created_at AS verified_at
        FROM verification_chain vc
        JOIN reports r ON vc.report_id = r.id
        JOIN users u ON r.user_id = u.id
        WHERE r.status = 'Approved'
          AND vc.block_hash IS NOT NULL
          AND vc.block_hash <> ''
        ORDER BY vc.created_at DESC
        LIMIT $limit
    ");
}


// Retains the existing function name for compatibility.
// Returns an approved report and its cached recording information.
function find_verified_report($query)
{
    if (!db_ready()) {
        throw new RuntimeException('Database connection unavailable.');
    }

    if (!is_scalar($query)) {
        return null;
    }

    global $conn;

    $query = trim((string) $query);

    if ($query === '') {
        return null;
    }

    $sql = "
        SELECT
            r.id, r.user_id, r.title, r.category,
            r.description, r.location,
            '' AS evidence,
            r.status, r.created_at,
            u.fullname,
            vc.block_hash, vc.prev_hash, vc.data_hash,
            vc.created_at AS verified_at
        FROM reports r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN verification_chain vc ON vc.report_id = r.id
        WHERE r.status = 'Approved'
    ";

    $id = filter_var(
        $query,
        FILTER_VALIDATE_INT,
        array('options' => array('min_range' => 1))
    );

    if ($id !== false) {
        $sql .= "
            AND r.id = ?
            ORDER BY vc.created_at DESC
            LIMIT 1
        ";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            throw new RuntimeException('Could not prepare report lookup.');
        }

        mysqli_stmt_bind_param($stmt, 'i', $id);
    } else {
        $sql .= "
            AND (r.title LIKE ? OR u.fullname LIKE ?)
            ORDER BY r.created_at DESC, vc.created_at DESC
            LIMIT 1
        ";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            throw new RuntimeException('Could not prepare report lookup.');
        }

        // Prepared parameters do not need mysqli_real_escape_string().
        $pattern = '%' . $query . '%';

        mysqli_stmt_bind_param($stmt, 'ss', $pattern, $pattern);
    }

    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Could not load the report.');
    }

    $result = mysqli_stmt_get_result($stmt);

    if (!$result) {
        throw new RuntimeException('Report result unavailable.');
    }

    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}


/* -------------------------------------------------
   Password-reset helpers
------------------------------------------------- */

// Existing token format retained for compatibility with reset pages.
// Token hashing and atomic one-use redemption need coordinated changes
// to those pages and the password_resets table.
function create_password_reset($email)
{
    if (!db_ready()) {
        throw new RuntimeException('Database connection unavailable.');
    }

    if (
        !is_string($email) ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        throw new InvalidArgumentException('A valid email is required.');
    }

    global $conn;

    $token = bin2hex(random_bytes(32));

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO password_resets (email, token) VALUES (?, ?)"
    );

    if (!$stmt) {
        throw new RuntimeException('Could not prepare password reset.');
    }

    mysqli_stmt_bind_param($stmt, 'ss', $email, $token);

    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Could not save password reset.');
    }

    mysqli_stmt_close($stmt);

    return $token;
}


function get_valid_reset($token)
{
    if (
        !is_string($token) ||
        !preg_match('/^[a-f0-9]{64}$/', $token)
    ) {
        return null;
    }

    if (!db_ready()) {
        throw new RuntimeException('Database connection unavailable.');
    }

    global $conn;

    $stmt = mysqli_prepare(
        $conn,
        "SELECT *
         FROM password_resets
         WHERE token = ?
           AND created_at >= NOW() - INTERVAL 60 MINUTE
         ORDER BY id DESC
         LIMIT 1"
    );

    if (!$stmt) {
        throw new RuntimeException('Could not prepare reset lookup.');
    }

    mysqli_stmt_bind_param($stmt, 's', $token);

    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Could not check password reset.');
    }

    $result = mysqli_stmt_get_result($stmt);

    if (!$result) {
        throw new RuntimeException('Password reset result unavailable.');
    }

    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}


function consume_reset_token($token)
{
    if (
        !is_string($token) ||
        !preg_match('/^[a-f0-9]{64}$/', $token)
    ) {
        return;
    }

    if (!db_ready()) {
        throw new RuntimeException('Database connection unavailable.');
    }

    global $conn;

    $stmt = mysqli_prepare(
        $conn,
        "DELETE FROM password_resets WHERE token = ?"
    );

    if (!$stmt) {
        throw new RuntimeException('Could not prepare reset removal.');
    }

    mysqli_stmt_bind_param($stmt, 's', $token);

    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Could not remove password reset.');
    }

    mysqli_stmt_close($stmt);
}


/* -------------------------------------------------
   Profile avatars
------------------------------------------------- */

function get_avatar_html($user)
{
    $filename = $user['profile_picture'] ?? '';

    // Accept filenames only, never directories or traversal paths.
    if (
        is_string($filename) &&
        preg_match('/^[a-zA-Z0-9_-]+\.(jpg|jpeg|png|gif)$/i', $filename)
    ) {
        $path = __DIR__ . '/../uploads/profile_pictures/' . $filename;

        if (is_file($path)) {
            return '<img src="../uploads/profile_pictures/'
                . e(rawurlencode($filename))
                . '" class="profile-avatar-img" alt="Profile picture">';
        }
    }

    $name = trim((string) ($user['fullname'] ?? ''));

    if ($name === '') {
        $initial = '?';
    } elseif (
        function_exists('mb_substr') &&
        function_exists('mb_strtoupper')
    ) {
        $initial = mb_strtoupper(
            mb_substr($name, 0, 1, 'UTF-8'),
            'UTF-8'
        );
    } else {
        $initial = strtoupper(substr($name, 0, 1));
    }

    return '<div class="profile-avatar">' . e($initial) . '</div>';
}


function handle_avatar_upload($file)
{
    if (
        !is_array($file) ||
        !isset($file['error']) ||
        !is_int($file['error'])
    ) {
        return array('error' => 'Invalid image upload.');
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return array('error' => 'Please choose an image.');
    }

    if (
        $file['error'] === UPLOAD_ERR_INI_SIZE ||
        $file['error'] === UPLOAD_ERR_FORM_SIZE
    ) {
        return array('error' => 'The image exceeds the upload size limit.');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return array('error' => 'The image upload failed. Please try again.');
    }

    $temp_path = $file['tmp_name'] ?? '';

    if (
        !is_string($temp_path) ||
        !is_uploaded_file($temp_path)
    ) {
        return array('error' => 'Invalid uploaded image.');
    }

    $actual_size = filesize($temp_path);

    if (
        $actual_size === false ||
        $actual_size <= 0 ||
        $actual_size > 3 * 1024 * 1024
    ) {
        return array('error' => 'Choose an image no larger than 3MB.');
    }

    if (!class_exists('finfo')) {
        return array('error' => 'Image validation is unavailable.');
    }

    $allowed_types = array(
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif'
    );

    try {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($temp_path);
        $image_info = @getimagesize($temp_path);

        if (
            !isset($allowed_types[$mime]) ||
            $image_info === false ||
            ($image_info['mime'] ?? '') !== $mime
        ) {
            return array(
                'error' => 'Please upload a valid JPG, PNG or GIF image.'
            );
        }

        $width = (int) $image_info[0];
        $height = (int) $image_info[1];

        if (
            $width < 1 ||
            $height < 1 ||
            $width > 6000 ||
            $height > 6000 ||
            $width * $height > 20000000
        ) {
            return array(
                'error' => 'The image dimensions are too large. Please resize it.'
            );
        }

        $destination_dir = __DIR__ . '/../uploads/profile_pictures/';

        if (
            !is_dir($destination_dir) &&
            !mkdir($destination_dir, 0750, true) &&
            !is_dir($destination_dir)
        ) {
            return array('error' => 'Image storage is unavailable.');
        }

        $filename = 'avatar_'
            . bin2hex(random_bytes(16))
            . '.'
            . $allowed_types[$mime];

        $destination = $destination_dir . $filename;

        if (!move_uploaded_file($temp_path, $destination)) {
            return array('error' => 'The image could not be saved.');
        }

        return array('filename' => $filename);
    } catch (Throwable $exception) {
        error_log('DiSSENT: avatar upload failed.');

        return array('error' => 'The image could not be saved.');
    }
}