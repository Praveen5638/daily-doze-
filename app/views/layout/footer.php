<?php
declare(strict_types=1);

$footerCategories = Catalog::categories();
$policyPages = Database::all(
    "SELECT title, slug FROM cms_pages WHERE slug IN ('privacy-policy','terms-and-conditions','shipping-policy','return-refund-policy','cancellation-policy','cookie-policy') ORDER BY id ASC"
);
$faqPage = Database::one("SELECT title, slug FROM cms_pages WHERE slug = 'faq' AND status = 'published'");
$phone = setting('support_phone');
$whatsapp = setting('whatsapp_number');
$email = setting('contact_email');
$addressParts = array_filter([setting('address_line1'), setting('address_line2'), setting('state'), setting('pincode')]);
$whatsappMessage = rawurlencode(setting('whatsapp_default_message', 'Hello Daily Dose Export, I would like to know more about your products.'));
?>
</main>

<section class="newsletter-band" aria-labelledby="newsletter-title">
  <div class="container newsletter-inner">
    <div class="newsletter-copy">
      <p class="eyebrow">From our kitchen to your inbox</p>
      <h2 id="newsletter-title"><?= e(setting('newsletter_title', 'Stay in touch')) ?></h2>
      <p><?= e(setting('newsletter_text')) ?></p>
    </div>
    <form class="newsletter-form" action="<?= e(url('newsletter.php')) ?>" method="post" data-newsletter-form>
      <?= csrf_field() ?>
      <input type="hidden" name="redirect_to" value="<?= e(ltrim(current_path(), '/')) ?>">
      <div class="hp-field" aria-hidden="true">
        <label for="newsletter-website">Company website</label>
        <input id="newsletter-website" type="text" name="company_website" tabindex="-1" autocomplete="off">
      </div>
      <label class="sr-only" for="newsletter-email">Email address</label>
      <input id="newsletter-email" type="email" name="email" placeholder="Your email address" required maxlength="190" autocomplete="email">
      <button class="btn btn-light" type="submit">Subscribe</button>
      <p class="form-message" data-form-message role="status"></p>
      <p class="newsletter-note">Occasional updates only. Unsubscribe anytime.</p>
    </form>
  </div>
</section>

<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <a class="brand brand-footer" href="<?= e(url('')) ?>">
        <span class="brand-mark"><?= icon('leaf') ?></span>
        <span class="brand-text"><span class="brand-name">Daily Dose</span><span class="brand-sub">Export</span></span>
      </a>
      <p class="footer-tagline"><?= e(setting('tagline', 'Pure From Farm To World')) ?></p>
      <p class="footer-blurb"><?= e(setting('slogan', 'Every Bite, A Promise of Health.')) ?> Natural Indian spices, grains, millets, teas and dry fruits for homes and businesses, packed with care in Varanasi.</p>
      <div class="footer-contact">
        <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= icon('phone') ?> <?= e($phone) ?></a>
        <?php if ($whatsapp !== ''): ?>
          <a href="https://wa.me/<?= e($whatsapp) ?>?text=<?= $whatsappMessage ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?> WhatsApp</a>
        <?php endif; ?>
        <?php if ($email !== ''): ?>
          <a href="mailto:<?= e($email) ?>"><?= icon('mail') ?> <?= e($email) ?></a>
        <?php endif; ?>
        <?php if ($addressParts !== []): ?>
          <span class="footer-address"><?= icon('pin') ?> <?= e(implode(', ', $addressParts)) ?></span>
        <?php endif; ?>
      </div>
    </div>

    <div class="footer-col">
      <h3>Shop</h3>
      <ul>
        <li><a href="<?= e(url('shop.php')) ?>">All products</a></li>
        <?php foreach (array_slice($footerCategories, 0, 6) as $cat): ?>
          <li><a href="<?= e(url('category/' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="footer-col">
      <h3>Company</h3>
      <ul>
        <li><a href="<?= e(url('about.php')) ?>">About us</a></li>
        <li><a href="<?= e(url('wholesale.php')) ?>">Wholesale</a></li>
        <li><a href="<?= e(url('export-enquiry.php')) ?>">Export enquiry</a></li>
        <li><a href="<?= e(url('track-order.php')) ?>">Track your order</a></li>
        <li><a href="<?= e(url('contact.php')) ?>">Contact</a></li>
        <?php if ($faqPage): ?><li><a href="<?= e(url('pages/' . $faqPage['slug'])) ?>">FAQs</a></li><?php endif; ?>
      </ul>
    </div>

    <div class="footer-col">
      <h3>Policies</h3>
      <ul>
        <?php foreach ($policyPages as $page): ?>
          <li><a href="<?= e(url('pages/' . $page['slug'])) ?>"><?= e($page['title']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <div class="container footer-bottom">
    <p>&copy; <?= date('Y') ?> <?= e(setting('site_name', 'Daily Dose Export')) ?>. All rights reserved.</p>
    <p class="footer-fineprint">Product availability, pricing and delivery are confirmed at the time of order. Statutory information is shown only when verified and provided by the business.</p>
    <p class="footer-made">Varanasi, Uttar Pradesh, India</p>
  </div>
</footer>

<?php if ($whatsapp !== ''): ?>
  <a class="whatsapp-float" href="https://wa.me/<?= e($whatsapp) ?>?text=<?= $whatsappMessage ?>" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp">
    <?= icon('whatsapp') ?>
    <span class="whatsapp-label">Chat with us</span>
  </a>
<?php endif; ?>

<div class="toast" data-toast role="status" aria-live="polite" hidden></div>

<script>
window.DDE = {
  baseUrl: <?= safe_json(APP_URL . '/') ?>,
  apiUrl: <?= safe_json(url('api/index.php')) ?>,
  csrf: <?= safe_json(Csrf::token()) ?>,
  loggedIn: <?= Auth::customer() ? 'true' : 'false' ?>,
  loginUrl: <?= safe_json(url('login.php')) ?>
};
</script>
<script src="<?= e(url('assets/js/main.js')) ?>" defer></script>
</body>
</html>
