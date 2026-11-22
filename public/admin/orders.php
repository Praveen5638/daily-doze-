<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin('orders.manage');

$statuses = ['pending', 'confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery', 'delivered', 'cancelled', 'return_requested', 'returned', 'refunded'];
$payments = ['unpaid', 'pending', 'paid', 'failed', 'refunded', 'partially_refunded'];

$status = (string) ($_GET['status'] ?? '');
if (!in_array($status, $statuses, true)) {
    $status = '';
}
$payment = (string) ($_GET['payment'] ?? '');
if (!in_array($payment, $payments, true)) {
    $payment = '';
}
$q = trim((string) ($_GET['q'] ?? ''));
$pageNo = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$where = [];
$params = [];
if ($status !== '') {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($payment !== '') {
    $where[] = 'payment_status = ?';
    $params[] = $payment;
}
if ($q !== '') {
    $where[] = '(order_number LIKE ? OR customer_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
$total = (int) Database::value('SELECT COUNT(*) FROM orders' . $whereSql, $params);
$pages = max(1, (int) ceil($total / $perPage));
$pageNo = min($pageNo, $pages);
$offset = ($pageNo - 1) * $perPage;

$orders = Database::all(
    'SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
     FROM orders o' . $whereSql . ' ORDER BY o.created_at DESC, o.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
    $params
);

$statusCounts = [];
foreach (Database::all('SELECT status, COUNT(*) AS total FROM orders GROUP BY status') as $row) {
    $statusCounts[(string) $row['status']] = (int) $row['total'];
}
$filterUrl = static function (array $overrides = []) use ($status, $payment, $q): string {
    $query = array_filter(array_merge(['status' => $status, 'payment' => $payment, 'q' => $q], $overrides), static fn ($value): bool => $value !== '' && $value !== null);
    return admin_url('orders.php' . ($query !== [] ? '?' . http_build_query($query) : ''));
};

$adminTitle = 'Orders';
$adminPage = 'orders';
require __DIR__ . '/inc/header.php';
?>

<div class="admin-page-head">
  <div>
    <h1>Orders</h1>
    <p>Review, update and track customer orders. Stock is restored automatically when an order is cancelled or refunded.</p>
  </div>
  <div class="admin-page-actions">
    <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('orders.php?status=pending')) ?>">Pending (<?= $statusCounts['pending'] ?? 0 ?>)</a>
  </div>
</div>

<div class="admin-panel">
  <div class="admin-toolbar">
    <form method="get" action="<?= e(admin_url('orders.php')) ?>">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Order number, name, email, phone" aria-label="Search orders">
      <select name="status" aria-label="Filter by status">
        <option value="">All statuses</option>
        <?php foreach ($statuses as $s): ?>
          <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(order_status_label($s)) ?> (<?= $statusCounts[$s] ?? 0 ?>)</option>
        <?php endforeach; ?>
      </select>
      <select name="payment" aria-label="Filter by payment">
        <option value="">All payments</option>
        <?php foreach ($payments as $p): ?>
          <option value="<?= e($p) ?>" <?= $payment === $p ? 'selected' : '' ?>><?= e(payment_status_label($p)) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary btn-sm" type="submit">Filter</button>
      <?php if ($status !== '' || $payment !== '' || $q !== ''): ?><a class="small" href="<?= e(admin_url('orders.php')) ?>">Clear</a><?php endif; ?>
    </form>
    <span class="spacer"></span>
    <span class="inline-note"><?= $total ?> order<?= $total === 1 ? '' : 's' ?></span>
  </div>
  <div class="table-scroll">
    <table class="admin-table">
      <thead>
        <tr><th>Order</th><th>Customer</th><th>Placed</th><th>Items</th><th>Payment</th><th>Status</th><th class="num">Total</th><th></th></tr>
      </thead>
      <tbody>
        <?php if ($orders === []): ?>
          <tr><td colspan="8" class="muted">No orders match these filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($orders as $order): ?>
          <tr>
            <td><strong><?= e($order['order_number']) ?></strong><span class="cell-sub"><?= $order['payment_method'] === 'cod' ? 'COD' : 'Online' ?></span></td>
            <td><?= e($order['customer_name']) ?><span class="cell-sub"><?= e($order['phone']) ?></span></td>
            <td><?= e(date_display($order['created_at'], true)) ?></td>
            <td><?= (int) $order['item_count'] ?></td>
            <td><span class="<?= e(status_class((string) $order['payment_status'])) ?>"><?= e(payment_status_label((string) $order['payment_status'])) ?></span></td>
            <td><span class="<?= e(status_class((string) $order['status'])) ?>"><?= e(order_status_label((string) $order['status'])) ?></span></td>
            <td class="num"><strong><?= e(money($order['total'])) ?></strong></td>
            <td><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('order-view.php?id=' . (int) $order['id'])) ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Order pages">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === 1 || $i === $pages || abs($i - $pageNo) <= 2): ?>
          <?php if ($i === $pageNo): ?><span class="is-current"><?= $i ?></span><?php else: ?><a href="<?= e($filterUrl(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
        <?php elseif (abs($i - $pageNo) === 3): ?>
          <span>…</span>
        <?php endif; ?>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
