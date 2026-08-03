<?php
session_start();
include('../backend/db.php');
include('../backend/content.php');

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    verify_csrf();

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if(mysqli_num_rows($result) == 1){

        $user = mysqli_fetch_assoc($result);

        if(password_verify($password, $user['password'])){

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];

            header("Location: dashboard.php");
            exit();

        } else {

            $message = "Incorrect password.";

        }

    } else {

        $message = "Email not found.";

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
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>

<body>

<?php include('../includes/header.php'); ?>

<section class="login-section">

    <div class="login-container">

        <h1>Login to DiSSENT</h1>

        <p>
            Access secure communication, verified reports,
            and cross-border collaboration tools.
        </p>

        <?php if($message != "") { ?>

            <div style="
                background:#f4f4f4;
                padding:10px;
                margin-bottom:15px;
                border-radius:5px;
                text-align:center;
                font-weight:bold;">
                <?php echo $message; ?>
            </div>

        <?php } ?>

        <form class="login-form" method="POST">

            <?php echo csrf_field(); ?>

            <input
                type="email"
                name="email"
                placeholder="Email Address"
                required>

            <input
                type="password"
                name="password"
                placeholder="Password"
                required>

            <button type="submit">
                Login
            </button>

        </form>

        <div class="login-links">

            <a href="forgot_password.php">
                Forgot Password?
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

<?php include('../includes/footer.php'); ?>

</body>
</html>