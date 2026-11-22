<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

Auth::requireAdmin('dashboard.view');
redirect('admin/dashboard.php');
