<?php
/*
 * DiSSENT compliance check: runs before a message is delivered.
 *
 * compliance_check() decides ALLOW or REVIEW. It is rule-based for now and is
 * a placeholder for the planned RAG + LLM (ReAct) check. To replace it, change
 * only compliance_check() and COMPLIANCE_CHECKER; the rest of the code calls
 * compliance_run(), which also holds the message if the check breaks.
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
    );
}


/*
 * $message_text  string
 * $attachment    null, or array('mime' => ..., 'original_name' => ..., 'path' => ...)
 * $sender, $receiver  arrays with 'id', 'role' and 'organisation'
 *                     (not used by the rules; the AI check will need them)
 *
 * Returns array('decision' => 'ALLOW' or 'REVIEW', 'reasons' => array of codes).
 */
function compliance_check($message_text, $attachment, $sender, $receiver)
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

        if (is_array($result) && is_array($result['reasons'] ?? null)) {
            if (($result['decision'] ?? '') === 'ALLOW') {
                return array('decision' => 'ALLOW', 'reasons' => array());
            }
            if (($result['decision'] ?? '') === 'REVIEW' && $result['reasons'] !== array()) {
                return array('decision' => 'REVIEW', 'reasons' => array_values($result['reasons']));
            }
        }

        error_log('DiSSENT: compliance check returned an invalid result.');
    } catch (Throwable $error) {
        // Never log the message text.
        error_log('DiSSENT: compliance check failed: ' . get_class($error));
    }

    return array('decision' => 'REVIEW', 'reasons' => array('check_failed'));
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
