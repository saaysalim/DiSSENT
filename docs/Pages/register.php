<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';

$message = '';
$message_type = 'error';
$fullname = '';
$email = '';
$organisation = '';

// These are professional categories, not account permission levels.
// Keep the existing POST field "role" and database column "organisation".
$registration_categories = [
    'Journalist',
    'Media Organisation',
    'Cross-Border Partner',
    'Fact Checker',
    'Investigator',
];

// Local closures avoid adding global helper functions to the existing project.
$registration_post_text = static function (string $key): string {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
};

$registration_escape = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

// Preserve the existing eight-character minimum. Reject overlong bcrypt input
// rather than allowing the hashing algorithm to silently truncate it.
$registration_min_password_characters = 8;
$registration_max_password_bytes = PASSWORD_DEFAULT === PASSWORD_BCRYPT ? 72 : 1024;

// A successful POST redirects back to this page so refresh does not resubmit it.
if (($_SESSION['dissent_registration_success'] ?? false) === true) {
    unset($_SESSION['dissent_registration_success']);
    $message = 'Registration successful! You can now log in.';
    $message_type = 'success';
}

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
        $organisation = trim($registration_post_text('role'));

        // Do not trim or repopulate passwords.
        $password = $registration_post_text('password');
        $confirm_password = $registration_post_text('confirm_password');

        if ($fullname === '' || $email === '' || $organisation === '' ||
            $password === '' || $confirm_password === '') {
            $message = 'Please complete every field.';
        } elseif (preg_match('//u', $fullname) !== 1 || strpos($fullname, "\0") !== false) {
            $message = 'Please enter a valid full name.';
        } elseif (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $message = 'Please enter a valid email address.';
        } elseif (!in_array($organisation, $registration_categories, true)) {
            $message = 'Please choose one of the listed professional categories.';
        } elseif (strpos($password, "\0") !== false) {
            $message = 'The password contains an unsupported character. Please choose another password.';
        } elseif (strlen($password) > $registration_max_password_bytes) {
            $message = 'The password is too long for the current password-hashing configuration. Please use a shorter passphrase.';
        } elseif (preg_match('/\A.{' . $registration_min_password_characters . ',}\z/us', $password) !== 1) {
            // /u counts Unicode characters rather than UTF-8 bytes; /s includes newlines.
            $message = 'Password must contain at least eight valid characters.';
        } elseif ($password !== $confirm_password) {
            $message = 'Passwords do not match.';
        } else {
            $registration_created = false;
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

                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    // Retains the original local-prototype duplicate-email feedback.
                    $message = 'An account with this email already exists. Please log in.';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Account permissions are always fixed by the server.
                    // A submitted professional category cannot grant admin access.
                    $stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO users (fullname, email, password, role, organisation)
                        VALUES (?, ?, ?, 'user', ?)"
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
                    // Handles a duplicate rejected by an existing UNIQUE constraint.
                    // The pre-check alone does not prevent concurrent duplicate inserts.
                    $message = 'An account with these details already exists. Please try logging in.';
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
                $_SESSION['dissent_registration_success'] = true;
                header('Location: register.php', true, 303);
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
    <title>Register - DiSSENT</title>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <!-- Registration-only additions; shared CSS is not replaced. -->
    <link
    rel="stylesheet"
    href="../assets/css/register.css?v=3"
>

<script
    src="../assets/JavaScript/register-passwords.js?v=3"
    defer
></script>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<section id="dissent-registration" class="register-section" aria-labelledby="register-title">
    <div class="register-container">
        <h1 id="register-title">Create Account</h1>
        <p class="register-intro">Register to access the DiSSENT media workspace.</p>
        <p class="register-required">All fields are required.</p>

        <?php if ($message !== ''): ?>
            <div
                class="register-message register-message--<?php echo $registration_escape($message_type); ?>"
                role="<?php echo $message_type === 'success' ? 'status' : 'alert'; ?>"
            >
                <?php echo $registration_escape($message); ?>
            </div>
        <?php endif; ?>

        <form id="dissent-register-form" class="register-form" method="POST" action="register.php">
            <?php echo csrf_field(); ?>

            <div class="register-field">
                <label for="register-fullname">Full name</label>
                <input
                    class="register-input"
                    id="register-fullname"
                    type="text"
                    name="fullname"
                    placeholder="Enter your full name"
                    autocomplete="name"
                    value="<?php echo $registration_escape($fullname); ?>"
                    required
                >
            </div>

            <div class="register-field">
                <label for="register-email">Email address</label>
                <input
                    class="register-input"
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

            <div class="register-field">
                <label for="register-category">Professional category</label>
                <select class="register-select" id="register-category" name="role" required>
                    <option value="">Choose a category</option>
                    <?php foreach ($registration_categories as $category): ?>
                        <option
                            value="<?php echo $registration_escape($category); ?>"
                            <?php echo $organisation === $category ? 'selected' : ''; ?>
                        ><?php echo $registration_escape($category); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="register-field">
                <label for="register-password">Password</label>
                <div class="register-password-control">
                    <input
                        class="register-input"
                        id="register-password"
                        type="password"
                        name="password"
                        placeholder="Create a password"
                        autocomplete="new-password"
                        autocapitalize="none"
                        spellcheck="false"
                        minlength="<?php echo $registration_min_password_characters; ?>"
                        aria-describedby="register-password-help"
                        required
                    >
                    <button
                        class="register-password-toggle"
                        type="button"
                        data-password-toggle
                        data-password-label="password"
                        aria-controls="register-password"
                        aria-label="Show password"
                        hidden
                    >Show</button>
                </div>
                <small class="register-help" id="register-password-help">Use at least eight characters. Do not reuse an important account's password.</small>
            </div>

            <div class="register-field">
                <label for="register-confirm-password">Confirm password</label>
                <div class="register-password-control">
                    <input
                        class="register-input"
                        id="register-confirm-password"
                        type="password"
                        name="confirm_password"
                        placeholder="Enter your password again"
                        autocomplete="new-password"
                        autocapitalize="none"
                        spellcheck="false"
                        minlength="<?php echo $registration_min_password_characters; ?>"
                        required
                    >
                    <button
                        class="register-password-toggle"
                        type="button"
                        data-password-toggle
                        data-password-label="password confirmation"
                        aria-controls="register-confirm-password"
                        aria-label="Show password confirmation"
                        hidden
                    >Show</button>
                </div>
            </div>

            <noscript>
                <p class="register-help">The Show/Hide buttons need JavaScript. You can still register with your passwords hidden.</p>
            </noscript>

            <button class="register-submit" type="submit">Register</button>
        </form>

        <div class="register-links">
            <p>Already have an account? <a href="login.php">Log in</a></p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
