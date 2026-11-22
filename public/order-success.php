<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$orderId = isset($_GET['order']) ? (int) $_GET['order'] : 0;
if ($orderId <= 0 || !Order::can_view($orderId)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
$order = Order::find($orderId);
if ($order === null) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$status = (string) $order['status'];
$paymentStatus = (string) $order['payment_status'];
$isCancelled = in_array($status, ['cancelled', 'returned', 'refunded'], true);
$isDelivered = $status === 'delivered';
$isShipped = in_array($status, ['shipped', 'out_for_delivery', 'delivered'], true);
$isConfirmed = in_array($status, ['confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery', 'delivered'], true) || $paymentStatus === 'paid';

$seo = [
    'title' => 'Order ' . $order['order_number'] . ' | ' . setting('site_name', 'Daily Dose Export'),
    'description' => 'Your Daily Dose Export order details.',
    'canonical' => url('order-success.php?order=' . $orderId),
    'robots' => 'noindex,nofollow',
];
$activeNav = 'shop';
$bodyClass = 'order-success';
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="container">
  <div class="success-wrap">
    <div class="success-card invoice-card">
      <div class="success-icon"><?= icon($isCancelled ? 'alert' : 'check') ?></div>
      <h1><?= $isCancelled ? 'Order ' . e(order_status_label($status)) : 'Thank you for your order' ?></h1>
      <p class="success-lead">
        <?php if ($isCancelled): ?>
          This order is no longer active. If you need help, please contact our team with the order number below.
        <?php elseif ($paymentStatus === 'paid'): ?>
          Your payment is confirmed and your order has been received. We will update you as it moves through packing and dispatch.
        <?php elseif ($order['payment_method'] === 'razorpay'): ?>
          Your order has been received. It will be confirmed once the online payment is completed.
        <?php else: ?>
          Your order has been received. Our team will confirm availability and delivery details with you.
        <?php endif; ?>
      </p>

      <div class="order-ref">
        <small>Order number</small>
        <strong><?= e($order['order_number']) ?></strong>
      </div>

      <?php if (!$isCancelled && $paymentStatus !== 'paid' && $order['payment_method'] === 'razorpay' && Payment::enabled()): ?>
        <div class="alert" style="text-align:left">Payment is still pending. <?= icon('lock') ?> <a href="<?= e(url('pay.php?order=' . $orderId)) ?>">Complete payment now</a> to confirm your order.</div>
      <?php endif; ?>
      <?php if ($paymentStatus === 'failed'): ?>
        <div class="alert alert-error" style="text-align:left">The last payment attempt failed. If money was deducted, please contact us with your order number. You can <a href="<?= e(url('pay.php?order=' . $orderId)) ?>">try the payment again</a>.</div>
      <?php endif; ?>

      <div class="order-detail-card" style="text-align:left">
        <div class="order-detail-head">
          <h2>Order details</h2>
          <span class="<?= e(status_class($status)) ?>"><?= e(order_status_label($status)) ?></span>
        </div>
        <dl class="order-meta-grid">
          <div><dt>Placed on</dt><dd><?= e(date_display($order['created_at'], true)) ?></dd></div>
          <div><dt>Payment</dt><dd><?= $order['payment_method'] === 'cod' ? 'Cash on delivery' : 'Online payment' ?> — <?= e(payment_status_label($paymentStatus)) ?></dd></div>
          <div><dt>Contact</dt><dd><?= e($order['customer_name']) ?><br><?= e($order['email']) ?><br><?= e($order['phone']) ?></dd></div>
          <div><dt>Deliver to</dt><dd><?= e($order['shipping_full_name']) ?><br><?= e(full_address($order)) ?></dd></div>
          <?php if (!empty($order['tracking_number'])): ?>
            <div><dt>Tracking</dt><dd><?= e($order['tracking_number']) ?><?php if (!empty($order['tracking_url'])): ?> — <a href="<?= e($order['tracking_url']) ?>" target="_blank" rel="noopener">Track parcel</a><?php endif; ?></dd></div>
          <?php endif; ?>
          <?php if (!empty($order['customer_notes'])): ?>
            <div><dt>Your notes</dt><dd><?= e($order['customer_notes']) ?></dd></div>
          <?php endif; ?>
        </dl>

        <table class="order-table">
          <thead><tr><th>Item</th><th>Qty</th><th style="text-align:right">Amount</th></tr></thead>
          <tbody>
            <?php foreach ($order['items'] as $item): ?>
              <tr>
                <td><?= e($item['product_name']) ?><br><span class="muted small"><?= e($item['variant_label']) ?> · SKU <?= e($item['sku']) ?></span></td>
                <td><?= (int) $item['quantity'] ?></td>
                <td style="text-align:right"><?= e(money($item['line_total'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="order-totals">
          <div class="summary-line"><span>Subtotal</span><span><?= e(money($order['subtotal'])) ?></span></div>
          <?php if ((float) $order['discount'] > 0): ?>
            <div class="summary-line is-discount"><span>Discount<?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?></span><span>−<?= e(money($order['discount'])) ?></span></div>
          <?php endif; ?>
          <div class="summary-line"><span>Shipping</span><span><?= (float) $order['shipping_fee'] > 0 ? e(money($order['shipping_fee'])) : 'To be confirmed' ?></span></div>
          <?php if ((float) $order['tax_total'] > 0): ?>
            <div class="summary-line"><span>Tax</span><span><?= e(money($order['tax_total'])) ?></span></div>
          <?php endif; ?>
          <div class="summary-total"><span>Total</span><span><?= e(money($order['total'])) ?></span></div>
        </div>
      </div>

      <?php if (!$isCancelled): ?>
        <div class="order-detail-card" style="text-align:left">
          <h2>Progress</h2>
          <div class="timeline">
            <div class="timeline-step is-done">
              <span class="timeline-dot"><?= icon('check') ?></span>
              <div><strong>Order received</strong><span><?= e(date_display($order['created_at'], true)) ?></span></div>
            </div>
            <div class="timeline-step <?= $isConfirmed ? 'is-done' : '' ?>">
              <span class="timeline-dot"><?= icon($isConfirmed ? 'check' : 'calendar') ?></span>
              <div><strong>Confirmed</strong><span>Our team confirms availability and delivery details.</span></div>
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
        </div>
      <?php endif; ?>

      <div class="success-actions">
        <a class="btn btn-primary" href="<?= e(url('shop.php')) ?>">Continue shopping</a>
        <a class="btn btn-ghost" href="<?= e(url('track-order.php?order=' . urlencode($order['order_number']))) ?>">Track this order</a>
        <button class="btn btn-ghost" type="button" data-print><?= icon('download') ?> Print summary</button>
      </div>
      <p class="summary-note" style="margin-top:18px">A confirmation email is sent to <?= e($order['email']) ?> when email delivery is configured. Keep your order number handy for any questions.</p>
    </div>
  </div>
</div>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
