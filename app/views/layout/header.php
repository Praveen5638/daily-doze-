<?php
declare(strict_types=1);

$seo = $seo ?? [];
if (isset($jsonld) && is_array($jsonld)) {
    $seo['jsonld'] = array_merge($seo['jsonld'] ?? [], [$jsonld]);
}
$activeNav = $activeNav ?? '';
$bodyClass = $bodyClass ?? '';
$navCategories = $navCategories ?? Catalog::categories();
$wishlistIds = $wishlistIds ?? (Auth::customerId() ? Catalog::wishlist_ids((int) Auth::customerId()) : []);
$customer = Auth::customer();
$cartCount = Cart::count();
$wishlistCount = count($wishlistIds);
$announcement = localized_setting('announcement_text');
$searchQuery = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$locale = current_locale();
$switchLocale = $locale === 'hi' ? 'en' : 'hi';
$langSwitchLabel = $locale === 'hi' ? t('lang.switch_en', 'Switch to English') : t('lang.switch_hi', 'Switch to Hindi');
$langCode = $locale === 'hi' ? 'EN' : 'हिं';
$navItems = [
    ['key' => 'home', 'label' => t('nav.home', 'Home'), 'href' => ''],
    ['key' => 'shop', 'label' => t('nav.shop', 'Shop'), 'href' => 'shop.php'],
    ['key' => 'about', 'label' => t('nav.about', 'About'), 'href' => 'about.php'],
    ['key' => 'wholesale', 'label' => t('nav.wholesale', 'Wholesale'), 'href' => 'wholesale.php'],
    ['key' => 'export', 'label' => t('nav.export', 'Export'), 'href' => 'export-enquiry.php'],
    ['key' => 'contact', 'label' => t('nav.contact', 'Contact'), 'href' => 'contact.php'],
];
?><!doctype html>
<html lang="<?= e($locale) ?>" class="no-js" data-theme-dark-label="<?= e(t('theme.to_dark', 'Switch to dark theme')) ?>" data-theme-light-label="<?= e(t('theme.to_light', 'Switch to light theme')) ?>">
<head>
<script>
(function () {
  var root = document.documentElement;
  try {
    var saved = localStorage.getItem('dde-theme');
    var theme = saved === 'dark' || saved === 'light' ? saved : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    root.setAttribute('data-theme', theme);
  } catch (e) {
    root.setAttribute('data-theme', 'light');
  }
})();
</script>
<?= render_seo_head($seo) ?>
<meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
<script>document.documentElement.classList.remove('no-js');</script>
</head>
<body class="page-<?= e($bodyClass) ?>">
<a class="skip-link" href="#main"><?= e(t('common.skip', 'Skip to content')) ?></a>

<?php if ($announcement !== ''): ?>
  <div class="announcement">
    <div class="container announcement-inner">
      <?php if (setting('announcement_link') !== ''): ?>
        <a href="<?= e(setting('announcement_link')) ?>"><?= e($announcement) ?> <span aria-hidden="true">&rarr;</span></a>
      <?php else: ?>
        <span><?= e($announcement) ?></span>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<header class="site-header" data-header>
  <div class="container header-inner">
    <button class="icon-btn menu-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="mobile-nav" aria-label="<?= e(t('nav.open_menu', 'Open menu')) ?>">
      <?= icon('menu') ?>
    </button>

    <a class="brand" href="<?= e(url('')) ?>" aria-label="<?= e(t('nav.home_aria', 'Daily Dose Export home')) ?>">
      <span class="brand-mark"><?= icon('leaf') ?></span>
      <span class="brand-text">
        <span class="brand-name">Daily Dose</span>
        <span class="brand-sub">Export</span>
      </span>
    </a>

    <nav class="primary-nav" aria-label="<?= e(t('nav.primary', 'Primary navigation')) ?>">
      <?php foreach ($navItems as $item): ?>
        <?php if ($item['key'] === 'shop'): ?>
          <div class="nav-group">
            <a class="nav-link <?= $activeNav === 'shop' ? 'is-active' : '' ?>" href="<?= e(url('shop.php')) ?>"><?= e($item['label']) ?></a>
            <div class="nav-dropdown">
              <a href="<?= e(url('shop.php')) ?>"><?= e(t('nav.all_products', 'All products')) ?></a>
              <?php foreach ($navCategories as $cat): ?>
                <a href="<?= e(url('category/' . $cat['slug'])) ?>"><?= e(category_label($cat)) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php else: ?>
          <a class="nav-link <?= $activeNav === $item['key'] ? 'is-active' : '' ?>" href="<?= e(url($item['href'])) ?>"><?= e($item['label']) ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>

    <div class="header-actions">
      <button class="icon-btn theme-toggle header-utility" type="button" data-theme-toggle aria-label="<?= e(t('theme.to_dark', 'Switch to dark theme')) ?>" title="<?= e(t('theme.to_dark', 'Switch to dark theme')) ?>">
        <span class="theme-icon theme-icon-sun"><?= icon('sun') ?></span>
        <span class="theme-icon theme-icon-moon"><?= icon('moon') ?></span>
      </button>
      <a class="icon-btn lang-toggle header-utility" href="<?= e(language_url($switchLocale)) ?>" rel="alternate" hreflang="<?= e($switchLocale) ?>" lang="<?= e($switchLocale) ?>" aria-label="<?= e($langSwitchLabel) ?>" title="<?= e($langSwitchLabel) ?>">
        <span class="lang-code" aria-hidden="true"><?= e($langCode) ?></span>
      </a>
      <button class="icon-btn search-toggle" type="button" data-search-toggle aria-expanded="false" aria-controls="site-search" aria-label="<?= e(t('common.search', 'Search products')) ?>">
        <?= icon('search') ?>
      </button>
      <a class="icon-btn header-account" href="<?= e($customer ? url('account.php') : url('login.php')) ?>" aria-label="<?= $customer ? e(t('nav.account', 'My account')) : e(t('nav.login', 'Log in')) ?>">
        <?= icon('user') ?>
        <span class="action-label"><?= $customer ? e(t('nav.account_short', 'Account')) : e(t('nav.login', 'Log in')) ?></span>
      </a>
      <a class="icon-btn header-wishlist" href="<?= e($customer ? url('account.php?tab=wishlist') : url('login.php?next=account.php%3Ftab%3Dwishlist')) ?>" aria-label="<?= e(t('nav.wishlist', 'Wishlist')) ?>">
        <?= icon('heart') ?>
        <span class="count-badge <?= $wishlistCount === 0 ? 'is-empty' : '' ?>" data-wishlist-count><?= $wishlistCount ?></span>
      </a>
      <a class="icon-btn header-cart" href="<?= e(url('cart.php')) ?>" aria-label="<?= e(t('nav.cart', 'Shopping cart')) ?>">
        <?= icon('cart') ?>
        <span class="count-badge <?= $cartCount === 0 ? 'is-empty' : '' ?>" data-cart-count><?= $cartCount ?></span>
      </a>
    </div>
  </div>

  <div class="search-panel" id="site-search" data-search-panel hidden>
    <div class="container">
      <form class="search-form" action="<?= e(url('shop.php')) ?>" method="get" role="search">
        <label class="sr-only" for="site-search-input"><?= e(t('common.search', 'Search products')) ?></label>
        <span class="search-icon"><?= icon('search') ?></span>
        <input id="site-search-input" type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="<?= e(t('search.placeholder', 'Search turmeric, ragi, dates…')) ?>" autocomplete="off" maxlength="100">
        <button class="btn btn-primary" type="submit"><?= e(t('common.search_button', 'Search')) ?></button>
      </form>
      <p class="search-hint"><?= e(t('search.hint', 'Search in English or Hindi — for example, haldi, dhaniya, khajoor or ragi.')) ?></p>
    </div>
  </div>
</header>

<nav class="mobile-nav" id="mobile-nav" aria-label="<?= e(t('nav.mobile', 'Mobile navigation')) ?>" hidden>
  <div class="mobile-nav-head">
    <span class="mobile-nav-title"><?= e(t('nav.menu', 'Menu')) ?></span>
    <button class="icon-btn" type="button" data-nav-close aria-label="<?= e(t('nav.close_menu', 'Close menu')) ?>"><?= icon('close') ?></button>
  </div>
  <div class="mobile-nav-body">
    <?php foreach ($navItems as $item): ?>
      <a class="mobile-nav-link <?= $activeNav === $item['key'] ? 'is-active' : '' ?>" href="<?= e(url($item['href'])) ?>"><?= e($item['label']) ?></a>
    <?php endforeach; ?>
    <div class="mobile-nav-tools">
      <button class="mobile-tool" type="button" data-theme-toggle aria-label="<?= e(t('theme.to_dark', 'Switch to dark theme')) ?>">
        <span class="theme-icon theme-icon-sun"><?= icon('sun') ?></span>
        <span class="theme-icon theme-icon-moon"><?= icon('moon') ?></span>
        <span class="mobile-tool-label"><?= e(t('theme.label', 'Theme')) ?></span>
      </button>
      <a class="mobile-tool" href="<?= e(language_url($switchLocale)) ?>" rel="alternate" hreflang="<?= e($switchLocale) ?>" lang="<?= e($switchLocale) ?>">
        <span class="lang-code"><?= e($langCode) ?></span>
        <span class="mobile-tool-label"><?= $locale === 'hi' ? 'English' : 'हिंदी' ?></span>
      </a>
    </div>
    <p class="mobile-nav-heading"><?= e(t('nav.shop_category', 'Shop by category')) ?></p>
    <?php foreach ($navCategories as $cat): ?>
      <a class="mobile-nav-link is-sub" href="<?= e(url('category/' . $cat['slug'])) ?>"><?= e(category_label($cat)) ?></a>
    <?php endforeach; ?>
    <div class="mobile-nav-cta">
      <?php if ($customer): ?>
        <a class="btn btn-secondary" href="<?= e(url('account.php')) ?>"><?= e(t('nav.account', 'My account')) ?></a>
      <?php else: ?>
        <a class="btn btn-secondary" href="<?= e(url('login.php')) ?>"><?= e(t('nav.login', 'Log in')) ?></a>
        <a class="btn btn-ghost" href="<?= e(url('register.php')) ?>"><?= e(t('nav.register', 'Create account')) ?></a>
      <?php endif; ?>
    </div>
    <a class="mobile-nav-phone" href="tel:<?= e(preg_replace('/\s+/', '', setting('support_phone'))) ?>"><?= icon('phone') ?> <?= e(setting('support_phone')) ?></a>
  </div>
</nav>
<div class="nav-backdrop" data-nav-backdrop hidden></div>

<?php $flashItems = flashes(); ?>
<?php if ($flashItems !== []): ?>
  <div class="container flash-wrap" aria-live="polite">
    <?php foreach ($flashItems as $flash): ?>
      <div class="flash flash-<?= e($flash['type']) ?>">
        <span class="flash-icon"><?= icon($flash['type'] === 'success' ? 'check' : ($flash['type'] === 'error' ? 'alert' : 'leaf')) ?></span>
        <span><?= e($flash['message']) ?></span>
        <button class="flash-close" type="button" aria-label="Dismiss" onclick="this.parentElement.remove()"><?= icon('close') ?></button>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<main id="main" class="site-main">
