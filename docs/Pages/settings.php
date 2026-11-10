<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';

require_login();

header('Cache-Control: no-store');

$user_id = (int) $_SESSION['user_id'];
$languages = settings_languages();

// One message per section: [section => [type, text]].
$notices = array();

// Show success feedback once after redirecting (PRG pattern).
$flash = $_SESSION['settings_flash'] ?? '';
unset($_SESSION['settings_flash']);

$flash_messages = array(
    'language_saved' => array('language', 'Your language preference was saved.'),
    'notifications_saved' => array('notifications', 'Your notification preferences were saved.'),
    'password_saved' => array('password', 'Password changed successfully.'),
);

if (isset($flash_messages[$flash])) {
    $notices[$flash_messages[$flash][0]] = array('success', $flash_messages[$flash][1]);
}

$read_password = static function ($name) {
    $value = $_POST[$name] ?? '';
    return is_string($value) ? $value : '';
};

$redirect_with = static function ($flash_key) {
    $_SESSION['settings_flash'] = $flash_key;
    header('Location: settings.php', true, 303);
    exit();
};

// Load the account (for the email shown on the page) and the saved settings.
$stmt = mysqli_prepare($conn, 'SELECT fullname, email, role FROM users WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$account = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$account) {
    $_SESSION = array();
    session_destroy();
    header('Location: login.php', true, 303);
    exit();
}

$settings_available = true;
$settings = array('language' => 'en', 'notify_report_updates' => 1, 'notify_messages' => 1);

try {
    $settings = get_user_settings($user_id);
} catch (Throwable $error) {
    // Usually means the migration has not been run yet.
    error_log('DiSSENT: settings unavailable: ' . get_class($error));
    $settings_available = false;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $action = is_string($action) ? $action : '';

    if ($action === 'save_language') {
        $language = $_POST['language'] ?? '';

        if (!$settings_available) {
            $notices['language'] = array('error', 'Settings cannot be saved right now. Please try again later.');
        } elseif (!is_string($language) || !isset($languages[$language])) {
            $notices['language'] = array('error', 'Please choose one of the listed languages.');
        } else {
            try {
                save_user_setting_values($user_id, array('language' => $language));
                $redirect_with('language_saved');
            } catch (Throwable $error) {
                error_log('DiSSENT: language save failed: ' . get_class($error));
                $notices['language'] = array('error', 'Your language preference could not be saved. Please try again later.');
            }
        }
    } elseif ($action === 'save_notifications') {
        if (!$settings_available) {
            $notices['notifications'] = array('error', 'Settings cannot be saved right now. Please try again later.');
        } else {
            try {
                save_user_setting_values($user_id, array(
                    'notify_report_updates' => isset($_POST['notify_report_updates']) ? 1 : 0,
                    'notify_messages' => isset($_POST['notify_messages']) ? 1 : 0,
                ));
                $redirect_with('notifications_saved');
            } catch (Throwable $error) {
                error_log('DiSSENT: notification save failed: ' . get_class($error));
                $notices['notifications'] = array('error', 'Your notification preferences could not be saved. Please try again later.');
            }
        }
    } elseif ($action === 'change_password') {
        try {
            $problem = account_change_password(
                $user_id,
                $read_password('current_password'),
                $read_password('new_password'),
                $read_password('confirm_password')
            );
        } catch (Throwable $error) {
            // Never log passwords.
            error_log('DiSSENT: settings password change failed: ' . get_class($error));
            $problem = 'Your password could not be changed. Please try again later.';
        }

        if ($problem === '') {
            session_regenerate_id(true);
            $redirect_with('password_saved');
        }

        $notices['password'] = array('error', $problem);
    } elseif ($action === 'delete_account') {
        $confirm_password = $read_password('delete_password');

        $stmt = mysqli_prepare($conn, 'SELECT password FROM users WHERE id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!isset($_POST['delete_confirm'])) {
            $notices['delete'] = array('error', 'Please tick the box to confirm that you understand this cannot be undone.');
        } elseif ($confirm_password === '' || !$row || !password_verify($confirm_password, (string) $row['password'])) {
            $notices['delete'] = array('error', 'Your password is incorrect. Your account was not deleted.');
        } else {
            $problem = account_delete($user_id);

            if ($problem === '') {
                // End the session completely, like logout.php.
                $_SESSION = array();

                if (ini_get('session.use_cookies')) {
                    $params = session_get_cookie_params();
                    setcookie(
                        session_name(),
                        '',
                        time() - 42000,
                        $params['path'],
                        $params['domain'],
                        $params['secure'],
                        $params['httponly']
                    );
                }

                session_destroy();
                header('Location: login.php?account_deleted=1', true, 303);
                exit();
            }

            $notices['delete'] = array('error', $problem);
        }
    } else {
        $notices['account'] = array('error', 'Unknown action. Please try again.');
    }
}

$notice_html = static function ($key) use ($notices) {
    if (!isset($notices[$key])) {
        return '';
    }

    list($type, $text) = $notices[$key];

    return '<div class="auth-message auth-message--' . e($type) . '"'
        . ' role="' . ($type === 'error' ? 'alert' : 'status') . '"'
        . ($type === 'error' ? ' tabindex="-1"' : '') . '>'
        . e($text) . '</div>';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - DiSSENT</title>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/auth.css?v=5">
    <link rel="stylesheet" href="../assets/css/settings.css?v=1">
    <script src="../assets/JavaScript/auth-forms.js?v=2" defer></script>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="auth-section settings-page" aria-labelledby="settingsHeading">
    <div class="settings-container">

        <div class="settings-intro">
            <h1 id="settingsHeading">Settings</h1>
            <p class="auth-intro">Manage your account, password, language, notifications and data.</p>
        </div>

        <nav class="settings-nav" aria-label="Settings sections">
            <ul>
                <li><a href="#account">Account</a></li>
                <li><a href="#password">Change password</a></li>
                <li><a href="#language">Language</a></li>
                <li><a href="#notifications">Notifications</a></li>
                <li><a href="#delete">Delete account</a></li>
            </ul>
        </nav>

        <?php if (!$settings_available): ?>
            <div class="auth-message auth-message--error" role="alert">
                Language and notification settings are not available yet.
                (Administrators: run <code>database/migrations/2026-11-10_user_settings.sql</code>.)
            </div>
        <?php endif; ?>

        <div class="settings-grid">

            <!-- Account -->
            <section class="auth-card settings-card" id="account" aria-labelledby="accountHeading">
                <h2 id="accountHeading">Account</h2>
                <?php echo $notice_html('account'); ?>
                <dl class="settings-facts">
                    <div><dt>Name</dt><dd><?php echo e($account['fullname']); ?></dd></div>
                    <div><dt>Email</dt><dd><?php echo e($account['email']); ?></dd></div>
                    <div><dt>Account role</dt><dd><?php echo e($account['role']); ?></dd></div>
                </dl>
                <p class="auth-help">Change your name, professional category and picture on your profile.</p>
                <a href="profile.php" class="auth-submit auth-submit--secondary settings-link-button">Edit profile</a>
            </section>

            <!-- Change password -->
            <section class="auth-card settings-card" id="password" aria-labelledby="passwordHeading">
                <h2 id="passwordHeading">Change password</h2>
                <p class="auth-intro">Enter your current password, then choose a new one.</p>
                <?php echo $notice_html('password'); ?>

                <form method="POST" action="settings.php#password" class="auth-form" data-auth-form>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="change_password">
                    <input type="email" autocomplete="username" value="<?php echo e($account['email']); ?>" readonly hidden>

                    <div class="auth-field">
                        <label for="settings-current-password">Current password</label>
                        <div class="password-field">
                            <input class="auth-input" type="password" id="settings-current-password" name="current_password"
                                autocomplete="current-password" autocapitalize="none" spellcheck="false" required>
                            <?php echo password_toggle_html('settings-current-password'); ?>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="settings-new-password">New password</label>
                        <div class="password-field">
                            <input class="auth-input" type="password" id="settings-new-password" name="new_password"
                                autocomplete="new-password" autocapitalize="none" spellcheck="false" minlength="8"
                                aria-describedby="settings-password-rules" required>
                            <?php echo password_toggle_html('settings-new-password'); ?>
                        </div>
                        <?php echo password_rules_html('settings-password-rules', 'settings-new-password'); ?>
                    </div>

                    <div class="auth-field">
                        <label for="settings-confirm-password">Confirm new password</label>
                        <div class="password-field">
                            <input class="auth-input" type="password" id="settings-confirm-password" name="confirm_password"
                                autocomplete="new-password" autocapitalize="none" spellcheck="false" minlength="8"
                                aria-describedby="settings-password-match" required>
                            <?php echo password_toggle_html('settings-confirm-password'); ?>
                        </div>
                        <?php echo password_match_html('settings-password-match', 'settings-confirm-password', 'settings-new-password'); ?>
                    </div>

                    <button type="submit" class="auth-submit" data-busy-text="Updating…">Update Password</button>
                </form>
            </section>

            <!-- Language -->
            <section class="auth-card settings-card" id="language" aria-labelledby="languageHeading">
                <h2 id="languageHeading">Language preference</h2>
                <p class="auth-intro">The site is in English for now. We will use your choice when translations are added.</p>
                <?php echo $notice_html('language'); ?>

                <form method="POST" action="settings.php#language" class="auth-form" data-auth-form>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save_language">

                    <div class="auth-field">
                        <label for="settings-language">Preferred language</label>
                        <select class="auth-select" id="settings-language" name="language" <?php echo $settings_available ? '' : 'disabled'; ?>>
                            <?php foreach ($languages as $code => $label): ?>
                                <option value="<?php echo e($code); ?>" <?php echo $settings['language'] === $code ? 'selected' : ''; ?>>
                                    <?php echo e($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="auth-submit" data-busy-text="Saving…" <?php echo $settings_available ? '' : 'disabled'; ?>>Save language</button>
                </form>
            </section>

            <!-- Notifications -->
            <section class="auth-card settings-card" id="notifications" aria-labelledby="notificationsHeading">
                <h2 id="notificationsHeading">Notification preferences</h2>
                <p class="auth-intro">Choose which emails you want. Email notifications are not switched on yet; your choices are saved for when they are.</p>
                <?php echo $notice_html('notifications'); ?>

                <form method="POST" action="settings.php#notifications" class="auth-form" data-auth-form>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save_notifications">

                    <fieldset class="settings-options" <?php echo $settings_available ? '' : 'disabled'; ?>>
                        <legend class="visually-hidden">Email notifications</legend>

                        <label class="settings-check">
                            <input type="checkbox" name="notify_report_updates" value="1" <?php echo $settings['notify_report_updates'] ? 'checked' : ''; ?>>
                            <span>
                                <strong>Report updates</strong>
                                <small>When one of your reports is approved or rejected.</small>
                            </span>
                        </label>

                        <label class="settings-check">
                            <input type="checkbox" name="notify_messages" value="1" <?php echo $settings['notify_messages'] ? 'checked' : ''; ?>>
                            <span>
                                <strong>New messages</strong>
                                <small>When someone sends you a secure message.</small>
                            </span>
                        </label>
                    </fieldset>

                    <button type="submit" class="auth-submit" data-busy-text="Saving…" <?php echo $settings_available ? '' : 'disabled'; ?>>Save notifications</button>
                </form>
            </section>

            <!-- Delete account -->
            <section class="auth-card settings-card settings-danger" id="delete" aria-labelledby="deleteHeading">
                <h2 id="deleteHeading">Delete my account</h2>
                <p class="auth-intro">This permanently removes your account. It cannot be undone.</p>
                <ul class="settings-list">
                    <li><strong>Removed:</strong> your login, settings, and reports that were not approved (with their documents).</li>
                    <li><strong>Kept:</strong> reports that were already approved stay published, and the people you messaged keep their conversations. In both places your name is replaced with “Deleted user”.</li>
                </ul>
                <?php echo $notice_html('delete'); ?>

                <form method="POST" action="settings.php#delete" class="auth-form" data-auth-form>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete_account">

                    <div class="auth-field">
                        <label for="settings-delete-password">Enter your password to confirm</label>
                        <div class="password-field">
                            <input class="auth-input" type="password" id="settings-delete-password" name="delete_password"
                                autocomplete="current-password" autocapitalize="none" spellcheck="false" required>
                            <?php echo password_toggle_html('settings-delete-password'); ?>
                        </div>
                    </div>

                    <label class="settings-check">
                        <input type="checkbox" name="delete_confirm" value="1" required>
                        <span><strong>I understand that my account will be deleted and this cannot be undone.</strong></span>
                    </label>

                    <button type="submit" class="auth-submit settings-delete-button" data-busy-text="Deleting…">Delete my account</button>
                </form>
            </section>

        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
