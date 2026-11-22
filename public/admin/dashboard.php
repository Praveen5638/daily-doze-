<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin('dashboard.view');
$adminUser = Auth::admin();

$totalOrders = (int) Database::value('SELECT COUNT(*) FROM orders');
$pendingOrders = (int) Database::value("SELECT COUNT(*) FROM orders WHERE status IN ('pending','confirmed','processing','packed')");
$todayOrders = (int) Database::value('SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()');
$paidRevenue = (float) Database::value("SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status = 'paid'");
$customersCount = (int) Database::value("SELECT COUNT(*) FROM customers WHERE status <> 'disabled'");
$newEnquiries = Auth::adminCan('enquiries.manage') ? (int) Database::value("SELECT COUNT(*) FROM enquiries WHERE status = 'new'") : 0;
$pendingReviews = Auth::adminCan('reviews.manage') ? (int) Database::value("SELECT COUNT(*) FROM reviews WHERE status = 'pending'") : 0;

$recentOrders = Database::all('SELECT * FROM orders ORDER BY created_at DESC, id DESC LIMIT 8');

$lowStock = [];
if (Auth::adminCan('inventory.manage')) {
    $lowStock = Database::all(
        'SELECT v.id, v.sku, v.pack_size, v.price, p.name AS product_name, p.slug AS product_slug,
                i.stock_qty, i.low_stock_threshold
         FROM product_variants v
         JOIN products p ON p.id = v.product_id
         JOIN product_inventory i ON i.variant_id = v.id
         WHERE v.status = "active" AND p.status IN ("published","draft")
           AND i.stock_qty <= i.low_stock_threshold
         ORDER BY i.stock_qty ASC, p.name ASC
         LIMIT 8'
    );
}

$recentEnquiries = Auth::adminCan('enquiries.manage')
    ? Database::all('SELECT * FROM enquiries ORDER BY created_at DESC, id DESC LIMIT 5')
    : [];

$days = [];
$maxDay = 1;
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $days[$day] = 0;
}
$chartRows = Database::all(
    'SELECT DATE(created_at) AS day, COUNT(*) AS count FROM orders
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)'
);
foreach ($chartRows as $row) {
    if (isset($days[(string) $row['day']])) {
        $days[(string) $row['day']] = (int) $row['count'];
        $maxDay = max($maxDay, (int) $row['count']);
    }
}

$adminTitle = 'Dashboard';
$adminPage = 'dashboard';
require __DIR__ . '/inc/header.php';
?>

<div class="admin-page-head">
  <div>
    <h1>Welcome, <?= e(explode(' ', (string) $adminUser['name'])[0]) ?></h1>
    <p>Here is what needs attention across orders, stock and enquiries.</p>
  </div>
  <div class="admin-page-actions">
    <?php if (Auth::adminCan('products.manage')): ?><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('product-edit.php')) ?>"><?= icon('plus') ?> Add product</a><?php endif; ?>
    <?php if (Auth::adminCan('orders.manage')): ?><a class="btn btn-primary btn-sm" href="<?= e(admin_url('orders.php')) ?>"><?= icon('package') ?> Manage orders</a><?php endif; ?>
  </div>
</div>

<div class="stat-grid">
  <div class="stat-card">
    <?= icon('package') ?>
    <small>Orders to action</small>
    <strong><?= $pendingOrders ?></strong>
    <span><?= $todayOrders ?> placed today · <?= $totalOrders ?> total</span>
  </div>
  <div class="stat-card">
    <?= icon('card') ?>
    <small>Recorded paid revenue</small>
    <strong><?= e(money($paidRevenue)) ?></strong>
    <span>Captured payments marked paid</span>
  </div>
  <div class="stat-card">
    <?= icon('users') ?>
    <small>Customers</small>
    <strong><?= $customersCount ?></strong>
    <span>Active and pending-deletion accounts</span>
  </div>
  <div class="stat-card <?= $pendingReviews > 0 ? 'is-alert' : '' ?>">
    <?= icon('star') ?>
    <small>Reviews awaiting approval</small>
    <strong><?= $pendingReviews ?></strong>
    <span><?= $newEnquiries ?> new enquiries</span>
  </div>
</div>

<div class="admin-grid-2">
  <div>
    <section class="admin-panel">
      <div class="admin-panel-head">
        <h2>Orders · last 7 days</h2>
        <span class="pill pill-gold"><?= array_sum($days) ?> orders</span>
      </div>
      <div class="admin-panel-body">
        <div class="admin-chart" role="img" aria-label="Orders per day for the last seven days">
          <?php foreach ($days as $day => $count): ?>
            <div class="admin-chart-bar">
              <span style="height:<?= max(3, (int) round(($count / $maxDay) * 100)) ?>%" title="<?= (int) $count ?> orders"></span>
              <small><?= e(date('D', strtotime($day))) ?></small>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="admin-panel">
      <div class="admin-panel-head">
        <h2>Recent orders</h2>
        <a class="small" href="<?= e(admin_url('orders.php')) ?>">All orders</a>
      </div>
      <div class="table-scroll">
        <table class="admin-table">
          <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php if ($recentOrders === []): ?>
              <tr><td colspan="5" class="muted">No orders yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($recentOrders as $order): ?>
              <tr>
                <td><strong><?= e($order['order_number']) ?></strong><span class="cell-sub"><?= e(date_display($order['created_at'], true)) ?></span></td>
                <td><?= e($order['customer_name']) ?><span class="cell-sub"><?= e($order['phone']) ?></span></td>
                <td class="num"><?= e(money($order['total'])) ?></td>
                <td><span class="<?= e(status_class((string) $order['status'])) ?>"><?= e(order_status_label((string) $order['status'])) ?></span></td>
                <td><a class="small" href="<?= e(admin_url('order-view.php?id=' . (int) $order['id'])) ?>">Open</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <div>
    <?php if (Auth::adminCan('inventory.manage')): ?>
      <section class="admin-panel">
        <div class="admin-panel-head">
          <h2>Low stock</h2>
          <a class="small" href="<?= e(admin_url('inventory.php?low=1')) ?>">Inventory</a>
        </div>
        <div class="admin-list">
          <?php if ($lowStock === []): ?>
            <p class="small muted" style="padding:16px">No variants at or below their low-stock threshold.</p>
          <?php endif; ?>
          <?php foreach ($lowStock as $row): ?>
            <div class="admin-list-item">
              <div><strong><?= e($row['product_name']) ?></strong><small><?= e($row['pack_size']) ?> · <?= e($row['sku']) ?></small></div>
              <div class="right">
                <strong style="color:<?= (int) $row['stock_qty'] === 0 ? 'var(--danger)' : 'var(--brown)' ?>"><?= (int) $row['stock_qty'] ?> left</strong>
                <small>threshold <?= (int) $row['low_stock_threshold'] ?></small>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if (Auth::adminCan('enquiries.manage')): ?>
      <section class="admin-panel">
        <div class="admin-panel-head">
          <h2>Latest enquiries</h2>
          <a class="small" href="<?= e(admin_url('enquiries.php')) ?>">All</a>
        </div>
        <div class="admin-list">
          <?php if ($recentEnquiries === []): ?>
            <p class="small muted" style="padding:16px">No enquiries yet.</p>
          <?php endif; ?>
          <?php foreach ($recentEnquiries as $enquiry): ?>
            <div class="admin-list-item">
              <div><strong><?= e($enquiry['reference']) ?> · <?= e(ucfirst((string) $enquiry['type'])) ?></strong><small><?= e($enquiry['name']) ?> · <?= e($enquiry['country']) ?></small></div>
              <div class="right"><span class="<?= e(status_class((string) $enquiry['status'])) ?>"><?= e(enquiry_status_label((string) $enquiry['status'])) ?></span></div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <section class="admin-panel">
      <div class="admin-panel-head"><h2>Quick links</h2></div>
      <div class="admin-panel-body" style="display:grid;gap:8px">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('')) ?>" target="_blank" rel="noopener"><?= icon('external') ?> Open storefront</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('notifications.php')) ?>"><?= icon('bell') ?> Notifications</a>
        <?php if (Auth::adminCan('settings.manage')): ?>
          <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('audit.php')) ?>"><?= icon('eye') ?> Audit log</a>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
