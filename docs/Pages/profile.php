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
        } elseif (($new_password_problem = password_policy_message($new_password)) !== '') {
            $password_message = $new_password_problem;
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

$category_suggestions = array(
    'Journalist',
    'Freelance Journalist',
    'Media Organisation',
    'Fact Checker',
    'Researcher / Investigator',
    'NGO / Human Rights Organisation',
    'Other',
);

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
    <link rel="stylesheet" href="../assets/css/auth.css?v=5">
    <link rel="stylesheet" href="../assets/css/profile.css?v=2">
    <script src="../assets/JavaScript/auth-forms.js?v=2" defer></script>
</head>

<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="auth-section profile-page" aria-labelledby="profileHeading">
    <div class="profile-container">

        <div class="profile-summary">
            <?php echo get_avatar_html($user); ?>

            <div class="profile-summary__text">
                <h1 id="profileHeading"><?php echo e($user['fullname']); ?></h1>
                <p class="profile-summary__email"><?php echo e($user['email']); ?></p>

                <ul class="profile-facts">
                    <li><span>Account role</span> <?php echo e($user['role']); ?></li>
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
                        <label for="organisation">Professional category</label>
                        <input
                            class="auth-input"
                            type="text"
                            id="organisation"
                            name="organisation"
                            list="categorySuggestions"
                            autocomplete="organization-title"
                            maxlength="100"
                            value="<?php echo e($organisation_value); ?>"
                            aria-describedby="organisationHelp"
                        >
                        <datalist id="categorySuggestions">
                            <?php foreach ($category_suggestions as $suggestion): ?>
                                <option value="<?php echo e($suggestion); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <small id="organisationHelp" class="auth-help">
                            Pick a suggestion or type your own. This does not change your account permissions.
                        </small>
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
                            JPG, PNG or GIF. Leave empty to keep your current picture.
                        </small>
                    </div>

                    <button type="submit" class="auth-submit" data-busy-text="Saving…">
                        Save Changes
                    </button>
                </form>
            </section>

            <section class="auth-card profile-card" aria-labelledby="passwordHeading">
                <h2 id="passwordHeading">Change password</h2>
                <p class="auth-intro">Enter your current password, then choose a new one.</p>

                <?php if ($password_message !== ''): ?>
                    <div
                        class="auth-message auth-message--<?php echo e($password_message_type); ?>"
                        role="<?php echo $password_message_type === 'error' ? 'alert' : 'status'; ?>"
                        <?php echo $password_message_type === 'error' ? 'tabindex="-1"' : ''; ?>
                    >
                        <?php echo e($password_message); ?>
                    </div>
                <?php endif; ?>

                <form
                    method="POST"
                    action="profile.php"
                    class="auth-form"
                    data-auth-form
                >
                    <?php echo csrf_field(); ?>

                    <input type="hidden" name="change_password" value="1">

                    <!-- Lets password managers save the new password for this account. -->
                    <input type="email" autocomplete="username" value="<?php echo e($user['email']); ?>" readonly hidden>

                    <div class="auth-field">
                        <label for="current_password">Current password</label>
                        <div class="password-field">
                            <input
                                class="auth-input"
                                type="password"
                                id="current_password"
                                name="current_password"
                                autocomplete="current-password"
                                autocapitalize="none"
                                spellcheck="false"
                                required
                            >
                            <?php echo password_toggle_html('current_password'); ?>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="new_password">New password</label>
                        <div class="password-field">
                            <input
                                class="auth-input"
                                type="password"
                                id="new_password"
                                name="new_password"
                                autocomplete="new-password"
                                autocapitalize="none"
                                spellcheck="false"
                                minlength="8"
                                aria-describedby="newPasswordRules"
                                required
                            >
                            <?php echo password_toggle_html('new_password'); ?>
                        </div>
                        <?php echo password_rules_html('newPasswordRules', 'new_password'); ?>
                    </div>

                    <div class="auth-field">
                        <label for="confirm_password">Confirm new password</label>
                        <div class="password-field">
                            <input
                                class="auth-input"
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                autocomplete="new-password"
                                autocapitalize="none"
                                spellcheck="false"
                                minlength="8"
                                aria-describedby="newPasswordMatch"
                                required
                            >
                            <?php echo password_toggle_html('confirm_password'); ?>
                        </div>
                        <?php echo password_match_html('newPasswordMatch', 'confirm_password', 'new_password'); ?>
                    </div>

                    <button type="submit" class="auth-submit auth-submit--secondary" data-busy-text="Updating…">
                        Update Password
                    </button>
                </form>
            </section>

        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
