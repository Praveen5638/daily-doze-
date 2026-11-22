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
    'title' => t('order.number', 'Order number') . ' ' . $order['order_number'] . ' | ' . setting('site_name', 'Daily Dose Export'),
    'description' => t('order.meta', 'Your Daily Dose Export order details.'),
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
      <h1><?= $isCancelled ? e(t('order.status_heading', 'Order') . ' ' . order_status_label($status)) : e(t('order.thank_you', 'Thank you for your order')) ?></h1>
      <p class="success-lead">
        <?php if ($isCancelled): ?>
          <?= e(t('order.cancelled_text', 'This order is no longer active. If you need help, please contact our team with the order number below.')) ?>
        <?php elseif ($paymentStatus === 'paid'): ?>
          <?= e(t('order.paid_text', 'Your payment is confirmed and your order has been received. We will update you as it moves through packing and dispatch.')) ?>
        <?php elseif ($order['payment_method'] === 'razorpay'): ?>
          <?= e(t('order.razorpay_pending_text', 'Your order has been received. It will be confirmed once the online payment is completed.')) ?>
        <?php else: ?>
          <?= e(t('order.received_text', 'Your order has been received. Our team will confirm availability and delivery details with you.')) ?>
        <?php endif; ?>
      </p>

      <div class="order-ref">
        <small><?= e(t('order.number', 'Order number')) ?></small>
        <strong><?= e($order['order_number']) ?></strong>
      </div>

      <?php if (!$isCancelled && $paymentStatus !== 'paid' && $order['payment_method'] === 'razorpay' && Payment::enabled()): ?>
        <div class="alert" style="text-align:left"><?= e(t('order.payment_pending', 'Payment is still pending.')) ?> <?= icon('lock') ?> <a href="<?= e(url('pay.php?order=' . $orderId)) ?>"><?= e(t('order.complete_payment', 'Complete payment now')) ?></a> <?= e(t('order.to_confirm', 'to confirm your order.')) ?></div>
      <?php endif; ?>
      <?php if ($paymentStatus === 'failed'): ?>
        <div class="alert alert-error" style="text-align:left"><?= e(t('order.payment_failed_text', 'The last payment attempt failed. If money was deducted, please contact us with your order number. You can')) ?> <a href="<?= e(url('pay.php?order=' . $orderId)) ?>"><?= e(t('order.try_again', 'try the payment again')) ?></a>.</div>
      <?php endif; ?>

      <div class="order-detail-card" style="text-align:left">
        <div class="order-detail-head">
          <h2><?= e(t('order.details', 'Order details')) ?></h2>
          <span class="<?= e(status_class($status)) ?>"><?= e(order_status_label($status)) ?></span>
        </div>
        <dl class="order-meta-grid">
          <div><dt><?= e(t('order.placed_on', 'Placed on')) ?></dt><dd><?= e(date_display($order['created_at'], true)) ?></dd></div>
          <div><dt><?= e(t('order.payment', 'Payment')) ?></dt><dd><?= e($order['payment_method'] === 'cod' ? t('order.cash_on_delivery', 'Cash on delivery') : t('order.online_payment', 'Online payment')) ?> — <?= e(payment_status_label($paymentStatus)) ?></dd></div>
          <div><dt><?= e(t('order.contact', 'Contact')) ?></dt><dd><?= e($order['customer_name']) ?><br><?= e($order['email']) ?><br><?= e($order['phone']) ?></dd></div>
          <div><dt><?= e(t('order.deliver_to', 'Deliver to')) ?></dt><dd><?= e($order['shipping_full_name']) ?><br><?= e(full_address($order)) ?></dd></div>
          <?php if (!empty($order['tracking_number'])): ?>
            <div><dt><?= e(t('order.tracking', 'Tracking')) ?></dt><dd><?= e($order['tracking_number']) ?><?php if (!empty($order['tracking_url'])): ?> — <a href="<?= e($order['tracking_url']) ?>" target="_blank" rel="noopener"><?= e(t('order.track_parcel', 'Track parcel')) ?></a><?php endif; ?></dd></div>
          <?php endif; ?>
          <?php if (!empty($order['customer_notes'])): ?>
            <div><dt><?= e(t('order.your_notes', 'Your notes')) ?></dt><dd><?= e($order['customer_notes']) ?></dd></div>
          <?php endif; ?>
        </dl>

        <table class="order-table">
          <thead><tr><th><?= e(t('order.item', 'Item')) ?></th><th><?= e(t('order.qty', 'Qty')) ?></th><th style="text-align:right"><?= e(t('order.amount', 'Amount')) ?></th></tr></thead>
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
          <div class="summary-line"><span><?= e(t('order.subtotal', 'Subtotal')) ?></span><span><?= e(money($order['subtotal'])) ?></span></div>
          <?php if ((float) $order['discount'] > 0): ?>
            <div class="summary-line is-discount"><span><?= e(t('order.discount', 'Discount')) ?><?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?></span><span>−<?= e(money($order['discount'])) ?></span></div>
          <?php endif; ?>
          <div class="summary-line"><span><?= e(t('order.shipping', 'Shipping')) ?></span><span><?= (float) $order['shipping_fee'] > 0 ? e(money($order['shipping_fee'])) : e(t('order.to_be_confirmed', 'To be confirmed')) ?></span></div>
          <?php if ((float) $order['tax_total'] > 0): ?>
            <div class="summary-line"><span><?= e(t('order.tax', 'Tax')) ?></span><span><?= e(money($order['tax_total'])) ?></span></div>
          <?php endif; ?>
          <div class="summary-total"><span><?= e(t('order.total', 'Total')) ?></span><span><?= e(money($order['total'])) ?></span></div>
        </div>
      </div>

      <?php if (!$isCancelled): ?>
        <div class="order-detail-card" style="text-align:left">
          <h2><?= e(t('order.progress', 'Progress')) ?></h2>
          <div class="timeline">
            <div class="timeline-step is-done">
              <span class="timeline-dot"><?= icon('check') ?></span>
              <div><strong><?= e(t('order.received', 'Order received')) ?></strong><span><?= e(date_display($order['created_at'], true)) ?></span></div>
            </div>
            <div class="timeline-step <?= $isConfirmed ? 'is-done' : '' ?>">
              <span class="timeline-dot"><?= icon($isConfirmed ? 'check' : 'calendar') ?></span>
              <div><strong><?= e(t('order.confirmed', 'Confirmed')) ?></strong><span><?= e(t('order.confirmed_desc', 'Our team confirms availability and delivery details.')) ?></span></div>
            </div>
            <div class="timeline-step <?= $isShipped ? 'is-done' : '' ?>">
              <span class="timeline-dot"><?= icon($isShipped ? 'check' : 'truck') ?></span>
              <div><strong><?= e(t('order.packed_dispatched', 'Packed & dispatched')) ?></strong><span><?= !empty($order['shipped_at']) ? e(date_display($order['shipped_at'], true)) : e(t('order.tracking_shared', 'Tracking details are shared after dispatch where available.')) ?></span></div>
            </div>
            <div class="timeline-step <?= $isDelivered ? 'is-done' : '' ?>">
              <span class="timeline-dot"><?= icon($isDelivered ? 'check' : 'package') ?></span>
              <div><strong><?= e(t('order.delivered', 'Delivered')) ?></strong><span><?= !empty($order['delivered_at']) ? e(date_display($order['delivered_at'], true)) : e(t('order.delivery_timeline', 'Delivery timeline is confirmed by our team.')) ?></span></div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="success-actions">
        <a class="btn btn-primary" href="<?= e(url('shop.php')) ?>"><?= e(t('order.continue_shopping', 'Continue shopping')) ?></a>
        <a class="btn btn-ghost" href="<?= e(url('track-order.php?order=' . urlencode($order['order_number']))) ?>"><?= e(t('order.track_this', 'Track this order')) ?></a>
        <button class="btn btn-ghost" type="button" data-print><?= icon('download') ?> <?= e(t('order.print_summary', 'Print summary')) ?></button>
      </div>
      <p class="summary-note" style="margin-top:18px"><?= e(sprintf(t('order.confirmation_email', 'A confirmation email is sent to %s when email delivery is configured. Keep your order number handy for any questions.'), $order['email'])) ?></p>
    </div>
  </div>
</div>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
