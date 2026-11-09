<?php

/*
 * Messaging bridge:
 * - System1 accepts messages for encryption, signing and publishing.
 * - System2 reports verification and decryption status.
 *
 * These functions do not save messages in MySQL.
 * Database storage is handled separately by the sending endpoint.
 */

function mc_request($base_url, $api_key, $method, $path, $body = null)
{
    $failure = array(
        'ok' => false,
        'data' => null
    );

    if (!function_exists('curl_init')) {
        error_log('DiSSENT: messaging client requires PHP cURL.');
        return $failure;
    }

    if (
        !is_string($base_url) ||
        !is_string($api_key) ||
        !is_string($method) ||
        !is_string($path)
    ) {
        return $failure;
    }

    $base_url = rtrim(trim($base_url), '/');
    $method = strtoupper(trim($method));

    if (
        $base_url === '' ||
        trim($api_key) === '' ||
        preg_match('/[\r\n]/', $api_key) ||
        !in_array($method, array('GET', 'POST'), true) ||
        $path === '' ||
        $path[0] !== '/'
    ) {
        return $failure;
    }

    $url = $base_url . $path;
    $url_parts = parse_url($url);

    if (
        $url_parts === false ||
        empty($url_parts['host']) ||
        !isset($url_parts['scheme']) ||
        !in_array(
            strtolower($url_parts['scheme']),
            array('http', 'https'),
            true
        ) ||
        isset($url_parts['user']) ||
        isset($url_parts['pass'])
    ) {
        return $failure;
    }

    $headers = array(
        'Accept: application/json',
        'X-API-Key: ' . $api_key
    );

    $encoded_body = null;

    if ($body !== null) {
        $encoded_body = json_encode($body);

        if ($encoded_body === false) {
            error_log('DiSSENT: messaging request JSON encoding failed.');
            return $failure;
        }

        $headers[] = 'Content-Type: application/json';
    }

    $ch = null;

    try {
        $ch = curl_init($url);

        if ($ch === false) {
            return $failure;
        }

        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,

            // Do not forward the API key to a redirected destination.
            CURLOPT_FOLLOWLOCATION => false,

            // Validate certificates when HTTPS is configured.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        );

        if ($encoded_body !== null) {
            $options[CURLOPT_POSTFIELDS] = $encoded_body;
        }

        if (!curl_setopt_array($ch, $options)) {
            error_log('DiSSENT: messaging request configuration failed.');
            return $failure;
        }

        $response = curl_exec($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            // Log only the error number, not message content or credentials.
            error_log(
                'DiSSENT: messaging service connection failed; cURL code ' .
                curl_errno($ch) . '.'
            );

            return $failure;
        }

        if ($http_code < 200 || $http_code >= 300) {
            error_log(
                'DiSSENT: messaging service returned HTTP ' .
                $http_code . '.'
            );

            return $failure;
        }

        $decoded = json_decode($response, true);

        if (
            json_last_error() !== JSON_ERROR_NONE ||
            !is_array($decoded)
        ) {
            error_log('DiSSENT: messaging service returned invalid JSON.');
            return $failure;
        }

        return array(
            'ok' => true,
            'data' => $decoded
        );
    } catch (Throwable $exception) {
        error_log('DiSSENT: messaging client request failed.');
        return $failure;
    } finally {
        if ($ch !== null && $ch !== false) {
            curl_close($ch);
        }
    }
}

/*
 * Returns the envelope ID when System1 acknowledges the request.
 * This acknowledgement does not prove recipient verification.
 *
 * Returns null when no valid acknowledgement is received.
 * A timeout does not prove that System1 rejected the message,
 * so this function does not automatically retry POST requests.
 */
function mc_send_secure($sender_id, $receiver_id, $message)
{
    if (
        !defined('BLOCKCHAIN_API_URL') ||
        !defined('BLOCKCHAIN_API_KEY')
    ) {
        error_log('DiSSENT: messaging sender configuration is missing.');
        return null;
    }

    if (
        (!is_int($sender_id) && !is_string($sender_id)) ||
        (!is_int($receiver_id) && !is_string($receiver_id)) ||
        !is_string($message) ||
        trim($message) === ''
    ) {
        return null;
    }

    $sender_id = filter_var(
        $sender_id,
        FILTER_VALIDATE_INT,
        array('options' => array('min_range' => 1))
    );

    $receiver_id = filter_var(
        $receiver_id,
        FILTER_VALIDATE_INT,
        array('options' => array('min_range' => 1))
    );

    if ($sender_id === false || $receiver_id === false) {
        return null;
    }

    $result = mc_request(
        BLOCKCHAIN_API_URL,
        BLOCKCHAIN_API_KEY,
        'POST',
        '/api/messages/send',
        array(
            'senderId' => (string) $sender_id,
            'receiverId' => (string) $receiver_id,
            'plaintext' => $message
        )
    );

    if (!$result['ok']) {
        return null;
    }

    $envelope_id = $result['data']['envelopeId'] ?? null;

    if (!is_string($envelope_id)) {
        return null;
    }

    $envelope_id = trim($envelope_id);

    if (
        $envelope_id === '' ||
        strlen($envelope_id) > 64 ||
        preg_match('/[\x00-\x1F\x7F]/', $envelope_id)
    ) {
        return null;
    }

    return $envelope_id;
}

/*
 * Returns one of:
 * pending, verified, failed, unknown.
 *
 * An unavailable service means "unknown", not "failed".
 * Only the status is returned; decrypted text and internal
 * service errors are not passed back to callers.
 *
 * The calling endpoint must check message ownership first.
 */
function mc_get_status($envelope_id)
{
    $unknown = array('status' => 'unknown');

    if (
        !defined('MESSAGING_API_URL') ||
        !defined('MESSAGING_API_KEY')
    ) {
        error_log('DiSSENT: messaging status configuration is missing.');
        return $unknown;
    }

    if (!is_string($envelope_id)) {
        return $unknown;
    }

    $envelope_id = trim($envelope_id);

    if (
        $envelope_id === '' ||
        strlen($envelope_id) > 64 ||
        preg_match('/[\x00-\x1F\x7F]/', $envelope_id)
    ) {
        return $unknown;
    }

    $result = mc_request(
        MESSAGING_API_URL,
        MESSAGING_API_KEY,
        'GET',
        '/api/messages/' . rawurlencode($envelope_id)
    );

    if (!$result['ok']) {
        return $unknown;
    }

    $status = $result['data']['status'] ?? 'unknown';

    $allowed_statuses = array(
        'pending',
        'verified',
        'failed',
        'unknown'
    );

    if (
        !is_string($status) ||
        !in_array($status, $allowed_statuses, true)
    ) {
        return $unknown;
    }

    return array('status' => $status);
}