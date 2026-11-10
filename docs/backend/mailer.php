<?php
/*
 * DiSSENT email sending (PHPMailer over SMTP).
 *
 * Settings come from the environment (docker-compose.yml reads them from .env):
 *   SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM, SMTP_SECURE
 * With the default .env (empty values) every email goes to the local Mailpit
 * inbox at http://127.0.0.1:8025 and nothing leaves this computer.
 *
 * Sending never shows technical details to visitors: failures are logged
 * (docker logs dissent-web) and the page carries on.
 *
 * Needs backend/content.php (e(), app_page_url()) to be loaded first.
 */

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

// PHPMailer is installed by docker/web/Dockerfile into /var/www/vendor (outside the web root).
$dissent_mailer_autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';

if (is_file($dissent_mailer_autoload)) {
    require_once $dissent_mailer_autoload;
}


function mail_settings()
{
    $from = trim((string) getenv('SMTP_FROM'));
    $from_name = 'DiSSENT';
    $from_address = 'no-reply@dissent.local';

    // Accept "Name <address>" or just "address".
    if (preg_match('/^\s*(.*?)\s*<([^<>\s]+)>\s*$/', $from, $parts)) {
        $from_name = $parts[1] !== '' ? trim($parts[1], " \"'") : $from_name;
        $from_address = $parts[2];
    } elseif ($from !== '') {
        $from_address = $from;
    }

    return array(
        'host' => trim((string) getenv('SMTP_HOST')) ?: 'mailpit',
        'port' => (int) (getenv('SMTP_PORT') ?: 1025),
        'user' => (string) getenv('SMTP_USER'),
        'pass' => (string) getenv('SMTP_PASS'),
        'secure' => strtolower(trim((string) getenv('SMTP_SECURE'))),
        'from_address' => $from_address,
        'from_name' => $from_name,
    );
}


// Sends one email. Returns true when the SMTP server accepted it.
// $purpose is a short label for the log ("welcome", "password reset").
function send_app_email($to, $subject, $html, $text, $purpose)
{
    if (!class_exists(PHPMailer::class)) {
        error_log('DiSSENT: ' . $purpose . ' email not sent: PHPMailer is not installed (rebuild the web container).');
        return false;
    }

    if (!is_string($to) || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
        error_log('DiSSENT: ' . $purpose . ' email not sent: invalid recipient address.');
        return false;
    }

    $settings = mail_settings();
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $settings['host'];
        $mail->Port = $settings['port'];
        $mail->Timeout = 10;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;

        if ($settings['user'] !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $settings['user'];
            $mail->Password = $settings['pass'];
        }

        if ($settings['secure'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($settings['secure'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            // No encryption (the local Mailpit inbox).
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $mail->setFrom($settings['from_address'], $settings['from_name']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $text;

        $mail->send();
        return true;
    } catch (MailerException $error) {
        // Never log passwords or the email body; ErrorInfo describes the SMTP problem.
        error_log('DiSSENT: ' . $purpose . ' email could not be sent: ' . $mail->ErrorInfo);
        return false;
    } catch (Throwable $error) {
        error_log('DiSSENT: ' . $purpose . ' email could not be sent: ' . get_class($error));
        return false;
    }
}


// Builds the HTML and plain-text versions of a DiSSENT email.
// $paragraphs are plain text (escaped here); the button links to $button_url.
function email_layout($heading, $paragraphs, $button_label, $button_url, $small_print)
{
    $navy = '#10244d';
    $button = '#354f91';
    $muted = '#4c5d75';

    $body = '';

    foreach ($paragraphs as $paragraph) {
        $body .= '<p style="margin:0 0 16px;font-size:16px;line-height:1.6;color:#1e293b;">'
            . e($paragraph) . '</p>';
    }

    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>' . e($heading) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f4f7fc;font-family:Arial,Helvetica,sans-serif;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fc;padding:24px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #d9e1ee;border-radius:12px;overflow:hidden;">'
        . '<tr><td style="background:' . $navy . ';padding:20px 28px;color:#ffffff;font-size:24px;font-weight:bold;letter-spacing:0.5px;">DiSSENT</td></tr>'
        . '<tr><td style="padding:28px;">'
        . '<h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:' . $navy . ';">' . e($heading) . '</h1>'
        . $body
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 20px;"><tr>'
        . '<td style="border-radius:8px;background:' . $button . ';">'
        . '<a href="' . e($button_url) . '" style="display:inline-block;padding:14px 26px;color:#ffffff;font-size:16px;font-weight:bold;text-decoration:none;border-radius:8px;">'
        . e($button_label) . '</a></td></tr></table>'
        . '<p style="margin:0 0 8px;font-size:13px;line-height:1.6;color:' . $muted . ';">If the button does not work, copy this link into your browser:<br>'
        . '<a href="' . e($button_url) . '" style="color:' . $button . ';word-break:break-all;">' . e($button_url) . '</a></p>'
        . '<p style="margin:16px 0 0;font-size:13px;line-height:1.6;color:' . $muted . ';">' . e($small_print) . '</p>'
        . '</td></tr>'
        . '<tr><td style="background:#f4f7fc;padding:16px 28px;font-size:12px;color:' . $muted . ';">'
        . 'DiSSENT — cross-border journalism platform. This is an automatic message; please do not reply.'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';

    $text = "DiSSENT\n\n" . $heading . "\n\n"
        . implode("\n\n", $paragraphs) . "\n\n"
        . $button_label . ":\n" . $button_url . "\n\n"
        . $small_print . "\n\n"
        . "-- \nDiSSENT — cross-border journalism platform. This is an automatic message; please do not reply.\n";

    return array('html' => $html, 'text' => $text);
}


// "Welcome to DiSSENT" after a successful registration.
function send_welcome_email($email, $fullname)
{
    $name = trim((string) $fullname) !== '' ? trim((string) $fullname) : 'there';

    $content = email_layout(
        'Welcome to DiSSENT',
        array(
            'Hello ' . $name . ',',
            'Your registration was successful. Your DiSSENT account is ready, and you can now log in to submit reports, send secure messages and follow editorial review.',
        ),
        'Log in to DiSSENT',
        app_page_url('login.php'),
        'If you did not create this account, you can ignore this email.'
    );

    return send_app_email($email, 'Welcome to DiSSENT', $content['html'], $content['text'], 'welcome');
}


// "Reset your DiSSENT password" with a link that is valid for 60 minutes.
function send_password_reset_email($email, $reset_link)
{
    $content = email_layout(
        'Reset your DiSSENT password',
        array(
            'We received a request to reset the password for your DiSSENT account.',
            'Press the button below to choose a new password. The link is valid for 60 minutes and can be used once.',
        ),
        'Choose a new password',
        $reset_link,
        'If you did not ask to reset your password, you can ignore this email. Your password will not change.'
    );

    return send_app_email($email, 'Reset your DiSSENT password', $content['html'], $content['text'], 'password reset');
}
