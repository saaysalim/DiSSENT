<?php
/*
 * DiSSENT messages: who may see a message, sending, admin review and the chat bubble.
 * Needs db.php and content.php to be loaded first.
 *
 * Status of a message:
 *   delivered       the receiver can see it
 *   pending_review  held by the compliance check; only the sender (and a reviewing admin) sees it
 *   rejected        an admin refused it; the receiver never sees it
 *   withdrawn       cancelled because one of the two accounts was deleted
 */

require_once __DIR__ . '/compliance.php';
require_once __DIR__ . '/message_files.php';


// Runs a prepared statement and throws if anything fails. Returns the statement.
function messages_query($sql, $types = '', $values = array())
{
    global $conn;

    if (!db_ready()) {
        throw new RuntimeException('Database connection unavailable.');
    }

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new RuntimeException('Message query could not be prepared.');
    }
    if ($types !== '' && !mysqli_stmt_bind_param($stmt, $types, ...$values)) {
        throw new RuntimeException('Message query could not be bound.');
    }
    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Message query failed.');
    }

    return $stmt;
}


function messages_rows($sql, $types = '', $values = array())
{
    $stmt = messages_query($sql, $types, $values);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return $rows;
}


function messages_transaction($work)
{
    global $conn;

    if (!db_ready() || !mysqli_begin_transaction($conn)) {
        throw new RuntimeException('Could not start a message transaction.');
    }

    try {
        $result = $work();

        if (!mysqli_commit($conn)) {
            throw new RuntimeException('Could not save a message transaction.');
        }

        return $result;
    } catch (Throwable $error) {
        mysqli_rollback($conn);
        throw $error;
    }
}


function messages_log_review($message_id, $reviewer_id, $decision, $reasons_json, $note, $file_name, $file_sha256)
{
    mysqli_stmt_close(messages_query(
        'INSERT INTO message_reviews (message_id, reviewer_id, decision, reasons, note, checker, file_name, file_sha256)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        'iissssss',
        array($message_id, $reviewer_id, $decision, $reasons_json, $note, COMPLIANCE_CHECKER, $file_name, $file_sha256)
    ));
}


// Reason texts for the codes stored with a message.
function messages_reasons($json)
{
    $codes = json_decode((string) $json, true);
    $labels = compliance_reason_labels();
    $texts = array();

    foreach (is_array($codes) ? $codes : array() as $code) {
        if (is_string($code)) {
            $texts[] = $labels[$code] ?? 'It needs a manual check';
        }
    }

    return array_values(array_unique($texts));
}


/* ---------- Sending ---------- */

/*
 * Saves a message that has passed the form checks, after the compliance check.
 * $file is the result of message_file_receive() or null.
 * Returns array('id' => ..., 'held' => bool).
 */
function messages_send($sender_id, $receiver_id, $text, $file)
{
    $saved = messages_transaction(function () use ($sender_id, $receiver_id, $text, $file) {
        global $conn;

        // Locked in id order, so a deletion running at the same moment cannot slip in between.
        $people = array_column(messages_rows(
            "SELECT id, role, organisation FROM users WHERE id IN (?, ?) AND role <> 'deleted' ORDER BY id FOR SHARE",
            'ii',
            array($sender_id, $receiver_id)
        ), null, 'id');

        if ($sender_id === $receiver_id || !isset($people[$sender_id], $people[$receiver_id])) {
            throw new MessageFileProblem('This message cannot be sent to that account.');
        }

        $check = compliance_run($text, $file, $people[$sender_id], $people[$receiver_id]);
        $held = $check['decision'] !== 'ALLOW';
        $reasons = $held ? json_encode($check['reasons']) : null;

        mysqli_stmt_close(messages_query(
            'INSERT INTO messages (sender_id, receiver_id, message, status, review_reasons, delivered_at)
             VALUES (?, ?, ?, ?, ?, ' . ($held ? 'NULL' : 'CURRENT_TIMESTAMP') . ')',
            'iisss',
            array($sender_id, $receiver_id, $text, $held ? 'pending_review' : 'delivered', $reasons)
        ));
        $id = (int) mysqli_insert_id($conn);

        if ($file !== null) {
            mysqli_stmt_close(messages_query(
                'INSERT INTO message_files (message_id, stored_name, original_name, mime, file_size, sha256) VALUES (?, ?, ?, ?, ?, ?)',
                'isssis',
                array($id, $file['stored_name'], $file['original_name'], $file['mime'], $file['file_size'], $file['sha256'])
            ));
        }

        if ($held) {
            messages_log_review($id, null, 'held', $reasons, null, $file['original_name'] ?? null, $file['sha256'] ?? null);
        }

        return array('id' => $id, 'held' => $held);
    });

    if (!$saved['held']) {
        messages_secure_transfer($saved['id']);
    }

    return $saved;
}


/*
 * Sends a delivered message through securecomm / Kafka, once.
 * Best effort: if the Java API is unreachable, the message is still delivered,
 * just without the "Secure Transfer" badge. Files stay on the website; the
 * secure copy carries the file's SHA-256 fingerprint.
 */
function messages_secure_transfer($message_id)
{
    try {
        $rows = messages_rows(
            "SELECT m.sender_id, m.receiver_id, m.message, f.sha256
             FROM messages m
             LEFT JOIN message_files f ON f.message_id = m.id
             WHERE m.id = ? AND m.status = 'delivered' AND m.envelope_id IS NULL",
            'i',
            array($message_id)
        );

        if ($rows === array()) {
            return;
        }

        $text = (string) $rows[0]['message'];

        if ($rows[0]['sha256'] !== null) {
            $text = ltrim($text . "\n[Attachment SHA-256: " . $rows[0]['sha256'] . ']');
        }

        $envelope_id = mc_send_secure((int) $rows[0]['sender_id'], (int) $rows[0]['receiver_id'], $text);

        if ($envelope_id) {
            mysqli_stmt_close(messages_query(
                'UPDATE messages SET envelope_id = ? WHERE id = ?',
                'si',
                array($envelope_id, $message_id)
            ));
        }
    } catch (Throwable $error) {
        error_log('DiSSENT: secure transfer of a message failed: ' . get_class($error));
    }
}


/* ---------- Admin review ---------- */

/*
 * Approves or rejects a held message. Returns '' on success, otherwise a message.
 * An administrator cannot decide about a conversation they take part in.
 */
function messages_review($message_id, $admin_id, $approve, $note)
{
    $problem = messages_transaction(function () use ($message_id, $admin_id, $approve, $note) {
        $rows = messages_rows(
            'SELECT m.sender_id, m.receiver_id, m.status, m.review_reasons, f.original_name, f.sha256,
                    s.role AS sender_role, r.role AS receiver_role
             FROM messages m
             JOIN users s ON s.id = m.sender_id
             JOIN users r ON r.id = m.receiver_id
             LEFT JOIN message_files f ON f.message_id = m.id
             WHERE m.id = ?
             FOR UPDATE',
            'i',
            array($message_id)
        );

        if ($rows === array()) {
            return 'This message no longer exists.';
        }

        $row = $rows[0];

        if ($row['status'] !== 'pending_review') {
            return 'This message has already been decided.';
        }
        if (in_array($admin_id, array((int) $row['sender_id'], (int) $row['receiver_id']), true)) {
            return 'You are part of this conversation, so another administrator must decide.';
        }
        if ($approve && ($row['sender_role'] === 'deleted' || $row['receiver_role'] === 'deleted')) {
            return 'One of the accounts was deleted, so this message cannot be delivered.';
        }

        $note = $note !== '' ? $note : null;

        mysqli_stmt_close(messages_query(
            $approve
                ? "UPDATE messages SET status = 'delivered', delivered_at = CURRENT_TIMESTAMP, review_note = ? WHERE id = ?"
                : "UPDATE messages SET status = 'rejected', review_note = ? WHERE id = ?",
            'si',
            array($note, $message_id)
        ));

        messages_log_review($message_id, $admin_id, $approve ? 'approved' : 'rejected', $row['review_reasons'], $note, $row['original_name'], $row['sha256']);

        return '';
    });

    if ($problem === '' && $approve) {
        messages_secure_transfer($message_id);
    }

    return $problem;
}


/*
 * Part of account_delete(), inside its transaction: withdraws waiting messages
 * and marks the files this person sent as removed.
 * Returns the stored names to delete once the transaction is committed.
 */
function messages_prepare_account_deletion($user_id)
{
    foreach (messages_rows(
        "SELECT id, review_reasons FROM messages WHERE status = 'pending_review' AND (sender_id = ? OR receiver_id = ?) FOR UPDATE",
        'ii',
        array($user_id, $user_id)
    ) as $row) {
        mysqli_stmt_close(messages_query("UPDATE messages SET status = 'withdrawn' WHERE id = ?", 'i', array($row['id'])));
        messages_log_review((int) $row['id'], null, 'withdrawn', $row['review_reasons'], 'An account was deleted.', null, null);
    }

    $stored_names = array_column(messages_rows(
        'SELECT f.stored_name FROM message_files f JOIN messages m ON m.id = f.message_id WHERE m.sender_id = ? AND f.removed_at IS NULL FOR UPDATE',
        'i',
        array($user_id)
    ), 'stored_name');

    // The fingerprint stays in the audit log; file names can be personal and are cleared.
    mysqli_stmt_close(messages_query(
        "UPDATE message_files f JOIN messages m ON m.id = f.message_id
         SET f.removed_at = CURRENT_TIMESTAMP, f.original_name = '', m.changed_at = CURRENT_TIMESTAMP(3)
         WHERE m.sender_id = ?",
        'i',
        array($user_id)
    ));
    mysqli_stmt_close(messages_query(
        'UPDATE message_reviews v JOIN messages m ON m.id = v.message_id SET v.file_name = NULL WHERE m.sender_id = ?',
        'i',
        array($user_id)
    ));

    return $stored_names;
}


/* ---------- Reading ---------- */

/*
 * Messages of one conversation that $user_id may see: everything they sent, and
 * only delivered messages they received. $extra is fixed SQL, never user input.
 */
function messages_visible($user_id, $other_id, $extra = '', $types = '', $values = array())
{
    return messages_rows(
        "SELECT m.id, m.sender_id, m.message, m.envelope_id, m.status, m.review_reasons, m.review_note,
                COALESCE(m.delivered_at, m.sent_at) AS shown_at,
                f.id AS file_id, f.original_name, f.mime, f.file_size, f.removed_at
         FROM messages m
         LEFT JOIN message_files f ON f.message_id = m.id
         WHERE ((m.sender_id = ? AND m.receiver_id = ?)
             OR (m.sender_id = ? AND m.receiver_id = ? AND m.status = 'delivered'))
         $extra
         ORDER BY shown_at ASC, m.id ASC",
        'iiii' . $types,
        array_merge(array($user_id, $other_id, $other_id, $user_id), $values)
    );
}


// Database time a little in the past; the chat asks for everything changed since then.
function messages_cursor()
{
    return (string) messages_rows('SELECT DATE_FORMAT(NOW(3) - INTERVAL 10 SECOND, "%Y-%m-%d %H:%i:%s.%f") AS c')[0]['c'];
}


// Preview (image) or download link (PDF). Everything is escaped.
function message_file_html($file_id, $original_name, $mime, $file_size, $removed_at)
{
    if ($file_id === null) {
        return '';
    }
    if ($removed_at !== null) {
        return '<span class="msg-file-removed">Attachment removed</span>';
    }

    $url = 'message_file.php?id=' . (int) $file_id;
    $size = $file_size >= 1048576 ? round($file_size / 1048576, 1) . ' MB' : max(1, (int) round($file_size / 1024)) . ' KB';

    if (strpos((string) $mime, 'image/') === 0) {
        return '<a class="msg-image" href="' . e($url) . '" target="_blank" rel="noopener">'
            . '<img src="' . e($url) . '" alt="' . e('Attached image: ' . $original_name) . '" width="220" height="165" loading="lazy"></a>';
    }

    return '<a class="msg-file" href="' . e($url) . '">Download PDF: ' . e($original_name) . ' (' . e($size) . ')</a>';
}


// One chat bubble, used by messaging.php, send_message.php and get_new_messages.php.
function message_html($row, $user_id, $other_name)
{
    $mine = (int) $row['sender_id'] === $user_id;
    $envelope = (string) ($row['envelope_id'] ?? '');
    $reviewed = $row['review_reasons'] !== null;

    $html = '<div class="' . ($mine ? 'sent-message' : 'received-message') . '" data-id="' . (int) $row['id'] . '"'
        . ' data-rev="' . e(message_rev($row)) . '"'
        . ($envelope !== '' ? ' data-envelope-id="' . e($envelope) . '"' : '') . '>'
        . '<strong>' . ($mine ? 'You' : e($other_name)) . '</strong>';

    if ((string) $row['message'] !== '') {
        $html .= '<p>' . e($row['message']) . '</p>';
    }

    $html .= message_file_html($row['file_id'], $row['original_name'], $row['mime'], (int) $row['file_size'], $row['removed_at'])
        . '<small class="msg-time" data-time="' . e($row['shown_at']) . '">' . e(date('d M Y, g:i a', strtotime((string) $row['shown_at']))) . '</small>';

    if ($mine && ($reviewed || $row['status'] !== 'delivered')) {
        $labels = array(
            'pending_review' => 'Waiting for review',
            'delivered' => 'Approved',
            'rejected' => 'Rejected',
            'withdrawn' => 'Not delivered: the account was deleted',
        );

        $html .= '<div class="review-status review-status--' . e($row['status']) . '"><strong>'
            . e($labels[$row['status']] ?? 'Waiting for review') . '</strong>';

        if ($row['status'] === 'pending_review') {
            $html .= '<ul>';
            foreach (messages_reasons($row['review_reasons']) as $reason) {
                $html .= '<li>' . e($reason) . '</li>';
            }
            $html .= '</ul>';
        }
        if ($row['status'] === 'rejected' && (string) $row['review_note'] !== '') {
            $html .= '<span>Reason: ' . e($row['review_note']) . '</span>';
        }

        $html .= '</div>';
    }

    if ($envelope !== '') {
        $html .= '<small class="secure-badge">Secure Transfer: Pending</small>';
    }

    return $html . '</div>';
}


// Changes whenever the bubble must be redrawn.
function message_rev($row)
{
    return $row['status'] . '-' . ((string) ($row['envelope_id'] ?? '') !== '' ? '1' : '0') . '-' . ($row['removed_at'] !== null ? '1' : '0');
}
