<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin('products.manage');

if (is_post()) {
    verify_csrf();
    if (post_str('action') === 'toggle_featured') {
        $id = post_int('id');
        $product = Database::one('SELECT id, name, is_featured FROM products WHERE id = ?', [$id]);
        if ($product !== null) {
            $newValue = (int) $product['is_featured'] === 1 ? 0 : 1;
            Database::update('products', ['is_featured' => $newValue], 'id = ?', [$id]);
            Notifications::audit('product.featured_toggled', 'product', (string) $id, ['is_featured' => $newValue]);
            flash('success', $product['name'] . ' is now ' . ($newValue === 1 ? 'featured' : 'not featured') . '.');
        }
    }
    redirect('admin/products.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$statusOptions = ['published', 'draft', 'archived'];
$status = (string) ($_GET['status'] ?? '');
if (!in_array($status, $statusOptions, true)) {
    $status = '';
}
$categoryId = max(0, (int) ($_GET['category'] ?? 0));
$q = trim((string) ($_GET['q'] ?? ''));
$pageNo = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$where = [];
$params = [];
if ($status !== '') {
    $where[] = 'p.status = ?';
    $params[] = $status;
}
if ($categoryId > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
}
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.slug LIKE ? OR p.search_aliases LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
$total = (int) Database::value('SELECT COUNT(*) FROM products p' . $whereSql, $params);
$pages = max(1, (int) ceil($total / $perPage));
$pageNo = min($pageNo, $pages);
$offset = ($pageNo - 1) * $perPage;

$products = Database::all(
    'SELECT p.*, c.name AS category_name,
        (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id AND v.status = "active") AS variant_count,
        (SELECT MIN(v.price) FROM product_variants v WHERE v.product_id = p.id AND v.status = "active") AS min_price,
        (SELECT COALESCE(SUM(i.stock_qty), 0) FROM product_inventory i JOIN product_variants v ON v.id = i.variant_id WHERE v.product_id = p.id) AS stock_total,
        (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS primary_image
     FROM products p JOIN categories c ON c.id = p.category_id' . $whereSql . '
     ORDER BY p.updated_at DESC, p.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
    $params
);
$categories = Database::all('SELECT id, name FROM categories ORDER BY sort_order ASC, name ASC');
$filterUrl = static function (array $overrides = []) use ($status, $categoryId, $q): string {
    $query = array_filter(array_merge(['status' => $status, 'category' => $categoryId ?: '', 'q' => $q], $overrides), static fn ($value): bool => $value !== '' && $value !== null);
    return admin_url('products.php' . ($query !== [] ? '?' . http_build_query($query) : ''));
};

$adminTitle = 'Products';
$adminPage = 'products';
require __DIR__ . '/inc/header.php';
?>

<div class="admin-page-head">
  <div>
    <h1>Products</h1>
    <p>Manage catalogue content, variants, pricing and product images. Prices and stock are only what you enter — nothing is invented.</p>
  </div>
  <div class="admin-page-actions">
    <a class="btn btn-ghost btn-sm" href="<?= e(url('shop.php')) ?>" target="_blank" rel="noopener"><?= icon('external') ?> View shop</a>
    <a class="btn btn-primary btn-sm" href="<?= e(admin_url('product-edit.php')) ?>"><?= icon('plus') ?> Add product</a>
  </div>
</div>

<div class="admin-panel">
  <div class="admin-toolbar">
    <form method="get" action="<?= e(admin_url('products.php')) ?>">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Product name or alias" aria-label="Search products">
      <select name="category" aria-label="Filter by category">
        <option value="">All categories</option>
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" aria-label="Filter by status">
        <option value="">All statuses</option>
        <?php foreach ($statusOptions as $s): ?>
          <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary btn-sm" type="submit">Filter</button>
      <?php if ($status !== '' || $categoryId > 0 || $q !== ''): ?><a class="small" href="<?= e(admin_url('products.php')) ?>">Clear</a><?php endif; ?>
    </form>
    <span class="spacer"></span>
    <span class="inline-note"><?= $total ?> product<?= $total === 1 ? '' : 's' ?></span>
  </div>
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>Product</th><th>Category</th><th>Variants</th><th class="num">From</th><th class="num">Stock</th><th>Status</th><th>Featured</th><th></th></tr></thead>
      <tbody>
        <?php if ($products === []): ?>
          <tr><td colspan="8" class="muted">No products match these filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($products as $product): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <img class="thumb" src="<?= e(asset_url($product['primary_image'])) ?>" alt="" width="40" height="40">
                <span><strong class="cell-title"><?= e($product['name']) ?></strong><span class="cell-sub">/<?= e($product['slug']) ?></span></span>
              </div>
            </td>
            <td><?= e($product['category_name']) ?></td>
            <td><?= (int) $product['variant_count'] ?></td>
            <td class="num"><?= $product['min_price'] !== null ? e(money($product['min_price'])) : '<span class="pill pill-gold">On request</span>' ?></td>
            <td class="num"><?= (int) $product['stock_total'] ?></td>
            <td><span class="<?= e(status_class((string) $product['status'])) ?>"><?= e(ucfirst((string) $product['status'])) ?></span></td>
            <td>
              <form method="post" action="<?= e(admin_url('products.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''))) ?>" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_featured">
                <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                <button class="pill <?= (int) $product['is_featured'] === 1 ? 'pill-green' : 'pill-grey' ?>" type="submit" title="Toggle featured"><?= (int) $product['is_featured'] === 1 ? 'Featured' : 'No' ?></button>
              </form>
            </td>
            <td>
              <div class="row-actions">
                <a href="<?= e(url('products/' . $product['slug'])) ?>" target="_blank" rel="noopener">View</a>
                <a href="<?= e(admin_url('product-edit.php?id=' . (int) $product['id'])) ?>">Edit</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Product pages">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === 1 || $i === $pages || abs($i - $pageNo) <= 2): ?>
          <?php if ($i === $pageNo): ?><span class="is-current"><?= $i ?></span><?php else: ?><a href="<?= e($filterUrl(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
        <?php endif; ?>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
