<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

if (!is_post()) {
    redirect(Auth::admin() !== null ? 'admin/dashboard.php' : 'admin/login.php');
}
verify_csrf();
Auth::adminLogout();
flash('success', 'You have been signed out of the admin portal.');
redirect('admin/login.php');
