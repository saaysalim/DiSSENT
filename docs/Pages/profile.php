<?php
session_start();
include('../backend/db.php');
include('../backend/content.php');

require_login();

$user_id = $_SESSION['user_id'];
$message = "";
$password_message = "";

// Handle profile info update (name, organisation, picture)
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])){

    verify_csrf();

    $fullname = trim($_POST['fullname']);
    $organisation = trim($_POST['organisation']);

    if($fullname === ""){
        $message = "Full name cannot be empty.";
    }else{

        $picture_filename = null;

        if(!empty($_FILES['profile_picture']['name'])){
            $upload = handle_avatar_upload($_FILES['profile_picture']);

            if(isset($upload['error'])){
                $message = $upload['error'];
            }else{
                $picture_filename = $upload['filename'];
            }
        }

        if($message === ""){
            if($picture_filename){
                $stmt = mysqli_prepare($conn, "UPDATE users SET fullname = ?, organisation = ?, profile_picture = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'sssi', $fullname, $organisation, $picture_filename, $user_id);
            }else{
                $stmt = mysqli_prepare($conn, "UPDATE users SET fullname = ?, organisation = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'ssi', $fullname, $organisation, $user_id);
            }

            mysqli_stmt_execute($stmt);

            $_SESSION['fullname'] = $fullname; // keep header showing the updated name immediately
            $message = "Profile updated successfully.";
        }
    }
}

// Handle password change
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])){

    verify_csrf();

    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    if(!password_verify($current_password, $row['password'])){
        $password_message = "Current password is incorrect.";
    }elseif(strlen($new_password) < 8){
        $password_message = "New password must be at least 8 characters long.";
    }elseif($new_password != $confirm_password){
        $password_message = "New passwords do not match.";
    }else{
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $hashed, $user_id);
        mysqli_stmt_execute($stmt);
        $password_message = "Password changed successfully.";
    }
}

// Always fetch fresh user data to display
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile - DiSSENT</title>

<link rel="stylesheet" href="../assets/css/base.css">
<link rel="stylesheet" href="../assets/css/profile.css">
</head>

<body>

<?php include('../includes/header.php'); ?>

<section class="profile-section">

    <div class="profile-container">

        <div class="profile-header">

            <?php echo get_avatar_html($user); ?>

            <h1><?php echo e($user['fullname']); ?></h1>

            <p>Welcome to your DiSSENT profile</p>

        </div>

        <?php if($message != "") { ?>
            <div class="profile-message"><?php echo e($message); ?></div>
        <?php } ?>

        <div class="profile-card">

            <h2>Account Information</h2>

            <p><strong>Role:</strong> <?php echo e($user['role']); ?></p>
            <p><strong>Member Since:</strong> <?php echo e(date('d M Y', strtotime($user['created_at']))); ?></p>

            <form method="POST" enctype="multipart/form-data" class="profile-form">

                <?php echo csrf_field(); ?>
                <input type="hidden" name="update_profile" value="1">

                <label for="fullname">Full Name</label>
                <input type="text" id="fullname" name="fullname" value="<?php echo e($user['fullname']); ?>" required>

                <label for="email">Email (cannot be changed)</label>
                <input type="email" id="email" value="<?php echo e($user['email']); ?>" disabled>

                <label for="organisation">Organisation / Role Type</label>
                <input type="text" id="organisation" name="organisation" value="<?php echo e($user['organisation']); ?>">

                <label for="profile_picture">Profile Picture</label>
                <input type="file" id="profile_picture" name="profile_picture" accept="image/*">

                <button type="submit" class="btn-primary">Save Changes</button>

            </form>

        </div>

        <div class="profile-card">

            <h2>Change Password</h2>

            <?php if($password_message != "") { ?>
                <div class="profile-message"><?php echo e($password_message); ?></div>
            <?php } ?>

            <form method="POST" class="profile-form">

                <?php echo csrf_field(); ?>
                <input type="hidden" name="change_password" value="1">

                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" required>

                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required minlength="8">

                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">

                <button type="submit" class="btn-secondary">Update Password</button>

            </form>

        </div>

    </div>

</section>

<?php include('../includes/footer.php'); ?>

</body>
</html>