<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';
require_once __DIR__ . '/../backend/mailer.php';

$message = '';
$message_type = 'error';
$fullname = '';
$email = '';
$organisation = '';

// These are professional categories, not account permission levels.
// Keep the existing POST field "role" and database column "organisation".
// "Other" can be typed in the role_other box (backend/content.php).
$registration_categories = professional_categories();

// Local closures avoid adding global helper functions to the existing project.
$registration_post_text = static function (string $key): string {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
};

$registration_escape = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $message = '';
    $message_type = 'error';

    // Keep the existing helper. Also respect an explicit false return value.
    $registration_csrf_result = verify_csrf();

    if ($registration_csrf_result === false) {
        http_response_code(403);
        $message = 'Your form session could not be verified. Refresh the page and try again.';
    } else {
        $fullname = trim($registration_post_text('fullname'));
        $email = trim($registration_post_text('email'));
        $organisation_choice = trim($registration_post_text('role'));
        list($organisation, $category_error) = category_from_post('role', $registration_categories);

        // Do not trim or repopulate passwords.
        $password = $registration_post_text('password');
        $confirm_password = $registration_post_text('confirm_password');

        if ($fullname === '' || $email === '' || $organisation_choice === '' ||
            $password === '' || $confirm_password === '') {
            $message = 'Please complete every field.';
        } elseif (preg_match('//u', $fullname) !== 1 || strpos($fullname, "\0") !== false) {
            $message = 'Please enter a valid full name.';
        } elseif (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $message = 'Please enter a valid email address.';
        } elseif ($category_error !== '') {
            $message = $category_error;
        } elseif (($registration_password_problem = password_policy_message($password)) !== '') {
            // Same rules as reset password and profile (backend/content.php).
            $message = $registration_password_problem;
        } elseif ($password !== $confirm_password) {
            $message = 'Passwords do not match.';
        } elseif (($_POST['consent'] ?? '') !== '1') {
            // Required tick box; the time is saved in users.consent_at.
            $message = 'Please tick the box to agree to the Terms of use and confirm you have read the Privacy notice.';
        } else {
            // The visitor is never told whether the email was already registered
            // (that would show who uses DiSSENT). Both cases go to Login with the same
            // message; the owner of an existing account gets an email instead.
            $registration_created = false;
            $registration_existing = false;
            $check_stmt = null;
            $stmt = null;
            $registration_previous_report_mode = null;

            try {
                if (!isset($conn) || !($conn instanceof mysqli)) {
                    throw new RuntimeException('Registration database connection is unavailable.');
                }

                // Catch query errors consistently, even if db.php changes mysqli's mode.
                // Restore the previous mode afterwards for shared header/footer code.
                $registration_driver = new mysqli_driver();
                $registration_previous_report_mode = $registration_driver->report_mode;
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

                $check_stmt = mysqli_prepare(
                    $conn,
                    'SELECT id FROM users WHERE email = ? LIMIT 1'
                );
                mysqli_stmt_bind_param($check_stmt, 's', $email);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);

                // Hashed in both cases so the answer takes the same time.
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    $registration_existing = true;
                } else {
                    // Account permissions are always fixed by the server.
                    // A submitted professional category cannot grant admin access.
                    $stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO users (fullname, email, password, role, organisation, consent_at)
                        VALUES (?, ?, ?, 'user', ?, CURRENT_TIMESTAMP)"
                    );
                    mysqli_stmt_bind_param(
                        $stmt,
                        'ssss',
                        $fullname,
                        $email,
                        $hashed_password,
                        $organisation
                    );
                    mysqli_stmt_execute($stmt);
                    $registration_created = true;
                }
            } catch (mysqli_sql_exception $error) {
                if ((int) $error->getCode() === 1062) {
                    // Handles a duplicate rejected by the UNIQUE constraint
                    // (two registrations at the same moment).
                    $registration_existing = true;
                } else {
                    // Avoid logging submitted passwords, email addresses or full SQL errors.
                    error_log('DiSSENT registration: database error code ' . (int) $error->getCode());
                    $message = 'Registration could not be completed. Please try again later.';
                }
            } catch (Throwable $error) {
                error_log('DiSSENT registration: internal error type ' . get_class($error));
                $message = 'Registration could not be completed. Please try again later.';
            } finally {
                if ($check_stmt instanceof mysqli_stmt) {
                    mysqli_stmt_close($check_stmt);
                }
                if ($stmt instanceof mysqli_stmt) {
                    mysqli_stmt_close($stmt);
                }
                if ($registration_previous_report_mode !== null) {
                    mysqli_report($registration_previous_report_mode);
                }
            }

            if ($registration_created) {
                // "Welcome to DiSSENT" email. A sending problem is logged only.
                send_welcome_email($email, $fullname);
            } elseif ($registration_existing && !register_notice_recent($email)) {
                // "Someone tried to register with your email" (at most one per hour).
                auth_record_attempt('register_notice', $email);
                send_register_attempt_email($email);
            }

            if ($registration_created || $registration_existing) {
                // login.php shows: "Thank you. If this email address can be used,
                // your account is ready. You can log in now."
                header('Location: login.php?registered=1', true, 303);
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create an account - DiSSENT</title>
    <meta name="description" content="Create a free DiSSENT account to submit reports, message other journalists and follow editorial review.">
    <link rel="stylesheet" href="../assets/css/base.css?v=5">
    <link rel="stylesheet" href="../assets/css/auth.css?v=7">
    <script src="../assets/JavaScript/auth-forms.js?v=3" defer></script>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="auth-section" id="main-content" tabindex="-1" aria-labelledby="register-title">
    <div class="auth-card">
        <h1 id="register-title">Create an account</h1>
        <p class="auth-intro">Register to access the DiSSENT media workspace.</p>
        <p class="auth-note">All fields are required.</p>

        <?php if ($message !== ''): ?>
            <div
                class="auth-message auth-message--<?php echo $registration_escape($message_type); ?>"
                role="<?php echo $message_type === 'success' ? 'status' : 'alert'; ?>"
                <?php echo $message_type === 'error' ? 'tabindex="-1"' : ''; ?>
            >
                <?php echo $registration_escape($message); ?>
            </div>
        <?php endif; ?>

        <form
            id="dissent-register-form"
            class="auth-form"
            method="POST"
            action="register.php"
            data-auth-form
        >
            <?php echo csrf_field(); ?>

            <div class="auth-field">
                <label for="register-fullname">Full name</label>
                <input
                    class="auth-input"
                    id="register-fullname"
                    type="text"
                    name="fullname"
                    placeholder="Enter your full name"
                    autocomplete="name"
                    autocapitalize="words"
                    maxlength="100"
                    value="<?php echo $registration_escape($fullname); ?>"
                    required
                >
            </div>

            <div class="auth-field">
                <label for="register-email">Email address</label>
                <input
                    class="auth-input"
                    id="register-email"
                    type="email"
                    name="email"
                    placeholder="Enter your email address"
                    autocomplete="email"
                    inputmode="email"
                    autocapitalize="none"
                    spellcheck="false"
                    maxlength="254"
                    value="<?php echo $registration_escape($email); ?>"
                    required
                >
            </div>

            <div class="auth-field">
                <?php echo category_select_html(
                    'role',
                    'register-category',
                    'Professional category',
                    $registration_categories,
                    $organisation !== '' ? $organisation : ($organisation_choice ?? ''),
                    'This describes your work. It does not change your account permissions.'
                ); ?>
            </div>

            <div class="auth-field">
                <label for="register-password">Password</label>
                <div class="password-field">
                    <input
                        class="auth-input"
                        id="register-password"
                        type="password"
                        name="password"
                        placeholder="New password"
                        autocomplete="new-password"
                        autocapitalize="none"
                        spellcheck="false"
                        minlength="8"
                        aria-describedby="register-password-rules"
                        required
                    >
                    <?php echo password_toggle_html('register-password'); ?>
                </div>
                <?php echo password_rules_html('register-password-rules', 'register-password'); ?>
            </div>

            <div class="auth-field">
                <label for="register-confirm-password">Confirm password</label>
                <div class="password-field">
                    <input
                        class="auth-input"
                        id="register-confirm-password"
                        type="password"
                        name="confirm_password"
                        placeholder="Repeat password"
                        autocomplete="new-password"
                        autocapitalize="none"
                        spellcheck="false"
                        minlength="8"
                        aria-describedby="register-password-match"
                        required
                    >
                    <?php echo password_toggle_html('register-confirm-password'); ?>
                </div>
                <?php echo password_match_html('register-password-match', 'register-confirm-password', 'register-password'); ?>
            </div>

            <div class="auth-field auth-consent">
                <label class="auth-checkbox" for="register-consent">
                    <input type="checkbox" id="register-consent" name="consent" value="1" required>
                    <span>I agree to the <a href="terms.php" target="_blank" rel="noopener">Terms of use</a> and I have read the <a href="privacy.php" target="_blank" rel="noopener">Privacy notice</a> (both open in a new tab).</span>
                </label>
            </div>

            <button class="auth-submit" type="submit" data-busy-text="Creating account…">Register</button>
        </form>

        <div class="auth-links">
            <p>Already have an account? <a href="login.php">Log in</a></p>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
