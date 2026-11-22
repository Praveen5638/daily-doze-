<?php
declare(strict_types=1);

$seo = $seo ?? [];
$activeNav = $activeNav ?? '';
$bodyClass = $bodyClass ?? '';
$navCategories = $navCategories ?? Catalog::categories();
$wishlistIds = $wishlistIds ?? (Auth::customerId() ? Catalog::wishlist_ids((int) Auth::customerId()) : []);
$customer = Auth::customer();
$cartCount = Cart::count();
$wishlistCount = count($wishlistIds);
$announcement = setting('announcement_text');
$searchQuery = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$navItems = [
    ['key' => 'home', 'label' => 'Home', 'href' => ''],
    ['key' => 'shop', 'label' => 'Shop', 'href' => 'shop.php'],
    ['key' => 'about', 'label' => 'About', 'href' => 'about.php'],
    ['key' => 'wholesale', 'label' => 'Wholesale', 'href' => 'wholesale.php'],
    ['key' => 'export', 'label' => 'Export', 'href' => 'export-enquiry.php'],
    ['key' => 'contact', 'label' => 'Contact', 'href' => 'contact.php'],
];
?><!doctype html>
<html lang="en" class="no-js">
<head>
<?= render_seo_head($seo) ?>
<meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
<script>document.documentElement.classList.remove('no-js');</script>
</head>
<body class="page-<?= e($bodyClass) ?>">
<a class="skip-link" href="#main">Skip to content</a>

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
    <button class="icon-btn menu-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="mobile-nav" aria-label="Open menu">
      <?= icon('menu') ?>
    </button>

    <a class="brand" href="<?= e(url('')) ?>" aria-label="<?= e(setting('site_name', 'Daily Dose Export')) ?> home">
      <span class="brand-mark"><?= icon('leaf') ?></span>
      <span class="brand-text">
        <span class="brand-name">Daily Dose</span>
        <span class="brand-sub">Export</span>
      </span>
    </a>

    <nav class="primary-nav" aria-label="Primary navigation">
      <?php foreach ($navItems as $item): ?>
        <?php if ($item['key'] === 'shop'): ?>
          <div class="nav-group">
            <a class="nav-link <?= $activeNav === 'shop' ? 'is-active' : '' ?>" href="<?= e(url('shop.php')) ?>"><?= e($item['label']) ?></a>
            <div class="nav-dropdown">
              <a href="<?= e(url('shop.php')) ?>">All products</a>
              <?php foreach ($navCategories as $cat): ?>
                <a href="<?= e(url('category/' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php else: ?>
          <a class="nav-link <?= $activeNav === $item['key'] ? 'is-active' : '' ?>" href="<?= e(url($item['href'])) ?>"><?= e($item['label']) ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>

    <div class="header-actions">
      <button class="icon-btn search-toggle" type="button" data-search-toggle aria-expanded="false" aria-controls="site-search" aria-label="Search products">
        <?= icon('search') ?>
      </button>
      <a class="icon-btn header-account" href="<?= e($customer ? url('account.php') : url('login.php')) ?>" aria-label="<?= $customer ? 'My account' : 'Log in' ?>">
        <?= icon('user') ?>
        <span class="action-label"><?= $customer ? 'Account' : 'Log in' ?></span>
      </a>
      <a class="icon-btn header-wishlist" href="<?= e($customer ? url('account.php?tab=wishlist') : url('login.php?next=account.php%3Ftab%3Dwishlist')) ?>" aria-label="Wishlist">
        <?= icon('heart') ?>
        <span class="count-badge <?= $wishlistCount === 0 ? 'is-empty' : '' ?>" data-wishlist-count><?= $wishlistCount ?></span>
      </a>
      <a class="icon-btn header-cart" href="<?= e(url('cart.php')) ?>" aria-label="Shopping cart">
        <?= icon('cart') ?>
        <span class="count-badge <?= $cartCount === 0 ? 'is-empty' : '' ?>" data-cart-count><?= $cartCount ?></span>
      </a>
    </div>
  </div>

  <div class="search-panel" id="site-search" data-search-panel hidden>
    <div class="container">
      <form class="search-form" action="<?= e(url('shop.php')) ?>" method="get" role="search">
        <label class="sr-only" for="site-search-input">Search products</label>
        <span class="search-icon"><?= icon('search') ?></span>
        <input id="site-search-input" type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="Search turmeric, ragi, dates…" autocomplete="off" maxlength="100">
        <button class="btn btn-primary" type="submit">Search</button>
      </form>
      <p class="search-hint">Search in English or Hindi — for example, haldi, dhaniya, khajoor or ragi.</p>
    </div>
  </div>
</header>

<nav class="mobile-nav" id="mobile-nav" aria-label="Mobile navigation" hidden>
  <div class="mobile-nav-head">
    <span class="mobile-nav-title">Menu</span>
    <button class="icon-btn" type="button" data-nav-close aria-label="Close menu"><?= icon('close') ?></button>
  </div>
  <div class="mobile-nav-body">
    <?php foreach ($navItems as $item): ?>
      <a class="mobile-nav-link <?= $activeNav === $item['key'] ? 'is-active' : '' ?>" href="<?= e(url($item['href'])) ?>"><?= e($item['label']) ?></a>
    <?php endforeach; ?>
    <p class="mobile-nav-heading">Shop by category</p>
    <?php foreach ($navCategories as $cat): ?>
      <a class="mobile-nav-link is-sub" href="<?= e(url('category/' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
    <div class="mobile-nav-cta">
      <?php if ($customer): ?>
        <a class="btn btn-secondary" href="<?= e(url('account.php')) ?>">My account</a>
      <?php else: ?>
        <a class="btn btn-secondary" href="<?= e(url('login.php')) ?>">Log in</a>
        <a class="btn btn-ghost" href="<?= e(url('register.php')) ?>">Create account</a>
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
