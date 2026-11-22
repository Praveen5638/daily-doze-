<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$next = safe_local_path($_POST['next'] ?? ($_GET['next'] ?? ''), 'account.php');
if (Auth::customer() !== null) {
    redirect($next);
}

$email = '';
$error = '';

if (is_post()) {
    verify_csrf();
    $email = strtolower(post_str('email'));
    $password = (string) ($_POST['password'] ?? '');
    if ($email === '' || $password === '') {
        $error = 'Please enter your email address and password.';
    } else {
        $result = Auth::login($email, $password);
        if ($result['ok']) {
            redirect($next);
        }
        $error = $result['message'];
    }
}

$seo = [
    'title' => 'Log in | ' . setting('site_name', 'Daily Dose Export'),
    'description' => 'Log in to your Daily Dose Export account to view orders, saved addresses and your wishlist.',
    'canonical' => url('login.php'),
    'robots' => 'noindex,follow',
];
$activeNav = '';
$bodyClass = 'login';
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="container auth-page">
  <div class="auth-layout">
    <div class="auth-media">
      <img src="<?= e(asset_url('assets/images/cat-spices.jpg')) ?>" alt="Indian spices" loading="lazy" width="800" height="1000">
      <div class="auth-media-copy">
        <h2>Your pantry, remembered</h2>
        <p>Save delivery addresses, follow your orders and keep a wishlist of everyday essentials.</p>
      </div>
    </div>
    <div class="auth-card">
      <h1>Welcome back</h1>
      <p class="auth-lead">Log in to continue to your account.</p>

      <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form class="auth-form" method="post" action="<?= e(url('login.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="field">
          <label for="email">Email address</label>
          <input id="email" type="email" name="email" value="<?= e($email) ?>" required maxlength="190" autocomplete="email" autofocus>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" type="password" name="password" required maxlength="72" autocomplete="current-password">
        </div>
        <div class="auth-small-links">
          <a href="<?= e(url('forgot-password.php')) ?>">Forgot your password?</a>
          <a href="<?= e(url('register.php?next=' . urlencode($next))) ?>">Create an account</a>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Log in <?= icon('arrow-right') ?></button>
      </form>

      <p class="auth-alt">New to Daily Dose Export? <a href="<?= e(url('register.php?next=' . urlencode($next))) ?>">Create your account</a></p>
      <p class="auth-security"><?= icon('lock') ?> Sessions are protected with secure cookies and CSRF tokens.</p>
    </div>
  </div>
</div>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
