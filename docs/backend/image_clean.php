<?php
/*
 * Removes hidden data from an uploaded image (26/11/2026).
 * Used for message images (message_files.php) and profile pictures (content.php).
 *
 * The image is decoded and only its pixels are saved again, so EXIF (GPS location,
 * camera, date and time), comments and other metadata are not copied. Phone photos
 * store "which way is up" in EXIF, so that is applied first and the photo stays upright.
 */


/*
 * $source: the uploaded file. $path: where to save the clean copy.
 * $mime: image/jpeg, image/png, image/webp or image/gif (already checked by the caller).
 * $max_side: scale down so the longest side is at most this many pixels (0 = keep the size).
 * Returns false if the image cannot be decoded; throws RuntimeException if it cannot be saved.
 */
function image_clean_save($source, $path, $mime, $max_side = 0)
{
    $open = array(
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
        'image/gif' => 'imagecreatefromgif',
    );

    if (!isset($open[$mime]) || !function_exists($open[$mime])) {
        throw new RuntimeException('Image cleaning is not installed.');
    }

    ini_set('memory_limit', '512M');

    $image = @$open[$mime]($source);

    if (!$image) {
        return false;
    }

    // Photos store "which way is up" in EXIF, which is about to be removed.
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($source);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        if (in_array($orientation, array(2, 4, 5, 7), true)) {
            imageflip($image, in_array($orientation, array(2, 5, 7), true) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
        }

        $turn = array(3 => 180, 5 => 90, 6 => 270, 7 => 270, 8 => 90);

        if (isset($turn[$orientation])) {
            $image = imagerotate($image, $turn[$orientation], 0);
        }
    }

    $width = imagesx($image);
    $height = imagesy($image);

    if ($max_side > 0 && max($width, $height) > $max_side) {
        $scale = $max_side / max($width, $height);
        $image = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)), IMG_BICUBIC);
    }

    if (!$image) {
        return false;
    }

    if ($mime === 'image/jpeg') {
        $saved = imagejpeg($image, $path, 88);
    } elseif ($mime === 'image/gif') {
        $saved = imagegif($image, $path);
    } else {
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $saved = $mime === 'image/png' ? imagepng($image, $path, 6) : imagewebp($image, $path, 88);
    }

    if (!$saved) {
        throw new RuntimeException('Could not save a cleaned image.');
    }

    return true;
}
