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
        flash('success', t('wholesale.sent', 'Thank you. Your wholesale enquiry has been received.'));
        redirect('wholesale.php');
    }
    foreach (array_keys($form) as $key) {
        $form[$key] = post_str($key, $form[$key]);
    }
    if (!RateLimiter::hit('wholesale:' . request_ip(), 'enquiry', 8, 3600)) {
        $error = t('wholesale.error_limit', 'Too many enquiries from this connection. Please try again later or call us.');
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
    'title' => t('wholesale.title', 'Wholesale enquiry') . ' | ' . setting('site_name', 'Daily Dose Export'),
    'description' => t('wholesale.meta', 'Request wholesale quantities of Indian spices, grains, millets, teas, salt and dry fruits from Daily Dose Export in Varanasi.'),
    'canonical' => url('wholesale.php'),
    'jsonld' => [
        web_page_jsonld(t('wholesale.title', 'Wholesale enquiries'), t('wholesale.meta', 'Request wholesale quantities of Indian spices, grains, millets, teas, salt and dry fruits from Daily Dose Export in Varanasi.'), url('wholesale.php'), 'WebPage', ['.page-lead']),
        ['@context' => 'https://schema.org', '@type' => 'Service', 'name' => 'Wholesale supply of Indian food products', 'serviceType' => 'Wholesale supply of Indian food products', 'url' => url('wholesale.php'), 'provider' => ['@id' => APP_URL . '/#organization'], 'areaServed' => ['@type' => 'Country', 'name' => 'India']],
        breadcrumb_jsonld([['label' => t('nav.home', 'Home'), 'href' => ''], ['label' => t('nav.wholesale', 'Wholesale'), 'href' => 'wholesale.php']]),
    ],
];
$activeNav = 'wholesale';
$bodyClass = 'wholesale';
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="page-hero">
  <div class="container">
    <nav class="breadcrumbs" aria-label="<?= e(t('common.breadcrumb', 'Breadcrumb')) ?>">
      <a href="<?= e(url('')) ?>"><?= e(t('nav.home', 'Home')) ?></a> <span aria-hidden="true">/</span> <span aria-current="page"><?= e(t('nav.wholesale', 'Wholesale')) ?></span>
    </nav>
    <p class="eyebrow"><?= e(t('wholesale.eyebrow', 'Bulk supply · India')) ?></p>
    <h1><?= e(t('wholesale.title', 'Wholesale enquiries')) ?></h1>
    <p class="page-lead"><?= e(t('wholesale.lead', 'Share your requirement and our team will confirm availability, pack sizes and a quotation. Minimum quantities and pricing are confirmed per enquiry.')) ?></p>
  </div>
</div>

<div class="container">
  <div class="enquiry-layout">
    <div class="contact-form-card">
      <h2><?= e(t('wholesale.form_title', 'Tell us what you need')) ?></h2>
      <p><?= e(t('wholesale.form_text', 'We supply retail packs through the shop, and bulk quantities by quotation. Fields marked with an asterisk are required.')) ?></p>

      <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= e(url('wholesale.php')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="hp-field" aria-hidden="true">
          <label for="ws_website"><?= e(t('checkout.website', 'Company website')) ?></label>
          <input id="ws_website" type="text" name="company_website" tabindex="-1" autocomplete="off">
        </div>
        <div class="form-row-2">
          <div class="field">
            <label for="ws_name"><?= e(t('wholesale.your_name', 'Your name')) ?> <span class="req">*</span></label>
            <input id="ws_name" type="text" name="name" value="<?= e($form['name']) ?>" required maxlength="120" autocomplete="name">
          </div>
          <div class="field">
            <label for="ws_company"><?= e(t('wholesale.company', 'Business / company')) ?></label>
            <input id="ws_company" type="text" name="company" value="<?= e($form['company']) ?>" maxlength="190" autocomplete="organization">
          </div>
        </div>
        <div class="form-row-2">
          <div class="field">
            <label for="ws_email"><?= e(t('wholesale.email', 'Email address')) ?> <span class="req">*</span></label>
            <input id="ws_email" type="email" name="email" value="<?= e($form['email']) ?>" required maxlength="190" autocomplete="email">
          </div>
          <div class="field">
            <label for="ws_phone"><?= e(t('wholesale.phone', 'Phone')) ?> <span class="req">*</span></label>
            <input id="ws_phone" type="tel" name="phone" value="<?= e($form['phone']) ?>" required maxlength="20" autocomplete="tel">
          </div>
        </div>
        <div class="form-row-2">
          <div class="field">
            <label for="ws_country"><?= e(t('wholesale.country', 'Country')) ?> <span class="req">*</span></label>
            <input id="ws_country" type="text" name="country" value="<?= e($form['country']) ?>" required maxlength="80" autocomplete="country-name">
          </div>
          <div class="field">
            <label for="ws_city"><?= e(t('wholesale.city', 'City')) ?></label>
            <input id="ws_city" type="text" name="city" value="<?= e($form['city']) ?>" maxlength="120" autocomplete="address-level2">
          </div>
        </div>
        <div class="field">
          <label for="ws_product"><?= e(t('wholesale.products', 'Products of interest')) ?> <span class="req">*</span></label>
          <input id="ws_product" type="text" name="product_interest" value="<?= e($form['product_interest']) ?>" required maxlength="190" placeholder="<?= e(t('wholesale.products_placeholder', 'e.g. turmeric, basmati rice, millet cookies')) ?>">
        </div>
        <div class="form-row-2">
          <div class="field">
            <label for="ws_quantity"><?= e(t('wholesale.quantity', 'Approximate quantity')) ?> <span class="req">*</span></label>
            <input id="ws_quantity" type="text" name="quantity" value="<?= e($form['quantity']) ?>" required maxlength="120" placeholder="<?= e(t('wholesale.quantity_placeholder', 'e.g. 50 kg monthly')) ?>">
          </div>
          <div class="field">
            <label for="ws_packaging"><?= e(t('wholesale.packaging', 'Preferred packaging')) ?></label>
            <input id="ws_packaging" type="text" name="packaging" value="<?= e($form['packaging']) ?>" maxlength="190" placeholder="<?= e(t('wholesale.packaging_placeholder', 'e.g. 1 kg retail pouches')) ?>">
          </div>
        </div>
        <div class="field">
          <label for="ws_buyer_type"><?= e(t('wholesale.buyer_type', 'Buyer type')) ?></label>
          <select id="ws_buyer_type" name="buyer_type">
            <option value="retail" <?= $form['buyer_type'] === 'retail' ? 'selected' : '' ?>><?= e(t('wholesale.retail', 'Retail shop')) ?></option>
            <option value="wholesale" <?= $form['buyer_type'] === 'wholesale' ? 'selected' : '' ?>><?= e(t('wholesale.distributor', 'Wholesale / distributor')) ?></option>
            <option value="export" <?= $form['buyer_type'] === 'export' ? 'selected' : '' ?>><?= e(t('wholesale.importer', 'Exporter / import')) ?></option>
          </select>
        </div>
        <div class="field">
          <label for="ws_message"><?= e(t('wholesale.details', 'Additional details')) ?></label>
          <textarea id="ws_message" name="message" maxlength="3000" placeholder="<?= e(t('wholesale.details_placeholder', 'Delivery location, timing, labelling or other requirements.')) ?>"><?= e($form['message']) ?></textarea>
        </div>
        <p class="form-note"><?= e(t('wholesale.note', 'Submitting an enquiry does not create an order. Availability, minimum quantity and pricing will be confirmed by our team.')) ?></p>
        <div>
          <button class="btn btn-primary" type="submit"><?= e(t('wholesale.submit', 'Send wholesale enquiry')) ?></button>
        </div>
      </form>
    </div>

    <aside class="enquiry-side">
      <div class="enquiry-side-card">
        <h3><?= icon('package') ?> <?= e(t('wholesale.next', 'What happens next')) ?></h3>
        <ul>
          <li><?= e(t('wholesale.next1', 'Our team reviews your products and quantities.')) ?></li>
          <li><?= e(t('wholesale.next2', 'We confirm availability, packaging options and a quotation.')) ?></li>
          <li><?= e(t('wholesale.next3', 'Once confirmed, your order is packed at our Varanasi facility.')) ?></li>
        </ul>
      </div>
      <div class="enquiry-side-card">
        <h3><?= icon('clipboard') ?> <?= e(t('wholesale.helpful', 'Helpful details')) ?></h3>
        <ul>
          <li><?= e(t('wholesale.help1', 'Product names and expected monthly quantity')) ?></li>
          <li><?= e(t('wholesale.help2', 'Preferred pack size or bulk format')) ?></li>
          <li><?= e(t('wholesale.help3', 'Delivery city and required timeline')) ?></li>
          <li><?= e(t('wholesale.help4', 'Any labelling or documentation needs')) ?></li>
        </ul>
      </div>
      <div class="enquiry-side-card">
        <h3><?= icon('phone') ?> <?= e(t('wholesale.prefer_call', 'Prefer to talk?')) ?></h3>
        <p><?= e(t('wholesale.call', 'Call')) ?> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) setting('support_phone')) ?? '') ?>"><?= e(setting('support_phone')) ?></a> <?= e(t('wholesale.or_contact', 'or use the')) ?> <a href="<?= e(url('contact.php')) ?>"><?= e(t('nav.contact', 'contact page')) ?></a>.</p>
        <p><?= e(t('wholesale.intl_1', 'For international consignments, use the')) ?> <a href="<?= e(url('export-enquiry.php')) ?>"><?= e(t('nav.export', 'export enquiry')) ?></a> <?= e(t('wholesale.intl_2', 'form.')) ?></p>
      </div>
    </aside>
  </div>
</div>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
