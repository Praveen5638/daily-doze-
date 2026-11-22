<?php
declare(strict_types=1);

function render_seo_head(array $seo = []): string
{
    $siteName = setting('site_name', 'Daily Dose Export');
    $title = trim((string) ($seo['title'] ?? ''));
    if ($title === '') {
        $title = $siteName . ' | ' . localized_setting('tagline', 'Pure From Farm To World');
    }
    $description = trim((string) ($seo['description'] ?? ''));
    if ($description === '') {
        $description = localized_setting('default_meta_description');
    }
    if ($description === '') {
        $description = localized_setting('slogan');
    }

    $canonicalBase = $seo['canonical'] ?? null;
    if (!is_string($canonicalBase) || trim($canonicalBase) === '') {
        $canonicalBase = url(ltrim(current_path(), '/'));
    }
    $explicitLocale = requested_locale();
    $canonical = $explicitLocale !== null ? locale_variant_url($canonicalBase, $explicitLocale) : $canonicalBase;

    $image = asset_url($seo['image'] ?? setting('og_image', 'assets/images/hero.jpg'));
    $type = (string) ($seo['type'] ?? 'website');
    $robots = (string) ($seo['robots'] ?? 'index,follow');
    $indexable = !str_contains($robots, 'noindex');
    if ($indexable) {
        foreach (['max-image-preview:large', 'max-snippet:-1', 'max-video-preview:-1'] as $directive) {
            if (!str_contains($robots, $directive)) {
                $robots .= ',' . $directive;
            }
        }
    }

    $jsonld = $seo['jsonld'] ?? [];
    if (!is_array($jsonld)) {
        $jsonld = [];
    }

    $pageLocale = $explicitLocale ?? current_locale();
    $ogLocale = $pageLocale === 'hi' ? 'hi_IN' : 'en_IN';
    $ogAlternate = $pageLocale === 'hi' ? 'en_IN' : 'hi_IN';

    $street = trim(setting('address_line1'));
    $locality = trim(setting('address_line2'));
    $region = trim(setting('state'));
    $postal = trim(setting('pincode'));
    $addressData = [];
    if ($street !== '') {
        $addressData['streetAddress'] = $street;
    }
    if ($locality !== '') {
        $addressData['addressLocality'] = $locality;
    }
    if ($region !== '') {
        $addressData['addressRegion'] = $region;
    }
    if ($postal !== '') {
        $addressData['postalCode'] = $postal;
    }
    if ($addressData !== []) {
        $addressData = ['@type' => 'PostalAddress'] + $addressData + ['addressCountry' => 'IN'];
    }
    $socials = array_values(array_filter([
        setting('instagram_url'), setting('facebook_url'), setting('youtube_url'), setting('x_url'),
    ]));

    $organization = [
        '@context' => 'https://schema.org',
        '@type' => ['Organization', 'LocalBusiness'],
        '@id' => APP_URL . '/#organization',
        'name' => $siteName,
        'url' => APP_URL . '/',
        'logo' => asset_url('assets/images/favicon.svg'),
    ];
    $orgDescription = localized_setting('default_meta_description');
    if ($orgDescription !== '') {
        $organization['description'] = $orgDescription;
    }
    if (trim(setting('legal_entity')) !== '') {
        $organization['legalName'] = trim(setting('legal_entity'));
    }
    if (trim(localized_setting('slogan')) !== '') {
        $organization['slogan'] = localized_setting('slogan');
    }
    if ($addressData !== []) {
        $organization['address'] = $addressData;
    }
    if (setting('support_phone') !== '') {
        $organization['telephone'] = setting('support_phone');
    }
    if (setting('contact_email') !== '') {
        $organization['email'] = setting('contact_email');
    }
    if (setting('support_phone') !== '' || setting('contact_email') !== '') {
        $contactPoint = [
            '@type' => 'ContactPoint',
            'contactType' => 'customer service',
            'areaServed' => 'IN',
            'availableLanguage' => ['English', 'Hindi'],
        ];
        if (setting('support_phone') !== '') {
            $contactPoint['telephone'] = setting('support_phone');
        }
        if (setting('contact_email') !== '') {
            $contactPoint['email'] = setting('contact_email');
        }
        $organization['contactPoint'] = [$contactPoint];
    }
    if ($socials !== []) {
        $organization['sameAs'] = $socials;
    }
    if (trim(setting('gstin')) !== '') {
        $organization['taxID'] = trim(setting('gstin'));
    }
    if (trim(setting('fssai_number')) !== '') {
        $organization['identifier'] = [
            '@type' => 'PropertyValue',
            'name' => 'FSSAI licence',
            'value' => trim(setting('fssai_number')),
        ];
    }
    $organization['currenciesAccepted'] = setting('currency', 'INR');
    $organization['areaServed'] = ['@type' => 'Country', 'name' => 'India'];

    $website = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        '@id' => APP_URL . '/#website',
        'url' => APP_URL . '/',
        'name' => $siteName,
        'inLanguage' => ['en', 'hi'],
        'publisher' => ['@id' => APP_URL . '/#organization'],
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => ['@type' => 'EntryPoint', 'urlTemplate' => url('shop.php') . '?q={search_term_string}'],
            'query-input' => 'required name=search_term_string',
        ],
    ];
    if ($orgDescription !== '') {
        $website['description'] = $orgDescription;
    }

    $fontFamilies = 'family=Fraunces:wght@400;500;600;700&family=Manrope:wght@400;500;600;700';
    if (current_locale() === 'hi') {
        $fontFamilies .= '&family=Noto+Sans+Devanagari:wght@400;500;600&family=Noto+Serif+Devanagari:wght@500;600;700';
    }

    $html = '<meta charset="utf-8">' . PHP_EOL
        . '<meta name="viewport" content="width=device-width, initial-scale=1">' . PHP_EOL
        . '<meta name="color-scheme" content="light dark">' . PHP_EOL
        . '<title>' . e($title) . '</title>' . PHP_EOL
        . '<meta name="description" content="' . e($description) . '">' . PHP_EOL
        . '<meta name="author" content="' . e($siteName) . '">' . PHP_EOL
        . '<meta name="application-name" content="' . e($siteName) . '">' . PHP_EOL
        . '<link rel="canonical" href="' . e($canonical) . '">' . PHP_EOL;
    if ($indexable) {
        foreach (hreflang_links($canonicalBase) as $alternate) {
            $html .= '<link rel="alternate" hreflang="' . e($alternate['hreflang']) . '" href="' . e($alternate['href']) . '">' . PHP_EOL;
        }
    }
    $html .= '<meta name="robots" content="' . e($robots) . '">' . PHP_EOL
        . '<meta name="theme-color" content="#173F32" data-theme-color>' . PHP_EOL
        . '<link rel="icon" href="' . e(url('assets/images/favicon.svg')) . '" type="image/svg+xml">' . PHP_EOL
        . '<meta property="og:site_name" content="' . e($siteName) . '">' . PHP_EOL
        . '<meta property="og:locale" content="' . e($ogLocale) . '">' . PHP_EOL
        . '<meta property="og:locale:alternate" content="' . e($ogAlternate) . '">' . PHP_EOL
        . '<meta property="og:title" content="' . e($title) . '">' . PHP_EOL
        . '<meta property="og:description" content="' . e($description) . '">' . PHP_EOL
        . '<meta property="og:type" content="' . e($type === 'product' ? 'product' : 'website') . '">' . PHP_EOL
        . '<meta property="og:url" content="' . e($canonical) . '">' . PHP_EOL
        . '<meta property="og:image" content="' . e($image) . '">' . PHP_EOL
        . '<meta property="og:image:alt" content="' . e($title) . '">' . PHP_EOL
        . '<meta name="twitter:card" content="summary_large_image">' . PHP_EOL
        . '<meta name="twitter:title" content="' . e($title) . '">' . PHP_EOL
        . '<meta name="twitter:description" content="' . e($description) . '">' . PHP_EOL
        . '<meta name="twitter:image" content="' . e($image) . '">' . PHP_EOL
        . '<meta name="twitter:image:alt" content="' . e($title) . '">' . PHP_EOL
        . '<link rel="preconnect" href="https://fonts.googleapis.com">' . PHP_EOL
        . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . PHP_EOL
        . '<link href="https://fonts.googleapis.com/css2?' . e($fontFamilies) . '&display=swap" rel="stylesheet">' . PHP_EOL
        . '<link rel="stylesheet" href="' . e(url('assets/css/main.css')) . '">' . PHP_EOL;

    if (isset($seo['preload_image'])) {
        $html .= '<link rel="preload" as="image" href="' . e(asset_url($seo['preload_image'])) . '" fetchpriority="high">' . PHP_EOL;
    }

    $blocks = array_merge([$organization, $website], array_values($jsonld));
    foreach ($blocks as $block) {
        if (!is_array($block) || $block === []) {
            continue;
        }
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
        } elseif ($i === 0) {
            $entry['item'] = APP_URL . '/';
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
    $out = '<nav class="breadcrumbs" aria-label="' . e(t('common.breadcrumb', 'Breadcrumb')) . '"><ol>';
    $last = count($items) - 1;
    foreach (array_values($items) as $i => $item) {
        $out .= '<li>';
        if ($i !== $last) {
            $href = (string) ($item['href'] ?? '');
            if ($href !== '' || $i === 0) {
                $out .= '<a href="' . e(url(ltrim($href, '/'))) . '">' . e($item['label']) . '</a>';
            } else {
                $out .= '<span>' . e($item['label']) . '</span>';
            }
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
    $siteName = setting('site_name', 'Daily Dose Export');
    $productUrl = url('products/' . $product['slug']);
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product['name'],
        'description' => excerpt($product['short_description'] ?? $product['description'] ?? '', 300),
        'url' => $productUrl,
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $productUrl],
        'brand' => ['@type' => 'Brand', 'name' => $siteName],
    ];
    $data['@id'] = $productUrl . '#product';
    if (!empty($product['category_name'])) {
        $data['category'] = $product['category_name'];
    }

    $images = [];
    foreach (($product['images'] ?? []) as $image) {
        if (!empty($image['image_path'])) {
            $images[] = asset_url($image['image_path']);
        }
    }
    if ($images === [] && !empty($product['primary_image'])) {
        $images[] = asset_url($product['primary_image']);
    }
    if ($images !== []) {
        $data['image'] = array_values(array_unique($images));
    }
    if (!empty($product['default_sku'])) {
        $data['sku'] = (string) $product['default_sku'];
    }

    $offers = [];
    $anyInStock = false;
    foreach (($product['variants'] ?? []) as $variant) {
        if ($variant['price'] === null) {
            continue;
        }
        $inStock = (int) ($variant['stock_qty'] ?? 0) > 0;
        $anyInStock = $anyInStock || $inStock;
        $offer = [
            '@type' => 'Offer',
            'url' => $productUrl,
            'priceCurrency' => 'INR',
            'price' => number_format((float) $variant['price'], 2, '.', ''),
            'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
            'seller' => ['@type' => 'Organization', '@id' => APP_URL . '/#organization'],
        ];
        if (!empty($variant['sku'])) {
            $offer['sku'] = (string) $variant['sku'];
        }
        $offers[] = $offer;
    }
    if ($offers === [] && $product['min_price'] !== null) {
        $inStock = (int) ($product['stock_total'] ?? 0) > 0;
        $anyInStock = $inStock;
        $offers[] = [
            '@type' => 'Offer',
            'url' => $productUrl,
            'priceCurrency' => 'INR',
            'price' => number_format((float) $product['min_price'], 2, '.', ''),
            'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
            'seller' => ['@type' => 'Organization', '@id' => APP_URL . '/#organization'],
        ];
    }
    if ($offers !== []) {
        if (count($offers) === 1) {
            $data['offers'] = $offers[0];
        } else {
            $prices = array_map(static fn (array $offer): float => (float) $offer['price'], $offers);
            $data['offers'] = [
                '@type' => 'AggregateOffer',
                'url' => $productUrl,
                'priceCurrency' => 'INR',
                'lowPrice' => number_format(min($prices), 2, '.', ''),
                'highPrice' => number_format(max($prices), 2, '.', ''),
                'offerCount' => count($offers),
                'availability' => $anyInStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => ['@type' => 'Organization', '@id' => APP_URL . '/#organization'],
            ];
        }
    }

    if (!empty($product['review_count']) && (int) $product['review_count'] > 0) {
        $data['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => number_format((float) $product['rating_avg'], 1, '.', ''),
            'reviewCount' => (int) $product['review_count'],
        ];
    }

    $reviews = $product['reviews'] ?? [];
    if (is_array($reviews) && $reviews !== []) {
        $reviewBlocks = [];
        foreach ($reviews as $review) {
            $rating = (int) ($review['rating'] ?? 0);
            if ($rating < 1 || $rating > 5) {
                continue;
            }
            $entry = [
                '@type' => 'Review',
                'author' => ['@type' => 'Person', 'name' => (string) ($review['name'] ?? 'Customer')],
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => $rating,
                    'bestRating' => 5,
                    'worstRating' => 1,
                ],
            ];
            if (!empty($review['title'])) {
                $entry['name'] = (string) $review['title'];
            }
            if (!empty($review['body'])) {
                $entry['reviewBody'] = excerpt((string) $review['body'], 600);
            }
            if (!empty($review['created_at'])) {
                $entry['datePublished'] = date('c', strtotime((string) $review['created_at']));
            }
            $reviewBlocks[] = $entry;
        }
        if ($reviewBlocks !== []) {
            $data['review'] = $reviewBlocks;
        }
    }

    return $data;
}

function web_page_jsonld(string $name, string $description, string $url, string $type = 'WebPage', array $speakable = []): array
{
    $data = [
        '@context' => 'https://schema.org',
        '@type' => $type,
        'name' => $name,
        'url' => $url,
        'isPartOf' => ['@id' => APP_URL . '/#website'],
        'inLanguage' => current_locale(),
    ];
    if ($description !== '') {
        $data['description'] = $description;
    }
    if ($speakable !== []) {
        $data['speakable'] = [
            '@type' => 'SpeakableSpecification',
            'cssSelector' => array_values($speakable),
        ];
    }
    return $data;
}

function item_list_jsonld(array $products, string $name = ''): array
{
    $items = [];
    $position = 1;
    foreach ($products as $product) {
        if (empty($product['slug']) || empty($product['name'])) {
            continue;
        }
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'url' => url('products/' . $product['slug']),
            'name' => $product['name'],
        ];
    }
    $data = [
        '@type' => 'ItemList',
        'numberOfItems' => count($items),
        'itemListElement' => $items,
    ];
    if ($name !== '') {
        $data['name'] = $name;
    }
    return $data;
}

function collection_jsonld(string $name, string $description, string $url, array $products = []): array
{
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $name,
        'url' => $url,
        'isPartOf' => ['@id' => APP_URL . '/#website'],
        'inLanguage' => current_locale(),
    ];
    if ($description !== '') {
        $data['description'] = $description;
    }
    if ($products !== []) {
        $data['mainEntity'] = item_list_jsonld($products, $name);
    }
    return $data;
}

function faq_jsonld_from_html(string $html): ?array
{
    if (trim($html) === '' || !preg_match_all('/<strong>(.+?)<\/strong>\s*<br\s*\/?>\s*(.+?)<\/p>/is', $html, $matches, PREG_SET_ORDER)) {
        return null;
    }
    $questions = [];
    foreach ($matches as $match) {
        $question = trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $answer = trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $answer = trim(preg_replace('/\s+/u', ' ', $answer) ?? '');
        if ($question === '' || $answer === '') {
            continue;
        }
        $questions[] = [
            '@type' => 'Question',
            'name' => $question,
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
        ];
    }
    if ($questions === []) {
        return null;
    }
    return [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $questions,
    ];
}
