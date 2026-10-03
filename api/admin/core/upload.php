<?php
/**
 * Image uploads: type is detected from the file content (not the name),
 * size is capped, files get random names under uploads/admin/<folder>/ and
 * the website-relative path is what gets stored in the DB.
 */

const UPLOAD_MAX_BYTES = 2 * 1024 * 1024;
const UPLOAD_MIME_EXT  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
// Larger uploads are scaled down (aspect kept) and re-compressed on save.
const UPLOAD_MAX_SIDE  = 1600;
const UPLOAD_RECOMPRESS_BYTES = 400 * 1024;

/**
 * Saves $_FILES[$field] if one was sent.
 * @return string|null relative path (e.g. uploads/admin/services/ab12cd.jpg) or null when no file
 */
function save_uploaded_image(string $field, string $folder): ?string
{
    $file = $_FILES[$field] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (!preg_match('/^[a-z0-9_-]+$/', $folder)) {
        throw new ApiException('Invalid upload folder.', 500);
    }
    if (is_array($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $tooBig = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        throw new ApiException($tooBig ? 'Image is too large (max 2 MB).' : 'Image upload failed.', 422, [$field => $tooBig ? 'Max 2 MB.' : 'Upload failed.']);
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        throw new ApiException('Image is too large (max 2 MB).', 422, [$field => 'Max 2 MB.']);
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new ApiException('Image upload failed.', 422, [$field => 'Upload failed.']);
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(UPLOAD_MIME_EXT[$mime]) || @getimagesize($file['tmp_name']) === false) {
        throw new ApiException('Only JPG, PNG or WEBP images are allowed.', 422, [$field => 'JPG, PNG or WEBP only.']);
    }

    $relDir = 'uploads/admin/' . $folder;
    $absDir = ZC_ROOT . '/' . $relDir;
    if (!is_dir($absDir) && !mkdir($absDir, 0755, true) && !is_dir($absDir)) {
        throw new ApiException('Upload folder is not writable.', 500);
    }
    upload_protect_dir(ZC_ROOT . '/uploads/admin');

    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . UPLOAD_MIME_EXT[$mime];
    if (!move_uploaded_file($file['tmp_name'], $absDir . '/' . $name)) {
        throw new ApiException('Could not save the image.', 500);
    }
    $info = @getimagesize($absDir . '/' . $name);
    if ($info && (max($info[0], $info[1]) > UPLOAD_MAX_SIDE || filesize($absDir . '/' . $name) > UPLOAD_RECOMPRESS_BYTES)) {
        image_fit_file($absDir . '/' . $name, $absDir . '/' . $name, UPLOAD_MAX_SIDE, UPLOAD_MAX_SIDE);
    }
    return $relDir . '/' . $name;
}

/**
 * Scales an image down to fit $maxW x $maxH (never up) and re-encodes it.
 * $format: 'webp' | 'jpg' | 'png' | null (keep the source format).
 * Transparency is kept for PNG/WebP. Needs GD; returns false (and leaves
 * $dest untouched) when GD is missing or the image can't be read, or when
 * the result would be larger than an in-place source.
 */
function image_fit_file(string $src, string $dest, int $maxW, int $maxH, ?string $format = null, int $quality = 80): bool
{
    $info = @getimagesize($src);
    if (!$info || !function_exists('imagecreatetruecolor')) {
        return false;
    }
    [$w, $h, $type] = $info;
    $load = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'][$type] ?? null;
    if (!$load || !function_exists($load) || !($img = @$load($src))) {
        return false;
    }
    $format = $format ?? [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$type];
    $scale = min(1, $maxW / $w, $maxH / $h);
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));

    $out = imagecreatetruecolor($nw, $nh);
    if ($format === 'jpg') {
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    } else {
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    }
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($img);

    $tmp = $dest . '.tmp' . bin2hex(random_bytes(3));
    $saved = match ($format) {
        'webp'  => imagewebp($out, $tmp, $quality),
        'png'   => imagepng($out, $tmp, 8),
        default => imagejpeg($out, $tmp, min(90, $quality + 2)),
    };
    imagedestroy($out);
    if (!$saved || !is_file($tmp)) {
        @unlink($tmp);
        return false;
    }
    if (realpath($src) === realpath($dest) && $scale >= 1 && filesize($tmp) >= filesize($src)) {
        @unlink($tmp);
        return false;
    }
    if (is_file($dest)) {
        @unlink($dest);
    }
    return rename($tmp, $dest);
}

/** Deletes a file previously saved by save_uploaded_image(). */
function delete_uploaded_image(?string $relPath): void
{
    if ($relPath && preg_match('#^uploads/admin/[a-z0-9_-]+/[A-Za-z0-9.-]+$#', $relPath)) {
        $abs = ZC_ROOT . '/' . $relPath;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}

/** Blocks script execution inside the uploads folder. */
function upload_protect_dir(string $dir): void
{
    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        // php_flag is only valid under mod_php; a bare php_flag gives a 500 on PHP-FPM hosts.
        @file_put_contents($htaccess, "# Uploaded files only: scripts are never executed or served, no directory listing.\n"
            . "<FilesMatch \"\\.(?i:php\\d?|phtml|phar|pht|phps|pl|py|cgi|sh|asp|aspx|jsp|shtml)(\\.|$)\">\n"
            . "    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>\n"
            . "    <IfModule !mod_authz_core.c>\n        Order allow,deny\n        Deny from all\n    </IfModule>\n"
            . "</FilesMatch>\nOptions -Indexes -ExecCGI\n"
            . "<IfModule mod_php.c>\n    php_flag engine off\n</IfModule>\n"
            . "<IfModule mod_php7.c>\n    php_flag engine off\n</IfModule>\n");
    }
}
