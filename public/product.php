<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$slug = isset($_GET['slug']) && is_string($_GET['slug']) ? trim($_GET['slug']) : '';
$product = $slug !== '' ? Catalog::product_by_slug($slug) : null;
if ($product === null) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$reviewsEnabled = setting('reviews_enabled', '1') === '1';

if (is_post()) {
    verify_csrf();
    $formAction = post_str('action');

    if ($formAction === 'review') {
        if (!$reviewsEnabled) {
            flash('error', t('product.reviews_closed', 'Reviews are currently closed.'));
            redirect('products/' . $product['slug']);
        }
        $customer = Auth::customer();
        if ($customer === null) {
            flash('error', t('product.login_review', 'Please log in to write a review.'));
            redirect('login.php?next=' . urlencode('products/' . $product['slug']));
        }
        if (!RateLimiter::hit('review:' . $customer['id'], 'create', 3, 86400)) {
            flash('error', t('product.review_limit', 'You have submitted several reviews today. Please try again tomorrow.'));
            redirect('products/' . $product['slug']);
        }
        $rating = post_int('rating');
        $title = mb_substr(post_str('title'), 0, 190);
        $body = trim((string) ($_POST['body'] ?? ''));
        $body = mb_substr($body, 0, 2000);
        if ($rating < 1 || $rating > 5) {
            flash('error', t('product.rating_invalid', 'Please choose a rating between 1 and 5 stars.'));
            redirect('products/' . $product['slug']);
        }
        if (mb_strlen($body) < 10) {
            flash('error', t('product.review_short', 'Please write at least a short sentence about the product.'));
            redirect('products/' . $product['slug']);
        }
        $duplicate = Database::one(
            'SELECT id FROM reviews WHERE product_id = ? AND customer_id = ? LIMIT 1',
            [$product['id'], $customer['id']]
        );
        if ($duplicate !== null) {
            flash('info', t('product.review_duplicate', 'You have already reviewed this product. Thank you for your feedback.'));
            redirect('products/' . $product['slug']);
        }
        $verifiedOrder = Database::value(
            'SELECT o.id FROM orders o JOIN order_items oi ON oi.order_id = o.id
             WHERE o.customer_id = ? AND oi.product_id = ? AND (o.payment_status = "paid" OR o.status = "delivered")
             ORDER BY o.id DESC LIMIT 1',
            [$customer['id'], $product['id']]
        );
        Database::insert('reviews', [
            'product_id' => (int) $product['id'],
            'customer_id' => (int) $customer['id'],
            'order_id' => $verifiedOrder !== null ? (int) $verifiedOrder : null,
            'name' => trim($customer['first_name'] . ' ' . ($customer['last_name'] ?? '')),
            'email' => $customer['email'],
            'rating' => $rating,
            'title' => $title !== '' ? $title : null,
            'body' => $body,
            'status' => 'pending',
            'is_verified' => $verifiedOrder !== null ? 1 : 0,
        ]);
        Notifications::admin('review', 'New review awaiting approval', $product['name'] . ' — ' . $rating . '/5 from ' . $customer['first_name'], 'reviews.php');
        flash('success', t('product.review_thanks', 'Thank you. Your review has been sent for approval.'));
        redirect('products/' . $product['slug']);
    }

    $variantId = post_int('variant_id');
    $quantity = max(1, min(99, post_int('quantity', 1)));
    $validVariant = null;
    foreach ($product['variants'] as $variantRow) {
        if ((int) $variantRow['id'] === $variantId) {
            $validVariant = $variantRow;
            break;
        }
    }
    if ($validVariant === null) {
        flash('error', t('product.invalid_pack', 'Please choose a valid pack size.'));
        redirect('products/' . $product['slug']);
    }
    if ($validVariant['price'] === null) {
        flash('info', t('product.price_enquiry', 'This pack is priced on request. Please send us an enquiry and we will confirm availability and pricing.'));
        redirect('contact.php');
    }
    $added = Cart::add($variantId, $quantity);
    if (!$added['ok']) {
        flash('error', $added['message']);
        redirect('products/' . $product['slug']);
    }
    if ($formAction === 'buy_now') {
        redirect('checkout.php');
    }
    flash('success', sprintf(t('product.added_to_cart', '%s (%s) was added to your cart.'), $product['name'], $validVariant['pack_size']));
    redirect('products/' . $product['slug']);
}

$variants = $product['variants'];
$selected = null;
foreach ($variants as $variantRow) {
    if ((int) $variantRow['id'] === (int) ($product['default_variant_id'] ?? 0)
        && $variantRow['price'] !== null && (int) $variantRow['stock_qty'] > 0) {
        $selected = $variantRow;
        break;
    }
}
if ($selected === null) {
    foreach ($variants as $variantRow) {
        if ($variantRow['price'] !== null && (int) $variantRow['stock_qty'] > 0) {
            $selected = $variantRow;
            break;
        }
    }
}
if ($selected === null) {
    foreach ($variants as $variantRow) {
        if ((int) $variantRow['id'] === (int) ($product['default_variant_id'] ?? 0)) {
            $selected = $variantRow;
            break;
        }
    }
}
if ($selected === null && $variants !== []) {
    $selected = $variants[0];
}
$selectedPrice = $selected && $selected['price'] !== null ? (float) $selected['price'] : null;
$selectedMrp = $selected && $selected['mrp'] !== null ? (float) $selected['mrp'] : null;
$selectedStock = $selected ? (int) $selected['stock_qty'] : 0;
$purchasable = $selectedPrice !== null && $selectedStock > 0;
$discount = discount_percent($selectedPrice, $selectedMrp);

$images = $product['images'];
$primaryImage = $images[0]['image_path'] ?? $product['primary_image'];

$related = Catalog::related_products((int) $product['category_id'], (int) $product['id'], 4);
$reviews = $reviewsEnabled ? Catalog::reviews((int) $product['id'], 6) : [];
$reviewCount = (int) $product['review_count'];
$ratingAvg = (float) $product['rating_avg'];
$wishlistIds = Auth::customerId() ? Catalog::wishlist_ids((int) Auth::customerId()) : [];
$inWishlist = in_array((int) $product['id'], $wishlistIds, true);
$product['reviews'] = $reviews;

$metaDescription = trim((string) ($product['meta_description'] ?? ''));
if ($metaDescription === '') {
    $metaDescription = trim((string) excerpt($product['short_description'] ?: $product['description'], 110));
    $metaDescription .= ' ' . ($variants !== []
        ? sprintf(t('product.meta_packs', 'Shop %1$s from Daily Dose Export, Varanasi — %2$d pack sizes listed.'), $product['name'], count($variants))
        : sprintf(t('product.meta_single', 'Shop %s from Daily Dose Export, Varanasi.'), $product['name']));
    if (mb_strlen($metaDescription) > 180) {
        $metaDescription = rtrim(mb_substr($metaDescription, 0, 177)) . '…';
    }
}

$seo = [
    'title' => $product['meta_title'] ?: $product['name'] . ' | ' . setting('site_name', 'Daily Dose Export'),
    'description' => $metaDescription,
    'canonical' => url('products/' . $product['slug']),
    'type' => 'product',
    'image' => $primaryImage,
    'preload_image' => $primaryImage,
    'jsonld' => [
        product_jsonld($product),
        breadcrumb_jsonld([
            ['label' => t('nav.home', 'Home'), 'href' => ''],
            ['label' => t('nav.shop', 'Shop'), 'href' => 'shop.php'],
            ['label' => category_label(['slug' => $product['category_slug'], 'name' => $product['category_name']]), 'href' => 'category/' . $product['category_slug']],
            ['label' => $product['name'], 'href' => 'products/' . $product['slug']],
        ]),
    ],
];
$activeNav = 'shop';
$bodyClass = 'product-detail';
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="container">
  <?= breadcrumbs_html([
      ['label' => t('nav.home', 'Home'), 'href' => ''],
      ['label' => t('nav.shop', 'Shop'), 'href' => 'shop.php'],
      ['label' => category_label(['slug' => $product['category_slug'], 'name' => $product['category_name']]), 'href' => 'category/' . $product['category_slug']],
      ['label' => $product['name']],
  ]) ?>

  <div class="product-layout">
    <div class="product-gallery">
      <div class="gallery-main" data-gallery-main>
        <img src="<?= e(asset_url($primaryImage)) ?>" alt="<?= e($images[0]['alt_text'] ?? $product['name']) ?>" width="900" height="900" fetchpriority="high" decoding="async">
      </div>
      <?php if (count($images) > 1): ?>
        <div class="gallery-thumbs">
          <?php foreach ($images as $index => $image): ?>
            <button class="gallery-thumb <?= $index === 0 ? 'is-active' : '' ?>" type="button" data-gallery-thumb data-full="<?= e(asset_url($image['image_path'])) ?>" aria-label="<?= e(sprintf(t('product.view_image', 'View image %d'), $index + 1)) ?>" aria-current="<?= $index === 0 ? 'true' : 'false' ?>">
              <img src="<?= e(asset_url($image['image_path'])) ?>" alt="" width="120" height="120" loading="lazy">
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="product-info">
      <a class="product-cat-link" href="<?= e(url('category/' . $product['category_slug'])) ?>"><?= e(category_label(['slug' => $product['category_slug'], 'name' => $product['category_name']])) ?></a>
      <h1><?= e($product['name']) ?></h1>
      <?php if ($reviewCount > 0): ?>
        <div class="product-rating">
          <?= rating_stars($ratingAvg) ?>
          <span><?= number_format($ratingAvg, 1) ?> · <?= $reviewCount ?> <?= $reviewCount === 1 ? e(t('product.review', 'review')) : e(t('product.reviews', 'reviews')) ?></span>
        </div>
      <?php endif; ?>
      <p class="product-short"><?= e($product['short_description']) ?></p>

      <div class="product-price-row">
        <span class="product-price" data-product-price><?= $selectedPrice !== null ? money($selectedPrice) : e(t('common.price_on_request', 'Price on request')) ?></span>
        <span class="product-mrp" data-product-mrp <?= $selectedMrp === null || $selectedPrice === null || $selectedMrp <= $selectedPrice ? 'hidden' : '' ?>><?= $selectedMrp !== null ? money($selectedMrp) : '' ?></span>
        <span class="product-save" data-product-save <?= $discount === null ? 'hidden' : '' ?>><?= $discount !== null ? e(sprintf(t('product.off', '%d%% off'), $discount)) : '' ?></span>
      </div>

      <div class="stock-line <?= $selectedPrice === null ? 'request' : ($selectedStock <= 0 ? 'out' : '') ?>" data-product-stock>
        <?php if ($selectedPrice === null): ?>
          <?= e(t('product.price_request_note', 'Price on request — contact us for a quotation.')) ?>
        <?php elseif ($selectedStock > 0): ?>
          <?= $selectedStock > 10 ? e(t('product.in_stock_ready', 'In stock — ready to dispatch')) : e(sprintf(t('product.only_left', 'Only %d left in stock'), $selectedStock)) ?>
        <?php else: ?>
          <?= e(t('product.out_stock_enquire', 'Out of stock — enquire for availability')) ?>
        <?php endif; ?>
      </div>

      <?php if ($product['description'] !== null && trim($product['description']) !== ''): ?>
        <p class="muted small"><?= nl2br(e($product['description'])) ?></p>
      <?php endif; ?>

      <form class="product-form" method="post" action="<?= e(url('product.php?slug=' . $product['slug'])) ?>" data-product-form>
        <?= csrf_field() ?>

        <?php if ($variants !== []): ?>
          <div class="variant-block">
            <div class="variant-label"><span><?= e(t('product.pack_size', 'Pack size')) ?></span><span><?= e(sprintf(t('product.options', '%d options'), count($variants))) ?></span></div>
            <div class="variant-options" role="radiogroup" aria-label="<?= e(t('product.choose_pack', 'Choose pack size')) ?>">
              <?php foreach ($variants as $variantRow): ?>
                <?php
                $vPrice = $variantRow['price'] !== null ? (float) $variantRow['price'] : null;
                $vMrp = $variantRow['mrp'] !== null ? (float) $variantRow['mrp'] : null;
                $vStock = (int) $variantRow['stock_qty'];
                ?>
                <label class="variant-chip <?= $vPrice !== null && $vStock <= 0 ? 'is-disabled' : '' ?>">
                  <input type="radio" name="variant_id" value="<?= (int) $variantRow['id'] ?>"
                    data-variant
                    data-price="<?= $vPrice !== null ? e((string) $vPrice) : '' ?>"
                    data-mrp="<?= $vMrp !== null ? e((string) $vMrp) : '' ?>"
                    data-stock="<?= $vStock ?>"
                    data-label="<?= e($variantRow['pack_size']) ?>"
                    <?= $selected && (int) $selected['id'] === (int) $variantRow['id'] ? 'checked' : '' ?>>
                  <span><?= e($variantRow['pack_size']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="buy-row">
          <div class="qty-stepper" data-qty>
            <button type="button" data-qty-minus aria-label="<?= e(t('product.decrease_qty', 'Decrease quantity')) ?>"><?= icon('minus') ?></button>
            <label class="sr-only" for="qty"><?= e(t('product.quantity', 'Quantity')) ?></label>
            <input id="qty" type="number" name="quantity" value="1" min="1" max="99" inputmode="numeric">
            <button type="button" data-qty-plus aria-label="<?= e(t('product.increase_qty', 'Increase quantity')) ?>"><?= icon('plus') ?></button>
          </div>
          <button class="btn btn-primary" type="submit" name="action" value="add" data-product-add data-add-to-cart="<?= $selected ? (int) $selected['id'] : '' ?>" <?= $purchasable ? '' : 'disabled' ?>>
            <?= icon('cart') ?> <?= e(t('common.add_to_cart', 'Add to cart')) ?>
          </button>
          <button class="btn btn-secondary" type="submit" name="action" value="buy_now" data-product-buy <?= $purchasable ? '' : 'disabled' ?>><?= e(t('product.buy_now', 'Buy now')) ?></button>
        </div>

        <div class="product-actions-secondary">
          <?php if (Auth::customerId()): ?>
            <button type="button" class="<?= $inWishlist ? 'is-active' : '' ?>" data-wishlist="<?= (int) $product['id'] ?>" aria-pressed="<?= $inWishlist ? 'true' : 'false' ?>">
              <?= icon('heart') ?> <span><?= $inWishlist ? e(t('product.saved', 'Saved to wishlist')) : e(t('product.save', 'Save to wishlist')) ?></span>
            </button>
          <?php else: ?>
            <a href="<?= e(url('login.php?next=' . urlencode('products/' . $product['slug']))) ?>"><?= icon('heart') ?> <?= e(t('product.login_save', 'Log in to save')) ?></a>
          <?php endif; ?>
          <a href="#reviews"><?= icon('star') ?> <?= e(t('product.read_reviews', 'Read reviews')) ?></a>
        </div>
      </form>

      <div class="product-trust">
        <div><?= icon('leaf') ?> <span><?= e(t('product.sourced', 'Sourced from Indian farms')) ?></span></div>
        <div><?= icon('package') ?> <span><?= e(t('product.packed', 'Packed with care in Varanasi')) ?></span></div>
        <div><?= icon('message') ?> <span><?= e(t('product.bulk', 'Need bulk pricing?')) ?> <a href="<?= e(url('wholesale.php')) ?>"><?= e(t('common.enquire', 'Enquire')) ?></a></span></div>
      </div>

      <div class="product-accordions">
        <details open>
          <summary><?= e(t('product.details', 'Product details')) ?> <?= icon('chevron-down') ?></summary>
          <div class="accordion-body">
            <dl class="detail-grid">
              <dt><?= e(t('product.category', 'Category')) ?></dt><dd><?= e(category_label(['slug' => $product['category_slug'], 'name' => $product['category_name']])) ?></dd>
              <?php if (!empty($product['product_type'])): ?><dt><?= e(t('product.type', 'Type')) ?></dt><dd><?= e(t('type.' . $product['product_type'], ucfirst($product['product_type']))) ?></dd><?php endif; ?>
              <?php if (!empty($product['origin'])): ?><dt><?= e(t('product.origin', 'Origin')) ?></dt><dd><?= e($product['origin']) ?></dd><?php endif; ?>
              <?php if (!empty($product['packaging_info'])): ?><dt><?= e(t('product.packaging', 'Packaging')) ?></dt><dd><?= e($product['packaging_info']) ?></dd><?php endif; ?>
              <dt><?= e(t('product.pack_sizes', 'Pack sizes')) ?></dt><dd><?= e(implode(', ', array_column($variants, 'pack_size'))) ?></dd>
              <?php if (!empty($product['net_quantity_info'])): ?><dt><?= e(t('product.net_qty', 'Net quantity')) ?></dt><dd><?= e($product['net_quantity_info']) ?></dd><?php endif; ?>
              <?php if (!empty($product['manufacturer_info'])): ?><dt><?= e(t('product.manufacturer', 'Manufacturer')) ?></dt><dd><?= e($product['manufacturer_info']) ?></dd><?php endif; ?>
            </dl>
          </div>
        </details>
        <details>
          <summary><?= e(t('product.ingredients', 'Ingredients & allergens')) ?> <?= icon('chevron-down') ?></summary>
          <div class="accordion-body">
            <?php if (!empty($product['ingredients'])): ?>
              <p><strong><?= e(t('product.ingredients_label', 'Ingredients:')) ?></strong> <?= e($product['ingredients']) ?></p>
            <?php else: ?>
              <p class="product-meta-pending"><?= e(t('product.ingredients_pending', 'Ingredient details pending admin input. The approved pack carries the full declaration.')) ?></p>
            <?php endif; ?>
            <?php if (!empty($product['allergen_info'])): ?>
              <p><strong><?= e(t('product.allergens_label', 'Allergens:')) ?></strong> <?= e($product['allergen_info']) ?></p>
            <?php else: ?>
              <p class="product-meta-pending"><?= e(t('product.allergens_pending', 'Allergen declaration pending admin input. Please check the approved pack or contact us for batch-specific details.')) ?></p>
            <?php endif; ?>
          </div>
        </details>
        <details>
          <summary><?= e(t('product.storage', 'Storage & care')) ?> <?= icon('chevron-down') ?></summary>
          <div class="accordion-body">
            <p><?= e($product['storage_instructions'] ?: t('product.storage_default', 'Store in a cool, dry place in an airtight container.')) ?></p>
          </div>
        </details>
        <details>
          <summary><?= e(t('product.nutrition', 'Nutritional information')) ?> <?= icon('chevron-down') ?></summary>
          <div class="accordion-body">
            <?php if (!empty($product['nutritional_info'])): ?>
              <p><?= nl2br(e($product['nutritional_info'])) ?></p>
            <?php else: ?>
              <p class="product-meta-pending"><?= e(t('product.nutrition_pending', 'Nutritional facts pending admin input. They are printed on the approved pack and can be shared for a specific batch on request.')) ?></p>
            <?php endif; ?>
          </div>
        </details>
        <details>
          <summary><?= e(t('product.delivery', 'Delivery & enquiries')) ?> <?= icon('chevron-down') ?></summary>
          <div class="accordion-body">
            <p><?= e(t('product.delivery_text_1', 'Delivery charges and timelines are confirmed by our team based on destination and order size. For wholesale or export quantities, use the')) ?> <a href="<?= e(url('wholesale.php')) ?>"><?= e(t('nav.wholesale', 'wholesale')) ?></a> <?= e(t('product.or', 'or')) ?> <a href="<?= e(url('export-enquiry.php')) ?>"><?= e(t('product.export_enquiry', 'export enquiry')) ?></a> <?= e(t('product.delivery_text_2', 'form.')) ?></p>
          </div>
        </details>
        <?php if (!empty($product['fssai_info'])): ?>
          <details>
            <summary><?= e(t('product.statutory', 'Statutory information')) ?> <?= icon('chevron-down') ?></summary>
            <div class="accordion-body"><p><?= e($product['fssai_info']) ?></p></div>
          </details>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($variants !== []): ?>
  <div class="sticky-mobile-bar">
    <span class="price" data-sticky-price><strong><?= $selectedPrice !== null ? money($selectedPrice) : e(t('common.price_on_request', 'Price on request')) ?></strong><small><?= $selected ? e($selected['pack_size']) : '' ?></small></span>
    <button class="btn btn-primary" type="button" data-sticky-add <?= $purchasable ? '' : 'disabled' ?>><?= icon('cart') ?> <?= e(t('common.add_to_cart', 'Add to cart')) ?></button>
  </div>
<?php endif; ?>

<?php if ($related !== []): ?>
  <section class="section-block">
    <div class="container">
      <div class="section-head-row">
        <div class="section-head">
          <p class="eyebrow"><?= e(t('product.also_like', 'You may also like')) ?></p>
          <h2><?= e(sprintf(t('product.more_from', 'More from %s'), category_label(['slug' => $product['category_slug'], 'name' => $product['category_name']]))) ?></h2>
        </div>
        <a class="text-link" href="<?= e(url('category/' . $product['category_slug'])) ?>"><?= e(t('product.view_category', 'View category')) ?> <?= icon('arrow-right') ?></a>
      </div>
      <div class="product-grid">
        <?php $mainProduct = $product; ?>
        <?php foreach ($related as $relatedProduct): ?>
          <?php $product = $relatedProduct; require BASE_PATH . '/app/views/partials/product-card.php'; ?>
        <?php endforeach; ?>
        <?php $product = $mainProduct; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php if ($reviewsEnabled): ?>
  <section class="section-block" id="reviews">
    <div class="container">
      <div class="section-head">
        <p class="eyebrow"><?= e(t('product.reviews_eyebrow', 'Customer reviews')) ?></p>
        <h2><?= e(t('product.reviews_title', 'What customers are saying')) ?></h2>
        <p class="section-lead"><?= e(t('product.reviews_note', 'Only reviews approved by our team are shown. We publish reviews from real customers, never samples.')) ?></p>
      </div>
      <div class="reviews-summary">
        <span class="reviews-score"><?= $reviewCount > 0 ? number_format($ratingAvg, 1) : '—' ?></span>
        <div>
          <?= rating_stars($reviewCount > 0 ? $ratingAvg : 0) ?>
          <p class="muted small mb-0"><?= $reviewCount ?> published <?= $reviewCount === 1 ? e(t('product.review', 'review')) : e(t('product.reviews', 'reviews')) ?></p>
        </div>
      </div>

      <?php if ($reviews === []): ?>
        <p class="muted small"><?= e(t('product.no_reviews', 'No reviews have been published for this product yet.')) ?></p>
      <?php else: ?>
        <div class="review-list">
          <?php foreach ($reviews as $review): ?>
            <article class="review-item">
              <div class="review-head">
                <span class="review-author"><?= e($review['name']) ?></span>
                <span class="review-date"><?= e(date_display($review['created_at'])) ?></span>
              </div>
              <?= rating_stars((float) $review['rating']) ?>
              <?php if (!empty($review['title'])): ?><p class="review-body"><strong><?= e($review['title']) ?></strong></p><?php endif; ?>
              <p class="review-body"><?= nl2br(e($review['body'])) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="review-form-card mt-2" style="max-width:720px">
        <h3><?= e(t('product.write_review', 'Write a review')) ?></h3>
        <?php if (Auth::customer()): ?>
          <form method="post" action="<?= e(url('product.php?slug=' . $product['slug'] . '#reviews')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="review">
            <div class="form-grid">
              <div class="field">
                <label for="review-rating"><?= e(t('product.rating', 'Rating')) ?> <span class="req">*</span></label>
                <select id="review-rating" name="rating" required>
                  <option value="5"><?= e(t('product.rating_5', '5 — Excellent')) ?></option>
                  <option value="4"><?= e(t('product.rating_4', '4 — Good')) ?></option>
                  <option value="3"><?= e(t('product.rating_3', '3 — Average')) ?></option>
                  <option value="2"><?= e(t('product.rating_2', '2 — Below expectations')) ?></option>
                  <option value="1"><?= e(t('product.rating_1', '1 — Poor')) ?></option>
                </select>
              </div>
              <div class="field">
                <label for="review-title"><?= e(t('product.review_title', 'Title')) ?> <span class="muted"><?= e(t('product.optional', '(optional)')) ?></span></label>
                <input id="review-title" type="text" name="title" maxlength="190" placeholder="<?= e(t('product.review_title_placeholder', 'A short summary')) ?>">
              </div>
              <div class="field is-full">
                <label for="review-body"><?= e(t('product.review_body', 'Your review')) ?> <span class="req">*</span></label>
                <textarea id="review-body" name="body" rows="4" minlength="10" maxlength="2000" required placeholder="<?= e(t('product.review_placeholder', 'Tell other customers what you thought of the product.')) ?>"></textarea>
                <span class="help"><?= e(t('product.review_help', 'Reviews are moderated before publishing. Please do not include personal contact details.')) ?></span>
              </div>
              <div class="field is-full">
                <button class="btn btn-primary" type="submit"><?= e(t('product.submit_review', 'Submit review')) ?></button>
              </div>
            </div>
          </form>
        <?php else: ?>
          <p class="muted small"><?= e(t('product.login_review_lead', 'Please')) ?> <a href="<?= e(url('login.php?next=' . urlencode('products/' . $product['slug'] . '#reviews'))) ?>"><?= e(t('nav.login', 'log in')) ?></a> <?= e(t('product.only_accounts', 'to write a review. Only customer accounts can submit reviews.')) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
