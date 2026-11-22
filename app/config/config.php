<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

/**
 * Minimal .env loader — no Composer required on shared hosting.
 */
function dde_load_env(string $file): void
{
    if (!is_readable($file)) {
        return;
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($value !== '' && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$key] = $value;
    }
}

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null) {
        return $default;
    }
    return $value;
}

function env_bool(string $key, bool $default = false): bool
{
    $value = env($key);
    if ($value === null) {
        return $default;
    }
    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
}

dde_load_env(BASE_PATH . '/.env');

define('APP_ENV', (string) env('APP_ENV', 'production'));
define('APP_DEBUG', env_bool('APP_DEBUG', false));
define('APP_URL', rtrim((string) env('APP_URL', 'https://dailydoseexport.com'), '/'));
define('APP_TIMEZONE', (string) env('APP_TIMEZONE', 'Asia/Kolkata'));
define('IS_HTTPS', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'));

define('DB_HOST', (string) env('DB_HOST', 'localhost'));
define('DB_PORT', (int) env('DB_PORT', 3306));
define('DB_NAME', (string) env('DB_NAME', 'dailydose'));
define('DB_USER', (string) env('DB_USER', ''));
define('DB_PASS', (string) env('DB_PASS', ''));

define('MAIL_TRANSPORT', (string) env('MAIL_TRANSPORT', 'log'));
define('MAIL_HOST', (string) env('MAIL_HOST', ''));
define('MAIL_PORT', (int) env('MAIL_PORT', 587));
define('MAIL_USER', (string) env('MAIL_USER', ''));
define('MAIL_PASS', (string) env('MAIL_PASS', ''));
define('MAIL_SECURE', (string) env('MAIL_SECURE', 'tls'));
define('MAIL_FROM', (string) env('MAIL_FROM', 'care@dailydoseexport.com'));
define('MAIL_FROM_NAME', (string) env('MAIL_FROM_NAME', 'Daily Dose Export'));

define('RAZORPAY_KEY_ID', (string) env('RAZORPAY_KEY_ID', ''));
define('RAZORPAY_KEY_SECRET', (string) env('RAZORPAY_KEY_SECRET', ''));
define('RAZORPAY_WEBHOOK_SECRET', (string) env('RAZORPAY_WEBHOOK_SECRET', ''));

define('PUBLIC_PATH', BASE_PATH . '/public');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');
