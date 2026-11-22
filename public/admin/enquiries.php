<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin('enquiries.manage');

$enquiryStatuses = ['new', 'contacted', 'quotation_sent', 'negotiation', 'converted', 'closed'];

if (is_post()) {
    verify_csrf();
    if (post_str('action') === 'update') {
        $enquiryId = post_int('enquiry_id');
        $enquiry = Database::one('SELECT * FROM enquiries WHERE id = ?', [$enquiryId]);
        if ($enquiry === null) {
            flash('error', 'That enquiry could not be found.');
            redirect('admin/enquiries.php');
        }
        $newStatus = post_str('status');
        $notes = trim((string) ($_POST['admin_notes'] ?? ''));
        if (!in_array($newStatus, $enquiryStatuses, true)) {
            flash('error', 'Please choose a valid enquiry status.');
        } elseif (mb_strlen($notes) > 5000) {
            flash('error', 'Admin notes must be 5,000 characters or fewer.');
        } else {
            Database::update('enquiries', [
                'status' => $newStatus,
                'admin_notes' => $notes !== '' ? $notes : null,
            ], 'id = ?', [$enquiryId]);
            Notifications::audit('enquiry.updated', 'enquiry', (string) $enquiryId, [
                'status' => $newStatus,
                'previous' => $enquiry['status'],
            ]);
            flash('success', 'Enquiry ' . $enquiry['reference'] . ' updated.');
        }
        redirect('admin/enquiries.php?view=' . $enquiryId);
    }
    redirect('admin/enquiries.php');
}

$typeFilter = (string) ($_GET['type'] ?? '');
if (!in_array($typeFilter, ['wholesale', 'export', 'contact'], true)) {
    $typeFilter = '';
}
$statusFilter = (string) ($_GET['status'] ?? '');
if (!in_array($statusFilter, $enquiryStatuses, true)) {
    $statusFilter = '';
}
$q = trim((string) ($_GET['q'] ?? ''));
$pageNo = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$viewId = max(0, (int) ($_GET['view'] ?? 0));

$where = [];
$params = [];
if ($typeFilter !== '') {
    $where[] = 'type = ?';
    $params[] = $typeFilter;
}
if ($statusFilter !== '') {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
}
if ($q !== '') {
    $where[] = '(reference LIKE ? OR name LIKE ? OR email LIKE ? OR company LIKE ? OR phone LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
$total = (int) Database::value('SELECT COUNT(*) FROM enquiries' . $whereSql, $params);
$pages = max(1, (int) ceil($total / $perPage));
$pageNo = min($pageNo, $pages);
$offset = ($pageNo - 1) * $perPage;
$enquiries = Database::all(
    'SELECT * FROM enquiries' . $whereSql . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
    $params
);
$newCount = (int) Database::value('SELECT COUNT(*) FROM enquiries WHERE status = "new"');
$filterUrl = static function (array $overrides = []) use ($typeFilter, $statusFilter, $q): string {
    $query = array_filter(array_merge(['type' => $typeFilter, 'status' => $statusFilter, 'q' => $q], $overrides), static fn ($value): bool => $value !== '' && $value !== null);
    return admin_url('enquiries.php' . ($query !== [] ? '?' . http_build_query($query) : ''));
};

$viewEnquiry = null;
$viewItems = [];
if ($viewId > 0) {
    $viewEnquiry = Database::one('SELECT * FROM enquiries WHERE id = ?', [$viewId]);
    if ($viewEnquiry !== null) {
        $viewItems = Database::all('SELECT * FROM enquiry_items WHERE enquiry_id = ? ORDER BY id ASC', [$viewId]);
    }
}
$typeLabels = ['wholesale' => 'Wholesale', 'export' => 'Export', 'contact' => 'Contact'];

$adminTitle = 'Enquiries';
$adminPage = 'enquiries';
require __DIR__ . '/inc/header.php';
?>

<div class="admin-page-head">
  <div>
    <h1>Enquiries</h1>
    <p>Wholesale, export and business enquiries. Move each enquiry through the pipeline as your team responds.</p>
  </div>
  <?php if ($newCount > 0): ?><div class="admin-page-actions"><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('enquiries.php?status=new')) ?>"><?= icon('alert') ?> <?= $newCount ?> new</a></div><?php endif; ?>
</div>

<?php if ($viewEnquiry !== null): ?>
  <section class="admin-panel">
    <div class="admin-panel-head">
      <h2><?= e($viewEnquiry['reference']) ?> · <?= e($typeLabels[$viewEnquiry['type']] ?? ucfirst((string) $viewEnquiry['type'])) ?></h2>
      <a class="small" href="<?= e(admin_url('enquiries.php')) ?>">Close</a>
    </div>
    <div class="admin-panel-body">
      <div class="admin-grid-2">
        <div>
          <p><span class="<?= e(status_class((string) $viewEnquiry['status'])) ?>"><?= e(enquiry_status_label((string) $viewEnquiry['status'])) ?></span></p>
          <p><strong>Name</strong><br><?= e($viewEnquiry['name']) ?><?= $viewEnquiry['company'] !== null && $viewEnquiry['company'] !== '' ? ' · ' . e($viewEnquiry['company']) : '' ?></p>
          <p><strong>Email</strong><br><a href="mailto:<?= e($viewEnquiry['email']) ?>?subject=<?= e(rawurlencode('Re: your enquiry ' . $viewEnquiry['reference'])) ?>"><?= e($viewEnquiry['email']) ?></a></p>
          <p><strong>Phone</strong><br><?= e($viewEnquiry['phone']) ?></p>
          <p><strong>Location</strong><br><?= e(trim(($viewEnquiry['city'] ?? '') . ', ' . $viewEnquiry['country'], ', ')) ?></p>
          <p><strong>Buyer type</strong><br><?= e(ucfirst((string) $viewEnquiry['buyer_type'])) ?></p>
          <p class="cell-sub">Received <?= e(date_display($viewEnquiry['created_at'])) ?></p>
        </div>
        <div>
          <p><strong>Product interest</strong><br><?= e($viewEnquiry['product_interest'] ?? '—') ?></p>
          <p><strong>Quantity</strong><br><?= e($viewEnquiry['quantity'] ?? '—') ?></p>
          <p><strong>Packaging</strong><br><?= e($viewEnquiry['packaging'] ?? '—') ?></p>
          <?php if ($viewEnquiry['destination_port'] !== null && $viewEnquiry['destination_port'] !== ''): ?>
            <p><strong>Destination port</strong><br><?= e($viewEnquiry['destination_port']) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($viewEnquiry['message'] !== null && trim((string) $viewEnquiry['message']) !== ''): ?>
        <div class="inline-note" style="margin-top:12px;white-space:pre-line"><?= e((string) $viewEnquiry['message']) ?></div>
      <?php endif; ?>
      <?php if ($viewItems !== []): ?>
        <h3 style="margin-top:22px">Items</h3>
        <div class="table-scroll">
          <table class="admin-table">
            <thead><tr><th>Product</th><th>Quantity</th><th>Packaging</th><th>Notes</th></tr></thead>
            <tbody>
              <?php foreach ($viewItems as $item): ?>
                <tr><td><?= e($item['product_name']) ?></td><td><?= e($item['quantity'] ?? '—') ?></td><td><?= e($item['packaging'] ?? '—') ?></td><td><?= e($item['notes'] ?? '—') ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <form class="admin-form" method="post" action="<?= e(admin_url('enquiries.php')) ?>" style="margin-top:20px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="enquiry_id" value="<?= (int) $viewEnquiry['id'] ?>">
        <div class="field-row">
          <div class="field">
            <label for="status">Pipeline status</label>
            <select id="status" name="status">
              <?php foreach ($enquiryStatuses as $statusOption): ?>
                <option value="<?= e($statusOption) ?>" <?= $viewEnquiry['status'] === $statusOption ? 'selected' : '' ?>><?= e(enquiry_status_label($statusOption)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field">
          <label for="admin_notes">Internal notes</label>
          <textarea id="admin_notes" name="admin_notes" rows="5" maxlength="5000" placeholder="Quotation sent on…, follow-up scheduled…"><?= e((string) ($viewEnquiry['admin_notes'] ?? '')) ?></textarea>
          <p class="field-hint">Visible only to admins.</p>
        </div>
        <div class="admin-form-actions">
          <button class="btn btn-primary" type="submit">Save enquiry</button>
        </div>
      </form>
    </div>
  </section>
<?php endif; ?>

<div class="admin-panel">
  <div class="admin-toolbar">
    <form method="get" action="<?= e(admin_url('enquiries.php')) ?>">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Reference, name, email" aria-label="Search enquiries">
      <select name="type" aria-label="Filter by type">
        <option value="">All types</option>
        <?php foreach ($typeLabels as $key => $label): ?><option value="<?= e($key) ?>" <?= $typeFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
      <select name="status" aria-label="Filter by status">
        <option value="">All statuses</option>
        <?php foreach ($enquiryStatuses as $statusOption): ?><option value="<?= e($statusOption) ?>" <?= $statusFilter === $statusOption ? 'selected' : '' ?>><?= e(enquiry_status_label($statusOption)) ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-primary btn-sm" type="submit">Filter</button>
      <?php if ($typeFilter !== '' || $statusFilter !== '' || $q !== ''): ?><a class="small" href="<?= e(admin_url('enquiries.php')) ?>">Clear</a><?php endif; ?>
    </form>
    <span class="spacer"></span>
    <span class="inline-note"><?= $total ?> enquir<?= $total === 1 ? 'y' : 'ies' ?></span>
  </div>
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>Reference</th><th>Contact</th><th>Type</th><th>Interest</th><th>Status</th><th>Received</th><th></th></tr></thead>
      <tbody>
        <?php if ($enquiries === []): ?><tr><td colspan="7" class="muted">No enquiries match these filters.</td></tr><?php endif; ?>
        <?php foreach ($enquiries as $enquiry): ?>
          <tr>
            <td><strong class="cell-title"><?= e($enquiry['reference']) ?></strong></td>
            <td><strong><?= e($enquiry['name']) ?></strong><span class="cell-sub"><?= e($enquiry['email']) ?><?= $enquiry['company'] !== null && $enquiry['company'] !== '' ? ' · ' . e($enquiry['company']) : '' ?></span></td>
            <td><?= e($typeLabels[$enquiry['type']] ?? ucfirst((string) $enquiry['type'])) ?></td>
            <td class="cell-sub"><?= e($enquiry['product_interest'] ?? '—') ?><br><?= e($enquiry['quantity'] ?? '') ?></td>
            <td><span class="<?= e(status_class((string) $enquiry['status'])) ?>"><?= e(enquiry_status_label((string) $enquiry['status'])) ?></span></td>
            <td class="cell-sub"><?= e(date_display($enquiry['created_at'])) ?></td>
            <td><div class="row-actions"><a href="<?= e(admin_url('enquiries.php?view=' . (int) $enquiry['id'])) ?>">Open</a></div></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Enquiry pages">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === 1 || $i === $pages || abs($i - $pageNo) <= 2): ?>
          <?php if ($i === $pageNo): ?><span class="is-current"><?= $i ?></span><?php else: ?><a href="<?= e($filterUrl(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
        <?php endif; ?>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
