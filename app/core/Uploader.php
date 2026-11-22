<?php
declare(strict_types=1);

final class Uploader
{
    private const MAX_BYTES = 5242880;

    public static function image(array $file, string $subdir = 'products'): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'No file was uploaded.'];
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Upload failed. Please try a smaller image.'];
        }
        if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'Images must be 5 MB or smaller.'];
        }
        if (!is_uploaded_file((string) $file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Invalid upload.'];
        }
        $info = @getimagesize((string) $file['tmp_name']);
        if ($info === false || (int) $info[0] < 10 || (int) $info[1] < 10) {
            return ['ok' => false, 'error' => 'The file is not a valid image.'];
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file((string) $file['tmp_name']);
        $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($map[$mime])) {
            return ['ok' => false, 'error' => 'Only JPG, PNG or WebP images are allowed.'];
        }

        $subdir = preg_match('/^[a-z0-9_-]+$/', $subdir) ? $subdir : 'products';
        $relativeDir = 'uploads/' . $subdir . '/' . date('Y/m');
        $absoluteDir = PUBLIC_PATH . '/' . $relativeDir;
        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0775, true)) {
            return ['ok' => false, 'error' => 'Could not create the upload folder.'];
        }
        $name = bin2hex(random_bytes(10)) . '.' . $map[$mime];
        $target = $absoluteDir . '/' . $name;
        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            return ['ok' => false, 'error' => 'Could not save the uploaded image.'];
        }
        @chmod($target, 0644);
        return ['ok' => true, 'path' => $relativeDir . '/' . $name, 'width' => (int) $info[0], 'height' => (int) $info[1]];
    }

    public static function remove(string $relativePath): void
    {
        if (!str_starts_with($relativePath, 'uploads/')) {
            return;
        }
        $target = realpath(PUBLIC_PATH . '/' . $relativePath);
        $base = realpath(UPLOAD_PATH);
        if ($target !== false && $base !== false && str_starts_with($target, $base . DIRECTORY_SEPARATOR) && is_file($target)) {
            @unlink($target);
        }
    }
}
