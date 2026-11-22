<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$categories = Catalog::categories();
$featured = Catalog::featured_products(8);
$testimonials = setting('reviews_enabled', '1') === '1' ? Catalog::testimonials(3) : [];
$wishlistIds = Auth::customerId() ? Catalog::wishlist_ids((int) Auth::customerId()) : [];
$coreValues = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', localized_setting('core_values')) ?: [])));
$heroImage = setting('hero_image', 'assets/images/hero.jpg');

$homeJsonld = [
    web_page_jsonld(
        localized_setting('site_name', 'Daily Dose Export'),
        localized_setting('default_meta_description'),
        url(''),
        'WebPage',
        ['.hero-copy h1', '.hero-lead']
    ),
];
if ($featured !== []) {
    $homeJsonld[] = ['@context' => 'https://schema.org'] + item_list_jsonld($featured, t('home.featured', 'Featured products'));
}
$seo = [
    'title' => localized_setting('site_name', 'Daily Dose Export') . ' | ' . localized_setting('tagline', 'Pure From Farm To World'),
    'description' => localized_setting('default_meta_description'),
    'canonical' => url(''),
    'image' => $heroImage,
    'preload_image' => $heroImage,
    'jsonld' => $homeJsonld,
];
$activeNav = 'home';
$bodyClass = 'home';
require BASE_PATH . '/app/views/layout/header.php';
?>

<section class="hero">
  <div class="container hero-inner">
    <div class="hero-copy">
      <p class="eyebrow"><?= e(localized_setting('hero_eyebrow', 'Natural food · Varanasi, India')) ?></p>
      <h1><?= e(localized_setting('hero_title', 'Pure From Farm To World')) ?></h1>
      <p class="hero-lead"><?= e(localized_setting('hero_text')) ?></p>
      <div class="hero-actions">
        <a class="btn btn-primary btn-lg" href="<?= e(url('shop.php')) ?>"><?= e(t('home.hero.cta', 'Explore our products')) ?> <?= icon('arrow-right') ?></a>
        <a class="btn btn-ghost btn-lg" href="<?= e(url('wholesale.php')) ?>"><?= e(t('home.hero.wholesale', 'Wholesale & export')) ?></a>
      </div>
      <p class="hero-note"><?= icon('leaf') ?> <?= e(localized_setting('hero_note', localized_setting('slogan'))) ?></p>
      <div class="hero-trust">
        <span><?= icon('pin') ?> <?= e(t('home.hero.trust1', 'Sourced from Indian farms')) ?></span>
        <span><?= icon('package') ?> <?= e(t('home.hero.trust2', 'Packed in Varanasi')) ?></span>
        <span><?= icon('truck') ?> <?= e(t('home.hero.trust3', 'Retail, wholesale & export')) ?></span>
      </div>
    </div>
    <div class="hero-media">
      <img src="<?= e(asset_url($heroImage)) ?>" alt="<?= e(t('home.hero.image_alt', 'Natural Indian food ingredients arranged on a table')) ?>" width="1200" height="960" fetchpriority="high" decoding="async">
      <div class="hero-badge">
        <?= icon('leaf') ?>
        <span><strong>Daily Dose Export</strong><small>Bhojubeer, Varanasi · India</small></span>
      </div>
    </div>
  </div>
</section>

<section class="section" aria-labelledby="categories-title">
  <div class="container">
    <div class="section-head-row">
      <div class="section-head">
        <p class="eyebrow"><?= e(t('home.categories.eyebrow', 'Shop by pantry')) ?></p>
        <h2 id="categories-title"><?= e(t('home.categories.title', 'Everyday essentials, carefully chosen')) ?></h2>
        <p class="section-lead"><?= e(t('home.categories.text', 'From ground spices to whole grains, millets, teas and dry fruits — each range is packed for homes, kitchens and bulk buyers.')) ?></p>
      </div>
      <a class="text-link" href="<?= e(url('shop.php')) ?>"><?= e(t('home.categories.view_all', 'View all products')) ?> <?= icon('arrow-right') ?></a>
    </div>
    <div class="category-grid">
      <?php foreach ($categories as $category): ?>
        <a class="category-tile" href="<?= e(url('category/' . $category['slug'])) ?>">
          <img src="<?= e(asset_url($category['image'])) ?>" alt="<?= e(category_label($category)) ?>" loading="lazy" decoding="async" width="480" height="600">
          <div class="category-tile-text">
            <h3><?= e(category_label($category)) ?></h3>
            <span><?= (int) $category['product_count'] ?> <?= (int) $category['product_count'] === 1 ? e(t('home.product', 'product')) : e(t('home.products', 'products')) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
      <a class="category-tile is-cta" href="<?= e(url('shop.php')) ?>">
        <div class="cta-inner">
          <?= icon('grid') ?>
          <h3><?= e(t('home.cta.title', 'Explore the full pantry')) ?></h3>
          <p><?= e(t('home.cta.text', 'Spices, grains, millets, cookies, teas, salt and dry fruits in one place.')) ?></p>
          <span class="text-link" style="color:var(--gold-soft);border-color:rgba(198,155,81,.4)"><?= e(t('home.cta.shop_all', 'Shop all')) ?> <?= icon('arrow-right') ?></span>
        </div>
      </a>
    </div>
  </div>
</section>

<section class="section section-warm" aria-labelledby="featured-title">
  <div class="container">
    <div class="section-head-row">
      <div class="section-head">
        <p class="eyebrow"><?= e(t('home.featured.eyebrow', 'Pantry favourites')) ?></p>
        <h2 id="featured-title"><?= e(t('home.featured.title', 'Chosen for everyday kitchens')) ?></h2>
        <p class="section-lead"><?= e(t('home.featured.text', 'A selection from our spice, grain, millet and dry fruit ranges. Availability and pack details are confirmed by our team.')) ?></p>
      </div>
      <a class="text-link" href="<?= e(url('shop.php?sort=featured')) ?>"><?= e(t('home.featured.browse', 'Browse the range')) ?> <?= icon('arrow-right') ?></a>
    </div>
    <?php if ($featured === []): ?>
      <p class="notice"><?= e(t('home.featured.empty', 'Products are being added to the catalogue. Please check back shortly or')) ?> <a href="<?= e(url('contact.php')) ?>"><?= e(t('home.featured.empty_link', 'contact us')) ?></a> <?= e(t('home.featured.empty_suffix', 'for availability.')) ?></p>
    <?php else: ?>
      <div class="product-grid">
        <?php foreach ($featured as $product): ?>
          <?php require BASE_PATH . '/app/views/partials/product-card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="section section-dark" aria-labelledby="why-title">
  <div class="container values-layout">
    <div class="values-intro">
      <p class="eyebrow"><?= e(t('home.why.eyebrow', 'Why Daily Dose')) ?></p>
      <h2 id="why-title"><?= e(t('home.why.title', 'A simpler standard for everyday food')) ?></h2>
      <p class="section-lead"><?= e(t('home.why.text', 'We focus on what matters: honest sourcing, careful handling and clear communication — for a bag of spices or a full export pallet.')) ?></p>
      <div class="mt-2">
        <a class="btn btn-gold" href="<?= e(url('about.php')) ?>"><?= e(t('home.why.cta', 'How we work')) ?> <?= icon('arrow-right') ?></a>
      </div>
    </div>
    <div class="values-list">
      <div class="value-item">
        <span class="value-icon"><?= icon('leaf') ?></span>
        <div>
          <h3><?= e(t('home.value1.title', 'Natural sourcing')) ?></h3>
          <p><?= e(t('home.value1.text', 'Products are sourced through Indian agricultural supply partners, with origin and variety confirmed on the approved pack.')) ?></p>
        </div>
      </div>
      <div class="value-item">
        <span class="value-icon"><?= icon('shield') ?></span>
        <div>
          <h3><?= e(t('home.value2.title', 'Care at every step')) ?></h3>
          <p><?= e(t('home.value2.text', 'Cleaning, grading and packing are handled with attention to condition and freshness, batch by batch.')) ?></p>
        </div>
      </div>
      <div class="value-item">
        <span class="value-icon"><?= icon('package') ?></span>
        <div>
          <h3><?= e(t('home.value3.title', 'Packed for the journey')) ?></h3>
          <p><?= e(t('home.value3.text', 'From household pouches to bulk and export cartons, packaging is selected for the destination and order size.')) ?></p>
        </div>
      </div>
      <div class="value-item">
        <span class="value-icon"><?= icon('message') ?></span>
        <div>
          <h3><?= e(t('home.value4.title', 'Clear communication')) ?></h3>
          <p><?= e(t('home.value4.text', 'Pricing, availability and documentation are confirmed by our team. We do not publish claims we cannot verify.')) ?></p>
        </div>
      </div>
      <div class="value-item">
        <span class="value-icon"><?= icon('users') ?></span>
        <div>
          <h3><?= e(t('home.value5.title', 'Built for homes and businesses')) ?></h3>
          <p><?= e(t('home.value5.text', 'Retail packs, wholesale quantities and export enquiries are all handled by the same team in Varanasi.')) ?></p>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section" aria-labelledby="journey-title">
  <div class="container">
    <div class="section-head is-center">
      <p class="eyebrow"><?= e(t('home.journey.eyebrow', 'Farm to product')) ?></p>
      <h2 id="journey-title"><?= e(t('home.journey.title', 'The journey behind every pack')) ?></h2>
      <p class="section-lead"><?= e(t('home.journey.text', 'A simple, visible process — from the fields we source from to the pack that reaches your kitchen.')) ?></p>
    </div>
    <ol class="journey" style="list-style:none">
      <li class="journey-step">
        <span class="journey-num">01</span>
        <h3><?= e(t('home.journey1.title', 'Sourcing')) ?></h3>
        <p><?= e(t('home.journey1.text', 'Produce is sourced through agricultural supply partners across India.')) ?></p>
      </li>
      <li class="journey-step">
        <span class="journey-num">02</span>
        <h3><?= e(t('home.journey2.title', 'Cleaning')) ?></h3>
        <p><?= e(t('home.journey2.text', 'Grains, spices and whole produce are cleaned before further handling.')) ?></p>
      </li>
      <li class="journey-step">
        <span class="journey-num">03</span>
        <h3><?= e(t('home.journey3.title', 'Grading')) ?></h3>
        <p><?= e(t('home.journey3.text', 'Material is sorted and graded so pack quality stays consistent.')) ?></p>
      </li>
      <li class="journey-step">
        <span class="journey-num">04</span>
        <h3><?= e(t('home.journey4.title', 'Processing')) ?></h3>
        <p><?= e(t('home.journey4.text', 'Grinding, blending and baking are done in controlled small batches.')) ?></p>
      </li>
      <li class="journey-step">
        <span class="journey-num">05</span>
        <h3><?= e(t('home.journey5.title', 'Packing')) ?></h3>
        <p><?= e(t('home.journey5.text', 'Packs are filled and labelled with the details printed on the approved pack.')) ?></p>
      </li>
      <li class="journey-step">
        <span class="journey-num">06</span>
        <h3><?= e(t('home.journey6.title', 'Distribution')) ?></h3>
        <p><?= e(t('home.journey6.text', 'Orders are dispatched for retail, wholesale and export destinations.')) ?></p>
      </li>
    </ol>
  </div>
</section>

<section class="section-tight">
  <div class="container">
    <div class="millet-layout">
      <div class="millet-media">
        <img src="<?= e(asset_url('assets/images/cat-millets.jpg')) ?>" alt="<?= e(t('home.millet.image_alt', 'Millet grains in a bowl')) ?>" loading="lazy" decoding="async" width="900" height="800">
      </div>
      <div class="millet-copy">
        <p class="eyebrow"><?= e(t('home.millet.eyebrow', 'Millets, back on the table')) ?></p>
        <h2><?= e(t('home.millet.title', 'Small grains. Long tradition.')) ?></h2>
        <p><?= e(t('home.millet.text', "Ragi, bajra, jowar and kodo are part of India's everyday food heritage. We pack them whole and clean, ready for rotis, porridge and family meals.")) ?></p>
        <div class="millet-chips">
          <span>Ragi</span>
          <span>Bajra</span>
          <span>Jowar</span>
          <span>Kodo</span>
          <span>Multi-millet mix</span>
        </div>
        <a class="text-link" href="<?= e(url('category/millets')) ?>"><?= e(t('home.millet.link', 'Shop millets')) ?> <?= icon('arrow-right') ?></a>
      </div>
    </div>
  </div>
</section>

<section class="section" aria-labelledby="packaging-title">
  <div class="container">
    <div class="packaging-layout">
      <div class="packaging-media">
        <img src="<?= e(asset_url('assets/images/packaging.jpg')) ?>" alt="<?= e(t('home.packaging.image_alt', 'Kraft packaging pouches prepared for food products')) ?>" loading="lazy" decoding="async" width="1000" height="760">
      </div>
      <div class="packaging-copy">
        <p class="eyebrow"><?= e(t('home.packaging.eyebrow', 'Packed with purpose')) ?></p>
        <h2 id="packaging-title"><?= e(t('home.packaging.title', 'Packaging that travels well')) ?></h2>
        <p class="section-lead"><?= e(t('home.packaging.text', 'Household packs are sealed for freshness. Bulk orders and export consignments are packed to suit the destination and handling requirements confirmed with our team.')) ?></p>
        <div class="packaging-list">
          <span><?= icon('check') ?> <?= e(t('home.packaging.item1', 'Food-grade pouches')) ?></span>
          <span><?= icon('check') ?> <?= e(t('home.packaging.item2', 'Clear jars')) ?></span>
          <span><?= icon('check') ?> <?= e(t('home.packaging.item3', 'Bulk cartons')) ?></span>
          <span><?= icon('check') ?> <?= e(t('home.packaging.item4', 'Export-ready packing')) ?></span>
          <span><?= icon('check') ?> <?= e(t('home.packaging.item5', 'Gift assortments')) ?></span>
          <span><?= icon('check') ?> <?= e(t('home.packaging.item6', 'Custom labels for approved orders')) ?></span>
        </div>
        <a class="btn btn-ghost" href="<?= e(url('wholesale.php')) ?>"><?= e(t('home.packaging.link', 'Talk about bulk packing')) ?> <?= icon('arrow-right') ?></a>
      </div>
    </div>
  </div>
</section>

<section class="section section-warm" aria-labelledby="story-title">
  <div class="container story-layout">
    <div class="story-media">
      <img src="<?= e(asset_url('assets/images/cat-grains.jpg')) ?>" alt="<?= e(t('home.story.image_alt', 'Grains and natural food ingredients')) ?>" loading="lazy" decoding="async" width="800" height="900">
    </div>
    <div>
      <p class="eyebrow"><?= e(t('home.story.eyebrow', 'Our story')) ?></p>
      <h2 id="story-title"><?= e(localized_setting('story_title', 'From Indian farms to your kitchen')) ?></h2>
      <p class="section-lead"><?= e(localized_setting('story_text')) ?></p>
      <?php if ($coreValues !== []): ?>
        <div class="story-values">
          <?php foreach (array_slice($coreValues, 0, 4) as $value): ?>
            <span><?= e($value) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="mt-2">
        <a class="btn btn-secondary" href="<?= e(url('about.php')) ?>"><?= e(t('home.story.cta', 'Meet Daily Dose')) ?> <?= icon('arrow-right') ?></a>
      </div>
      <p class="signature"><?= e(localized_setting('slogan', 'Every Bite, A Promise of Health.')) ?></p>
    </div>
  </div>
</section>

<?php if ($testimonials !== []): ?>
  <section class="section" aria-labelledby="testimonials-title">
    <div class="container">
      <div class="section-head is-center">
        <p class="eyebrow"><?= e(t('home.reviews.eyebrow', 'Customer words')) ?></p>
        <h2 id="testimonials-title"><?= e(t('home.reviews.title', 'Notes from our customers')) ?></h2>
        <p class="section-lead"><?= e(t('home.reviews.text', 'Only approved reviews from real customers are published here.')) ?></p>
      </div>
      <div class="testimonial-grid">
        <?php foreach ($testimonials as $review): ?>
          <article class="testimonial-card">
            <?= rating_stars((float) $review['rating']) ?>
            <blockquote>“<?= e(excerpt($review['body'], 220)) ?>”</blockquote>
            <div class="testimonial-meta">
              <strong><?= e($review['name']) ?></strong>
              <?php if (!empty($review['product_name'])): ?>
                <?= e(t('home.reviews.on', 'On')) ?> <a href="<?= e(url('products/' . $review['product_slug'])) ?>"><?= e($review['product_name']) ?></a>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<section class="section-tight">
  <div class="container">
    <div class="cta-band">
      <div>
        <p class="eyebrow"><?= e(t('home.trade.eyebrow', 'Trade with Daily Dose')) ?></p>
        <h2><?= e(t('home.trade.title', 'Sourcing for a business?')) ?></h2>
        <p><?= e(t('home.trade.text', 'Tell us what you need and where it needs to reach. We handle domestic wholesale and export enquiries with clear pricing and packing options.')) ?></p>
      </div>
      <div class="cta-actions">
        <a class="btn btn-light" href="<?= e(url('wholesale.php')) ?>"><?= e(t('home.trade.wholesale', 'Wholesale enquiry')) ?></a>
        <a class="btn btn-gold" href="<?= e(url('export-enquiry.php')) ?>"><?= e(t('home.trade.export', 'Export enquiry')) ?> <?= icon('arrow-right') ?></a>
      </div>
    </div>
  </div>
</section>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
