<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return APP_URL . ($path === '' ? '/' : '/' . ltrim($path, '/'));
}

function asset_url(?string $path): string
{
    if ($path === null || trim($path) === '') {
        return url('assets/images/placeholder.svg');
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return url(ltrim($path, '/'));
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function admin_url(string $path = ''): string
{
    return url('admin/' . ltrim($path, '/'));
}

function safe_local_path(?string $path, string $fallback = 'account.php'): string
{
    $path = trim((string) $path);
    if ($path === '' || str_starts_with($path, '//') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path)) {
        return $fallback;
    }
    $path = ltrim($path, '/');
    if ($path === '' || str_contains($path, '://') || str_contains($path, '\\')) {
        return $fallback;
    }
    return $path;
}

function current_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    return $path === null || $path === '' ? '/' : $path;
}

function page_url(array $overrides = []): string
{
    $query = $_GET;
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }
    $qs = http_build_query($query);
    $path = ltrim(current_path(), '/');
    return url($path) . ($qs !== '' ? '?' . $qs : '');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($items) ? $items : [];
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !Csrf::check($token)) {
        http_response_code(419);
        exit('Security check failed. Please go back, refresh the page and try again.');
    }
}

function setting(string $key, string $default = ''): string
{
    return Settings::get($key, $default);
}

function current_locale(): string
{
    return $GLOBALS['__locale'] ?? 'en';
}

function supported_locales(): array
{
    return ['en' => 'English', 'hi' => 'हिंदी'];
}

function t(string $key, ?string $fallback = null): string
{
    if (!isset($GLOBALS['__lang']['en'])) {
        $file = BASE_PATH . '/app/lang/en.php';
        $GLOBALS['__lang']['en'] = is_file($file) ? (require $file) : [];
    }
    $locale = current_locale();
    if ($locale !== 'en' && !isset($GLOBALS['__lang'][$locale])) {
        $file = BASE_PATH . '/app/lang/' . $locale . '.php';
        $GLOBALS['__lang'][$locale] = is_file($file) ? (require $file) : [];
    }
    return $GLOBALS['__lang'][$locale][$key]
        ?? $GLOBALS['__lang']['en'][$key]
        ?? $fallback
        ?? $key;
}

function language_url(string $locale): string
{
    if (!isset(supported_locales()[$locale])) {
        $locale = 'en';
    }
    $overrides = ['lang' => $locale];
    $path = current_path();
    if (preg_match('#^/(products|pages)/#', $path)) {
        $overrides['slug'] = null;
    }
    if (str_starts_with($path, '/category/')) {
        $overrides['category'] = null;
    }
    return page_url($overrides);
}

function requested_locale(): ?string
{
    $lang = $_GET['lang'] ?? null;
    return is_string($lang) && array_key_exists($lang, supported_locales()) ? $lang : null;
}

function locale_variant_url(string $url, string $locale): string
{
    if (!array_key_exists($locale, supported_locales())) {
        $locale = 'en';
    }
    $parts = parse_url($url);
    if ($parts === false || !isset($parts['host'])) {
        return $url;
    }
    $query = [];
    if (isset($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    if ($locale === 'en') {
        unset($query['lang']);
    } else {
        $query['lang'] = $locale;
    }
    $base = ($parts['scheme'] ?? 'https') . '://' . $parts['host'];
    if (isset($parts['port'])) {
        $base .= ':' . $parts['port'];
    }
    $base .= $parts['path'] ?? '/';
    $qs = http_build_query($query);
    return $base . ($qs !== '' ? '?' . $qs : '') . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
}

function hreflang_links(string $canonical): array
{
    $base = locale_variant_url($canonical, 'en');
    $links = [];
    foreach (array_keys(supported_locales()) as $code) {
        $links[] = ['hreflang' => $code, 'href' => locale_variant_url($base, $code)];
    }
    $links[] = ['hreflang' => 'x-default', 'href' => $base];
    return $links;
}

function localized_setting(string $key, string $default = ''): string
{
    $locale = current_locale();
    if ($locale !== 'en') {
        $translated = setting($key . '_' . $locale);
        if ($translated !== '') {
            return $translated;
        }
    }
    return setting($key, $default);
}

function category_label(array $category): string
{
    $slug = (string) ($category['slug'] ?? '');
    $name = (string) ($category['name'] ?? $slug);
    if ($slug === '') {
        return $name;
    }
    return t('category.' . $slug, $name);
}

function money(mixed $amount): string
{
    if ($amount === null || $amount === '') {
        return 'Price on request';
    }
    $value = (float) $amount;
    $decimals = fmod($value, 1.0) === 0.0 ? 0 : 2;
    return '₹' . number_format($value, $decimals, '.', ',');
}

function discount_percent(mixed $price, mixed $mrp): ?int
{
    if ($price === null || $mrp === null || (float) $mrp <= 0 || (float) $price >= (float) $mrp) {
        return null;
    }
    return (int) round((((float) $mrp - (float) $price) / (float) $mrp) * 100);
}

function excerpt(?string $text, int $limit = 130): string
{
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)) ?? '');
    if (mb_strlen($plain) <= $limit) {
        return $plain;
    }
    return rtrim(mb_substr($plain, 0, $limit)) . '…';
}

function date_display(?string $dateTime, bool $withTime = false): string
{
    if (!$dateTime) {
        return '—';
    }
    $ts = strtotime($dateTime);
    return $ts ? date($withTime ? 'j M Y, g:i A' : 'j M Y', $ts) : '—';
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post_str(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function post_int(string $key, int $default = 0): int
{
    $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT);
    return $value === false ? $default : (int) $value;
}

function request_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function is_https(): bool
{
    return IS_HTTPS;
}

function log_message(string $level, string $message, array $context = []): void
{
    $dir = STORAGE_PATH . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . strtoupper($level) . ' ' . $message;
    if ($context !== []) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    @file_put_contents($dir . '/app.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function safe_json(mixed $data): string
{
    return json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?: '{}';
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function sanitize_html(string $html): string
{
    if (trim($html) === '') {
        return '';
    }
    if (mb_strlen($html) > 300000) {
        $html = mb_substr($html, 0, 300000);
    }
    $allowed = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'blockquote' => [], 'hr' => [], 'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'width', 'height'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
    ];
    $dangerous = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math', 'link', 'meta', 'base'];

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $doc->loadHTML('<div id="dde-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $wrapper = $doc->getElementsByTagName('div')->item(0);
    if (!$wrapper) {
        return e(strip_tags($html));
    }

    $clean = function (DOMNode $parent) use (&$clean, $allowed, $dangerous): void {
        $children = [];
        foreach ($parent->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $node) {
            if ($node instanceof DOMComment) {
                $parent->removeChild($node);
                continue;
            }
            if (!$node instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($node->tagName);
            if (in_array($tag, $dangerous, true)) {
                $parent->removeChild($node);
                continue;
            }
            $clean($node);
            if (!array_key_exists($tag, $allowed)) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }
            $attrs = [];
            foreach ($node->attributes as $attr) {
                $attrs[] = $attr->name;
            }
            foreach ($attrs as $name) {
                if (!in_array(strtolower($name), $allowed[$tag], true)) {
                    $node->removeAttribute($name);
                }
            }
            if ($tag === 'a') {
                $href = $node->getAttribute('href');
                if ($href !== '' && !is_safe_url($href)) {
                    $node->removeAttribute('href');
                }
            }
            if ($tag === 'img') {
                $src = $node->getAttribute('src');
                if ($src === '' || !is_safe_url($src, true)) {
                    $parent->removeChild($node);
                    continue;
                }
                $node->setAttribute('loading', 'lazy');
            }
        }
    };
    $clean($wrapper);

    $out = '';
    foreach ($wrapper->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}

function is_safe_url(string $url, bool $imageOnly = false): bool
{
    $url = trim($url);
    if ($url === '' || str_starts_with($url, '#')) {
        return true;
    }
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
        return true;
    }
    if (preg_match('/^(javascript|data|vbscript|file):/i', $url)) {
        return false;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if ($scheme === '') {
        return true;
    }
    if ($imageOnly) {
        return in_array($scheme, ['http', 'https'], true);
    }
    return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
}

function pagination_html(int $page, int $totalPages, array $query = []): string
{
    if ($totalPages <= 1) {
        return '';
    }
    $page = max(1, min($page, $totalPages));
    $html = '<nav class="pagination" aria-label="Pagination">';
    $mk = function (int $p, string $label, string $class = '') use ($query): string {
        $qs = http_build_query(array_merge($query, ['page' => $p]));
        return '<a class="page-link ' . $class . '" href="' . e(current_path() . '?' . $qs) . '">' . $label . '</a>';
    };
    if ($page > 1) {
        $html .= $mk($page - 1, '&larr; Prev');
    }
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    if ($start > 1) {
        $html .= $mk(1, '1');
        if ($start > 2) {
            $html .= '<span class="page-gap">…</span>';
        }
    }
    for ($p = $start; $p <= $end; $p++) {
        $html .= $p === $page
            ? '<span class="page-link is-current" aria-current="page">' . $p . '</span>'
            : $mk($p, (string) $p);
    }
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<span class="page-gap">…</span>';
        }
        $html .= $mk($totalPages, (string) $totalPages);
    }
    if ($page < $totalPages) {
        $html .= $mk($page + 1, 'Next &rarr;');
    }
    return $html . '</nav>';
}

function rating_stars(float $average): string
{
    $out = '<span class="stars" aria-label="' . e(number_format($average, 1)) . ' out of 5">';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<span class="star ' . ($i <= round($average) ? 'is-on' : '') . '">' . icon('star') . '</span>';
    }
    return $out . '</span>';
}

function order_status_label(string $status): string
{
    $labels = [
        'pending' => 'Pending', 'confirmed' => 'Confirmed', 'processing' => 'Processing',
        'packed' => 'Packed', 'shipped' => 'Shipped', 'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'return_requested' => 'Return requested',
        'returned' => 'Returned', 'refunded' => 'Refunded',
    ];
    return t('order_status.' . $status, $labels[$status] ?? ucfirst(str_replace('_', ' ', $status)));
}

function payment_status_label(string $status): string
{
    $labels = [
        'unpaid' => 'Unpaid', 'pending' => 'Payment pending', 'paid' => 'Paid',
        'failed' => 'Failed', 'refunded' => 'Refunded', 'partially_refunded' => 'Partially refunded',
    ];
    return t('pay_status.' . $status, $labels[$status] ?? ucfirst($status));
}

function enquiry_status_label(string $status): string
{
    return [
        'new' => 'New', 'contacted' => 'Contacted', 'quotation_sent' => 'Quotation sent',
        'negotiation' => 'Negotiation', 'converted' => 'Converted', 'closed' => 'Closed',
    ][$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function status_class(string $status): string
{
    return 'status status-' . preg_replace('/[^a-z0-9-]/', '-', strtolower(str_replace('_', '-', $status)));
}

function email_wrap(string $title, string $bodyHtml, ?array $cta = null): string
{
    $site = e(setting('site_name', 'Daily Dose Export'));
    $phone = e(setting('support_phone', ''));
    $ctaHtml = '';
    if ($cta && !empty($cta['url']) && !empty($cta['label'])) {
        $ctaHtml = '<p style="margin:26px 0"><a href="' . e($cta['url']) . '" style="background:#245A43;color:#fff;text-decoration:none;padding:13px 22px;border-radius:6px;display:inline-block;font-weight:600">' . e($cta['label']) . '</a></p>';
    }
    return '<!doctype html><html><body style="margin:0;background:#F8F5EC;font-family:Arial,Helvetica,sans-serif;color:#252B27">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F8F5EC;padding:24px 12px"><tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFDF8;border:1px solid #E5DFD0;border-radius:8px;overflow:hidden">'
        . '<tr><td style="background:#173F32;color:#FFFDF8;padding:20px 28px;font-size:20px;font-weight:700;letter-spacing:.3px">' . $site . '</td></tr>'
        . '<tr><td style="padding:28px"><h1 style="font-size:22px;margin:0 0 16px;color:#173F32">' . e($title) . '</h1>'
        . $bodyHtml . $ctaHtml
        . '<hr style="border:none;border-top:1px solid #E5DFD0;margin:26px 0"><p style="font-size:12px;color:#737A73;margin:0">'
        . 'Questions? Call ' . $phone . '. This message was sent by ' . $site . '.</p></td></tr></table></td></tr></table></body></html>';
}

function unique_code(string $prefix, string $table, string $column, int $randomLength = 4): string
{
    for ($i = 0; $i < 20; $i++) {
        $code = $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, $randomLength));
        $exists = Database::value("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?", [$code]);
        if ((int) $exists === 0) {
            return $code;
        }
    }
    return $prefix . '-' . time();
}

function full_address(array $order, string $prefix = 'shipping_'): string
{
    $parts = [
        $order[$prefix . 'house'] ?? '',
        $order[$prefix . 'street'] ?? '',
        $order[$prefix . 'landmark'] ?? '',
        $order[$prefix . 'city'] ?? '',
        $order[$prefix . 'state'] ?? '',
        $order[$prefix . 'pincode'] ?? '',
        $order[$prefix . 'country'] ?? '',
    ];
    return implode(', ', array_filter(array_map('trim', $parts)));
}
