<?php
/*
 * DiSSENT compliance check: runs before a message is delivered.
 *
 * compliance_check() decides ALLOW or REVIEW:
 *   AI_ENABLED=0 (default): the rules below (compliance_check_rules()).
 *   AI_ENABLED=1: the AI service (RAG + ReAct, ai/README.md) at AI_SERVICE_URL. If it is down,
 *   slower than AI_TIMEOUT_SECONDS, or answers with invalid data, the message is held
 *   (REVIEW, "check_failed"), exactly as when the rules break.
 * The rest of the code calls compliance_run(), which also holds the message if the check breaks.
 *
 * Simple rules give false alarms and can be avoided on purpose. They support a
 * human reviewer; they do not prove that a message is safe.
 */

define('COMPLIANCE_CHECKER', 'rules-v1');


// Reason codes (stored in the database) and the text people see.
function compliance_reason_labels()
{
    return array(
        'attachment' => 'It has an attachment',
        'phone' => 'It may contain a phone number',
        'email' => 'It may contain an email address',
        'id_number' => 'It may contain an ID or passport number',
        'iban' => 'It may contain a bank account number (IBAN)',
        'check_failed' => 'The automatic check could not run',
        'ai_review' => 'The AI check suggested a review',
    );
}


// Name stored with each review log entry (message_reviews.checker).
function compliance_checker_name()
{
    return compliance_ai_enabled() ? 'ai-rag-react-v1' : COMPLIANCE_CHECKER;
}


function compliance_ai_enabled()
{
    return getenv('AI_ENABLED') === '1';
}


/*
 * $message_text  string
 * $attachment    null, or array('mime' => ..., 'original_name' => ..., 'path' => ...)
 * $sender, $receiver  arrays with 'id', 'role' and 'organisation'
 *                     (not used by the rules; the AI check will need them)
 *
 * Returns array('decision' => 'ALLOW' or 'REVIEW', 'reasons' => array of codes), and with
 * the AI check also 'ai' => the AI's result (stored with a held message for the admin).
 */
function compliance_check($message_text, $attachment, $sender, $receiver)
{
    if (compliance_ai_enabled()) {
        return compliance_check_ai($message_text, $attachment, $sender, $receiver);
    }

    return compliance_check_rules($message_text, $attachment, $sender, $receiver);
}


// The rule-based check (the baseline in ai/eval/results_baseline.md).
function compliance_check_rules($message_text, $attachment, $sender, $receiver)
{
    $text = compliance_normalise($message_text);
    $reasons = array();

    if ($attachment !== null) {
        $reasons[] = 'attachment';
    }
    if (compliance_has_email($text)) {
        $reasons[] = 'email';
    }
    if (compliance_has_iban($text)) {
        $reasons[] = 'iban';
    }
    if (compliance_has_phone($text)) {
        $reasons[] = 'phone';
    }
    if (compliance_has_id_number($text)) {
        $reasons[] = 'id_number';
    }

    return array(
        'decision' => $reasons === array() ? 'ALLOW' : 'REVIEW',
        'reasons' => $reasons,
    );
}


// Safe wrapper used by send_message.php: any problem means REVIEW, never ALLOW.
function compliance_run($message_text, $attachment, $sender, $receiver)
{
    try {
        $result = compliance_check($message_text, $attachment, $sender, $receiver);
        $ai = is_array($result['ai'] ?? null) ? array('ai' => $result['ai']) : array();

        if (is_array($result) && is_array($result['reasons'] ?? null)) {
            if (($result['decision'] ?? '') === 'ALLOW') {
                return array('decision' => 'ALLOW', 'reasons' => array()) + $ai;
            }
            if (($result['decision'] ?? '') === 'REVIEW' && $result['reasons'] !== array()) {
                return array('decision' => 'REVIEW', 'reasons' => array_values($result['reasons'])) + $ai;
            }
        }

        error_log('DiSSENT: compliance check returned an invalid result.');
    } catch (Throwable $error) {
        // Never log the message text.
        error_log('DiSSENT: compliance check failed: ' . get_class($error));
    }

    return array('decision' => 'REVIEW', 'reasons' => array('check_failed'));
}


/* ---------- AI check (RAG + ReAct service, ai/README.md) ---------- */

/*
 * Asks the AI service. The message text goes only to AI_SERVICE_URL (inside the DiSSENT
 * Docker network by default). Countries are sent when the accounts have one (not stored yet).
 * On any problem the message is held: decision REVIEW, reason "check_failed", and the 'ai'
 * part says what went wrong, so the admin sees it next to the held message.
 */
function compliance_check_ai($message_text, $attachment, $sender, $receiver)
{
    $started = microtime(true);
    $url = rtrim((string) (getenv('AI_SERVICE_URL') ?: 'http://ai:8000'), '/') . '/check';
    $timeout = max(1, min(30, (int) (getenv('AI_TIMEOUT_SECONDS') ?: 5)));
    $connect_timeout = min(2, $timeout);
    $country = static function ($person) {
        $code = strtoupper(trim((string) ($person['country'] ?? '')));
        return preg_match('/\A[A-Z]{2}\z/', $code) === 1 ? $code : '';
    };

    $body = json_encode(array(
        'text' => (string) $message_text,
        'sender_country' => $country($sender),
        'receiver_country' => $country($receiver),
        'has_attachment' => $attachment !== null,
    ), JSON_INVALID_UTF8_SUBSTITUTE);

    $problem = '';
    $data = null;

    if (!function_exists('curl_init')) {
        $problem = 'cURL is not installed';
    } else {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Accept: application/json'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $connect_timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ));
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $connected = (float) curl_getinfo($ch, CURLINFO_CONNECT_TIME) > 0;
        curl_close($ch);

        if ($errno === CURLE_OPERATION_TIMEDOUT && !$connected) {
            $problem = 'the service could not be reached within ' . $connect_timeout . ' seconds';
        } elseif ($errno === CURLE_OPERATION_TIMEDOUT) {
            $problem = 'no answer within ' . $timeout . ' seconds';
        } elseif ($errno !== 0 || $raw === false) {
            $problem = 'the service could not be reached';
        } elseif ($status !== 200) {
            $problem = 'the service answered with HTTP ' . $status;
        } else {
            $data = compliance_ai_validate(json_decode((string) $raw, true));
            if ($data === null) {
                $problem = 'the answer was not valid';
            }
        }
    }

    if ($problem !== '') {
        // Never log the message text.
        error_log('DiSSENT: AI compliance check failed (' . $problem . '); the message is held.');

        return array(
            'decision' => 'REVIEW',
            'reasons' => array('check_failed'),
            'ai' => array(
                'status' => 'failed',
                'decision' => 'REVIEW',
                'risk_level' => 'unknown',
                'reasons' => array('The AI check did not work (' . $problem . '), so the message was held for safety.'),
                'citations' => array(),
                'steps' => array(),
                'model_name' => 'unavailable',
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            ),
        );
    }

    $data['status'] = 'ok';

    return array(
        'decision' => $data['decision'],
        'reasons' => $data['decision'] === 'REVIEW' ? array('ai_review') : array(),
        'ai' => $data,
    );
}


// Checks the shape of the AI answer. Returns the cleaned answer, or null if anything is wrong.
function compliance_ai_validate($data)
{
    $string = static function ($value, $max) {
        return is_string($value) && $value !== '' && mb_strlen($value, 'UTF-8') <= $max;
    };

    if (!is_array($data) || !in_array($data['decision'] ?? null, array('ALLOW', 'REVIEW'), true)) {
        return null;
    }
    if (!$string($data['risk_level'] ?? null, 20) || !$string($data['model_name'] ?? null, 60)) {
        return null;
    }
    if (!is_array($data['reasons'] ?? null) || count($data['reasons']) > 20
        || !is_array($data['citations'] ?? null) || count($data['citations']) > 30
        || !is_array($data['steps'] ?? null) || count($data['steps']) > 20
        || !is_numeric($data['latency_ms'] ?? null)) {
        return null;
    }
    foreach ($data['reasons'] as $reason) {
        if (!$string($reason, 600)) {
            return null;
        }
    }
    if ($data['decision'] === 'REVIEW' && $data['reasons'] === array()) {
        return null;
    }

    $citations = array();
    foreach ($data['citations'] as $c) {
        if (!is_array($c) || !$string($c['source'] ?? null, 200) || !$string($c['passage_id'] ?? null, 60)
            || !$string($c['quote'] ?? null, 600) || preg_match('/\A[A-Z]{2}\z/', (string) ($c['country'] ?? '')) !== 1) {
            return null;
        }
        $citations[] = array('source' => $c['source'], 'country' => $c['country'], 'passage_id' => $c['passage_id'], 'quote' => $c['quote']);
    }

    $steps = array();
    foreach ($data['steps'] as $s) {
        if (!is_array($s) || !$string($s['thought'] ?? null, 1000) || !$string($s['action'] ?? null, 1000)
            || !is_string($s['observation'] ?? null) || mb_strlen($s['observation'], 'UTF-8') > 2000) {
            return null;
        }
        $steps[] = array('thought' => $s['thought'], 'action' => $s['action'], 'observation' => $s['observation']);
    }

    return array(
        'decision' => $data['decision'],
        'risk_level' => $data['risk_level'],
        'reasons' => array_values($data['reasons']),
        'citations' => $citations,
        'steps' => $steps,
        'model_name' => $data['model_name'],
        'latency_ms' => (int) $data['latency_ms'],
    );
}


/* ---------- Rules ---------- */

// preg_match that fails loudly, so a broken pattern or bad text holds the message.
function compliance_match($pattern, $text)
{
    $found = preg_match($pattern, $text);

    if ($found === false) {
        throw new RuntimeException('Compliance pattern failed.');
    }

    return $found === 1;
}


function compliance_match_all($pattern, $text)
{
    if (preg_match_all($pattern, $text, $found) === false) {
        throw new RuntimeException('Compliance pattern failed.');
    }

    return $found[0];
}


// Same digits and symbols in every script; invisible characters removed.
function compliance_normalise($text)
{
    $text = (string) $text;

    if (preg_match('//u', $text) !== 1) {
        throw new RuntimeException('Message text is not valid UTF-8.');
    }

    $map = array('＠' => '@', '．' => '.', '＋' => '+', '－' => '-', '–' => '-', '—' => '-', '‐' => '-', '‑' => '-', '−' => '-', '٫' => '.');

    // Arabic, Persian, Devanagari, Bengali, full-width and bold/mono digits -> 0-9
    foreach (array(0x0660, 0x06F0, 0x0966, 0x09E6, 0xFF10, 0x1D7CE, 0x1D7D8, 0x1D7E2, 0x1D7EC, 0x1D7F6) as $zero) {
        for ($i = 0; $i < 10; $i++) {
            $map[mb_chr($zero + $i, 'UTF-8')] = (string) $i;
        }
    }

    $text = strtr($text, $map);
    $text = preg_replace('/\p{Cf}+/u', '', $text);
    $text = $text === null ? null : preg_replace('/[\p{Z}\t]+/u', ' ', $text);

    if ($text === null) {
        throw new RuntimeException('Message text could not be prepared.');
    }

    return $text;
}


function compliance_has_email($text)
{
    return compliance_match('/[\p{L}\p{N}._%+\-]+\s?(?:@|\(at\)|\[at\])\s?[\p{L}\p{N}\-]+(?:\s?(?:\.|\(dot\)|\[dot\])\s?[\p{L}\p{N}\-]+)+/iu', $text);
}


function compliance_has_phone($text)
{
    foreach (compliance_match_all('/(?<![\p{L}\p{N}])(?:\+\s?)?\(?\d(?:[ ().\/\-]{0,3}\d){6,19}(?![\p{L}\p{N}])/u', $text) as $candidate) {
        $candidate = trim($candidate);
        $digits = strlen(preg_replace('/\D/', '', $candidate));

        // Dates (25/11/2026, 2026-11-25, also with a time) and year ranges are not phone numbers.
        if (
            preg_match('#^\d{1,2}([./-])\d{1,2}\1\d{4}( \d{1,2})?$#', $candidate) ||
            preg_match('#^(19|20)\d{2}([./-])\d{1,2}\2\d{1,2}( \d{1,2})?$#', $candidate) ||
            preg_match('#^(19|20)\d{2} ?[-/ ] ?(19|20)\d{2}$#', $candidate)
        ) {
            continue;
        }

        if ($digits >= ($candidate[0] === '+' ? 7 : 8) && $digits <= 15) {
            return true;
        }
    }

    return false;
}


function compliance_has_iban($text)
{
    // Country => exact IBAN length.
    $lengths = array('AD' => 24, 'AE' => 23, 'AL' => 28, 'AT' => 20, 'AZ' => 28, 'BA' => 20, 'BE' => 16, 'BG' => 22, 'BH' => 22, 'BR' => 29, 'BY' => 28, 'CH' => 21, 'CR' => 22, 'CY' => 28, 'CZ' => 24, 'DE' => 22, 'DK' => 18, 'DO' => 28, 'EE' => 20, 'EG' => 29, 'ES' => 24, 'FI' => 18, 'FO' => 18, 'FR' => 27, 'GB' => 22, 'GE' => 22, 'GI' => 23, 'GL' => 18, 'GR' => 27, 'GT' => 28, 'HR' => 21, 'HU' => 28, 'IE' => 22, 'IL' => 23, 'IQ' => 23, 'IS' => 26, 'IT' => 27, 'JO' => 30, 'KW' => 30, 'KZ' => 20, 'LB' => 28, 'LI' => 21, 'LT' => 20, 'LU' => 20, 'LV' => 21, 'LY' => 25, 'MC' => 27, 'MD' => 24, 'ME' => 22, 'MK' => 19, 'MR' => 27, 'MT' => 31, 'MU' => 30, 'NL' => 18, 'NO' => 15, 'PK' => 24, 'PL' => 28, 'PS' => 29, 'PT' => 25, 'QA' => 29, 'RO' => 24, 'RS' => 22, 'SA' => 24, 'SC' => 31, 'SD' => 18, 'SE' => 24, 'SI' => 19, 'SK' => 24, 'SM' => 27, 'ST' => 25, 'SV' => 28, 'TL' => 23, 'TN' => 24, 'TR' => 26, 'UA' => 29, 'VA' => 22, 'VG' => 24, 'XK' => 20);

    foreach (compliance_match_all('/(?<![A-Z0-9])[A-Z]{2}\d{2}(?:[ \-]?[A-Z0-9]){11,40}/i', $text) as $candidate) {
        $compact = strtoupper(preg_replace('/[ \-]/', '', $candidate));
        $length = $lengths[substr($compact, 0, 2)] ?? 0;

        if ($length === 0 || strlen($compact) < $length) {
            continue;
        }

        $iban = substr($compact, 0, $length);
        $remainder = 0;

        foreach (str_split(substr($iban, 4) . substr($iban, 0, 4)) as $char) {
            foreach (str_split(ctype_alpha($char) ? (string) (ord($char) - 55) : $char) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }

        if ($remainder === 1) {
            return true;
        }
    }

    return false;
}


function compliance_has_id_number($text)
{
    // Passport style (1-3 letters + 6-9 digits), Irish PPS (7 digits + 1-2 letters), long digit runs.
    if (compliance_match('/(?<![A-Z0-9])(?:[A-Z]{1,3}[ \-]?\d{6,9}|\d{7}[ \-]?[A-W][A-IW]?|\d{9,20}|\d{4}(?:[ \-]\d{4}){2,4})(?![A-Z0-9])/i', $text)) {
        return true;
    }

    // An ID word together with any number of 5 or more digits.
    return compliance_match('/passport|national id|id card|identity card|driving licen[cs]e|visa number|گذرنامه|پاسپورت|کارت ملی|کد ملی|تذکره|جواز سفر|بطاقة هوية/iu', $text)
        && compliance_match('/\d(?:[ \-]?\d){4,}/', $text);
}
