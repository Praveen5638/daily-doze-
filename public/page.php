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

$pageUrl = url('pages/' . $cmsPage['slug']);
$seo = [
    'title' => $pageTitle,
    'description' => $pageDescription !== '' ? $pageDescription : excerpt(localized_setting('default_meta_description'), 160),
    'canonical' => $pageUrl,
    'robots' => $isDraft ? 'noindex,follow' : 'index,follow',
];
if (!$isDraft) {
    $seo['jsonld'] = [
        web_page_jsonld($cmsPage['title'], $pageDescription, $pageUrl, 'WebPage', ['.page-lead']),
        breadcrumb_jsonld([
            ['label' => t('nav.home', 'Home'), 'href' => ''],
            ['label' => $cmsPage['title'], 'href' => 'pages/' . $cmsPage['slug']],
        ]),
    ];
    if ($cmsPage['slug'] === 'faq') {
        $faqSchema = faq_jsonld_from_html((string) $cmsPage['body']);
        if ($faqSchema !== null) {
            $seo['jsonld'][] = $faqSchema;
        }
    }
}
$activeNav = '';
$bodyClass = 'cms-page';
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="page-hero">
  <div class="container">
    <nav class="breadcrumbs" aria-label="<?= e(t('common.breadcrumb', 'Breadcrumb')) ?>">
      <a href="<?= e(url('')) ?>"><?= e(t('nav.home', 'Home')) ?></a> <span aria-hidden="true">/</span> <span aria-current="page"><?= e($cmsPage['title']) ?></span>
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
          <strong><?= e(t('page.draft_title', 'This page is being finalised.')) ?></strong>
          <p style="margin:8px 0 0"><?= e(t('page.draft_before', 'Our team is completing this policy with the business owner before publishing it. For any questions in the meantime, please')) ?> <a href="<?= e(url('contact.php')) ?>"><?= e(t('page.contact_link', 'contact us')) ?></a> <?= e(t('page.draft_or_call', 'or call')) ?> <a href="tel:<?= e(preg_replace('/\s+/', '', (string) setting('support_phone'))) ?>"><?= e(setting('support_phone')) ?></a>.</p>
        </div>
        <p><?= e(t('page.draft_second', 'This page will be published once the wording has been reviewed and approved. It is shown here so that every link on the website leads to a working page.')) ?></p>
        <p><a href="<?= e(url('contact.php')) ?>"><?= e(t('page.contact_link', 'Contact Daily Dose Export')) ?></a></p>
      <?php else: ?>
        <?php
        $bodyHtml = (string) $cmsPage['body'];
        if ($cmsPage['slug'] === 'faq') {
            $bodyHtml = preg_replace_callback(
                '/<p>\s*<strong>(.+?)<\/strong>\s*<br\s*\/?>\s*(.+?)<\/p>/is',
                static fn (array $m): string => '<h3>' . $m[1] . '</h3><p>' . $m[2] . '</p>',
                $bodyHtml
            ) ?? $bodyHtml;
        }
        echo sanitize_html($bodyHtml);
        ?>
        <p class="muted small" style="margin-top:2.4rem"><?= e(sprintf(t('page.last_updated', 'Last updated: %s.'), date_display($cmsPage['updated_at']))) ?> <?= e(t('page.last_updated_note', 'If anything here is unclear, please')) ?> <a href="<?= e(url('contact.php')) ?>"><?= e(t('page.contact_link', 'contact us')) ?></a>.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
