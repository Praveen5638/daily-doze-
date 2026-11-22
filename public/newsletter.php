<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!is_post()) {
    redirect('');
}

verify_csrf();
$returnTo = safe_local_path(post_str('redirect_to'), '');
if (trim((string) ($_POST['company_website'] ?? '')) !== '') {
    flash('success', 'Thank you for subscribing.');
    redirect($returnTo);
}
$email = strtolower(post_str('email'));

if (!RateLimiter::hit('newsletter:' . request_ip(), 'subscribe', 8, 3600)) {
    flash('error', 'Too many subscription attempts. Please try again later.');
    redirect($returnTo);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    flash('error', 'Please enter a valid email address.');
    redirect($returnTo);
}

$existing = Database::one('SELECT id, status, token FROM newsletter_subscribers WHERE email = ?', [$email]);
if ($existing === null) {
    $token = bin2hex(random_bytes(32));
    Database::insert('newsletter_subscribers', [
        'email' => $email,
        'status' => 'subscribed',
        'token' => $token,
    ]);
    $unsubscribeUrl = url('newsletter-unsubscribe.php?token=' . $token);
    Mailer::send(
        $email,
        'Welcome to ' . setting('site_name', 'Daily Dose Export'),
        email_wrap(
            'You are on the list',
            '<p>Thank you for subscribing to updates from ' . e(setting('site_name', 'Daily Dose Export')) . '.</p>'
            . '<p>' . e(setting('newsletter_text', 'Occasional updates only. Unsubscribe anytime.')) . '</p>'
            . '<p style="font-size:12px;color:#737A73">If you did not subscribe, you can <a href="' . e($unsubscribeUrl) . '">unsubscribe here</a>.</p>',
            ['url' => url('shop.php'), 'label' => 'Explore the shop']
        )
    );
} elseif ($existing['status'] !== 'subscribed') {
    Database::update('newsletter_subscribers', ['status' => 'subscribed'], 'id = ?', [$existing['id']]);
}

flash('success', 'Thank you for subscribing. Check your inbox for a confirmation.');
redirect($returnTo);
