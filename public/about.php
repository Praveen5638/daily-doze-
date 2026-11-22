<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$team = Database::all(
    "SELECT * FROM team_members WHERE status = 'active' ORDER BY sort_order ASC, id ASC"
);
$values = [];
foreach (preg_split('/\r\n|\r|\n/', (string) localized_setting('core_values')) as $line) {
    $line = trim($line);
    if ($line !== '') {
        $values[] = $line;
    }
}

$aboutDescription = localized_setting('about_intro') !== '' ? excerpt(localized_setting('about_intro'), 160) : t('about.meta', 'The story, mission and values behind Daily Dose Export — natural Indian food from Varanasi.');
$seo = [
    'title' => t('nav.about', 'About us') . ' | ' . setting('site_name', 'Daily Dose Export'),
    'description' => $aboutDescription,
    'canonical' => url('about.php'),
    'jsonld' => [
        web_page_jsonld('About Daily Dose Export', $aboutDescription, url('about.php'), 'AboutPage', ['.page-lead']),
        breadcrumb_jsonld([['label' => t('nav.home', 'Home'), 'href' => ''], ['label' => t('nav.about', 'About us'), 'href' => 'about.php']]),
    ],
];
$activeNav = 'about';
$bodyClass = 'about';
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="page-hero">
  <div class="container">
    <nav class="breadcrumbs" aria-label="<?= e(t('common.breadcrumb', 'Breadcrumb')) ?>">
      <a href="<?= e(url('')) ?>"><?= e(t('nav.home', 'Home')) ?></a> <span aria-hidden="true">/</span> <span aria-current="page"><?= e(t('nav.about', 'About us')) ?></span>
    </nav>
    <p class="eyebrow"><?= e(t('about.eyebrow', 'Rooted in Varanasi · Grown across India')) ?></p>
    <h1><?= e(t('about.title', 'Natural food, handled with care')) ?></h1>
    <p class="page-lead"><?= e(localized_setting('about_intro')) ?></p>
  </div>
</div>

<section class="section">
  <div class="container about-intro">
    <div>
      <p class="eyebrow"><?= e(t('about.story_eyebrow', 'Our story')) ?></p>
      <h2><?= e(localized_setting('story_title', 'From Indian farms to your kitchen')) ?></h2>
      <p class="muted"><?= nl2br(e(localized_setting('story_text'))) ?></p>
      <div class="about-stats">
        <div class="about-stat"><strong><?= e(t('about.stat1', 'Farm-sourced')) ?></strong><span><?= e(t('about.stat1_text', 'Ingredients chosen from Indian agricultural supply partners')) ?></span></div>
        <div class="about-stat"><strong><?= e(t('about.stat2', 'Packed in Varanasi')) ?></strong><span><?= e(t('about.stat2_text', 'Careful cleaning, grading and packing')) ?></span></div>
        <div class="about-stat"><strong><?= e(t('about.stat3', 'Home & business')) ?></strong><span><?= e(t('about.stat3_text', 'Retail packs, wholesale quantities and export enquiries')) ?></span></div>
      </div>
      <div class="btn-row" style="margin-top:26px">
        <a class="btn btn-primary" href="<?= e(url('shop.php')) ?>"><?= e(t('about.explore', 'Explore our pantry')) ?></a>
        <a class="btn btn-ghost" href="<?= e(url('contact.php')) ?>"><?= e(t('about.talk', 'Talk to our team')) ?></a>
      </div>
    </div>
    <div class="about-intro-media">
      <img src="<?= e(asset_url('assets/images/cat-grains.jpg')) ?>" alt="<?= e(t('about.image_alt', 'Grains sourced for Daily Dose Export')) ?>" width="800" height="680" loading="lazy">
    </div>
  </div>
</section>

<section class="section section-soft">
  <div class="container">
    <div class="section-head">
      <p class="eyebrow"><?= e(t('about.guides', 'What guides us')) ?></p>
      <h2><?= e(t('about.mission_vision', 'Mission & vision')) ?></h2>
    </div>
    <div class="mv-grid">
      <article class="mv-card">
        <span class="num">01</span>
        <h2><?= e(t('about.mission', 'Mission')) ?></h2>
        <p><?= e(localized_setting('mission')) ?></p>
      </article>
      <article class="mv-card">
        <span class="num">02</span>
        <h2><?= e(t('about.vision', 'Vision')) ?></h2>
        <p><?= e(localized_setting('vision')) ?></p>
      </article>
    </div>
  </div>
</section>

<?php if ($values !== []): ?>
  <section class="section">
    <div class="container">
      <div class="section-head">
        <p class="eyebrow"><?= e(t('about.values_eyebrow', 'Our core values')) ?></p>
        <h2><?= e(t('about.values_title', 'The standards behind every pack')) ?></h2>
      </div>
      <div class="values-grid">
        <?php foreach ($values as $value): ?>
          <article class="value-card">
            <?= icon('leaf') ?>
            <h3><?= e($value) ?></h3>
            <p><?= e(t('about.value_note', 'Held to across sourcing, handling, packing and customer conversations.')) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php if ($team !== []): ?>
  <section class="section section-dark">
    <div class="container">
      <div class="section-head">
        <p class="eyebrow"><?= e(t('about.leadership', 'Leadership')) ?></p>
        <h2><?= e(t('about.leadership_title', 'The people behind Daily Dose')) ?></h2>
      </div>
      <div class="team-grid">
        <?php foreach ($team as $member): ?>
          <article class="team-card">
            <div class="team-photo">
              <?php if (!empty($member['image'])): ?>
                <img src="<?= e(asset_url($member['image'])) ?>" alt="<?= e($member['name']) ?>" loading="lazy" width="220" height="260">
              <?php else: ?>
                <?= e(mb_substr($member['name'], 0, 1)) ?>
              <?php endif; ?>
            </div>
            <div>
              <h3><?= e($member['name']) ?></h3>
              <p class="team-role"><?= e($member['position']) ?></p>
              <?php if (!empty($member['bio'])): ?><p class="team-bio"><?= e($member['bio']) ?></p><?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="quote-band">
      <blockquote>“<?= e(localized_setting('slogan', 'Every Bite, A Promise of Health.')) ?>”</blockquote>
      <cite>Daily Dose Export · Bhojubeer, Varanasi, India</cite>
    </div>
    <div class="section-head" style="margin-top:44px">
      <p class="eyebrow"><?= e(t('about.work', 'Work with us')) ?></p>
      <h2><?= e(t('about.work_title', 'Retail, wholesale and export')) ?></h2>
      <p><?= e(t('about.work_text', 'Whether you need a family pack or a bulk consignment, our team will confirm availability, packaging and pricing directly.')) ?></p>
    </div>
    <div class="btn-row">
      <a class="btn btn-primary" href="<?= e(url('wholesale.php')) ?>"><?= e(t('about.wholesale', 'Wholesale enquiry')) ?></a>
      <a class="btn btn-ghost" href="<?= e(url('export-enquiry.php')) ?>"><?= e(t('about.export', 'Export enquiry')) ?></a>
      <a class="btn btn-ghost" href="<?= e(url('contact.php')) ?>"><?= e(t('about.contact', 'Contact us')) ?></a>
    </div>
  </div>
</section>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
