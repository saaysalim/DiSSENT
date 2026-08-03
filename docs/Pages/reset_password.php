<?php
session_start();
include('../backend/db.php');
include('../backend/content.php');

$token = isset($_GET['token']) ? $_GET['token'] : (isset($_POST['token']) ? $_POST['token'] : '');
$message = "";
$valid = false;

$reset = get_valid_reset($token);

if(!$reset){
    $message = "This reset link is invalid or has expired. Please request a new one.";
}else{
    $valid = true;

    if($_SERVER["REQUEST_METHOD"] == "POST"){

        verify_csrf();

        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if(strlen($password) < 8){
            $message = "Password must be at least 8 characters long.";
        }elseif($password != $confirm_password){
            $message = "Passwords do not match.";
        }else{
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE email = ?");
            mysqli_stmt_bind_param($stmt, 'ss', $hashed_password, $reset['email']);
            mysqli_stmt_execute($stmt);

            consume_reset_token($token);

            $message = "Password updated successfully. You can now log in.";
            $valid = false; // hide the form after success
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

<link rel="stylesheet" href="../assets/css/base.css">
<link rel="stylesheet" href="../assets/css/auth.css">
</head>

<body>

<?php include('../includes/header.php'); ?>

<section class="forgot-section">

    <div class="forgot-container">

        <h1>Reset Password</h1>

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

        <?php if($valid){ ?>

            <form class="forgot-form" method="POST">

                <?php echo csrf_field(); ?>
                <input type="hidden" name="token" value="<?php echo e($token); ?>">

                <input
                    type="password"
                    name="password"
                    placeholder="New Password"
                    required>

                <input
                    type="password"
                    name="confirm_password"
                    placeholder="Confirm New Password"
                    required>

                <button type="submit">
                    Update Password
                </button>

            </form>

        <?php } ?>

        <div class="forgot-links">
            <a href="login.php">Back to Login</a>
        </div>

    </div>

</section>

<?php include('../includes/footer.php'); ?>

</body>
</html>