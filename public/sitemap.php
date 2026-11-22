<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');

$products = Database::all("SELECT id, slug, updated_at FROM products WHERE status = 'published' ORDER BY id ASC");
$categories = Database::all("SELECT slug, updated_at FROM categories WHERE status = 'active' ORDER BY sort_order ASC, id ASC");
$pages = Database::all("SELECT slug, updated_at FROM cms_pages WHERE status = 'published' ORDER BY id ASC");

$productImages = [];
foreach (Database::all('SELECT product_id, image_path, alt_text FROM product_images ORDER BY is_primary DESC, sort_order ASC, id ASC') as $image) {
    $productId = (int) $image['product_id'];
    if (isset($productImages[$productId]) && count($productImages[$productId]) >= 5) {
        continue;
    }
    $productImages[$productId][] = $image;
}

$urls = [];
$staticPages = [
    ['', '1.0', 'weekly'],
    ['shop.php', '0.9', 'daily'],
    ['about.php', '0.7', 'monthly'],
    ['contact.php', '0.7', 'monthly'],
    ['wholesale.php', '0.7', 'monthly'],
    ['export-enquiry.php', '0.7', 'monthly'],
];
foreach ($staticPages as [$path, $priority, $changefreq]) {
    $urls[] = [
        'loc' => url($path),
        'lastmod' => null,
        'changefreq' => $changefreq,
        'priority' => $priority,
        'images' => [],
    ];
}
foreach ($categories as $category) {
    $urls[] = [
        'loc' => url('category/' . $category['slug']),
        'lastmod' => $category['updated_at'] ? date('c', strtotime((string) $category['updated_at'])) : null,
        'changefreq' => 'weekly',
        'priority' => '0.8',
        'images' => [],
    ];
}
foreach ($products as $product) {
    $urls[] = [
        'loc' => url('products/' . $product['slug']),
        'lastmod' => $product['updated_at'] ? date('c', strtotime((string) $product['updated_at'])) : null,
        'changefreq' => 'weekly',
        'priority' => '0.8',
        'images' => $productImages[(int) $product['id']] ?? [],
    ];
}
foreach ($pages as $page) {
    $urls[] = [
        'loc' => url('pages/' . $page['slug']),
        'lastmod' => $page['updated_at'] ? date('c', strtotime((string) $page['updated_at'])) : null,
        'changefreq' => 'monthly',
        'priority' => $page['slug'] === 'faq' ? '0.6' : '0.4',
        'images' => [],
    ];
}

$xmlEscape = static fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
    . ' xmlns:xhtml="http://www.w3.org/1999/xhtml"'
    . ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
foreach ($urls as $entry) {
    echo "  <url>\n";
    echo '    <loc>' . $xmlEscape($entry['loc']) . "</loc>\n";
    foreach (hreflang_links($entry['loc']) as $alternate) {
        echo '    <xhtml:link rel="alternate" hreflang="' . $xmlEscape($alternate['hreflang']) . '" href="' . $xmlEscape($alternate['href']) . '"/>' . "\n";
    }
    if (!empty($entry['lastmod'])) {
        echo '    <lastmod>' . $xmlEscape($entry['lastmod']) . "</lastmod>\n";
    }
    echo '    <changefreq>' . $xmlEscape($entry['changefreq']) . "</changefreq>\n";
    echo '    <priority>' . $xmlEscape($entry['priority']) . "</priority>\n";
    foreach ($entry['images'] as $image) {
        if (trim((string) ($image['image_path'] ?? '')) === '') {
            continue;
        }
        echo "    <image:image>\n";
        echo '      <image:loc>' . $xmlEscape(asset_url((string) $image['image_path'])) . "</image:loc>\n";
        if (trim((string) ($image['alt_text'] ?? '')) !== '') {
            echo '      <image:title>' . $xmlEscape((string) $image['alt_text']) . "</image:title>\n";
        }
        echo "    </image:image>\n";
    }
    echo "  </url>\n";
}
echo "</urlset>\n";
