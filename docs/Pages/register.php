<?php
session_start();
include('../backend/db.php');
include('../backend/content.php');

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    verify_csrf();

    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $organisation = trim($_POST['role']); // job type / org, stored separately from account permissions
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if(strlen($password) < 8){

        $message = "Password must be at least 8 characters long.";

    }
    elseif($password != $confirm_password){

        $message = "Passwords do not match.";

    }
    else{

        $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($check_stmt, 's', $email);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if(mysqli_stmt_num_rows($check_stmt) > 0){

            $message = "Email already exists.";

        }else{

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // role is always 'user' at registration — admin access is never self-service
            $stmt = mysqli_prepare($conn, "INSERT INTO users (fullname, email, password, role, organisation) VALUES (?, ?, ?, 'user', ?)");
            mysqli_stmt_bind_param($stmt, 'ssss', $fullname, $email, $hashed_password, $organisation);

            if(mysqli_stmt_execute($stmt)){

                $message = "Registration successful! You can now login.";

            }else{

                $message = "Registration failed. Please try again.";

            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Register - DiSSENT</title>

    <link rel="stylesheet"
        href="../assets/css/base.css">

    <link rel="stylesheet"
        href="../assets/css/auth.css">

</head>

<body>

<?php include('../includes/header.php'); ?>

<section class="register-section">

    <div class="register-container">

        <h1>Create Account</h1>

        <p>
            Register to access secure cross-border journalism services.
        </p>

        <?php if(!empty($message)){ ?>

            <div style="
                background:#f4f4f4;
                padding:12px;
                margin-bottom:20px;
                border-radius:6px;
                text-align:center;
                font-weight:bold;
                color:#333;
            ">
                <?php echo $message; ?>
            </div>

        <?php } ?>

        <form class="register-form"
            method="POST"
            action="">

            <?php echo csrf_field(); ?>

            <input
                type="text"
                name="fullname"
                placeholder="Full Name"
                required>

            <input
                type="email"
                name="email"
                placeholder="Email Address"
                required>

            <select name="role" required>

    <option value="">Select Role</option>

    <option value="Journalist">
        Journalist
    </option>

    <option value="Media Organisation">
        Media Organisation
    </option>

    <option value="Cross-Border Partner">
        Cross-Border Partner
    </option>

    <option value="Fact Checker">
        Fact Checker
    </option>

    <option value="Investigator">
        Investigator
    </option>

</select>

            <input
                type="password"
                name="password"
                placeholder="Password"
                required>

            <input
                type="password"
                name="confirm_password"
                placeholder="Confirm Password"
                required>

            <button type="submit">
                Register
            </button>

        </form>

        <div class="register-links">

            <p>
                Already have an account?
                <a href="login.php">
                    Login
                </a>
            </p>

        </div>

    </div>

</section>

<?php include('../includes/footer.php'); ?>

</body>
</html>