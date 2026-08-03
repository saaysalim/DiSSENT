<?php

// Thin HTTP client for securecomm's report-verification REST API. Every call can fail
// (remote host down, network hiccup) and must degrade gracefully rather than fatal —
// these will eventually be real network calls from free PHP hosting to a separate server.

function bc_request($method, $path, $body = null){
    $url = BLOCKCHAIN_API_URL . $path;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = array('X-API-Key: ' . BLOCKCHAIN_API_KEY);

    if($body !== null){
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if($response === false){
        return array('ok' => false, 'error' => $error ?: 'Verification service unavailable.');
    }

    $decoded = json_decode($response, true);

    if($httpCode >= 200 && $httpCode < 300){
        return array('ok' => true, 'data' => $decoded);
    }

    $message = is_array($decoded) && isset($decoded['error']) ? $decoded['error'] : 'Verification service error.';
    return array('ok' => false, 'error' => $message);
}

// Records a new verification block for a report. Returns the block hash, or null on failure.
function bc_add_verification_block($report_id, $title, $description){
    $result = bc_request('POST', '/api/verification', array(
        'reportId' => (string)$report_id,
        'title' => $title,
        'description' => $description
    ));

    if(!$result['ok']){
        return null;
    }

    return $result['data']['blockHash'];
}

// Fetches the ledger's verification record for a single report, or null if not found/unreachable.
function bc_get_verification($report_id){
    $result = bc_request('GET', '/api/verification/' . urlencode((string)$report_id));
    return $result['ok'] ? $result['data'] : null;
}

// Fetches recent verification history (most recent first), or an empty array if unreachable.
function bc_get_verification_history($limit = 10){
    $result = bc_request('GET', '/api/verification/history?limit=' . (int)$limit);
    return $result['ok'] ? $result['data'] : array();
}

// Returns true/false from the ledger's chain-integrity check, or null if the service is unreachable
// (callers should treat null as "unknown", not as "broken").
function bc_verify_chain_integrity(){
    $result = bc_request('GET', '/api/verification/integrity');
    return $result['ok'] ? $result['data']['chainIntact'] : null;
}
