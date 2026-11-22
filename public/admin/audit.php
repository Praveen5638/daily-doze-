<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin('settings.manage');

$q = trim((string) ($_GET['q'] ?? ''));
$adminFilter = max(0, (int) ($_GET['admin'] ?? 0));
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
$pageNo = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(a.action LIKE ? OR a.entity_type LIKE ? OR a.entity_id LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($adminFilter > 0) {
    $where[] = 'a.admin_id = ?';
    $params[] = $adminFilter;
}
$fromDate = null;
if ($from !== '') {
    $ts = strtotime($from . ' 00:00:00');
    if ($ts !== false) {
        $fromDate = date('Y-m-d H:i:s', $ts);
        $where[] = 'a.created_at >= ?';
        $params[] = $fromDate;
    }
}
$toDate = null;
if ($to !== '') {
    $ts = strtotime($to . ' 23:59:59');
    if ($ts !== false) {
        $toDate = date('Y-m-d H:i:s', $ts);
        $where[] = 'a.created_at <= ?';
        $params[] = $toDate;
    }
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
$total = (int) Database::value('SELECT COUNT(*) FROM audit_logs a' . $whereSql, $params);
$pages = max(1, (int) ceil($total / $perPage));
$pageNo = min($pageNo, $pages);
$offset = ($pageNo - 1) * $perPage;
$logs = Database::all(
    'SELECT a.*, ad.name AS admin_name, ad.email AS admin_email
     FROM audit_logs a LEFT JOIN admins ad ON ad.id = a.admin_id' . $whereSql . '
     ORDER BY a.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
    $params
);
$admins = Database::all('SELECT id, name FROM admins ORDER BY name ASC');
$filterUrl = static function (array $overrides = []) use ($q, $adminFilter, $from, $to): string {
    $query = array_filter(array_merge(['q' => $q, 'admin' => $adminFilter ?: '', 'from' => $from, 'to' => $to], $overrides), static fn ($value): bool => $value !== '' && $value !== null);
    return admin_url('audit.php' . ($query !== [] ? '?' . http_build_query($query) : ''));
};

$adminTitle = 'Audit log';
$adminPage = 'audit';
require __DIR__ . '/inc/header.php';
?>

<div class="admin-page-head">
  <div>
    <h1>Audit log</h1>
    <p>A read-only record of staff actions. Entries are retained for accountability and incident review.</p>
  </div>
</div>

<div class="admin-panel">
  <div class="admin-toolbar">
    <form method="get" action="<?= e(admin_url('audit.php')) ?>">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Action, entity or ID" aria-label="Search audit log">
      <select name="admin" aria-label="Filter by admin">
        <option value="">All staff</option>
        <?php foreach ($admins as $adminRow): ?>
          <option value="<?= (int) $adminRow['id'] ?>" <?= $adminFilter === (int) $adminRow['id'] ? 'selected' : '' ?>><?= e($adminRow['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="small">From <input type="date" name="from" value="<?= e($from) ?>" aria-label="From date"></label>
      <label class="small">To <input type="date" name="to" value="<?= e($to) ?>" aria-label="To date"></label>
      <button class="btn btn-primary btn-sm" type="submit">Filter</button>
      <?php if ($q !== '' || $adminFilter > 0 || $from !== '' || $to !== ''): ?><a class="small" href="<?= e(admin_url('audit.php')) ?>">Clear</a><?php endif; ?>
    </form>
    <span class="spacer"></span>
    <span class="inline-note"><?= $total ?> entr<?= $total === 1 ? 'y' : 'ies' ?></span>
  </div>
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>When</th><th>Staff</th><th>Action</th><th>Entity</th><th>IP</th><th>Details</th></tr></thead>
      <tbody>
        <?php if ($logs === []): ?><tr><td colspan="6" class="muted">No audit entries match these filters.</td></tr><?php endif; ?>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td class="cell-sub"><?= e(date_display($log['created_at'], true)) ?></td>
            <td><?= e($log['admin_name'] ?? 'System') ?><span class="cell-sub"><?= e($log['admin_email'] ?? '') ?></span></td>
            <td><code><?= e($log['action']) ?></code></td>
            <td class="cell-sub"><?= e($log['entity_type'] ?? '—') ?><?= $log['entity_id'] !== null ? ' · ' . e($log['entity_id']) : '' ?></td>
            <td class="cell-sub"><?= e($log['ip'] ?? '—') ?></td>
            <td class="cell-sub">
              <?php if ($log['details'] !== null && $log['details'] !== ''): ?>
                <details><summary>View</summary><pre style="white-space:pre-wrap;max-width:420px;font-size:.75rem"><?= e((string) json_encode(json_decode((string) $log['details'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre></details>
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Audit pages">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === 1 || $i === $pages || abs($i - $pageNo) <= 2): ?>
          <?php if ($i === $pageNo): ?><span class="is-current"><?= $i ?></span><?php else: ?><a href="<?= e($filterUrl(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
        <?php endif; ?>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
