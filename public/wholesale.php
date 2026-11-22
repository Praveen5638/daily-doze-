<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$prefillProduct = mb_substr(trim((string) ($_GET['product'] ?? '')), 0, 190);
$prefillQuantity = mb_substr(trim((string) ($_GET['quantity'] ?? '')), 0, 120);

$form = [
    'name' => '',
    'company' => '',
    'email' => '',
    'phone' => '',
    'country' => 'India',
    'city' => '',
    'product_interest' => $prefillProduct,
    'quantity' => $prefillQuantity,
    'packaging' => '',
    'buyer_type' => 'wholesale',
    'message' => '',
];
$error = '';

if (is_post()) {
    verify_csrf();
    if (trim((string) ($_POST['company_website'] ?? '')) !== '') {
        flash('success', 'Thank you. Your wholesale enquiry has been received.');
        redirect('wholesale.php');
    }
    foreach (array_keys($form) as $key) {
        $form[$key] = post_str($key, $form[$key]);
    }
    if (!RateLimiter::hit('wholesale:' . request_ip(), 'enquiry', 8, 3600)) {
        $error = 'Too many enquiries from this connection. Please try again later or call us.';
    } else {
        $result = Enquiry::create('wholesale', $form);
        if ($result['ok']) {
            flash('success', $result['message']);
            redirect('wholesale.php');
        }
        $error = $result['message'];
    }
}

$seo = [
    'title' => 'Wholesale enquiry | ' . setting('site_name', 'Daily Dose Export'),
    'description' => 'Request wholesale quantities of Indian spices, grains, millets, teas, salt and dry fruits from Daily Dose Export in Varanasi.',
    'canonical' => url('wholesale.php'),
];
$activeNav = 'wholesale';
$bodyClass = 'wholesale';
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="page-hero">
  <div class="container">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
      <a href="<?= e(url('')) ?>">Home</a> <span aria-hidden="true">/</span> <span aria-current="page">Wholesale</span>
    </nav>
    <p class="eyebrow">Bulk supply · India</p>
    <h1>Wholesale enquiries</h1>
    <p class="page-lead">Share your requirement and our team will confirm availability, pack sizes and a quotation. Minimum quantities and pricing are confirmed per enquiry.</p>
  </div>
</div>

<div class="container">
  <div class="enquiry-layout">
    <div class="contact-form-card">
      <h2>Tell us what you need</h2>
      <p>We supply retail packs through the shop, and bulk quantities by quotation. Fields marked with an asterisk are required.</p>

      <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= e(url('wholesale.php')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="hp-field" aria-hidden="true">
          <label for="ws_website">Company website</label>
          <input id="ws_website" type="text" name="company_website" tabindex="-1" autocomplete="off">
        </div>
        <div class="form-row-2">
          <div class="field">
            <label for="ws_name">Your name <span class="req">*</span></label>
            <input id="ws_name" type="text" name="name" value="<?= e($form['name']) ?>" required maxlength="120" autocomplete="name">
          </div>
          <div class="field">
            <label for="ws_company">Business / company</label>
            <input id="ws_company" type="text" name="company" value="<?= e($form['company']) ?>" maxlength="190" autocomplete="organization">
          </div>
        </div>
        <div class="form-row-2">
          <div class="field">
            <label for="ws_email">Email address <span class="req">*</span></label>
            <input id="ws_email" type="email" name="email" value="<?= e($form['email']) ?>" required maxlength="190" autocomplete="email">
          </div>
          <div class="field">
            <label for="ws_phone">Phone <span class="req">*</span></label>
            <input id="ws_phone" type="tel" name="phone" value="<?= e($form['phone']) ?>" required maxlength="20" autocomplete="tel">
          </div>
        </div>
        <div class="form-row-2">
          <div class="field">
            <label for="ws_country">Country <span class="req">*</span></label>
            <input id="ws_country" type="text" name="country" value="<?= e($form['country']) ?>" required maxlength="80" autocomplete="country-name">
          </div>
          <div class="field">
            <label for="ws_city">City</label>
            <input id="ws_city" type="text" name="city" value="<?= e($form['city']) ?>" maxlength="120" autocomplete="address-level2">
          </div>
        </div>
        <div class="field">
          <label for="ws_product">Products of interest <span class="req">*</span></label>
          <input id="ws_product" type="text" name="product_interest" value="<?= e($form['product_interest']) ?>" required maxlength="190" placeholder="e.g. turmeric, basmati rice, millet cookies">
        </div>
        <div class="form-row-2">
          <div class="field">
            <label for="ws_quantity">Approximate quantity <span class="req">*</span></label>
            <input id="ws_quantity" type="text" name="quantity" value="<?= e($form['quantity']) ?>" required maxlength="120" placeholder="e.g. 50 kg monthly">
          </div>
          <div class="field">
            <label for="ws_packaging">Preferred packaging</label>
            <input id="ws_packaging" type="text" name="packaging" value="<?= e($form['packaging']) ?>" maxlength="190" placeholder="e.g. 1 kg retail pouches">
          </div>
        </div>
        <div class="field">
          <label for="ws_buyer_type">Buyer type</label>
          <select id="ws_buyer_type" name="buyer_type">
            <option value="retail" <?= $form['buyer_type'] === 'retail' ? 'selected' : '' ?>>Retail shop</option>
            <option value="wholesale" <?= $form['buyer_type'] === 'wholesale' ? 'selected' : '' ?>>Wholesale / distributor</option>
            <option value="export" <?= $form['buyer_type'] === 'export' ? 'selected' : '' ?>>Exporter / import</option>
          </select>
        </div>
        <div class="field">
          <label for="ws_message">Additional details</label>
          <textarea id="ws_message" name="message" maxlength="3000" placeholder="Delivery location, timing, labelling or other requirements."><?= e($form['message']) ?></textarea>
        </div>
        <p class="form-note">Submitting an enquiry does not create an order. Availability, minimum quantity and pricing will be confirmed by our team.</p>
        <div>
          <button class="btn btn-primary" type="submit">Send wholesale enquiry</button>
        </div>
      </form>
    </div>

    <aside class="enquiry-side">
      <div class="enquiry-side-card">
        <h3><?= icon('package') ?> What happens next</h3>
        <ul>
          <li>Our team reviews your products and quantities.</li>
          <li>We confirm availability, packaging options and a quotation.</li>
          <li>Once confirmed, your order is packed at our Varanasi facility.</li>
        </ul>
      </div>
      <div class="enquiry-side-card">
        <h3><?= icon('clipboard') ?> Helpful details</h3>
        <ul>
          <li>Product names and expected monthly quantity</li>
          <li>Preferred pack size or bulk format</li>
          <li>Delivery city and required timeline</li>
          <li>Any labelling or documentation needs</li>
        </ul>
      </div>
      <div class="enquiry-side-card">
        <h3><?= icon('phone') ?> Prefer to talk?</h3>
        <p>Call <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) setting('support_phone')) ?? '') ?>"><?= e(setting('support_phone')) ?></a> or use the <a href="<?= e(url('contact.php')) ?>">contact page</a>.</p>
        <p>For international consignments, use the <a href="<?= e(url('export-enquiry.php')) ?>">export enquiry</a> form.</p>
      </div>
    </aside>
  </div>
</div>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
