<?php
declare(strict_types=1);

/** @var array $product */
$wishlistIds = $wishlistIds ?? [];
$minPrice = $product['min_price'] !== null ? (float) $product['min_price'] : null;
$minMrp = $product['min_mrp'] !== null ? (float) $product['min_mrp'] : null;
$discount = discount_percent($minPrice, $minMrp);
$inStock = (int) ($product['stock_total'] ?? 0) > 0;
$ratingAvg = (float) ($product['rating_avg'] ?? 0);
$reviewCount = (int) ($product['review_count'] ?? 0);
$isWish = in_array((int) $product['id'], array_map('intval', $wishlistIds), true);
$productUrl = url('products/' . $product['slug']);
?>
<article class="product-card">
  <div class="card-media">
    <a class="card-media-link" href="<?= e($productUrl) ?>" aria-label="<?= e($product['name']) ?>">
      <img src="<?= e(asset_url($product['primary_image'])) ?>" alt="<?= e($product['name']) ?>" width="640" height="640" loading="lazy" decoding="async">
      <?php if ($discount !== null): ?>
        <span class="badge badge-save"><?= $discount ?>% off</span>
      <?php elseif (($product['is_new'] ?? 0) == 1): ?>
        <span class="badge badge-new">New</span>
      <?php endif; ?>
    </a>
    <button class="wish-btn <?= $isWish ? 'is-active' : '' ?>" type="button" data-wishlist="<?= (int) $product['id'] ?>" aria-label="<?= $isWish ? 'Remove from wishlist' : 'Add to wishlist' ?>" aria-pressed="<?= $isWish ? 'true' : 'false' ?>">
      <?= icon('heart') ?>
    </button>
  </div>
  <div class="card-body">
    <p class="card-cat"><?= e($product['category_name']) ?></p>
    <h3 class="card-title"><a href="<?= e($productUrl) ?>"><?= e($product['name']) ?></a></h3>
    <p class="card-desc"><?= e(excerpt($product['short_description'], 72)) ?></p>
    <?php if ($reviewCount > 0): ?>
      <p class="card-rating"><?= rating_stars($ratingAvg) ?> <span><?= number_format($ratingAvg, 1) ?> (<?= $reviewCount ?>)</span></p>
    <?php endif; ?>
    <div class="card-price-row">
      <?php if ($minPrice !== null): ?>
        <p class="card-price"><span class="price-from">From</span> <?= money($minPrice) ?></p>
        <?php if ($minMrp !== null && $minMrp > $minPrice): ?><p class="card-mrp"><?= money($minMrp) ?></p><?php endif; ?>
      <?php else: ?>
        <p class="card-price is-request">Price on request</p>
      <?php endif; ?>
    </div>
    <p class="card-stock <?= $inStock && $minPrice !== null ? 'in' : 'out' ?>">
      <?= $minPrice === null ? 'Priced per order' : ($inStock ? 'In stock' : 'Out of stock') ?>
    </p>
    <div class="card-actions">
      <?php if (!empty($product['default_variant_id']) && $inStock): ?>
        <button class="btn btn-primary btn-sm btn-add" type="button" data-add-to-cart="<?= (int) $product['default_variant_id'] ?>" data-product-name="<?= e($product['name']) ?>">
          <?= icon('cart') ?> Add to cart
        </button>
      <?php endif; ?>
      <a class="btn btn-ghost btn-sm <?= !empty($product['default_variant_id']) && $inStock ? '' : 'btn-block' ?>" href="<?= e($productUrl) ?>">
        <?= $minPrice === null ? 'Enquire' : 'View details' ?>
      </a>
    </div>
  </div>
</article>
