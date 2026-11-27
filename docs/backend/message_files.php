<?php
/*
 * DiSSENT message attachments: one JPG, PNG, WebP or PDF per message, up to 5 MB.
 *
 * Files are stored outside the web root in private/message-files/ under a random
 * name and are only sent out by Pages/message_file.php.
 *
 * Images are decoded and saved again, which removes EXIF data such as the GPS
 * position. Information inside a PDF (for example its author) is NOT removed.
 */

require_once __DIR__ . '/image_clean.php';

define('MESSAGE_FILE_MAX_BYTES', 5 * 1024 * 1024);
define('MESSAGE_FILE_MAX_PIXELS', 25000000);
define('MESSAGE_FILE_MAX_SIDE', 2560);


class MessageFileProblem extends Exception
{
}


function message_file_types()
{
    return array(
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    );
}


function message_files_dir($create = false)
{
    $directory = dirname(__DIR__, 2) . '/private/message-files';

    if ($create && !is_dir($directory)) {
        @mkdir($directory, 0770, true);
    }

    if (!is_dir($directory) || is_link($directory)) {
        throw new RuntimeException('Message file storage is unavailable.');
    }

    return $directory;
}


// Full path of a stored file, or null when the name is not one of ours.
function message_file_path($stored_name)
{
    if (!is_string($stored_name) || preg_match('/\A[a-f0-9]{48}\.(jpg|png|webp|pdf)\z/', $stored_name) !== 1) {
        return null;
    }

    return dirname(__DIR__, 2) . '/private/message-files/' . $stored_name;
}


function message_file_delete($stored_name)
{
    $path = message_file_path($stored_name);

    if ($path !== null && is_file($path) && !@unlink($path)) {
        error_log('DiSSENT: a message file could not be deleted.');
    }
}


/*
 * Checks, cleans and stores the uploaded file.
 * Returns null when no file was chosen, or an array with stored_name,
 * original_name, mime, file_size, sha256 and path.
 * Throws MessageFileProblem with a message that is safe to show to the user.
 */
function message_file_receive($upload)
{
    if ($upload === null) {
        return null;
    }

    if (!is_array($upload) || !is_int($upload['error'] ?? null)) {
        throw new MessageFileProblem('Please attach one file only.');
    }

    if ($upload['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (in_array($upload['error'], array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)) {
        throw new MessageFileProblem('The file is too large. The limit is 5 MB.');
    }

    $source = $upload['tmp_name'] ?? '';

    if ($upload['error'] !== UPLOAD_ERR_OK || !is_string($source) || !is_uploaded_file($source)) {
        throw new MessageFileProblem('The file could not be uploaded. Please try again.');
    }

    $size = filesize($source);

    if ($size === false || $size < 1) {
        throw new MessageFileProblem('The file is empty.');
    }
    if ($size > MESSAGE_FILE_MAX_BYTES) {
        throw new MessageFileProblem('The file is too large. The limit is 5 MB.');
    }

    // The real type, from the content. The file name is not trusted.
    $types = message_file_types();
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($source);

    if (!is_string($mime) || !isset($types[$mime])) {
        throw new MessageFileProblem('Only JPG, PNG, WebP and PDF files can be attached.');
    }

    if ($mime === 'application/pdf' && file_get_contents($source, false, null, 0, 5) !== '%PDF-') {
        throw new MessageFileProblem('This PDF is not valid.');
    }

    $extension = $types[$mime];
    $stored_name = bin2hex(random_bytes(24)) . '.' . $extension;
    $path = message_files_dir(true) . '/' . $stored_name;

    try {
        if ($mime === 'application/pdf') {
            if (!move_uploaded_file($source, $path)) {
                throw new RuntimeException('Could not store a message file.');
            }
        } else {
            message_file_clean_image($source, $path, $mime);
        }

        @chmod($path, 0640);
        $stored_size = filesize($path);
        $hash = hash_file('sha256', $path);

        if ($stored_size === false || $hash === false) {
            throw new RuntimeException('Could not read a stored message file.');
        }
        if ($stored_size > MESSAGE_FILE_MAX_BYTES) {
            throw new MessageFileProblem('This image is larger than 5 MB after its hidden data is removed. Please send a smaller one or use JPG.');
        }
    } catch (Throwable $error) {
        if (is_file($path)) {
            @unlink($path);
        }
        throw $error;
    }

    return array(
        'stored_name' => $stored_name,
        'original_name' => message_file_display_name($upload['name'] ?? '', $extension),
        'mime' => $mime,
        'file_size' => $stored_size,
        'sha256' => $hash,
        'path' => $path,
    );
}


// A tidy file name to show, always ending in the real type.
function message_file_display_name($name, $extension)
{
    $name = is_string($name) ? $name : '';
    $name = preg_match('//u', $name) === 1 ? $name : '';
    $name = preg_replace('/[\p{C}\\\\\/:*?"<>|]+/u', ' ', $name) ?? '';
    $name = trim(preg_replace('/\.[A-Za-z0-9]{1,5}$/', '', trim($name)) ?? '', " .\t");
    $name = mb_substr($name, 0, 100, 'UTF-8');

    return ($name !== '' ? $name : 'file') . '.' . $extension;
}


// Decodes the image and writes only its pixels to $path.
function message_file_clean_image($source, $path, $mime)
{
    $bad = 'This image could not be read. Please try another one.';
    $info = @getimagesize($source);

    if ($info === false || ($info['mime'] ?? '') !== $mime || $info[0] < 1 || $info[1] < 1) {
        throw new MessageFileProblem($bad);
    }
    if ($info[0] * $info[1] > MESSAGE_FILE_MAX_PIXELS) {
        throw new MessageFileProblem('This image has too many pixels (the limit is 25 megapixels). Please send a smaller one.');
    }
    // Shared with profile pictures (image_clean.php): decode, turn upright, save only the pixels.
    if (!image_clean_save($source, $path, $mime, MESSAGE_FILE_MAX_SIDE)) {
        throw new MessageFileProblem($bad . ' Animated images are not supported.');
    }
}
