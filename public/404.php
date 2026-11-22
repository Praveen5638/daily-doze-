<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!headers_sent()) {
    http_response_code(404);
}
$categories = Catalog::categories();
$seo = [
    'title' => 'Page not found | ' . setting('site_name', 'Daily Dose Export'),
    'description' => 'The page you were looking for could not be found. Explore the Daily Dose Export pantry or contact our team.',
    'canonical' => null,
    'robots' => 'noindex,follow',
];
$activeNav = '';
$bodyClass = 'not-found';
require BASE_PATH . '/app/views/layout/header.php';
?>

<section class="error-page">
  <div class="container">
    <p class="error-code">404</p>
    <h1>We could not find that page</h1>
    <p>The link may be old or mistyped. Try searching our pantry, or use the links below to continue.</p>

    <form class="search-form error-search" action="<?= e(url('shop.php')) ?>" method="get" role="search" style="max-width:460px;margin:26px auto 0">
      <label class="sr-only" for="error-search">Search products</label>
      <input id="error-search" type="search" name="q" placeholder="Search spices, grains, millets…" maxlength="120">
      <button class="btn btn-primary" type="submit"><?= icon('search') ?> Search</button>
    </form>

    <div class="btn-row" style="justify-content:center;margin-top:26px">
      <a class="btn btn-primary" href="<?= e(url('')) ?>">Back to home</a>
      <a class="btn btn-ghost" href="<?= e(url('shop.php')) ?>">Browse the shop</a>
      <a class="btn btn-ghost" href="<?= e(url('contact.php')) ?>">Contact us</a>
    </div>

    <?php if ($categories !== []): ?>
      <div class="error-links">
        <p class="eyebrow">Popular categories</p>
        <div class="btn-row" style="justify-content:center">
          <?php foreach (array_slice($categories, 0, 6) as $category): ?>
            <a class="btn btn-sm btn-ghost" href="<?= e(url('category/' . $category['slug'])) ?>"><?= e($category['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
