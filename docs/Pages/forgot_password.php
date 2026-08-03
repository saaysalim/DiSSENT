<?php
session_start();
include('../backend/db.php');
include('../backend/content.php');

$message = "";
$debug_link = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    verify_csrf();

    $email = trim($_POST['email']);

    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if(mysqli_stmt_num_rows($stmt) > 0){

        $token = create_password_reset($email);
        $reset_link = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/reset_password.php?token=" . $token;

        // Attempt to send email. On free hosting this may silently fail —
        // that's expected, see DEBUG_MODE fallback below.
        @mail($email, "DiSSENT Password Reset", "Click here to reset your password: " . $reset_link);

        if(DEBUG_MODE){
            $debug_link = $reset_link;
        }
    }

    // Always show the same message, whether or not the email was found —
    // this prevents visitors from discovering which emails are registered.
    $message = "If that email address is registered, a password reset link has been sent.";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Forgot Password - DiSSENT</title>

<link rel="stylesheet" href="../assets/css/base.css">
<link rel="stylesheet" href="../assets/css/auth.css">
</head>

<body>

<?php include('../includes/header.php'); ?>

<section class="forgot-section">

    <div class="forgot-container">

        <h1>Forgot Password</h1>

        <p>
            Enter your email address to recover your account.
        </p>

        <?php if($message != "") { ?>

            <div style="
                background:#f4f4f4;
                padding:10px;
                margin-bottom:15px;
                border-radius:5px;
                text-align:center;
                font-weight:bold;">
                <?php echo e($message); ?>
            </div>

        <?php } ?>

        <?php if($debug_link != "") { ?>

            <div style="
                background:#fff8e1;
                border-left:4px solid #f5a623;
                padding:10px;
                margin-bottom:15px;
                border-radius:5px;
                word-break:break-all;">
                <strong>DEBUG MODE (remove before launch):</strong><br>
                <a href="<?php echo e($debug_link); ?>"><?php echo e($debug_link); ?></a>
            </div>

        <?php } ?>

        <form class="forgot-form" method="POST">

            <?php echo csrf_field(); ?>

            <input
                type="email"
                name="email"
                placeholder="Email Address"
                required>

            <button type="submit">
                Send Reset Link
            </button>

        </form>

        <div class="forgot-links">

            <a href="login.php">
                Back to Login
            </a>

        </div>

    </div>

</section>

<?php include('../includes/footer.php'); ?>

</body>
</html>