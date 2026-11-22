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
    'title' => 'Newsletter preferences | ' . setting('site_name', 'Daily Dose Export'),
    'description' => 'Manage your Daily Dose Export newsletter subscription.',
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
      <h1>This link is not valid</h1>
      <p class="auth-lead">We could not match this unsubscribe link to a newsletter subscription. It may have been superseded.</p>
      <div class="notice" style="text-align:left">If you are still receiving emails and want to stop them, please <a href="<?= e(url('contact.php')) ?>">contact us</a> and our team will remove your address.</div>
      <a class="btn btn-primary" href="<?= e(url('')) ?>">Return to home</a>
    <?php elseif ($done || $subscriber['status'] === 'unsubscribed'): ?>
      <div class="success-icon" style="margin:0 auto 14px"><?= icon('check') ?></div>
      <h1>You are unsubscribed</h1>
      <p class="auth-lead"><?= e($subscriber['email']) ?> will no longer receive newsletter emails from Daily Dose Export.</p>
      <p class="muted small">Order and enquiry emails are separate and will still be sent when you place an order or contact us.</p>
      <a class="btn btn-primary" href="<?= e(url('')) ?>">Return to home</a>
    <?php else: ?>
      <h1>Unsubscribe from updates?</h1>
      <p class="auth-lead">This will stop newsletter emails to <strong><?= e($subscriber['email']) ?></strong>. You will still receive essential messages about any orders or enquiries.</p>
      <form method="post" action="<?= e(url('newsletter-unsubscribe.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <button class="btn btn-primary btn-block" type="submit">Yes, unsubscribe me</button>
      </form>
      <p class="auth-alt"><a href="<?= e(url('')) ?>">Keep my subscription</a></p>
    <?php endif; ?>
  </div>
</div>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
