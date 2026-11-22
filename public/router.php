<?php
// Router for PHP's built-in development server. Apache uses .htaccess in production.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

if (preg_match('#^/products/([a-z0-9-]+)/?$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/product.php';
    return true;
}
if (preg_match('#^/category/([a-z0-9-]+)/?$#', $path, $m)) {
    $_GET['category'] = $m[1];
    require __DIR__ . '/shop.php';
    return true;
}
if (preg_match('#^/pages/([a-z0-9-]+)/?$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/page.php';
    return true;
}
if ($path === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    return true;
}
if ($path === '/admin' || $path === '/admin/') {
    require __DIR__ . '/admin/index.php';
    return true;
}
if ($path === '/') {
    require __DIR__ . '/index.php';
    return true;
}
http_response_code(404);
require __DIR__ . '/404.php';
return true;
