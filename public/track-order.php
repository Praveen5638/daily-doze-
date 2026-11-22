<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$orderNumber = isset($_GET['order']) && is_string($_GET['order']) ? strtoupper(trim($_GET['order'])) : '';
$contact = '';
$order = null;
$error = '';

if (is_post()) {
    verify_csrf();
    $orderNumber = strtoupper(post_str('order_number'));
    $contact = post_str('contact');
    if ($orderNumber === '' || $contact === '') {
        $error = 'Please enter your order number and the email address or phone number used for the order.';
    } elseif (!RateLimiter::hit('track:' . request_ip(), 'lookup', 12, 900)) {
        $error = 'Too many tracking attempts. Please wait a few minutes and try again.';
    } else {
        $order = Order::lookup($orderNumber, $contact);
        if ($order === null) {
            $error = 'We could not find an order with those details. Please check the order number and contact information.';
        }
    }
}

$seo = [
    'title' => 'Track your order | ' . setting('site_name', 'Daily Dose Export'),
    'description' => 'Track the status of a Daily Dose Export order using your order number and contact details.',
    'canonical' => url('track-order.php'),
    'robots' => 'noindex,follow',
    'jsonld' => [
        breadcrumb_jsonld([['label' => 'Home', 'href' => ''], ['label' => 'Track order']]),
    ],
];
$activeNav = 'contact';
$bodyClass = 'track-order';
require BASE_PATH . '/app/views/layout/header.php';

$status = $order['status'] ?? '';
$isCancelled = $order && in_array($status, ['cancelled', 'returned', 'refunded'], true);
$isConfirmed = $order && (in_array($status, ['confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery', 'delivered'], true) || $order['payment_status'] === 'paid');
$isShipped = $order && in_array($status, ['shipped', 'out_for_delivery', 'delivered'], true);
$isDelivered = $order && $status === 'delivered';
?>

<div class="container">
  <?= breadcrumbs_html([['label' => 'Home', 'href' => ''], ['label' => 'Track order']]) ?>
  <div style="padding:22px 0 10px">
    <h1 style="font-size:clamp(1.9rem,4vw,2.6rem)">Track your order</h1>
    <p class="muted small">Enter your order number and the email address or phone number used at checkout. For privacy, both must match.</p>
  </div>

  <?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="track-card">
    <form method="post" action="<?= e(url('track-order.php')) ?>">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="field">
          <label for="order_number">Order number <span class="req">*</span></label>
          <input id="order_number" type="text" name="order_number" value="<?= e($orderNumber) ?>" placeholder="DDE-XXXXXX" required maxlength="30" autocomplete="off">
        </div>
        <div class="field">
          <label for="contact">Email or phone <span class="req">*</span></label>
          <input id="contact" type="text" name="contact" value="<?= e($contact) ?>" placeholder="you@example.com or mobile number" required maxlength="190" autocomplete="off">
        </div>
      </div>
      <div class="mt-2">
        <button class="btn btn-primary" type="submit">Find my order <?= icon('arrow-right') ?></button>
        <a class="btn btn-ghost" href="<?= e(url('contact.php')) ?>">Need help?</a>
      </div>
    </form>
  </div>

  <?php if ($order !== null): ?>
    <div class="order-detail-card" style="max-width:680px;margin-inline:auto">
      <div class="order-detail-head">
        <h2><?= e($order['order_number']) ?></h2>
        <span class="<?= e(status_class($status)) ?>"><?= e(order_status_label($status)) ?></span>
      </div>
      <dl class="order-meta-grid">
        <div><dt>Placed on</dt><dd><?= e(date_display($order['created_at'], true)) ?></dd></div>
        <div><dt>Payment</dt><dd><?= $order['payment_method'] === 'cod' ? 'Cash on delivery' : 'Online payment' ?> — <?= e(payment_status_label($order['payment_status'])) ?></dd></div>
        <div><dt>Items</dt><dd><?= count($order['items']) ?> item(s)</dd></div>
        <div><dt>Total</dt><dd><?= e(money($order['total'])) ?></dd></div>
        <?php if (!empty($order['tracking_number'])): ?>
          <div><dt>Tracking number</dt><dd><?= e($order['tracking_number']) ?></dd></div>
          <?php if (!empty($order['tracking_url'])): ?>
            <div><dt>Tracking link</dt><dd><a href="<?= e($order['tracking_url']) ?>" target="_blank" rel="noopener">Open tracking</a></dd></div>
          <?php endif; ?>
        <?php endif; ?>
      </dl>

      <?php if (!$isCancelled): ?>
        <div class="timeline" style="margin-top:20px">
          <div class="timeline-step is-done">
            <span class="timeline-dot"><?= icon('check') ?></span>
            <div><strong>Order received</strong><span><?= e(date_display($order['created_at'], true)) ?></span></div>
          </div>
          <div class="timeline-step <?= $isConfirmed ? 'is-done' : '' ?>">
            <span class="timeline-dot"><?= icon($isConfirmed ? 'check' : 'calendar') ?></span>
            <div><strong>Confirmed</strong><span>Availability and delivery details are confirmed by our team.</span></div>
          </div>
          <div class="timeline-step <?= $isShipped ? 'is-done' : '' ?>">
            <span class="timeline-dot"><?= icon($isShipped ? 'check' : 'truck') ?></span>
            <div><strong>Packed &amp; dispatched</strong><span><?= !empty($order['shipped_at']) ? e(date_display($order['shipped_at'], true)) : 'Tracking details are shared after dispatch where available.' ?></span></div>
          </div>
          <div class="timeline-step <?= $isDelivered ? 'is-done' : '' ?>">
            <span class="timeline-dot"><?= icon($isDelivered ? 'check' : 'package') ?></span>
            <div><strong>Delivered</strong><span><?= !empty($order['delivered_at']) ? e(date_display($order['delivered_at'], true)) : 'Delivery timeline is confirmed by our team.' ?></span></div>
          </div>
        </div>
      <?php else: ?>
        <p class="muted small">This order is marked as <?= e(strtolower(order_status_label($status))) ?>. Please contact us if you need more information.</p>
      <?php endif; ?>

      <h3 style="font-size:1rem;margin-top:22px">Items</h3>
      <table class="order-table">
        <thead><tr><th>Item</th><th>Qty</th><th style="text-align:right">Amount</th></tr></thead>
        <tbody>
          <?php foreach ($order['items'] as $item): ?>
            <tr>
              <td><?= e($item['product_name']) ?><br><span class="muted small"><?= e($item['variant_label']) ?></span></td>
              <td><?= (int) $item['quantity'] ?></td>
              <td style="text-align:right"><?= e(money($item['line_total'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="btn-row mt-2">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('contact.php')) ?>">Contact support</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('shop.php')) ?>">Continue shopping</a>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
