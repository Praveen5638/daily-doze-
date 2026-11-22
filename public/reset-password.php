<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$token = (string) ($_POST['token'] ?? ($_GET['token'] ?? ''));
$reset = null;
$tokenValid = false;

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $reset = Database::one(
        'SELECT pr.id, pr.customer_id, pr.expires_at, pr.used_at, c.email, c.first_name, c.status
         FROM password_resets pr JOIN customers c ON c.id = pr.customer_id
         WHERE pr.token_hash = ? LIMIT 1',
        [hash('sha256', $token)]
    );
    $tokenValid = $reset !== null
        && $reset['used_at'] === null
        && !empty($reset['expires_at'])
        && strtotime((string) $reset['expires_at']) > time()
        && $reset['status'] !== 'disabled';
}

$error = '';
$done = false;

if ($tokenValid && is_post()) {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if (!RateLimiter::hit('reset:' . request_ip(), 'reset', 10, 900)) {
        $error = t('reset.error_limit', 'Too many attempts. Please request a new reset link and try again later.');
    } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,72}$/', $password)) {
        $error = t('reset.error_weak', 'Use at least 8 characters, including a letter and a number.');
    } elseif ($password !== $confirm) {
        $error = t('reset.error_mismatch', 'The passwords do not match.');
    } else {
        Database::update('customers', [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ], 'id = ?', [$reset['customer_id']]);
        Database::update('password_resets', ['used_at' => date('Y-m-d H:i:s')], 'id = ?', [$reset['id']]);
        Database::q('DELETE FROM password_resets WHERE customer_id = ?', [$reset['customer_id']]);
        if (Auth::customerId() === (int) $reset['customer_id']) {
            Auth::logout();
        }
        flash('success', t('reset.success', 'Your password has been updated. Please log in with your new password.'));
        redirect('login.php');
    }
} elseif (is_post()) {
    verify_csrf();
}

if ($tokenValid && $reset !== null) {
    Notifications::audit('customer.password_reset_requested', 'customer', (string) $reset['customer_id']);
}

$seo = [
    'title' => t('reset.title', 'Choose a new password') . ' | ' . setting('site_name', 'Daily Dose Export'),
    'description' => t('reset.meta', 'Choose a new password for your Daily Dose Export account.'),
    'canonical' => url('reset-password.php'),
    'robots' => 'noindex,nofollow',
];
$activeNav = '';
$bodyClass = 'login';
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="container auth-page">
  <div class="auth-card auth-card-narrow">
    <?php if (!$tokenValid): ?>
      <h1><?= e(t('reset.invalid_title', 'This link is not valid')) ?></h1>
      <p class="auth-lead"><?= e(t('reset.invalid_text', 'The password reset link is invalid, has expired, or has already been used. Reset links stay valid for one hour.')) ?></p>
      <div class="notice"><?= e(t('reset.one_time', 'For your security, each reset link can only be used once.')) ?></div>
      <a class="btn btn-primary btn-block" href="<?= e(url('forgot-password.php')) ?>"><?= e(t('reset.request_new', 'Request a new link')) ?></a>
      <p class="auth-alt"><a href="<?= e(url('login.php')) ?>"><?= icon('arrow-left') ?> <?= e(t('reset.back', 'Back to log in')) ?></a></p>
    <?php else: ?>
      <h1><?= e(t('reset.title', 'Choose a new password')) ?></h1>
      <p class="auth-lead"><?= e(sprintf(t('reset.lead', 'Resetting the password for %s.'), (string) $reset['email'])) ?></p>

      <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form class="auth-form" method="post" action="<?= e(url('reset-password.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field">
          <label for="password"><?= e(t('reset.new_password', 'New password')) ?></label>
          <input id="password" type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password" autofocus>
        </div>
        <div class="field">
          <label for="password_confirm"><?= e(t('reset.confirm_password', 'Confirm new password')) ?></label>
          <input id="password_confirm" type="password" name="password_confirm" required minlength="8" maxlength="72" autocomplete="new-password">
        </div>
        <p class="field-hint"><?= e(t('reset.hint', 'At least 8 characters, with one letter and one number.')) ?></p>
        <button class="btn btn-primary btn-block" type="submit"><?= e(t('reset.submit', 'Update password')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
