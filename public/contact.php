<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$form = ['name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'message' => ''];
$error = '';

if (is_post()) {
    verify_csrf();
    if (trim((string) ($_POST['company_website'] ?? '')) !== '') {
        flash('success', 'Thank you. Your message has been sent.');
        redirect('contact.php');
    }
    foreach ($form as $key => $_) {
        $form[$key] = post_str($key);
    }
    if (!RateLimiter::hit('contact:' . request_ip(), 'contact', 10, 3600)) {
        $error = 'Too many messages from this connection. Please try again later or call us.';
    } else {
        $result = Enquiry::contact($form);
        if ($result['ok']) {
            flash('success', $result['message']);
            redirect('contact.php');
        }
        $error = $result['message'];
    }
}

$phone = (string) setting('support_phone');
$phoneHref = preg_replace('/[^0-9+]/', '', $phone) ?? '';
$email = (string) setting('contact_email');
$whatsapp = (string) setting('whatsapp_number');
$whatsappMessage = rawurlencode((string) setting('whatsapp_default_message', 'Hello Daily Dose Export, I would like to know more about your products.'));
$addressParts = array_filter([
    setting('address_line1'),
    setting('address_line2'),
    setting('state'),
    setting('pincode'),
    setting('country'),
]);
$mapUrl = trim((string) setting('map_url'));

$seo = [
    'title' => 'Contact us | ' . setting('site_name', 'Daily Dose Export'),
    'description' => 'Speak with the Daily Dose Export team in Varanasi about products, orders, wholesale or export enquiries.',
    'canonical' => url('contact.php'),
];
$activeNav = 'contact';
$bodyClass = 'contact';
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="page-hero">
  <div class="container">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
      <a href="<?= e(url('')) ?>">Home</a> <span aria-hidden="true">/</span> <span aria-current="page">Contact</span>
    </nav>
    <p class="eyebrow">We are here to help</p>
    <h1>Contact Daily Dose Export</h1>
    <p class="page-lead">Questions about a product, an order, wholesale or export? Send us a message and our team will get back to you.</p>
  </div>
</div>

<div class="container">
  <div class="contact-layout">
    <aside class="contact-info-card">
      <h2>Talk to our team</h2>
      <p style="margin:0;color:rgba(255,253,248,.7);font-size:.86rem">Based in Varanasi, serving homes and businesses across India and abroad.</p>
      <div class="contact-info-list">
        <?php if ($phone !== ''): ?>
          <div class="contact-info-item">
            <?= icon('phone') ?>
            <div><strong>Phone</strong><a href="tel:<?= e($phoneHref) ?>"><?= e($phone) ?></a></div>
          </div>
        <?php endif; ?>
        <?php if ($whatsapp !== ''): ?>
          <div class="contact-info-item">
            <?= icon('whatsapp') ?>
            <div><strong>WhatsApp</strong><a href="https://wa.me/<?= e($whatsapp) ?>?text=<?= $whatsappMessage ?>" target="_blank" rel="noopener">Message us on WhatsApp</a></div>
          </div>
        <?php endif; ?>
        <?php if ($email !== ''): ?>
          <div class="contact-info-item">
            <?= icon('mail') ?>
            <div><strong>Email</strong><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></div>
          </div>
        <?php endif; ?>
        <?php if ($addressParts !== []): ?>
          <div class="contact-info-item">
            <?= icon('pin') ?>
            <div>
              <strong>Address</strong>
              <?= e(implode(', ', $addressParts)) ?>
              <?php if ($mapUrl !== ''): ?><br><a href="<?= e($mapUrl) ?>" target="_blank" rel="noopener">View on map <?= icon('external') ?></a><?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <div style="margin-top:26px">
        <a class="btn btn-light btn-sm" href="<?= e(url('wholesale.php')) ?>">Wholesale enquiry</a>
        <a class="btn btn-ghost btn-sm" style="color:var(--warm);border-color:rgba(255,253,248,.35);margin-top:8px" href="<?= e(url('export-enquiry.php')) ?>">Export enquiry</a>
      </div>
    </aside>

    <div class="contact-form-card">
      <h2>Send a message</h2>
      <p>Fields marked with an asterisk are required. We reply using the contact details you provide.</p>

      <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= e(url('contact.php')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="hp-field" aria-hidden="true">
          <label for="contact_website">Company website</label>
          <input id="contact_website" type="text" name="company_website" tabindex="-1" autocomplete="off">
        </div>
        <div class="form-row-2">
          <div class="field">
            <label for="name">Your name <span class="req">*</span></label>
            <input id="name" type="text" name="name" value="<?= e($form['name']) ?>" required maxlength="120" autocomplete="name">
          </div>
          <div class="field">
            <label for="email">Email address <span class="req">*</span></label>
            <input id="email" type="email" name="email" value="<?= e($form['email']) ?>" required maxlength="190" autocomplete="email">
          </div>
        </div>
        <div class="form-row-2">
          <div class="field">
            <label for="phone">Phone (optional)</label>
            <input id="phone" type="tel" name="phone" value="<?= e($form['phone']) ?>" maxlength="20" autocomplete="tel">
          </div>
          <div class="field">
            <label for="subject">Subject</label>
            <input id="subject" type="text" name="subject" value="<?= e($form['subject']) ?>" maxlength="190" placeholder="Product, order, wholesale…">
          </div>
        </div>
        <div class="field">
          <label for="message">Message <span class="req">*</span></label>
          <textarea id="message" name="message" required maxlength="3000" placeholder="Tell us how we can help."><?= e($form['message']) ?></textarea>
        </div>
        <p class="form-note">By sending this message you agree to be contacted about your enquiry. We do not sell your details.</p>
        <div>
          <button class="btn btn-primary" type="submit"><?= icon('mail') ?> Send message</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
