<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin('reviews.manage');

$reviewStatuses = ['pending', 'approved', 'rejected'];

if (is_post()) {
    verify_csrf();
    $action = post_str('action');
    $reviewId = post_int('review_id');
    $review = Database::one('SELECT * FROM reviews WHERE id = ?', [$reviewId]);
    if ($review === null) {
        flash('error', 'That review could not be found.');
        redirect('admin/reviews.php');
    }
    if ($action === 'set_status') {
        $newStatus = post_str('status');
        if (in_array($newStatus, $reviewStatuses, true)) {
            Database::update('reviews', ['status' => $newStatus], 'id = ?', [$reviewId]);
            Notifications::audit('review.status_updated', 'review', (string) $reviewId, ['status' => $newStatus, 'product_id' => $review['product_id']]);
            flash('success', 'Review marked as ' . $newStatus . '.');
        } else {
            flash('error', 'Please choose a valid review status.');
        }
    } elseif ($action === 'toggle_verified') {
        $verified = (int) $review['is_verified'] === 1 ? 0 : 1;
        Database::update('reviews', ['is_verified' => $verified], 'id = ?', [$reviewId]);
        Notifications::audit('review.verified_toggled', 'review', (string) $reviewId, ['is_verified' => $verified]);
        flash('success', 'Verified badge ' . ($verified === 1 ? 'enabled' : 'removed') . '. Only mark a review verified when you have confirmed the purchase.');
    } elseif ($action === 'delete') {
        Database::q('DELETE FROM reviews WHERE id = ?', [$reviewId]);
        Notifications::audit('review.deleted', 'review', (string) $reviewId, ['product_id' => $review['product_id'], 'rating' => (int) $review['rating']]);
        flash('success', 'Review deleted.');
    }
    redirect('admin/reviews.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$statusFilter = (string) ($_GET['status'] ?? '');
if (!in_array($statusFilter, $reviewStatuses, true)) {
    $statusFilter = '';
}
$q = trim((string) ($_GET['q'] ?? ''));
$pageNo = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$where = [];
$params = [];
if ($statusFilter !== '') {
    $where[] = 'r.status = ?';
    $params[] = $statusFilter;
}
if ($q !== '') {
    $where[] = '(r.name LIKE ? OR r.title LIKE ? OR r.body LIKE ? OR p.name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
$total = (int) Database::value('SELECT COUNT(*) FROM reviews r JOIN products p ON p.id = r.product_id' . $whereSql, $params);
$pages = max(1, (int) ceil($total / $perPage));
$pageNo = min($pageNo, $pages);
$offset = ($pageNo - 1) * $perPage;
$reviews = Database::all(
    'SELECT r.*, p.name AS product_name, p.slug AS product_slug, c.first_name AS customer_first_name
     FROM reviews r JOIN products p ON p.id = r.product_id LEFT JOIN customers c ON c.id = r.customer_id' . $whereSql . '
     ORDER BY r.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
    $params
);
$counts = [];
foreach ($reviewStatuses as $statusOption) {
    $counts[$statusOption] = (int) Database::value('SELECT COUNT(*) FROM reviews WHERE status = ?', [$statusOption]);
}
$filterUrl = static function (array $overrides = []) use ($statusFilter, $q): string {
    $query = array_filter(array_merge(['status' => $statusFilter, 'q' => $q], $overrides), static fn ($value): bool => $value !== '' && $value !== null);
    return admin_url('reviews.php' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
$statusLabels = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];

$adminTitle = 'Reviews';
$adminPage = 'reviews';
require __DIR__ . '/inc/header.php';
?>

<div class="admin-page-head">
  <div>
    <h1>Reviews</h1>
    <p>Only approved reviews are shown on product pages. Never mark a review verified unless you have confirmed the purchase.</p>
  </div>
</div>

<div class="admin-panel">
  <div class="admin-toolbar">
    <div class="admin-checks">
      <a class="pill <?= $statusFilter === '' ? 'pill-green' : 'pill-grey' ?>" href="<?= e(admin_url('reviews.php')) ?>">All (<?= array_sum($counts) ?>)</a>
      <?php foreach ($reviewStatuses as $statusOption): ?>
        <a class="pill <?= $statusFilter === $statusOption ? 'pill-green' : 'pill-grey' ?>" href="<?= e(admin_url('reviews.php?status=' . $statusOption)) ?>"><?= e($statusLabels[$statusOption]) ?> (<?= (int) $counts[$statusOption] ?>)</a>
      <?php endforeach; ?>
    </div>
    <span class="spacer"></span>
    <form method="get" action="<?= e(admin_url('reviews.php')) ?>" class="inline-form">
      <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Product or reviewer" aria-label="Search reviews">
      <button class="btn btn-primary btn-sm" type="submit">Search</button>
    </form>
  </div>
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>Product</th><th>Review</th><th class="num">Rating</th><th>Status</th><th>Received</th><th></th></tr></thead>
      <tbody>
        <?php if ($reviews === []): ?><tr><td colspan="6" class="muted">No reviews in this view.</td></tr><?php endif; ?>
        <?php foreach ($reviews as $review): ?>
          <tr>
            <td><a href="<?= e(url('products/' . $review['product_slug'])) ?>" target="_blank" rel="noopener"><strong class="cell-title"><?= e($review['product_name']) ?></strong></a></td>
            <td>
              <strong class="cell-title"><?= e($review['name']) ?><?= (int) $review['is_verified'] === 1 ? ' <span class="pill pill-green">Verified</span>' : '' ?></strong>
              <?php if ($review['title'] !== null && $review['title'] !== ''): ?><span class="cell-sub"><?= e($review['title']) ?></span><?php endif; ?>
              <div class="cell-sub" style="max-width:460px"><?= e($review['body']) ?></div>
              <?php if ($review['email'] !== null): ?><span class="cell-sub"><?= e($review['email']) ?><?= $review['customer_id'] !== null ? ' · registered customer' : '' ?></span><?php endif; ?>
            </td>
            <td class="num"><?= rating_stars((int) $review['rating']) ?></td>
            <td><span class="<?= e(status_class((string) $review['status'])) ?>"><?= e(ucfirst((string) $review['status'])) ?></span></td>
            <td class="cell-sub"><?= e(date_display($review['created_at'])) ?></td>
            <td>
              <div class="row-actions" style="flex-wrap:wrap;max-width:190px">
                <?php if ($review['status'] !== 'approved'): ?>
                  <form method="post" action="<?= e(admin_url('reviews.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''))) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="set_status">
                    <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
                    <input type="hidden" name="status" value="approved">
                    <button class="link-quiet" type="submit">Approve</button>
                  </form>
                <?php endif; ?>
                <?php if ($review['status'] !== 'rejected'): ?>
                  <form method="post" action="<?= e(admin_url('reviews.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''))) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="set_status">
                    <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
                    <input type="hidden" name="status" value="rejected">
                    <button class="link-quiet" type="submit">Reject</button>
                  </form>
                <?php endif; ?>
                <form method="post" action="<?= e(admin_url('reviews.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''))) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle_verified">
                  <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
                  <button class="link-quiet" type="submit"><?= (int) $review['is_verified'] === 1 ? 'Unverify' : 'Verify' ?></button>
                </form>
                <form method="post" action="<?= e(admin_url('reviews.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''))) ?>" data-confirm="Delete this review permanently?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
                  <button class="link-danger" type="submit">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Review pages">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === 1 || $i === $pages || abs($i - $pageNo) <= 2): ?>
          <?php if ($i === $pageNo): ?><span class="is-current"><?= $i ?></span><?php else: ?><a href="<?= e($filterUrl(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
        <?php endif; ?>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
