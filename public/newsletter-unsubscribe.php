<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$token = (string) ($_POST['token'] ?? ($_GET['token'] ?? ''));
$subscriber = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $subscriber = Database::one('SELECT id, email, status, token FROM newsletter_subscribers WHERE token = ?', [$token]);
}
$done = false;

if ($subscriber !== null && is_post()) {
    verify_csrf();
    Database::update('newsletter_subscribers', ['status' => 'unsubscribed'], 'id = ?', [$subscriber['id']]);
    redirect('newsletter-unsubscribe.php?token=' . $token . '&done=1');
}

if ($subscriber !== null && isset($_GET['done'])) {
    $done = true;
}

$seo = [
    'title' => t('unsub.title', 'Newsletter preferences') . ' | ' . setting('site_name', 'Daily Dose Export'),
    'description' => t('unsub.meta', 'Manage your Daily Dose Export newsletter subscription.'),
    'canonical' => url('newsletter-unsubscribe.php'),
    'robots' => 'noindex,nofollow',
];
$activeNav = '';
$bodyClass = 'newsletter-preferences';
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="container auth-page">
  <div class="auth-card auth-card-narrow" style="text-align:center">
    <?php if ($subscriber === null): ?>
      <h1><?= e(t('unsub.invalid_title', 'This link is not valid')) ?></h1>
      <p class="auth-lead"><?= e(t('unsub.invalid_text', 'We could not match this unsubscribe link to a newsletter subscription. It may have been superseded.')) ?></p>
      <div class="notice" style="text-align:left"><?= e(t('unsub.invalid_note', 'If you are still receiving emails and want to stop them, please contact us and our team will remove your address.')) ?></div>
      <a class="btn btn-primary" href="<?= e(url('')) ?>"><?= e(t('unsub.home', 'Return to home')) ?></a>
    <?php elseif ($done || $subscriber['status'] === 'unsubscribed'): ?>
      <div class="success-icon" style="margin:0 auto 14px"><?= icon('check') ?></div>
      <h1><?= e(t('unsub.done_title', 'You are unsubscribed')) ?></h1>
      <p class="auth-lead"><?= e(sprintf(t('unsub.done_text', '%s will no longer receive newsletter emails from Daily Dose Export.'), (string) $subscriber['email'])) ?></p>
      <p class="muted small"><?= e(t('unsub.done_note', 'Order and enquiry emails are separate and will still be sent when you place an order or contact us.')) ?></p>
      <a class="btn btn-primary" href="<?= e(url('')) ?>"><?= e(t('unsub.home', 'Return to home')) ?></a>
    <?php else: ?>
      <h1><?= e(t('unsub.confirm_title', 'Unsubscribe from updates?')) ?></h1>
      <p class="auth-lead"><?= e(sprintf(t('unsub.confirm_text', 'This will stop newsletter emails to %s. You will still receive essential messages about any orders or enquiries.'), (string) $subscriber['email'])) ?></p>
      <form method="post" action="<?= e(url('newsletter-unsubscribe.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <button class="btn btn-primary btn-block" type="submit"><?= e(t('unsub.confirm_button', 'Yes, unsubscribe me')) ?></button>
      </form>
      <p class="auth-alt"><a href="<?= e(url('')) ?>"><?= e(t('unsub.keep', 'Keep my subscription')) ?></a></p>
    <?php endif; ?>
  </div>
</div>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
