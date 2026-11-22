<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$customer = Auth::customer();

if (is_post()) {
    verify_csrf();
    $action = post_str('action');

    if ($action === 'update') {
        $variantId = post_int('variant_id');
        $quantity = max(0, min(99, post_int('quantity', 1)));
        $result = Cart::updateQuantity($variantId, $quantity);
        if (!$result['ok']) {
            flash('error', $result['message']);
        } else {
            flash('success', t('cart.updated', 'Your cart has been updated.'));
        }
        redirect('cart.php');
    }

    if ($action === 'remove') {
        $variantId = post_int('variant_id');
        if ($variantId > 0) {
            Cart::remove($variantId);
            flash('success', t('cart.removed', 'Item removed from your cart.'));
        }
        redirect('cart.php');
    }

    if ($action === 'coupon') {
        if (!RateLimiter::hit('coupon:' . request_ip(), 'apply', 12, 900)) {
            flash('error', t('cart.coupon_limit', 'Too many coupon attempts. Please wait a few minutes and try again.'));
            redirect('cart.php');
        }
        $code = strtoupper(post_str('coupon_code'));
        if ($code === '') {
            flash('error', t('cart.coupon_enter', 'Please enter a coupon code.'));
            redirect('cart.php');
        }
        $items = Cart::detailedItems();
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += (float) $item['line_total'];
        }
        $result = Coupon::evaluate($code, $items, $subtotal, Auth::customerId(), $customer['email'] ?? null);
        if ($result['ok']) {
            $_SESSION['coupon_code'] = $code;
            flash('success', $result['message']);
        } else {
            flash('error', $result['message'] !== '' ? $result['message'] : t('cart.coupon_failed', 'This coupon cannot be applied.'));
        }
        redirect('cart.php');
    }

    if ($action === 'coupon_remove') {
        unset($_SESSION['coupon_code']);
        flash('success', t('cart.coupon_removed', 'Coupon removed.'));
        redirect('cart.php');
    }
}

$summary = Cart::summary(null, $customer['email'] ?? null, Auth::customerId());
$items = $summary['items'];
$hasBlockingIssues = $summary['issues'] !== [];
$canCheckout = $items !== [] && !$hasBlockingIssues;
$coupon = $summary['coupon'];
$seo = [
    'title' => t('cart.title', 'Your cart') . ' | ' . setting('site_name', 'Daily Dose Export'),
    'description' => t('cart.meta', 'Review the items in your Daily Dose Export cart before checkout.'),
    'canonical' => url('cart.php'),
    'robots' => 'noindex,nofollow',
    'jsonld' => [
        breadcrumb_jsonld([['label' => t('nav.home', 'Home'), 'href' => ''], ['label' => t('nav.cart', 'Cart')]]),
    ],
];
$activeNav = 'shop';
$bodyClass = 'cart';
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="container">
  <?= breadcrumbs_html([['label' => t('nav.home', 'Home'), 'href' => ''], ['label' => t('nav.cart', 'Cart')]]) ?>
  <div style="padding-top:18px">
    <h1 style="font-size:clamp(1.9rem,4vw,2.6rem)"><?= e(t('cart.title', 'Your cart')) ?></h1>
    <p class="muted small"><?= e(t('cart.note', 'Prices and totals are calculated on our server at checkout. Availability is confirmed before your order is accepted.')) ?></p>
  </div>

  <div class="cart-layout">
    <div>
      <?php if ($items === []): ?>
        <div class="cart-empty">
          <?= icon('cart', 'icon') ?>
          <h2><?= e(t('cart.empty_title', 'Your cart is empty')) ?></h2>
          <p><?= e(t('cart.empty_text', 'Explore spices, grains, millets, cookies, teas, salt and dry fruits from our pantry.')) ?></p>
          <div class="btn-row" style="justify-content:center">
            <a class="btn btn-primary" href="<?= e(url('shop.php')) ?>"><?= e(t('cart.start_shopping', 'Start shopping')) ?></a>
            <a class="btn btn-ghost" href="<?= e(url('wholesale.php')) ?>"><?= e(t('cart.wholesale', 'Wholesale enquiry')) ?></a>
          </div>
        </div>
      <?php else: ?>
        <?php if ($summary['issues'] !== []): ?>
          <div class="cart-issues" role="alert">
            <strong><?= e(t('cart.review_items', 'Please review these items before checkout:')) ?></strong>
            <ul>
              <?php foreach ($summary['issues'] as $issue): ?><li><?= e($issue) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <div class="cart-list">
          <?php foreach ($items as $item): ?>
            <div class="cart-row">
              <a class="cart-thumb" href="<?= e(url('products/' . $item['slug'])) ?>">
                <img src="<?= e(asset_url($item['image'])) ?>" alt="<?= e($item['name']) ?>" width="120" height="120" loading="lazy">
              </a>
              <div>
                <h3 class="cart-item-name"><a href="<?= e(url('products/' . $item['slug'])) ?>"><?= e($item['name']) ?></a></h3>
                <p class="cart-item-meta"><?= e($item['pack_size']) ?> · SKU <?= e($item['sku']) ?></p>
                <div class="cart-item-controls">
                  <form method="post" action="<?= e(url('cart.php')) ?>" data-cart-update="<?= (int) $item['variant_id'] ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="variant_id" value="<?= (int) $item['variant_id'] ?>">
                    <div class="qty-stepper" data-qty>
                      <button type="button" data-qty-minus aria-label="<?= e(sprintf(t('product.decrease_qty_item', 'Decrease quantity of %s'), $item['name'])) ?>"><?= icon('minus') ?></button>
                      <label class="sr-only" for="qty-<?= (int) $item['variant_id'] ?>"><?= e(t('product.quantity', 'Quantity')) ?></label>
                      <input id="qty-<?= (int) $item['variant_id'] ?>" type="number" name="quantity" value="<?= (int) $item['quantity'] ?>" min="1" max="<?= max(1, (int) $item['stock_qty']) ?>" inputmode="numeric">
                      <button type="button" data-qty-plus aria-label="<?= e(sprintf(t('product.increase_qty_item', 'Increase quantity of %s'), $item['name'])) ?>"><?= icon('plus') ?></button>
                    </div>
                    <button class="remove-btn" type="submit" name="action" value="update"><?= e(t('cart.update', 'Update')) ?></button>
                  </form>
                  <form method="post" action="<?= e(url('cart.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="variant_id" value="<?= (int) $item['variant_id'] ?>">
                    <button class="remove-btn" type="submit" data-cart-remove="<?= (int) $item['variant_id'] ?>"><?= icon('trash') ?> <?= e(t('cart.remove', 'Remove')) ?></button>
                  </form>
                </div>
              </div>
              <div class="cart-line-price">
                <?= e(money($item['line_total'])) ?>
                <small><?= e(money($item['price'])) ?> × <?= (int) $item['quantity'] ?></small>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <a class="cart-continue text-link" href="<?= e(url('shop.php')) ?>"><?= icon('arrow-right') ?> <?= e(t('cart.continue', 'Continue shopping')) ?></a>
      <?php endif; ?>
    </div>

    <?php if ($items !== []): ?>
      <aside class="summary-card" aria-label="<?= e(t('cart.summary', 'Order summary')) ?>">
        <h2><?= e(t('cart.summary', 'Order summary')) ?></h2>
        <?php if ($coupon !== null && $summary['coupon_ok']): ?>
          <div class="coupon-applied">
            <span><?= e(t('cart.coupon_applied', 'Coupon')) ?> <strong><?= e($coupon['code']) ?></strong> <?= e(t('cart.applied', 'applied')) ?></span>
            <form method="post" action="<?= e(url('cart.php')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="coupon_remove">
              <button type="submit" data-coupon-remove><?= e(t('cart.remove_coupon', 'Remove')) ?></button>
            </form>
          </div>
        <?php else: ?>
          <form class="coupon-form" method="post" action="<?= e(url('cart.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="coupon">
            <label class="sr-only" for="coupon_code"><?= e(t('cart.coupon_placeholder', 'Coupon code')) ?></label>
            <input id="coupon_code" type="text" name="coupon_code" placeholder="<?= e(t('cart.coupon_placeholder', 'Coupon code')) ?>" maxlength="40" autocomplete="off">
            <button class="btn btn-ghost" type="submit"><?= e(t('cart.apply', 'Apply')) ?></button>
          </form>
          <?php if ($summary['coupon_message'] !== ''): ?>
            <p class="coupon-message"><?= e($summary['coupon_message']) ?></p>
          <?php endif; ?>
        <?php endif; ?>

        <div class="summary-line"><span><?= e(t('cart.subtotal', 'Subtotal')) ?></span><span><?= e(money($summary['subtotal'])) ?></span></div>
        <?php if ($summary['savings'] > 0): ?>
          <div class="summary-line is-discount"><span><?= e(t('cart.pack_savings', 'Pack savings')) ?></span><span>−<?= e(money($summary['savings'])) ?></span></div>
        <?php endif; ?>
        <?php if ($summary['discount'] > 0): ?>
          <div class="summary-line is-discount"><span><?= e(t('cart.coupon_discount', 'Coupon discount')) ?></span><span>−<?= e(money($summary['discount'])) ?></span></div>
        <?php endif; ?>
        <div class="summary-line"><span><?= e(t('cart.shipping', 'Shipping')) ?></span><span><?= $summary['shipping'] > 0 ? e(money($summary['shipping'])) : e(t('cart.to_confirm', 'To be confirmed')) ?></span></div>
        <?php if ($summary['tax'] > 0): ?>
          <div class="summary-line"><span><?= e(t('cart.tax_label', 'Tax')) ?> (<?= e((string) $summary['tax_percent']) ?>%)</span><span><?= e(money($summary['tax'])) ?></span></div>
        <?php endif; ?>
        <?php if ($summary['free_shipping_threshold'] > 0 && $summary['free_shipping_remaining'] > 0): ?>
          <p class="summary-note"><?= e(sprintf(t('cart.free_ship_remaining', 'Add %s more to qualify for free shipping.'), money($summary['free_shipping_remaining']))) ?></p>
        <?php endif; ?>
        <div class="summary-total"><span><?= e(t('cart.total', 'Total')) ?> <small><?= $summary['tax'] > 0 ? e(t('cart.incl_tax', 'incl. tax')) : e(t('cart.excl_tax', 'excl. tax')) ?></small></span><span><?= e(money($summary['total'])) ?></span></div>
        <?php if (((float) $summary['savings'] + (float) $summary['discount']) > 0): ?>
          <p class="summary-savings"><?= e(sprintf(t('cart.save_amount', 'You save %s on this order.'), money((float) $summary['savings'] + (float) $summary['discount']))) ?></p>
        <?php endif; ?>

        <?php if ($canCheckout): ?>
          <a class="btn btn-primary btn-block" href="<?= e(url('checkout.php')) ?>"><?= e(t('cart.checkout', 'Proceed to checkout')) ?> <?= icon('arrow-right') ?></a>
        <?php else: ?>
          <button class="btn btn-primary btn-block" type="button" disabled><?= e(t('cart.checkout_unavailable', 'Checkout unavailable')) ?></button>
          <p class="summary-note"><?= e(t('cart.resolve', 'Please resolve the highlighted item issues before checkout.')) ?></p>
        <?php endif; ?>
        <p class="summary-note"><?= e(localized_setting('shipping_note')) ?></p>
      </aside>
    <?php endif; ?>
  </div>
</div>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
