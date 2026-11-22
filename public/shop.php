<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$categorySlug = isset($_GET['category']) && is_string($_GET['category']) ? trim($_GET['category']) : '';
$activeCategory = $categorySlug !== '' ? Catalog::category_by_slug($categorySlug) : null;
if ($categorySlug !== '' && $activeCategory === null) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$sortOptions = [
    'featured' => t('shop.sort_featured', 'Featured'),
    'newest' => t('shop.sort_newest', 'Newest'),
    'price_asc' => t('shop.sort_price_asc', 'Price: low to high'),
    'price_desc' => t('shop.sort_price_desc', 'Price: high to low'),
    'name' => t('shop.sort_name', 'Name A–Z'),
    'popular' => t('shop.sort_popular', 'Most popular'),
];
$requestedSort = isset($_GET['sort']) && is_string($_GET['sort']) ? $_GET['sort'] : 'featured';
$sort = isset($sortOptions[$requestedSort]) ? $requestedSort : 'featured';

$minPrice = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? max(0, (float) $_GET['min_price']) : '';
$maxPrice = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? max(0, (float) $_GET['max_price']) : '';
$pack = isset($_GET['pack']) && is_string($_GET['pack']) ? trim($_GET['pack']) : '';
$type = isset($_GET['type']) && is_string($_GET['type']) ? trim($_GET['type']) : '';
$availability = isset($_GET['availability']) && $_GET['availability'] === 'in_stock' ? 'in_stock' : '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$q = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 100) : '';

$filters = [
    'q' => $q,
    'category' => $categorySlug,
    'min_price' => $minPrice,
    'max_price' => $maxPrice,
    'pack' => $pack,
    'type' => $type,
    'availability' => $availability,
    'sort' => $sort,
    'page' => $page,
];

$categories = Catalog::categories();
$packSizes = Catalog::pack_sizes();
$productTypes = Catalog::product_types();
$typeLabel = static fn (string $value): string => t('type.' . $value, ucwords(str_replace(['-', '_'], ' ', $value)));
$priceBounds = Catalog::price_bounds();
$hasPriceRange = $priceBounds['min_price'] !== null;
$result = Catalog::search($filters);
$products = $result['items'];

$categoryName = $activeCategory !== null ? category_label($activeCategory) : t('shop.all_products', 'All products');
$pageTitle = $q !== '' ? t('shop.search_results', 'Search results') . ' — ' . $q : $categoryName;
$description = $activeCategory['description'] ?? 'Browse natural Indian spices, grains, millets, cookies, teas, pink salt and dry fruits from Daily Dose Export.';
if ($activeCategory !== null && mb_strlen($description) < 100) {
    $description = trim($description . ' ' . sprintf(
        t('shop.meta_category', 'Browse %1$d %2$s products from Daily Dose Export, Varanasi.'),
        (int) $result['total'],
        $categoryName
    ));
}

$isFiltered = $q !== '' || $minPrice !== '' || $maxPrice !== '' || $pack !== '' || $type !== '' || $availability !== '';
if ($activeCategory !== null) {
    $canonical = url('category/' . $activeCategory['slug']);
} else {
    $canonical = url('shop.php');
}
if ($page > 1) {
    $canonical .= (str_contains($canonical, '?') ? '&' : '?') . 'page=' . $page;
}
$seo = [
    'title' => ($q !== '' ? t('shop.search_results', 'Search results') . ': ' . $q : $categoryName) . ' | ' . setting('site_name', 'Daily Dose Export'),
    'description' => excerpt($description, 180),
    'canonical' => $canonical,
    'robots' => ($isFiltered || $page > 1) ? 'noindex,follow' : 'index,follow',
    'jsonld' => [
        breadcrumb_jsonld($activeCategory !== null
            ? [['label' => t('nav.home', 'Home'), 'href' => ''], ['label' => t('nav.shop', 'Shop'), 'href' => 'shop.php'], ['label' => $activeCategory['name'], 'href' => 'category/' . $activeCategory['slug']]]
            : [['label' => t('nav.home', 'Home'), 'href' => ''], ['label' => t('nav.shop', 'Shop'), 'href' => 'shop.php']]),
    ],
];
if (!$isFiltered) {
    $seo['jsonld'][] = collection_jsonld($categoryName, $seo['description'], $canonical, $products);
}
$activeNav = 'shop';
$bodyClass = 'shop';
$wishlistIds = Auth::customerId() ? Catalog::wishlist_ids((int) Auth::customerId()) : [];

$queryForPagination = array_filter([
    'q' => $q,
    'category' => $categorySlug,
    'min_price' => $minPrice,
    'max_price' => $maxPrice,
    'pack' => $pack,
    'type' => $type,
    'availability' => $availability,
    'sort' => $sort !== 'featured' ? $sort : '',
], static fn ($v) => $v !== '' && $v !== null);

$filterUrl = static function (array $overrides = []) use ($queryForPagination): string {
    $query = array_merge($queryForPagination, $overrides);
    $query = array_filter($query, static fn ($v) => $v !== '' && $v !== null);
    $qs = http_build_query($query);
    return url('shop.php') . ($qs !== '' ? '?' . $qs : '');
};

require BASE_PATH . '/app/views/layout/header.php';
?>

<section class="page-hero">
  <div class="container">
    <?= breadcrumbs_html($activeCategory !== null
        ? [['label' => t('nav.home', 'Home'), 'href' => ''], ['label' => t('nav.shop', 'Shop'), 'href' => 'shop.php'], ['label' => category_label($activeCategory)]]
        : [['label' => t('nav.home', 'Home'), 'href' => ''], ['label' => t('nav.shop', 'Shop')]]) ?>
    <h1><?= e($q !== '' ? t('shop.search_results', 'Search results') : $categoryName) ?></h1>
    <p class="page-lead">
      <?php if ($q !== ''): ?>
        <?= e(t('shop.showing_for', 'Showing products matching')) ?> “<?= e($q) ?>”. <?= e(t('shop.search_hint', 'Search by English or Hindi name — haldi, dhaniya, khajoor, ragi and more.')) ?>
      <?php else: ?>
        <?= e($description) ?> <?= e(t('shop.confirm_note', 'Availability and pack details are confirmed by our team.')) ?>
      <?php endif; ?>
    </p>
  </div>
</section>

<div class="container">
  <div class="shop-layout">
    <aside class="filter-card" id="shop-filters" aria-label="<?= e(t('shop.filters', 'Product filters')) ?>">
      <div class="filter-head">
        <h2><?= e(t('shop.filters', 'Filters')) ?></h2>
        <div style="display:flex;gap:10px;align-items:center">
          <?php if ($isFiltered || $categorySlug !== ''): ?>
            <a class="filter-clear" href="<?= e(url('shop.php')) ?>"><?= e(t('shop.clear_all', 'Clear all')) ?></a>
          <?php endif; ?>
          <button class="icon-btn filter-close" type="button" data-filter-close aria-label="<?= e(t('shop.close_filters', 'Close filters')) ?>"><?= icon('close') ?></button>
        </div>
      </div>

      <form action="<?= e(url('shop.php')) ?>" method="get">
        <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
        <?php if ($sort !== 'featured'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>

        <div class="filter-section">
          <h3><?= e(t('shop.category', 'Category')) ?></h3>
          <label class="filter-option">
            <input type="radio" name="category" value="" <?= $categorySlug === '' ? 'checked' : '' ?>>
            <span><?= e(t('shop.all_products', 'All products')) ?></span>
          </label>
          <?php foreach ($categories as $cat): ?>
            <label class="filter-option">
              <input type="radio" name="category" value="<?= e($cat['slug']) ?>" <?= $categorySlug === $cat['slug'] ? 'checked' : '' ?>>
              <span><?= e(category_label($cat)) ?></span>
              <span class="count"><?= (int) $cat['product_count'] ?></span>
            </label>
          <?php endforeach; ?>
        </div>

        <?php if ($productTypes !== []): ?>
          <div class="filter-section">
            <h3><?= e(t('shop.product_type', 'Product type')) ?></h3>
            <label class="filter-option">
              <input type="radio" name="type" value="" <?= $type === '' ? 'checked' : '' ?>>
              <span><?= e(t('shop.any_type', 'Any type')) ?></span>
            </label>
            <?php foreach ($productTypes as $typeOption): ?>
              <label class="filter-option">
                <input type="radio" name="type" value="<?= e($typeOption) ?>" <?= $type === $typeOption ? 'checked' : '' ?>>
                <span><?= e($typeLabel($typeOption)) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($hasPriceRange): ?>
          <div class="filter-section">
            <h3><?= e(t('shop.price', 'Price (₹)')) ?></h3>
            <div class="price-inputs">
              <input type="number" name="min_price" min="0" step="1" placeholder="<?= e((string) (int) floor((float) $priceBounds['min_price'])) ?>" value="<?= $minPrice === '' ? '' : e((string) $minPrice) ?>" aria-label="<?= e(t('shop.min_price', 'Minimum price')) ?>">
              <span class="muted">—</span>
              <input type="number" name="max_price" min="0" step="1" placeholder="<?= e((string) (int) ceil((float) $priceBounds['max_price'])) ?>" value="<?= $maxPrice === '' ? '' : e((string) $maxPrice) ?>" aria-label="<?= e(t('shop.max_price', 'Maximum price')) ?>">
            </div>
          </div>
        <?php endif; ?>

        <?php if ($packSizes !== []): ?>
          <div class="filter-section">
            <h3><?= e(t('shop.pack_size', 'Pack size')) ?></h3>
            <label class="filter-option">
              <input type="radio" name="pack" value="" <?= $pack === '' ? 'checked' : '' ?>>
              <span><?= e(t('shop.any_pack', 'Any pack')) ?></span>
            </label>
            <?php foreach ($packSizes as $size): ?>
              <label class="filter-option">
                <input type="radio" name="pack" value="<?= e($size) ?>" <?= $pack === $size ? 'checked' : '' ?>>
                <span><?= e($size) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="filter-section">
          <h3><?= e(t('shop.availability', 'Availability')) ?></h3>
          <label class="filter-option">
            <input type="checkbox" name="availability" value="in_stock" <?= $availability === 'in_stock' ? 'checked' : '' ?>>
            <span><?= e(t('shop.in_stock_only', 'In stock only')) ?></span>
          </label>
        </div>

        <div class="filter-actions">
          <button class="btn btn-primary" type="submit"><?= e(t('shop.apply_filters', 'Apply filters')) ?></button>
        </div>
      </form>
    </aside>

    <div class="shop-content">
      <div class="shop-toolbar">
        <button class="btn btn-ghost btn-sm filter-toggle" type="button" data-filter-toggle aria-expanded="false" aria-controls="shop-filters">
          <?= icon('filter') ?> <?= e(t('shop.filters', 'Filters')) ?>
        </button>
        <p class="shop-count"><strong><?= (int) $result['total'] ?></strong> <?= (int) $result['total'] === 1 ? e(t('shop.result', 'product')) : e(t('shop.results', 'products')) ?><?= $q !== '' ? ' ' . e(t('shop.for_query', 'for')) . ' “' . e($q) . '”' : '' ?></p>
        <form class="shop-sort" action="<?= e(url('shop.php')) ?>" method="get">
          <?php foreach ($queryForPagination as $key => $value): ?>
            <?php if ($key !== 'sort'): ?><input type="hidden" name="<?= e($key) ?>" value="<?= e((string) $value) ?>"><?php endif; ?>
          <?php endforeach; ?>
          <label for="sort"><?= e(t('shop.sort', 'Sort by')) ?></label>
          <select id="sort" name="sort" data-auto-submit>
            <?php foreach ($sortOptions as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <noscript><button class="btn btn-ghost btn-sm" type="submit"><?= e(t('shop.sort', 'Sort by')) ?></button></noscript>
        </form>
      </div>

      <?php
      $activeChips = [];
      if ($q !== '') {
          $activeChips[] = ['label' => t('shop.chip_search', 'Search: %s') ? sprintf(t('shop.chip_search', 'Search: %s'), $q) : 'Search: ' . $q, 'remove' => ['q' => '', 'page' => '']];
      }
      if ($activeCategory !== null) {
          $activeChips[] = ['label' => category_label($activeCategory), 'remove' => ['category' => '', 'page' => '']];
      }
      if ($minPrice !== '' || $maxPrice !== '') {
          $activeChips[] = ['label' => sprintf(t('shop.chip_price', 'Price: %s – %s'), ($minPrice !== '' ? money($minPrice) : t('shop.any', 'Any')), ($maxPrice !== '' ? money($maxPrice) : t('shop.any', 'Any'))), 'remove' => ['min_price' => '', 'max_price' => '', 'page' => '']];
      }
      if ($pack !== '') {
          $activeChips[] = ['label' => sprintf(t('shop.chip_pack', 'Pack: %s'), $pack), 'remove' => ['pack' => '', 'page' => '']];
      }
      if ($type !== '') {
          $activeChips[] = ['label' => sprintf(t('shop.chip_type', 'Type: %s'), $typeLabel($type)), 'remove' => ['type' => '', 'page' => '']];
      }
      if ($availability !== '') {
          $activeChips[] = ['label' => t('shop.in_stock_only', 'In stock only'), 'remove' => ['availability' => '', 'page' => '']];
      }
      ?>
      <?php if ($activeChips !== []): ?>
        <div class="active-chips">
          <?php foreach ($activeChips as $chip): ?>
            <span class="chip"><?= e($chip['label']) ?> <a href="<?= e($filterUrl($chip['remove'])) ?>" aria-label="<?= e(t('shop.remove_filter', 'Remove filter')) ?>"><?= icon('close') ?></a></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($products === []): ?>
        <div class="shop-empty">
          <?= icon('search', 'icon') ?>
          <h3><?= e(t('shop.empty_title', 'No products matched')) ?></h3>
          <p><?= e(t('shop.empty_text', 'Try a different search term, adjust the filters, or browse the full shop.')) ?></p>
          <div class="btn-row" style="justify-content:center">
            <a class="btn btn-primary" href="<?= e(url('shop.php')) ?>"><?= e(t('shop.browse_all', 'Browse all products')) ?></a>
            <a class="btn btn-ghost" href="<?= e(url('contact.php')) ?>"><?= e(t('shop.ask_availability', 'Ask us for availability')) ?></a>
          </div>
        </div>
      <?php else: ?>
        <div class="product-grid">
          <?php foreach ($products as $product): ?>
            <?php require BASE_PATH . '/app/views/partials/product-card.php'; ?>
          <?php endforeach; ?>
        </div>
        <?= pagination_html((int) $result['page'], (int) $result['pages'], $queryForPagination) ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<section class="section-tight">
  <div class="container">
    <div class="alert"><?= e(t('shop.help', 'Looking for a specific grade, pack size or bulk quantity?')) ?> <a href="<?= e(url('contact.php')) ?>"><?= e(t('shop.help_link', 'Send us a message')) ?></a> <?= e(t('shop.help_or', 'or call')) ?> <a href="tel:<?= e(preg_replace('/\s+/', '', setting('support_phone'))) ?>"><?= e(setting('support_phone')) ?></a>.</div>
  </div>
</section>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
