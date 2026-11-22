<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!is_post()) {
    redirect(Auth::customer() !== null ? 'account.php' : '');
}

verify_csrf();
Auth::logout();
flash('success', 'You have been logged out. Your cart has been saved on this device.');
redirect('');
