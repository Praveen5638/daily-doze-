<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$slug = (string) ($_GET['slug'] ?? '');
$cmsPage = null;
if (preg_match('/^[a-z0-9-]{1,140}$/', $slug)) {
    $cmsPage = Database::one('SELECT * FROM cms_pages WHERE slug = ?', [$slug]);
}

if ($cmsPage === null) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$isDraft = $cmsPage['status'] !== 'published';
$pageTitle = $cmsPage['meta_title'] !== '' && $cmsPage['meta_title'] !== null
    ? $cmsPage['meta_title']
    : $cmsPage['title'] . ' | ' . setting('site_name', 'Daily Dose Export');
$pageDescription = trim((string) $cmsPage['meta_description']);

$seo = [
    'title' => $pageTitle,
    'description' => $pageDescription !== '' ? $pageDescription : excerpt(setting('default_meta_description'), 160),
    'canonical' => url('pages/' . $cmsPage['slug']),
    'robots' => $isDraft ? 'noindex,follow' : null,
];
$activeNav = '';
$bodyClass = 'cms-page';
$jsonld = $isDraft ? null : web_page_jsonld($cmsPage['title'], $pageDescription, url('pages/' . $cmsPage['slug']));
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="page-hero">
  <div class="container">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
      <a href="<?= e(url('')) ?>">Home</a> <span aria-hidden="true">/</span> <span aria-current="page"><?= e($cmsPage['title']) ?></span>
    </nav>
    <h1><?= e($cmsPage['title']) ?></h1>
    <?php if (!$isDraft && $pageDescription !== ''): ?><p class="page-lead"><?= e($pageDescription) ?></p><?php endif; ?>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="prose">
      <?php if ($isDraft): ?>
        <div class="notice">
          <strong>This page is being finalised.</strong>
          <p style="margin:8px 0 0">Our team is completing this policy with the business owner before publishing it. For any questions in the meantime, please <a href="<?= e(url('contact.php')) ?>">contact us</a> or call <a href="tel:<?= e(preg_replace('/\s+/', '', (string) setting('support_phone'))) ?>"><?= e(setting('support_phone')) ?></a>.</p>
        </div>
        <p>This page will be published once the wording has been reviewed and approved. It is shown here so that every link on the website leads to a working page.</p>
        <p><a href="<?= e(url('contact.php')) ?>">Contact Daily Dose Export</a></p>
      <?php else: ?>
        <?= sanitize_html((string) $cmsPage['body']) ?>
        <p class="muted small" style="margin-top:2.4rem">Last updated: <?= e(date_display($cmsPage['updated_at'])) ?>. If anything here is unclear, please <a href="<?= e(url('contact.php')) ?>">contact us</a>.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
