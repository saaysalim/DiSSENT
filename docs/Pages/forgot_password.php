<?php
session_start();
include('../backend/db.php');
include('../backend/content.php');

$message = "";
$message_type = "success";
$debug_link = "";
$email = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    verify_csrf();

    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';

    if($email === '' || strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false){
        $message = "Please enter a valid email address.";
        $message_type = "error";
    }else{
        try {
            $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            $account_found = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);

            if($account_found){

                $token = create_password_reset($email);

                // http or https and host come from APP_URL (or the request);
                // the folder comes from this script's real path, not the query string.
                $page_folder = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/Pages/forgot_password.php')), '/');
                $reset_link = app_base_url() . $page_folder . "/reset_password.php?token=" . $token;

                // Attempt to send email. On free hosting this may silently fail.
                @mail($email, "DiSSENT Password Reset", "Click here to reset your password: " . $reset_link);

                // Local development only (APP_DEBUG=1): show the link on screen.
                if(DEBUG_MODE){
                    $debug_link = $reset_link;
                }
            }
        } catch (Throwable $error) {
            // Never reveal whether the email exists or show database details.
            error_log('DiSSENT: password reset request failed: ' . get_class($error));
        }

        // Always show the same message, whether or not the email was found —
        // this prevents visitors from discovering which emails are registered.
        $message = "If an account exists for this email, we have sent a password reset link.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - DiSSENT</title>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/auth.css?v=5">
    <script src="../assets/JavaScript/auth-forms.js?v=2" defer></script>
</head>
<body>

<?php include('../includes/header.php'); ?>

<main class="auth-section" aria-labelledby="forgot-title">
    <div class="auth-card">
        <h1 id="forgot-title">Forgot Password</h1>
        <p class="auth-intro">Enter the email address you registered with and we will send you a link to choose a new password.</p>

        <?php if($message !== "") { ?>
            <div
                class="auth-message auth-message--<?php echo e($message_type); ?>"
                role="<?php echo $message_type === 'error' ? 'alert' : 'status'; ?>"
                <?php echo $message_type === 'error' ? 'tabindex="-1"' : ''; ?>
            >
                <?php echo e($message); ?>
            </div>
        <?php } ?>

        <?php if($debug_link !== "") { ?>
            <div class="auth-message auth-message--debug">
                <strong>Local development only (APP_DEBUG=1)</strong>
                <a href="<?php echo e($debug_link); ?>"><?php echo e($debug_link); ?></a>
            </div>
        <?php } ?>

        <form class="auth-form" method="POST" action="forgot_password.php" data-auth-form>

            <?php echo csrf_field(); ?>

            <div class="auth-field">
                <label for="forgot-email">Email address</label>
                <input
                    class="auth-input"
                    id="forgot-email"
                    type="email"
                    name="email"
                    placeholder="Enter your email address"
                    autocomplete="email"
                    inputmode="email"
                    autocapitalize="none"
                    spellcheck="false"
                    maxlength="254"
                    value="<?php echo e($email); ?>"
                    required
                >
            </div>

            <button class="auth-submit" type="submit" data-busy-text="Sending…">Send Reset Link</button>

        </form>

        <div class="auth-links">
            <p><a href="login.php">Back to Login</a></p>
        </div>
    </div>
</main>

<?php include('../includes/footer.php'); ?>

</body>
</html>
