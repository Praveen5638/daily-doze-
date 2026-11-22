<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin('coupons.manage');

$errors = [];
$editId = max(0, (int) ($_GET['edit'] ?? 0));
$editCoupon = $editId > 0 ? Database::one('SELECT * FROM coupons WHERE id = ?', [$editId]) : null;
$showForm = $editCoupon !== null || isset($_GET['new']);

if (is_post()) {
    verify_csrf();
    $action = post_str('action');

    if ($action === 'toggle') {
        $targetId = post_int('id');
        $coupon = Database::one('SELECT id, code, status FROM coupons WHERE id = ?', [$targetId]);
        if ($coupon !== null) {
            $next = $coupon['status'] === 'active' ? 'inactive' : 'active';
            Database::update('coupons', ['status' => $next], 'id = ?', [$targetId]);
            Notifications::audit('coupon.status_toggled', 'coupon', (string) $targetId, ['status' => $next]);
            flash('success', 'Coupon ' . $coupon['code'] . ' is now ' . $next . '.');
        }
        redirect('admin/coupons.php');
    }

    if ($action === 'save') {
        $targetId = post_int('id');
        $existing = $targetId > 0 ? Database::one('SELECT * FROM coupons WHERE id = ?', [$targetId]) : null;
        $code = strtoupper(trim(post_str('code')));
        $description = trim(post_str('description'));
        $type = post_str('type');
        $value = trim(post_str('value'));
        $minOrder = trim(post_str('min_order_amount'));
        $maxDiscount = trim(post_str('max_discount'));
        $usageLimit = trim(post_str('usage_limit'));
        $perCustomer = trim(post_str('per_customer_limit'));
        $appliesTo = post_str('applies_to');
        $categoryId = post_int('category_id');
        $productId = post_int('product_id');
        $startsAt = trim(post_str('starts_at'));
        $expiresAt = trim(post_str('expires_at'));
        $status = post_str('status');

        if (!preg_match('/^[A-Z0-9_-]{3,40}$/', $code)) {
            $errors[] = 'Coupon code must be 3–40 characters of letters, numbers, dashes or underscores.';
        }
        if (Database::value('SELECT COUNT(*) FROM coupons WHERE code = ? AND id <> ?', [$code, $targetId]) > 0) {
            $errors[] = 'That coupon code already exists.';
        }
        if (mb_strlen($description) > 255) {
            $errors[] = 'The description must be 255 characters or fewer.';
        }
        if (!in_array($type, ['fixed', 'percent'], true)) {
            $errors[] = 'Choose a discount type.';
        }
        $valueFloat = is_numeric($value) ? (float) $value : -1;
        if ($valueFloat <= 0) {
            $errors[] = 'Discount value must be greater than zero.';
        } elseif ($type === 'percent' && $valueFloat > 100) {
            $errors[] = 'A percentage discount cannot exceed 100%.';
        }
        $minOrderFloat = $minOrder === '' ? 0.0 : (is_numeric($minOrder) ? (float) $minOrder : -1);
        if ($minOrderFloat < 0) {
            $errors[] = 'Minimum order amount must be zero or more.';
        }
        $maxDiscountFloat = $maxDiscount === '' ? null : (is_numeric($maxDiscount) ? (float) $maxDiscount : -1);
        if ($maxDiscountFloat !== null && $maxDiscountFloat <= 0) {
            $errors[] = 'Maximum discount must be greater than zero, or left empty.';
        }
        $usageLimitInt = $usageLimit === '' ? null : filter_var($usageLimit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
        if ($usageLimit !== '' && $usageLimitInt === false) {
            $errors[] = 'Total usage limit must be a whole number of 1 or more.';
        }
        $perCustomerInt = filter_var($perCustomer === '' ? 1 : $perCustomer, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($perCustomerInt === false) {
            $errors[] = 'Per-customer limit must be a whole number between 1 and 1000.';
        }
        if (!in_array($appliesTo, ['all', 'category', 'product'], true)) {
            $errors[] = 'Choose what the coupon applies to.';
        }
        if ($appliesTo === 'category' && ($categoryId <= 0 || Database::value('SELECT COUNT(*) FROM categories WHERE id = ?', [$categoryId]) === 0)) {
            $errors[] = 'Choose a valid category for this coupon.';
        }
        if ($appliesTo === 'product' && ($productId <= 0 || Database::value('SELECT COUNT(*) FROM products WHERE id = ?', [$productId]) === 0)) {
            $errors[] = 'Choose a valid product for this coupon.';
        }
        $startDate = null;
        if ($startsAt !== '') {
            $timestamp = strtotime($startsAt);
            if ($timestamp === false) {
                $errors[] = 'The start date is not valid.';
            } else {
                $startDate = date('Y-m-d H:i:s', $timestamp);
            }
        }
        $expiryDate = null;
        if ($expiresAt !== '') {
            $timestamp = strtotime($expiresAt);
            if ($timestamp === false) {
                $errors[] = 'The expiry date is not valid.';
            } else {
                $expiryDate = date('Y-m-d H:i:s', $timestamp);
            }
        }
        if ($startDate !== null && $expiryDate !== null && $expiryDate <= $startDate) {
            $errors[] = 'The expiry must be after the start date.';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'inactive';
        }

        if ($errors === []) {
            $data = [
                'code' => $code,
                'description' => $description !== '' ? $description : null,
                'type' => $type,
                'value' => $valueFloat,
                'min_order_amount' => $minOrderFloat,
                'max_discount' => $type === 'percent' ? $maxDiscountFloat : null,
                'usage_limit' => $usageLimitInt === false ? null : $usageLimitInt,
                'per_customer_limit' => (int) $perCustomerInt,
                'applies_to' => $appliesTo,
                'category_id' => $appliesTo === 'category' ? $categoryId : null,
                'product_id' => $appliesTo === 'product' ? $productId : null,
                'starts_at' => $startDate,
                'expires_at' => $expiryDate,
                'status' => $status,
            ];
            if ($existing === null) {
                $targetId = Database::insert('coupons', $data);
                Notifications::audit('coupon.created', 'coupon', (string) $targetId, ['code' => $code, 'type' => $type, 'value' => $valueFloat]);
                flash('success', 'Coupon created.');
            } else {
                Database::update('coupons', $data, 'id = ?', [$targetId]);
                Notifications::audit('coupon.updated', 'coupon', (string) $targetId, ['code' => $code, 'type' => $type, 'value' => $valueFloat]);
                flash('success', 'Coupon saved.');
            }
            redirect('admin/coupons.php');
        }
        $showForm = true;
    }
}

$coupons = Database::all(
    'SELECT c.*,
        (SELECT COUNT(*) FROM coupon_usage cu WHERE cu.coupon_id = c.id) AS used_count,
        cat.name AS category_name, p.name AS product_name
     FROM coupons c
     LEFT JOIN categories cat ON cat.id = c.category_id
     LEFT JOIN products p ON p.id = c.product_id
     ORDER BY c.id DESC'
);
$categories = Database::all('SELECT id, name FROM categories ORDER BY name ASC');
$products = Database::all('SELECT id, name FROM products ORDER BY name ASC');

$formValues = $editCoupon ?? [
    'id' => 0, 'code' => '', 'description' => '', 'type' => 'percent', 'value' => '',
    'min_order_amount' => '0', 'max_discount' => '', 'usage_limit' => '', 'per_customer_limit' => '1',
    'applies_to' => 'all', 'category_id' => null, 'product_id' => null, 'starts_at' => null,
    'expires_at' => null, 'status' => 'inactive',
];
if (is_post() && post_str('action') === 'save' && $errors !== []) {
    $formValues = array_merge($formValues, [
        'id' => post_int('id'), 'code' => post_str('code'), 'description' => post_str('description'),
        'type' => post_str('type'), 'value' => post_str('value'), 'min_order_amount' => post_str('min_order_amount'),
        'max_discount' => post_str('max_discount'), 'usage_limit' => post_str('usage_limit'),
        'per_customer_limit' => post_str('per_customer_limit'), 'applies_to' => post_str('applies_to'),
        'category_id' => post_int('category_id') ?: null, 'product_id' => post_int('product_id') ?: null,
        'starts_at' => post_str('starts_at') ?: null, 'expires_at' => post_str('expires_at') ?: null,
        'status' => post_str('status'),
    ]);
}
$dateInput = static function (?string $value): string {
    return $value !== null && $value !== '' ? str_replace(' ', 'T', substr($value, 0, 16)) : '';
};

$adminTitle = 'Coupons';
$adminPage = 'coupons';
require __DIR__ . '/inc/header.php';
?>

<div class="admin-page-head">
  <div>
    <h1>Coupons</h1>
    <p>Discount codes are validated on the server at checkout. Create codes only when you are ready to honour them.</p>
  </div>
  <div class="admin-page-actions">
    <a class="btn btn-primary btn-sm" href="<?= e(admin_url('coupons.php?new=1')) ?>"><?= icon('plus') ?> New coupon</a>
  </div>
</div>

<?php if ($showForm): ?>
  <section class="admin-panel">
    <div class="admin-panel-head">
      <h2><?= (int) $formValues['id'] > 0 ? 'Edit coupon' : 'New coupon' ?></h2>
      <a class="small" href="<?= e(admin_url('coupons.php')) ?>">Close</a>
    </div>
    <?php if ($errors !== []): ?>
      <div class="admin-panel-body"><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div></div>
    <?php endif; ?>
    <form class="admin-form" method="post" action="<?= e(admin_url('coupons.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int) $formValues['id'] ?>">
      <div class="admin-panel-body">
        <div class="field-row">
          <div class="field">
            <label for="code">Coupon code <span class="req">*</span></label>
            <input id="code" type="text" name="code" value="<?= e((string) $formValues['code']) ?>" maxlength="40" placeholder="e.g. WELCOME10" style="text-transform:uppercase" required>
          </div>
          <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
              <option value="inactive" <?= $formValues['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
              <option value="active" <?= $formValues['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            </select>
          </div>
        </div>
        <div class="field">
          <label for="description">Description (internal)</label>
          <input id="description" type="text" name="description" value="<?= e((string) $formValues['description']) ?>" maxlength="255">
        </div>
        <div class="field-row">
          <div class="field">
            <label for="type">Discount type <span class="req">*</span></label>
            <select id="type" name="type">
              <option value="percent" <?= $formValues['type'] === 'percent' ? 'selected' : '' ?>>Percentage</option>
              <option value="fixed" <?= $formValues['type'] === 'fixed' ? 'selected' : '' ?>>Fixed amount (₹)</option>
            </select>
          </div>
          <div class="field">
            <label for="value">Discount value <span class="req">*</span></label>
            <input id="value" type="number" name="value" value="<?= e((string) $formValues['value']) ?>" min="0.01" step="0.01" required>
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="min_order_amount">Minimum order amount (₹)</label>
            <input id="min_order_amount" type="number" name="min_order_amount" value="<?= e((string) $formValues['min_order_amount']) ?>" min="0" step="0.01">
          </div>
          <div class="field">
            <label for="max_discount">Maximum discount (₹, percentage only)</label>
            <input id="max_discount" type="number" name="max_discount" value="<?= e((string) $formValues['max_discount']) ?>" min="0" step="0.01">
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="usage_limit">Total usage limit</label>
            <input id="usage_limit" type="number" name="usage_limit" value="<?= e((string) $formValues['usage_limit']) ?>" min="1" step="1" placeholder="Unlimited">
          </div>
          <div class="field">
            <label for="per_customer_limit">Per-customer limit</label>
            <input id="per_customer_limit" type="number" name="per_customer_limit" value="<?= e((string) $formValues['per_customer_limit']) ?>" min="1" step="1">
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="applies_to">Applies to</label>
            <select id="applies_to" name="applies_to">
              <option value="all" <?= $formValues['applies_to'] === 'all' ? 'selected' : '' ?>>All products</option>
              <option value="category" <?= $formValues['applies_to'] === 'category' ? 'selected' : '' ?>>A category</option>
              <option value="product" <?= $formValues['applies_to'] === 'product' ? 'selected' : '' ?>>A product</option>
            </select>
          </div>
          <div class="field">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id">
              <option value="0">—</option>
              <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['id'] ?>" <?= (int) $formValues['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field">
          <label for="product_id">Product</label>
          <select id="product_id" name="product_id">
            <option value="0">—</option>
            <?php foreach ($products as $product): ?>
              <option value="<?= (int) $product['id'] ?>" <?= (int) $formValues['product_id'] === (int) $product['id'] ? 'selected' : '' ?>><?= e($product['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="starts_at">Starts at</label>
            <input id="starts_at" type="datetime-local" name="starts_at" value="<?= e($dateInput($formValues['starts_at'] !== null ? (string) $formValues['starts_at'] : null)) ?>">
          </div>
          <div class="field">
            <label for="expires_at">Expires at</label>
            <input id="expires_at" type="datetime-local" name="expires_at" value="<?= e($dateInput($formValues['expires_at'] !== null ? (string) $formValues['expires_at'] : null)) ?>">
          </div>
        </div>
      </div>
      <div class="admin-form-actions">
        <button class="btn btn-primary" type="submit"><?= (int) $formValues['id'] > 0 ? 'Save coupon' : 'Create coupon' ?></button>
        <a class="btn btn-ghost" href="<?= e(admin_url('coupons.php')) ?>">Cancel</a>
      </div>
    </form>
  </section>
<?php endif; ?>

<div class="admin-panel">
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>Code</th><th>Discount</th><th>Conditions</th><th class="num">Used</th><th>Window</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if ($coupons === []): ?>
          <tr><td colspan="7" class="muted">No coupons yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($coupons as $coupon): ?>
          <tr>
            <td><strong class="cell-title"><code><?= e($coupon['code']) ?></code></strong><span class="cell-sub"><?= e($coupon['description'] ?? '') ?></span></td>
            <td><?= $coupon['type'] === 'percent' ? (float) $coupon['value'] . '%' : e(money($coupon['value'])) ?><?= $coupon['max_discount'] !== null ? '<span class="cell-sub"> up to ' . e(money($coupon['max_discount'])) . '</span>' : '' ?></td>
            <td class="cell-sub">
              Min <?= e(money($coupon['min_order_amount'])) ?><br>
              <?php if ($coupon['applies_to'] === 'category'): ?>Category: <?= e($coupon['category_name'] ?? '—') ?>
              <?php elseif ($coupon['applies_to'] === 'product'): ?>Product: <?= e($coupon['product_name'] ?? '—') ?>
              <?php else: ?>All products<?php endif; ?>
            </td>
            <td class="num"><?= (int) $coupon['used_count'] ?><?= $coupon['usage_limit'] !== null ? ' / ' . (int) $coupon['usage_limit'] : '' ?></td>
            <td class="cell-sub">
              <?= $coupon['starts_at'] !== null ? 'From ' . e(date_display($coupon['starts_at'])) : 'No start' ?><br>
              <?= $coupon['expires_at'] !== null ? 'Until ' . e(date_display($coupon['expires_at'])) : 'No expiry' ?>
            </td>
            <td><span class="<?= e(status_class((string) $coupon['status'])) ?>"><?= e(ucfirst((string) $coupon['status'])) ?></span></td>
            <td>
              <div class="row-actions">
                <a href="<?= e(admin_url('coupons.php?edit=' . (int) $coupon['id'])) ?>">Edit</a>
                <form method="post" action="<?= e(admin_url('coupons.php')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $coupon['id'] ?>">
                  <button class="link-quiet" type="submit"><?= $coupon['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
