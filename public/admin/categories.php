<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin('categories.manage');

$errors = [];
$editId = max(0, (int) ($_GET['edit'] ?? 0));
$editCategory = $editId > 0 ? Database::one('SELECT * FROM categories WHERE id = ?', [$editId]) : null;
$showForm = $editCategory !== null || isset($_GET['new']);

if (is_post()) {
    verify_csrf();
    $action = post_str('action');

    if ($action === 'delete') {
        $targetId = post_int('id');
        $category = Database::one('SELECT * FROM categories WHERE id = ?', [$targetId]);
        if ($category !== null) {
            $productCount = (int) Database::value('SELECT COUNT(*) FROM products WHERE category_id = ?', [$targetId]);
            $childCount = (int) Database::value('SELECT COUNT(*) FROM categories WHERE parent_id = ?', [$targetId]);
            if ($productCount > 0 || $childCount > 0) {
                flash('error', 'This category cannot be deleted while it has products or subcategories. Move them first.');
            } else {
                if ($category['image'] !== null && $category['image'] !== '') {
                    Uploader::remove((string) $category['image']);
                }
                Database::q('DELETE FROM categories WHERE id = ?', [$targetId]);
                Notifications::audit('category.deleted', 'category', (string) $targetId, ['name' => $category['name']]);
                flash('success', 'Category deleted.');
            }
        }
        redirect('admin/categories.php');
    }

    if ($action === 'save') {
        $targetId = post_int('id');
        $existing = $targetId > 0 ? Database::one('SELECT * FROM categories WHERE id = ?', [$targetId]) : null;
        $name = trim(post_str('name'));
        $slugInput = strtolower(trim(post_str('slug')));
        $description = trim(post_str('description'));
        $metaTitle = trim(post_str('meta_title'));
        $metaDescription = trim(post_str('meta_description'));
        $sortOrder = post_int('sort_order');
        $status = post_str('status');
        $parentId = post_int('parent_id');

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors[] = 'Category name must be between 2 and 120 characters.';
        }
        if (mb_strlen($metaTitle) > 190) {
            $errors[] = 'SEO title must be 190 characters or fewer.';
        }
        if (mb_strlen($metaDescription) > 320) {
            $errors[] = 'SEO description must be 320 characters or fewer.';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }
        if ($parentId > 0) {
            $parent = Database::one('SELECT id FROM categories WHERE id = ?', [$parentId]);
            if ($parent === null || $parentId === $targetId) {
                $errors[] = 'Please choose a valid parent category.';
            }
        } else {
            $parentId = 0;
        }
        $slugBase = $slugInput !== '' ? $slugInput : strtolower($name);
        $slugBase = preg_replace('/[^a-z0-9]+/', '-', $slugBase) ?? '';
        $slugBase = trim($slugBase, '-');
        if ($slugBase === '' || strlen($slugBase) > 140) {
            $errors[] = 'The URL slug must use letters, numbers and hyphens only (maximum 140 characters).';
            $slugBase = $slugBase !== '' ? substr($slugBase, 0, 130) : 'category';
        }
        $slug = $slugBase;
        $suffix = 2;
        while (Database::value('SELECT COUNT(*) FROM categories WHERE slug = ? AND id <> ?', [$slug, $targetId]) > 0) {
            $slug = $slugBase . '-' . $suffix;
            $suffix++;
            if ($suffix > 99) {
                $errors[] = 'This slug is already used. Please choose another.';
                break;
            }
        }

        if ($errors === []) {
            $imagePath = $existing['image'] ?? null;
            if (isset($_FILES['image']) && is_array($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $upload = Uploader::image($_FILES['image'], 'categories');
                if (!$upload['ok']) {
                    $errors[] = $upload['error'];
                } else {
                    if ($imagePath !== null && $imagePath !== '') {
                        Uploader::remove((string) $imagePath);
                    }
                    $imagePath = $upload['path'];
                }
            }
        }

        if ($errors === []) {
            $data = [
                'parent_id' => $parentId > 0 ? $parentId : null,
                'name' => $name,
                'slug' => $slug,
                'description' => $description !== '' ? $description : null,
                'image' => $imagePath !== null && $imagePath !== '' ? $imagePath : null,
                'meta_title' => $metaTitle !== '' ? $metaTitle : null,
                'meta_description' => $metaDescription !== '' ? $metaDescription : null,
                'sort_order' => $sortOrder,
                'status' => $status,
            ];
            if ($existing === null) {
                $targetId = Database::insert('categories', $data);
                Notifications::audit('category.created', 'category', (string) $targetId, ['name' => $name]);
                flash('success', 'Category created.');
            } else {
                Database::update('categories', $data, 'id = ?', [$targetId]);
                Notifications::audit('category.updated', 'category', (string) $targetId, ['name' => $name]);
                flash('success', 'Category saved.');
            }
            redirect('admin/categories.php');
        }
        $showForm = true;
    }
}

$categories = Database::all(
    'SELECT c.*, p.name AS parent_name,
        (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS product_count
     FROM categories c LEFT JOIN categories p ON p.id = c.parent_id
     ORDER BY c.sort_order ASC, c.name ASC'
);
$parents = Database::all('SELECT id, name FROM categories ORDER BY sort_order ASC, name ASC');
$formValues = $editCategory ?? [
    'id' => 0, 'parent_id' => null, 'name' => '', 'slug' => '', 'description' => '',
    'image' => null, 'meta_title' => '', 'meta_description' => '', 'sort_order' => 0, 'status' => 'active',
];
if (is_post() && post_str('action') === 'save' && $errors !== []) {
    $formValues = array_merge($formValues, [
        'id' => post_int('id'), 'parent_id' => post_int('parent_id') ?: null, 'name' => post_str('name'),
        'slug' => post_str('slug'), 'description' => post_str('description'),
        'meta_title' => post_str('meta_title'), 'meta_description' => post_str('meta_description'),
        'sort_order' => post_int('sort_order'), 'status' => post_str('status'),
    ]);
}

$adminTitle = 'Categories';
$adminPage = 'categories';
require __DIR__ . '/inc/header.php';
?>

<div class="admin-page-head">
  <div>
    <h1>Categories</h1>
    <p>Categories organise the shop menu and product filters. Inactive categories are hidden from the storefront.</p>
  </div>
  <div class="admin-page-actions">
    <a class="btn btn-primary btn-sm" href="<?= e(admin_url('categories.php?new=1')) ?>"><?= icon('plus') ?> Add category</a>
  </div>
</div>

<?php if ($showForm): ?>
  <section class="admin-panel">
    <div class="admin-panel-head">
      <h2><?= (int) $formValues['id'] > 0 ? 'Edit category' : 'New category' ?></h2>
      <a class="small" href="<?= e(admin_url('categories.php')) ?>">Close</a>
    </div>
    <?php if ($errors !== []): ?>
      <div class="admin-panel-body"><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div></div>
    <?php endif; ?>
    <form class="admin-form" method="post" action="<?= e(admin_url('categories.php')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int) $formValues['id'] ?>">
      <div class="admin-panel-body">
        <div class="field-row">
          <div class="field">
            <label for="name">Name <span class="req">*</span></label>
            <input id="name" type="text" name="name" value="<?= e((string) $formValues['name']) ?>" maxlength="120" required>
          </div>
          <div class="field">
            <label for="slug">URL slug</label>
            <input id="slug" type="text" name="slug" value="<?= e((string) $formValues['slug']) ?>" maxlength="140" placeholder="Auto-generated if empty">
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="parent_id">Parent category</label>
            <select id="parent_id" name="parent_id">
              <option value="0">None (top level)</option>
              <?php foreach ($parents as $parent): ?>
                <?php if ((int) $parent['id'] === (int) $formValues['id']) { continue; } ?>
                <option value="<?= (int) $parent['id'] ?>" <?= (int) $formValues['parent_id'] === (int) $parent['id'] ? 'selected' : '' ?>><?= e($parent['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
              <option value="active" <?= $formValues['status'] === 'active' ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= $formValues['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>
        </div>
        <div class="field">
          <label for="description">Description</label>
          <textarea id="description" name="description" rows="3"><?= e((string) $formValues['description']) ?></textarea>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="sort_order">Sort order</label>
            <input id="sort_order" type="number" name="sort_order" value="<?= (int) $formValues['sort_order'] ?>" step="1">
          </div>
          <div class="field">
            <label for="image">Category image</label>
            <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
            <?php if (!empty($formValues['image'])): ?>
              <p class="field-hint"><img src="<?= e(asset_url((string) $formValues['image'])) ?>" alt="" width="64" height="64" style="object-fit:cover;border-radius:8px"> Current image will be replaced.</p>
            <?php endif; ?>
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="meta_title">SEO title</label>
            <input id="meta_title" type="text" name="meta_title" value="<?= e((string) $formValues['meta_title']) ?>" maxlength="190">
          </div>
          <div class="field">
            <label for="meta_description">SEO description</label>
            <input id="meta_description" type="text" name="meta_description" value="<?= e((string) $formValues['meta_description']) ?>" maxlength="320">
          </div>
        </div>
      </div>
      <div class="admin-form-actions">
        <button class="btn btn-primary" type="submit"><?= (int) $formValues['id'] > 0 ? 'Save category' : 'Create category' ?></button>
        <a class="btn btn-ghost" href="<?= e(admin_url('categories.php')) ?>">Cancel</a>
      </div>
    </form>
  </section>
<?php endif; ?>

<div class="admin-panel">
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>Category</th><th>Parent</th><th class="num">Products</th><th class="num">Order</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if ($categories === []): ?>
          <tr><td colspan="6" class="muted">No categories yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($categories as $category): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <img class="thumb" src="<?= e(asset_url($category['image'])) ?>" alt="" width="40" height="40">
                <span><strong class="cell-title"><?= e($category['name']) ?></strong><span class="cell-sub">/<?= e($category['slug']) ?></span></span>
              </div>
            </td>
            <td><?= e($category['parent_name'] ?? '—') ?></td>
            <td class="num"><?= (int) $category['product_count'] ?></td>
            <td class="num"><?= (int) $category['sort_order'] ?></td>
            <td><span class="<?= e(status_class((string) $category['status'])) ?>"><?= e(ucfirst((string) $category['status'])) ?></span></td>
            <td>
              <div class="row-actions">
                <a href="<?= e(url('category/' . $category['slug'])) ?>" target="_blank" rel="noopener">View</a>
                <a href="<?= e(admin_url('categories.php?edit=' . (int) $category['id'])) ?>">Edit</a>
                <form method="post" action="<?= e(admin_url('categories.php')) ?>" data-confirm="Delete this category? This cannot be undone.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                  <button class="link-danger" type="submit">Delete</button>
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
