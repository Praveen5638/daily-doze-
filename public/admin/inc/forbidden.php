<?php
declare(strict_types=1);

$adminTitle = 'Access denied';
$adminPage = '';
require __DIR__ . '/header.php';
?>
<div class="admin-panel">
  <div class="admin-panel-body" style="text-align:center;padding:50px 24px">
    <p class="error-code" style="font-size:3.4rem;margin:0">403</p>
    <h1 style="font-size:1.5rem">You do not have permission for this section</h1>
    <p class="muted">Your role (<?= e($admin['role_name'] ?? 'staff') ?>) does not include access to this page. Ask a super admin if you need it.</p>
    <a class="btn btn-primary" href="<?= e(admin_url('dashboard.php')) ?>">Back to dashboard</a>
  </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
