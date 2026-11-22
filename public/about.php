<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$team = Database::all(
    "SELECT * FROM team_members WHERE status = 'active' ORDER BY sort_order ASC, id ASC"
);
$values = [];
foreach (preg_split('/\r\n|\r|\n/', (string) setting('core_values')) as $line) {
    $line = trim($line);
    if ($line !== '') {
        $values[] = $line;
    }
}

$seo = [
    'title' => 'About us | ' . setting('site_name', 'Daily Dose Export'),
    'description' => setting('about_intro') !== '' ? excerpt(setting('about_intro'), 160) : 'The story, mission and values behind Daily Dose Export — natural Indian food from Varanasi.',
    'canonical' => url('about.php'),
];
$activeNav = 'about';
$bodyClass = 'about';
$jsonld = web_page_jsonld('About Daily Dose Export', $seo['description'], url('about.php'));
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="page-hero">
  <div class="container">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
      <a href="<?= e(url('')) ?>">Home</a> <span aria-hidden="true">/</span> <span aria-current="page">About us</span>
    </nav>
    <p class="eyebrow">Rooted in Varanasi · Grown across India</p>
    <h1>Natural food, handled with care</h1>
    <p class="page-lead"><?= e(setting('about_intro')) ?></p>
  </div>
</div>

<section class="section">
  <div class="container about-intro">
    <div>
      <p class="eyebrow">Our story</p>
      <h2><?= e(setting('story_title', 'From Indian farms to your kitchen')) ?></h2>
      <p class="muted"><?= nl2br(e(setting('story_text'))) ?></p>
      <div class="about-stats">
        <div class="about-stat"><strong>Farm-sourced</strong><span>Ingredients chosen from Indian agricultural supply partners</span></div>
        <div class="about-stat"><strong>Packed in Varanasi</strong><span>Careful cleaning, grading and packing</span></div>
        <div class="about-stat"><strong>Home &amp; business</strong><span>Retail packs, wholesale quantities and export enquiries</span></div>
      </div>
      <div class="btn-row" style="margin-top:26px">
        <a class="btn btn-primary" href="<?= e(url('shop.php')) ?>">Explore our pantry</a>
        <a class="btn btn-ghost" href="<?= e(url('contact.php')) ?>">Talk to our team</a>
      </div>
    </div>
    <div class="about-intro-media">
      <img src="<?= e(asset_url('assets/images/cat-grains.jpg')) ?>" alt="Grains sourced for Daily Dose Export" width="800" height="680" loading="lazy">
    </div>
  </div>
</section>

<section class="section section-soft">
  <div class="container">
    <div class="section-head">
      <p class="eyebrow">What guides us</p>
      <h2>Mission &amp; vision</h2>
    </div>
    <div class="mv-grid">
      <article class="mv-card">
        <span class="num">01</span>
        <h2>Mission</h2>
        <p><?= e(setting('mission')) ?></p>
      </article>
      <article class="mv-card">
        <span class="num">02</span>
        <h2>Vision</h2>
        <p><?= e(setting('vision')) ?></p>
      </article>
    </div>
  </div>
</section>

<?php if ($values !== []): ?>
  <section class="section">
    <div class="container">
      <div class="section-head">
        <p class="eyebrow">Our core values</p>
        <h2>The standards behind every pack</h2>
      </div>
      <div class="values-grid">
        <?php foreach ($values as $value): ?>
          <article class="value-card">
            <?= icon('leaf') ?>
            <h3><?= e($value) ?></h3>
            <p>Held to across sourcing, handling, packing and customer conversations.</p>
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
        <p class="eyebrow">Leadership</p>
        <h2>The people behind Daily Dose</h2>
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
      <blockquote>“Every Bite, A Promise of Health.”</blockquote>
      <cite>Daily Dose Export · Bhojubeer, Varanasi, India</cite>
    </div>
    <div class="section-head" style="margin-top:44px">
      <p class="eyebrow">Work with us</p>
      <h2>Retail, wholesale and export</h2>
      <p>Whether you need a family pack or a bulk consignment, our team will confirm availability, packaging and pricing directly.</p>
    </div>
    <div class="btn-row">
      <a class="btn btn-primary" href="<?= e(url('wholesale.php')) ?>">Wholesale enquiry</a>
      <a class="btn btn-ghost" href="<?= e(url('export-enquiry.php')) ?>">Export enquiry</a>
      <a class="btn btn-ghost" href="<?= e(url('contact.php')) ?>">Contact us</a>
    </div>
  </div>
</section>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
