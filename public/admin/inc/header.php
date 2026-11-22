<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
Auth::requireAdmin();

$admin = Auth::admin();
$adminTitle = $adminTitle ?? 'Dashboard';
$adminPage = $adminPage ?? 'dashboard';
$admin = $admin ?? [];
$unread = Notifications::unreadCount();
$notifications = Notifications::latest(6);

$pendingOrders = Auth::adminCan('orders.manage')
    ? (int) Database::value("SELECT COUNT(*) FROM orders WHERE status IN ('pending','confirmed','processing','packed')")
    : 0;
$newEnquiries = Auth::adminCan('enquiries.manage')
    ? (int) Database::value("SELECT COUNT(*) FROM enquiries WHERE status = 'new'")
    : 0;
$newMessages = Auth::adminCan('enquiries.manage')
    ? (int) Database::value("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")
    : 0;
$pendingReviews = Auth::adminCan('reviews.manage')
    ? (int) Database::value("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")
    : 0;

$navItems = [
    ['dashboard', 'dashboard.php', 'Dashboard', 'grid', 'dashboard.view', 0],
    ['orders', 'orders.php', 'Orders', 'package', 'orders.manage', $pendingOrders],
    ['products', 'products.php', 'Products', 'tag', 'products.manage', 0],
    ['categories', 'categories.php', 'Categories', 'grid', 'categories.manage', 0],
    ['inventory', 'inventory.php', 'Inventory', 'box', 'inventory.manage', 0],
    ['coupons', 'coupons.php', 'Coupons', 'tag', 'coupons.manage', 0],
    ['customers', 'customers.php', 'Customers', 'users', 'customers.manage', 0],
    ['enquiries', 'enquiries.php', 'Enquiries', 'clipboard', 'enquiries.manage', $newEnquiries],
    ['messages', 'messages.php', 'Messages', 'message', 'enquiries.manage', $newMessages],
    ['reviews', 'reviews.php', 'Reviews', 'star', 'reviews.manage', $pendingReviews],
    ['content', 'content.php', 'Content', 'file', 'content.manage', 0],
    ['staff', 'staff.php', 'Staff', 'shield', 'users.manage', 0],
    ['settings', 'settings.php', 'Settings', 'settings', 'settings.manage', 0],
    ['audit', 'audit.php', 'Audit log', 'eye', 'settings.manage', 0],
];

$initial = strtoupper(mb_substr((string) ($admin['name'] ?? 'A'), 0, 1));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <title><?= e($adminTitle) ?> | Admin · <?= e(setting('site_name', 'Daily Dose Export')) ?></title>
  <link rel="icon" href="<?= e(url('assets/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(url('assets/css/main.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('admin/assets/admin.css')) ?>">
</head>
<body class="admin-body">
<div class="admin-shell">
  <div class="admin-overlay" data-admin-overlay></div>
  <aside class="admin-sidebar" aria-label="Admin navigation">
    <a class="admin-brand" href="<?= e(admin_url('dashboard.php')) ?>">
      <span class="brand-mark"><?= icon('leaf') ?></span>
      <span><strong>Daily Dose</strong><small>Admin portal</small></span>
    </a>
    <nav class="admin-nav">
      <?php $lastGroup = null; ?>
      <?php foreach ($navItems as [$key, $href, $label, $iconName, $permission, $count]): ?>
        <?php if (!Auth::adminCan($permission)) { continue; } ?>
        <?php
        $group = in_array($key, ['dashboard', 'orders', 'products', 'categories', 'inventory', 'coupons'], true) ? 'Store' : 'Management';
        if (in_array($key, ['customers', 'enquiries', 'messages', 'reviews'], true)) { $group = 'People'; }
        if (in_array($key, ['content', 'staff', 'settings', 'audit'], true)) { $group = 'Website'; }
        if ($group !== $lastGroup) {
            echo '<span class="admin-nav-label">' . e($group) . '</span>';
            $lastGroup = $group;
        }
        ?>
        <a class="<?= $adminPage === $key ? 'is-active' : '' ?>" href="<?= e(admin_url($href)) ?>">
          <?= icon($iconName) ?> <?= e($label) ?>
          <?php if ($count > 0): ?><span class="pill-count"><?= (int) $count ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="admin-sidebar-foot">
      <span>v1.0 · <?= e(date('Y')) ?></span>
      <form method="post" action="<?= e(admin_url('logout.php')) ?>">
        <?= csrf_field() ?>
        <button class="link-btn" type="submit" style="background:none;border:0;color:inherit;font:inherit;cursor:pointer">Log out</button>
      </form>
    </div>
  </aside>

  <div class="admin-main">
    <header class="admin-topbar">
      <button class="icon-btn admin-menu-btn" type="button" data-admin-menu aria-label="Toggle navigation"><?= icon('menu') ?></button>
      <div class="admin-crumb">
        <small>Admin · <?= e(setting('site_name', 'Daily Dose Export')) ?></small>
        <strong><?= e($adminTitle) ?></strong>
      </div>
      <div class="admin-topbar-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('')) ?>" target="_blank" rel="noopener"><?= icon('external') ?> <span style="display:inline">View site</span></a>
        <div class="notif-wrap">
          <button class="icon-btn" type="button" data-notif-toggle aria-label="Notifications" aria-haspopup="true">
            <?= icon('bell') ?>
            <?php if ($unread > 0): ?><span class="count-badge" style="position:absolute;top:-3px;right:-3px;width:17px;height:17px;font-size:.58rem"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
          </button>
          <div class="notif-panel" data-notif-panel>
            <div class="notif-panel-head">
              <strong>Notifications</strong>
              <a class="small" href="<?= e(admin_url('notifications.php')) ?>">View all</a>
            </div>
            <div class="notif-list">
              <?php if ($notifications === []): ?>
                <p class="small muted" style="padding:16px">No notifications yet.</p>
              <?php else: ?>
                <?php foreach ($notifications as $note): ?>
                  <a class="notif-item" href="<?= e(admin_url($note['link'] ?: 'notifications.php')) ?>">
                    <strong><?= e($note['title']) ?></strong>
                    <?= e($note['message']) ?>
                    <small><?= e(date_display($note['created_at'], true)) ?><?= (int) $note['is_read'] === 0 ? ' · New' : '' ?></small>
                  </a>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="admin-user">
          <span class="admin-avatar"><?= e($initial) ?></span>
          <span><strong><?= e($admin['name'] ?? '') ?></strong><small><?= e($admin['role_name'] ?? 'Staff') ?></small></span>
        </div>
      </div>
    </header>

    <main class="admin-content" id="admin-main">
      <?php foreach (flashes() as $flash): ?>
        <div class="alert alert-<?= e($flash['type'] === 'error' ? 'error' : 'success') ?>" role="status"><?= e($flash['message']) ?></div>
      <?php endforeach; ?>
