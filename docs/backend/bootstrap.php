<?php
/*
 * Runs before every page (php.ini auto_prepend_file, set in docker/web/Dockerfile).
 * Security headers and session cookie settings for the whole site.
 * It must not output anything.
 */

// True when the visitor's connection is HTTPS, also behind the Cloudflare tunnel.
function request_is_https()
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));

    if ($https !== '' && $https !== 'off') {
        return true;
    }

    // Proxy headers are only trusted when the request comes from the host or Docker
    // (cloudflared runs on the laptop); the ports only listen on 127.0.0.1.
    if (!request_from_private_network()) {
        return false;
    }

    $proto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    $visitor = (string) ($_SERVER['HTTP_CF_VISITOR'] ?? '');

    return $proto === 'https' || strpos($visitor, '"https"') !== false;
}


function request_from_private_network()
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false
        && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}


// True only for a browser on this computer: a local Host name and no tunnel/proxy headers.
// (Through cloudflared the request also arrives from the laptop, so the IP alone is not enough.)
function request_is_local()
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host);

    if (!in_array($host, array('localhost', '127.0.0.1', '[::1]'), true)) {
        return false;
    }

    foreach (array('HTTP_CF_CONNECTING_IP', 'HTTP_CF_RAY', 'HTTP_CDN_LOOP', 'HTTP_CF_VISITOR', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_HOST', 'HTTP_X_FORWARDED_PROTO', 'HTTP_FORWARDED', 'HTTP_X_REAL_IP') as $header) {
        if (isset($_SERVER[$header])) {
            return false;
        }
    }

    return request_from_private_network();
}


if (PHP_SAPI !== 'cli' && !headers_sent()) {
    // One random value per page; inline <script> tags must carry it (see CSP_NONCE).
    define('CSP_NONCE', rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '='));

    $https = request_is_https();

    ini_set('session.cookie_secure', $https ? '1' : '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . CSP_NONCE . "'; "
        . "style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; "
        . "form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    if ($https) {
        header('Strict-Transport-Security: max-age=31536000');
    }
} elseif (!defined('CSP_NONCE')) {
    define('CSP_NONCE', '');
}
