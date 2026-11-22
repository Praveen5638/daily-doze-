<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!is_post()) {
    redirect('');
}

verify_csrf();
$returnTo = safe_local_path(post_str('redirect_to'), '');
if (trim((string) ($_POST['company_website'] ?? '')) !== '') {
    flash('success', t('newsletter.flash_thanks', 'Thank you for subscribing.'));
    redirect($returnTo);
}
$email = strtolower(post_str('email'));

if (!RateLimiter::hit('newsletter:' . request_ip(), 'subscribe', 8, 3600)) {
    flash('error', t('newsletter.flash_limit', 'Too many subscription attempts. Please try again later.'));
    redirect($returnTo);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    flash('error', t('newsletter.flash_invalid', 'Please enter a valid email address.'));
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
    $unsubscribeLink = '<a href="' . e($unsubscribeUrl) . '">' . e(t('newsletter.unsubscribe_here', 'unsubscribe here')) . '</a>';
    Mailer::send(
        $email,
        t('newsletter.welcome_subject', 'Welcome to ' . setting('site_name', 'Daily Dose Export')),
        email_wrap(
            t('newsletter.welcome_heading', 'You are on the list'),
            '<p>' . e(t('newsletter.welcome_body', 'Thank you for subscribing to updates from ' . setting('site_name', 'Daily Dose Export') . '.')) . '</p>'
            . '<p>' . e(setting('newsletter_text', 'Occasional updates only. Unsubscribe anytime.')) . '</p>'
            . '<p style="font-size:12px;color:#737A73">' . sprintf(e(t('newsletter.welcome_alt_html', 'If you did not subscribe, you can %s.')), $unsubscribeLink) . '</p>',
            ['url' => url('shop.php'), 'label' => t('newsletter.welcome_button', 'Explore the shop')]
        )
    );
} elseif ($existing['status'] !== 'subscribed') {
    Database::update('newsletter_subscribers', ['status' => 'subscribed'], 'id = ?', [$existing['id']]);
}

flash('success', t('newsletter.flash_success', 'Thank you for subscribing. Check your inbox for a confirmation.'));
redirect($returnTo);
