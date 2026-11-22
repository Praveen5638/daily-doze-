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
            flash('error', 'Please enter your first name.');
        } elseif (mb_strlen($lastName) > 80) {
            flash('error', 'Please keep your last name under 80 characters.');
        } elseif (strlen(preg_replace('/\D/', '', $phone) ?? '') < 10) {
            flash('error', 'Please enter a valid mobile number.');
        } elseif ($newPassword !== '' || $confirmPassword !== '') {
            if (!password_verify($currentPassword, (string) $customer['password_hash'])) {
                flash('error', 'Your current password is not correct, so the password was not changed.');
                redirect('account.php?tab=profile');
            }
            if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,72}$/', $newPassword)) {
                flash('error', 'The new password must be at least 8 characters and include a letter and a number.');
                redirect('account.php?tab=profile');
            }
            if ($newPassword !== $confirmPassword) {
                flash('error', 'The new passwords do not match.');
                redirect('account.php?tab=profile');
            }
            Database::update('customers', [
                'first_name' => $firstName,
                'last_name' => $lastName !== '' ? $lastName : null,
                'phone' => $phone,
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ], 'id = ?', [$customerId]);
            Auth::logout();
            flash('success', 'Your profile and password were updated. Please log in again with your new password.');
            redirect('login.php');
        } else {
            Database::update('customers', [
                'first_name' => $firstName,
                'last_name' => $lastName !== '' ? $lastName : null,
                'phone' => $phone,
            ], 'id = ?', [$customerId]);
            flash('success', 'Your profile has been updated.');
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
                flash('error', 'That address could not be found.');
                redirect('account.php?tab=addresses');
            }
        } elseif ($addressesCount >= 10) {
            flash('error', 'You can save up to 10 addresses. Please remove one before adding another.');
            redirect('account.php?tab=addresses');
        }

        if (!in_array($type, ['home', 'office', 'other'], true)) {
            $type = 'home';
        }
        if ($fullName === '' || mb_strlen($fullName) > 120) {
            flash('error', 'Please enter the recipient name.');
        } elseif (strlen(preg_replace('/\D/', '', $phone) ?? '') < 7) {
            flash('error', 'Please enter a valid phone number for this address.');
        } elseif ($house === '' || $street === '' || $city === '' || $state === '') {
            flash('error', 'Please complete the address fields.');
        } elseif (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 -]{2,11}$/', $pincode)) {
            flash('error', 'Please enter a valid postal / PIN code.');
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
                flash('success', 'Address updated.');
            } else {
                $data['customer_id'] = $customerId;
                Database::insert('customer_addresses', $data);
                flash('success', 'Address saved.');
            }
            redirect('account.php?tab=addresses');
        }
        redirect('account.php?tab=addresses');
    }

    if ($action === 'address_delete' || $action === 'address_default') {
        $addressId = post_int('id');
        $address = Database::one('SELECT * FROM customer_addresses WHERE id = ? AND customer_id = ?', [$addressId, $customerId]);
        if ($address === null) {
            flash('error', 'That address could not be found.');
        } elseif ($action === 'address_default') {
            Database::q('UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?', [$customerId]);
            Database::update('customer_addresses', ['is_default' => 1], 'id = ? AND customer_id = ?', [$addressId, $customerId]);
            flash('success', 'Default delivery address updated.');
        } else {
            Database::q('DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?', [$addressId, $customerId]);
            if ((int) $address['is_default'] === 1) {
                $replacement = Database::one('SELECT id FROM customer_addresses WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$customerId]);
                if ($replacement !== null) {
                    Database::update('customer_addresses', ['is_default' => 1], 'id = ?', [$replacement['id']]);
                }
            }
            flash('success', 'Address removed.');
        }
        redirect('account.php?tab=addresses');
    }

    if ($action === 'wishlist_remove') {
        $productId = post_int('product_id');
        Database::q(
            'DELETE wi FROM wishlist_items wi JOIN wishlists w ON w.id = wi.wishlist_id WHERE w.customer_id = ? AND wi.product_id = ?',
            [$customerId, $productId]
        );
        flash('success', 'Item removed from your wishlist.');
        redirect('account.php?tab=wishlist');
    }

    if ($action === 'request_deletion') {
        if (!isset($_POST['confirm_deletion'])) {
            flash('error', 'Please confirm that you want to request account deletion.');
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
        flash('success', 'Your deletion request has been received. Our team will review it and contact you. If you change your mind, please contact support.');
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
        flash('error', 'That order could not be found in your account.');
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
    'title' => 'My account | ' . setting('site_name', 'Daily Dose Export'),
    'description' => 'Manage your Daily Dose Export orders, addresses, wishlist and account preferences.',
    'canonical' => url('account.php'),
    'robots' => 'noindex,nofollow',
];
$activeNav = '';
$bodyClass = 'account';
require BASE_PATH . '/app/views/layout/header.php';
?>

<div class="page-hero">
  <div class="container">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
      <a href="<?= e(url('')) ?>">Home</a> <span aria-hidden="true">/</span> <span aria-current="page">My account</span>
    </nav>
    <h1>Hello, <?= e($customer['first_name']) ?></h1>
    <p class="page-lead">Manage your orders, delivery addresses and account preferences.</p>
  </div>
</div>

<div class="container">
  <div class="account-layout">
    <aside class="account-sidebar">
      <div class="account-greeting">
        <small>Signed in as</small>
        <strong><?= e($customerName) ?></strong>
      </div>
      <nav class="account-nav" aria-label="Account sections">
        <a class="<?= $tab === 'dashboard' ? 'is-active' : '' ?>" href="<?= e(url('account.php')) ?>"><?= icon('grid') ?> Dashboard</a>
        <a class="<?= $tab === 'orders' ? 'is-active' : '' ?>" href="<?= e(url('account.php?tab=orders')) ?>"><?= icon('package') ?> Orders</a>
        <a class="<?= $tab === 'wishlist' ? 'is-active' : '' ?>" href="<?= e(url('account.php?tab=wishlist')) ?>"><?= icon('heart') ?> Wishlist</a>
        <a class="<?= $tab === 'addresses' ? 'is-active' : '' ?>" href="<?= e(url('account.php?tab=addresses')) ?>"><?= icon('pin') ?> Addresses</a>
        <a class="<?= $tab === 'profile' ? 'is-active' : '' ?>" href="<?= e(url('account.php?tab=profile')) ?>"><?= icon('user') ?> Profile</a>
        <a class="<?= $tab === 'reviews' ? 'is-active' : '' ?>" href="<?= e(url('account.php?tab=reviews')) ?>"><?= icon('star') ?> My reviews</a>
        <a class="<?= $tab === 'settings' ? 'is-active' : '' ?>" href="<?= e(url('account.php?tab=settings')) ?>"><?= icon('settings') ?> Settings</a>
      </nav>
      <form method="post" action="<?= e(url('logout.php')) ?>" class="account-logout">
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm btn-block" type="submit"><?= icon('logout') ?> Log out</button>
      </form>
    </aside>

    <div class="account-main">
      <?php if ($customer['status'] === 'pending_deletion'): ?>
        <div class="alert alert-error">Your account deletion request is being reviewed. Please contact support if you would like to cancel it.</div>
      <?php endif; ?>

      <?php if ($tab === 'dashboard'): ?>
        <section class="account-card">
          <div class="account-card-head">
            <h1>Dashboard</h1>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('shop.php')) ?>">Continue shopping</a>
          </div>
          <p class="muted small">Member since <?= e(date_display($customer['created_at'])) ?>.</p>
          <div class="account-facts">
            <a class="account-fact" href="<?= e(url('account.php?tab=orders')) ?>"><strong><?= count($orders) ?></strong><span>Orders placed</span></a>
            <a class="account-fact" href="<?= e(url('account.php?tab=wishlist')) ?>"><strong><?= count($wishlistIds) ?></strong><span>Wishlist items</span></a>
            <a class="account-fact" href="<?= e(url('account.php?tab=addresses')) ?>"><strong><?= count($addresses) ?></strong><span>Saved addresses</span></a>
          </div>
        </section>

        <section class="account-card">
          <div class="account-card-head">
            <h2>Recent orders</h2>
            <?php if ($orders !== []): ?><a class="small" href="<?= e(url('account.php?tab=orders')) ?>">View all</a><?php endif; ?>
          </div>
          <?php if ($orders === []): ?>
            <div class="empty-state">
              <?= icon('package') ?>
              <p>You have not placed an order yet.</p>
              <a class="btn btn-primary btn-sm" href="<?= e(url('shop.php')) ?>">Explore the pantry</a>
            </div>
          <?php else: ?>
            <div class="account-orders">
              <?php foreach (array_slice($orders, 0, 4) as $row): ?>
                <a class="account-order" href="<?= e(url('account.php?tab=orders&order=' . (int) $row['id'])) ?>">
                  <div>
                    <strong><?= e($row['order_number']) ?></strong>
                    <small><?= e(date_display($row['created_at'], true)) ?> · <?= (int) Database::value('SELECT COUNT(*) FROM order_items WHERE order_id = ?', [$row['id']]) ?> item(s)</small>
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
              <h1>Your orders</h1>
              <a class="btn btn-ghost btn-sm" href="<?= e(url('track-order.php')) ?>">Track an order</a>
            </div>
            <?php if ($orders === []): ?>
              <div class="empty-state">
                <?= icon('package') ?>
                <p>No orders yet. When you place an order it will appear here.</p>
                <a class="btn btn-primary btn-sm" href="<?= e(url('shop.php')) ?>">Start shopping</a>
              </div>
            <?php else: ?>
              <div class="account-orders">
                <?php foreach ($orders as $row): ?>
                  <a class="account-order" href="<?= e(url('account.php?tab=orders&order=' . (int) $row['id'])) ?>">
                    <div>
                      <strong><?= e($row['order_number']) ?></strong>
                      <small>Placed <?= e(date_display($row['created_at'], true)) ?> · <?= $row['payment_method'] === 'cod' ? 'Cash on delivery' : 'Online payment' ?> · <?= e(money($row['total'])) ?></small>
                    </div>
                    <div class="right">
                      <span class="<?= e(status_class((string) $row['status'])) ?>"><?= e(order_status_label((string) $row['status'])) ?></span>
                      <small>View details</small>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        <?php else: ?>
          <p><a class="small" href="<?= e(url('account.php?tab=orders')) ?>"><?= icon('arrow-left') ?> All orders</a></p>
          <div class="account-card">
            <div class="account-card-head">
              <h1>Order <?= e($order['order_number']) ?></h1>
              <span class="<?= e(status_class($status)) ?>"><?= e(order_status_label($status)) ?></span>
            </div>
            <dl class="order-meta-grid">
              <div><dt>Placed on</dt><dd><?= e(date_display($order['created_at'], true)) ?></dd></div>
              <div><dt>Payment</dt><dd><?= $order['payment_method'] === 'cod' ? 'Cash on delivery' : 'Online payment' ?> — <?= e(payment_status_label($paymentStatus)) ?></dd></div>
              <div><dt>Contact</dt><dd><?= e($order['customer_name']) ?><br><?= e($order['email']) ?><br><?= e($order['phone']) ?></dd></div>
              <div><dt>Deliver to</dt><dd><?= e($order['shipping_full_name']) ?><br><?= e(full_address($order)) ?></dd></div>
              <?php if (!empty($order['tracking_number'])): ?>
                <div><dt>Tracking</dt><dd><?= e($order['tracking_number']) ?><?php if (!empty($order['tracking_url'])): ?> — <a href="<?= e($order['tracking_url']) ?>" target="_blank" rel="noopener">Track parcel</a><?php endif; ?></dd></div>
              <?php endif; ?>
              <?php if (!empty($order['customer_notes'])): ?>
                <div><dt>Your notes</dt><dd><?= e($order['customer_notes']) ?></dd></div>
              <?php endif; ?>
            </dl>

            <table class="order-table">
              <thead><tr><th>Item</th><th>Qty</th><th style="text-align:right">Amount</th></tr></thead>
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
              <div class="summary-line"><span>Subtotal</span><span><?= e(money($order['subtotal'])) ?></span></div>
              <?php if ((float) $order['discount'] > 0): ?>
                <div class="summary-line is-discount"><span>Discount<?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?></span><span>−<?= e(money($order['discount'])) ?></span></div>
              <?php endif; ?>
              <div class="summary-line"><span>Shipping</span><span><?= (float) $order['shipping_fee'] > 0 ? e(money($order['shipping_fee'])) : 'To be confirmed' ?></span></div>
              <?php if ((float) $order['tax_total'] > 0): ?>
                <div class="summary-line"><span>Tax</span><span><?= e(money($order['tax_total'])) ?></span></div>
              <?php endif; ?>
              <div class="summary-total"><span>Total</span><span><?= e(money($order['total'])) ?></span></div>
            </div>

            <?php if (!$isCancelled && $paymentStatus !== 'paid' && $order['payment_method'] === 'razorpay' && Payment::enabled()): ?>
              <div class="alert" style="margin-top:20px">Payment is still pending. <a href="<?= e(url('pay.php?order=' . (int) $order['id'])) ?>">Complete payment</a>.</div>
            <?php endif; ?>

            <?php if (!$isCancelled): ?>
              <h2 style="margin-top:26px">Progress</h2>
              <div class="timeline">
                <div class="timeline-step is-done">
                  <span class="timeline-dot"><?= icon('check') ?></span>
                  <div><strong>Order received</strong><span><?= e(date_display($order['created_at'], true)) ?></span></div>
                </div>
                <div class="timeline-step <?= $isConfirmed ? 'is-done' : '' ?>">
                  <span class="timeline-dot"><?= icon($isConfirmed ? 'check' : 'calendar') ?></span>
                  <div><strong>Confirmed</strong><span>Our team confirms availability and delivery details.</span></div>
                </div>
                <div class="timeline-step <?= $isShipped ? 'is-done' : '' ?>">
                  <span class="timeline-dot"><?= icon($isShipped ? 'check' : 'truck') ?></span>
                  <div><strong>Packed &amp; dispatched</strong><span><?= !empty($order['shipped_at']) ? e(date_display($order['shipped_at'], true)) : 'Tracking details are shared after dispatch where available.' ?></span></div>
                </div>
                <div class="timeline-step <?= $isDelivered ? 'is-done' : '' ?>">
                  <span class="timeline-dot"><?= icon($isDelivered ? 'check' : 'package') ?></span>
                  <div><strong>Delivered</strong><span><?= !empty($order['delivered_at']) ? e(date_display($order['delivered_at'], true)) : 'Delivery timeline is confirmed by our team.' ?></span></div>
                </div>
              </div>
            <?php endif; ?>

            <div class="btn-row" style="margin-top:22px">
              <a class="btn btn-ghost btn-sm" href="<?= e(url('track-order.php?order=' . urlencode($order['order_number']))) ?>">Track order</a>
              <button class="btn btn-ghost btn-sm" type="button" data-print><?= icon('download') ?> Print</button>
            </div>
          </div>
        <?php endif; ?>

      <?php elseif ($tab === 'wishlist'): ?>
        <section class="account-card">
          <div class="account-card-head">
            <h1>Your wishlist</h1>
          </div>
          <?php if ($wishlistProducts === []): ?>
            <div class="empty-state">
              <?= icon('heart') ?>
              <p>Your wishlist is empty. Tap the heart on any product to save it here.</p>
              <a class="btn btn-primary btn-sm" href="<?= e(url('shop.php')) ?>">Browse products</a>
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
            <h1>Delivery addresses</h1>
            <?php if (!$showAddressForm): ?>
              <a class="btn btn-primary btn-sm" href="<?= e(url('account.php?tab=addresses&new=1')) ?>"><?= icon('plus') ?> Add address</a>
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
                  <label for="full_name">Recipient name</label>
                  <input id="full_name" type="text" name="full_name" value="<?= e((string) $a['full_name']) ?>" required maxlength="120" autocomplete="name">
                </div>
                <div class="field">
                  <label for="address_phone">Phone</label>
                  <input id="address_phone" type="tel" name="phone" value="<?= e((string) $a['phone']) ?>" required maxlength="20" autocomplete="tel">
                </div>
              </div>
              <div class="field-row">
                <div class="field">
                  <label for="label">Label (optional)</label>
                  <input id="label" type="text" name="label" value="<?= e((string) $a['label']) ?>" maxlength="60" placeholder="Home, office, parents…">
                </div>
                <div class="field">
                  <label for="address_type">Address type</label>
                  <select id="address_type" name="address_type">
                    <option value="home" <?= $a['address_type'] === 'home' ? 'selected' : '' ?>>Home</option>
                    <option value="office" <?= $a['address_type'] === 'office' ? 'selected' : '' ?>>Office</option>
                    <option value="other" <?= $a['address_type'] === 'other' ? 'selected' : '' ?>>Other</option>
                  </select>
                </div>
              </div>
              <div class="field">
                <label for="house">House / flat / building</label>
                <input id="house" type="text" name="house" value="<?= e((string) $a['house']) ?>" required maxlength="120" autocomplete="address-line1">
              </div>
              <div class="field">
                <label for="street">Street / area</label>
                <input id="street" type="text" name="street" value="<?= e((string) $a['street']) ?>" required maxlength="190" autocomplete="address-line2">
              </div>
              <div class="field">
                <label for="landmark">Landmark (optional)</label>
                <input id="landmark" type="text" name="landmark" value="<?= e((string) $a['landmark']) ?>" maxlength="190">
              </div>
              <div class="field-row">
                <div class="field">
                  <label for="city">City</label>
                  <input id="city" type="text" name="city" value="<?= e((string) $a['city']) ?>" required maxlength="120" autocomplete="address-level2">
                </div>
                <div class="field">
                  <label for="state">State</label>
                  <input id="state" type="text" name="state" value="<?= e((string) $a['state']) ?>" required maxlength="120" autocomplete="address-level1">
                </div>
              </div>
              <div class="field-row">
                <div class="field">
                  <label for="pincode">PIN / postal code</label>
                  <input id="pincode" type="text" name="pincode" value="<?= e((string) $a['pincode']) ?>" required maxlength="12" autocomplete="postal-code">
                </div>
                <div class="field">
                  <label for="country">Country</label>
                  <input id="country" type="text" name="country" value="<?= e((string) $a['country']) ?>" maxlength="80" autocomplete="country-name">
                </div>
              </div>
              <label class="check-line">
                <input type="checkbox" name="is_default" value="1" <?= (int) $a['is_default'] === 1 ? 'checked' : '' ?>>
                <span>Use this as my default delivery address</span>
              </label>
              <div class="btn-row">
                <button class="btn btn-primary" type="submit"><?= $a['id'] > 0 ? 'Update address' : 'Save address' ?></button>
                <a class="btn btn-ghost" href="<?= e(url('account.php?tab=addresses')) ?>">Cancel</a>
              </div>
            </form>
          <?php endif; ?>

          <?php if ($addresses === []): ?>
            <div class="empty-state">
              <?= icon('pin') ?>
              <p>No saved addresses yet. Add one to speed up checkout.</p>
            </div>
          <?php else: ?>
            <div class="address-grid">
              <?php foreach ($addresses as $address): ?>
                <div class="address-card <?= (int) $address['is_default'] === 1 ? 'is-default' : '' ?>">
                  <?php if ((int) $address['is_default'] === 1): ?><span class="badge">Default</span><?php endif; ?>
                  <strong><?= e($address['full_name']) ?></strong><?= $address['label'] ? ' · ' . e($address['label']) : '' ?><br>
                  <?= e($address['house']) ?>, <?= e($address['street']) ?><?= $address['landmark'] ? ', ' . e($address['landmark']) : '' ?><br>
                  <?= e($address['city']) ?>, <?= e($address['state']) ?> <?= e($address['pincode']) ?><br>
                  <?= e($address['country']) ?><br>
                  <span class="muted small"><?= e(ucfirst($address['address_type'])) ?> · <?= e($address['phone']) ?></span>
                  <div class="address-card-actions">
                    <a href="<?= e(url('account.php?tab=addresses&edit=' . (int) $address['id'])) ?>">Edit</a>
                    <?php if ((int) $address['is_default'] !== 1): ?>
                      <form method="post" action="<?= e(url('account.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="address_default">
                        <input type="hidden" name="id" value="<?= (int) $address['id'] ?>">
                        <button type="submit">Make default</button>
                      </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('account.php')) ?>" data-confirm="Remove this saved address?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="address_delete">
                      <input type="hidden" name="id" value="<?= (int) $address['id'] ?>">
                      <button type="submit">Remove</button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

      <?php elseif ($tab === 'profile'): ?>
        <section class="account-card">
          <div class="account-card-head"><h1>Profile</h1></div>
          <form class="form-stack" method="post" action="<?= e(url('account.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile_update">
            <div class="field-row">
              <div class="field">
                <label for="first_name">First name</label>
                <input id="first_name" type="text" name="first_name" value="<?= e($customer['first_name']) ?>" required maxlength="80" autocomplete="given-name">
              </div>
              <div class="field">
                <label for="last_name">Last name</label>
                <input id="last_name" type="text" name="last_name" value="<?= e((string) $customer['last_name']) ?>" maxlength="80" autocomplete="family-name">
              </div>
            </div>
            <div class="field-row">
              <div class="field">
                <label for="profile_email">Email address</label>
                <input id="profile_email" type="email" value="<?= e($customer['email']) ?>" disabled>
                <p class="field-hint">To change your email address, please contact support.</p>
              </div>
              <div class="field">
                <label for="profile_phone">Mobile number</label>
                <input id="profile_phone" type="tel" name="phone" value="<?= e($customer['phone']) ?>" required maxlength="20" autocomplete="tel">
              </div>
            </div>
            <div class="divider" style="margin:6px 0"></div>
            <h2 style="font-size:1.1rem">Change password</h2>
            <p class="muted small" style="margin:0">Leave these fields blank to keep your current password.</p>
            <div class="field">
              <label for="current_password">Current password</label>
              <input id="current_password" type="password" name="current_password" maxlength="72" autocomplete="current-password">
            </div>
            <div class="field-row">
              <div class="field">
                <label for="new_password">New password</label>
                <input id="new_password" type="password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password">
              </div>
              <div class="field">
                <label for="confirm_password">Confirm new password</label>
                <input id="confirm_password" type="password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password">
              </div>
            </div>
            <p class="field-hint">At least 8 characters, including a letter and a number. Changing your password signs you out of this session.</p>
            <div class="btn-row">
              <button class="btn btn-primary" type="submit">Save changes</button>
            </div>
          </form>
        </section>

      <?php elseif ($tab === 'reviews'): ?>
        <section class="account-card">
          <div class="account-card-head"><h1>My reviews</h1></div>
          <?php if ($reviews === []): ?>
            <div class="empty-state">
              <?= icon('star') ?>
              <p>You have not written any reviews yet. Reviews are welcome after a delivered order.</p>
              <a class="btn btn-primary btn-sm" href="<?= e(url('account.php?tab=orders')) ?>">View orders</a>
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
                    <span class="<?= e(status_class((string) $review['status'])) ?>"><?= e(ucfirst((string) $review['status'])) ?></span>
                  </div>
                  <?php if (!empty($review['title'])): ?><strong><?= e($review['title']) ?></strong><?php endif; ?>
                  <p class="review-body"><?= nl2br(e($review['body'])) ?></p>
                  <?php if ($review['status'] === 'pending'): ?><p class="muted small" style="margin-top:6px">Awaiting moderation — it will appear on the product page once approved.</p><?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

      <?php else: ?>
        <section class="account-card">
          <div class="account-card-head"><h1>Account settings</h1></div>
          <dl class="order-meta-grid">
            <div><dt>Email</dt><dd><?= e($customer['email']) ?></dd></div>
            <div><dt>Mobile</dt><dd><?= e($customer['phone']) ?></dd></div>
            <div><dt>Member since</dt><dd><?= e(date_display($customer['created_at'])) ?></dd></div>
            <div><dt>Account status</dt><dd><?= e(ucfirst(str_replace('_', ' ', (string) $customer['status']))) ?></dd></div>
          </dl>
          <div class="divider"></div>
          <h2 style="font-size:1.15rem">Request account deletion</h2>
          <p class="muted small">Deleting your account is permanent. Orders required for tax and accounting records may be retained as required by law. Our team will confirm the request by email before anything is removed.</p>
          <form method="post" action="<?= e(url('account.php')) ?>" data-confirm="Submit an account deletion request? Our team will review it before taking action.">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="request_deletion">
            <label class="check-line">
              <input type="checkbox" name="confirm_deletion" value="1" required>
              <span>I understand and want to request deletion of my account.</span>
            </label>
            <button class="btn btn-danger btn-sm" type="submit" style="margin-top:14px"><?= icon('trash') ?> Request deletion</button>
          </form>
        </section>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require BASE_PATH . '/app/views/layout/footer.php'; ?>
