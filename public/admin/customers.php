<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin('customers.manage');

if (is_post()) {
    verify_csrf();
    $action = post_str('action');
    $customerId = post_int('customer_id');
    $customer = Database::one('SELECT * FROM customers WHERE id = ?', [$customerId]);
    if ($customer === null) {
        flash('error', 'That customer could not be found.');
        redirect('admin/customers.php');
    }
    if ($action === 'toggle') {
        $newStatus = $customer['status'] === 'active' ? 'disabled' : 'active';
        Database::update('customers', ['status' => $newStatus], 'id = ?', [$customerId]);
        Notifications::audit('customer.status_updated', 'customer', (string) $customerId, ['status' => $newStatus]);
        flash('success', 'The customer account is now ' . $newStatus . '.');
    } elseif ($action === 'approve_deletion') {
        if ($customer['status'] !== 'pending_deletion') {
            flash('error', 'This account has not requested deletion.');
        } else {
            try {
                $pdo = Database::pdo();
                $pdo->beginTransaction();
                Database::update('customers', [
                    'first_name' => 'Deleted',
                    'last_name' => null,
                    'email' => 'deleted-' . $customerId . '-' . bin2hex(random_bytes(4)) . '@invalid.local',
                    'phone' => '',
                    'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
                    'status' => 'disabled',
                ], 'id = ?', [$customerId]);
                Database::q('DELETE FROM customer_addresses WHERE customer_id = ?', [$customerId]);
                Database::q('DELETE FROM wishlist_items WHERE wishlist_id IN (SELECT id FROM wishlists WHERE customer_id = ?)', [$customerId]);
                $pdo->commit();
                Notifications::audit('customer.deletion_processed', 'customer', (string) $customerId);
                flash('success', 'Account personal data was anonymised and the account disabled. Order records were retained for legal and accounting requirements.');
            } catch (Throwable $e) {
                if (Database::pdo()->inTransaction()) {
                    Database::pdo()->rollBack();
                }
                log_message('error', 'Customer deletion failed', ['error' => $e->getMessage(), 'customer_id' => $customerId]);
                flash('error', 'The deletion request could not be processed. Please try again.');
            }
        }
    } elseif ($action === 'reject_deletion') {
        Database::update('customers', ['status' => 'active'], 'id = ?', [$customerId]);
        Notifications::audit('customer.deletion_rejected', 'customer', (string) $customerId);
        flash('success', 'The account was restored to active.');
    }
    redirect('admin/customers.php' . (post_str('return') !== '' ? '?view=' . $customerId : ''));
}

$q = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? '');
if (!in_array($statusFilter, ['active', 'disabled', 'pending_deletion'], true)) {
    $statusFilter = '';
}
$pageNo = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$viewId = max(0, (int) ($_GET['view'] ?? 0));

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(c.email LIKE ? OR c.phone LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($statusFilter !== '') {
    $where[] = 'c.status = ?';
    $params[] = $statusFilter;
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
$total = (int) Database::value('SELECT COUNT(*) FROM customers c' . $whereSql, $params);
$pages = max(1, (int) ceil($total / $perPage));
$pageNo = min($pageNo, $pages);
$offset = ($pageNo - 1) * $perPage;
$customers = Database::all(
    'SELECT c.*,
        (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS order_count,
        (SELECT COALESCE(SUM(o.total), 0) FROM orders o WHERE o.customer_id = c.id AND o.payment_status = "paid") AS paid_total
     FROM customers c' . $whereSql . '
     ORDER BY c.created_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
    $params
);
$pendingDeletion = (int) Database::value('SELECT COUNT(*) FROM customers WHERE status = "pending_deletion"');
$filterUrl = static function (array $overrides = []) use ($q, $statusFilter): string {
    $query = array_filter(array_merge(['q' => $q, 'status' => $statusFilter], $overrides), static fn ($value): bool => $value !== '' && $value !== null);
    return admin_url('customers.php' . ($query !== [] ? '?' . http_build_query($query) : ''));
};

$viewCustomer = null;
$viewOrders = [];
$viewAddresses = [];
$viewCounts = [];
if ($viewId > 0) {
    $viewCustomer = Database::one('SELECT * FROM customers WHERE id = ?', [$viewId]);
    if ($viewCustomer !== null) {
        $viewOrders = Database::all('SELECT id, order_number, status, payment_status, total, created_at FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 15', [$viewId]);
        $viewAddresses = Database::all('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC', [$viewId]);
        $viewCounts = [
            'orders' => (int) Database::value('SELECT COUNT(*) FROM orders WHERE customer_id = ?', [$viewId]),
            'paid' => (float) Database::value('SELECT COALESCE(SUM(total), 0) FROM orders WHERE customer_id = ? AND payment_status = "paid"', [$viewId]),
            'wishlist' => (int) Database::value('SELECT COUNT(*) FROM wishlist_items wi JOIN wishlists w ON w.id = wi.wishlist_id WHERE w.customer_id = ?', [$viewId]),
            'reviews' => (int) Database::value('SELECT COUNT(*) FROM reviews WHERE customer_id = ?', [$viewId]),
        ];
    }
}

$adminTitle = 'Customers';
$adminPage = 'customers';
require __DIR__ . '/inc/header.php';
?>

<div class="admin-page-head">
  <div>
    <h1>Customers</h1>
    <p>Customer accounts, order history and deletion requests. Handle personal data only for legitimate business purposes.</p>
  </div>
  <?php if ($pendingDeletion > 0): ?>
    <div class="admin-page-actions"><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('customers.php?status=pending_deletion')) ?>"><?= icon('alert') ?> <?= $pendingDeletion ?> deletion request<?= $pendingDeletion === 1 ? '' : 's' ?></a></div>
  <?php endif; ?>
</div>

<?php if ($viewCustomer !== null): ?>
  <section class="admin-panel">
    <div class="admin-panel-head">
      <h2><?= e($viewCustomer['first_name'] . ' ' . ($viewCustomer['last_name'] ?? '')) ?></h2>
      <a class="small" href="<?= e(admin_url('customers.php')) ?>">Close</a>
    </div>
    <div class="admin-panel-body">
      <div class="admin-grid-2 is-even">
        <div>
          <p><span class="<?= e(status_class((string) $viewCustomer['status'])) ?>"><?= e(ucwords(str_replace('_', ' ', (string) $viewCustomer['status']))) ?></span></p>
          <p><strong>Email</strong><br><?= e($viewCustomer['email']) ?></p>
          <p><strong>Phone</strong><br><?= e($viewCustomer['phone'] !== '' ? $viewCustomer['phone'] : '—') ?></p>
          <p><strong>Joined</strong><br><?= e(date_display($viewCustomer['created_at'])) ?><br><span class="cell-sub">Last login: <?= e($viewCustomer['last_login_at'] !== null ? date_display($viewCustomer['last_login_at']) : 'Never') ?></span></p>
        </div>
        <div>
          <p><strong>Orders</strong><br><?= (int) $viewCounts['orders'] ?> · <?= e(money($viewCounts['paid'])) ?> paid</p>
          <p><strong>Wishlist</strong><br><?= (int) $viewCounts['wishlist'] ?> saved item<?= $viewCounts['wishlist'] === 1 ? '' : 's' ?></p>
          <p><strong>Reviews</strong><br><?= (int) $viewCounts['reviews'] ?> submitted</p>
          <div class="row-actions" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">
            <?php if ($viewCustomer['status'] === 'pending_deletion'): ?>
              <form method="post" action="<?= e(admin_url('customers.php')) ?>" data-confirm="Anonymise this customer's personal data and disable the account? Orders are retained.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="approve_deletion">
                <input type="hidden" name="customer_id" value="<?= (int) $viewCustomer['id'] ?>">
                <input type="hidden" name="return" value="1">
                <button class="btn btn-primary btn-sm" type="submit">Process deletion</button>
              </form>
              <form method="post" action="<?= e(admin_url('customers.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject_deletion">
                <input type="hidden" name="customer_id" value="<?= (int) $viewCustomer['id'] ?>">
                <input type="hidden" name="return" value="1">
                <button class="btn btn-ghost btn-sm" type="submit">Reject request</button>
              </form>
            <?php elseif ($viewCustomer['status'] !== 'disabled' || !str_starts_with((string) $viewCustomer['email'], 'deleted-')): ?>
              <form method="post" action="<?= e(admin_url('customers.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="customer_id" value="<?= (int) $viewCustomer['id'] ?>">
                <input type="hidden" name="return" value="1">
                <button class="btn btn-ghost btn-sm" type="submit"><?= $viewCustomer['status'] === 'active' ? 'Disable account' : 'Enable account' ?></button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <h3 style="margin-top:26px">Addresses</h3>
      <?php if ($viewAddresses === []): ?>
        <p class="muted">No saved addresses.</p>
      <?php else: ?>
        <div class="admin-grid-2">
          <?php foreach ($viewAddresses as $address): ?>
            <div class="inline-note">
              <strong><?= e($address['label'] !== null && $address['label'] !== '' ? $address['label'] : ucfirst((string) $address['address_type'])) ?></strong><?= (int) $address['is_default'] === 1 ? ' · default' : '' ?><br>
              <?= e(full_address($address)) ?><br>
              <?= e($address['phone']) ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <h3 style="margin-top:26px">Recent orders</h3>
      <div class="table-scroll">
        <table class="admin-table">
          <thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Payment</th><th class="num">Total</th><th></th></tr></thead>
          <tbody>
            <?php if ($viewOrders === []): ?><tr><td colspan="6" class="muted">No orders.</td></tr><?php endif; ?>
            <?php foreach ($viewOrders as $order): ?>
              <tr>
                <td><a href="<?= e(admin_url('order-view.php?id=' . (int) $order['id'])) ?>"><strong><?= e($order['order_number']) ?></strong></a></td>
                <td class="cell-sub"><?= e(date_display($order['created_at'])) ?></td>
                <td><span class="<?= e(status_class((string) $order['status'])) ?>"><?= e(order_status_label((string) $order['status'])) ?></span></td>
                <td><span class="<?= e(status_class((string) $order['payment_status'])) ?>"><?= e(payment_status_label((string) $order['payment_status'])) ?></span></td>
                <td class="num"><?= e(money($order['total'])) ?></td>
                <td><a href="<?= e(admin_url('order-view.php?id=' . (int) $order['id'])) ?>">Open</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
<?php endif; ?>

<div class="admin-panel">
  <div class="admin-toolbar">
    <form method="get" action="<?= e(admin_url('customers.php')) ?>">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Name, email or phone" aria-label="Search customers">
      <select name="status" aria-label="Filter by status">
        <option value="">All statuses</option>
        <?php foreach (['active' => 'Active', 'disabled' => 'Disabled', 'pending_deletion' => 'Pending deletion'] as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary btn-sm" type="submit">Filter</button>
      <?php if ($q !== '' || $statusFilter !== ''): ?><a class="small" href="<?= e(admin_url('customers.php')) ?>">Clear</a><?php endif; ?>
    </form>
    <span class="spacer"></span>
    <span class="inline-note"><?= $total ?> customer<?= $total === 1 ? '' : 's' ?></span>
  </div>
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>Customer</th><th>Contact</th><th>Joined</th><th class="num">Orders</th><th class="num">Paid</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if ($customers === []): ?><tr><td colspan="7" class="muted">No customers match these filters.</td></tr><?php endif; ?>
        <?php foreach ($customers as $customer): ?>
          <tr>
            <td><strong class="cell-title"><?= e($customer['first_name'] . ' ' . ($customer['last_name'] ?? '')) ?></strong><span class="cell-sub">#<?= (int) $customer['id'] ?></span></td>
            <td class="cell-sub"><?= e($customer['email']) ?><br><?= e($customer['phone'] !== '' ? $customer['phone'] : '—') ?></td>
            <td class="cell-sub"><?= e(date_display($customer['created_at'])) ?></td>
            <td class="num"><?= (int) $customer['order_count'] ?></td>
            <td class="num"><?= e(money($customer['paid_total'])) ?></td>
            <td><span class="<?= e(status_class((string) $customer['status'])) ?>"><?= e(ucwords(str_replace('_', ' ', (string) $customer['status']))) ?></span></td>
            <td><div class="row-actions"><a href="<?= e(admin_url('customers.php?view=' . (int) $customer['id'])) ?>">View</a></div></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Customer pages">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === 1 || $i === $pages || abs($i - $pageNo) <= 2): ?>
          <?php if ($i === $pageNo): ?><span class="is-current"><?= $i ?></span><?php else: ?><a href="<?= e($filterUrl(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
        <?php endif; ?>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
