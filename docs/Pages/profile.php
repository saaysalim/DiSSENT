<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';

require_login();

$user_id = (int) $_SESSION['user_id'];

$message = '';
$message_type = 'error';
$password_message = '';
$password_message_type = 'error';

// Read text fields without accepting unexpected arrays.
$read_text = static function ($name) {
    $value = $_POST[$name] ?? '';
    return is_string($value) ? trim($value) : '';
};

// Passwords must not be trimmed.
$read_password = static function ($name) {
    $value = $_POST[$name] ?? '';
    return is_string($value) ? $value : '';
};

$text_length = static function ($value) {
    return function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
};

// Load the current account before processing changes.
try {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, fullname, email, organisation,
                profile_picture, role, created_at, password
         FROM users
         WHERE id = ?
         LIMIT 1"
    );

    if (!$stmt) {
        throw new RuntimeException('Account query preparation failed.');
    }

    mysqli_stmt_bind_param($stmt, 'i', $user_id);

    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Account lookup failed.');
    }

    $result = mysqli_stmt_get_result($stmt);

    if (!$result) {
        throw new RuntimeException('Account result unavailable.');
    }

    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
} catch (Throwable $exception) {
    error_log('DiSSENT: profile account lookup failed.');
    http_response_code(503);
    exit('Your profile could not be loaded. Please try again later.');
}

if (!$user) {
    $_SESSION = array();
    session_destroy();

    header('Location: login.php', true, 303);
    exit();
}

$fullname_value = (string) $user['fullname'];
$organisation_value = (string) ($user['organisation'] ?? '');

// Show success feedback once after redirecting.
$flash = $_SESSION['profile_flash'] ?? '';
unset($_SESSION['profile_flash']);

if ($flash === 'profile_saved') {
    $message = 'Profile updated successfully.';
    $message_type = 'success';
} elseif ($flash === 'password_saved') {
    $password_message = 'Password changed successfully.';
    $password_message_type = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $message = '';
    $password_message = '';
    $message_type = 'error';
    $password_message_type = 'error';

    $update_profile = isset($_POST['update_profile']);
    $change_password = isset($_POST['change_password']);

    // Accept exactly one action per request.
    if ($update_profile === $change_password) {
        $message = 'Please submit one form at a time.';
    } elseif ($update_profile) {
        $fullname_value = $read_text('fullname');
        $organisation_value = $read_text('organisation');

        if (
            !is_string($_POST['fullname'] ?? null) ||
            !is_string($_POST['organisation'] ?? null)
        ) {
            $message = 'Please enter valid profile information.';
        } elseif ($fullname_value === '') {
            $message = 'Full name cannot be empty.';
        } elseif ($text_length($fullname_value) > 100) {
            $message = 'Please keep your name within 100 characters.';
        } elseif ($text_length($organisation_value) > 100) {
            $message = 'Please keep the organisation or job type within 100 characters.';
        } else {
            $picture_filename = null;
            $stmt = null;

            try {
                $file = $_FILES['profile_picture'] ?? null;

                if ($file !== null) {
                    if (
                        !is_array($file) ||
                        !isset($file['error']) ||
                        !is_int($file['error'])
                    ) {
                        throw new RuntimeException('Invalid upload data.');
                    }

                    if ($file['error'] !== UPLOAD_ERR_NO_FILE) {
                        if ($file['error'] !== UPLOAD_ERR_OK) {
                            $message = 'The picture could not be uploaded. Check its size and try again.';
                        } else {
                            $upload = handle_avatar_upload($file);

                            if (!is_array($upload)) {
                                throw new RuntimeException('Invalid upload response.');
                            }

                            if (isset($upload['error'])) {
                                $message = is_string($upload['error'])
                                    ? $upload['error']
                                    : 'The picture could not be uploaded.';
                            } elseif (
                                isset($upload['filename']) &&
                                is_string($upload['filename']) &&
                                $upload['filename'] !== ''
                            ) {
                                $picture_filename = $upload['filename'];
                            } else {
                                throw new RuntimeException('Uploaded filename unavailable.');
                            }
                        }
                    }
                }

                if ($message === '') {
                    if ($picture_filename !== null) {
                        $stmt = mysqli_prepare(
                            $conn,
                            "UPDATE users
                             SET fullname = ?,
                                 organisation = ?,
                                 profile_picture = ?
                             WHERE id = ?"
                        );

                        if (!$stmt) {
                            throw new RuntimeException('Profile update preparation failed.');
                        }

                        mysqli_stmt_bind_param(
                            $stmt,
                            'sssi',
                            $fullname_value,
                            $organisation_value,
                            $picture_filename,
                            $user_id
                        );
                    } else {
                        $stmt = mysqli_prepare(
                            $conn,
                            "UPDATE users
                             SET fullname = ?, organisation = ?
                             WHERE id = ?"
                        );

                        if (!$stmt) {
                            throw new RuntimeException('Profile update preparation failed.');
                        }

                        mysqli_stmt_bind_param(
                            $stmt,
                            'ssi',
                            $fullname_value,
                            $organisation_value,
                            $user_id
                        );
                    }

                    if (!mysqli_stmt_execute($stmt)) {
                        throw new RuntimeException('Profile update failed.');
                    }
                }
            } catch (Throwable $exception) {
                error_log('DiSSENT: profile update failed.');
                $message = 'Your changes could not be saved. Please try again later.';
            } finally {
                if ($stmt instanceof mysqli_stmt) {
                    mysqli_stmt_close($stmt);
                }
            }

            if ($message === '') {
                $_SESSION['fullname'] = $fullname_value;
                $_SESSION['profile_flash'] = 'profile_saved';

                header('Location: profile.php', true, 303);
                exit();
            }
        }
    } elseif ($change_password) {
        $current_password = $read_password('current_password');
        $new_password = $read_password('new_password');
        $confirm_password = $read_password('confirm_password');

        if (
            $current_password === '' ||
            $new_password === '' ||
            $confirm_password === ''
        ) {
            $password_message = 'Please complete all three password fields.';
        } elseif (
            !password_verify($current_password, $user['password'])
        ) {
            $password_message = 'Current password is incorrect.';
        } elseif ($text_length($new_password) < 8) {
            $password_message = 'New password must contain at least 8 characters.';
        } elseif (
            strlen($new_password) > 72 ||
            strpos($new_password, "\0") !== false
        ) {
            $password_message = 'This password is too long or contains an unsupported character. Please choose another.';
        } elseif ($new_password !== $confirm_password) {
            $password_message = 'New passwords do not match.';
        } elseif (password_verify($new_password, $user['password'])) {
            $password_message = 'Please choose a different password from your current one.';
        } else {
            $stmt = null;

            try {
                $hashed_password = password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );

                if ($hashed_password === false) {
                    throw new RuntimeException('Password hashing failed.');
                }

                // Avoid overwriting a password changed since this page loaded.
                $old_hash = $user['password'];

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE users
                     SET password = ?
                     WHERE id = ? AND password = ?"
                );

                if (!$stmt) {
                    throw new RuntimeException('Password update preparation failed.');
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    'sis',
                    $hashed_password,
                    $user_id,
                    $old_hash
                );

                if (!mysqli_stmt_execute($stmt)) {
                    throw new RuntimeException('Password update failed.');
                }

                if (mysqli_stmt_affected_rows($stmt) !== 1) {
                    $password_message = 'Your account changed during this request. Refresh the page and try again.';
                }
            } catch (Throwable $exception) {
                // Never log passwords.
                error_log('DiSSENT: password update failed.');
                $password_message = 'Your password could not be changed. Please try again later.';
            } finally {
                if ($stmt instanceof mysqli_stmt) {
                    mysqli_stmt_close($stmt);
                }
            }

            if ($password_message === '') {
                session_regenerate_id(true);
                $_SESSION['profile_flash'] = 'password_saved';

                header('Location: profile.php', true, 303);
                exit();
            }
        }
    }
}

// The password hash is not needed when rendering the page.
unset($user['password']);

$created_timestamp = strtotime((string) ($user['created_at'] ?? ''));
$member_since = $created_timestamp !== false
    ? date('d M Y', $created_timestamp)
    : 'Not available';
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

<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="profile-page">
    <section class="profile-section" aria-labelledby="profileHeading">
        <div class="profile-container">

            <div class="profile-header">
                <?php echo get_avatar_html($user); ?>

                <h1 id="profileHeading">
                    <?php echo e($user['fullname']); ?>
                </h1>

                <p>Manage your profile and account settings.</p>
            </div>

            <?php if ($message !== ''): ?>
                <div
                    class="profile-message profile-message--<?php echo e($message_type); ?>"
                    role="<?php echo $message_type === 'error' ? 'alert' : 'status'; ?>"
                >
                    <?php echo e($message); ?>
                </div>
            <?php endif; ?>

            <div class="profile-card">
                <h2>Account Information</h2>

                <p>
                    <strong>Account role:</strong>
                    <?php echo e($user['role']); ?>
                </p>

                <p>
                    <strong>Member since:</strong>
                    <?php echo e($member_since); ?>
                </p>

                <form
                    method="POST"
                    action="profile.php"
                    enctype="multipart/form-data"
                    class="profile-form"
                >
                    <?php echo csrf_field(); ?>

                    <input type="hidden" name="update_profile" value="1">

                    <label for="fullname">Full name</label>
                    <input
                        type="text"
                        id="fullname"
                        name="fullname"
                        autocomplete="name"
                        maxlength="100"
                        value="<?php echo e($fullname_value); ?>"
                        required
                    >

                    <label for="email">Email address (read-only)</label>
                    <input
                        type="email"
                        id="email"
                        autocomplete="email"
                        value="<?php echo e($user['email']); ?>"
                        readonly
                    >

                    <label for="organisation">
                        Organisation or job type
                    </label>
                    <input
                        type="text"
                        id="organisation"
                        name="organisation"
                        maxlength="100"
                        value="<?php echo e($organisation_value); ?>"
                        aria-describedby="organisationHelp"
                    >

                    <p id="organisationHelp" class="profile-help">
                        This describes your work. It does not change
                        your account permissions.
                    </p>

                    <label for="profile_picture">Profile picture</label>
                    <input
                        type="file"
                        id="profile_picture"
                        name="profile_picture"
                        accept="image/jpeg,image/png,image/gif"
                        aria-describedby="pictureHelp"
                    >

                    <p id="pictureHelp" class="profile-help">
                        Choose a JPG, PNG or GIF image. Leave this empty
                        to keep your current picture.
                    </p>

                    <button type="submit" class="btn-primary">
                        Save Changes
                    </button>
                </form>
            </div>

            <div class="profile-card">
                <h2>Change Password</h2>

                <?php if ($password_message !== ''): ?>
                    <div
                        class="profile-message profile-message--<?php echo e($password_message_type); ?>"
                        role="<?php echo $password_message_type === 'error' ? 'alert' : 'status'; ?>"
                    >
                        <?php echo e($password_message); ?>
                    </div>
                <?php endif; ?>

                <form
                    method="POST"
                    action="profile.php"
                    class="profile-form"
                >
                    <?php echo csrf_field(); ?>

                    <input type="hidden" name="change_password" value="1">

                    <label for="current_password">Current password</label>
                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        autocomplete="current-password"
                        required
                    >

                    <label for="new_password">New password</label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        autocomplete="new-password"
                        minlength="8"
                        aria-describedby="passwordHelp"
                        required
                    >

                    <p id="passwordHelp" class="profile-help">
                        Use at least 8 characters. Choose a unique password
                        that you do not use on another website.
                    </p>

                    <label for="confirm_password">
                        Confirm new password
                    </label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    >

                    <button type="submit" class="btn-secondary">
                        Update Password
                    </button>
                </form>
            </div>

        </div>
    </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>