<?php
declare(strict_types=1);

if (defined('DDE_BOOTSTRAPPED')) {
    return;
}
define('DDE_BOOTSTRAPPED', true);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/helpers/icons.php';
require_once __DIR__ . '/helpers/seo.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Csrf.php';
require_once __DIR__ . '/core/Settings.php';
require_once __DIR__ . '/core/RateLimiter.php';
require_once __DIR__ . '/core/Mailer.php';
require_once __DIR__ . '/core/Uploader.php';
require_once __DIR__ . '/core/Notifications.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Catalog.php';
require_once __DIR__ . '/core/Coupon.php';
require_once __DIR__ . '/core/Cart.php';
require_once __DIR__ . '/core/Order.php';
require_once __DIR__ . '/core/Payment.php';
require_once __DIR__ . '/core/Enquiry.php';

if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

date_default_timezone_set(APP_TIMEZONE);
error_reporting(APP_DEBUG ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
if (!is_dir(STORAGE_PATH . '/logs')) {
    @mkdir(STORAGE_PATH . '/logs', 0775, true);
}
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

set_exception_handler(function (Throwable $e): void {
    log_message('error', 'Unhandled exception: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    if (APP_DEBUG) {
        echo '<!doctype html><meta charset="utf-8"><title>Application error</title><body style="font-family:system-ui;padding:40px;max-width:900px;margin:auto">'
            . '<h1 style="color:#173F32">Application error</h1><p>' . e($e->getMessage()) . '</p>'
            . '<p style="color:#737A73">' . e($e->getFile()) . ':' . (int) $e->getLine() . '</p></body>';
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title><body style="font-family:system-ui;padding:40px;text-align:center;background:#F8F5EC;color:#252B27">'
            . '<h1 style="color:#173F32">We could not complete that request</h1>'
            . '<p>Please try again in a moment, or call us on ' . e(setting('support_phone', '+91 84299 33125')) . '.</p>'
            . '<p><a href="' . e(url('')) . '" style="color:#245A43">Return to home</a></p></body>';
    }
    exit;
});

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => IS_HTTPS,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('dde_session');
    session_start();
}

$locale = (string) ($_SESSION['locale'] ?? $_COOKIE['dde_locale'] ?? 'en');
if (!array_key_exists($locale, supported_locales())) {
    $locale = 'en';
}
$requestedLocale = isset($_GET['lang']) && is_string($_GET['lang']) ? $_GET['lang'] : '';
if ($requestedLocale !== '' && array_key_exists($requestedLocale, supported_locales())) {
    $locale = $requestedLocale;
    $_SESSION['locale'] = $locale;
    setcookie('dde_locale', $locale, [
        'expires' => time() + 31536000,
        'path' => '/',
        'secure' => IS_HTTPS,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    // The default locale lives on clean URLs; non-default locales keep ?lang= so search engines can crawl them.
    if ($locale === 'en' && !headers_sent() && !is_post()) {
        $target = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $query = $_GET;
        unset($query['lang']);
        $qs = http_build_query($query);
        header('Location: ' . $target . ($qs !== '' ? '?' . $qs : ''), true, 302);
        exit;
    }
}
$GLOBALS['__locale'] = $locale;

if (!headers_sent()) {
    header('Content-Language: ' . ($locale === 'hi' ? 'hi' : 'en'));
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=(self "https://checkout.razorpay.com")');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://checkout.razorpay.com https://cdn.razorpay.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://api.razorpay.com https://lumberjack.razorpay.com; frame-src https://api.razorpay.com https://checkout.razorpay.com; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
    if (IS_HTTPS) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

Csrf::token();
if (!isset($_SESSION['guest_cart']) || !is_array($_SESSION['guest_cart'])) {
    $_SESSION['guest_cart'] = [];
}

Settings::load();

$requestPath = current_path();
$isAdminArea = str_starts_with($requestPath, '/admin');
$isStatic = (bool) preg_match('/\.(css|js|jpe?g|png|webp|gif|svg|ico|woff2?)$/i', $requestPath);
if (setting('maintenance_mode', '0') === '1' && !$isAdminArea && !$isStatic) {
    http_response_code(503);
    header('Retry-After: 3600');
    if (str_starts_with($requestPath, '/api')) {
        json_response(['ok' => false, 'message' => 'The website is temporarily under maintenance.'], 503);
    }
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Back shortly | ' . e(setting('site_name', 'Daily Dose Export')) . '</title>'
        . '<body style="margin:0;font-family:Inter,Arial,sans-serif;background:#F8F5EC;color:#252B27;display:grid;place-items:center;min-height:100vh;text-align:center;padding:24px">'
        . '<div><p style="letter-spacing:.22em;text-transform:uppercase;font-size:12px;color:#9A7046">' . e(setting('site_name')) . '</p>'
        . '<h1 style="font-family:Georgia,serif;color:#173F32;font-size:clamp(28px,6vw,44px)">We are updating the website</h1>'
        . '<p style="max-width:440px;color:#737A73">We will be back shortly. For urgent orders or enquiries, call ' . e(setting('support_phone', '+91 84299 33125')) . '.</p>'
        . '<a href="https://wa.me/' . e(setting('whatsapp_number')) . '" style="display:inline-block;margin-top:8px;background:#245A43;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none">Message us on WhatsApp</a></div></body></html>';
    exit;
}
