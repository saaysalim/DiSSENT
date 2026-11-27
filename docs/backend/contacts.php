<?php
/*
 * DiSSENT contacts and message requests (26/11/2026).
 *
 * Members never see a list of everyone. Two people can message each other only after
 * one sent a message request (by the exact email address) and the other accepted it,
 * like message requests in WhatsApp or Signal. Table: contacts (one row per pair),
 * see database/migrations/2026-11-26_contacts.sql.
 *
 * "New message" always gives the same answer, whether or not the address has an account,
 * so it cannot be used to find out who uses DiSSENT. Declined and blocked requests are
 * not shown to the sender as such: the request simply stays unanswered for them.
 *
 * Later (media centre roles plan), members of the same media centre could be connected
 * automatically: add rows with source 'centre' and centre_id, or extend contacts_connected().
 */

require_once __DIR__ . '/messages.php';

const CONTACT_REQUEST_REPLY = 'Request sent. If this person has a DiSSENT account, they will see your request.';
const CONTACT_EMAIL_INVALID = 'Please type their full email address, e.g. name@example.com';
const CONTACT_REQUEST_LIMIT_REPLY = 'You have reached the limit for new message requests. Please try again later.';
const CONTACT_REQUESTS_PER_DAY = 20;
const CONTACT_REQUESTS_PER_ADDRESS_PER_WEEK = 5;
const CONTACT_FIRST_MESSAGE_MAX = 500;


// The contacts row of a pair (either direction), or null. $lock = 'FOR UPDATE' or 'FOR SHARE' inside a transaction.
function contacts_pair($a, $b, $lock = '')
{
    $rows = messages_rows(
        'SELECT id, requester_id, addressee_id, status FROM contacts
         WHERE user_low = LEAST(?, ?) AND user_high = GREATEST(?, ?) ' . (in_array($lock, array('FOR UPDATE', 'FOR SHARE'), true) ? $lock : ''),
        'iiii',
        array($a, $b, $a, $b)
    );

    return $rows[0] ?? null;
}


// True when the two people may message each other.
function contacts_connected($a, $b)
{
    $pair = contacts_pair((int) $a, (int) $b);

    return $pair !== null && $pair['status'] === 'accepted';
}


/*
 * The people this user is connected with, newest conversation first; people without
 * messages yet follow A-Z; deleted accounts (history only) last.
 * Never returns email addresses.
 */
function contacts_list($user_id)
{
    return messages_rows(
        "SELECT u.id, u.fullname, u.role, u.organisation, u.profile_picture, last.last_at
         FROM contacts c
         JOIN users u ON u.id = IF(c.requester_id = ?, c.addressee_id, c.requester_id)
         LEFT JOIN (
             SELECT IF(m.sender_id = ?, m.receiver_id, m.sender_id) AS other_id,
                    MAX(COALESCE(m.delivered_at, m.sent_at)) AS last_at
             FROM messages m
             WHERE m.sender_id = ?
                OR (m.receiver_id = ? AND m.status = 'delivered')
             GROUP BY other_id
         ) last ON last.other_id = u.id
         WHERE (c.requester_id = ? OR c.addressee_id = ?) AND c.status = 'accepted'
           AND (u.role <> 'deleted' OR last.last_at IS NOT NULL)
         ORDER BY (u.role = 'deleted') ASC, (last.last_at IS NULL) ASC, last.last_at DESC, u.fullname ASC",
        'iiiiii',
        array($user_id, $user_id, $user_id, $user_id, $user_id, $user_id)
    );
}


// Number of accepted contacts (the chat page notices when someone accepts a request).
function contacts_count($user_id)
{
    return (int) messages_rows(
        "SELECT COUNT(*) AS n FROM contacts WHERE (requester_id = ? OR addressee_id = ?) AND status = 'accepted'",
        'ii',
        array($user_id, $user_id)
    )[0]['n'];
}


// Requests waiting for this user, oldest first, with the first message if it was delivered.
function contacts_incoming_requests($user_id)
{
    return messages_rows(
        "SELECT c.id, u.fullname, u.organisation, c.created_at,
                m.status AS message_status, m.message
         FROM contacts c
         JOIN users u ON u.id = c.requester_id AND u.role <> 'deleted'
         LEFT JOIN messages m ON m.id = c.first_message_id
         WHERE c.addressee_id = ? AND c.status = 'pending'
         ORDER BY c.created_at ASC, c.id ASC",
        'i',
        array($user_id)
    );
}


/*
 * "New message": a request to the account with exactly this email address.
 * Returns array('ok' => bool, 'message' => text to show). The text is the same whether
 * or not the address has an account (only form errors and the limits differ, and those
 * are checked before the address is looked up).
 */
function contacts_send_request($requester_id, $email, $text)
{
    $email = trim((string) $email);
    $text = trim((string) $text);

    if ($email === '' || strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return array('ok' => false, 'message' => CONTACT_EMAIL_INVALID, 'field' => 'email');
    }
    if (preg_match('//u', $text) !== 1) {
        return array('ok' => false, 'message' => 'The message contains characters that cannot be sent.');
    }
    if (mb_strlen($text, 'UTF-8') > CONTACT_FIRST_MESSAGE_MAX) {
        return array('ok' => false, 'message' => 'Please keep the first message within 500 characters.');
    }

    $hash = hash('sha256', strtolower($email));

    // Limits first, before the address is looked up, so they reveal nothing either.
    $counts = messages_rows(
        'SELECT SUM(created_at >= NOW() - INTERVAL 1 DAY) AS today,
                SUM(email_hash = ? AND created_at >= NOW() - INTERVAL 7 DAY) AS this_address
         FROM contact_request_log WHERE requester_id = ?',
        'si',
        array($hash, $requester_id)
    )[0];

    if ((int) $counts['today'] >= CONTACT_REQUESTS_PER_DAY || (int) $counts['this_address'] >= CONTACT_REQUESTS_PER_ADDRESS_PER_WEEK) {
        return array('ok' => false, 'message' => CONTACT_REQUEST_LIMIT_REPLY);
    }

    mysqli_stmt_close(messages_query('INSERT INTO contact_request_log (requester_id, email_hash) VALUES (?, ?)', 'is', array($requester_id, $hash)));
    mysqli_stmt_close(messages_query('DELETE FROM contact_request_log WHERE created_at < NOW() - INTERVAL 8 DAY'));

    $neutral = array('ok' => true, 'message' => CONTACT_REQUEST_REPLY);
    $target = messages_rows("SELECT id FROM users WHERE email = ? AND role <> 'deleted' LIMIT 1", 's', array($email));
    $target_id = (int) ($target[0]['id'] ?? 0);

    if ($target_id === 0 || $target_id === $requester_id) {
        return $neutral;
    }

    try {
        $action = messages_transaction(function () use ($requester_id, $target_id) {
            $pair = contacts_pair($requester_id, $target_id, 'FOR UPDATE');

            if ($pair === null) {
                mysqli_stmt_close(messages_query(
                    "INSERT INTO contacts (requester_id, addressee_id, status, source) VALUES (?, ?, 'pending', 'request')",
                    'ii',
                    array($requester_id, $target_id)
                ));
                return 'request';
            }

            $i_asked = (int) $pair['requester_id'] === $requester_id;

            switch ($pair['status']) {
                case 'accepted':
                    return 'message';
                case 'pending':
                    if ($i_asked) {
                        return 'none'; // already asked; no second request or message
                    }
                    // They asked me first: wanting to talk to each other means yes.
                    mysqli_stmt_close(messages_query(
                        "UPDATE contacts SET status = 'accepted', decided_at = CURRENT_TIMESTAMP WHERE id = ?",
                        'i',
                        array($pair['id'])
                    ));
                    return 'message';
                default: // declined or blocked
                    if ($i_asked) {
                        return 'none'; // stays unanswered for the requester
                    }
                    // The person who declined or blocked now asks: a fresh request from them.
                    mysqli_stmt_close(messages_query(
                        "UPDATE contacts SET requester_id = ?, addressee_id = ?, status = 'pending', source = 'request',
                                first_message_id = NULL, created_at = CURRENT_TIMESTAMP, decided_at = NULL
                         WHERE id = ?",
                        'iii',
                        array($requester_id, $target_id, $pair['id'])
                    ));
                    return 'request';
            }
        });
    } catch (Throwable $error) {
        // For example both people asked at the same moment. The answer stays the same.
        error_log('DiSSENT: message request not saved: ' . get_class($error));
        return $neutral;
    }

    if ($text !== '' && ($action === 'request' || $action === 'message')) {
        try {
            // Same compliance check as every message: sensitive text waits for an administrator.
            $saved = messages_send($requester_id, $target_id, $text, null, $action === 'request');

            if ($action === 'request') {
                mysqli_stmt_close(messages_query(
                    'UPDATE contacts SET first_message_id = ? WHERE user_low = LEAST(?, ?) AND user_high = GREATEST(?, ?)',
                    'iiiii',
                    array($saved['id'], $requester_id, $target_id, $requester_id, $target_id)
                ));
            }
        } catch (Throwable $error) {
            // Never log message text. The answer stays the same.
            error_log('DiSSENT: first message of a request failed: ' . get_class($error));
        }
    }

    return $neutral;
}


/*
 * Accept, decline or block a request waiting for $user_id.
 * Returns array('error' => '' or text, 'other_id' => requester id).
 */
function contacts_respond($user_id, $contact_id, $decision)
{
    $status = array('accept' => 'accepted', 'decline' => 'declined', 'block' => 'blocked')[$decision] ?? null;

    if ($status === null) {
        return array('error' => 'Please choose Accept, Decline or Block.', 'other_id' => 0);
    }

    return messages_transaction(function () use ($user_id, $contact_id, $status) {
        $rows = messages_rows(
            "SELECT id, requester_id FROM contacts WHERE id = ? AND addressee_id = ? AND status = 'pending' FOR UPDATE",
            'ii',
            array($contact_id, $user_id)
        );

        if ($rows === array()) {
            return array('error' => 'This request is no longer waiting for an answer.', 'other_id' => 0);
        }

        mysqli_stmt_close(messages_query(
            'UPDATE contacts SET status = ?, decided_at = CURRENT_TIMESTAMP WHERE id = ?',
            'si',
            array($status, $contact_id)
        ));

        return array('error' => '', 'other_id' => (int) $rows[0]['requester_id']);
    });
}


/*
 * Part of account_delete(), inside its transaction: removes waiting, declined and blocked
 * requests and the request log. Accepted rows stay, so the other person can still read
 * the conversation (the name becomes "Deleted user").
 */
function contacts_prepare_account_deletion($user_id)
{
    mysqli_stmt_close(messages_query(
        "DELETE FROM contacts WHERE (requester_id = ? OR addressee_id = ?) AND status <> 'accepted'",
        'ii',
        array($user_id, $user_id)
    ));
    mysqli_stmt_close(messages_query('DELETE FROM contact_request_log WHERE requester_id = ?', 'i', array($user_id)));
}
