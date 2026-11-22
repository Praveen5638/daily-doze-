<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin('content.manage');

$tab = (string) ($_GET['tab'] ?? 'pages');
if (!in_array($tab, ['pages', 'banners', 'team'], true)) {
    $tab = 'pages';
}
$errors = [];
$editId = max(0, (int) ($_GET['edit'] ?? 0));
$isNewRequest = isset($_GET['new']);

if (is_post()) {
    verify_csrf();
    $action = post_str('action');

    if ($action === 'page_save') {
        $pageId = post_int('id');
        $existing = $pageId > 0 ? Database::one('SELECT * FROM cms_pages WHERE id = ?', [$pageId]) : null;
        $title = trim(post_str('title'));
        $slugInput = strtolower(trim(post_str('slug')));
        $body = (string) ($_POST['body'] ?? '');
        $metaTitle = trim(post_str('meta_title'));
        $metaDescription = trim(post_str('meta_description'));
        $status = post_str('status');

        if (mb_strlen($title) < 2 || mb_strlen($title) > 190) {
            $errors[] = 'Page title must be between 2 and 190 characters.';
        }
        if (mb_strlen($body) > 500000) {
            $errors[] = 'The page body is too large.';
        }
        if (mb_strlen($metaTitle) > 190) {
            $errors[] = 'SEO title must be 190 characters or fewer.';
        }
        if (mb_strlen($metaDescription) > 320) {
            $errors[] = 'SEO description must be 320 characters or fewer.';
        }
        if (!in_array($status, ['published', 'draft'], true)) {
            $status = 'draft';
        }
        $slugBase = $slugInput !== '' ? $slugInput : strtolower($title);
        $slugBase = preg_replace('/[^a-z0-9]+/', '-', $slugBase) ?? '';
        $slugBase = trim($slugBase, '-');
        if ($slugBase === '' || strlen($slugBase) > 140) {
            $errors[] = 'The page slug must use letters, numbers and hyphens only (maximum 140 characters).';
            $slugBase = $slugBase !== '' ? substr($slugBase, 0, 130) : 'page';
        }
        $slug = $slugBase;
        $suffix = 2;
        while (Database::value('SELECT COUNT(*) FROM cms_pages WHERE slug = ? AND id <> ?', [$slug, $pageId]) > 0) {
            $slug = $slugBase . '-' . $suffix;
            $suffix++;
            if ($suffix > 99) {
                $errors[] = 'This slug is already used. Please choose another.';
                break;
            }
        }
        if ($errors === []) {
            $data = [
                'title' => $title,
                'slug' => $slug,
                'body' => $body !== '' ? $body : null,
                'meta_title' => $metaTitle !== '' ? $metaTitle : null,
                'meta_description' => $metaDescription !== '' ? $metaDescription : null,
                'status' => $status,
            ];
            if ($existing === null) {
                $pageId = Database::insert('cms_pages', $data);
                Notifications::audit('cms_page.created', 'cms_page', (string) $pageId, ['slug' => $slug]);
                flash('success', 'Page created.');
            } else {
                Database::update('cms_pages', $data, 'id = ?', [$pageId]);
                Notifications::audit('cms_page.updated', 'cms_page', (string) $pageId, ['slug' => $slug, 'status' => $status]);
                flash('success', 'Page saved.');
            }
            redirect('admin/content.php?tab=pages');
        }
        $editId = $pageId;
        $isNewRequest = $existing === null;
        $tab = 'pages';
    }

    if ($action === 'banner_save') {
        $bannerId = post_int('id');
        $existing = $bannerId > 0 ? Database::one('SELECT * FROM banners WHERE id = ?', [$bannerId]) : null;
        $title = trim(post_str('title'));
        $subtitle = trim(post_str('subtitle'));
        $linkUrl = trim(post_str('link_url'));
        $placement = post_str('placement');
        $sortOrder = post_int('sort_order');
        $status = post_str('status');
        $startsAt = trim(post_str('starts_at'));
        $endsAt = trim(post_str('ends_at'));

        if (mb_strlen($title) > 190 || mb_strlen($subtitle) > 255) {
            $errors[] = 'Banner title or subtitle is too long.';
        }
        if (!in_array($placement, ['home_announcement', 'home_hero', 'home_mid', 'shop_top'], true)) {
            $errors[] = 'Please choose a banner placement.';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'inactive';
        }
        if ($linkUrl !== '') {
            if (str_starts_with($linkUrl, '//') || preg_match('/^(javascript|data):/i', $linkUrl) || (!str_starts_with($linkUrl, '/') && !preg_match('#^[a-z0-9][a-z0-9._/\-?=&%\#]*$#i', $linkUrl))) {
                $errors[] = 'Banner link must be a relative site path or a full https:// address.';
            } elseif (preg_match('#^https?://#i', $linkUrl) && !is_safe_url($linkUrl)) {
                $errors[] = 'Banner link must use a valid http or https address.';
            }
        }
        $startDate = null;
        if ($startsAt !== '') {
            $ts = strtotime($startsAt);
            if ($ts === false) {
                $errors[] = 'The banner start date is not valid.';
            } else {
                $startDate = date('Y-m-d H:i:s', $ts);
            }
        }
        $endDate = null;
        if ($endsAt !== '') {
            $ts = strtotime($endsAt);
            if ($ts === false) {
                $errors[] = 'The banner end date is not valid.';
            } else {
                $endDate = date('Y-m-d H:i:s', $ts);
            }
        }
        if ($startDate !== null && $endDate !== null && $endDate <= $startDate) {
            $errors[] = 'The banner end date must be after the start date.';
        }
        $imagePath = $existing['image'] ?? null;
        if ($errors === [] && isset($_FILES['image']) && is_array($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = Uploader::image($_FILES['image'], 'banners');
            if (!$upload['ok']) {
                $errors[] = $upload['error'];
            } else {
                if ($imagePath !== null && $imagePath !== '') {
                    Uploader::remove((string) $imagePath);
                }
                $imagePath = $upload['path'];
            }
        }
        if ($errors === []) {
            $data = [
                'title' => $title !== '' ? $title : null,
                'subtitle' => $subtitle !== '' ? $subtitle : null,
                'image' => $imagePath !== null && $imagePath !== '' ? $imagePath : null,
                'link_url' => $linkUrl !== '' ? $linkUrl : null,
                'placement' => $placement,
                'sort_order' => $sortOrder,
                'status' => $status,
                'starts_at' => $startDate,
                'ends_at' => $endDate,
            ];
            if ($existing === null) {
                $bannerId = Database::insert('banners', $data);
                Notifications::audit('banner.created', 'banner', (string) $bannerId, ['placement' => $placement]);
                flash('success', 'Banner created.');
            } else {
                Database::update('banners', $data, 'id = ?', [$bannerId]);
                Notifications::audit('banner.updated', 'banner', (string) $bannerId, ['placement' => $placement, 'status' => $status]);
                flash('success', 'Banner saved.');
            }
            redirect('admin/content.php?tab=banners');
        }
        $editId = $bannerId;
        $isNewRequest = $existing === null;
        $tab = 'banners';
    }

    if ($action === 'banner_delete') {
        $bannerId = post_int('id');
        $banner = Database::one('SELECT * FROM banners WHERE id = ?', [$bannerId]);
        if ($banner !== null) {
            if ($banner['image'] !== null && $banner['image'] !== '') {
                Uploader::remove((string) $banner['image']);
            }
            Database::q('DELETE FROM banners WHERE id = ?', [$bannerId]);
            Notifications::audit('banner.deleted', 'banner', (string) $bannerId);
            flash('success', 'Banner deleted.');
        }
        redirect('admin/content.php?tab=banners');
    }

    if ($action === 'team_save') {
        $memberId = post_int('id');
        $existing = $memberId > 0 ? Database::one('SELECT * FROM team_members WHERE id = ?', [$memberId]) : null;
        $name = trim(post_str('name'));
        $position = trim(post_str('position'));
        $bio = trim((string) ($_POST['bio'] ?? ''));
        $sortOrder = post_int('sort_order');
        $status = post_str('status');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors[] = 'Team member name must be between 2 and 120 characters.';
        }
        if ($position === '' || mb_strlen($position) > 120) {
            $errors[] = 'Please enter a position (maximum 120 characters).';
        }
        if (mb_strlen($bio) > 2000) {
            $errors[] = 'The bio must be 2,000 characters or fewer.';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }
        $imagePath = $existing['image'] ?? null;
        if ($errors === [] && isset($_FILES['image']) && is_array($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = Uploader::image($_FILES['image'], 'team');
            if (!$upload['ok']) {
                $errors[] = $upload['error'];
            } else {
                if ($imagePath !== null && $imagePath !== '') {
                    Uploader::remove((string) $imagePath);
                }
                $imagePath = $upload['path'];
            }
        }
        if ($errors === []) {
            $data = [
                'name' => $name,
                'position' => $position,
                'bio' => $bio !== '' ? $bio : null,
                'image' => $imagePath !== null && $imagePath !== '' ? $imagePath : null,
                'sort_order' => $sortOrder,
                'status' => $status,
            ];
            if ($existing === null) {
                $memberId = Database::insert('team_members', $data);
                Notifications::audit('team_member.created', 'team_member', (string) $memberId, ['name' => $name]);
                flash('success', 'Team member added.');
            } else {
                Database::update('team_members', $data, 'id = ?', [$memberId]);
                Notifications::audit('team_member.updated', 'team_member', (string) $memberId, ['name' => $name]);
                flash('success', 'Team member saved.');
            }
            redirect('admin/content.php?tab=team');
        }
        $editId = $memberId;
        $isNewRequest = $existing === null;
        $tab = 'team';
    }

    if ($action === 'team_delete') {
        $memberId = post_int('id');
        $member = Database::one('SELECT * FROM team_members WHERE id = ?', [$memberId]);
        if ($member !== null) {
            if ($member['image'] !== null && $member['image'] !== '') {
                Uploader::remove((string) $member['image']);
            }
            Database::q('DELETE FROM team_members WHERE id = ?', [$memberId]);
            Notifications::audit('team_member.deleted', 'team_member', (string) $memberId, ['name' => $member['name']]);
            flash('success', 'Team member removed.');
        }
        redirect('admin/content.php?tab=team');
    }
}

$pages = Database::all('SELECT * FROM cms_pages ORDER BY id ASC');
$banners = Database::all('SELECT * FROM banners ORDER BY placement ASC, sort_order ASC, id ASC');
$team = Database::all('SELECT * FROM team_members ORDER BY sort_order ASC, id ASC');

$editPage = $tab === 'pages' && $editId > 0 ? Database::one('SELECT * FROM cms_pages WHERE id = ?', [$editId]) : null;
$editBanner = $tab === 'banners' && $editId > 0 ? Database::one('SELECT * FROM banners WHERE id = ?', [$editId]) : null;
$editMember = $tab === 'team' && $editId > 0 ? Database::one('SELECT * FROM team_members WHERE id = ?', [$editId]) : null;
$showPageForm = $tab === 'pages' && ($editPage !== null || $isNewRequest || $errors !== []);
$showBannerForm = $tab === 'banners' && ($editBanner !== null || $isNewRequest || $errors !== []);
$showMemberForm = $tab === 'team' && ($editMember !== null || $isNewRequest || $errors !== []);

$pageValues = $editPage ?? ['id' => 0, 'title' => '', 'slug' => '', 'body' => '', 'meta_title' => '', 'meta_description' => '', 'status' => 'draft'];
$bannerValues = $editBanner ?? ['id' => 0, 'title' => '', 'subtitle' => '', 'image' => null, 'link_url' => '', 'placement' => 'home_mid', 'sort_order' => 0, 'status' => 'inactive', 'starts_at' => null, 'ends_at' => null];
$memberValues = $editMember ?? ['id' => 0, 'name' => '', 'position' => '', 'bio' => '', 'image' => null, 'sort_order' => 0, 'status' => 'active'];
if (is_post() && $errors !== []) {
    if ($tab === 'pages') {
        $pageValues = array_merge($pageValues, ['id' => post_int('id'), 'title' => post_str('title'), 'slug' => post_str('slug'), 'body' => (string) ($_POST['body'] ?? ''), 'meta_title' => post_str('meta_title'), 'meta_description' => post_str('meta_description'), 'status' => post_str('status')]);
    } elseif ($tab === 'banners') {
        $bannerValues = array_merge($bannerValues, [
            'id' => post_int('id'), 'title' => post_str('title'), 'subtitle' => post_str('subtitle'),
            'link_url' => post_str('link_url'), 'placement' => post_str('placement'), 'sort_order' => post_int('sort_order'),
            'status' => post_str('status'), 'starts_at' => post_str('starts_at') ?: null, 'ends_at' => post_str('ends_at') ?: null,
        ]);
    } else {
        $memberValues = array_merge($memberValues, ['id' => post_int('id'), 'name' => post_str('name'), 'position' => post_str('position'), 'bio' => (string) ($_POST['bio'] ?? ''), 'sort_order' => post_int('sort_order'), 'status' => post_str('status')]);
    }
}
$dateInput = static function (?string $value): string {
    return $value !== null && $value !== '' ? str_replace(' ', 'T', substr($value, 0, 16)) : '';
};
$placementLabels = ['home_announcement' => 'Home announcement', 'home_hero' => 'Home hero', 'home_mid' => 'Home mid banner', 'shop_top' => 'Shop top banner'];
$tabUrl = static function (string $target, array $extra = []): string {
    return admin_url('content.php?' . http_build_query(array_merge(['tab' => $target], $extra)));
};

$adminTitle = 'Content';
$adminPage = 'content';
require __DIR__ . '/inc/header.php';
?>

<div class="admin-page-head">
  <div>
    <h1>Content</h1>
    <p>CMS pages, promotional banners and team profiles. Storefront text settings live under <a href="<?= e(admin_url('settings.php')) ?>">Settings</a>.</p>
  </div>
</div>

<nav class="admin-checks" style="margin-bottom:18px" aria-label="Content sections">
  <a class="pill <?= $tab === 'pages' ? 'pill-green' : 'pill-grey' ?>" href="<?= e($tabUrl('pages')) ?>">Pages (<?= count($pages) ?>)</a>
  <a class="pill <?= $tab === 'banners' ? 'pill-green' : 'pill-grey' ?>" href="<?= e($tabUrl('banners')) ?>">Banners (<?= count($banners) ?>)</a>
  <a class="pill <?= $tab === 'team' ? 'pill-green' : 'pill-grey' ?>" href="<?= e($tabUrl('team')) ?>">Team (<?= count($team) ?>)</a>
</nav>

<?php if ($tab === 'pages'): ?>
  <?php if ($showPageForm): ?>
    <section class="admin-panel">
      <div class="admin-panel-head">
        <h2><?= (int) $pageValues['id'] > 0 ? 'Edit page' : 'New page' ?></h2>
        <a class="small" href="<?= e($tabUrl('pages')) ?>">Close</a>
      </div>
      <?php if ($errors !== []): ?><div class="admin-panel-body"><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div></div><?php endif; ?>
      <form class="admin-form" method="post" action="<?= e(admin_url('content.php?tab=pages')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="page_save">
        <input type="hidden" name="id" value="<?= (int) $pageValues['id'] ?>">
        <div class="admin-panel-body">
          <div class="field-row">
            <div class="field">
              <label for="title">Title <span class="req">*</span></label>
              <input id="title" type="text" name="title" value="<?= e((string) $pageValues['title']) ?>" maxlength="190" required>
            </div>
            <div class="field">
              <label for="slug">Slug</label>
              <input id="slug" type="text" name="slug" value="<?= e((string) $pageValues['slug']) ?>" maxlength="140" placeholder="Auto-generated if empty">
              <p class="field-hint">Page is available at /pages/&lt;slug&gt;.</p>
            </div>
          </div>
          <div class="field">
            <label for="body">Body</label>
            <textarea id="body" name="body" rows="18" style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.86rem"><?= e((string) ($pageValues['body'] ?? '')) ?></textarea>
            <p class="field-hint">HTML is sanitised before display. Use headings, paragraphs, lists and tables. Draft pages are visible only with a notice.</p>
          </div>
          <div class="field-row">
            <div class="field">
              <label for="meta_title">SEO title</label>
              <input id="meta_title" type="text" name="meta_title" value="<?= e((string) $pageValues['meta_title']) ?>" maxlength="190">
            </div>
            <div class="field">
              <label for="status">Status</label>
              <select id="status" name="status">
                <option value="draft" <?= $pageValues['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="published" <?= $pageValues['status'] === 'published' ? 'selected' : '' ?>>Published</option>
              </select>
            </div>
          </div>
          <div class="field">
            <label for="meta_description">SEO description</label>
            <textarea id="meta_description" name="meta_description" rows="2" maxlength="320"><?= e((string) $pageValues['meta_description']) ?></textarea>
          </div>
        </div>
        <div class="admin-form-actions">
          <button class="btn btn-primary" type="submit"><?= (int) $pageValues['id'] > 0 ? 'Save page' : 'Create page' ?></button>
          <a class="btn btn-ghost" href="<?= e($tabUrl('pages')) ?>">Cancel</a>
        </div>
      </form>
    </section>
  <?php endif; ?>

  <div class="admin-panel">
    <div class="admin-toolbar">
      <span class="inline-note">Policy pages shown in the footer are managed here. Keep legal text reviewed by the business.</span>
      <span class="spacer"></span>
      <a class="btn btn-primary btn-sm" href="<?= e($tabUrl('pages', ['new' => 1])) ?>"><?= icon('plus') ?> New page</a>
    </div>
    <div class="table-scroll">
      <table class="admin-table">
        <thead><tr><th>Title</th><th>Slug</th><th>Status</th><th>Updated</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($pages as $page): ?>
            <tr>
              <td><strong class="cell-title"><?= e($page['title']) ?></strong></td>
              <td><code>/pages/<?= e($page['slug']) ?></code></td>
              <td><span class="<?= e(status_class((string) $page['status'])) ?>"><?= e(ucfirst((string) $page['status'])) ?></span></td>
              <td class="cell-sub"><?= e(date_display($page['updated_at'])) ?></td>
              <td><div class="row-actions"><a href="<?= e(url('pages/' . $page['slug'])) ?>" target="_blank" rel="noopener">View</a><a href="<?= e($tabUrl('pages', ['edit' => (int) $page['id']])) ?>">Edit</a></div></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php if ($tab === 'banners'): ?>
  <?php if ($showBannerForm): ?>
    <section class="admin-panel">
      <div class="admin-panel-head">
        <h2><?= (int) $bannerValues['id'] > 0 ? 'Edit banner' : 'New banner' ?></h2>
        <a class="small" href="<?= e($tabUrl('banners')) ?>">Close</a>
      </div>
      <?php if ($errors !== []): ?><div class="admin-panel-body"><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div></div><?php endif; ?>
      <form class="admin-form" method="post" action="<?= e(admin_url('content.php?tab=banners')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="banner_save">
        <input type="hidden" name="id" value="<?= (int) $bannerValues['id'] ?>">
        <div class="admin-panel-body">
          <div class="field-row">
            <div class="field">
              <label for="banner_title">Title</label>
              <input id="banner_title" type="text" name="title" value="<?= e((string) $bannerValues['title']) ?>" maxlength="190">
            </div>
            <div class="field">
              <label for="banner_subtitle">Subtitle</label>
              <input id="banner_subtitle" type="text" name="subtitle" value="<?= e((string) $bannerValues['subtitle']) ?>" maxlength="255">
            </div>
          </div>
          <div class="field-row">
            <div class="field">
              <label for="placement">Placement</label>
              <select id="placement" name="placement">
                <?php foreach ($placementLabels as $key => $label): ?>
                  <option value="<?= e($key) ?>" <?= $bannerValues['placement'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="link_url">Link</label>
              <input id="link_url" type="text" name="link_url" value="<?= e((string) $bannerValues['link_url']) ?>" maxlength="255" placeholder="shop.php or https://…">
            </div>
          </div>
          <div class="field">
            <label for="banner_image">Image</label>
            <input id="banner_image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
            <?php if (!empty($bannerValues['image'])): ?><p class="field-hint"><img src="<?= e(asset_url((string) $bannerValues['image'])) ?>" alt="" style="max-width:220px;border-radius:8px"> Uploading a new image replaces this one.</p><?php endif; ?>
          </div>
          <div class="field-row">
            <div class="field">
              <label for="starts_at">Starts at</label>
              <input id="starts_at" type="datetime-local" name="starts_at" value="<?= e($dateInput($bannerValues['starts_at'] !== null ? (string) $bannerValues['starts_at'] : null)) ?>">
            </div>
            <div class="field">
              <label for="ends_at">Ends at</label>
              <input id="ends_at" type="datetime-local" name="ends_at" value="<?= e($dateInput($bannerValues['ends_at'] !== null ? (string) $bannerValues['ends_at'] : null)) ?>">
            </div>
          </div>
          <div class="field-row">
            <div class="field">
              <label for="banner_sort">Sort order</label>
              <input id="banner_sort" type="number" name="sort_order" value="<?= (int) $bannerValues['sort_order'] ?>" step="1">
            </div>
            <div class="field">
              <label for="banner_status">Status</label>
              <select id="banner_status" name="status">
                <option value="inactive" <?= $bannerValues['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="active" <?= $bannerValues['status'] === 'active' ? 'selected' : '' ?>>Active</option>
              </select>
            </div>
          </div>
        </div>
        <div class="admin-form-actions">
          <button class="btn btn-primary" type="submit"><?= (int) $bannerValues['id'] > 0 ? 'Save banner' : 'Create banner' ?></button>
          <a class="btn btn-ghost" href="<?= e($tabUrl('banners')) ?>">Cancel</a>
        </div>
      </form>
    </section>
  <?php endif; ?>

  <div class="admin-panel">
    <div class="admin-toolbar">
      <span class="inline-note">Banner slots must be rendered by the theme where configured. Unverified promotional claims should not be published.</span>
      <span class="spacer"></span>
      <a class="btn btn-primary btn-sm" href="<?= e($tabUrl('banners', ['new' => 1])) ?>"><?= icon('plus') ?> New banner</a>
    </div>
    <div class="table-scroll">
      <table class="admin-table">
        <thead><tr><th>Banner</th><th>Placement</th><th>Schedule</th><th class="num">Order</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php if ($banners === []): ?><tr><td colspan="6" class="muted">No banners yet.</td></tr><?php endif; ?>
          <?php foreach ($banners as $banner): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <img class="thumb" src="<?= e(asset_url($banner['image'])) ?>" alt="" width="56" height="36">
                  <span><strong class="cell-title"><?= e($banner['title'] ?? 'Untitled banner') ?></strong><span class="cell-sub"><?= e($banner['subtitle'] ?? '') ?></span></span>
                </div>
              </td>
              <td><?= e($placementLabels[$banner['placement']] ?? $banner['placement']) ?></td>
              <td class="cell-sub"><?= $banner['starts_at'] !== null ? 'From ' . e(date_display($banner['starts_at'])) : 'No start' ?><br><?= $banner['ends_at'] !== null ? 'Until ' . e(date_display($banner['ends_at'])) : 'No end' ?></td>
              <td class="num"><?= (int) $banner['sort_order'] ?></td>
              <td><span class="<?= e(status_class((string) $banner['status'])) ?>"><?= e(ucfirst((string) $banner['status'])) ?></span></td>
              <td>
                <div class="row-actions">
                  <a href="<?= e($tabUrl('banners', ['edit' => (int) $banner['id']])) ?>">Edit</a>
                  <form method="post" action="<?= e(admin_url('content.php?tab=banners')) ?>" data-confirm="Delete this banner?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="banner_delete">
                    <input type="hidden" name="id" value="<?= (int) $banner['id'] ?>">
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
<?php endif; ?>

<?php if ($tab === 'team'): ?>
  <?php if ($showMemberForm): ?>
    <section class="admin-panel">
      <div class="admin-panel-head">
        <h2><?= (int) $memberValues['id'] > 0 ? 'Edit team member' : 'Add team member' ?></h2>
        <a class="small" href="<?= e($tabUrl('team')) ?>">Close</a>
      </div>
      <?php if ($errors !== []): ?><div class="admin-panel-body"><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div></div><?php endif; ?>
      <form class="admin-form" method="post" action="<?= e(admin_url('content.php?tab=team')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="team_save">
        <input type="hidden" name="id" value="<?= (int) $memberValues['id'] ?>">
        <div class="admin-panel-body">
          <div class="field-row">
            <div class="field">
              <label for="member_name">Name <span class="req">*</span></label>
              <input id="member_name" type="text" name="name" value="<?= e((string) $memberValues['name']) ?>" maxlength="120" required>
            </div>
            <div class="field">
              <label for="member_position">Position <span class="req">*</span></label>
              <input id="member_position" type="text" name="position" value="<?= e((string) $memberValues['position']) ?>" maxlength="120" required>
            </div>
          </div>
          <div class="field">
            <label for="member_bio">Bio</label>
            <textarea id="member_bio" name="bio" rows="4" maxlength="2000"><?= e((string) $memberValues['bio']) ?></textarea>
          </div>
          <div class="field-row">
            <div class="field">
              <label for="member_image">Photo</label>
              <input id="member_image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
              <?php if (!empty($memberValues['image'])): ?><p class="field-hint"><img src="<?= e(asset_url((string) $memberValues['image'])) ?>" alt="" style="max-width:80px;border-radius:8px"> Uploading a new photo replaces this one.</p><?php endif; ?>
            </div>
            <div class="field">
              <label for="member_sort">Sort order</label>
              <input id="member_sort" type="number" name="sort_order" value="<?= (int) $memberValues['sort_order'] ?>" step="1">
            </div>
          </div>
          <div class="field" style="max-width:260px">
            <label for="member_status">Status</label>
            <select id="member_status" name="status">
              <option value="active" <?= $memberValues['status'] === 'active' ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= $memberValues['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>
        </div>
        <div class="admin-form-actions">
          <button class="btn btn-primary" type="submit"><?= (int) $memberValues['id'] > 0 ? 'Save team member' : 'Add team member' ?></button>
          <a class="btn btn-ghost" href="<?= e($tabUrl('team')) ?>">Cancel</a>
        </div>
      </form>
    </section>
  <?php endif; ?>

  <div class="admin-panel">
    <div class="admin-toolbar">
      <span class="inline-note">Only add photos and biographies supplied by the individuals.</span>
      <span class="spacer"></span>
      <a class="btn btn-primary btn-sm" href="<?= e($tabUrl('team', ['new' => 1])) ?>"><?= icon('plus') ?> Add team member</a>
    </div>
    <div class="table-scroll">
      <table class="admin-table">
        <thead><tr><th>Member</th><th>Position</th><th class="num">Order</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php if ($team === []): ?><tr><td colspan="5" class="muted">No team members yet.</td></tr><?php endif; ?>
          <?php foreach ($team as $member): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <img class="thumb" src="<?= e(asset_url($member['image'])) ?>" alt="" width="40" height="40" style="border-radius:50%">
                  <strong class="cell-title"><?= e($member['name']) ?></strong>
                </div>
              </td>
              <td><?= e($member['position']) ?></td>
              <td class="num"><?= (int) $member['sort_order'] ?></td>
              <td><span class="<?= e(status_class((string) $member['status'])) ?>"><?= e(ucfirst((string) $member['status'])) ?></span></td>
              <td>
                <div class="row-actions">
                  <a href="<?= e($tabUrl('team', ['edit' => (int) $member['id']])) ?>">Edit</a>
                  <form method="post" action="<?= e(admin_url('content.php?tab=team')) ?>" data-confirm="Remove this team member?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="team_delete">
                    <input type="hidden" name="id" value="<?= (int) $member['id'] ?>">
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
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
