<?php
declare(strict_types=1);

function render_seo_head(array $seo = []): string
{
    $siteName = setting('site_name', 'Daily Dose Export');
    $title = $seo['title'] ?? ($siteName . ' | ' . setting('tagline', 'Pure From Farm To World'));
    $description = $seo['description'] ?? setting('default_meta_description', '');
    $canonical = $seo['canonical'] ?? url(ltrim(current_path(), '/'));
    $image = asset_url($seo['image'] ?? setting('og_image', 'assets/images/hero.jpg'));
    $type = $seo['type'] ?? 'website';
    $robots = $seo['robots'] ?? 'index,follow';
    $jsonld = $seo['jsonld'] ?? [];

    $address = array_filter([
        setting('address_line1'), setting('address_line2'),
        setting('state'), setting('pincode'), setting('country'),
    ]);
    $socials = array_values(array_filter([
        setting('instagram_url'), setting('facebook_url'), setting('youtube_url'), setting('x_url'),
    ]));

    $organization = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        '@id' => APP_URL . '/#organization',
        'name' => $siteName,
        'url' => APP_URL . '/',
        'slogan' => setting('slogan'),
    ];
    if ($socials !== []) {
        $organization['sameAs'] = $socials;
    }
    if (setting('support_phone') !== '') {
        $organization['telephone'] = setting('support_phone');
    }
    if ($address !== []) {
        $organization['address'] = [
            '@type' => 'PostalAddress',
            'streetAddress' => trim(setting('address_line1') . ' ' . setting('address_line2')),
            'addressLocality' => setting('address_line2', 'Varanasi'),
            'addressRegion' => setting('state'),
            'postalCode' => setting('pincode'),
            'addressCountry' => 'IN',
        ];
    }

    $website = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        '@id' => APP_URL . '/#website',
        'url' => APP_URL . '/',
        'name' => $siteName,
        'publisher' => ['@id' => APP_URL . '/#organization'],
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => ['@type' => 'EntryPoint', 'urlTemplate' => url('shop') . '?q={search_term_string}'],
            'query-input' => 'required name=search_term_string',
        ],
    ];

    $html = '<meta charset="utf-8">' . PHP_EOL
        . '<meta name="viewport" content="width=device-width, initial-scale=1">' . PHP_EOL
        . '<title>' . e($title) . '</title>' . PHP_EOL
        . '<meta name="description" content="' . e($description) . '">' . PHP_EOL
        . '<link rel="canonical" href="' . e($canonical) . '">' . PHP_EOL
        . '<meta name="robots" content="' . e($robots) . '">' . PHP_EOL
        . '<meta name="theme-color" content="#173F32">' . PHP_EOL
        . '<link rel="icon" href="' . e(url('assets/images/favicon.svg')) . '" type="image/svg+xml">' . PHP_EOL
        . '<meta property="og:site_name" content="' . e($siteName) . '">' . PHP_EOL
        . '<meta property="og:title" content="' . e($title) . '">' . PHP_EOL
        . '<meta property="og:description" content="' . e($description) . '">' . PHP_EOL
        . '<meta property="og:type" content="' . e($type === 'product' ? 'product' : 'website') . '">' . PHP_EOL
        . '<meta property="og:url" content="' . e($canonical) . '">' . PHP_EOL
        . '<meta property="og:image" content="' . e($image) . '">' . PHP_EOL
        . '<meta name="twitter:card" content="summary_large_image">' . PHP_EOL
        . '<meta name="twitter:title" content="' . e($title) . '">' . PHP_EOL
        . '<meta name="twitter:description" content="' . e($description) . '">' . PHP_EOL
        . '<meta name="twitter:image" content="' . e($image) . '">' . PHP_EOL
        . '<link rel="preconnect" href="https://fonts.googleapis.com">' . PHP_EOL
        . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . PHP_EOL
        . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">' . PHP_EOL
        . '<link rel="stylesheet" href="' . e(url('assets/css/main.css')) . '">' . PHP_EOL;

    if (isset($seo['preload_image'])) {
        $html .= '<link rel="preload" as="image" href="' . e(asset_url($seo['preload_image'])) . '" fetchpriority="high">' . PHP_EOL;
    }

    $blocks = array_merge([$organization, $website], array_values($jsonld));
    foreach ($blocks as $block) {
        $html .= '<script type="application/ld+json">' . safe_json($block) . '</script>' . PHP_EOL;
    }
    return $html;
}

function breadcrumb_jsonld(array $items): array
{
    $elements = [];
    foreach (array_values($items) as $i => $item) {
        $entry = [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $item['label'],
        ];
        if (!empty($item['href'])) {
            $entry['item'] = url(ltrim($item['href'], '/'));
        }
        $elements[] = $entry;
    }
    return [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $elements,
    ];
}

function breadcrumbs_html(array $items): string
{
    if ($items === []) {
        return '';
    }
    $out = '<nav class="breadcrumbs" aria-label="Breadcrumb"><ol>';
    $last = count($items) - 1;
    foreach (array_values($items) as $i => $item) {
        $out .= '<li>';
        if ($i !== $last && !empty($item['href'])) {
            $out .= '<a href="' . e(url(ltrim($item['href'], '/'))) . '">' . e($item['label']) . '</a>';
        } else {
            $out .= '<span aria-current="page">' . e($item['label']) . '</span>';
        }
        if ($i !== $last) {
            $out .= '<span class="crumb-sep" aria-hidden="true">/</span>';
        }
        $out .= '</li>';
    }
    return $out . '</ol></nav>';
}

function product_jsonld(array $product): array
{
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product['name'],
        'description' => excerpt($product['short_description'] ?? $product['description'] ?? '', 300),
        'url' => url('products/' . $product['slug']),
        'brand' => ['@type' => 'Brand', 'name' => setting('site_name', 'Daily Dose Export')],
    ];
    if (!empty($product['primary_image'])) {
        $data['image'] = [asset_url($product['primary_image'])];
    }
    if (!empty($product['default_sku'])) {
        $data['sku'] = $product['default_sku'];
    }
    $prices = [];
    foreach (($product['variants'] ?? []) as $variant) {
        if ($variant['price'] !== null) {
            $prices[] = (float) $variant['price'];
        }
    }
    if ($prices === [] && $product['min_price'] !== null) {
        $prices[] = (float) $product['min_price'];
    }
    if ($prices !== []) {
        $inStock = (int) ($product['stock_total'] ?? 0) > 0;
        $low = min($prices);
        $high = max($prices);
        $offer = [
            'url' => url('products/' . $product['slug']),
            'priceCurrency' => 'INR',
            'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'seller' => ['@type' => 'Organization', 'name' => setting('site_name', 'Daily Dose Export')],
        ];
        if (count($prices) > 1 && $high > $low) {
            $data['offers'] = array_merge(['@type' => 'AggregateOffer', 'offerCount' => count($prices)], $offer, [
                'lowPrice' => number_format($low, 2, '.', ''),
                'highPrice' => number_format($high, 2, '.', ''),
            ]);
        } else {
            $data['offers'] = array_merge(['@type' => 'Offer', 'price' => number_format($low, 2, '.', '')], $offer);
        }
    }
    if (!empty($product['review_count']) && (int) $product['review_count'] > 0) {
        $data['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => number_format((float) $product['rating_avg'], 1, '.', ''),
            'reviewCount' => (int) $product['review_count'],
        ];
    }
    return $data;
}

function web_page_jsonld(string $name, string $description, string $url): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $name,
        'description' => $description,
        'url' => $url,
        'isPartOf' => ['@id' => APP_URL . '/#website'],
    ];
}
