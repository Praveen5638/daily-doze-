<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$nextSelf = 'account.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
$customer = Auth::requireCustomer($nextSelf);
$customerId = (int) $customer['id'];
$customer = Database::one('SELECT * FROM customers WHERE id = ?', [$customerId]) ?? $customer;

$allowedTabs = ['dashboard', 'orders', 'wishlist', 'addresses', 'profile', 'reviews', 'settings'];
$tab = (string) ($_GET['tab'] ?? 'dashboard');
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'dashboard';
}

if (is_post()) {
    verify_csrf();
    $action = post_str('action');

    if ($action === 'profile_update') {
        $firstName = trim(post_str('first_name'));
        $lastName = trim(post_str('last_name'));
        $phone = preg_replace('/[^0-9+]/', '', post_str('phone')) ?? '';
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($firstName === '' || mb_strlen($firstName) > 80) {
            flash('error', t('account.flash_first', 'Please enter your first name.'));
        } elseif (mb_strlen($lastName) > 80) {
            flash('error', t('account.flash_last', 'Please keep your last name under 80 characters.'));
        } elseif (strlen(preg_replace('/\D/', '', $phone) ?? '') < 10) {
            flash('error', t('account.flash_phone', 'Please enter a valid mobile number.'));
        } elseif ($newPassword !== '' || $confirmPassword !== '') {
            if (!password_verify($currentPassword, (string) $customer['password_hash'])) {
                flash('error', t('account.flash_current_pw', 'Your current password is not correct, so the password was not changed.'));
                redirect('account.php?tab=profile');
            }
            if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,72}$/', $newPassword)) {
                flash('error', t('account.flash_new_pw', 'The new password must be at least 8 characters and include a letter and a number.'));
                redirect('account.php?tab=profile');
            }
            if ($newPassword !== $confirmPassword) {
                flash('error', t('account.flash_pw_mismatch', 'The new passwords do not match.'));
                redirect('account.php?tab=profile');
            }
            Database::update('customers', [
                'first_name' => $firstName,
                'last_name' => $lastName !== '' ? $lastName : null,
                'phone' => $phone,
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ], 'id = ?', [$customerId]);
            Auth::logout();
            flash('success', t('account.flash_profile_pw', 'Your profile and password were updated. Please log in again with your new password.'));
            redirect('login.php');
        } else {
            Database::update('customers', [
                'first_name' => $firstName,
                'last_name' => $lastName !== '' ? $lastName : null,
                'phone' => $phone,
            ], 'id = ?', [$customerId]);
            flash('success', t('account.flash_profile_ok', 'Your profile has been updated.'));
        }
        redirect('account.php?tab=profile');
    }

    if ($action === 'address_save') {
        $addressId = post_int('id');
        $fullName = trim(post_str('full_name'));
        $phone = preg_replace('/[^0-9+]/', '', post_str('phone')) ?? '';
        $type = post_str('address_type', 'home');
        $house = trim(post_str('house'));
        $street = trim(post_str('street'));
        $landmark = trim(post_str('landmark'));
        $city = trim(post_str('city'));
        $state = trim(post_str('state'));
        $pincode = trim(post_str('pincode'));
        $country = trim(post_str('country', 'India'));
        $label = trim(post_str('label'));
        $makeDefault = isset($_POST['is_default']);

        $addressesCount = (int) Database::value('SELECT COUNT(*) FROM customer_addresses WHERE customer_id = ?', [$customerId]);
        $existing = null;
        if ($addressId > 0) {
            $existing = Database::one('SELECT * FROM customer_addresses WHERE id = ? AND customer_id = ?', [$addressId, $customerId]);
            if ($existing === null) {
                flash('error', t('account.flash_address_missing', 'That address could not be found.'));
                redirect('account.php?tab=addresses');
            }
        } elseif ($addressesCount >= 10) {
            flash('error', t('account.flash_address_limit', 'You can save up to 10 addresses. Please remove one before adding another.'));
            redirect('account.php?tab=addresses');
        }

        if (!in_array($type, ['home', 'office', 'other'], true)) {
            $type = 'home';
        }
        if ($fullName === '' || mb_strlen($fullName) > 120) {
            flash('error', t('account.flash_recipient', 'Please enter the recipient name.'));
        } elseif (strlen(preg_replace('/\D/', '', $phone) ?? '') < 7) {
            flash('error', t('account.flash_phone_invalid', 'Please enter a valid phone number for this address.'));
        } elseif ($house === '' || $street === '' || $city === '' || $state === '') {
            flash('error', t('account.flash_address_fields', 'Please complete the address fields.'));
        } elseif (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 -]{2,11}$/', $pincode)) {
            flash('error', t('account.flash_pincode', 'Please enter a valid postal / PIN code.'));
        } else {
            if ($country === '') {
                $country = 'India';
            }
            $makeDefault = $makeDefault || ($existing !== null && (int) $existing['is_default'] === 1) || $addressesCount === 0;
            if ($makeDefault) {
                Database::q('UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?', [$customerId]);
            }
            $data = [
                'label' => $label !== '' ? mb_substr($label, 0, 60) : null,
                'full_name' => $fullName,
                'phone' => $phone,
                'address_type' => $type,
                'house' => mb_substr($house, 0, 120),
                'street' => mb_substr($street, 0, 190),
                'landmark' => $landmark !== '' ? mb_substr($landmark, 0, 190) : null,
                'city' => mb_substr($city, 0, 120),
                'state' => mb_substr($state, 0, 120),
                'pincode' => mb_substr($pincode, 0, 12),
                'country' => mb_substr($country, 0, 80),
                'is_default' => $makeDefault ? 1 : 0,
            ];
            if ($existing !== null) {
                Database::update('customer_addresses', $data, 'id = ? AND customer_id = ?', [$addressId, $customerId]);
                flash('success', t('account.flash_address_updated', 'Address updated.'));
            } else {
                $data['customer_id'] = $customerId;
                Database::insert('customer_addresses', $data);
                flash('success', t('account.flash_address_saved', 'Address saved.'));
            }
            redirect('account.php?tab=addresses');
        }
        redirect('account.php?tab=addresses');
    }

    if ($action === 'address_delete' || $action === 'address_default') {
        $addressId = post_int('id');
        $address = Database::one('SELECT * FROM customer_addresses WHERE id = ? AND customer_id = ?', [$addressId, $customerId]);
        if ($address === null) {
            flash('error', t('account.flash_address_missing', 'That address could not be found.'));
        } elseif ($action === 'address_default') {
            Database::q('UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?', [$customerId]);
            Database::update('customer_addresses', ['is_default' => 1], 'id = ? AND customer_id = ?', [$addressId, $customerId]);
            flash('success', t('account.flash_default_updated', 'Default delivery address updated.'));
        } else {
            Database::q('DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?', [$addressId, $customerId]);
            if ((int) $address['is_default'] === 1) {
                $replacement = Database::one('SELECT id FROM customer_addresses WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$customerId]);
                if ($replacement !== null) {
                    Database::update('customer_addresses', ['is_default' => 1], 'id = ?', [$replacement['id']]);
                }
            }
            flash('success', t('account.flash_address_removed', 'Address removed.'));
        }
        redirect('account.php?tab=addresses');
    }

    if ($action === 'wishlist_remove') {
        $productId = post_int('product_id');
        Database::q(
            'DELETE wi FROM wishlist_items wi JOIN wishlists w ON w.id = wi.wishlist_id WHERE w.customer_id = ? AND wi.product_id = ?',
            [$customerId, $productId]
        );
        flash('success', t('account.flash_wishlist_removed', 'Item removed from your wishlist.'));
        redirect('account.php?tab=wishlist');
    }

    if ($action === 'request_deletion') {
        if (!isset($_POST['confirm_deletion'])) {
            flash('error', t('account.flash_deletion_confirm', 'Please confirm that you want to request account deletion.'));
            redirect('account.php?tab=settings');
        }
        Database::update('customers', ['status' => 'pending_deletion'], 'id = ?', [$customerId]);
        Notifications::admin(
            'account_deletion_request',
            'Account deletion requested',
            $customer['first_name'] . ' ' . ($customer['last_name'] ?? '') . ' · ' . $customer['email'],
            'customers.php'
        );
        Notifications::audit('customer.deletion_requested', 'customer', (string) $customerId);
        Auth::logout();
        flash('success', t('account.flash_deletion_received', 'Your deletion request has been received. Our team will review it and contact you. If you change your mind, please contact support.'));
        redirect('');
    }

    redirect('account.php');
}

$wishlistIds = array_map('intval', array_column(
    Database::all(
        'SELECT wi.product_id FROM wishlists w JOIN wishlist_items wi ON wi.wishlist_id = w.id
         WHERE w.customer_id = ? ORDER BY wi.created_at DESC',
        [$customerId]
    ),
    'product_id'
));
$wishlistProducts = [];
if ($tab === 'wishlist') {
    $wishlistProducts = Catalog::products_by_ids($wishlistIds);
    usort($wishlistProducts, static function (array $a, array $b) use ($wishlistIds): int {
        return array_search((int) $a['id'], $wishlistIds, true) <=> array_search((int) $b['id'], $wishlistIds, true);
    });
}

$orders = Database::all(
    'SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC, id DESC',
    [$customerId]
);
$addresses = Database::all(
    'SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC',
    [$customerId]
);
$reviews = Database::all(
    'SELECT r.*, p.name AS product_name, p.slug AS product_slug
     FROM reviews r LEFT JOIN products p ON p.id = r.product_id
     WHERE r.customer_id = ? ORDER BY r.created_at DESC',
    [$customerId]
);

$order = null;
if ($tab === 'orders' && isset($_GET['order'])) {
    $candidate = Order::find((int) $_GET['order']);
    if ($candidate !== null && (int) $candidate['customer_id'] === $customerId) {
        $order = $candidate;
    } else {
        flash('error', t('account.flash_order_missing', 'That order could not be found in your account.'));
        redirect('account.php?tab=orders');
    }
}

$editAddress = null;
if ($tab === 'addresses' && isset($_GET['edit'])) {
    $editAddress = Database::one('SELECT * FROM customer_addresses WHERE id = ? AND customer_id = ?', [(int) $_GET['edit'], $customerId]);
}
$showAddressForm = $tab === 'addresses' && (isset($_GET['new']) || $editAddress !== null);

$customerName = trim($customer['first_name'] . ' ' . ($customer['last_name'] ?? ''));
$status = $order !== null ? (string) $order['status'] : '';
$paymentStatus = $order !== null ? (string) $order['payment_status'] : '';
$isCancelled = $order !== null && in_array($status, ['cancelled', 'returned', 'refunded'], true);
$isDelivered = $status === 'delivered';
$isShipped = in_array($status, ['shipped', 'out_for_delivery', 'delivered'], true);
$isConfirmed = in_array($status, ['confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery', 'delivered'], true) || $paymentStatus === 'paid';

$seo = [
    'title' => t('account.title', 'My account') . ' | ' . setting('site_name', 'Daily Dose Export'),
    'description' => t('account.meta', 'Manage your Daily Dose Export orders, addresses, wishlist and account preferences.'),
    'canonical' => url('account.php'),
    'robots' => 'noindex,nofollow',
];
$activeNav = '';
$bodyClass = 'account';
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="page-hero">
  <div class="container">
    <nav class="breadcrumbs" aria-label="<?= e(t('common.breadcrumb', 'Breadcrumb')) ?>">
      <a href="<?= e(url('')) ?>"><?= e(t('nav.home', 'Home')) ?></a> <span aria-hidden="true">/</span> <span aria-current="page"><?= e(t('account.title', 'My account')) ?></span>
    </nav>
    <h1><?= e(sprintf(t('account.hello', 'Hello, %s'), $customer['first_name'])) ?></h1>
    <p class="page-lead"><?= e(t('account.lead', 'Manage your orders, delivery addresses and account preferences.')) ?></p>
  </div>
</div>

<div class="container">
  <div class="account-layout">
    <aside class="account-sidebar">
      <div class="account-greeting">
        <small><?= e(t('account.signed_in', 'Signed in as')) ?></small>
        <strong><?= e($customerName) ?></strong>
      </div>
      <nav class="account-nav" aria-label="<?= e(t('account.sections', 'Account sections')) ?>">
        <a class="<?= $tab === 'dashboard' ? 'is-active' : '' ?>" href="<?= e(url('account.php')) ?>"><?= icon('grid') ?> <?= e(t('account.dashboard', 'Dashboard')) ?></a>
        <a class="<?= $tab === 'orders' ? 'is-active' : '' ?>" href="<?= e(url('account.php?tab=orders')) ?>"><?= icon('package') ?> <?= e(t('account.orders', 'Orders')) ?></a>
        <a class="<?= $tab === 'wishlist' ? 'is-active' : '' ?>" href="<?= e(url('account.php?tab=wishlist')) ?>"><?= icon('heart') ?> <?= e(t('account.wishlist', 'Wishlist')) ?></a>
        <a class="<?= $tab === 'addresses' ? 'is-active' : '' ?>" href="<?= e(url('account.php?tab=addresses')) ?>"><?= icon('pin') ?> <?= e(t('account.addresses', 'Addresses')) ?></a>
        <a class="<?= $tab === 'profile' ? 'is-active' : '' ?>" href="<?= e(url('account.php?tab=profile')) ?>"><?= icon('user') ?> <?= e(t('account.profile', 'Profile')) ?></a>
        <a class="<?= $tab === 'reviews' ? 'is-active' : '' ?>" href="<?= e(url('account.php?tab=reviews')) ?>"><?= icon('star') ?> <?= e(t('account.reviews', 'My reviews')) ?></a>
        <a class="<?= $tab === 'settings' ? 'is-active' : '' ?>" href="<?= e(url('account.php?tab=settings')) ?>"><?= icon('settings') ?> <?= e(t('account.settings', 'Settings')) ?></a>
      </nav>
      <form method="post" action="<?= e(url('logout.php')) ?>" class="account-logout">
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm btn-block" type="submit"><?= icon('logout') ?> <?= e(t('account.logout', 'Log out')) ?></button>
      </form>
    </aside>

    <div class="account-main">
      <?php if ($customer['status'] === 'pending_deletion'): ?>
        <div class="alert alert-error"><?= e(t('account.pending_deletion', 'Your account deletion request is being reviewed. Please contact support if you would like to cancel it.')) ?></div>
      <?php endif; ?>

      <?php if ($tab === 'dashboard'): ?>
        <section class="account-card">
          <div class="account-card-head">
            <h1><?= e(t('account.dashboard', 'Dashboard')) ?></h1>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('shop.php')) ?>"><?= e(t('account.continue_shopping', 'Continue shopping')) ?></a>
          </div>
          <p class="muted small"><?= e(sprintf(t('account.member_since', 'Member since %s'), date_display($customer['created_at']))) ?>.</p>
          <div class="account-facts">
            <a class="account-fact" href="<?= e(url('account.php?tab=orders')) ?>"><strong><?= count($orders) ?></strong><span><?= e(t('account.orders_placed', 'Orders placed')) ?></span></a>
            <a class="account-fact" href="<?= e(url('account.php?tab=wishlist')) ?>"><strong><?= count($wishlistIds) ?></strong><span><?= e(t('account.wishlist_items', 'Wishlist items')) ?></span></a>
            <a class="account-fact" href="<?= e(url('account.php?tab=addresses')) ?>"><strong><?= count($addresses) ?></strong><span><?= e(t('account.saved_addresses', 'Saved addresses')) ?></span></a>
          </div>
        </section>

        <section class="account-card">
          <div class="account-card-head">
            <h2><?= e(t('account.recent_orders', 'Recent orders')) ?></h2>
            <?php if ($orders !== []): ?><a class="small" href="<?= e(url('account.php?tab=orders')) ?>"><?= e(t('account.view_all', 'View all')) ?></a><?php endif; ?>
          </div>
          <?php if ($orders === []): ?>
            <div class="empty-state">
              <?= icon('package') ?>
              <p><?= e(t('account.no_orders', 'You have not placed an order yet.')) ?></p>
              <a class="btn btn-primary btn-sm" href="<?= e(url('shop.php')) ?>"><?= e(t('account.explore_pantry', 'Explore the pantry')) ?></a>
            </div>
          <?php else: ?>
            <div class="account-orders">
              <?php foreach (array_slice($orders, 0, 4) as $row): ?>
                <a class="account-order" href="<?= e(url('account.php?tab=orders&order=' . (int) $row['id'])) ?>">
                  <div>
                    <strong><?= e($row['order_number']) ?></strong>
                    <small><?= e(date_display($row['created_at'], true)) ?> · <?= e(sprintf(t('account.items_count', '%d item(s)'), (int) Database::value('SELECT COUNT(*) FROM order_items WHERE order_id = ?', [$row['id']]))) ?></small>
                  </div>
                  <div class="right">
                    <span class="<?= e(status_class((string) $row['status'])) ?>"><?= e(order_status_label((string) $row['status'])) ?></span>
                    <small><?= e(money($row['total'])) ?></small>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

      <?php elseif ($tab === 'orders'): ?>
        <?php if ($order === null): ?>
          <section class="account-card">
            <div class="account-card-head">
              <h1><?= e(t('account.your_orders', 'Your orders')) ?></h1>
              <a class="btn btn-ghost btn-sm" href="<?= e(url('track-order.php')) ?>"><?= e(t('account.track_order', 'Track an order')) ?></a>
            </div>
            <?php if ($orders === []): ?>
              <div class="empty-state">
                <?= icon('package') ?>
                <p><?= e(t('account.no_orders_yet', 'No orders yet. When you place an order it will appear here.')) ?></p>
                <a class="btn btn-primary btn-sm" href="<?= e(url('shop.php')) ?>"><?= e(t('account.start_shopping', 'Start shopping')) ?></a>
              </div>
            <?php else: ?>
              <div class="account-orders">
                <?php foreach ($orders as $row): ?>
                  <a class="account-order" href="<?= e(url('account.php?tab=orders&order=' . (int) $row['id'])) ?>">
                    <div>
                      <strong><?= e($row['order_number']) ?></strong>
                      <small><?= e(t('account.placed', 'Placed')) ?> <?= e(date_display($row['created_at'], true)) ?> · <?= e($row['payment_method'] === 'cod' ? t('order.cash_on_delivery', 'Cash on delivery') : t('order.online_payment', 'Online payment')) ?> · <?= e(money($row['total'])) ?></small>
                    </div>
                    <div class="right">
                      <span class="<?= e(status_class((string) $row['status'])) ?>"><?= e(order_status_label((string) $row['status'])) ?></span>
                      <small><?= e(t('account.view_details', 'View details')) ?></small>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        <?php else: ?>
          <p><a class="small" href="<?= e(url('account.php?tab=orders')) ?>"><?= icon('arrow-left') ?> <?= e(t('account.all_orders', 'All orders')) ?></a></p>
          <div class="account-card">
            <div class="account-card-head">
              <h1><?= e(sprintf(t('account.order_heading', 'Order %s'), $order['order_number'])) ?></h1>
              <span class="<?= e(status_class($status)) ?>"><?= e(order_status_label($status)) ?></span>
            </div>
            <dl class="order-meta-grid">
              <div><dt><?= e(t('order.placed_on', 'Placed on')) ?></dt><dd><?= e(date_display($order['created_at'], true)) ?></dd></div>
              <div><dt><?= e(t('order.payment', 'Payment')) ?></dt><dd><?= e($order['payment_method'] === 'cod' ? t('order.cash_on_delivery', 'Cash on delivery') : t('order.online_payment', 'Online payment')) ?> — <?= e(payment_status_label($paymentStatus)) ?></dd></div>
              <div><dt><?= e(t('order.contact', 'Contact')) ?></dt><dd><?= e($order['customer_name']) ?><br><?= e($order['email']) ?><br><?= e($order['phone']) ?></dd></div>
              <div><dt><?= e(t('order.deliver_to', 'Deliver to')) ?></dt><dd><?= e($order['shipping_full_name']) ?><br><?= e(full_address($order)) ?></dd></div>
              <?php if (!empty($order['tracking_number'])): ?>
                <div><dt><?= e(t('order.tracking', 'Tracking')) ?></dt><dd><?= e($order['tracking_number']) ?><?php if (!empty($order['tracking_url'])): ?> — <a href="<?= e($order['tracking_url']) ?>" target="_blank" rel="noopener"><?= e(t('order.track_parcel', 'Track parcel')) ?></a><?php endif; ?></dd></div>
              <?php endif; ?>
              <?php if (!empty($order['customer_notes'])): ?>
                <div><dt><?= e(t('order.your_notes', 'Your notes')) ?></dt><dd><?= e($order['customer_notes']) ?></dd></div>
              <?php endif; ?>
            </dl>

            <table class="order-table">
              <thead><tr><th><?= e(t('order.item', 'Item')) ?></th><th><?= e(t('order.qty', 'Qty')) ?></th><th style="text-align:right"><?= e(t('order.amount', 'Amount')) ?></th></tr></thead>
              <tbody>
                <?php foreach ($order['items'] as $item): ?>
                  <tr>
                    <td><?= e($item['product_name']) ?><br><span class="muted small"><?= e($item['variant_label']) ?> · SKU <?= e($item['sku']) ?></span></td>
                    <td><?= (int) $item['quantity'] ?></td>
                    <td style="text-align:right"><?= e(money($item['line_total'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <div class="order-totals">
              <div class="summary-line"><span><?= e(t('order.subtotal', 'Subtotal')) ?></span><span><?= e(money($order['subtotal'])) ?></span></div>
              <?php if ((float) $order['discount'] > 0): ?>
                <div class="summary-line is-discount"><span><?= e(t('order.discount', 'Discount')) ?><?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?></span><span>−<?= e(money($order['discount'])) ?></span></div>
              <?php endif; ?>
              <div class="summary-line"><span><?= e(t('order.shipping', 'Shipping')) ?></span><span><?= (float) $order['shipping_fee'] > 0 ? e(money($order['shipping_fee'])) : e(t('order.to_be_confirmed', 'To be confirmed')) ?></span></div>
              <?php if ((float) $order['tax_total'] > 0): ?>
                <div class="summary-line"><span><?= e(t('order.tax', 'Tax')) ?></span><span><?= e(money($order['tax_total'])) ?></span></div>
              <?php endif; ?>
              <div class="summary-total"><span><?= e(t('order.total', 'Total')) ?></span><span><?= e(money($order['total'])) ?></span></div>
            </div>

            <?php if (!$isCancelled && $paymentStatus !== 'paid' && $order['payment_method'] === 'razorpay' && Payment::enabled()): ?>
              <div class="alert" style="margin-top:20px"><?= e(t('order.payment_pending', 'Payment is still pending.')) ?> <a href="<?= e(url('pay.php?order=' . (int) $order['id'])) ?>"><?= e(t('account.complete_payment', 'Complete payment')) ?></a>.</div>
            <?php endif; ?>

            <?php if (!$isCancelled): ?>
              <h2 style="margin-top:26px"><?= e(t('order.progress', 'Progress')) ?></h2>
              <div class="timeline">
                <div class="timeline-step is-done">
                  <span class="timeline-dot"><?= icon('check') ?></span>
                  <div><strong><?= e(t('order.received', 'Order received')) ?></strong><span><?= e(date_display($order['created_at'], true)) ?></span></div>
                </div>
                <div class="timeline-step <?= $isConfirmed ? 'is-done' : '' ?>">
                  <span class="timeline-dot"><?= icon($isConfirmed ? 'check' : 'calendar') ?></span>
                  <div><strong><?= e(t('order.confirmed', 'Confirmed')) ?></strong><span><?= e(t('order.confirmed_desc', 'Our team confirms availability and delivery details.')) ?></span></div>
                </div>
                <div class="timeline-step <?= $isShipped ? 'is-done' : '' ?>">
                  <span class="timeline-dot"><?= icon($isShipped ? 'check' : 'truck') ?></span>
                  <div><strong><?= e(t('order.packed_dispatched', 'Packed & dispatched')) ?></strong><span><?= !empty($order['shipped_at']) ? e(date_display($order['shipped_at'], true)) : e(t('order.tracking_shared', 'Tracking details are shared after dispatch where available.')) ?></span></div>
                </div>
                <div class="timeline-step <?= $isDelivered ? 'is-done' : '' ?>">
                  <span class="timeline-dot"><?= icon($isDelivered ? 'check' : 'package') ?></span>
                  <div><strong><?= e(t('order.delivered', 'Delivered')) ?></strong><span><?= !empty($order['delivered_at']) ? e(date_display($order['delivered_at'], true)) : e(t('order.delivery_timeline', 'Delivery timeline is confirmed by our team.')) ?></span></div>
                </div>
              </div>
            <?php endif; ?>

            <div class="btn-row" style="margin-top:22px">
              <a class="btn btn-ghost btn-sm" href="<?= e(url('track-order.php?order=' . urlencode($order['order_number']))) ?>"><?= e(t('account.track_order', 'Track order')) ?></a>
              <button class="btn btn-ghost btn-sm" type="button" data-print><?= icon('download') ?> <?= e(t('account.print', 'Print')) ?></button>
            </div>
          </div>
        <?php endif; ?>

      <?php elseif ($tab === 'wishlist'): ?>
        <section class="account-card">
          <div class="account-card-head">
            <h1><?= e(t('account.your_wishlist', 'Your wishlist')) ?></h1>
          </div>
          <?php if ($wishlistProducts === []): ?>
            <div class="empty-state">
              <?= icon('heart') ?>
              <p><?= e(t('account.wishlist_empty', 'Your wishlist is empty. Tap the heart on any product to save it here.')) ?></p>
              <a class="btn btn-primary btn-sm" href="<?= e(url('shop.php')) ?>"><?= e(t('account.browse_products', 'Browse products')) ?></a>
            </div>
          <?php else: ?>
            <div class="product-grid">
              <?php foreach ($wishlistProducts as $product): ?>
                <?php require BASE_PATH . '/app/views/partials/product-card.php'; ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

      <?php elseif ($tab === 'addresses'): ?>
        <section class="account-card">
          <div class="account-card-head">
            <h1><?= e(t('account.delivery_addresses', 'Delivery addresses')) ?></h1>
            <?php if (!$showAddressForm): ?>
              <a class="btn btn-primary btn-sm" href="<?= e(url('account.php?tab=addresses&new=1')) ?>"><?= icon('plus') ?> <?= e(t('account.add_address', 'Add address')) ?></a>
            <?php endif; ?>
          </div>

          <?php if ($showAddressForm): ?>
            <?php
            $a = $editAddress ?? [
                'id' => 0, 'label' => '', 'full_name' => $customerName, 'phone' => $customer['phone'],
                'address_type' => 'home', 'house' => '', 'street' => '', 'landmark' => '',
                'city' => '', 'state' => '', 'pincode' => '', 'country' => 'India', 'is_default' => count($addresses) === 0 ? 1 : 0,
            ];
            ?>
            <form class="form-stack" method="post" action="<?= e(url('account.php')) ?>" style="margin-bottom:26px">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="address_save">
              <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
              <div class="field-row">
                <div class="field">
                  <label for="full_name"><?= e(t('account.recipient', 'Recipient name')) ?></label>
                  <input id="full_name" type="text" name="full_name" value="<?= e((string) $a['full_name']) ?>" required maxlength="120" autocomplete="name">
                </div>
                <div class="field">
                  <label for="address_phone"><?= e(t('account.phone', 'Phone')) ?></label>
                  <input id="address_phone" type="tel" name="phone" value="<?= e((string) $a['phone']) ?>" required maxlength="20" autocomplete="tel">
                </div>
              </div>
              <div class="field-row">
                <div class="field">
                  <label for="label"><?= e(t('account.label', 'Label (optional)')) ?></label>
                  <input id="label" type="text" name="label" value="<?= e((string) $a['label']) ?>" maxlength="60" placeholder="<?= e(t('account.label_placeholder', 'Home, office, parents…')) ?>">
                </div>
                <div class="field">
                  <label for="address_type"><?= e(t('account.address_type', 'Address type')) ?></label>
                  <select id="address_type" name="address_type">
                    <option value="home" <?= $a['address_type'] === 'home' ? 'selected' : '' ?>><?= e(t('account.home', 'Home')) ?></option>
                    <option value="office" <?= $a['address_type'] === 'office' ? 'selected' : '' ?>><?= e(t('account.office', 'Office')) ?></option>
                    <option value="other" <?= $a['address_type'] === 'other' ? 'selected' : '' ?>><?= e(t('account.other', 'Other')) ?></option>
                  </select>
                </div>
              </div>
              <div class="field">
                <label for="house"><?= e(t('account.house', 'House / flat / building')) ?></label>
                <input id="house" type="text" name="house" value="<?= e((string) $a['house']) ?>" required maxlength="120" autocomplete="address-line1">
              </div>
              <div class="field">
                <label for="street"><?= e(t('account.street', 'Street / area')) ?></label>
                <input id="street" type="text" name="street" value="<?= e((string) $a['street']) ?>" required maxlength="190" autocomplete="address-line2">
              </div>
              <div class="field">
                <label for="landmark"><?= e(t('account.landmark', 'Landmark (optional)')) ?></label>
                <input id="landmark" type="text" name="landmark" value="<?= e((string) $a['landmark']) ?>" maxlength="190">
              </div>
              <div class="field-row">
                <div class="field">
                  <label for="city"><?= e(t('account.city', 'City')) ?></label>
                  <input id="city" type="text" name="city" value="<?= e((string) $a['city']) ?>" required maxlength="120" autocomplete="address-level2">
                </div>
                <div class="field">
                  <label for="state"><?= e(t('account.state', 'State')) ?></label>
                  <input id="state" type="text" name="state" value="<?= e((string) $a['state']) ?>" required maxlength="120" autocomplete="address-level1">
                </div>
              </div>
              <div class="field-row">
                <div class="field">
                  <label for="pincode"><?= e(t('account.pincode', 'PIN / postal code')) ?></label>
                  <input id="pincode" type="text" name="pincode" value="<?= e((string) $a['pincode']) ?>" required maxlength="12" autocomplete="postal-code">
                </div>
                <div class="field">
                  <label for="country"><?= e(t('account.country', 'Country')) ?></label>
                  <input id="country" type="text" name="country" value="<?= e((string) $a['country']) ?>" maxlength="80" autocomplete="country-name">
                </div>
              </div>
              <label class="check-line">
                <input type="checkbox" name="is_default" value="1" <?= (int) $a['is_default'] === 1 ? 'checked' : '' ?>>
                <span><?= e(t('account.default_check', 'Use this as my default delivery address')) ?></span>
              </label>
              <div class="btn-row">
                <button class="btn btn-primary" type="submit"><?= e($a['id'] > 0 ? t('account.update_address', 'Update address') : t('account.save_address', 'Save address')) ?></button>
                <a class="btn btn-ghost" href="<?= e(url('account.php?tab=addresses')) ?>"><?= e(t('account.cancel', 'Cancel')) ?></a>
              </div>
            </form>
          <?php endif; ?>

          <?php if ($addresses === []): ?>
            <div class="empty-state">
              <?= icon('pin') ?>
              <p><?= e(t('account.no_addresses', 'No saved addresses yet. Add one to speed up checkout.')) ?></p>
            </div>
          <?php else: ?>
            <div class="address-grid">
              <?php foreach ($addresses as $address): ?>
                <div class="address-card <?= (int) $address['is_default'] === 1 ? 'is-default' : '' ?>">
                  <?php if ((int) $address['is_default'] === 1): ?><span class="badge"><?= e(t('account.default_badge', 'Default')) ?></span><?php endif; ?>
                  <strong><?= e($address['full_name']) ?></strong><?= $address['label'] ? ' · ' . e($address['label']) : '' ?><br>
                  <?= e($address['house']) ?>, <?= e($address['street']) ?><?= $address['landmark'] ? ', ' . e($address['landmark']) : '' ?><br>
                  <?= e($address['city']) ?>, <?= e($address['state']) ?> <?= e($address['pincode']) ?><br>
                  <?= e($address['country']) ?><br>
                  <span class="muted small"><?= e(t('account.' . $address['address_type'], ucfirst($address['address_type']))) ?> · <?= e($address['phone']) ?></span>
                  <div class="address-card-actions">
                    <a href="<?= e(url('account.php?tab=addresses&edit=' . (int) $address['id'])) ?>"><?= e(t('account.edit', 'Edit')) ?></a>
                    <?php if ((int) $address['is_default'] !== 1): ?>
                      <form method="post" action="<?= e(url('account.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="address_default">
                        <input type="hidden" name="id" value="<?= (int) $address['id'] ?>">
                        <button type="submit"><?= e(t('account.make_default', 'Make default')) ?></button>
                      </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('account.php')) ?>" data-confirm="<?= e(t('account.confirm_remove', 'Remove this saved address?')) ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="address_delete">
                      <input type="hidden" name="id" value="<?= (int) $address['id'] ?>">
                      <button type="submit"><?= e(t('account.remove', 'Remove')) ?></button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

      <?php elseif ($tab === 'profile'): ?>
        <section class="account-card">
          <div class="account-card-head"><h1><?= e(t('account.profile', 'Profile')) ?></h1></div>
          <form class="form-stack" method="post" action="<?= e(url('account.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile_update">
            <div class="field-row">
              <div class="field">
                <label for="first_name"><?= e(t('account.first_name', 'First name')) ?></label>
                <input id="first_name" type="text" name="first_name" value="<?= e($customer['first_name']) ?>" required maxlength="80" autocomplete="given-name">
              </div>
              <div class="field">
                <label for="last_name"><?= e(t('account.last_name', 'Last name')) ?></label>
                <input id="last_name" type="text" name="last_name" value="<?= e((string) $customer['last_name']) ?>" maxlength="80" autocomplete="family-name">
              </div>
            </div>
            <div class="field-row">
              <div class="field">
                <label for="profile_email"><?= e(t('account.email', 'Email address')) ?></label>
                <input id="profile_email" type="email" value="<?= e($customer['email']) ?>" disabled>
                <p class="field-hint"><?= e(t('account.email_hint', 'To change your email address, please contact support.')) ?></p>
              </div>
              <div class="field">
                <label for="profile_phone"><?= e(t('account.mobile', 'Mobile number')) ?></label>
                <input id="profile_phone" type="tel" name="phone" value="<?= e($customer['phone']) ?>" required maxlength="20" autocomplete="tel">
              </div>
            </div>
            <div class="divider" style="margin:6px 0"></div>
            <h2 style="font-size:1.1rem"><?= e(t('account.change_password', 'Change password')) ?></h2>
            <p class="muted small" style="margin:0"><?= e(t('account.password_keep', 'Leave these fields blank to keep your current password.')) ?></p>
            <div class="field">
              <label for="current_password"><?= e(t('account.current_password', 'Current password')) ?></label>
              <input id="current_password" type="password" name="current_password" maxlength="72" autocomplete="current-password">
            </div>
            <div class="field-row">
              <div class="field">
                <label for="new_password"><?= e(t('account.new_password', 'New password')) ?></label>
                <input id="new_password" type="password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password">
              </div>
              <div class="field">
                <label for="confirm_password"><?= e(t('account.confirm_new_password', 'Confirm new password')) ?></label>
                <input id="confirm_password" type="password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password">
              </div>
            </div>
            <p class="field-hint"><?= e(t('account.password_hint', 'At least 8 characters, including a letter and a number. Changing your password signs you out of this session.')) ?></p>
            <div class="btn-row">
              <button class="btn btn-primary" type="submit"><?= e(t('account.save_changes', 'Save changes')) ?></button>
            </div>
          </form>
        </section>

      <?php elseif ($tab === 'reviews'): ?>
        <section class="account-card">
          <div class="account-card-head"><h1><?= e(t('account.reviews', 'My reviews')) ?></h1></div>
          <?php if ($reviews === []): ?>
            <div class="empty-state">
              <?= icon('star') ?>
              <p><?= e(t('account.no_reviews', 'You have not written any reviews yet. Reviews are welcome after a delivered order.')) ?></p>
              <a class="btn btn-primary btn-sm" href="<?= e(url('account.php?tab=orders')) ?>"><?= e(t('account.view_orders', 'View orders')) ?></a>
            </div>
          <?php else: ?>
            <div class="review-list">
              <?php foreach ($reviews as $review): ?>
                <article class="review-item">
                  <div class="review-head">
                    <div>
                      <p class="review-author"><?= $review['product_slug'] ? '<a href="' . e(url('products/' . $review['product_slug'])) . '">' . e((string) $review['product_name']) . '</a>' : e((string) $review['product_name']) ?></p>
                      <p class="review-date"><?= e(date_display($review['created_at'], true)) ?> · <?= rating_stars((float) $review['rating']) ?></p>
                    </div>
                    <span class="<?= e(status_class((string) $review['status'])) ?>"><?= e(t('review_status.' . $review['status'], ucfirst((string) $review['status']))) ?></span>
                  </div>
                  <?php if (!empty($review['title'])): ?><strong><?= e($review['title']) ?></strong><?php endif; ?>
                  <p class="review-body"><?= nl2br(e($review['body'])) ?></p>
                  <?php if ($review['status'] === 'pending'): ?><p class="muted small" style="margin-top:6px"><?= e(t('account.awaiting_moderation', 'Awaiting moderation — it will appear on the product page once approved.')) ?></p><?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

      <?php else: ?>
        <section class="account-card">
          <div class="account-card-head"><h1><?= e(t('account.settings_title', 'Account settings')) ?></h1></div>
          <dl class="order-meta-grid">
            <div><dt><?= e(t('account.email', 'Email')) ?></dt><dd><?= e($customer['email']) ?></dd></div>
            <div><dt><?= e(t('account.mobile', 'Mobile')) ?></dt><dd><?= e($customer['phone']) ?></dd></div>
            <div><dt><?= e(t('account.member_since_label', 'Member since')) ?></dt><dd><?= e(date_display($customer['created_at'])) ?></dd></div>
            <div><dt><?= e(t('account.status', 'Account status')) ?></dt><dd><?= e(t('account_status.' . $customer['status'], ucfirst(str_replace('_', ' ', (string) $customer['status'])))) ?></dd></div>
          </dl>
          <div class="divider"></div>
          <h2 style="font-size:1.15rem"><?= e(t('account.delete_title', 'Request account deletion')) ?></h2>
          <p class="muted small"><?= e(t('account.delete_text', 'Deleting your account is permanent. Orders required for tax and accounting records may be retained as required by law. Our team will confirm the request by email before anything is removed.')) ?></p>
          <form method="post" action="<?= e(url('account.php')) ?>" data-confirm="<?= e(t('account.delete_confirm', 'Submit an account deletion request? Our team will review it before taking action.')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="request_deletion">
            <label class="check-line">
              <input type="checkbox" name="confirm_deletion" value="1" required>
              <span><?= e(t('account.delete_understand', 'I understand and want to request deletion of my account.')) ?></span>
            </label>
            <button class="btn btn-danger btn-sm" type="submit" style="margin-top:14px"><?= icon('trash') ?> <?= e(t('account.delete_button', 'Request deletion')) ?></button>
          </form>
        </section>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
