<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin('dashboard.view');

if (is_post()) {
    verify_csrf();
    $action = post_str('action');
    if ($action === 'mark_all') {
        Notifications::markAllRead();
        flash('success', 'All notifications marked as read.');
    } elseif ($action === 'toggle') {
        $notificationId = post_int('id');
        $notification = Database::one('SELECT id, is_read FROM notifications WHERE id = ?', [$notificationId]);
        if ($notification !== null) {
            Database::update('notifications', ['is_read' => (int) $notification['is_read'] === 1 ? 0 : 1], 'id = ?', [$notificationId]);
        }
    } elseif ($action === 'delete') {
        Database::q('DELETE FROM notifications WHERE id = ?', [post_int('id')]);
        flash('success', 'Notification removed.');
    }
    redirect('admin/notifications.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$unreadOnly = isset($_GET['unread']) && $_GET['unread'] === '1';
$pageNo = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$whereSql = $unreadOnly ? ' WHERE is_read = 0' : '';
$total = (int) Database::value('SELECT COUNT(*) FROM notifications' . $whereSql);
$pages = max(1, (int) ceil($total / $perPage));
$pageNo = min($pageNo, $pages);
$offset = ($pageNo - 1) * $perPage;
$notifications = Database::all('SELECT * FROM notifications' . $whereSql . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset);
$unreadCount = Notifications::unreadCount();

$adminTitle = 'Notifications';
$adminPage = 'notifications';
require __DIR__ . '/inc/header.php';
?>

<div class="admin-page-head">
  <div>
    <h1>Notifications</h1>
    <p>New orders, enquiries, messages and pending reviews appear here for the team.</p>
  </div>
  <div class="admin-page-actions">
    <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('notifications.php' . ($unreadOnly ? '' : '?unread=1'))) ?>"><?= $unreadOnly ? 'Show all' : 'Unread only' ?></a>
    <?php if ($unreadCount > 0): ?>
      <form method="post" action="<?= e(admin_url('notifications.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mark_all">
        <button class="btn btn-primary btn-sm" type="submit">Mark all read</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="admin-panel">
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>Notification</th><th>Received</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if ($notifications === []): ?><tr><td colspan="4" class="muted">No notifications<?= $unreadOnly ? ' unread' : '' ?>.</td></tr><?php endif; ?>
        <?php foreach ($notifications as $note): ?>
          <tr>
            <td>
              <strong class="cell-title"><?= e($note['title']) ?></strong>
              <?php if ($note['message'] !== null): ?><span class="cell-sub"><?= e($note['message']) ?></span><?php endif; ?>
              <?php if ($note['link'] !== null): ?><a class="small" href="<?= e(admin_url($note['link'])) ?>">Open</a><?php endif; ?>
            </td>
            <td class="cell-sub"><?= e(date_display($note['created_at'], true)) ?></td>
            <td><span class="pill <?= (int) $note['is_read'] === 1 ? 'pill-grey' : 'pill-gold' ?>"><?= (int) $note['is_read'] === 1 ? 'Read' : 'New' ?></span></td>
            <td>
              <div class="row-actions">
                <form method="post" action="<?= e(admin_url('notifications.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''))) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $note['id'] ?>">
                  <button class="link-quiet" type="submit"><?= (int) $note['is_read'] === 1 ? 'Mark unread' : 'Mark read' ?></button>
                </form>
                <form method="post" action="<?= e(admin_url('notifications.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''))) ?>" data-confirm="Remove this notification?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $note['id'] ?>">
                  <button class="link-danger" type="submit">Remove</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Notification pages">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === 1 || $i === $pages || abs($i - $pageNo) <= 2): ?>
          <?php if ($i === $pageNo): ?><span class="is-current"><?= $i ?></span><?php else: ?><a href="<?= e(admin_url('notifications.php?' . http_build_query(array_filter(['unread' => $unreadOnly ? '1' : '', 'page' => $i])))) ?>"><?= $i ?></a><?php endif; ?>
        <?php endif; ?>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
