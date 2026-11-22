<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin('inventory.manage');

$adminId = (int) (Auth::admin()['id'] ?? 0);

if (is_post()) {
    verify_csrf();
    $action = post_str('action');
    $variantId = post_int('variant_id');
    $variant = Database::one(
        'SELECT v.id, v.sku, v.pack_size, p.name FROM product_variants v JOIN products p ON p.id = v.product_id WHERE v.id = ?',
        [$variantId]
    );
    if ($variant === null) {
        flash('error', 'That variant could not be found.');
        redirect('admin/inventory.php');
    }

    if ($action === 'adjust') {
        $mode = post_str('mode');
        $quantity = post_int('quantity');
        $note = trim(post_str('note'));
        $errors = [];
        if (!in_array($mode, ['receive', 'remove', 'set'], true)) {
            $errors[] = 'Please choose an adjustment type.';
        }
        if ($mode !== 'set' && ($quantity <= 0 || $quantity > 1000000)) {
            $errors[] = 'Quantity must be between 1 and 1,000,000.';
        }
        if ($mode === 'set' && ($quantity < 0 || $quantity > 1000000)) {
            $errors[] = 'Stock level must be between 0 and 1,000,000.';
        }
        if (mb_strlen($note) > 255) {
            $errors[] = 'The note must be 255 characters or fewer.';
        }
        if ($errors === []) {
            try {
                $pdo = Database::pdo();
                $pdo->beginTransaction();
                $inventory = Database::one('SELECT * FROM product_inventory WHERE variant_id = ? FOR UPDATE', [$variantId]);
                if ($inventory === null) {
                    Database::insert('product_inventory', ['variant_id' => $variantId, 'stock_qty' => 0, 'low_stock_threshold' => 10]);
                    $inventory = Database::one('SELECT * FROM product_inventory WHERE variant_id = ? FOR UPDATE', [$variantId]);
                }
                $current = (int) $inventory['stock_qty'];
                $reserved = (int) $inventory['reserved_qty'];
                if ($mode === 'receive') {
                    $delta = $quantity;
                    $reason = 'stock_received';
                } elseif ($mode === 'remove') {
                    $delta = -$quantity;
                    $reason = 'manual_adjustment';
                    if ($current - $quantity < $reserved) {
                        $errors[] = 'You cannot remove more stock than is available (' . max(0, $current - $reserved) . ' units).';
                    }
                } else {
                    $delta = $quantity - $current;
                    $reason = 'manual_adjustment';
                    if ($quantity < $reserved) {
                        $errors[] = 'Stock cannot be set below the reserved quantity (' . $reserved . ').';
                    }
                }
                if ($errors === [] && $delta === 0) {
                    $errors[] = 'The new stock level is unchanged.';
                }
                if ($errors !== []) {
                    $pdo->rollBack();
                } else {
                    Database::update('product_inventory', ['stock_qty' => $current + $delta], 'variant_id = ?', [$variantId]);
                    Database::insert('inventory_transactions', [
                        'variant_id' => $variantId,
                        'change_qty' => $delta,
                        'reason' => $reason,
                        'reference_type' => 'admin_adjustment',
                        'reference_id' => (string) $variantId,
                        'admin_id' => $adminId > 0 ? $adminId : null,
                        'note' => $note !== '' ? $note : null,
                    ]);
                    $pdo->commit();
                    Notifications::audit('inventory.adjusted', 'variant', (string) $variantId, [
                        'sku' => $variant['sku'], 'change' => $delta, 'mode' => $mode, 'note' => $note,
                    ]);
                    flash('success', 'Stock for ' . $variant['sku'] . ' updated to ' . ($current + $delta) . '.');
                    $errors = [];
                }
            } catch (Throwable $e) {
                if (Database::pdo()->inTransaction()) {
                    Database::pdo()->rollBack();
                }
                log_message('error', 'Inventory adjustment failed', ['error' => $e->getMessage(), 'variant_id' => $variantId]);
                $errors[] = 'The stock adjustment could not be saved. Please try again.';
            }
        }
        if ($errors !== []) {
            foreach ($errors as $error) {
                flash('error', $error);
            }
        }
        redirect('admin/inventory.php');
    }

    if ($action === 'threshold') {
        $threshold = post_int('low_stock_threshold');
        if ($threshold < 0 || $threshold > 100000) {
            flash('error', 'The low-stock threshold must be between 0 and 100,000.');
        } else {
            $inventory = Database::one('SELECT id FROM product_inventory WHERE variant_id = ?', [$variantId]);
            if ($inventory === null) {
                Database::insert('product_inventory', ['variant_id' => $variantId, 'stock_qty' => 0, 'low_stock_threshold' => $threshold]);
            } else {
                Database::update('product_inventory', ['low_stock_threshold' => $threshold], 'variant_id = ?', [$variantId]);
            }
            Notifications::audit('inventory.threshold_updated', 'variant', (string) $variantId, ['threshold' => $threshold]);
            flash('success', 'Low-stock threshold updated for ' . $variant['sku'] . '.');
        }
        redirect('admin/inventory.php');
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$lowOnly = isset($_GET['low']) && $_GET['low'] === '1';
$categoryId = max(0, (int) ($_GET['category'] ?? 0));
$pageNo = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

$where = ['p.status <> "archived"'];
$params = [];
if ($q !== '') {
    $where[] = '(v.sku LIKE ? OR p.name LIKE ? OR v.pack_size LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($lowOnly) {
    $where[] = 'COALESCE(i.stock_qty, 0) <= COALESCE(i.low_stock_threshold, 10)';
}
if ($categoryId > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
}
$whereSql = ' WHERE ' . implode(' AND ', $where);
$baseFrom = ' FROM product_variants v JOIN products p ON p.id = v.product_id LEFT JOIN product_inventory i ON i.variant_id = v.id';
$total = (int) Database::value('SELECT COUNT(*)' . $baseFrom . $whereSql, $params);
$pages = max(1, (int) ceil($total / $perPage));
$pageNo = min($pageNo, $pages);
$offset = ($pageNo - 1) * $perPage;

$rows = Database::all(
    'SELECT v.id, v.sku, v.pack_size, v.status AS variant_status,
        p.name AS product_name, p.slug, c.name AS category_name,
        COALESCE(i.stock_qty, 0) AS stock_qty,
        COALESCE(i.reserved_qty, 0) AS reserved_qty,
        COALESCE(i.low_stock_threshold, 10) AS low_stock_threshold
     FROM product_variants v
     JOIN products p ON p.id = v.product_id
     JOIN categories c ON c.id = p.category_id
     LEFT JOIN product_inventory i ON i.variant_id = v.id' . $whereSql . '
     ORDER BY (COALESCE(i.stock_qty, 0) <= COALESCE(i.low_stock_threshold, 10)) DESC, p.name ASC, v.pack_size ASC
     LIMIT ' . $perPage . ' OFFSET ' . $offset,
    $params
);

$summary = Database::one(
    'SELECT
        COALESCE(SUM(i.stock_qty), 0) AS units,
        COUNT(CASE WHEN COALESCE(i.stock_qty, 0) = 0 THEN 1 END) AS out_of_stock,
        COUNT(CASE WHEN COALESCE(i.stock_qty, 0) > 0 AND COALESCE(i.stock_qty, 0) <= COALESCE(i.low_stock_threshold, 10) THEN 1 END) AS low_stock
     FROM product_variants v JOIN products p ON p.id = v.product_id LEFT JOIN product_inventory i ON i.variant_id = v.id
     WHERE p.status <> "archived"'
) ?? ['units' => 0, 'out_of_stock' => 0, 'low_stock' => 0];

$transactions = Database::all(
    'SELECT t.*, v.sku, v.pack_size, p.name AS product_name, a.name AS admin_name
     FROM inventory_transactions t
     JOIN product_variants v ON v.id = t.variant_id
     JOIN products p ON p.id = v.product_id
     LEFT JOIN admins a ON a.id = t.admin_id
     ORDER BY t.id DESC LIMIT 20'
);
$categories = Database::all('SELECT id, name FROM categories ORDER BY name ASC');
$filterUrl = static function (array $overrides = []) use ($q, $lowOnly, $categoryId): string {
    $query = array_filter(array_merge([
        'q' => $q,
        'low' => $lowOnly ? '1' : '',
        'category' => $categoryId > 0 ? $categoryId : '',
    ], $overrides), static fn ($value): bool => $value !== '' && $value !== null);
    return admin_url('inventory.php' . ($query !== [] ? '?' . http_build_query($query) : ''));
};

$adminTitle = 'Inventory';
$adminPage = 'inventory';
require __DIR__ . '/inc/header.php';
?>

<div class="admin-page-head">
  <div>
    <h1>Inventory</h1>
    <p>Stock is tracked per variant. Every adjustment is recorded with a reason and your admin account.</p>
  </div>
</div>

<div class="stat-grid">
  <div class="admin-panel stat-card"><span class="stat-label">Units on hand</span><strong class="stat-value"><?= (int) $summary['units'] ?></strong><span class="stat-meta">across active products</span></div>
  <div class="admin-panel stat-card"><span class="stat-label">Low stock</span><strong class="stat-value"><?= (int) $summary['low_stock'] ?></strong><span class="stat-meta">at or below threshold</span></div>
  <div class="admin-panel stat-card"><span class="stat-label">Out of stock</span><strong class="stat-value"><?= (int) $summary['out_of_stock'] ?></strong><span class="stat-meta">variants unavailable</span></div>
</div>

<div class="admin-panel">
  <div class="admin-toolbar">
    <form method="get" action="<?= e(admin_url('inventory.php')) ?>">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="SKU, product or pack" aria-label="Search inventory">
      <select name="category" aria-label="Filter by category">
        <option value="">All categories</option>
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="check"><input type="checkbox" name="low" value="1" <?= $lowOnly ? 'checked' : '' ?>> Low stock only</label>
      <button class="btn btn-primary btn-sm" type="submit">Filter</button>
      <?php if ($q !== '' || $lowOnly || $categoryId > 0): ?><a class="small" href="<?= e(admin_url('inventory.php')) ?>">Clear</a><?php endif; ?>
    </form>
    <span class="spacer"></span>
    <span class="inline-note"><?= $total ?> variant<?= $total === 1 ? '' : 's' ?></span>
  </div>
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>Product</th><th>SKU</th><th>Pack</th><th class="num">On hand</th><th class="num">Reserved</th><th class="num">Available</th><th class="num">Low at</th><th>Status</th><th>Adjust</th></tr></thead>
      <tbody>
        <?php if ($rows === []): ?>
          <tr><td colspan="9" class="muted">No variants match these filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
          <?php
          $stock = (int) $row['stock_qty'];
          $reserved = (int) $row['reserved_qty'];
          $threshold = (int) $row['low_stock_threshold'];
          $available = max(0, $stock - $reserved);
          $stockClass = $stock === 0 ? 'pill-red' : ($stock <= $threshold ? 'pill-gold' : 'pill-green');
          $stockLabel = $stock === 0 ? 'Out of stock' : ($stock <= $threshold ? 'Low stock' : 'In stock');
          ?>
          <tr>
            <td><strong class="cell-title"><?= e($row['product_name']) ?></strong><span class="cell-sub"><?= e($row['category_name']) ?><?= $row['variant_status'] === 'inactive' ? ' · variant inactive' : '' ?></span></td>
            <td><code><?= e($row['sku']) ?></code></td>
            <td><?= e($row['pack_size']) ?></td>
            <td class="num"><?= $stock ?></td>
            <td class="num"><?= $reserved ?></td>
            <td class="num"><?= $available ?></td>
            <td class="num">
              <form class="inline-form" method="post" action="<?= e(admin_url('inventory.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="threshold">
                <input type="hidden" name="variant_id" value="<?= (int) $row['id'] ?>">
                <input type="number" name="low_stock_threshold" value="<?= $threshold ?>" min="0" max="100000" step="1" aria-label="Low stock threshold for <?= e($row['sku']) ?>" style="width:72px">
                <button class="link-quiet" type="submit">Save</button>
              </form>
            </td>
            <td><span class="<?= $stockClass ?>"><?= $stockLabel ?></span></td>
            <td>
              <details>
                <summary class="small" style="cursor:pointer">Update stock</summary>
                <form method="post" action="<?= e(admin_url('inventory.php')) ?>" class="admin-form" style="margin-top:10px">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="adjust">
                  <input type="hidden" name="variant_id" value="<?= (int) $row['id'] ?>">
                  <select name="mode" aria-label="Adjustment type">
                    <option value="receive">Receive stock (+)</option>
                    <option value="remove">Remove stock (−)</option>
                    <option value="set">Set exact level</option>
                  </select>
                  <input type="number" name="quantity" min="0" max="1000000" step="1" placeholder="Quantity" required aria-label="Quantity">
                  <input type="text" name="note" maxlength="255" placeholder="Reason or reference (optional)" aria-label="Note">
                  <button class="btn btn-primary btn-sm" type="submit">Save adjustment</button>
                </form>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Inventory pages">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === 1 || $i === $pages || abs($i - $pageNo) <= 2): ?>
          <?php if ($i === $pageNo): ?><span class="is-current"><?= $i ?></span><?php else: ?><a href="<?= e($filterUrl(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
        <?php endif; ?>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>

<div class="admin-panel">
  <div class="admin-panel-head"><h2>Recent stock movements</h2></div>
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>When</th><th>Variant</th><th class="num">Change</th><th>Reason</th><th>By</th><th>Note</th></tr></thead>
      <tbody>
        <?php if ($transactions === []): ?>
          <tr><td colspan="6" class="muted">No stock movements recorded yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($transactions as $entry): ?>
          <tr>
            <td class="cell-sub"><?= e(date_display($entry['created_at'])) ?></td>
            <td><strong class="cell-title"><?= e($entry['product_name']) ?></strong><span class="cell-sub"><?= e($entry['sku']) ?> · <?= e($entry['pack_size']) ?></span></td>
            <td class="num"><?= (int) $entry['change_qty'] > 0 ? '+' : '' ?><?= (int) $entry['change_qty'] ?></td>
            <td><?= e(ucwords(str_replace('_', ' ', (string) $entry['reason']))) ?></td>
            <td><?= e($entry['admin_name'] ?? 'System') ?></td>
            <td class="cell-sub"><?= e($entry['note'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
