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
if ($order['payment_status'] === 'paid') {
    redirect('order-success.php?order=' . $orderId);
}
if ($order['payment_method'] !== 'razorpay' || !Payment::enabled()) {
    flash('info', t('pay.unavailable', 'Online payment is not available for this order. Please contact us for assistance.'));
    redirect('order-success.php?order=' . $orderId);
}
if ($order['status'] === 'cancelled') {
    flash('error', t('pay.cancelled', 'This order has been cancelled. Please contact us if you need help.'));
    redirect('order-success.php?order=' . $orderId);
}

$seo = [
    'title' => t('pay.title', 'Complete payment') . ' | ' . setting('site_name', 'Daily Dose Export'),
    'description' => t('pay.meta', 'Complete the online payment for your Daily Dose Export order.'),
    'canonical' => url('pay.php?order=' . $orderId),
    'robots' => 'noindex,nofollow',
];
$activeNav = 'shop';
$bodyClass = 'payment';
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="success-wrap">
  <div class="success-card" style="text-align:left">
    <p class="eyebrow"><?= e(t('pay.eyebrow', 'Secure payment')) ?></p>
    <h1><?= e(t('pay.title', 'Complete your payment')) ?></h1>
    <p class="success-lead" style="margin-left:0"><?= e(sprintf(t('pay.lead', 'Order %s is reserved. It will be confirmed once payment is successful.'), $order['order_number'])) ?></p>

    <div class="order-detail-card" style="margin-top:0">
      <div class="order-detail-head">
        <h2><?= e($order['order_number']) ?></h2>
        <span class="<?= e(status_class($order['payment_status'])) ?>"><?= e(payment_status_label($order['payment_status'])) ?></span>
      </div>
      <div class="summary-line"><span><?= e(t('pay.items', 'Items')) ?></span><span><?= count($order['items']) ?></span></div>
      <div class="summary-line"><span><?= e(t('pay.subtotal', 'Subtotal')) ?></span><span><?= e(money($order['subtotal'])) ?></span></div>
      <?php if ((float) $order['discount'] > 0): ?>
        <div class="summary-line is-discount"><span><?= e(t('pay.discount', 'Discount')) ?></span><span>−<?= e(money($order['discount'])) ?></span></div>
      <?php endif; ?>
      <?php if ((float) $order['shipping_fee'] > 0): ?>
        <div class="summary-line"><span><?= e(t('pay.shipping', 'Shipping')) ?></span><span><?= e(money($order['shipping_fee'])) ?></span></div>
      <?php endif; ?>
      <?php if ((float) $order['tax_total'] > 0): ?>
        <div class="summary-line"><span><?= e(t('pay.tax', 'Tax')) ?></span><span><?= e(money($order['tax_total'])) ?></span></div>
      <?php endif; ?>
      <div class="summary-total"><span><?= e(t('pay.amount', 'Amount to pay')) ?></span><span><?= e(money($order['total'])) ?></span></div>
    </div>

    <div id="pay-message" class="alert mt-2" role="status" hidden></div>

    <div class="btn-row mt-2">
      <button class="btn btn-primary btn-lg" type="button" data-pay-now="<?= (int) $order['id'] ?>">
        <?= icon('lock') ?> <?= e(t('pay.button', 'Pay securely with Razorpay')) ?>
      </button>
      <a class="btn btn-ghost" href="<?= e(url('order-success.php?order=' . $orderId)) ?>"><?= e(t('pay.pay_later', 'Pay later')) ?></a>
    </div>
    <p class="secure-note"><?= icon('shield') ?> <?= e(t('pay.secure_note', 'Payment is processed by Razorpay. Daily Dose Export does not store your card or UPI credentials.')) ?></p>
    <p class="summary-note"><?= e(sprintf(t('pay.deducted_note', 'If the amount was deducted and this page did not update, please contact us with order %s and the payment reference.'), $order['order_number'])) ?></p>
  </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var button = document.querySelector('[data-pay-now]');
  if (!button) return;
  var message = document.getElementById('pay-message');

  function showMessage(text, isError) {
    if (!message) return;
    message.textContent = text;
    message.className = 'alert mt-2' + (isError ? ' alert-error' : '');
    message.hidden = false;
  }

  function verifyPayment(response) {
    return fetch(window.DDE.apiUrl + '?action=razorpay.verify', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': window.DDE.csrf
      },
      body: JSON.stringify({
        order_id: Number(button.getAttribute('data-pay-now')),
        razorpay_order_id: response.razorpay_order_id,
        razorpay_payment_id: response.razorpay_payment_id,
        razorpay_signature: response.razorpay_signature
      })
    }).then(function (res) { return res.json(); }).then(function (data) {
      if (!data.ok) {
        throw new Error(data.message || <?= safe_json(t('pay.js_verify_failed', 'We could not verify the payment.')) ?>);
      }
      window.location.href = data.data.redirect;
    });
  }

  button.addEventListener('click', function () {
    if (typeof window.Razorpay === 'undefined') {
      showMessage(<?= safe_json(t('pay.js_not_loaded', 'The payment window is still loading. Please check your connection and try again.')) ?>, true);
      return;
    }
    button.disabled = true;
    showMessage(<?= safe_json(t('pay.js_opening', 'Opening the secure payment window…')) ?>, false);

    fetch(window.DDE.apiUrl + '?action=razorpay.create', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': window.DDE.csrf
      },
      body: JSON.stringify({ order_id: Number(button.getAttribute('data-pay-now')) })
    }).then(function (res) { return res.json(); }).then(function (data) {
      if (!data.ok) { throw new Error(data.message || <?= safe_json(t('pay.js_could_not_start', 'Could not start the payment.')) ?>); }
      if (data.data.paid && data.data.redirect) { window.location.href = data.data.redirect; return; }

      var checkout = new window.Razorpay({
        key: data.data.key_id,
        amount: data.data.amount,
        currency: 'INR',
        name: <?= safe_json(setting('site_name', 'Daily Dose Export')) ?>,
        description: <?= safe_json(t('order.number', 'Order number')) ?> + ' ' + data.data.order_number,
        order_id: data.data.gateway_order_id,
        prefill: {
          name: <?= safe_json($order['customer_name']) ?>,
          email: <?= safe_json($order['email']) ?>,
          contact: <?= safe_json($order['phone']) ?>
        },
        notes: { order_number: data.data.order_number },
        theme: { color: '#245A43' },
        handler: function (response) {
          showMessage(<?= safe_json(t('pay.js_verifying', 'Verifying your payment…')) ?>, false);
          verifyPayment(response).catch(function (error) {
            showMessage(error.message + ' ' + <?= safe_json(t('pay.js_deducted', 'Please contact us with your order number if money was deducted.')) ?>, true);
            button.disabled = false;
          });
        },
        modal: {
          ondismiss: function () {
            showMessage(<?= safe_json(t('pay.js_not_completed', 'Payment was not completed. Your order remains reserved and you can try again.')) ?>, true);
            button.disabled = false;
          }
        }
      });
      checkout.on('payment.failed', function (resp) {
        showMessage(<?= safe_json(t('pay.js_failed', 'Payment failed:')) ?> + ' ' + (resp.error && resp.error.description ? resp.error.description : <?= safe_json(t('pay.js_try_another', 'Please try another method.')) ?>), true);
        button.disabled = false;
      });
      checkout.open();
      button.disabled = false;
    }).catch(function (error) {
      showMessage(error.message || <?= safe_json(t('pay.js_could_not_start', 'Could not start the payment.')) ?>, true);
      button.disabled = false;
    });
  });
});
</script>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
