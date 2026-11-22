<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$nextRaw = (string) ($_POST['next'] ?? ($_GET['next'] ?? ''));
$next = safe_local_path($nextRaw, 'admin/index.php');
if (!str_starts_with($next, 'admin/')) {
    $next = 'admin/index.php';
}

if (Auth::admin() !== null) {
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
        $result = Auth::adminLogin($email, $password);
        if ($result['ok']) {
            redirect($next);
        }
        $error = $result['message'];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <title>Staff login | <?= e(setting('site_name', 'Daily Dose Export')) ?></title>
  <link rel="icon" href="<?= e(url('assets/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(url('assets/css/main.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('admin/assets/admin.css')) ?>">
</head>
<body class="admin-body">
<div class="admin-login-shell">
  <div class="admin-login-card">
    <div class="admin-login-brand">
      <span class="brand-mark"><?= icon('leaf') ?></span>
      <span><strong>Daily Dose</strong><small>Export · Admin</small></span>
    </div>
    <h1>Staff sign in</h1>
    <p>Authorised staff only. All sign-ins and changes are recorded in the audit log.</p>

    <?php if ($error !== ''): ?>
      <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form class="admin-form" method="post" action="<?= e(admin_url('login.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="field">
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" value="<?= e($email) ?>" required maxlength="190" autocomplete="username" autofocus>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required maxlength="72" autocomplete="current-password">
      </div>
      <button class="btn btn-primary btn-block" type="submit"><?= icon('lock') ?> Sign in</button>
    </form>

    <p class="admin-login-foot"><a href="<?= e(url('')) ?>"><?= icon('arrow-left') ?> Back to the website</a></p>
  </div>
</div>
</body>
</html>
