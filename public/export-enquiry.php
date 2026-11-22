<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$prefillProduct = mb_substr(trim((string) ($_GET['product'] ?? '')), 0, 190);

$form = [
    'name' => '',
    'company' => '',
    'email' => '',
    'phone' => '',
    'country' => '',
    'city' => '',
    'product_interest' => $prefillProduct,
    'quantity' => '',
    'packaging' => '',
    'destination_port' => '',
    'message' => '',
];
$error = '';

if (is_post()) {
    verify_csrf();
    if (trim((string) ($_POST['company_website'] ?? '')) !== '') {
        flash('success', t('export.sent', 'Thank you. Your export enquiry has been received.'));
        redirect('export-enquiry.php');
    }
    foreach (array_keys($form) as $key) {
        $form[$key] = post_str($key);
    }
    $form['buyer_type'] = 'export';
    if (!RateLimiter::hit('export:' . request_ip(), 'enquiry', 8, 3600)) {
        $error = t('export.error_limit', 'Too many enquiries from this connection. Please try again later or call us.');
    } else {
        $result = Enquiry::create('export', $form);
        if ($result['ok']) {
            flash('success', $result['message']);
            redirect('export-enquiry.php');
        }
        $error = $result['message'];
    }
}

$seo = [
    'title' => t('export.title', 'Export enquiry') . ' | ' . setting('site_name', 'Daily Dose Export'),
    'description' => t('export.meta', 'International buyers: enquire about Indian spices, grains, millets, teas and dry fruits from Daily Dose Export, Varanasi.'),
    'canonical' => url('export-enquiry.php'),
    'jsonld' => [
        web_page_jsonld(t('export.title', 'Export enquiries'), t('export.meta', 'International buyers: enquire about Indian spices, grains, millets, teas and dry fruits from Daily Dose Export, Varanasi.'), url('export-enquiry.php'), 'WebPage', ['.page-lead']),
        ['@context' => 'https://schema.org', '@type' => 'Service', 'name' => 'Export enquiries for Indian food products', 'serviceType' => 'Export enquiry for Indian food products', 'url' => url('export-enquiry.php'), 'provider' => ['@id' => APP_URL . '/#organization']],
        breadcrumb_jsonld([['label' => t('nav.home', 'Home'), 'href' => ''], ['label' => t('nav.export', 'Export enquiry'), 'href' => 'export-enquiry.php']]),
    ],
];
$activeNav = 'export';
$bodyClass = 'export';
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="page-hero is-dark">
  <div class="container">
    <nav class="breadcrumbs" aria-label="<?= e(t('common.breadcrumb', 'Breadcrumb')) ?>">
      <a href="<?= e(url('')) ?>"><?= e(t('nav.home', 'Home')) ?></a> <span aria-hidden="true">/</span> <span aria-current="page"><?= e(t('nav.export', 'Export enquiry')) ?></span>
    </nav>
    <p class="eyebrow"><?= e(t('export.eyebrow', 'From India to the world')) ?></p>
    <h1><?= e(t('export.title', 'Export enquiries')) ?></h1>
    <p class="page-lead"><?= e(localized_setting('tagline', 'Pure From Farm To World')) ?>. <?= e(t('export.lead', 'Tell us your destination, products and volumes, and our team will respond with availability and next steps.')) ?></p>
  </div>
</div>

<div class="container">
  <div class="enquiry-layout">
    <div class="contact-form-card">
      <h2><?= e(t('export.form_title', 'International enquiry')) ?></h2>
      <p><?= e(t('export.form_text', 'For importers, distributors and international retail buyers. Fields marked with an asterisk are required.')) ?></p>

      <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= e(url('export-enquiry.php')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="hp-field" aria-hidden="true">
          <label for="ex_website"><?= e(t('checkout.website', 'Company website')) ?></label>
          <input id="ex_website" type="text" name="company_website" tabindex="-1" autocomplete="off">
        </div>
        <div class="form-row-2">
          <div class="field">
            <label for="ex_name"><?= e(t('wholesale.your_name', 'Your name')) ?> <span class="req">*</span></label>
            <input id="ex_name" type="text" name="name" value="<?= e($form['name']) ?>" required maxlength="120" autocomplete="name">
          </div>
          <div class="field">
            <label for="ex_company"><?= e(t('wholesale.company', 'Company')) ?></label>
            <input id="ex_company" type="text" name="company" value="<?= e($form['company']) ?>" maxlength="190" autocomplete="organization">
          </div>
        </div>
        <div class="form-row-2">
          <div class="field">
            <label for="ex_email"><?= e(t('wholesale.email', 'Email address')) ?> <span class="req">*</span></label>
            <input id="ex_email" type="email" name="email" value="<?= e($form['email']) ?>" required maxlength="190" autocomplete="email">
          </div>
          <div class="field">
            <label for="ex_phone"><?= e(t('export.phone_whatsapp', 'Phone / WhatsApp')) ?> <span class="req">*</span></label>
            <input id="ex_phone" type="tel" name="phone" value="<?= e($form['phone']) ?>" required maxlength="20" autocomplete="tel">
          </div>
        </div>
        <div class="form-row-2">
          <div class="field">
            <label for="ex_country"><?= e(t('export.destination', 'Destination country')) ?> <span class="req">*</span></label>
            <input id="ex_country" type="text" name="country" value="<?= e($form['country']) ?>" required maxlength="80" autocomplete="country-name">
          </div>
          <div class="field">
            <label for="ex_city"><?= e(t('wholesale.city', 'City')) ?></label>
            <input id="ex_city" type="text" name="city" value="<?= e($form['city']) ?>" maxlength="120">
          </div>
        </div>
        <div class="field">
          <label for="ex_product"><?= e(t('export.products', 'Products of interest')) ?> <span class="req">*</span></label>
          <input id="ex_product" type="text" name="product_interest" value="<?= e($form['product_interest']) ?>" required maxlength="190" placeholder="<?= e(t('export.products_placeholder', 'Spices, grains, millets, teas, salt, dry fruits…')) ?>">
        </div>
        <div class="form-row-2">
          <div class="field">
            <label for="ex_quantity"><?= e(t('export.quantity', 'Target quantity')) ?> <span class="req">*</span></label>
            <input id="ex_quantity" type="text" name="quantity" value="<?= e($form['quantity']) ?>" required maxlength="120" placeholder="<?= e(t('export.quantity_placeholder', 'e.g. 1 x 20ft container')) ?>">
          </div>
          <div class="field">
            <label for="ex_port"><?= e(t('export.port', 'Destination port')) ?></label>
            <input id="ex_port" type="text" name="destination_port" value="<?= e($form['destination_port']) ?>" maxlength="190" placeholder="<?= e(t('export.port_placeholder', 'e.g. Jebel Ali')) ?>">
          </div>
        </div>
        <div class="field">
          <label for="ex_packaging"><?= e(t('export.packaging', 'Preferred packaging')) ?></label>
          <input id="ex_packaging" type="text" name="packaging" value="<?= e($form['packaging']) ?>" maxlength="190" placeholder="<?= e(t('export.packaging_placeholder', 'Retail packs, bulk bags, private label…')) ?>">
        </div>
        <div class="field">
          <label for="ex_message"><?= e(t('export.details', 'Additional details')) ?></label>
          <textarea id="ex_message" name="message" maxlength="3000" placeholder="<?= e(t('export.details_placeholder', 'Labelling, documentation, shipment timing or other requirements.')) ?>"><?= e($form['message']) ?></textarea>
        </div>
        <p class="form-note"><?= e(t('export.note', 'Submitting this form is an enquiry, not an order. Product availability, specifications, documentation and pricing will be confirmed by our team per destination.')) ?></p>
        <div>
          <button class="btn btn-primary" type="submit"><?= e(t('export.submit', 'Send export enquiry')) ?></button>
        </div>
      </form>
    </div>

    <aside class="enquiry-side">
      <div class="enquiry-side-card">
        <h3><?= icon('leaf') ?> <?= e(t('export.desk', 'Export desk')) ?></h3>
        <p><?= e(t('export.desk_text', 'Our team coordinates product selection, packing and dispatch documentation from Varanasi, India. Requirements vary by market, so every enquiry is reviewed individually.')) ?></p>
      </div>
      <div class="enquiry-side-card">
        <h3><?= icon('clipboard') ?> <?= e(t('export.include', 'Include in your enquiry')) ?></h3>
        <ul>
          <li><?= e(t('export.include1', 'Destination country and port')) ?></li>
          <li><?= e(t('export.include2', 'Product list and annual / shipment volume')) ?></li>
          <li><?= e(t('export.include3', 'Desired packaging and labelling')) ?></li>
          <li><?= e(t('export.include4', 'Required documentation for your market')) ?></li>
          <li><?= e(t('export.include5', 'Target shipment window')) ?></li>
        </ul>
      </div>
      <div class="enquiry-side-card">
        <h3><?= icon('shield') ?> <?= e(t('export.no_claim', 'What we do not claim')) ?></h3>
        <p><?= e(t('export.no_claim_text', 'Certifications, registrations and export documents are confirmed only for the specific product, batch and destination. Nothing on this page is a guarantee of regulatory approval in your market.')) ?></p>
      </div>
      <div class="enquiry-side-card">
        <h3><?= icon('phone') ?> <?= e(t('export.also', 'Also available')) ?></h3>
        <p><?= e(t('export.also_text_1', 'For domestic bulk supply, use the')) ?> <a href="<?= e(url('wholesale.php')) ?>"><?= e(t('nav.wholesale', 'wholesale enquiry')) ?></a> <?= e(t('export.also_text_2', 'form or call')) ?> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) setting('support_phone')) ?? '') ?>"><?= e(setting('support_phone')) ?></a>.</p>
      </div>
    </aside>
  </div>
</div>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
