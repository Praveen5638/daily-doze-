<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin('orders.manage');

$orderId = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));
$order = $orderId > 0 ? Order::find($orderId) : null;
if ($order === null) {
    flash('error', 'That order could not be found.');
    redirect('admin/orders.php');
}

$validStatuses = ['pending', 'confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery', 'delivered', 'cancelled', 'return_requested', 'returned', 'refunded'];

if (is_post()) {
    verify_csrf();
    $action = post_str('action');

    if ($action === 'update_status') {
        $newStatus = post_str('status');
        $trackingNumber = mb_substr(trim(post_str('tracking_number')), 0, 120);
        $trackingUrl = mb_substr(trim(post_str('tracking_url')), 0, 255);
        $adminNotes = trim(post_str('admin_notes'));
        if (!in_array($newStatus, $validStatuses, true)) {
            flash('error', 'Please choose a valid order status.');
        } elseif ($trackingUrl !== '' && !is_safe_url($trackingUrl)) {
            flash('error', 'The tracking link must be a normal http(s) or site link.');
        } else {
            $result = Order::update_status($orderId, $newStatus, [
                'tracking_number' => $trackingNumber,
                'tracking_url' => $trackingUrl,
                'admin_notes' => $adminNotes,
            ]);
            if ($result['ok']) {
                Notifications::audit('order.status_updated', 'order', (string) $orderId, ['status' => $newStatus]);
                flash('success', $result['message']);
            } else {
                flash('error', $result['message']);
            }
        }
        redirect('admin/order-view.php?id=' . $orderId);
    }

    if ($action === 'save_notes') {
        Database::update('orders', ['admin_notes' => trim(post_str('admin_notes')) ?: null], 'id = ?', [$orderId]);
        Notifications::audit('order.notes_updated', 'order', (string) $orderId);
        flash('success', 'Internal notes saved.');
        redirect('admin/order-view.php?id=' . $orderId);
    }

    if ($action === 'mark_cod_paid') {
        if ($order['payment_method'] !== 'cod') {
            flash('error', 'Only cash-on-delivery orders can be marked paid here.');
        } elseif ($order['payment_status'] === 'paid') {
            flash('success', 'This order is already marked paid.');
        } elseif (in_array($order['status'], ['cancelled', 'returned', 'refunded'], true)) {
            flash('error', 'Payment cannot be recorded on a cancelled or refunded order.');
        } else {
            $pdo = Database::pdo();
            try {
                $pdo->beginTransaction();
                Database::update('orders', ['payment_status' => 'paid', 'payment_reference' => 'COD-' . $order['order_number']], 'id = ?', [$orderId]);
                Database::insert('payments', [
                    'order_id' => $orderId,
                    'gateway' => 'cod',
                    'gateway_payment_id' => 'COD-' . $order['order_number'],
                    'amount' => (float) $order['total'],
                    'currency' => 'INR',
                    'status' => 'captured',
                    'signature_verified' => 0,
                    'payload' => json_encode(['recorded_by_admin' => (int) Auth::admin()['id'], 'at' => date('c')]),
                ]);
                $pdo->commit();
                Notifications::audit('order.cod_payment_recorded', 'order', (string) $orderId, ['amount' => (float) $order['total']]);
                $fresh = Order::find($orderId);
                if ($fresh !== null) {
                    Order::sendOrderEmail($fresh, 'paid');
                }
                flash('success', 'Cash-on-delivery payment recorded as received.');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                log_message('error', 'COD payment recording failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
                flash('error', 'Could not record the COD payment.');
            }
        }
        redirect('admin/order-view.php?id=' . $orderId);
    }
}

$order = Order::find($orderId);
$auditTrail = Database::all(
    "SELECT a.*, ad.name AS admin_name FROM audit_logs a LEFT JOIN admins ad ON ad.id = a.admin_id
     WHERE a.entity_type = 'order' AND a.entity_id = ? ORDER BY a.id DESC LIMIT 12",
    [(string) $orderId]
);
$status = (string) $order['status'];
$isCancelled = in_array($status, ['cancelled', 'returned', 'refunded'], true);

$adminTitle = 'Order ' . $order['order_number'];
$adminPage = 'orders';
require __DIR__ . '/inc/header.php';
?>

<div class="admin-page-head">
  <div>
    <h1>Order <?= e($order['order_number']) ?></h1>
    <p>Placed <?= e(date_display($order['created_at'], true)) ?> · <?= e($order['payment_method'] === 'cod' ? 'Cash on delivery' : 'Online payment') ?> · <?= e(payment_status_label((string) $order['payment_status'])) ?></p>
  </div>
  <div class="admin-page-actions">
    <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('orders.php')) ?>"><?= icon('arrow-left') ?> All orders</a>
    <button class="btn btn-ghost btn-sm" type="button" data-print><?= icon('download') ?> Print invoice</button>
    <span class="<?= e(status_class($status)) ?>"><?= e(order_status_label($status)) ?></span>
  </div>
</div>

<div class="admin-grid-2">
  <div>
    <section class="admin-panel">
      <div class="admin-panel-head">
        <h2>Items</h2>
        <span class="inline-note"><?= count($order['items']) ?> line item(s)</span>
      </div>
      <div class="table-scroll">
        <table class="admin-table">
          <thead><tr><th>Product</th><th>Variant</th><th>SKU</th><th class="num">Price</th><th class="num">Qty</th><th class="num">Line total</th></tr></thead>
          <tbody>
            <?php foreach ($order['items'] as $item): ?>
              <tr>
                <td><?= e($item['product_name']) ?></td>
                <td><?= e($item['variant_label']) ?></td>
                <td><span class="inline-note"><?= e($item['sku']) ?></span></td>
                <td class="num"><?= e(money($item['unit_price'])) ?></td>
                <td class="num"><?= (int) $item['quantity'] ?></td>
                <td class="num"><?= e(money($item['line_total'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="admin-panel-body">
        <div class="order-totals">
          <div class="summary-line"><span>Subtotal</span><span><?= e(money($order['subtotal'])) ?></span></div>
          <?php if ((float) $order['discount'] > 0): ?>
            <div class="summary-line is-discount"><span>Discount<?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?></span><span>−<?= e(money($order['discount'])) ?></span></div>
          <?php endif; ?>
          <div class="summary-line"><span>Shipping</span><span><?= (float) $order['shipping_fee'] > 0 ? e(money($order['shipping_fee'])) : 'Not charged' ?></span></div>
          <div class="summary-line"><span>Tax</span><span><?= (float) $order['tax_total'] > 0 ? e(money($order['tax_total'])) : '—' ?></span></div>
          <div class="summary-total"><span>Total</span><span><?= e(money($order['total'])) ?></span></div>
        </div>
      </div>
    </section>

    <section class="admin-panel">
      <div class="admin-panel-head"><h2>Customer &amp; delivery</h2></div>
      <div class="admin-panel-body">
        <div class="form-grid">
          <div>
            <p class="inline-note" style="text-transform:uppercase;letter-spacing:.1em">Customer</p>
            <p><?= e($order['customer_name']) ?><br><?= e($order['email']) ?><br><?= e($order['phone']) ?></p>
          </div>
          <div>
            <p class="inline-note" style="text-transform:uppercase;letter-spacing:.1em">Deliver to</p>
            <p><?= e($order['shipping_full_name']) ?><br><?= e(full_address($order)) ?><br><?= e($order['shipping_phone']) ?></p>
          </div>
        </div>
        <?php if (!empty($order['customer_notes'])): ?>
          <div class="notice" style="margin:16px 0 0"><strong>Customer notes:</strong><br><?= nl2br(e($order['customer_notes'])) ?></div>
        <?php endif; ?>
      </div>
    </section>

    <section class="admin-panel">
      <div class="admin-panel-head"><h2>Payments</h2></div>
      <div class="table-scroll">
        <table class="admin-table">
          <thead><tr><th>Gateway</th><th>Reference</th><th>Status</th><th class="num">Amount</th><th>Created</th></tr></thead>
          <tbody>
            <?php if ($order['payments'] === []): ?><tr><td colspan="5" class="muted">No payment records.</td></tr><?php endif; ?>
            <?php foreach ($order['payments'] as $payment): ?>
              <tr>
                <td><?= e(ucfirst((string) $payment['gateway'])) ?></td>
                <td><?= e($payment['gateway_payment_id'] ?: $payment['gateway_order_id'] ?: '—') ?></td>
                <td><span class="<?= e(status_class((string) $payment['status'])) ?>"><?= e(ucfirst((string) $payment['status'])) ?></span></td>
                <td class="num"><?= e(money($payment['amount'])) ?></td>
                <td><?= e(date_display($payment['created_at'], true)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($order['payment_method'] === 'cod' && $order['payment_status'] !== 'paid' && !$isCancelled): ?>
        <div class="admin-panel-body" style="border-top:1px solid var(--line)">
          <form method="post" action="<?= e(admin_url('order-view.php?id=' . $orderId)) ?>" data-confirm="Record the full COD amount as received? This cannot be undone here.">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $orderId ?>">
            <input type="hidden" name="action" value="mark_cod_paid">
            <button class="btn btn-primary btn-sm" type="submit"><?= icon('check') ?> Record COD payment received (<?= e(money($order['total'])) ?>)</button>
          </form>
        </div>
      <?php endif; ?>
    </section>

    <?php if ($auditTrail !== []): ?>
      <section class="admin-panel">
        <div class="admin-panel-head"><h2>Recent activity</h2></div>
        <div class="admin-list">
          <?php foreach ($auditTrail as $entry): ?>
            <div class="admin-list-item">
              <div><strong><?= e(str_replace(['order.', '_'], ['', ' '], (string) $entry['action'])) ?></strong><small><?= e($entry['admin_name'] ?: 'System') ?></small></div>
              <div class="right"><small><?= e(date_display($entry['created_at'], true)) ?></small></div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  </div>

  <div>
    <section class="admin-panel">
      <div class="admin-panel-head"><h2>Update order</h2></div>
      <div class="admin-panel-body">
        <form class="admin-form" method="post" action="<?= e(admin_url('order-view.php?id=' . $orderId)) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $orderId ?>">
          <input type="hidden" name="action" value="update_status">
          <div class="field">
            <label for="status">Order status</label>
            <select id="status" name="status">
              <?php foreach ($validStatuses as $s): ?>
                <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(order_status_label($s)) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="inline-note">Cancelling, returning or refunding restores stock once.</p>
          </div>
          <div class="field">
            <label for="tracking_number">Tracking number</label>
            <input id="tracking_number" type="text" name="tracking_number" value="<?= e((string) $order['tracking_number']) ?>" maxlength="120">
          </div>
          <div class="field">
            <label for="tracking_url">Tracking link</label>
            <input id="tracking_url" type="url" name="tracking_url" value="<?= e((string) $order['tracking_url']) ?>" maxlength="255" placeholder="https://…">
          </div>
          <div class="field">
            <label for="admin_notes">Internal notes</label>
            <textarea id="admin_notes" name="admin_notes" maxlength="3000"><?= e((string) $order['admin_notes']) ?></textarea>
            <p class="inline-note">Not shown to the customer.</p>
          </div>
          <button class="btn btn-primary" type="submit">Save &amp; notify customer</button>
        </form>
      </div>
    </section>

    <section class="admin-panel">
      <div class="admin-panel-head"><h2>Quick notes</h2></div>
      <div class="admin-panel-body">
        <form class="admin-form" method="post" action="<?= e(admin_url('order-view.php?id=' . $orderId)) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $orderId ?>">
          <input type="hidden" name="action" value="save_notes">
          <div class="field">
            <label for="notes_only">Internal notes</label>
            <textarea id="notes_only" name="admin_notes" maxlength="3000"><?= e((string) $order['admin_notes']) ?></textarea>
          </div>
          <button class="btn btn-ghost btn-sm" type="submit">Save notes only</button>
        </form>
      </div>
    </section>
  </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
