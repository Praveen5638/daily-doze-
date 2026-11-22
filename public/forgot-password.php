<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (Auth::customer() !== null) {
    redirect('account.php');
}

$email = '';
$error = '';

if (is_post()) {
    verify_csrf();
    $email = strtolower(post_str('email'));
    if (!RateLimiter::hit('forgot:' . request_ip(), 'forgot', 6, 3600)) {
        $error = t('forgot.error_limit', 'Too many reset requests from this connection. Please try again in an hour.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('forgot.error_email', 'Please enter the email address you registered with.');
    } else {
        $customer = Database::one('SELECT id, first_name, email, status FROM customers WHERE email = ?', [$email]);
        if ($customer !== null && $customer['status'] !== 'disabled') {
            Database::q('DELETE FROM password_resets WHERE customer_id = ?', [$customer['id']]);
            $token = bin2hex(random_bytes(32));
            Database::insert('password_resets', [
                'customer_id' => (int) $customer['id'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            ]);
            $resetUrl = url('reset-password.php?token=' . $token);
            Mailer::send(
                $customer['email'],
                sprintf(t('forgot.email_subject', 'Reset your %s password'), setting('site_name', 'Daily Dose Export')),
                email_wrap(
                    t('forgot.email_heading', 'Reset your password'),
                    '<p>' . e(sprintf(t('auth.email_hello', 'Hello %s,'), (string) $customer['first_name'])) . '</p>'
                    . '<p>' . e(t('forgot.email_body', 'We received a request to reset your password. This link is valid for one hour and can be used once. If you did not request this, you can safely ignore this email — your password has not changed.')) . '</p>',
                    ['url' => $resetUrl, 'label' => t('forgot.email_button', 'Choose a new password')]
                )
            );
        }
        flash('success', t('forgot.success', 'If an account exists for that email address, a password reset link is on its way. Please check your inbox and spam folder.'));
        redirect('login.php');
    }
}

$seo = [
    'title' => t('forgot.title', 'Forgot password') . ' | ' . setting('site_name', 'Daily Dose Export'),
    'description' => t('forgot.meta', 'Request a password reset link for your Daily Dose Export account.'),
    'canonical' => url('forgot-password.php'),
    'robots' => 'noindex,follow',
];
$activeNav = '';
$bodyClass = 'login';
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="container auth-page">
  <div class="auth-card auth-card-narrow">
    <h1><?= e(t('forgot.title', 'Forgot your password?')) ?></h1>
    <p class="auth-lead"><?= e(t('forgot.lead', 'Enter the email address linked to your account and we will send you a secure reset link.')) ?></p>

    <?php if ($error !== ''): ?>
      <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form class="auth-form" method="post" action="<?= e(url('forgot-password.php')) ?>">
      <?= csrf_field() ?>
      <div class="field">
        <label for="email"><?= e(t('forgot.email', 'Email address')) ?></label>
        <input id="email" type="email" name="email" value="<?= e($email) ?>" required maxlength="190" autocomplete="email" autofocus>
      </div>
      <button class="btn btn-primary btn-block" type="submit"><?= e(t('forgot.submit', 'Send reset link')) ?></button>
    </form>

    <p class="auth-alt"><a href="<?= e(url('login.php')) ?>"><?= icon('arrow-left') ?> <?= e(t('forgot.back', 'Back to log in')) ?></a></p>
    <p class="auth-security"><?= icon('lock') ?> <?= e(t('forgot.security', 'For your security, we never confirm whether an email address is registered.')) ?></p>
  </div>
</div>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
