<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$customer = Auth::customer();
$summary = Cart::summary(null, $customer['email'] ?? null, Auth::customerId());
if ($summary['items'] === []) {
    flash('info', 'Your cart is empty. Add a few products before checking out.');
    redirect('cart.php');
}
if ($summary['issues'] !== []) {
    flash('error', 'Please resolve the highlighted item issues in your cart before checkout.');
    redirect('cart.php');
}

$codEnabled = setting('cod_enabled', '0') === '1';
$onlineEnabled = Payment::enabled();
$defaultPayment = $codEnabled ? 'cod' : ($onlineEnabled ? 'razorpay' : '');

$addresses = [];
$defaultAddress = null;
if ($customer !== null) {
    $addresses = Database::all(
        'SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id ASC',
        [$customer['id']]
    );
    foreach ($addresses as $addressRow) {
        if ((int) $addressRow['is_default'] === 1) {
            $defaultAddress = $addressRow;
            break;
        }
    }
    $defaultAddress ??= $addresses[0] ?? null;
}

$form = [
    'customer_name' => $customer ? trim($customer['first_name'] . ' ' . ($customer['last_name'] ?? '')) : '',
    'email' => $customer['email'] ?? '',
    'phone' => $customer['phone'] ?? '',
    'shipping_full_name' => $defaultAddress['full_name'] ?? ($customer ? trim($customer['first_name'] . ' ' . ($customer['last_name'] ?? '')) : ''),
    'shipping_phone' => $defaultAddress['phone'] ?? ($customer['phone'] ?? ''),
    'shipping_address_type' => $defaultAddress['address_type'] ?? 'home',
    'shipping_house' => $defaultAddress['house'] ?? '',
    'shipping_street' => $defaultAddress['street'] ?? '',
    'shipping_landmark' => $defaultAddress['landmark'] ?? '',
    'shipping_city' => $defaultAddress['city'] ?? '',
    'shipping_state' => $defaultAddress['state'] ?? '',
    'shipping_pincode' => $defaultAddress['pincode'] ?? '',
    'shipping_country' => $defaultAddress['country'] ?? 'India',
    'customer_notes' => '',
    'payment_method' => $defaultPayment,
];

$formError = '';

if (is_post()) {
    verify_csrf();
    foreach (array_keys($form) as $key) {
        if (isset($_POST[$key]) && is_string($_POST[$key])) {
            $form[$key] = trim($_POST[$key]);
        }
    }

    if (trim((string) ($_POST['company_website'] ?? '')) !== '') {
        $formError = 'We could not process this request.';
    } elseif (empty($_POST['terms'])) {
        $formError = 'Please accept the terms and privacy policy to place your order.';
    } elseif ($form['payment_method'] !== 'cod' && $form['payment_method'] !== 'razorpay') {
        $formError = 'Please choose a payment method.';
    } elseif ($form['payment_method'] === 'cod' && !$codEnabled) {
        $formError = 'Cash on delivery is not available. Please choose online payment or contact us.';
    } elseif ($form['payment_method'] === 'razorpay' && !$onlineEnabled) {
        $formError = 'Online payment is not available right now. Please choose another method or contact us.';
    } elseif (!RateLimiter::hit('checkout:' . request_ip(), 'place', 20, 3600)) {
        $formError = 'Too many checkout attempts from this connection. Please try again later or contact us for help.';
    } else {
        $idempotencyKey = (string) ($_POST['idempotency_key'] ?? '');
        $result = Order::create($form, $idempotencyKey);
        if (!$result['ok']) {
            $formError = $result['message'];
        } else {
            unset($_SESSION['checkout_idempotency']);
            $order = $result['order'];
            if ($order['payment_method'] === 'razorpay' && $order['payment_status'] !== 'paid') {
                redirect('pay.php?order=' . $order['id']);
            }
            flash('success', 'Thank you. Your order has been received.');
            redirect('order-success.php?order=' . $order['id']);
        }
    }
}

$idempotencyKey = (string) ($_SESSION['checkout_idempotency'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $idempotencyKey)) {
    $idempotencyKey = bin2hex(random_bytes(16));
    $_SESSION['checkout_idempotency'] = $idempotencyKey;
}

$seo = [
    'title' => 'Checkout | ' . setting('site_name', 'Daily Dose Export'),
    'description' => 'Complete your Daily Dose Export order.',
    'canonical' => url('checkout.php'),
    'robots' => 'noindex,nofollow',
];
$activeNav = 'shop';
$bodyClass = 'checkout';
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="container">
  <div style="padding-top:26px">
    <h1 style="font-size:clamp(1.9rem,4vw,2.6rem);margin-bottom:6px">Checkout</h1>
    <p class="muted small">Guest checkout is available. Your order details will be confirmed by our team.</p>
    <div class="checkout-steps" aria-label="Checkout steps">
      <span><span class="num">1</span> Details</span>
      <span><span class="num">2</span> Delivery</span>
      <span><span class="num">3</span> Payment</span>
      <span><span class="num">4</span> Review</span>
      <span><span class="num">5</span> Confirmation</span>
    </div>
  </div>

  <?php if ($formError !== ''): ?>
    <div class="alert alert-error" role="alert"><?= e($formError) ?></div>
  <?php endif; ?>
  <?php if (!$codEnabled && !$onlineEnabled): ?>
    <div class="alert">
      <strong>Payment methods are being configured.</strong> You can still review your cart, or <a href="<?= e(url('contact.php')) ?>">contact our team</a> to place an order by phone. We will publish payment options once they are enabled.
    </div>
  <?php endif; ?>

  <div class="checkout-layout">
    <form class="checkout-form" method="post" action="<?= e(url('checkout.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="idempotency_key" value="<?= e($idempotencyKey) ?>">
      <div class="hp-field" aria-hidden="true">
        <label for="company_website">Website</label>
        <input id="company_website" type="text" name="company_website" value="" tabindex="-1" autocomplete="off">
      </div>

      <div class="form-card">
        <h2><span class="step-index">1</span> Contact details</h2>
        <?php if ($customer === null): ?>
          <p class="small muted">Already have an account? <a href="<?= e(url('login.php?next=checkout.php')) ?>">Log in</a> for faster checkout.</p>
        <?php endif; ?>
        <div class="form-grid">
          <div class="field">
            <label for="customer_name">Full name <span class="req">*</span></label>
            <input id="customer_name" type="text" name="customer_name" value="<?= e($form['customer_name']) ?>" required maxlength="120" autocomplete="name">
          </div>
          <div class="field">
            <label for="email">Email address <span class="req">*</span></label>
            <input id="email" type="email" name="email" value="<?= e($form['email']) ?>" required maxlength="190" autocomplete="email">
            <span class="help">Order updates and receipts are sent here.</span>
          </div>
          <div class="field">
            <label for="phone">Mobile number <span class="req">*</span></label>
            <input id="phone" type="tel" name="phone" value="<?= e($form['phone']) ?>" required maxlength="20" autocomplete="tel" inputmode="tel">
          </div>
        </div>
      </div>

      <div class="form-card">
        <h2><span class="step-index">2</span> Delivery address</h2>
        <?php if ($addresses !== []): ?>
          <div class="field" style="margin-bottom:14px">
            <label for="saved_address">Use a saved address</label>
            <select id="saved_address" data-address-select>
              <option value="">Enter a new address below</option>
              <?php foreach ($addresses as $addressRow): ?>
                <option value="<?= (int) $addressRow['id'] ?>"
                  data-name="<?= e($addressRow['full_name']) ?>"
                  data-phone="<?= e($addressRow['phone']) ?>"
                  data-type="<?= e($addressRow['address_type']) ?>"
                  data-house="<?= e($addressRow['house']) ?>"
                  data-street="<?= e($addressRow['street']) ?>"
                  data-landmark="<?= e($addressRow['landmark'] ?? '') ?>"
                  data-city="<?= e($addressRow['city']) ?>"
                  data-state="<?= e($addressRow['state']) ?>"
                  data-pincode="<?= e($addressRow['pincode']) ?>"
                  data-country="<?= e($addressRow['country']) ?>">
                  <?= e(($addressRow['label'] ?: ucfirst($addressRow['address_type'])) . ' — ' . $addressRow['house'] . ', ' . $addressRow['city']) ?><?= (int) $addressRow['is_default'] === 1 ? ' (default)' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <div class="form-grid">
          <div class="field">
            <label for="shipping_full_name">Recipient name <span class="req">*</span></label>
            <input id="shipping_full_name" type="text" name="shipping_full_name" value="<?= e($form['shipping_full_name']) ?>" required maxlength="120" autocomplete="shipping name">
          </div>
          <div class="field">
            <label for="shipping_phone">Delivery phone <span class="req">*</span></label>
            <input id="shipping_phone" type="tel" name="shipping_phone" value="<?= e($form['shipping_phone']) ?>" required maxlength="20" autocomplete="shipping tel">
          </div>
          <div class="field">
            <label for="shipping_address_type">Address type</label>
            <select id="shipping_address_type" name="shipping_address_type">
              <option value="home" <?= $form['shipping_address_type'] === 'home' ? 'selected' : '' ?>>Home</option>
              <option value="office" <?= $form['shipping_address_type'] === 'office' ? 'selected' : '' ?>>Office</option>
              <option value="other" <?= $form['shipping_address_type'] === 'other' ? 'selected' : '' ?>>Other</option>
            </select>
          </div>
          <div class="field">
            <label for="shipping_house">House / flat number <span class="req">*</span></label>
            <input id="shipping_house" type="text" name="shipping_house" value="<?= e($form['shipping_house']) ?>" required maxlength="120" autocomplete="shipping address-line1">
          </div>
          <div class="field is-full">
            <label for="shipping_street">Street / locality <span class="req">*</span></label>
            <input id="shipping_street" type="text" name="shipping_street" value="<?= e($form['shipping_street']) ?>" required maxlength="190" autocomplete="shipping address-line2">
          </div>
          <div class="field">
            <label for="shipping_landmark">Landmark <span class="muted">(optional)</span></label>
            <input id="shipping_landmark" type="text" name="shipping_landmark" value="<?= e($form['shipping_landmark']) ?>" maxlength="190">
          </div>
          <div class="field">
            <label for="shipping_city">City <span class="req">*</span></label>
            <input id="shipping_city" type="text" name="shipping_city" value="<?= e($form['shipping_city']) ?>" required maxlength="120" autocomplete="shipping address-level2">
          </div>
          <div class="field">
            <label for="shipping_state">State <span class="req">*</span></label>
            <input id="shipping_state" type="text" name="shipping_state" value="<?= e($form['shipping_state']) ?>" required maxlength="120" autocomplete="shipping address-level1">
          </div>
          <div class="field">
            <label for="shipping_pincode">PIN code <span class="req">*</span></label>
            <input id="shipping_pincode" type="text" name="shipping_pincode" value="<?= e($form['shipping_pincode']) ?>" required maxlength="12" autocomplete="shipping postal-code" inputmode="numeric">
          </div>
          <div class="field">
            <label for="shipping_country">Country <span class="req">*</span></label>
            <input id="shipping_country" type="text" name="shipping_country" value="<?= e($form['shipping_country']) ?>" required maxlength="80" autocomplete="shipping country-name">
          </div>
          <div class="field is-full">
            <label for="customer_notes">Order notes <span class="muted">(optional)</span></label>
            <textarea id="customer_notes" name="customer_notes" rows="3" maxlength="1000" placeholder="Delivery instructions, preferred time, GST details for a business order…"><?= e($form['customer_notes']) ?></textarea>
          </div>
        </div>
      </div>

      <div class="form-card">
        <h2><span class="step-index">3</span> Payment method</h2>
        <?php if ($codEnabled || $onlineEnabled): ?>
          <div class="payment-options">
            <?php if ($codEnabled): ?>
              <label class="payment-option">
                <input type="radio" name="payment_method" value="cod" <?= $form['payment_method'] === 'cod' ? 'checked' : '' ?>>
                <span><strong>Cash on delivery</strong><span>Pay in cash when your order is delivered. Available locations are confirmed by our team.</span></span>
              </label>
            <?php endif; ?>
            <?php if ($onlineEnabled): ?>
              <label class="payment-option">
                <input type="radio" name="payment_method" value="razorpay" <?= $form['payment_method'] === 'razorpay' ? 'checked' : '' ?>>
                <span><strong>Pay online</strong><span>Secure payment through Razorpay. Your order is confirmed after successful payment.</span></span>
              </label>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <p class="muted small mb-0">No payment method is enabled yet. Please contact us to complete an order.</p>
        <?php endif; ?>
      </div>

      <div class="form-card">
        <h2><span class="step-index">4</span> Review &amp; place order</h2>
        <label class="check-line" style="margin-bottom:14px">
          <input type="checkbox" name="terms" value="1" required <?= !empty($_POST['terms']) ? 'checked' : '' ?>>
          <span>I agree to the <a href="<?= e(url('pages/terms-and-conditions')) ?>" target="_blank" rel="noopener">terms and conditions</a> and <a href="<?= e(url('pages/privacy-policy')) ?>" target="_blank" rel="noopener">privacy policy</a>. I understand that product availability and delivery will be confirmed by Daily Dose Export.</span>
        </label>
        <button class="btn btn-primary btn-lg checkout-submit" type="submit" <?= ($codEnabled || $onlineEnabled) ? '' : 'disabled' ?>>
          Place order <?= icon('arrow-right') ?>
        </button>
        <p class="secure-note"><?= icon('lock') ?> Your details are transmitted securely and totals are verified on our server.</p>
      </div>
    </form>

    <aside class="checkout-summary summary-card" aria-label="Order summary">
      <h2>Your order</h2>
      <div class="checkout-items">
        <?php foreach ($summary['items'] as $item): ?>
          <div class="checkout-item">
            <img src="<?= e(asset_url($item['image'])) ?>" alt="" width="60" height="60" loading="lazy">
            <span class="name"><?= e($item['name']) ?><small><?= e($item['pack_size']) ?> × <?= (int) $item['quantity'] ?></small></span>
            <span class="amount"><?= e(money($item['line_total'])) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="summary-line"><span>Subtotal</span><span><?= e(money($summary['subtotal'])) ?></span></div>
      <?php if ($summary['discount'] > 0): ?>
        <div class="summary-line is-discount"><span>Coupon <?= $summary['coupon'] ? '(' . e($summary['coupon']['code']) . ')' : '' ?></span><span>−<?= e(money($summary['discount'])) ?></span></div>
      <?php endif; ?>
      <div class="summary-line"><span>Shipping</span><span><?= $summary['shipping'] > 0 ? e(money($summary['shipping'])) : 'To be confirmed' ?></span></div>
      <?php if ($summary['tax'] > 0): ?>
        <div class="summary-line"><span>Tax (<?= e((string) $summary['tax_percent']) ?>%)</span><span><?= e(money($summary['tax'])) ?></span></div>
      <?php endif; ?>
      <div class="summary-total"><span>Total</span><span><?= e(money($summary['total'])) ?></span></div>
      <?php if ($summary['coupon_message'] !== '' && !$summary['coupon_ok']): ?>
        <p class="coupon-message"><?= e($summary['coupon_message']) ?> <a href="<?= e(url('cart.php')) ?>">Review cart</a></p>
      <?php endif; ?>
      <p class="summary-note"><?= e(setting('shipping_note')) ?></p>
      <p class="summary-note">Need help? Call <a href="tel:<?= e(preg_replace('/\s+/', '', setting('support_phone'))) ?>"><?= e(setting('support_phone')) ?></a>.</p>
      <a class="cart-continue text-link" href="<?= e(url('cart.php')) ?>"><?= icon('arrow-right') ?> Back to cart</a>
    </aside>
  </div>
</div>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
