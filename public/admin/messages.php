<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin('enquiries.manage');

$messageStatuses = ['new', 'read', 'replied', 'closed'];

if (is_post()) {
    verify_csrf();
    if (post_str('action') === 'update') {
        $messageId = post_int('message_id');
        $newStatus = post_str('status');
        $message = Database::one('SELECT * FROM contact_messages WHERE id = ?', [$messageId]);
        if ($message === null) {
            flash('error', 'That message could not be found.');
        } elseif (!in_array($newStatus, $messageStatuses, true)) {
            flash('error', 'Please choose a valid message status.');
        } else {
            Database::update('contact_messages', ['status' => $newStatus], 'id = ?', [$messageId]);
            Notifications::audit('contact_message.status_updated', 'contact_message', (string) $messageId, ['status' => $newStatus]);
            flash('success', 'Message marked as ' . $newStatus . '.');
        }
        redirect('admin/messages.php' . ($message !== null ? '?view=' . $messageId : ''));
    }
    redirect('admin/messages.php');
}

$statusFilter = (string) ($_GET['status'] ?? '');
if (!in_array($statusFilter, $messageStatuses, true)) {
    $statusFilter = '';
}
$pageNo = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$viewId = max(0, (int) ($_GET['view'] ?? 0));

$where = [];
$params = [];
if ($statusFilter !== '') {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
$total = (int) Database::value('SELECT COUNT(*) FROM contact_messages' . $whereSql, $params);
$pages = max(1, (int) ceil($total / $perPage));
$pageNo = min($pageNo, $pages);
$offset = ($pageNo - 1) * $perPage;
$messages = Database::all('SELECT * FROM contact_messages' . $whereSql . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);
$newCount = (int) Database::value('SELECT COUNT(*) FROM contact_messages WHERE status = "new"');
$counts = [];
foreach ($messageStatuses as $statusOption) {
    $counts[$statusOption] = (int) Database::value('SELECT COUNT(*) FROM contact_messages WHERE status = ?', [$statusOption]);
}

$viewMessage = null;
if ($viewId > 0) {
    $viewMessage = Database::one('SELECT * FROM contact_messages WHERE id = ?', [$viewId]);
    if ($viewMessage !== null && $viewMessage['status'] === 'new') {
        Database::update('contact_messages', ['status' => 'read'], 'id = ?', [$viewId]);
        $viewMessage['status'] = 'read';
    }
}
$filterUrl = static function (array $overrides = []) use ($statusFilter): string {
    $query = array_filter(array_merge(['status' => $statusFilter], $overrides), static fn ($value): bool => $value !== '' && $value !== null);
    return admin_url('messages.php' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
$statusLabels = ['new' => 'New', 'read' => 'Read', 'replied' => 'Replied', 'closed' => 'Closed'];

$adminTitle = 'Messages';
$adminPage = 'messages';
require __DIR__ . '/inc/header.php';
?>

<div class="admin-page-head">
  <div>
    <h1>Messages</h1>
    <p>Messages sent through the website contact form. Reply by email, then record the status here.</p>
  </div>
  <?php if ($newCount > 0): ?><div class="admin-page-actions"><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('messages.php?status=new')) ?>"><?= icon('alert') ?> <?= $newCount ?> unread</a></div><?php endif; ?>
</div>

<?php if ($viewMessage !== null): ?>
  <section class="admin-panel">
    <div class="admin-panel-head">
      <h2><?= e($viewMessage['subject']) ?></h2>
      <a class="small" href="<?= e(admin_url('messages.php')) ?>">Close</a>
    </div>
    <div class="admin-panel-body">
      <p><span class="<?= e(status_class((string) $viewMessage['status'])) ?>"><?= e(ucfirst((string) $viewMessage['status'])) ?></span> <span class="cell-sub"><?= e(date_display($viewMessage['created_at'])) ?></span></p>
      <p><strong><?= e($viewMessage['name']) ?></strong><br>
        <a href="mailto:<?= e($viewMessage['email']) ?>?subject=<?= e(rawurlencode('Re: ' . $viewMessage['subject'])) ?>"><?= e($viewMessage['email']) ?></a>
        <?= $viewMessage['phone'] !== null && $viewMessage['phone'] !== '' ? ' · <a href="tel:' . e($viewMessage['phone']) . '">' . e($viewMessage['phone']) . '</a>' : '' ?>
      </p>
      <div class="inline-note" style="white-space:pre-line;margin:16px 0"><?= e((string) $viewMessage['message']) ?></div>
      <form class="admin-form" method="post" action="<?= e(admin_url('messages.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="message_id" value="<?= (int) $viewMessage['id'] ?>">
        <div class="field" style="max-width:280px">
          <label for="status">Mark as</label>
          <select id="status" name="status">
            <?php foreach ($messageStatuses as $statusOption): ?>
              <option value="<?= e($statusOption) ?>" <?= $viewMessage['status'] === $statusOption ? 'selected' : '' ?>><?= e($statusLabels[$statusOption]) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="admin-form-actions">
          <a class="btn btn-ghost" href="mailto:<?= e($viewMessage['email']) ?>?subject=<?= e(rawurlencode('Re: ' . $viewMessage['subject'])) ?>">Reply by email</a>
          <button class="btn btn-primary" type="submit">Save status</button>
        </div>
      </form>
    </div>
  </section>
<?php endif; ?>

<div class="admin-panel">
  <div class="admin-toolbar">
    <div class="admin-checks">
      <a class="pill <?= $statusFilter === '' ? 'pill-green' : 'pill-grey' ?>" href="<?= e(admin_url('messages.php')) ?>">All (<?= array_sum($counts) ?>)</a>
      <?php foreach ($messageStatuses as $statusOption): ?>
        <a class="pill <?= $statusFilter === $statusOption ? 'pill-green' : 'pill-grey' ?>" href="<?= e(admin_url('messages.php?status=' . $statusOption)) ?>"><?= e($statusLabels[$statusOption]) ?> (<?= (int) $counts[$statusOption] ?>)</a>
      <?php endforeach; ?>
    </div>
    <span class="spacer"></span>
    <span class="inline-note"><?= $total ?> message<?= $total === 1 ? '' : 's' ?></span>
  </div>
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>From</th><th>Subject</th><th>Status</th><th>Received</th><th></th></tr></thead>
      <tbody>
        <?php if ($messages === []): ?><tr><td colspan="5" class="muted">No messages in this view.</td></tr><?php endif; ?>
        <?php foreach ($messages as $entry): ?>
          <tr>
            <td><strong class="cell-title"><?= e($entry['name']) ?></strong><span class="cell-sub"><?= e($entry['email']) ?></span></td>
            <td><?= e($entry['subject']) ?><span class="cell-sub"><?= e(excerpt($entry['message'], 80)) ?></span></td>
            <td><span class="<?= e(status_class((string) $entry['status'])) ?>"><?= e(ucfirst((string) $entry['status'])) ?></span></td>
            <td class="cell-sub"><?= e(date_display($entry['created_at'])) ?></td>
            <td><div class="row-actions"><a href="<?= e(admin_url('messages.php?view=' . (int) $entry['id'])) ?>">Open</a></div></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Message pages">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === 1 || $i === $pages || abs($i - $pageNo) <= 2): ?>
          <?php if ($i === $pageNo): ?><span class="is-current"><?= $i ?></span><?php else: ?><a href="<?= e($filterUrl(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
        <?php endif; ?>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
