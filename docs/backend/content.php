<?php

require_once __DIR__ . '/blockchain_client.php';
require_once __DIR__ . '/messaging_client.php';

// Local development only: APP_DEBUG=1 (set in docker-compose.yml) shows
// password-reset links on screen. Production never sets it, so it stays off.
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', getenv('APP_DEBUG') === '1');
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
   FAQ content (faq.php)
   One structured list so it can also be used later as the
   knowledge base for an AI help assistant. Plain text only:
   every value is escaped when it is printed.
   Each item: q (question), a (short answer), optional steps
   (numbered list) and optional links (page => label).
------------------------------------------------- */

function faq_items()
{
    return array(
        array(
            'id' => 'getting-started',
            'title' => 'Getting started',
            'items' => array(
                array(
                    'q' => 'What is DiSSENT?',
                    'a' => 'DiSSENT is a shared workspace for journalists, including refugee and displaced reporters. You can submit reports for editorial review, message other members securely, and follow what happens to your reports. Approved reports are published in the Media Centre and recorded with the verification service.',
                ),
                array(
                    'q' => 'Do I need an account?',
                    'a' => 'No account is needed to read the Home, About, Media Centre, Verification, FAQ and Contact pages. You need an account to submit reports, send messages, and use the Dashboard, Profile and Settings pages.',
                    'links' => array('register.php' => 'Create an account'),
                ),
                array(
                    'q' => 'How do I find my way around on a phone or tablet?',
                    'a' => 'Press the menu button (three lines) at the top left. A side menu opens with all the pages, the search box, the language list and your account links. Close it with the X button, the Esc key, or by tapping outside it.',
                ),
            ),
        ),
        array(
            'id' => 'account',
            'title' => 'Account and login',
            'items' => array(
                array(
                    'q' => 'How do I create an account?',
                    'a' => 'Registration takes about a minute.',
                    'steps' => array(
                        'Press "Get Started" at the top of the page, or open the Register page.',
                        'Fill in Full name, Email address and Professional category.',
                        'Create a password that meets every rule in the checklist, then type it again in Confirm password.',
                        'Press "Register". A green message says "Registration successful! You can now log in."',
                        'Open the Login page and log in with your email and password.',
                    ),
                    'links' => array('register.php' => 'Open Register'),
                ),
                array(
                    'q' => 'What is a professional category?',
                    'a' => 'It describes your work: Journalist, Freelance Journalist, Media Organisation, Fact Checker, Researcher / Investigator, NGO / Human Rights Organisation, or Other. It is shown next to your name on published reports. It does not change what you can do on the site: every new account is a normal member. You can change it later on your Profile page.',
                ),
                array(
                    'q' => 'What are the password rules?',
                    'a' => 'Your password needs at least 8 characters, an uppercase letter, a lowercase letter, a number and a special character such as ! ? # @ or %. A checklist under the password box ticks off each rule as you type, and the eye button shows what you typed. The same rules apply when you register, reset your password or change it. Use a password you do not use anywhere else.',
                ),
                array(
                    'q' => 'How do I log in and log out?',
                    'a' => 'Open the Login page, enter your email address and password, and press "Log in". You go to your Dashboard. To log out, press "Logout" in the menu. You return to the Login page with the message "You have been logged out". Always log out on shared computers.',
                    'links' => array('login.php' => 'Open Login'),
                ),
                array(
                    'q' => 'I forgot my password. What do I do?',
                    'a' => 'You can choose a new password with a reset link.',
                    'steps' => array(
                        'On the Login page, press "Forgot password?".',
                        'Enter the email address you registered with and press "Send Reset Link".',
                        'If an account exists for that email, we send a link. For your privacy, the page shows the same message either way.',
                        'Open the link within 60 minutes. Choose a new password that meets the rules and press "Update Password".',
                        'Log in with your new password.',
                    ),
                    'links' => array('forgot_password.php' => 'Reset my password'),
                ),
                array(
                    'q' => 'How do I change my name, picture or password?',
                    'a' => 'Press your name in the menu to open your Profile. In "Profile details" you can change your full name, professional category and picture (JPG, PNG or GIF), then press "Save Changes". In "Change password", enter your current password and the new one twice, then press "Update Password". You can also change your password on the Settings page.',
                    'links' => array('profile.php' => 'Open Profile', 'settings.php' => 'Open Settings'),
                ),
                array(
                    'q' => 'How do I delete my account?',
                    'a' => 'Open Settings and go to "Delete my account". Enter your password, tick the confirmation box and press "Delete my account". Your login, settings and unpublished reports (with their documents) are removed. Reports that were already approved stay published, and the people you talked to keep their conversations with you; in both places your name is replaced with "Deleted user". This cannot be undone.',
                    'links' => array('settings.php' => 'Open Settings'),
                ),
            ),
        ),
        array(
            'id' => 'reports',
            'title' => 'Submitting reports',
            'items' => array(
                array(
                    'q' => 'How do I submit a report?',
                    'a' => 'You need to be logged in.',
                    'steps' => array(
                        'Open Reports from the menu.',
                        'In "Submit a report", fill in Report title, Category (for example "human rights") and Report description. These are required.',
                        'Optionally add a Location (use a general area if an exact place could identify a source), an Evidence link and one supporting PDF.',
                        'Press "Submit for review".',
                        'Your report appears under "My reports" with the status "Pending review".',
                    ),
                    'links' => array('reports.php' => 'Open Reports'),
                ),
                array(
                    'q' => 'What can I upload?',
                    'a' => 'You can attach one PDF file per report, up to 5 MB. The site checks that the file really is a PDF. The document is stored outside the public website: only you and administrators can download it, and approving the report does not make the document public. For the Evidence link, use a normal web address without passwords or private access tokens.',
                ),
                array(
                    'q' => 'What happens after I submit?',
                    'a' => 'An administrator reads your report and makes one decision. You can see the status under "My reports": "Pending review" means it is waiting for an administrator. "Approved" means it is published in the Media Centre and sent to the verification service. "Rejected" means it is not published; the reason is shown under the report. Older reports may show "Legacy verification status".',
                ),
                array(
                    'q' => 'What does the administrator do?',
                    'a' => 'On the Admin page, the administrator opens "Read report and review" and can open the report and its document. They then press "Approve for publication" or "Reject report". A reason is required to reject. Each report gets one decision, and every decision is saved in an activity log.',
                ),
                array(
                    'q' => 'Can I edit or delete a report after submitting it?',
                    'a' => 'Not at the moment. If you need to correct a report, use the Contact page and include the report ID shown under "My reports".',
                    'links' => array('contact_us.php' => 'Contact the team'),
                ),
            ),
        ),
        array(
            'id' => 'verification',
            'title' => 'Report verification',
            'items' => array(
                array(
                    'q' => 'What does "recorded on the blockchain" mean?',
                    'a' => 'When an administrator approves a report, DiSSENT sends a digital fingerprint (a hash) of the report text to the verification service, which stores it on a Hyperledger Fabric blockchain. If anyone later changes the text, its fingerprint no longer matches. This shows the text has not changed since it was recorded. It does not prove that what the report says is true; that is what editorial review is for.',
                ),
                array(
                    'q' => 'What do the recording statuses mean?',
                    'a' => 'Administrators see a "Blockchain recording status" for each report. "Reported recorded" means the verification service confirmed it saved the record. "Unconfirmed" means the service did not answer in time, so the record may or may not have been saved. "Unknown" means nothing has been recorded, for example because the report is still pending or was rejected.',
                ),
                array(
                    'q' => 'What is chain integrity?',
                    'a' => 'The records on the blockchain are linked together like a chain: each one includes the fingerprint of the one before it. The Chain Integrity card on the Verification page shows the result of checking every link. "Check passed" means nothing in the chain was changed. "Check failed" means something does not match and the team needs to investigate. "Unavailable" means the verification service could not be reached right now.',
                ),
                array(
                    'q' => 'How do I check a report?',
                    'a' => 'Open Verification, type a report ID, title or journalist name in "Find a Report" and press "Find Report". You can also press "View Record" in the "Recent Recorded Verifications" table to request the current record from the verification service.',
                    'links' => array('verification.php' => 'Open Verification'),
                ),
            ),
        ),
        array(
            'id' => 'messaging',
            'title' => 'Secure messaging',
            'items' => array(
                array(
                    'q' => 'How do I send a secure message?',
                    'a' => 'You need to be logged in.',
                    'steps' => array(
                        'Open Messaging from the menu.',
                        'Choose a person from the Contacts list. You can search by name or role.',
                        'Type your message in the box at the bottom.',
                        'Press the send button (paper plane). Your message appears in the conversation.',
                    ),
                    'links' => array('messaging.php' => 'Open Messaging'),
                ),
                array(
                    'q' => 'What do the message statuses mean?',
                    'a' => 'Each message shows a "Secure Transfer" badge. "Pending" means DiSSENT is waiting for confirmation. "Verified ✓" means an encrypted and signed copy travelled between the two DiSSENT systems and the second system checked the signature and decrypted it. "Failed" means that check did not succeed. In every case your message is still saved and shown to the other person.',
                ),
                array(
                    'q' => 'Who can I message?',
                    'a' => 'Any registered member shown in your Contacts list.',
                ),
            ),
        ),
        array(
            'id' => 'privacy',
            'title' => 'Privacy and safety',
            'items' => array(
                array(
                    'q' => 'Who can see my reports?',
                    'a' => 'Before a decision, only you and administrators can see your report. When it is approved, it is published in the Media Centre: anyone can see its title, category, description, location (if you gave one), evidence link, and your name and professional category. Your uploaded PDF is never public: only you and administrators can download it.',
                ),
                array(
                    'q' => 'Who can see my messages?',
                    'a' => 'Only you and the person you are talking to can see your messages on the site. Messages are stored in the DiSSENT database, so people who manage the server could technically access them. Never send passwords in a message.',
                ),
                array(
                    'q' => 'How is my data protected?',
                    'a' => 'Passwords are stored only as secure hashes, never as plain text. Forms are protected against requests from other websites, and your login cookie cannot be read by scripts. Uploaded documents are kept outside the public website. Messages sent between the two DiSSENT systems are encrypted (AES) and signed (ECDSA).',
                ),
                array(
                    'q' => 'How can I stay safe when reporting?',
                    'a' => 'Give a general area instead of an exact location if a place could identify a source. Do not put names or private details of sources in evidence links. Use a strong password that you do not use anywhere else, and log out on shared devices.',
                ),
            ),
        ),
    );
}


/* -------------------------------------------------
   User settings (settings.php)
   Stored in the user_settings table, see
   database/migrations/2026-11-10_user_settings.sql
------------------------------------------------- */

function settings_languages()
{
    return array(
        'en' => 'English',
        'fa' => 'Farsi (فارسی)',
        'fr' => 'French (Français)',
        'es' => 'Spanish (Español)',
        'ar' => 'Arabic (العربية)',
        'de' => 'German (Deutsch)',
        'nl' => 'Dutch (Nederlands)',
    );
}


// Returns the saved settings, or the defaults when nothing is saved yet.
// Throws if the user_settings table does not exist yet.
function get_user_settings($user_id)
{
    global $conn;

    $settings = array(
        'language' => 'en',
        'notify_report_updates' => 1,
        'notify_messages' => 1,
    );

    $stmt = mysqli_prepare(
        $conn,
        'SELECT language, notify_report_updates, notify_messages
         FROM user_settings WHERE user_id = ? LIMIT 1'
    );

    if (!$stmt) {
        throw new RuntimeException('Settings lookup unavailable.');
    }

    $user_id = (int) $user_id;
    mysqli_stmt_bind_param($stmt, 'i', $user_id);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Settings lookup failed.');
    }

    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($row) {
        $settings['language'] = (string) $row['language'];
        $settings['notify_report_updates'] = (int) $row['notify_report_updates'];
        $settings['notify_messages'] = (int) $row['notify_messages'];
    }

    return $settings;
}


function save_user_setting_values($user_id, $values)
{
    global $conn;

    $current = get_user_settings($user_id);
    $merged = array_merge($current, array_intersect_key($values, $current));

    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO user_settings (user_id, language, notify_report_updates, notify_messages)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             language = VALUES(language),
             notify_report_updates = VALUES(notify_report_updates),
             notify_messages = VALUES(notify_messages)'
    );

    if (!$stmt) {
        throw new RuntimeException('Settings update unavailable.');
    }

    $user_id = (int) $user_id;
    $language = (string) $merged['language'];
    $notify_reports = (int) $merged['notify_report_updates'];
    $notify_messages = (int) $merged['notify_messages'];

    mysqli_stmt_bind_param($stmt, 'isii', $user_id, $language, $notify_reports, $notify_messages);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        throw new RuntimeException('Settings update failed.');
    }
}


/* -------------------------------------------------
   Account helpers (settings.php)
------------------------------------------------- */

// Changes a password after checking the current one. Returns '' on success,
// otherwise a message for the user. Same rules as register/reset/profile.
function account_change_password($user_id, $current_password, $new_password, $confirm_password)
{
    global $conn;

    if ($current_password === '' || $new_password === '' || $confirm_password === '') {
        return 'Please complete all three password fields.';
    }

    $stmt = mysqli_prepare($conn, 'SELECT password FROM users WHERE id = ? LIMIT 1');

    if (!$stmt) {
        throw new RuntimeException('Password lookup unavailable.');
    }

    $user_id = (int) $user_id;
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row || !password_verify($current_password, (string) $row['password'])) {
        return 'Current password is incorrect.';
    }

    $problem = password_policy_message($new_password);

    if ($problem !== '') {
        return $problem;
    }

    if ($new_password !== $confirm_password) {
        return 'New passwords do not match.';
    }

    if (password_verify($new_password, (string) $row['password'])) {
        return 'Please choose a different password from your current one.';
    }

    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    $old_hash = (string) $row['password'];

    // Avoid overwriting a password changed since the page loaded.
    $stmt = mysqli_prepare($conn, 'UPDATE users SET password = ? WHERE id = ? AND password = ?');

    if (!$stmt) {
        throw new RuntimeException('Password update unavailable.');
    }

    mysqli_stmt_bind_param($stmt, 'sis', $hash, $user_id, $old_hash);
    mysqli_stmt_execute($stmt);
    $changed = mysqli_stmt_affected_rows($stmt) === 1;
    mysqli_stmt_close($stmt);

    return $changed
        ? ''
        : 'Your account changed during this request. Refresh the page and try again.';
}


// Deletes or anonymises everything that belongs to one account, in one
// transaction. Returns '' on success, otherwise a message for the user.
//
// Removed:    unpublished reports (pending/rejected) and their PDF files and
//             records, settings, password-reset tokens, contact-form messages
//             sent from the account's email, and the profile picture.
// Kept:       approved (published) reports, because they are public and
//             recorded with the verification service, and messages, so the
//             other person keeps their conversation. Both now show
//             "Deleted user". Admin audit entries are kept.
// The users row is kept (other records point to it) but anonymised and
// given an unusable random password, so nobody can log in to it.
function account_delete($user_id)
{
    global $conn;

    $user_id = (int) $user_id;
    $driver = new mysqli_driver();
    $previous_mode = $driver->report_mode;
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $files_to_remove = array();

    try {
        mysqli_begin_transaction($conn);

        $stmt = mysqli_prepare($conn, 'SELECT email, role, profile_picture FROM users WHERE id = ? FOR UPDATE');
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$user) {
            mysqli_rollback($conn);
            return 'This account could not be found.';
        }

        if ($user['role'] === 'admin') {
            $admins = (int) mysqli_fetch_row(mysqli_query(
                $conn,
                "SELECT COUNT(*) FROM users WHERE role = 'admin'"
            ))[0];

            if ($admins <= 1) {
                mysqli_rollback($conn);
                return 'You are the only administrator, so this account cannot be deleted. Make another account an administrator first.';
            }
        }

        // Unpublished reports: collect their PDF files, then delete the rows.
        $stmt = mysqli_prepare(
            $conn,
            "SELECT d.stored_name
             FROM report_documents d
             JOIN reports r ON r.id = d.report_id
             WHERE r.user_id = ? AND r.status <> 'Approved'"
        );
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            if (preg_match('/\A[a-f0-9]{48}\.pdf\z/', (string) $row['stored_name'])) {
                $files_to_remove[] = dirname(__DIR__, 2) . '/private/report-documents/' . $row['stored_name'];
            }
        }

        mysqli_stmt_close($stmt);

        $statements = array(
            "DELETE d FROM report_documents d JOIN reports r ON r.id = d.report_id
             WHERE r.user_id = ? AND r.status <> 'Approved'",
            "DELETE v FROM verification_chain v JOIN reports r ON r.id = v.report_id
             WHERE r.user_id = ? AND r.status <> 'Approved'",
            "DELETE FROM reports WHERE user_id = ? AND status <> 'Approved'",
            'DELETE FROM user_settings WHERE user_id = ?',
        );

        foreach ($statements as $sql) {
            $stmt = mysqli_prepare($conn, $sql);

            if (substr_count($sql, '?') === 2) {
                mysqli_stmt_bind_param($stmt, 'ii', $user_id, $user_id);
            } else {
                mysqli_stmt_bind_param($stmt, 'i', $user_id);
            }

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $email = (string) $user['email'];

        foreach (array('DELETE FROM password_resets WHERE email = ?', 'DELETE FROM contact_messages WHERE email = ?') as $sql) {
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $anonymous_email = 'deleted-' . $user_id . '-' . bin2hex(random_bytes(6)) . '@deleted.invalid';
        $unusable_hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE users
             SET fullname = 'Deleted user', email = ?, password = ?,
                 role = 'deleted', organisation = NULL, profile_picture = NULL
             WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'ssi', $anonymous_email, $unusable_hash, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);

        $picture = (string) ($user['profile_picture'] ?? '');

        if (preg_match('/^[a-zA-Z0-9_-]+\.(jpg|jpeg|png|gif)$/i', $picture)) {
            $files_to_remove[] = __DIR__ . '/../uploads/profile_pictures/' . $picture;
        }
    } catch (Throwable $error) {
        try {
            mysqli_rollback($conn);
        } catch (Throwable $ignored) {
        }

        error_log('DiSSENT: account deletion failed: ' . get_class($error));
        return 'Your account could not be deleted. Nothing was changed. Please try again later.';
    } finally {
        mysqli_report($previous_mode);
    }

    // Files are removed only after the database changes are committed.
    foreach ($files_to_remove as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    return '';
}


/* -------------------------------------------------
   Password rules (register, reset password, profile)
   Keep in sync with assets/JavaScript/auth-forms.js.
------------------------------------------------- */

// Returns the rules this password does not meet, as short phrases.
function password_rule_failures($password)
{
    $password = is_string($password) ? $password : '';
    $failures = array();

    $length = function_exists('mb_strlen')
        ? mb_strlen($password, 'UTF-8')
        : strlen($password);

    if ($length < 8) {
        $failures[] = 'at least 8 characters';
    }
    if (preg_match('/\p{Lu}/u', $password) !== 1) {
        $failures[] = 'an uppercase letter';
    }
    if (preg_match('/\p{Ll}/u', $password) !== 1) {
        $failures[] = 'a lowercase letter';
    }
    if (preg_match('/\p{Nd}/u', $password) !== 1) {
        $failures[] = 'a number';
    }
    if (preg_match('/[^\p{L}\p{N}\s]/u', $password) !== 1) {
        $failures[] = 'a special character';
    }

    return $failures;
}


// Returns '' when the password is acceptable, otherwise a message to show.
function password_policy_message($password)
{
    if (!is_string($password) || $password === '') {
        return 'Please enter a password.';
    }

    if (preg_match('//u', $password) !== 1 || strpos($password, "\0") !== false) {
        return 'The password contains an unsupported character. Please choose another password.';
    }

    // bcrypt only uses the first 72 bytes; reject longer input instead of truncating it.
    if (PASSWORD_DEFAULT === PASSWORD_BCRYPT && strlen($password) > 72) {
        return 'The password is too long. Please use 72 characters or fewer.';
    }

    $failures = password_rule_failures($password);

    if ($failures === array()) {
        return '';
    }

    $last = array_pop($failures);
    $list = $failures === array() ? $last : implode(', ', $failures) . ' and ' . $last;

    return 'Your password needs ' . $list . '.';
}


// Eye button that shows/hides the password input with this id (wired up by auth-forms.js).
function password_toggle_html($input_id)
{
    $id = e($input_id);

    return '<button type="button" class="password-toggle" data-password-toggle'
        . ' aria-controls="' . $id . '" aria-label="Show password" hidden>'
        . '<svg class="password-toggle__eye" viewBox="0 0 24 24" width="22" height="22" fill="none"'
        . ' stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"'
        . ' aria-hidden="true" focusable="false">'
        . '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"></path>'
        . '<circle cx="12" cy="12" r="3"></circle></svg>'
        . '<svg class="password-toggle__eye-off" viewBox="0 0 24 24" width="22" height="22" fill="none"'
        . ' stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"'
        . ' aria-hidden="true" focusable="false">'
        . '<path d="M10.6 5.1A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-2.9 3.9"></path>'
        . '<path d="M6.6 6.6A17.3 17.3 0 0 0 2 12s3.5 7 10 7a9.9 9.9 0 0 0 5.4-1.6"></path>'
        . '<path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>'
        . '<path d="M3 3l18 18"></path></svg>'
        . '</button>';
}


// Live checklist under a new-password input (static help text without JavaScript).
function password_rules_html($list_id, $input_id)
{
    $rules = array(
        'length' => 'At least 8 characters',
        'upper' => 'An uppercase letter (A–Z)',
        'lower' => 'A lowercase letter (a–z)',
        'number' => 'A number (0–9)',
        'special' => 'A special character (e.g. ! ? # @ %)',
    );

    $html = '<ul class="password-checklist" id="' . e($list_id) . '"'
        . ' data-password-rules-for="' . e($input_id) . '">';

    foreach ($rules as $rule => $label) {
        $html .= '<li data-rule="' . e($rule) . '">'
            . '<span class="password-checklist__icon" aria-hidden="true"></span>'
            . '<span>' . e($label) . '</span>'
            . '<span class="visually-hidden password-checklist__state"></span>'
            . '</li>';
    }

    return $html . '</ul>';
}


// "Passwords match / do not match" line under a confirm-password input.
function password_match_html($message_id, $confirm_id, $password_id)
{
    return '<p class="password-match" id="' . e($message_id) . '"'
        . ' data-password-match-for="' . e($confirm_id) . '"'
        . ' data-password-match-with="' . e($password_id) . '"'
        . ' aria-live="polite"></p>';
}


/* -------------------------------------------------
   Site address for links in emails
------------------------------------------------- */

// Uses APP_URL when set (recommended, e.g. https://example.org), so links
// cannot be steered by a forged Host header. Falls back to the request.
function app_base_url()
{
    $configured = getenv('APP_URL');

    if (is_string($configured)) {
        $configured = rtrim(trim($configured), '/');

        if (preg_match('#^https?://[a-z0-9.-]+(:[0-9]{1,5})?$#i', $configured) === 1) {
            return $configured;
        }
    }

    $https_flag = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

    $is_https = ($https_flag !== '' && $https_flag !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
        || $forwarded === 'https';

    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');

    if (preg_match('/^[a-z0-9.-]+(:[0-9]{1,5})?$/i', $host) !== 1) {
        $host = 'localhost';
    }

    return ($is_https ? 'https' : 'http') . '://' . $host;
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