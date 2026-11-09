<?php

// HTTP client for the Java report-verification service.
// Connection settings come from BLOCKCHAIN_API_URL and BLOCKCHAIN_API_KEY.

function bc_request($method, $path, $body = null)
{
    if (
        !defined('BLOCKCHAIN_API_URL') ||
        !defined('BLOCKCHAIN_API_KEY')
    ) {
        return array(
            'ok' => false,
            'error' => 'The verification service is not configured.'
        );
    }

    if (!function_exists('curl_init')) {
        return array(
            'ok' => false,
            'error' => 'The verification connection is unavailable.'
        );
    }

    $base_url = rtrim((string) BLOCKCHAIN_API_URL, '/');

    if ($base_url === '' || (string) BLOCKCHAIN_API_KEY === '') {
        return array(
            'ok' => false,
            'error' => 'The verification service is not configured.'
        );
    }

    $url = $base_url . '/' . ltrim($path, '/');

    $headers = array(
        'Accept: application/json',
        'X-API-Key: ' . BLOCKCHAIN_API_KEY
    );

    $encoded_body = null;

    if ($body !== null) {
        $encoded_body = json_encode($body);

        if ($encoded_body === false) {
            return array(
                'ok' => false,
                'error' => 'The verification request could not be prepared.'
            );
        }

        $headers[] = 'Content-Type: application/json';
    }

    $ch = null;

    try {
        $ch = curl_init($url);

        if ($ch === false) {
            return array(
                'ok' => false,
                'error' => 'The verification connection could not be started.'
            );
        }

        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,

            // Do not forward the API key to a redirected destination.
            CURLOPT_FOLLOWLOCATION => false,

            // Preserve certificate checks when HTTPS is configured.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        );

        if ($encoded_body !== null) {
            $options[CURLOPT_POSTFIELDS] = $encoded_body;
        }

        if (!curl_setopt_array($ch, $options)) {
            return array(
                'ok' => false,
                'error' => 'The verification request could not be prepared.'
            );
        }

        $response = curl_exec($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_errno = curl_errno($ch);

        if ($response === false) {
            error_log(
                'DiSSENT verification connection failed; cURL code '
                . $curl_errno
            );

            return array(
                'ok' => false,
                'error' => $curl_errno === CURLE_OPERATION_TIMEDOUT
                    ? 'The verification service did not respond in time.'
                    : 'The verification service is unavailable.'
            );
        }

        if ($http_code < 200 || $http_code >= 300) {
            error_log(
                'DiSSENT verification service returned HTTP '
                . $http_code
            );

            if ($http_code === 404) {
                $message = 'The verification record was not found.';
            } elseif ($http_code === 401 || $http_code === 403) {
                $message = 'The verification service refused access.';
            } elseif ($http_code === 409) {
                $message =
                    'The verification request conflicts with an existing record.';
            } else {
                $message = 'The verification service could not complete the request.';
            }

            return array(
                'ok' => false,
                'error' => $message,
                'http_code' => $http_code
            );
        }

        $decoded = json_decode($response, true);

        if (
            json_last_error() !== JSON_ERROR_NONE ||
            !is_array($decoded)
        ) {
            error_log('DiSSENT verification service returned invalid JSON data.');

            return array(
                'ok' => false,
                'error' => 'The verification service returned an unexpected response.',
                'http_code' => $http_code
            );
        }

        return array(
            'ok' => true,
            'data' => $decoded,
            'http_code' => $http_code
        );
    } catch (Throwable $exception) {
        error_log('DiSSENT verification request could not be completed.');

        return array(
            'ok' => false,
            'error' => 'The verification service is unavailable.'
        );
    } finally {
        if ($ch !== null && $ch !== false) {
            curl_close($ch);
        }
    }
}


// Requests a verification record.
// Returns the hash reported by the Java service, or null.
// A timeout does not establish whether the remote operation completed.
function bc_add_verification_block($report_id, $title, $description)
{
    $result = bc_request(
        'POST',
        '/api/verification',
        array(
            'reportId' => (string) $report_id,
            'title' => $title,
            'description' => $description
        )
    );

    if (!$result['ok']) {
        return null;
    }

    $block_hash = $result['data']['blockHash'] ?? null;

    if (!is_string($block_hash) || trim($block_hash) === '') {
        error_log('DiSSENT verification response did not contain a block hash.');
        return null;
    }

    return trim($block_hash);
}


// Returns a verification record, or null when it cannot be retrieved.
function bc_get_verification($report_id)
{
    $result = bc_request(
        'GET',
        '/api/verification/' . rawurlencode((string) $report_id)
    );

    return $result['ok'] ? $result['data'] : null;
}


// Returns the history supplied by the API.
// An empty array can mean no records OR an unavailable service;
// callers must not treat it as proof that the ledger is empty.
function bc_get_verification_history($limit = 10)
{
    $limit = max(1, min(100, (int) $limit));

    $result = bc_request(
        'GET',
        '/api/verification/history?limit=' . $limit
    );

    return $result['ok'] ? $result['data'] : array();
}


// Returns:
// true  = the service reports the chain is intact
// false = the service reports an integrity failure
// null  = the result is unavailable or invalid
function bc_verify_chain_integrity()
{
    $result = bc_request('GET', '/api/verification/integrity');

    if (!$result['ok']) {
        return null;
    }

    $chain_intact = $result['data']['chainIntact'] ?? null;

    if (!is_bool($chain_intact)) {
        error_log(
            'DiSSENT integrity response did not contain a boolean chainIntact.'
        );

        return null;
    }

    return $chain_intact;
}