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
        $error = 'Too many reset requests from this connection. Please try again in an hour.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter the email address you registered with.';
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
                'Reset your ' . setting('site_name', 'Daily Dose Export') . ' password',
                email_wrap(
                    'Reset your password',
                    '<p>Hello ' . e((string) $customer['first_name']) . ',</p>'
                    . '<p>We received a request to reset your password. This link is valid for one hour and can be used once.</p>'
                    . '<p>If you did not request this, you can safely ignore this email — your password has not changed.</p>',
                    ['url' => $resetUrl, 'label' => 'Choose a new password']
                )
            );
        }
        flash('success', 'If an account exists for that email address, a password reset link is on its way. Please check your inbox and spam folder.');
        redirect('login.php');
    }
}

$seo = [
    'title' => 'Forgot password | ' . setting('site_name', 'Daily Dose Export'),
    'description' => 'Request a password reset link for your Daily Dose Export account.',
    'canonical' => url('forgot-password.php'),
    'robots' => 'noindex,follow',
];
$activeNav = '';
$bodyClass = 'login';
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="container auth-page">
  <div class="auth-card auth-card-narrow">
    <h1>Forgot your password?</h1>
    <p class="auth-lead">Enter the email address linked to your account and we will send you a secure reset link.</p>

    <?php if ($error !== ''): ?>
      <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form class="auth-form" method="post" action="<?= e(url('forgot-password.php')) ?>">
      <?= csrf_field() ?>
      <div class="field">
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" value="<?= e($email) ?>" required maxlength="190" autocomplete="email" autofocus>
      </div>
      <button class="btn btn-primary btn-block" type="submit">Send reset link</button>
    </form>

    <p class="auth-alt"><a href="<?= e(url('login.php')) ?>"><?= icon('arrow-left') ?> Back to log in</a></p>
    <p class="auth-security"><?= icon('lock') ?> For your security, we never confirm whether an email address is registered.</p>
  </div>
</div>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
