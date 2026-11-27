<?php
session_start();
include('../backend/db.php');
include('../backend/content.php');
include('../backend/mailer.php');

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$token = is_string($token) ? $token : '';
$message = "";
$message_type = "error";
$valid = false;
$reset = null;

try {
    $reset = get_valid_reset($token);
} catch (Throwable $error) {
    error_log('DiSSENT: reset token lookup failed: ' . get_class($error));
}

if(!$reset){
    $message = "This reset link is invalid or has expired (links work for 60 minutes). Please request a new one.";
}else{
    $valid = true;

    if($_SERVER["REQUEST_METHOD"] == "POST"){

        verify_csrf();

        // Passwords are never trimmed.
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $confirm_password = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

        if($password === '' || $confirm_password === ''){
            $message = "Please fill in both password fields.";
        }elseif(($password_problem = password_policy_message($password)) !== ''){
            // Same rules as registration and the profile page.
            $message = $password_problem;
        }elseif($password !== $confirm_password){
            $message = "Passwords do not match.";
        }else{
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // A new session_version logs this account out everywhere.
            $stmt = mysqli_prepare($conn, "UPDATE users SET password = ?, session_version = session_version + 1 WHERE email = ? AND role <> 'deleted'");
            mysqli_stmt_bind_param($stmt, 'ss', $hashed_password, $reset['email']);
            mysqli_stmt_execute($stmt);

            if(mysqli_stmt_affected_rows($stmt) === 1){
                consume_reset_token($token);
                send_password_changed_email($reset['email']);

                $message = "Password updated successfully. You can now log in.";
                $message_type = "success";
                $valid = false; // hide the form after success
            }else{
                $message = "Your password could not be changed. Please try again later.";
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
    <title>Reset Password - DiSSENT</title>
    <meta name="description" content="Choose a new password for your DiSSENT account.">
    <link rel="stylesheet" href="../assets/css/base.css?v=5">
    <link rel="stylesheet" href="../assets/css/auth.css?v=7">
    <script src="../assets/JavaScript/auth-forms.js?v=3" defer></script>
</head>
<body>

<?php include('../includes/header.php'); ?>

<main class="auth-section" id="main-content" tabindex="-1" aria-labelledby="reset-title">
    <div class="auth-card">
        <h1 id="reset-title">Reset Password</h1>
        <p class="auth-intro">Choose a new password for your DiSSENT account.</p>

        <?php if($message !== "") { ?>
            <div
                class="auth-message auth-message--<?php echo e($message_type); ?>"
                role="<?php echo $message_type === 'error' ? 'alert' : 'status'; ?>"
                <?php echo $message_type === 'error' ? 'tabindex="-1"' : ''; ?>
            >
                <?php echo e($message); ?>
            </div>
        <?php } ?>

        <?php if($valid){ ?>

            <form class="auth-form" method="POST" action="reset_password.php" data-auth-form>

                <?php echo csrf_field(); ?>
                <input type="hidden" name="token" value="<?php echo e($token); ?>">

                <!-- Lets password managers save the new password for the right account. -->
                <input type="email" autocomplete="username" value="<?php echo e($reset['email']); ?>" readonly hidden>

                <div class="auth-field">
                    <label for="reset-password">New password</label>
                    <div class="password-field">
                        <input
                            class="auth-input"
                            id="reset-password"
                            type="password"
                            name="password"
                            placeholder="New password"
                            autocomplete="new-password"
                            autocapitalize="none"
                            spellcheck="false"
                            minlength="8"
                            aria-describedby="reset-password-rules"
                            required
                        >
                        <?php echo password_toggle_html('reset-password'); ?>
                    </div>
                    <?php echo password_rules_html('reset-password-rules', 'reset-password'); ?>
                </div>

                <div class="auth-field">
                    <label for="reset-confirm-password">Confirm new password</label>
                    <div class="password-field">
                        <input
                            class="auth-input"
                            id="reset-confirm-password"
                            type="password"
                            name="confirm_password"
                            placeholder="Repeat password"
                            autocomplete="new-password"
                            autocapitalize="none"
                            spellcheck="false"
                            minlength="8"
                            aria-describedby="reset-password-match"
                            required
                        >
                        <?php echo password_toggle_html('reset-confirm-password'); ?>
                    </div>
                    <?php echo password_match_html('reset-password-match', 'reset-confirm-password', 'reset-password'); ?>
                </div>

                <button class="auth-submit" type="submit" data-busy-text="Updating…">Update Password</button>

            </form>

        <?php } ?>

        <div class="auth-links">
            <p>
                <?php if(!$reset){ ?>
                    <a href="forgot_password.php">Request a new link</a> ·
                <?php } ?>
                <a href="login.php">Back to Login</a>
            </p>
        </div>
    </div>
</main>

<?php include('../includes/footer.php'); ?>

</body>
</html>
