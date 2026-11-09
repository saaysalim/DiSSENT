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
$email = '';

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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Login - DiSSENT</title>

    <!-- Existing shared styles -->
    <link
        rel="stylesheet"
        href="../assets/css/base.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/auth.css"
    >

    <!-- Login-only responsive styles -->
    <link
        rel="stylesheet"
        href="../assets/css/login-enhancements.css?v=1"
    >

    <!-- Matches your existing JavaScript folder -->
    <script
        src="../assets/JavaScript/login-passwords.js?v=1"
        defer
    ></script>
</head>

<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<section
    id="dissent-login"
    class="login-section"
    aria-labelledby="login-title"
>
    <div class="login-container">

        <h1 id="login-title">
            Login to DiSSENT
        </h1>

        <p class="login-intro">
            Access your media workspace, reports and collaboration tools.
        </p>

        <?php if ($message !== ''): ?>
            <div
                id="login-message"
                class="login-message"
                role="alert"
                tabindex="-1"
            >
                <?php echo $login_escape($message); ?>
            </div>
        <?php endif; ?>

        <form
            id="dissent-login-form"
            class="login-form"
            method="POST"
            action="login.php"
        >
            <?php echo csrf_field(); ?>

            <!-- Email address -->
            <div class="login-field">

                <label for="login-email">
                    Email address
                </label>

                <input
                    class="login-input"
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

            <!-- Password and Show/Hide button -->
            <div class="login-field">

                <label for="login-password">
                    Password
                </label>

                <div class="login-password-control">

                    <input
                        class="login-input"
                        id="login-password"
                        type="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        autocapitalize="none"
                        spellcheck="false"
                        required
                    >

                    <button
                        id="login-password-toggle"
                        class="login-password-toggle"
                        type="button"
                        aria-controls="login-password"
                        aria-label="Show password"
                        hidden
                    >
                        Show
                    </button>

                </div>
            </div>

            <noscript>
                <p class="login-help">
                    You can still log in with JavaScript disabled.
                    Your password will remain hidden.
                </p>
            </noscript>

            <button
                id="login-submit"
                class="login-submit"
                type="submit"
            >
                Log in
            </button>

        </form>

        <div class="login-links">

            <a href="forgot_password.php">
                Forgot password?
            </a>

            <p>
                Don't have an account?
                <a href="register.php">
                    Register
                </a>
            </p>

        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>