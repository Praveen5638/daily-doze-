<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin('settings.manage');

$groupDefinitions = [
    'general' => ['label' => 'General', 'fields' => [
        ['site_name', 'Site name', 'text', ['max' => 120]],
        ['tagline', 'Tagline', 'text', ['max' => 190]],
        ['slogan', 'Slogan', 'text', ['max' => 190]],
        ['announcement_text', 'Announcement bar text', 'text', ['max' => 190, 'hint' => 'Leave empty to hide the announcement bar.']],
        ['announcement_link', 'Announcement link', 'text', ['max' => 255, 'hint' => 'Relative path such as shop.php, or a full https:// link.']],
        ['footer_text', 'Footer introduction', 'textarea', ['max' => 400, 'hint' => 'Shown in the footer below the brand slogan.']],
    ]],
    'home' => ['label' => 'Home page', 'fields' => [
        ['hero_eyebrow', 'Hero eyebrow', 'text', ['max' => 120]],
        ['hero_title', 'Hero title', 'text', ['max' => 190]],
        ['hero_text', 'Hero text', 'textarea', ['max' => 600]],
        ['hero_note', 'Hero note', 'text', ['max' => 190]],
        ['newsletter_title', 'Newsletter title', 'text', ['max' => 190]],
        ['newsletter_text', 'Newsletter text', 'textarea', ['max' => 400]],
    ]],
    'about' => ['label' => 'About page', 'fields' => [
        ['about_intro', 'About introduction', 'textarea', ['max' => 800]],
        ['mission', 'Mission', 'textarea', ['max' => 500]],
        ['vision', 'Vision', 'textarea', ['max' => 500]],
        ['core_values', 'Core values', 'textarea', ['max' => 1200, 'hint' => 'One value per line. These appear as the values grid on the About page.']],
        ['story_title', 'Story title', 'text', ['max' => 190]],
        ['story_text', 'Story text', 'textarea', ['max' => 3000]],
    ]],
    'contact' => ['label' => 'Contact', 'fields' => [
        ['support_phone', 'Support phone', 'text', ['max' => 30, 'hint' => 'Displayed on the website and in emails.']],
        ['whatsapp_number', 'WhatsApp number', 'text', ['max' => 20, 'hint' => 'Digits only, including country code. Example: 918429933125.']],
        ['contact_email', 'Public contact email', 'email', ['max' => 190, 'hint' => 'Leave empty if a public email is not available; the form and phone remain.']],
        ['address_line1', 'Address line 1', 'text', ['max' => 190]],
        ['address_line2', 'Address line 2', 'text', ['max' => 190]],
        ['state', 'State', 'text', ['max' => 120]],
        ['pincode', 'PIN code', 'text', ['max' => 12]],
        ['country', 'Country', 'text', ['max' => 80]],
        ['map_url', 'Map link', 'url', ['max' => 255, 'hint' => 'Optional Google Maps or similar link.']],
        ['whatsapp_default_message', 'WhatsApp default message', 'textarea', ['max' => 400]],
    ]],
    'commerce' => ['label' => 'Commerce', 'fields' => [
        ['currency', 'Currency code', 'text', ['max' => 3, 'hint' => 'Three-letter ISO code, e.g. INR.']],
        ['tax_percent', 'Tax rate (%)', 'number', ['max' => 100, 'step' => '0.01']],
        ['shipping_flat_rate', 'Flat shipping rate', 'number', ['max' => 1000000, 'step' => '0.01']],
        ['free_shipping_threshold', 'Free shipping over', 'number', ['max' => 10000000, 'step' => '0.01', 'hint' => 'Leave empty to disable free-shipping thresholds.']],
        ['cod_enabled', 'Cash on delivery enabled', 'checkbox'],
        ['cod_fee', 'COD handling fee', 'number', ['max' => 100000, 'step' => '0.01']],
        ['shipping_note', 'Shipping note', 'textarea', ['max' => 500]],
    ]],
    'payments' => ['label' => 'Payments', 'fields' => [
        ['razorpay_enabled', 'Razorpay enabled', 'checkbox', ['hint' => 'Only enable after the Razorpay account and webhook are fully configured.']],
        ['razorpay_key_id', 'Razorpay Key ID', 'text', ['max' => 120, 'hint' => 'Public key ID (starts with rzp_). The key secret is read only from the protected environment configuration.']],
    ]],
    'seo' => ['label' => 'SEO', 'fields' => [
        ['default_meta_description', 'Default meta description', 'textarea', ['max' => 320]],
    ]],
    'social' => ['label' => 'Social', 'fields' => [
        ['instagram_url', 'Instagram URL', 'url', ['max' => 255]],
        ['facebook_url', 'Facebook URL', 'url', ['max' => 255]],
        ['youtube_url', 'YouTube URL', 'url', ['max' => 255]],
        ['x_url', 'X (Twitter) URL', 'url', ['max' => 255]],
    ]],
    'legal' => ['label' => 'Legal', 'fields' => [
        ['legal_entity', 'Registered legal entity', 'text', ['max' => 190, 'hint' => 'Only enter verified business registration details.']],
        ['gstin', 'GSTIN', 'text', ['max' => 40, 'hint' => 'Only enter a verified GSTIN.']],
        ['fssai_number', 'FSSAI licence number', 'text', ['max' => 40, 'hint' => 'Only enter a verified FSSAI licence. This is displayed only when present.']],
    ]],
    'system' => ['label' => 'System', 'fields' => [
        ['admin_notification_email', 'Admin notification email', 'email', ['max' => 190, 'hint' => 'Optional. Falls back to the contact email or SMTP from-address.']],
        ['reviews_enabled', 'Product reviews enabled', 'checkbox'],
        ['maintenance_mode', 'Maintenance mode', 'checkbox', ['hint' => 'Shows a maintenance page to visitors. The admin panel stays available.']],
    ]],
];

$activeGroup = (string) ($_GET['group'] ?? 'general');
if (!isset($groupDefinitions[$activeGroup])) {
    $activeGroup = 'general';
}
$errors = [];

if (is_post()) {
    verify_csrf();
    $postGroup = post_str('group');
    if (!isset($groupDefinitions[$postGroup])) {
        $postGroup = 'general';
    }
    $activeGroup = $postGroup;
    $values = [];
    foreach ($groupDefinitions[$activeGroup]['fields'] as $field) {
        [$key, $label, $type] = $field;
        $options = $field[3] ?? [];
        if ($type === 'checkbox') {
            $value = isset($_POST[$key]) ? '1' : '0';
        } else {
            $value = trim((string) ($_POST[$key] ?? ''));
        }
        $max = (int) ($options['max'] ?? 500);
        if ($type !== 'checkbox' && mb_strlen($value) > $max) {
            $errors[] = $label . ' must be ' . $max . ' characters or fewer.';
        }
        if ($type === 'email' && $value !== '' && (!filter_var($value, FILTER_VALIDATE_EMAIL) || strlen($value) > 190)) {
            $errors[] = 'Please enter a valid email address for ' . $label . '.';
        }
        if ($type === 'url' && $value !== '' && (!is_safe_url($value) || !preg_match('#^https?://#i', $value))) {
            $errors[] = $label . ' must be a valid http or https address.';
        }
        if ($type === 'number' && $value !== '' && (!is_numeric($value) || (float) $value < 0 || (float) $value > (float) $max)) {
            $errors[] = $label . ' must be a number between 0 and ' . $max . '.';
        }
        $values[$key] = $value;
    }

    if ($activeGroup === 'general') {
        if (($values['site_name'] ?? '') === '') {
            $errors[] = 'Site name cannot be empty.';
        }
        $link = $values['announcement_link'] ?? '';
        if ($link !== '' && !str_starts_with($link, '/') && !preg_match('#^[a-z0-9][a-z0-9._/\-?=&%\#]*$#i', $link) && !(is_safe_url($link) && preg_match('#^https?://#i', $link))) {
            $errors[] = 'The announcement link must be a relative path or a full https:// address.';
        }
    }
    if ($activeGroup === 'contact') {
        if (($values['support_phone'] ?? '') === '') {
            $errors[] = 'Support phone cannot be empty.';
        }
        $wa = $values['whatsapp_number'] ?? '';
        if ($wa !== '' && !preg_match('/^[0-9]{8,15}$/', $wa)) {
            $errors[] = 'WhatsApp number must be 8–15 digits including the country code, with no spaces or punctuation.';
            $values['whatsapp_number'] = preg_replace('/\D/', '', $wa) ?? '';
        }
        $pin = $values['pincode'] ?? '';
        if ($pin !== '' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9 -]{2,11}$/', $pin)) {
            $errors[] = 'Please enter a valid PIN code.';
        }
    }
    if ($activeGroup === 'commerce') {
        $values['currency'] = strtoupper($values['currency'] ?? 'INR');
        if (!preg_match('/^[A-Z]{3}$/', $values['currency'])) {
            $errors[] = 'Currency must be a three-letter code such as INR.';
        }
        $values['cod_fee'] = $values['cod_fee'] === '' ? '0' : $values['cod_fee'];
        $values['shipping_flat_rate'] = $values['shipping_flat_rate'] === '' ? '0' : $values['shipping_flat_rate'];
        $values['tax_percent'] = $values['tax_percent'] === '' ? '0' : $values['tax_percent'];
    }
    if ($activeGroup === 'payments') {
        $keyId = $values['razorpay_key_id'] ?? '';
        if ($keyId !== '' && !preg_match('/^rzp_[A-Za-z0-9]{6,}$/', $keyId)) {
            $errors[] = 'The Razorpay Key ID should begin with rzp_ and contain no spaces.';
        }
        if (($values['razorpay_enabled'] ?? '0') === '1') {
            if ($keyId === '') {
                $errors[] = 'Enter the Razorpay Key ID before enabling online payments.';
            }
            if (!defined('RAZORPAY_KEY_SECRET') || (string) RAZORPAY_KEY_SECRET === '') {
                $errors[] = 'Razorpay cannot be enabled until RAZORPAY_KEY_SECRET is configured in the protected environment.';
            }
        }
    }

    if ($errors === []) {
        $imageSettings = [];
        if ($activeGroup === 'home' && isset($_FILES['hero_image']) && (int) ($_FILES['hero_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = Uploader::image($_FILES['hero_image'], 'site');
            if ($upload['ok']) {
                $imageSettings['hero_image'] = $upload['path'];
            } else {
                $errors[] = 'Hero image: ' . $upload['error'];
            }
        }
        if ($activeGroup === 'seo' && isset($_FILES['og_image']) && (int) ($_FILES['og_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = Uploader::image($_FILES['og_image'], 'site');
            if ($upload['ok']) {
                $imageSettings['og_image'] = $upload['path'];
            } else {
                $errors[] = 'Social share image: ' . $upload['error'];
            }
        }
        if ($errors === []) {
            $changed = [];
            foreach ($values as $key => $value) {
                $current = setting($key, '');
                if ($current !== $value) {
                    Settings::set($key, $value, $activeGroup);
                    $changed[] = $key;
                }
            }
            foreach ($imageSettings as $key => $value) {
                Settings::set($key, $value, $activeGroup);
                $changed[] = $key;
            }
            if ($changed === []) {
                flash('success', 'No changes to save.');
            } else {
                Notifications::audit('settings.updated', 'settings', $activeGroup, ['keys' => $changed]);
                flash('success', $groupDefinitions[$activeGroup]['label'] . ' settings saved.');
            }
            redirect('admin/settings.php?group=' . $activeGroup);
        }
    }
}

$stored = Settings::all();
$adminTitle = 'Settings';
$adminPage = 'settings';
require __DIR__ . '/inc/header.php';
?>

<div class="admin-page-head">
  <div>
    <h1>Settings</h1>
    <p>Website content, commerce and contact configuration. Secret keys are never stored or displayed here.</p>
  </div>
</div>

<nav class="admin-checks" style="margin-bottom:18px" aria-label="Settings groups">
  <?php foreach ($groupDefinitions as $key => $definition): ?>
    <a class="pill <?= $activeGroup === $key ? 'pill-green' : 'pill-grey' ?>" href="<?= e(admin_url('settings.php?group=' . $key)) ?>"><?= e($definition['label']) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($errors !== []): ?>
  <div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<section class="admin-panel">
  <div class="admin-panel-head">
    <h2><?= e($groupDefinitions[$activeGroup]['label']) ?></h2>
  </div>
  <form class="admin-form" method="post" action="<?= e(admin_url('settings.php?group=' . $activeGroup)) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="group" value="<?= e($activeGroup) ?>">
    <div class="admin-panel-body">
      <?php foreach ($groupDefinitions[$activeGroup]['fields'] as $field): ?>
        <?php
        [$key, $label, $type] = $field;
        $options = $field[3] ?? [];
        $value = (string) ($_POST[$field[0]] ?? ($stored[$field[0]] ?? ''));
        if ($type === 'checkbox') {
            $value = is_post() ? (isset($_POST[$key]) ? '1' : '0') : (string) ($stored[$key] ?? '0');
        }
        ?>
        <?php if ($type === 'checkbox'): ?>
          <div class="field">
            <label class="check"><input type="checkbox" name="<?= e($key) ?>" value="1" <?= $value === '1' ? 'checked' : '' ?>> <?= e($label) ?></label>
            <?php if (!empty($options['hint'])): ?><p class="field-hint"><?= e($options['hint']) ?></p><?php endif; ?>
          </div>
        <?php elseif ($type === 'textarea'): ?>
          <div class="field">
            <label for="setting-<?= e($key) ?>"><?= e($label) ?></label>
            <textarea id="setting-<?= e($key) ?>" name="<?= e($key) ?>" rows="4" maxlength="<?= (int) ($options['max'] ?? 500) ?>"><?= e($value) ?></textarea>
            <?php if (!empty($options['hint'])): ?><p class="field-hint"><?= e($options['hint']) ?></p><?php endif; ?>
          </div>
        <?php elseif ($type === 'number'): ?>
          <div class="field">
            <label for="setting-<?= e($key) ?>"><?= e($label) ?></label>
            <input id="setting-<?= e($key) ?>" type="number" name="<?= e($key) ?>" value="<?= e($value) ?>" min="0" max="<?= (int) ($options['max'] ?? 1000000) ?>" step="<?= e($options['step'] ?? '0.01') ?>">
            <?php if (!empty($options['hint'])): ?><p class="field-hint"><?= e($options['hint']) ?></p><?php endif; ?>
          </div>
        <?php else: ?>
          <div class="field">
            <label for="setting-<?= e($key) ?>"><?= e($label) ?></label>
            <input id="setting-<?= e($key) ?>" type="<?= e($type === 'email' ? 'email' : ($type === 'url' ? 'url' : 'text')) ?>" name="<?= e($key) ?>" value="<?= e($value) ?>" maxlength="<?= (int) ($options['max'] ?? 500) ?>" <?= $key === 'currency' ? 'style="text-transform:uppercase"' : '' ?>>
            <?php if (!empty($options['hint'])): ?><p class="field-hint"><?= e($options['hint']) ?></p><?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>

      <?php if ($activeGroup === 'home'): ?>
        <div class="field">
          <label for="hero_image">Hero image</label>
          <input id="hero_image" type="file" name="hero_image" accept="image/jpeg,image/png,image/webp">
          <p class="field-hint">Current: <?= e(setting('hero_image', 'Not set')) ?>. Use verified brand photography, not unrelated supermarket images.</p>
        </div>
      <?php endif; ?>
      <?php if ($activeGroup === 'seo'): ?>
        <div class="field">
          <label for="og_image">Social share image</label>
          <input id="og_image" type="file" name="og_image" accept="image/jpeg,image/png,image/webp">
          <p class="field-hint">Current: <?= e(setting('og_image', 'Not set')) ?>.</p>
        </div>
      <?php endif; ?>

      <?php if ($activeGroup === 'payments'): ?>
        <div class="inline-note">
          <strong>Secret configuration</strong><br>
          Razorpay key secret: <?= defined('RAZORPAY_KEY_SECRET') && (string) RAZORPAY_KEY_SECRET !== '' ? '<span class="pill pill-green">Configured in environment</span>' : '<span class="pill pill-red">Not configured</span>' ?><br>
          The key secret must be provided through the protected environment file (or server environment), not through this form. The webhook uses the same secret to verify signatures.
        </div>
      <?php endif; ?>
      <?php if ($activeGroup === 'system'): ?>
        <div class="inline-note">Maintenance mode shows a holding page to storefront visitors while the admin panel remains accessible. Remember to turn it off after updates.</div>
      <?php endif; ?>
      <?php if ($activeGroup === 'legal'): ?>
        <div class="inline-note">Do not invent FSSAI numbers, GSTINs or registrations. Leave these fields empty until the business supplies verified details.</div>
      <?php endif; ?>
    </div>
    <div class="admin-form-actions">
      <button class="btn btn-primary" type="submit">Save <?= e(strtolower($groupDefinitions[$activeGroup]['label'])) ?> settings</button>
    </div>
  </form>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
