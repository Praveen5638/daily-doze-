<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin('users.manage');

$currentAdmin = Auth::admin();
$currentAdminId = (int) ($currentAdmin['id'] ?? 0);
$errors = [];
$editId = max(0, (int) ($_GET['edit'] ?? 0));
$editAdmin = $editId > 0 ? Database::one(
    'SELECT a.*, r.name AS role_name, r.slug AS role_slug FROM admins a JOIN admin_roles r ON r.id = a.role_id WHERE a.id = ?',
    [$editId]
) : null;
$showForm = $editAdmin !== null || isset($_GET['new']);

if (is_post()) {
    verify_csrf();
    $action = post_str('action');

    if ($action === 'save') {
        $targetId = post_int('id');
        $existing = $targetId > 0 ? Database::one('SELECT * FROM admins WHERE id = ?', [$targetId]) : null;
        $name = trim(post_str('name'));
        $email = strtolower(trim(post_str('email')));
        $roleId = post_int('role_id');
        $status = post_str('status');
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors[] = 'Staff name must be between 2 and 120 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (Database::value('SELECT COUNT(*) FROM admins WHERE email = ? AND id <> ?', [$email, $targetId]) > 0) {
            $errors[] = 'That email address is already used by another staff account.';
        }
        $role = Database::one('SELECT * FROM admin_roles WHERE id = ?', [$roleId]);
        if ($role === null) {
            $errors[] = 'Please choose a valid role.';
        }
        if (!in_array($status, ['active', 'disabled'], true)) {
            $status = 'active';
        }
        if ($existing === null) {
            if ($password !== $passwordConfirm) {
                $errors[] = 'The password confirmation does not match.';
            }
            if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,72}$/', $password)) {
                $errors[] = 'Password must be 8–72 characters and include at least one letter and one number.';
            }
        } elseif ($password !== '' || $passwordConfirm !== '') {
            if ($password !== $passwordConfirm) {
                $errors[] = 'The password confirmation does not match.';
            }
            if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,72}$/', $password)) {
                $errors[] = 'Password must be 8–72 characters and include at least one letter and one number.';
            }
        }
        if ($existing !== null && $targetId === $currentAdminId) {
            if ($roleId !== (int) $existing['role_id']) {
                $errors[] = 'You cannot change your own role. Ask another Super Admin.';
            }
            if ($status !== 'active') {
                $errors[] = 'You cannot disable your own account.';
            }
        }
        if ($existing !== null && $role !== null && $existing['status'] === 'active' && ($status === 'disabled' || $roleId !== (int) $existing['role_id'])) {
            if ($existing['role_id'] === 1) {
                $remaining = (int) Database::value('SELECT COUNT(*) FROM admins WHERE role_id = 1 AND status = "active" AND id <> ?', [$targetId]);
                if ($remaining === 0) {
                    $errors[] = 'At least one active Super Admin account must remain.';
                }
            }
        }

        if ($errors === []) {
            $data = [
                'name' => $name,
                'email' => $email,
                'role_id' => $roleId,
                'status' => $status,
            ];
            if ($existing === null) {
                $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                $targetId = Database::insert('admins', $data);
                Notifications::audit('admin.staff_created', 'admin', (string) $targetId, ['email' => $email, 'role_id' => $roleId]);
                flash('success', 'Staff account created. Share the password securely and ask them to change it after signing in.');
            } else {
                if ($password !== '') {
                    $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }
                Database::update('admins', $data, 'id = ?', [$targetId]);
                Notifications::audit('admin.staff_updated', 'admin', (string) $targetId, ['email' => $email, 'role_id' => $roleId, 'status' => $status, 'password_reset' => $password !== '']);
                flash('success', 'Staff account saved.');
            }
            redirect('admin/staff.php');
        }
        $showForm = true;
        $editId = $targetId;
    }

    if ($action === 'change_own_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $newConfirm = (string) ($_POST['new_password_confirm'] ?? '');
        $row = Database::one('SELECT * FROM admins WHERE id = ?', [$currentAdminId]);
        if ($row === null || !password_verify($currentPassword, (string) $row['password_hash'])) {
            $errors[] = 'Your current password is incorrect.';
        } elseif ($newPassword !== $newConfirm) {
            $errors[] = 'The new password confirmation does not match.';
        } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,72}$/', $newPassword)) {
            $errors[] = 'New password must be 8–72 characters and include at least one letter and one number.';
        } elseif ($newPassword === $currentPassword) {
            $errors[] = 'Choose a password different from your current one.';
        }
        if ($errors === []) {
            Database::update('admins', ['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)], 'id = ?', [$currentAdminId]);
            Notifications::audit('admin.password_changed', 'admin', (string) $currentAdminId, ['self' => true]);
            flash('success', 'Your password has been changed.');
            redirect('admin/staff.php');
        }
    }
}

$staff = Database::all(
    'SELECT a.*, r.name AS role_name, r.slug AS role_slug FROM admins a JOIN admin_roles r ON r.id = a.role_id ORDER BY a.id ASC'
);
$roles = Database::all(
    'SELECT r.*, (SELECT COUNT(*) FROM admin_permissions p WHERE p.role_id = r.id) AS permission_count FROM admin_roles r ORDER BY r.id ASC'
);
$formValues = $editAdmin ?? ['id' => 0, 'name' => '', 'email' => '', 'role_id' => 3, 'status' => 'active'];
if (is_post() && post_str('action') === 'save' && $errors !== []) {
    $formValues = array_merge($formValues, [
        'id' => post_int('id'), 'name' => post_str('name'), 'email' => post_str('email'),
        'role_id' => post_int('role_id'), 'status' => post_str('status'),
    ]);
}

$adminTitle = 'Staff & roles';
$adminPage = 'staff';
require __DIR__ . '/inc/header.php';
?>

<div class="admin-page-head">
  <div>
    <h1>Staff &amp; roles</h1>
    <p>Admin accounts and role-based access. Only Super Admins can manage staff. Never share passwords over public channels.</p>
  </div>
  <div class="admin-page-actions">
    <a class="btn btn-primary btn-sm" href="<?= e(admin_url('staff.php?new=1')) ?>"><?= icon('plus') ?> Add staff</a>
  </div>
</div>

<?php if ($errors !== []): ?>
  <div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="admin-grid-2">
  <div>
    <?php if ($showForm): ?>
      <section class="admin-panel">
        <div class="admin-panel-head">
          <h2><?= (int) $formValues['id'] > 0 ? 'Edit staff account' : 'New staff account' ?></h2>
          <a class="small" href="<?= e(admin_url('staff.php')) ?>">Close</a>
        </div>
        <form class="admin-form" method="post" action="<?= e(admin_url('staff.php')) ?>">
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
                <label for="email">Email <span class="req">*</span></label>
                <input id="email" type="email" name="email" value="<?= e((string) $formValues['email']) ?>" maxlength="190" required autocomplete="off">
              </div>
            </div>
            <div class="field-row">
              <div class="field">
                <label for="role_id">Role</label>
                <select id="role_id" name="role_id">
                  <?php foreach ($roles as $role): ?>
                    <option value="<?= (int) $role['id'] ?>" <?= (int) $formValues['role_id'] === (int) $role['id'] ? 'selected' : '' ?>><?= e($role['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                  <option value="active" <?= $formValues['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                  <option value="disabled" <?= $formValues['status'] === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                </select>
              </div>
            </div>
            <div class="field-row">
              <div class="field">
                <label for="password"><?= (int) $formValues['id'] > 0 ? 'New password (optional)' : 'Password' ?> <?= (int) $formValues['id'] === 0 ? '<span class="req">*</span>' : '' ?></label>
                <input id="password" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" <?= (int) $formValues['id'] === 0 ? 'required' : '' ?>>
              </div>
              <div class="field">
                <label for="password_confirm">Confirm password</label>
                <input id="password_confirm" type="password" name="password_confirm" minlength="8" maxlength="72" autocomplete="new-password" <?= (int) $formValues['id'] === 0 ? 'required' : '' ?>>
              </div>
            </div>
            <p class="field-hint">8–72 characters with at least one letter and one number.</p>
          </div>
          <div class="admin-form-actions">
            <button class="btn btn-primary" type="submit"><?= (int) $formValues['id'] > 0 ? 'Save account' : 'Create account' ?></button>
            <a class="btn btn-ghost" href="<?= e(admin_url('staff.php')) ?>">Cancel</a>
          </div>
        </form>
      </section>
    <?php endif; ?>

    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Staff accounts</h2></div>
      <div class="table-scroll">
        <table class="admin-table">
          <thead><tr><th>Name</th><th>Role</th><th>Last login</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($staff as $member): ?>
              <tr>
                <td><strong class="cell-title"><?= e($member['name']) ?><?= (int) $member['id'] === $currentAdminId ? ' (you)' : '' ?></strong><span class="cell-sub"><?= e($member['email']) ?></span></td>
                <td><?= e($member['role_name']) ?></td>
                <td class="cell-sub"><?= e($member['last_login_at'] !== null ? date_display($member['last_login_at'], true) : 'Never') ?></td>
                <td><span class="<?= e(status_class((string) $member['status'])) ?>"><?= e(ucfirst((string) $member['status'])) ?></span></td>
                <td><div class="row-actions"><a href="<?= e(admin_url('staff.php?edit=' . (int) $member['id'])) ?>">Edit</a></div></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <aside>
    <section class="admin-panel">
      <div class="admin-panel-head"><h2>Roles</h2></div>
      <div class="admin-panel-body">
        <?php foreach ($roles as $role): ?>
          <p style="margin-bottom:14px"><strong><?= e($role['name']) ?></strong><br><span class="cell-sub"><?= e($role['description'] ?? '') ?></span><br><span class="cell-sub"><?= (int) $role['permission_count'] ?> permission<?= (int) $role['permission_count'] === 1 ? '' : 's' ?></span></p>
        <?php endforeach; ?>
        <p class="inline-note">Permissions are defined by the seeded role configuration. Ask a developer before changing them.</p>
      </div>
    </section>

    <section class="admin-panel">
      <div class="admin-panel-head"><h2>Your password</h2></div>
      <form class="admin-form" method="post" action="<?= e(admin_url('staff.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_own_password">
        <div class="admin-panel-body">
          <div class="field">
            <label for="current_password">Current password</label>
            <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
          </div>
          <div class="field">
            <label for="new_password">New password</label>
            <input id="new_password" type="password" name="new_password" required minlength="8" maxlength="72" autocomplete="new-password">
          </div>
          <div class="field">
            <label for="new_password_confirm">Confirm new password</label>
            <input id="new_password_confirm" type="password" name="new_password_confirm" required minlength="8" maxlength="72" autocomplete="new-password">
          </div>
        </div>
        <div class="admin-form-actions">
          <button class="btn btn-primary btn-sm" type="submit">Change my password</button>
        </div>
      </form>
    </section>
  </aside>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
