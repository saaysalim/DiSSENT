<?php

// Thin HTTP clients for the secure-messaging bridge: send goes through securecomm
// (System1, encrypt+sign+publish), status is polled from securecomm2 (System2,
// verify+decrypt). Same fail-soft approach as blockchain_client.php.

function mc_request($base_url, $api_key, $method, $path, $body = null){
    $url = $base_url . $path;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = array('X-API-Key: ' . $api_key);

    if($body !== null){
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if($response === false || $httpCode < 200 || $httpCode >= 300){
        return array('ok' => false);
    }

    return array('ok' => true, 'data' => json_decode($response, true));
}

// Sends a message through the encrypt+sign+publish pipeline. Returns an envelope id
// to poll for status, or null if the secure-transport layer is unreachable — callers
// should still keep their own plaintext copy (e.g. in MySQL) so the feature degrades
// to "unverified but delivered" rather than failing outright.
function mc_send_secure($sender_id, $receiver_id, $message){
    $result = mc_request(BLOCKCHAIN_API_URL, BLOCKCHAIN_API_KEY, 'POST', '/api/messages/send', array(
        'senderId' => (string)$sender_id,
        'receiverId' => (string)$receiver_id,
        'plaintext' => $message
    ));

    return $result['ok'] ? $result['data']['envelopeId'] : null;
}

// Polls verification/decryption status for a previously sent envelope.
// Returns array('status' => 'pending'|'verified'|'failed'|'unknown', ...).
function mc_get_status($envelope_id){
    if(!$envelope_id){
        return array('status' => 'unknown');
    }

    $result = mc_request(MESSAGING_API_URL, MESSAGING_API_KEY, 'GET', '/api/messages/' . urlencode($envelope_id));
    return $result['ok'] ? $result['data'] : array('status' => 'unknown');
}
