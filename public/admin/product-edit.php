<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin('products.manage');

$id = max(0, (int) ($_GET['id'] ?? 0));
$product = $id > 0 ? Database::one('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id > 0 && $product === null) {
    http_response_code(404);
    flash('error', 'That product could not be found.');
    redirect('admin/products.php');
}
$isNew = $product === null;
$adminId = (int) (Auth::admin()['id'] ?? 0);
$statuses = ['published', 'draft', 'archived'];
$editUrl = static function (int $targetId): string {
    return admin_url('product-edit.php?id=' . $targetId);
};

/* ---------- Side actions on an existing product ---------- */
if (is_post() && !$isNew) {
    verify_csrf();
    $action = post_str('action');
    if ($action === 'image_primary' || $action === 'image_delete') {
        $imageId = post_int('image_id');
        $image = Database::one('SELECT * FROM product_images WHERE id = ? AND product_id = ?', [$imageId, $id]);
        if ($image !== null) {
            if ($action === 'image_primary') {
                Database::q('UPDATE product_images SET is_primary = 0 WHERE product_id = ?', [$id]);
                Database::update('product_images', ['is_primary' => 1], 'id = ?', [$imageId]);
                Notifications::audit('product.image_primary', 'product', (string) $id, ['image_id' => $imageId]);
                flash('success', 'Primary image updated.');
            } else {
                Uploader::remove((string) $image['image_path']);
                Database::q('DELETE FROM product_images WHERE id = ?', [$imageId]);
                if ((int) $image['is_primary'] === 1) {
                    $next = Database::one('SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1', [$id]);
                    if ($next !== null) {
                        Database::update('product_images', ['is_primary' => 1], 'id = ?', [(int) $next['id']]);
                    }
                }
                Notifications::audit('product.image_deleted', 'product', (string) $id, ['image_id' => $imageId]);
                flash('success', 'Image removed.');
            }
        }
        redirect($editUrl($id));
    }

    if ($action === 'image_alt') {
        $imageId = post_int('image_id');
        $image = Database::one('SELECT id FROM product_images WHERE id = ? AND product_id = ?', [$imageId, $id]);
        if ($image !== null) {
            Database::update('product_images', ['alt_text' => mb_substr(trim(post_str('alt_text')), 0, 190) ?: null], 'id = ?', [$imageId]);
            Notifications::audit('product.image_alt_updated', 'product', (string) $id, ['image_id' => $imageId]);
            flash('success', 'Image description saved.');
        }
        redirect($editUrl($id));
    }

    if ($action === 'image_move') {
        $imageId = post_int('image_id');
        $direction = post_str('direction');
        $images = Database::all('SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC', [$id]);
        $index = null;
        foreach ($images as $position => $image) {
            if ((int) $image['id'] === $imageId) {
                $index = $position;
                break;
            }
        }
        if ($index !== null && in_array($direction, ['up', 'down'], true)) {
            $swap = $direction === 'up' ? $index - 1 : $index + 1;
            if ($swap >= 0 && $swap < count($images)) {
                $order = array_column($images, 'id');
                [$order[$index], $order[$swap]] = [$order[$swap], $order[$index]];
                foreach ($order as $sortOrder => $imageIdx) {
                    Database::update('product_images', ['sort_order' => $sortOrder], 'id = ?', [(int) $imageIdx]);
                }
                Notifications::audit('product.images_reordered', 'product', (string) $id);
                flash('success', 'Image order updated.');
            }
        }
        redirect($editUrl($id));
    }

    if ($action === 'variant_delete') {
        $variantId = post_int('variant_id');
        $variant = Database::one('SELECT * FROM product_variants WHERE id = ? AND product_id = ?', [$variantId, $id]);
        if ($variant !== null) {
            $ordered = Database::value('SELECT COUNT(*) FROM order_items WHERE variant_id = ?', [$variantId]);
            if ((int) $ordered > 0) {
                flash('error', 'This variant appears in past orders, so it cannot be deleted. Set it to inactive instead.');
            } else {
                Database::q('DELETE FROM product_variants WHERE id = ?', [$variantId]);
                if ((int) $variant['is_default'] === 1) {
                    $next = Database::one('SELECT id FROM product_variants WHERE product_id = ? AND status = "active" ORDER BY id ASC LIMIT 1', [$id]);
                    if ($next !== null) {
                        Database::update('product_variants', ['is_default' => 1], 'id = ?', [(int) $next['id']]);
                    }
                }
                Notifications::audit('product.variant_deleted', 'product', (string) $id, ['sku' => $variant['sku'], 'pack_size' => $variant['pack_size']]);
                flash('success', 'Variant deleted.');
            }
        }
        redirect($editUrl($id));
    }
}

/* ---------- Main save ---------- */
$errors = [];
$form = $isNew ? [
    'name' => '', 'category_id' => '', 'product_type' => '', 'slug' => '', 'short_description' => '',
    'description' => '', 'ingredients' => '', 'packaging_info' => '', 'storage_instructions' => '',
    'origin' => '', 'manufacturer_info' => '', 'nutritional_info' => '', 'allergen_info' => '',
    'fssai_info' => '', 'net_quantity_info' => '', 'tax_rate' => '0', 'is_featured' => '0',
    'is_new' => '0', 'status' => 'draft', 'meta_title' => '', 'meta_description' => '',
    'keywords' => '', 'search_aliases' => '',
] : $product;
$variantRows = [];

if (is_post() && post_str('action') === 'save') {
    verify_csrf();
    foreach (array_keys($form) as $key) {
        if ($key !== 'id') {
            $form[$key] = trim((string) ($_POST[$key] ?? ''));
        }
    }
    $form['category_id'] = (string) post_int('category_id');
    $form['tax_rate'] = trim((string) ($_POST['tax_rate'] ?? '0'));
    $form['is_featured'] = isset($_POST['is_featured']) ? '1' : '0';
    $form['is_new'] = isset($_POST['is_new']) ? '1' : '0';
    $form['status'] = post_str('status');

    $name = (string) $form['name'];
    $categoryId = (int) $form['category_id'];
    $shortDescription = (string) $form['short_description'];
    $metaTitle = (string) $form['meta_title'];
    $metaDescription = (string) $form['meta_description'];
    $taxRate = is_numeric($form['tax_rate']) ? (float) $form['tax_rate'] : -1;

    if (mb_strlen($name) < 2 || mb_strlen($name) > 190) {
        $errors[] = 'Product name must be between 2 and 190 characters.';
    }
    if ($categoryId <= 0 || Database::value('SELECT COUNT(*) FROM categories WHERE id = ?', [$categoryId]) === 0) {
        $errors[] = 'Please choose a category.';
    }
    if (mb_strlen($shortDescription) > 500) {
        $errors[] = 'Short description must be 500 characters or fewer.';
    }
    if (mb_strlen($metaTitle) > 190) {
        $errors[] = 'SEO title must be 190 characters or fewer.';
    }
    if (mb_strlen($metaDescription) > 320) {
        $errors[] = 'SEO description must be 320 characters or fewer.';
    }
    if ($taxRate < 0 || $taxRate > 100) {
        $errors[] = 'Tax rate must be a number between 0 and 100.';
    }
    if (!in_array($form['status'], $statuses, true)) {
        $errors[] = 'Please choose a valid status.';
    }

    $slugBase = strtolower(trim((string) $form['slug']));
    if ($slugBase === '') {
        $slugBase = strtolower($name);
    }
    $slugBase = preg_replace('/[^a-z0-9]+/', '-', $slugBase) ?? '';
    $slugBase = trim($slugBase, '-');
    if ($slugBase === '' || strlen($slugBase) > 200) {
        $errors[] = 'The URL slug must use letters, numbers and hyphens only (maximum 200 characters).';
        $slugBase = $slugBase !== '' ? substr($slugBase, 0, 200) : 'product';
    }
    $slug = $slugBase;
    $suffix = 2;
    while (Database::value('SELECT COUNT(*) FROM products WHERE slug = ? AND id <> ?', [$slug, $id]) > 0) {
        $slug = $slugBase . '-' . $suffix;
        $suffix++;
        if ($suffix > 99) {
            $errors[] = 'This URL slug is already in use. Please choose another one.';
            break;
        }
    }
    $form['slug'] = $slug;

    $postedVariants = is_array($_POST['variants'] ?? null) ? $_POST['variants'] : [];
    $defaultKey = post_str('default_variant');
    $cleanVariants = [];
    $seenSkus = [];
    foreach ($postedVariants as $key => $row) {
        if (!is_array($row)) {
            continue;
        }
        $key = (string) $key;
        $existingId = ctype_digit($key) ? (int) $key : 0;
        $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
        $packSize = trim((string) ($row['pack_size'] ?? ''));
        $weight = trim((string) ($row['weight_grams'] ?? ''));
        $price = trim((string) ($row['price'] ?? ''));
        $mrp = trim((string) ($row['mrp'] ?? ''));
        $cost = trim((string) ($row['cost_price'] ?? ''));
        $variantStatus = (string) ($row['status'] ?? 'active');
        $stock = trim((string) ($row['stock_qty'] ?? '0'));
        $threshold = trim((string) ($row['low_stock_threshold'] ?? '10'));

        if ($sku === '' && $packSize === '' && $price === '' && $mrp === '') {
            continue;
        }
        if ($sku === '' || !preg_match('/^[A-Z0-9][A-Z0-9._-]{1,79}$/', $sku)) {
            $errors[] = 'Every variant needs a SKU using letters, numbers, dots, dashes or underscores (2–80 characters).';
            continue;
        }
        if (isset($seenSkus[$sku])) {
            $errors[] = 'The SKU ' . $sku . ' is used more than once.';
            continue;
        }
        $seenSkus[$sku] = true;
        if (Database::value('SELECT COUNT(*) FROM product_variants WHERE sku = ? AND product_id <> ?', [$sku, $id]) > 0) {
            $errors[] = 'The SKU ' . $sku . ' is already used by another product.';
            continue;
        }
        if ($packSize === '' || mb_strlen($packSize) > 60) {
            $errors[] = 'Every variant needs a pack size (maximum 60 characters).';
            continue;
        }
        $weightValue = $weight === '' ? null : filter_var($weight, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 10000000]]);
        if ($weight !== '' && $weightValue === false) {
            $errors[] = 'Weight for ' . $sku . ' must be a whole number of grams.';
            continue;
        }
        $priceValue = $price === '' ? null : filter_var($price, FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0, 'max_range' => 10000000]]);
        if ($price !== '' && $priceValue === false) {
            $errors[] = 'Price for ' . $sku . ' must be a number of 0 or more, or left empty for "price on request".';
            continue;
        }
        $mrpValue = $mrp === '' ? null : filter_var($mrp, FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0, 'max_range' => 10000000]]);
        if ($mrp !== '' && $mrpValue === false) {
            $errors[] = 'MRP for ' . $sku . ' must be a number of 0 or more.';
            continue;
        }
        if ($priceValue !== null && $mrpValue !== null && (float) $mrpValue < (float) $priceValue) {
            $errors[] = 'MRP for ' . $sku . ' cannot be less than the selling price.';
            continue;
        }
        $costValue = $cost === '' ? null : filter_var($cost, FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0, 'max_range' => 10000000]]);
        if ($cost !== '' && $costValue === false) {
            $errors[] = 'Cost price for ' . $sku . ' must be a number of 0 or more.';
            continue;
        }
        if (!in_array($variantStatus, ['active', 'inactive'], true)) {
            $variantStatus = 'active';
        }
        $stockValue = filter_var($stock === '' ? 0 : $stock, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
        $thresholdValue = filter_var($threshold === '' ? 10 : $threshold, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]);
        if ($stockValue === false || $thresholdValue === false) {
            $errors[] = 'Initial stock and low-stock threshold for ' . $sku . ' must be whole numbers.';
            continue;
        }
        if ($existingId > 0 && Database::value('SELECT COUNT(*) FROM product_variants WHERE id = ? AND product_id = ?', [$existingId, $id]) === 0) {
            $errors[] = 'One of the variants does not belong to this product.';
            continue;
        }
        $cleanVariants[] = [
            'key' => $key,
            'id' => $existingId,
            'sku' => $sku,
            'pack_size' => $packSize,
            'weight_grams' => $weightValue === false ? null : $weightValue,
            'price' => $priceValue === false ? null : $priceValue,
            'mrp' => $mrpValue === false ? null : $mrpValue,
            'cost_price' => $costValue === false ? null : $costValue,
            'status' => $variantStatus,
            'stock' => (int) $stockValue,
            'threshold' => (int) $thresholdValue,
        ];
    }
    if ($cleanVariants === []) {
        $errors[] = 'At least one valid variant (pack size and SKU) is required before saving.';
    }
    if ($defaultKey !== '' && !array_filter($cleanVariants, static fn (array $row): bool => $row['key'] === $defaultKey)) {
        $defaultKey = '';
    }

    if ($errors === []) {
        $data = [
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => $slug,
            'product_type' => (string) $form['product_type'] !== '' ? mb_substr((string) $form['product_type'], 0, 80) : null,
            'short_description' => $shortDescription !== '' ? $shortDescription : null,
            'description' => (string) $form['description'] !== '' ? (string) $form['description'] : null,
            'ingredients' => (string) $form['ingredients'] !== '' ? (string) $form['ingredients'] : null,
            'packaging_info' => (string) $form['packaging_info'] !== '' ? (string) $form['packaging_info'] : null,
            'storage_instructions' => (string) $form['storage_instructions'] !== '' ? (string) $form['storage_instructions'] : null,
            'origin' => (string) $form['origin'] !== '' ? mb_substr((string) $form['origin'], 0, 190) : null,
            'manufacturer_info' => (string) $form['manufacturer_info'] !== '' ? (string) $form['manufacturer_info'] : null,
            'nutritional_info' => (string) $form['nutritional_info'] !== '' ? (string) $form['nutritional_info'] : null,
            'allergen_info' => (string) $form['allergen_info'] !== '' ? (string) $form['allergen_info'] : null,
            'fssai_info' => (string) $form['fssai_info'] !== '' ? mb_substr((string) $form['fssai_info'], 0, 255) : null,
            'net_quantity_info' => (string) $form['net_quantity_info'] !== '' ? mb_substr((string) $form['net_quantity_info'], 0, 190) : null,
            'is_featured' => (int) $form['is_featured'],
            'is_new' => (int) $form['is_new'],
            'status' => (string) $form['status'],
            'tax_rate' => $taxRate,
            'meta_title' => $metaTitle !== '' ? $metaTitle : null,
            'meta_description' => $metaDescription !== '' ? $metaDescription : null,
            'keywords' => (string) $form['keywords'] !== '' ? mb_substr((string) $form['keywords'], 0, 500) : null,
            'search_aliases' => (string) $form['search_aliases'] !== '' ? mb_substr((string) $form['search_aliases'], 0, 500) : null,
        ];
        try {
            Database::pdo()->beginTransaction();
            if ($isNew) {
                $id = Database::insert('products', $data);
            } else {
                Database::update('products', $data, 'id = ?', [$id]);
            }
            $keyToVariantId = [];
            foreach ($cleanVariants as $row) {
                if ($row['id'] > 0) {
                    Database::update('product_variants', [
                        'sku' => $row['sku'],
                        'pack_size' => $row['pack_size'],
                        'weight_grams' => $row['weight_grams'],
                        'price' => $row['price'],
                        'mrp' => $row['mrp'],
                        'cost_price' => $row['cost_price'],
                        'status' => $row['status'],
                    ], 'id = ? AND product_id = ?', [$row['id'], $id]);
                    $variantId = $row['id'];
                } else {
                    $variantId = Database::insert('product_variants', [
                        'product_id' => $id,
                        'sku' => $row['sku'],
                        'pack_size' => $row['pack_size'],
                        'weight_grams' => $row['weight_grams'],
                        'price' => $row['price'],
                        'mrp' => $row['mrp'],
                        'cost_price' => $row['cost_price'],
                        'is_default' => 0,
                        'status' => $row['status'],
                    ]);
                    Database::insert('product_inventory', [
                        'variant_id' => $variantId,
                        'stock_qty' => $row['stock'],
                        'low_stock_threshold' => $row['threshold'],
                    ]);
                    if ($row['stock'] > 0) {
                        Database::insert('inventory_transactions', [
                            'variant_id' => $variantId,
                            'change_qty' => $row['stock'],
                            'reason' => 'stock_received',
                            'reference_type' => 'product_edit',
                            'reference_id' => (string) $id,
                            'admin_id' => $adminId > 0 ? $adminId : null,
                            'note' => 'Initial stock recorded while creating the variant',
                        ]);
                    }
                }
                $keyToVariantId[$row['key']] = $variantId;
            }
            $defaultVariantId = isset($keyToVariantId[$defaultKey]) ? $keyToVariantId[$defaultKey] : null;
            if ($defaultVariantId === null) {
                $existingDefault = Database::one('SELECT id FROM product_variants WHERE product_id = ? AND is_default = 1 ORDER BY status = "active" DESC, id ASC LIMIT 1', [$id]);
                $defaultVariantId = $existingDefault !== null ? (int) $existingDefault['id'] : (int) reset($keyToVariantId);
            }
            Database::q('UPDATE product_variants SET is_default = 0 WHERE product_id = ?', [$id]);
            if ($defaultVariantId > 0) {
                Database::update('product_variants', ['is_default' => 1], 'id = ? AND product_id = ?', [$defaultVariantId, $id]);
            }
            Database::pdo()->commit();
        } catch (Throwable $e) {
            if (Database::pdo()->inTransaction()) {
                Database::pdo()->rollBack();
            }
            log_message('error', 'Product save failed', ['error' => $e->getMessage(), 'product_id' => $id]);
            $errors[] = 'The product could not be saved. Please review the details and try again.';
            $isNew = $product === null;
        }
    }

    if ($errors === []) {
        $maxImages = 8;
        $existingCount = (int) Database::value('SELECT COUNT(*) FROM product_images WHERE product_id = ?', [$id]);
        if (isset($_FILES['images']) && is_array($_FILES['images']['name'] ?? null)) {
            $fileCount = count($_FILES['images']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if (($existingCount + $i) >= $maxImages) {
                    flash('warning', 'Only up to ' . $maxImages . ' images can be stored per product. Some uploads were skipped.');
                    break;
                }
                $file = [
                    'name' => $_FILES['images']['name'][$i] ?? '',
                    'type' => $_FILES['images']['type'][$i] ?? '',
                    'tmp_name' => $_FILES['images']['tmp_name'][$i] ?? '',
                    'error' => $_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $_FILES['images']['size'][$i] ?? 0,
                ];
                if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $upload = Uploader::image($file, 'products');
                if (!$upload['ok']) {
                    flash('error', 'Image "' . ($file['name'] !== '' ? $file['name'] : 'upload') . '": ' . $upload['error']);
                    continue;
                }
                $hasPrimary = (int) Database::value('SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_primary = 1', [$id]) > 0;
                $sortOrder = (int) Database::value('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_images WHERE product_id = ?', [$id]);
                Database::insert('product_images', [
                    'product_id' => $id,
                    'image_path' => $upload['path'],
                    'alt_text' => $name,
                    'sort_order' => $sortOrder,
                    'is_primary' => $hasPrimary ? 0 : 1,
                ]);
            }
        }
        Notifications::audit($product === null ? 'product.created' : 'product.updated', 'product', (string) $id, ['name' => $name, 'status' => $form['status']]);
        flash('success', $product === null ? 'Product created.' : 'Product saved.');
        redirect($editUrl($id));
    }
    $isNew = $product === null;
}

$categories = Database::all('SELECT id, name FROM categories ORDER BY sort_order ASC, name ASC');
$images = $isNew ? [] : Database::all('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC', [$id]);
$variants = $isNew ? [] : Database::all(
    'SELECT v.*, i.stock_qty, i.reserved_qty, i.low_stock_threshold
     FROM product_variants v LEFT JOIN product_inventory i ON i.variant_id = v.id
     WHERE v.product_id = ? ORDER BY v.is_default DESC, v.id ASC',
    [$id]
);

$fieldValue = static function (string $key) use ($form): string {
    return (string) ($form[$key] ?? '');
};
$inputError = static function (string $needle) use ($errors): string {
    foreach ($errors as $error) {
        if (stripos($error, $needle) !== false) {
            return ' field-error';
        }
    }
    return '';
};

$adminTitle = $isNew ? 'Add product' : 'Edit product';
$adminPage = 'products';
require __DIR__ . '/inc/header.php';
?>

<div class="admin-page-head">
  <div>
    <h1><?= $isNew ? 'Add product' : e($product['name']) ?></h1>
    <p><?= $isNew ? 'Create a product, then add variants and photos.' : 'Update catalogue content, variants, pricing, stock and images.' ?></p>
  </div>
  <div class="admin-page-actions">
    <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('products.php')) ?>"><?= icon('arrow-left') ?> All products</a>
    <?php if (!$isNew): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('products/' . $product['slug'])) ?>" target="_blank" rel="noopener"><?= icon('external') ?> View</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($errors !== []): ?>
  <div class="alert alert-error" role="alert">
    <strong>Please fix the following:</strong>
    <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<form class="admin-form" method="post" action="<?= e($isNew ? admin_url('product-edit.php') : $editUrl($id)) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">

  <div class="admin-grid-2">
    <div>
      <section class="admin-panel">
        <div class="admin-panel-head"><h2>Basics</h2></div>
        <div class="admin-panel-body">
          <div class="field">
            <label for="name">Product name <span class="req">*</span></label>
            <input id="name" type="text" name="name" value="<?= e($fieldValue('name')) ?>" maxlength="190" required>
          </div>
          <div class="field-row">
            <div class="field">
              <label for="category_id">Category <span class="req">*</span></label>
              <select id="category_id" name="category_id" required>
                <option value="">Choose a category</option>
                <?php foreach ($categories as $category): ?>
                  <option value="<?= (int) $category['id'] ?>" <?= (int) $fieldValue('category_id') === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="product_type">Product type</label>
              <input id="product_type" type="text" name="product_type" value="<?= e($fieldValue('product_type')) ?>" maxlength="80" placeholder="e.g. Ground spice">
            </div>
          </div>
          <div class="field">
            <label for="slug">URL slug</label>
            <input id="slug" type="text" name="slug" value="<?= e($fieldValue('slug')) ?>" maxlength="200" placeholder="Auto-generated from the name if left empty">
            <p class="field-hint">Shown as /products/&lt;slug&gt;. Leave blank to generate automatically.</p>
          </div>
          <div class="field<?= $inputError('Short description') ?>">
            <label for="short_description">Short description</label>
            <textarea id="short_description" name="short_description" rows="2" maxlength="500"><?= e($fieldValue('short_description')) ?></textarea>
            <p class="field-hint">Used on product cards and search results (500 characters).</p>
          </div>
          <div class="field">
            <label for="description">Full description</label>
            <textarea id="description" name="description" rows="7"><?= e($fieldValue('description')) ?></textarea>
            <p class="field-hint">Plain text. Line breaks are preserved on the product page.</p>
          </div>
          <div class="field">
            <label for="product_type_note">Publishing status</label>
            <select id="product_type_note" name="status">
              <?php foreach ($statuses as $statusOption): ?>
                <option value="<?= e($statusOption) ?>" <?= $fieldValue('status') === $statusOption ? 'selected' : '' ?>><?= e(ucfirst($statusOption)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="admin-checks">
            <label class="check"><input type="checkbox" name="is_featured" value="1" <?= $fieldValue('is_featured') === '1' ? 'checked' : '' ?>> Featured on the home page</label>
            <label class="check"><input type="checkbox" name="is_new" value="1" <?= $fieldValue('is_new') === '1' ? 'checked' : '' ?>> Show in "New arrivals"</label>
          </div>
          <div class="field">
            <label for="images">Add images</label>
            <input id="images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
            <p class="field-hint">Up to 8 images per product, 5 MB each. JPG, PNG or WebP. The first image becomes primary.</p>
          </div>
        </div>
      </section>

      <section class="admin-panel">
        <div class="admin-panel-head"><h2>Product details</h2></div>
        <div class="admin-panel-body">
          <p class="inline-note">Only publish values that have been verified by the business. Leave fields blank when information is not available.</p>
          <div class="field-row">
            <div class="field">
              <label for="origin">Origin</label>
              <input id="origin" type="text" name="origin" value="<?= e($fieldValue('origin')) ?>" maxlength="190">
            </div>
            <div class="field">
              <label for="net_quantity_info">Net quantity note</label>
              <input id="net_quantity_info" type="text" name="net_quantity_info" value="<?= e($fieldValue('net_quantity_info')) ?>" maxlength="190" placeholder="Shown alongside pack details">
            </div>
          </div>
          <div class="field">
            <label for="ingredients">Ingredients</label>
            <textarea id="ingredients" name="ingredients" rows="3"><?= e($fieldValue('ingredients')) ?></textarea>
          </div>
          <div class="field">
            <label for="packaging_info">Packaging</label>
            <textarea id="packaging_info" name="packaging_info" rows="2"><?= e($fieldValue('packaging_info')) ?></textarea>
          </div>
          <div class="field">
            <label for="storage_instructions">Storage instructions</label>
            <textarea id="storage_instructions" name="storage_instructions" rows="2"><?= e($fieldValue('storage_instructions')) ?></textarea>
          </div>
          <div class="field">
            <label for="manufacturer_info">Manufacturer / packer information</label>
            <textarea id="manufacturer_info" name="manufacturer_info" rows="2"><?= e($fieldValue('manufacturer_info')) ?></textarea>
          </div>
          <div class="field">
            <label for="nutritional_info">Nutritional information</label>
            <textarea id="nutritional_info" name="nutritional_info" rows="3" placeholder="Only enter verified values."><?= e($fieldValue('nutritional_info')) ?></textarea>
          </div>
          <div class="field">
            <label for="allergen_info">Allergen information</label>
            <textarea id="allergen_info" name="allergen_info" rows="2"><?= e($fieldValue('allergen_info')) ?></textarea>
          </div>
          <div class="field">
            <label for="fssai_info">FSSAI / licence information</label>
            <input id="fssai_info" type="text" name="fssai_info" value="<?= e($fieldValue('fssai_info')) ?>" maxlength="255" placeholder="Only if verified">
          </div>
        </div>
      </section>

      <section class="admin-panel">
        <div class="admin-panel-head">
          <h2>Variants &amp; pricing</h2>
          <span class="inline-note">Leave price empty to show "Price on request".</span>
        </div>
        <div class="table-scroll">
          <table class="admin-table">
            <thead><tr><th>SKU</th><th>Pack size</th><th class="num">Grams</th><th class="num">Price ₹</th><th class="num">MRP ₹</th><th class="num">Cost ₹</th><th>Status</th><th>Default</th><th></th></tr></thead>
            <tbody id="variant-rows">
              <?php foreach ($variants as $variant): ?>
                <?php $key = (string) $variant['id']; ?>
                <tr class="variant-row">
                  <td><input type="text" name="variants[<?= e($key) ?>][sku]" value="<?= e($variant['sku']) ?>" maxlength="80" required></td>
                  <td><input type="text" name="variants[<?= e($key) ?>][pack_size]" value="<?= e($variant['pack_size']) ?>" maxlength="60" required></td>
                  <td><input type="number" name="variants[<?= e($key) ?>][weight_grams]" value="<?= $variant['weight_grams'] !== null ? (int) $variant['weight_grams'] : '' ?>" min="0" step="1"></td>
                  <td><input type="number" name="variants[<?= e($key) ?>][price]" value="<?= $variant['price'] !== null ? e(number_format((float) $variant['price'], 2, '.', '')) : '' ?>" min="0" step="0.01"></td>
                  <td><input type="number" name="variants[<?= e($key) ?>][mrp]" value="<?= $variant['mrp'] !== null ? e(number_format((float) $variant['mrp'], 2, '.', '')) : '' ?>" min="0" step="0.01"></td>
                  <td><input type="number" name="variants[<?= e($key) ?>][cost_price]" value="<?= $variant['cost_price'] !== null ? e(number_format((float) $variant['cost_price'], 2, '.', '')) : '' ?>" min="0" step="0.01"></td>
                  <td>
                    <select name="variants[<?= e($key) ?>][status]">
                      <option value="active" <?= $variant['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                      <option value="inactive" <?= $variant['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                  </td>
                  <td><input type="radio" name="default_variant" value="<?= e($key) ?>" <?= (int) $variant['is_default'] === 1 ? 'checked' : '' ?> aria-label="Default variant"></td>
                  <td class="row-actions">
                    <span class="cell-sub"><?= (int) ($variant['stock_qty'] ?? 0) ?> in stock</span>
                    <button class="link-danger" type="submit" form="variant-delete-<?= (int) $variant['id'] ?>" data-confirm="Delete variant <?= e($variant['sku']) ?>? Past orders keep their records.">Delete</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="admin-panel-foot">
          <button class="btn btn-ghost btn-sm" type="button" data-add-variant><?= icon('plus') ?> Add variant</button>
          <span class="inline-note">Existing stock can be adjusted from <a href="<?= e(admin_url('inventory.php')) ?>">Inventory</a>.</span>
        </div>
      </section>
      <template id="variant-new-template">
        <tr class="variant-row">
          <td><input type="text" name="variants[__KEY__][sku]" maxlength="80" placeholder="SKU"></td>
          <td><input type="text" name="variants[__KEY__][pack_size]" maxlength="60" placeholder="e.g. 250 g"></td>
          <td><input type="number" name="variants[__KEY__][weight_grams]" min="0" step="1"></td>
          <td><input type="number" name="variants[__KEY__][price]" min="0" step="0.01"></td>
          <td><input type="number" name="variants[__KEY__][mrp]" min="0" step="0.01"></td>
          <td><input type="number" name="variants[__KEY__][cost_price]" min="0" step="0.01"></td>
          <td>
            <select name="variants[__KEY__][status]"><option value="active">Active</option><option value="inactive">Inactive</option></select>
          </td>
          <td><input type="radio" name="default_variant" value="__KEY__" aria-label="Default variant"></td>
          <td class="row-actions">
            <input type="number" name="variants[__KEY__][stock_qty]" min="0" step="1" value="0" title="Initial stock" style="width:74px">
            <input type="number" name="variants[__KEY__][low_stock_threshold]" min="0" step="1" value="10" title="Low stock threshold" style="width:74px">
          </td>
        </tr>
      </template>

      <section class="admin-panel">
        <div class="admin-panel-head"><h2>Search engine listing</h2></div>
        <div class="admin-panel-body">
          <div class="field<?= $inputError('SEO title') ?>">
            <label for="meta_title">Meta title</label>
            <input id="meta_title" type="text" name="meta_title" value="<?= e($fieldValue('meta_title')) ?>" maxlength="190">
          </div>
          <div class="field<?= $inputError('SEO description') ?>">
            <label for="meta_description">Meta description</label>
            <textarea id="meta_description" name="meta_description" rows="2" maxlength="320"><?= e($fieldValue('meta_description')) ?></textarea>
          </div>
          <div class="field">
            <label for="keywords">Keywords</label>
            <input id="keywords" type="text" name="keywords" value="<?= e($fieldValue('keywords')) ?>" maxlength="500" placeholder="Comma-separated">
          </div>
          <div class="field">
            <label for="search_aliases">Search aliases</label>
            <input id="search_aliases" type="text" name="search_aliases" value="<?= e($fieldValue('search_aliases')) ?>" maxlength="500" placeholder="Hindi or alternate names, comma-separated">
            <p class="field-hint">Used by the shop search, e.g. "haldi, turmeric".</p>
          </div>
        </div>
      </section>

      <div class="admin-form-actions">
        <button class="btn btn-primary" type="submit"><?= $isNew ? 'Create product' : 'Save changes' ?></button>
        <a class="btn btn-ghost" href="<?= e(admin_url('products.php')) ?>">Cancel</a>
      </div>
    </div>

    <aside>
      <?php if (!$isNew): ?>
        <section class="admin-panel">
          <div class="admin-panel-head"><h2>Tax</h2></div>
          <div class="admin-panel-body">
            <div class="field">
              <label for="tax_rate">Tax rate (%)</label>
              <input id="tax_rate" type="number" name="tax_rate" value="<?= e($fieldValue('tax_rate')) ?>" min="0" max="100" step="0.01">
              <p class="field-hint">Store-wide tax applies at checkout when configured.</p>
            </div>
          </div>
        </section>
      <?php else: ?>
        <input type="hidden" name="tax_rate" value="<?= e($fieldValue('tax_rate')) ?>">
        <section class="admin-panel">
          <div class="admin-panel-body">
            <p class="inline-note">Save the product first to manage photos, stock history and tax.</p>
          </div>
        </section>
      <?php endif; ?>

      <section class="admin-panel">
        <div class="admin-panel-head"><h2>Product status</h2></div>
        <div class="admin-panel-body">
          <?php if (!$isNew): ?>
            <p><span class="<?= e(status_class((string) $product['status'])) ?>"><?= e(ucfirst((string) $product['status'])) ?></span></p>
            <p class="cell-sub">Created <?= e(date_display($product['created_at'])) ?><br>Updated <?= e(date_display($product['updated_at'])) ?></p>
            <p class="cell-sub">Stock is managed per variant in <a href="<?= e(admin_url('inventory.php')) ?>">Inventory</a>.</p>
          <?php else: ?>
            <p class="cell-sub">New products start as drafts until you publish them.</p>
          <?php endif; ?>
        </div>
      </section>
    </aside>
  </div>
</form>

<?php if (!$isNew): ?>
  <?php foreach ($variants as $variant): ?>
    <form id="variant-delete-<?= (int) $variant['id'] ?>" method="post" action="<?= e($editUrl($id)) ?>" hidden>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="variant_delete">
      <input type="hidden" name="variant_id" value="<?= (int) $variant['id'] ?>">
    </form>
  <?php endforeach; ?>

  <section class="admin-panel" id="images">
    <div class="admin-panel-head">
      <h2>Images</h2>
      <span class="inline-note"><?= count($images) ?> of 8</span>
    </div>
    <div class="admin-panel-body">
      <?php if ($images === []): ?>
        <p class="muted">No images yet. Add up to 8 verified product photos below.</p>
      <?php endif; ?>
      <div class="image-grid">
        <?php foreach ($images as $index => $image): ?>
          <div class="image-item">
            <img src="<?= e(asset_url($image['image_path'])) ?>" alt="<?= e($image['alt_text'] ?? '') ?>">
            <div class="image-item-actions">
              <?php if ((int) $image['is_primary'] === 1): ?>
                <span class="pill pill-green">Primary</span>
              <?php else: ?>
                <form method="post" action="<?= e($editUrl($id)) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="image_primary">
                  <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                  <button class="pill pill-grey" type="submit">Make primary</button>
                </form>
              <?php endif; ?>
              <form method="post" action="<?= e($editUrl($id)) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="image_move">
                <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                <input type="hidden" name="direction" value="up">
                <button class="btn btn-ghost btn-sm" type="submit" <?= $index === 0 ? 'disabled' : '' ?> aria-label="Move image up">↑</button>
              </form>
              <form method="post" action="<?= e($editUrl($id)) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="image_move">
                <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                <input type="hidden" name="direction" value="down">
                <button class="btn btn-ghost btn-sm" type="submit" <?= $index === count($images) - 1 ? 'disabled' : '' ?> aria-label="Move image down">↓</button>
              </form>
              <form method="post" action="<?= e($editUrl($id)) ?>" data-confirm="Remove this image?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="image_delete">
                <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit">Remove</button>
              </form>
            </div>
            <form class="image-alt-form" method="post" action="<?= e($editUrl($id)) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="image_alt">
              <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
              <label class="sr-only" for="alt-<?= (int) $image['id'] ?>">Image description</label>
              <input id="alt-<?= (int) $image['id'] ?>" type="text" name="alt_text" value="<?= e($image['alt_text'] ?? '') ?>" maxlength="190" placeholder="Describe this image">
              <button class="btn btn-ghost btn-sm" type="submit">Save</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="inline-note">New images are uploaded with the main form. JPG, PNG or WebP, up to 5 MB each.</p>
    </div>
  </section>
<?php endif; ?>

<script>
(function () {
  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-add-variant]');
    if (!trigger) {
      return;
    }
    var template = document.getElementById('variant-new-template');
    var rows = document.getElementById('variant-rows');
    if (!template || !rows) {
      return;
    }
    var key = 'n' + Date.now() + Math.floor(Math.random() * 1000);
    rows.insertAdjacentHTML('beforeend', template.innerHTML.split('__KEY__').join(key));
  });
})();
</script>

<?php require __DIR__ . '/inc/footer.php'; ?>
