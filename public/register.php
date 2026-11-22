<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$next = safe_local_path($_POST['next'] ?? ($_GET['next'] ?? ''), 'account.php');
if (Auth::customer() !== null) {
    redirect($next);
}

$form = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
];
$error = '';
$fieldErrors = [];

if (is_post()) {
    verify_csrf();
    foreach ($form as $key => $_) {
        $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    $terms = isset($_POST['terms']);

    if (!RateLimiter::hit('register:' . request_ip(), 'register', 10, 3600)) {
        $error = 'Too many registration attempts from this connection. Please try again later.';
    } elseif ($form['first_name'] === '') {
        $fieldErrors['first_name'] = 'Enter your first name.';
    } elseif ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['email'] = 'Enter a valid email address.';
    } elseif (strlen(preg_replace('/\D/', '', $form['phone']) ?? '') < 10) {
        $fieldErrors['phone'] = 'Enter a valid mobile number.';
    } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,72}$/', $password)) {
        $fieldErrors['password'] = 'Use at least 8 characters, including a letter and a number.';
    } elseif ($password !== $confirm) {
        $fieldErrors['password_confirm'] = 'Passwords do not match.';
    } elseif (!$terms) {
        $error = 'Please accept the Terms of Service and Privacy Policy to continue.';
    }

    if ($error === '' && $fieldErrors === []) {
        $result = Auth::register($form + ['password' => $password]);
        if ($result['ok']) {
            RateLimiter::clear('register:' . request_ip(), 'register');
            redirect($next);
        }
        $error = $result['message'];
    }
}

$seo = [
    'title' => 'Create your account | ' . setting('site_name', 'Daily Dose Export'),
    'description' => 'Create a Daily Dose Export account to order pure pantry staples, save addresses and keep a wishlist.',
    'canonical' => url('register.php'),
    'robots' => 'noindex,follow',
];
$activeNav = '';
$bodyClass = 'register';
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="container auth-page">
  <div class="auth-layout">
    <div class="auth-media">
      <img src="<?= e(asset_url('assets/images/cat-millets.jpg')) ?>" alt="Millets and grains" loading="lazy" width="800" height="1000">
      <div class="auth-media-copy">
        <h2>From our pantry to yours</h2>
        <p>Clean, carefully sourced ingredients — with a record of every order from mill to doorstep.</p>
      </div>
    </div>
    <div class="auth-card">
      <h1>Create your account</h1>
      <p class="auth-lead">It takes a minute, and checkout gets faster every time.</p>

      <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form class="auth-form" method="post" action="<?= e(url('register.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="field-row">
          <div class="field">
            <label for="first_name">First name</label>
            <input id="first_name" type="text" name="first_name" value="<?= e($form['first_name']) ?>" required maxlength="80" autocomplete="given-name" autofocus>
            <?php if (isset($fieldErrors['first_name'])): ?><p class="field-error"><?= e($fieldErrors['first_name']) ?></p><?php endif; ?>
          </div>
          <div class="field">
            <label for="last_name">Last name</label>
            <input id="last_name" type="text" name="last_name" value="<?= e($form['last_name']) ?>" maxlength="80" autocomplete="family-name">
          </div>
        </div>
        <div class="field">
          <label for="email">Email address</label>
          <input id="email" type="email" name="email" value="<?= e($form['email']) ?>" required maxlength="190" autocomplete="email">
          <?php if (isset($fieldErrors['email'])): ?><p class="field-error"><?= e($fieldErrors['email']) ?></p><?php endif; ?>
        </div>
        <div class="field">
          <label for="phone">Mobile number</label>
          <input id="phone" type="tel" name="phone" value="<?= e($form['phone']) ?>" required maxlength="20" autocomplete="tel" inputmode="tel">
          <?php if (isset($fieldErrors['phone'])): ?><p class="field-error"><?= e($fieldErrors['phone']) ?></p><?php endif; ?>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password">
            <?php if (isset($fieldErrors['password'])): ?><p class="field-error"><?= e($fieldErrors['password']) ?></p><?php endif; ?>
          </div>
          <div class="field">
            <label for="password_confirm">Confirm password</label>
            <input id="password_confirm" type="password" name="password_confirm" required minlength="8" maxlength="72" autocomplete="new-password">
            <?php if (isset($fieldErrors['password_confirm'])): ?><p class="field-error"><?= e($fieldErrors['password_confirm']) ?></p><?php endif; ?>
          </div>
        </div>
        <p class="field-hint">At least 8 characters, with one letter and one number.</p>
        <label class="check-line">
          <input type="checkbox" name="terms" value="1" <?= isset($_POST['terms']) ? 'checked' : '' ?>>
          <span>I agree to the <a href="<?= e(url('pages/terms-and-conditions')) ?>" target="_blank" rel="noopener">Terms of Service</a> and <a href="<?= e(url('pages/privacy-policy')) ?>" target="_blank" rel="noopener">Privacy Policy</a>.</span>
        </label>
        <button class="btn btn-primary btn-block" type="submit">Create account <?= icon('arrow-right') ?></button>
      </form>

      <p class="auth-alt">Already have an account? <a href="<?= e(url('login.php?next=' . urlencode($next))) ?>">Log in</a></p>
    </div>
  </div>
</div>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
