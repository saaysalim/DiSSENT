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

// Read text fields without accepting unexpected arrays.
$read_text = static function ($name) {
    $value = $_POST[$name] ?? '';
    return is_string($value) ? trim($value) : '';
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
                profile_picture, role, created_at
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
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $message = '';
    $message_type = 'error';

    // Passwords are changed in Settings only (one form for the whole site).
    if (!isset($_POST['update_profile'])) {
        $message = 'Please use the form below. To change your password, open Settings.';
    } else {
        $fullname_value = $read_text('fullname');
        list($organisation_value, $category_error) = category_from_post('organisation', professional_categories());

        if (!is_string($_POST['fullname'] ?? null)) {
            $message = 'Please enter valid profile information.';
        } elseif ($fullname_value === '') {
            $message = 'Full name cannot be empty.';
        } elseif ($text_length($fullname_value) > 100) {
            $message = 'Please keep your name within 100 characters.';
        } elseif ($category_error !== '') {
            $message = $category_error;
            $organisation_value = (string) ($user['organisation'] ?? '');
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
    }
}


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
    <meta name="description" content="Your DiSSENT profile: name, professional category and picture.">

    <link rel="stylesheet" href="../assets/css/base.css?v=5">
    <link rel="stylesheet" href="../assets/css/auth.css?v=7">
    <link rel="stylesheet" href="../assets/css/profile.css?v=3">
    <script src="../assets/JavaScript/auth-forms.js?v=3" defer></script>
</head>

<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="auth-section profile-page" id="main-content" tabindex="-1" aria-labelledby="profileHeading">
    <div class="profile-container">

        <div class="profile-summary">
            <?php echo get_avatar_html($user); ?>

            <div class="profile-summary__text">
                <h1 id="profileHeading"><?php echo e($user['fullname']); ?></h1>
                <p class="profile-summary__email"><?php echo e($user['email']); ?></p>

                <ul class="profile-facts">
                    <li><span>Account</span> <?php echo $user['role'] === 'admin' ? 'Administrator' : 'Member'; ?></li>
                    <?php if ($organisation_value !== ''): ?>
                        <li><span>Category</span> <?php echo e($organisation_value); ?></li>
                    <?php endif; ?>
                    <li><span>Member since</span> <?php echo e($member_since); ?></li>
                </ul>
            </div>
        </div>

        <div class="profile-grid">

            <section class="auth-card profile-card" aria-labelledby="profileDetailsHeading">
                <h2 id="profileDetailsHeading">Profile details</h2>
                <p class="auth-intro">Update your name, category and picture.</p>

                <?php if ($message !== ''): ?>
                    <div
                        class="auth-message auth-message--<?php echo e($message_type); ?>"
                        role="<?php echo $message_type === 'error' ? 'alert' : 'status'; ?>"
                        <?php echo $message_type === 'error' ? 'tabindex="-1"' : ''; ?>
                    >
                        <?php echo e($message); ?>
                    </div>
                <?php endif; ?>

                <form
                    method="POST"
                    action="profile.php"
                    enctype="multipart/form-data"
                    class="auth-form"
                    data-auth-form
                >
                    <?php echo csrf_field(); ?>

                    <input type="hidden" name="update_profile" value="1">

                    <div class="auth-field">
                        <label for="fullname">Full name</label>
                        <input
                            class="auth-input"
                            type="text"
                            id="fullname"
                            name="fullname"
                            placeholder="Enter your full name"
                            autocomplete="name"
                            autocapitalize="words"
                            maxlength="100"
                            value="<?php echo e($fullname_value); ?>"
                            required
                        >
                    </div>

                    <div class="auth-field">
                        <label for="email">Email address</label>
                        <input
                            class="auth-input"
                            type="email"
                            id="email"
                            autocomplete="email"
                            value="<?php echo e($user['email']); ?>"
                            aria-describedby="emailHelp"
                            readonly
                        >
                        <small id="emailHelp" class="auth-help">Your email address cannot be changed here.</small>
                    </div>

                    <div class="auth-field">
                        <?php echo category_select_html(
                            'organisation',
                            'organisation',
                            'Professional category',
                            professional_categories(),
                            $organisation_value,
                            'Choose "Other" to type your own. This does not change your account permissions.'
                        ); ?>
                    </div>

                    <div class="auth-field">
                        <label for="profile_picture">Profile picture</label>
                        <input
                            class="auth-input"
                            type="file"
                            id="profile_picture"
                            name="profile_picture"
                            accept="image/jpeg,image/png,image/gif"
                            aria-describedby="pictureHelp"
                        >
                        <small id="pictureHelp" class="auth-help">
                            JPG, PNG or GIF. Leave empty to keep your current picture. Hidden data such as the location of a phone photo is removed.
                        </small>
                    </div>

                    <button type="submit" class="auth-submit" data-busy-text="Saving…">
                        Save Changes
                    </button>
                </form>
            </section>

            <section class="auth-card profile-card" aria-labelledby="passwordHeading">
                <h2 id="passwordHeading">Password</h2>
                <p class="auth-intro">You change your password in Settings. Your other devices are then logged out; this one stays logged in.</p>
                <a class="auth-submit auth-submit--secondary profile-settings-link" href="settings.php#password">Change password in Settings</a>
            </section>

        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
