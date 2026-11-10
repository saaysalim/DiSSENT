<?php
/*
 * DiSSENT login page.
 *
 * Keeps the existing:
 * - mysqli connection: $conn
 * - CSRF helpers: verify_csrf() and csrf_field()
 * - users table and session keys
 * - dashboard destination
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (!session_start()) {
        http_response_code(503);
        exit('Login is temporarily unavailable. Please try again.');
    }
}

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';

header('Cache-Control: no-store');

/*
 * If already signed in, use the existing dashboard.
 */
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php', true, 303);
    exit;
}

$message = '';
$message_type = 'error';
$email = '';

/*
 * Shown once after logout.php redirects here. The text is fixed,
 * so nothing from the URL is ever printed.
 */
if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' &&
    ($_GET['logged_out'] ?? '') === '1'
) {
    $message = 'You have been logged out.';
    $message_type = 'success';
} elseif (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' &&
    ($_GET['account_deleted'] ?? '') === '1'
) {
    // Shown once after settings.php deletes the account.
    $message = 'Your account has been deleted.';
    $message_type = 'success';
}

/*
 * Escape values before placing them into HTML.
 */
$login_escape = static function (string $value): string {
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    /*
     * Reject unexpected array inputs.
     * Trim the email, but never trim the password.
     */
    $email = is_string($_POST['email'] ?? null)
        ? trim($_POST['email'])
        : '';

    $password = is_string($_POST['password'] ?? null)
        ? $_POST['password']
        : '';

    /*
     * Keep your existing CSRF helper.
     * It must return false or stop the request on an invalid token.
     */
    $csrf_result = verify_csrf();

    if ($csrf_result === false) {
        http_response_code(403);

        $message =
            'Your session could not be verified. ' .
            'Refresh and try again.';

    } elseif ($email === '' || $password === '') {
        $message = 'Please enter your email address and password.';

    } elseif (
        strlen($email) > 254 ||
        filter_var($email, FILTER_VALIDATE_EMAIL) === false
    ) {
        $message = 'Please enter a valid email address.';

    } elseif (
        strlen($password) > 4096 ||
        strpos($password, "\0") !== false
    ) {
        /*
         * Reject abnormal input without revealing
         * whether an account exists.
         */
        $message = 'Email or password is incorrect.';

    } else {
        $stmt = null;
        $previous_report_mode = null;

        $account = null;
        $authenticated = false;

        try {
            if (!isset($conn) || !($conn instanceof mysqli)) {
                throw new RuntimeException(
                    'Database connection unavailable.'
                );
            }

            /*
             * Use exceptions for this query and restore
             * the previous MySQLi reporting mode afterwards.
             */
            $driver = new mysqli_driver();

            $previous_report_mode = $driver->report_mode;

            mysqli_report(
                MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT
            );

            /*
             * Retrieve only the columns needed for login.
             *
             * LIMIT 2 lets us reject ambiguous duplicate-email
             * records instead of silently choosing one.
             */
            $stmt = mysqli_prepare(
                $conn,
                'SELECT id, fullname, role, password
                 FROM users
                 WHERE email = ?
                 LIMIT 2'
            );

            mysqli_stmt_bind_param(
                $stmt,
                's',
                $email
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) === 1) {
                mysqli_stmt_bind_result(
                    $stmt,
                    $id,
                    $fullname,
                    $role,
                    $stored_password
                );

                if (mysqli_stmt_fetch($stmt) === true) {
                    $account = [
                        'id' => (int) $id,
                        'fullname' => (string) $fullname,
                        'role' => (string) $role,
                        'password' => (string) $stored_password,
                    ];
                }
            }

            /*
             * Unknown accounts still perform a hash comparison.
             *
             * This dummy hash is NOT a demo account,
             * a usable password or a login bypass.
             */
            $dummy_hash =
                '$2y$12$hCkGFuV3rbln.4qpoJNxx.g7amsDiCOyKIGEktpU3cndzMiHM/KfS';

            $hash_to_check = $account['password'] ?? $dummy_hash;

            $password_matches = password_verify(
                $password,
                $hash_to_check
            );

            if (
                $account !== null &&
                $account['id'] > 0 &&
                $password_matches
            ) {
                /*
                 * Retain your existing session-ID renewal
                 * after successful authentication.
                 */
                if (!session_regenerate_id(true)) {
                    throw new RuntimeException(
                        'Session renewal failed.'
                    );
                }

                $_SESSION['user_id'] = $account['id'];
                $_SESSION['fullname'] = $account['fullname'];

                /*
                 * Permissions come from the database,
                 * never from a submitted form field.
                 */
                $_SESSION['role'] = $account['role'];

                $authenticated = true;

            } else {
                $message = 'Email or password is incorrect.';
            }

        } catch (Throwable $error) {
            /*
             * Do not log passwords or show database
             * details to the visitor.
             */
            error_log(
                'DiSSENT login failure: ' . get_class($error)
            );

            http_response_code(503);

            $message =
                'Login is temporarily unavailable. ' .
                'Please try again later.';

        } finally {
            if ($stmt instanceof mysqli_stmt) {
                mysqli_stmt_close($stmt);
            }

            if ($previous_report_mode !== null) {
                mysqli_report($previous_report_mode);
            }
        }

        if ($authenticated) {
            header('Location: dashboard.php', true, 303);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DiSSENT</title>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/auth.css?v=5">
    <script src="../assets/JavaScript/auth-forms.js?v=2" defer></script>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="auth-section" aria-labelledby="login-title">
    <div class="auth-card">
        <h1 id="login-title">Login to DiSSENT</h1>
        <p class="auth-intro">Access your media workspace, reports and collaboration tools.</p>

        <?php if ($message !== ''): ?>
            <div
                class="auth-message auth-message--<?php echo $login_escape($message_type); ?>"
                role="<?php echo $message_type === 'success' ? 'status' : 'alert'; ?>"
                <?php echo $message_type === 'error' ? 'tabindex="-1"' : ''; ?>
            >
                <?php echo $login_escape($message); ?>
            </div>
        <?php endif; ?>

        <form
            id="dissent-login-form"
            class="auth-form"
            method="POST"
            action="login.php"
            data-auth-form
        >
            <?php echo csrf_field(); ?>

            <div class="auth-field">
                <label for="login-email">Email address</label>
                <input
                    class="auth-input"
                    id="login-email"
                    type="email"
                    name="email"
                    placeholder="Enter your email address"
                    autocomplete="username"
                    inputmode="email"
                    autocapitalize="none"
                    spellcheck="false"
                    maxlength="254"
                    value="<?php echo $login_escape($email); ?>"
                    required
                >
            </div>

            <div class="auth-field">
                <label for="login-password">Password</label>
                <div class="password-field">
                    <input
                        class="auth-input"
                        id="login-password"
                        type="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        autocapitalize="none"
                        spellcheck="false"
                        required
                    >
                    <?php echo password_toggle_html('login-password'); ?>
                </div>
            </div>

            <p class="auth-forgot"><a href="forgot_password.php">Forgot password?</a></p>

            <button class="auth-submit" type="submit" data-busy-text="Logging in…">Log in</button>
        </form>

        <div class="auth-links">
            <p>Don't have an account? <a href="register.php">Register</a></p>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
