<?php
declare(strict_types=1);

final class Order
{
    public static function create(array $data, string $idempotencyKey): array
    {
        $idempotencyKey = trim($idempotencyKey);
        if (!preg_match('/^[a-f0-9]{16,64}$/i', $idempotencyKey)) {
            return ['ok' => false, 'message' => 'Your checkout session expired. Please reload the page and try again.'];
        }
        $existing = Database::one('SELECT * FROM orders WHERE idempotency_key = ?', [$idempotencyKey]);
        if ($existing !== null) {
            return ['ok' => true, 'order' => $existing, 'duplicate' => true];
        }

        $name = trim((string) ($data['customer_name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phone = preg_replace('/[^0-9+]/', '', (string) ($data['phone'] ?? '')) ?? '';
        if ($name === '' || mb_strlen($name) > 120) {
            return ['ok' => false, 'message' => 'Please enter the customer name.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Please enter a valid email address for order updates.'];
        }
        if (strlen(preg_replace('/\D/', '', $phone) ?? '') < 10) {
            return ['ok' => false, 'message' => 'Please enter a valid mobile number.'];
        }
        $required = ['shipping_house' => 'house or flat number', 'shipping_street' => 'street or locality', 'shipping_city' => 'city', 'shipping_state' => 'state', 'shipping_pincode' => 'PIN code'];
        foreach ($required as $field => $label) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                return ['ok' => false, 'message' => 'Please enter your ' . $label . '.'];
            }
        }

        $paymentMethod = (string) ($data['payment_method'] ?? 'cod');
        if (!in_array($paymentMethod, ['cod', 'razorpay'], true)) {
            return ['ok' => false, 'message' => 'Please choose a payment method.'];
        }
        if ($paymentMethod === 'cod' && setting('cod_enabled', '0') !== '1') {
            return ['ok' => false, 'message' => 'Cash on delivery is not available for this order. Please pay online.'];
        }
        if ($paymentMethod === 'razorpay' && !Payment::enabled()) {
            return ['ok' => false, 'message' => 'Online payment is not available right now.'];
        }

        $items = Cart::detailedItems();
        if ($items === []) {
            return ['ok' => false, 'message' => 'Your cart is empty.'];
        }
        foreach ($items as $item) {
            if (!$item['purchasable']) {
                return ['ok' => false, 'message' => $item['name'] . ' (' . $item['pack_size'] . ') is not available. Please update your cart.'];
            }
        }

        $couponCode = $_SESSION['coupon_code'] ?? null;
        $customerId = Auth::customerId();
        $pdo = Database::pdo();

        try {
            $pdo->beginTransaction();

            $doubleCheck = Database::one('SELECT * FROM orders WHERE idempotency_key = ?', [$idempotencyKey]);
            if ($doubleCheck !== null) {
                $pdo->commit();
                return ['ok' => true, 'order' => $doubleCheck, 'duplicate' => true];
            }

            $lockedItems = [];
            foreach ($items as $item) {
                $variant = Database::one(
                    'SELECT v.id, v.product_id, v.price, v.mrp, v.sku, v.pack_size, v.status AS variant_status,
                            p.name, p.status AS product_status, p.category_id,
                            COALESCE(i.stock_qty, 0) AS stock_qty
                     FROM product_variants v JOIN products p ON p.id = v.product_id
                     LEFT JOIN product_inventory i ON i.variant_id = v.id
                     WHERE v.id = ? FOR UPDATE',
                    [$item['variant_id']]
                );
                if ($variant === null || $variant['variant_status'] !== 'active'
                    || $variant['product_status'] !== 'published' || $variant['price'] === null) {
                    throw new RuntimeException($item['name'] . ' is no longer available. Please review your cart.');
                }
                $quantity = (int) $item['quantity'];
                if ($quantity > (int) $variant['stock_qty']) {
                    throw new RuntimeException('Only ' . (int) $variant['stock_qty'] . ' pack(s) of ' . $variant['name'] . ' are available.');
                }
                $price = (float) $variant['price'];
                $lockedItems[] = [
                    'variant_id' => (int) $variant['id'],
                    'product_id' => (int) $variant['product_id'],
                    'category_id' => (int) $variant['category_id'],
                    'name' => $variant['name'],
                    'pack_size' => $variant['pack_size'],
                    'sku' => $variant['sku'],
                    'price' => $price,
                    'mrp' => $variant['mrp'] !== null ? (float) $variant['mrp'] : null,
                    'quantity' => $quantity,
                    'line_total' => round($price * $quantity, 2),
                ];
            }

            $totals = Cart::calculate($lockedItems, is_string($couponCode) ? $couponCode : null, $email, $customerId);
            if ($couponCode !== null && $couponCode !== '' && !$totals['coupon_ok']) {
                throw new RuntimeException($totals['coupon_message'] !== '' ? $totals['coupon_message'] : 'The coupon is no longer valid. Please remove it and try again.');
            }

            $orderNumber = unique_code('DDE', 'orders', 'order_number');
            $orderId = Database::insert('orders', [
                'order_number' => $orderNumber,
                'idempotency_key' => $idempotencyKey,
                'customer_id' => $customerId,
                'customer_name' => $name,
                'email' => $email,
                'phone' => $phone,
                'shipping_full_name' => trim((string) ($data['shipping_full_name'] ?? $name)),
                'shipping_phone' => preg_replace('/[^0-9+]/', '', (string) ($data['shipping_phone'] ?? $phone)) ?: $phone,
                'shipping_address_type' => in_array($data['shipping_address_type'] ?? '', ['home', 'office', 'other'], true) ? $data['shipping_address_type'] : 'home',
                'shipping_house' => trim((string) $data['shipping_house']),
                'shipping_street' => trim((string) $data['shipping_street']),
                'shipping_landmark' => trim((string) ($data['shipping_landmark'] ?? '')),
                'shipping_city' => trim((string) $data['shipping_city']),
                'shipping_state' => trim((string) $data['shipping_state']),
                'shipping_pincode' => trim((string) $data['shipping_pincode']),
                'shipping_country' => trim((string) ($data['shipping_country'] ?? 'India')),
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'shipping_fee' => $totals['shipping'],
                'tax_total' => $totals['tax'],
                'total' => $totals['total'],
                'coupon_id' => $totals['coupon']['id'] ?? null,
                'coupon_code' => $totals['coupon']['code'] ?? null,
                'status' => 'pending',
                'payment_status' => $paymentMethod === 'cod' ? 'unpaid' : 'pending',
                'payment_method' => $paymentMethod,
                'customer_notes' => trim((string) ($data['customer_notes'] ?? '')),
            ]);

            foreach ($lockedItems as $item) {
                Database::insert('order_items', [
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'],
                    'product_name' => $item['name'],
                    'variant_label' => $item['pack_size'],
                    'sku' => $item['sku'],
                    'unit_price' => $item['price'],
                    'mrp' => $item['mrp'],
                    'quantity' => $item['quantity'],
                    'line_total' => $item['line_total'],
                ]);
                $updated = Database::q(
                    'UPDATE product_inventory SET stock_qty = stock_qty - ? WHERE variant_id = ? AND stock_qty >= ?',
                    [$item['quantity'], $item['variant_id'], $item['quantity']]
                )->rowCount();
                if ($updated !== 1) {
                    throw new RuntimeException('Stock for ' . $item['name'] . ' changed while placing the order. Please review your cart.');
                }
                Database::insert('inventory_transactions', [
                    'variant_id' => $item['variant_id'],
                    'change_qty' => -$item['quantity'],
                    'reason' => 'order_reserved',
                    'reference_type' => 'order',
                    'reference_id' => (string) $orderId,
                    'note' => $orderNumber,
                ]);
            }

            if (!empty($totals['coupon']['id'])) {
                Database::insert('coupon_usage', [
                    'coupon_id' => (int) $totals['coupon']['id'],
                    'customer_id' => $customerId,
                    'order_id' => $orderId,
                    'email' => $email,
                    'discount_amount' => $totals['discount'],
                ]);
            }

            $pdo->commit();
            $order = self::find($orderId);
            if ($order === null) {
                return ['ok' => false, 'message' => 'The order could not be created. Please try again.'];
            }

            Cart::clear();
            self::remember($orderId);
            Notifications::admin(
                'new_order',
                'New order ' . $orderNumber,
                $name . ' placed an order for ' . money($order['total']) . ' via ' . ($paymentMethod === 'cod' ? 'COD' : 'online payment') . '.',
                'orders.php?status=pending'
            );
            self::sendOrderEmail($order, 'placed');
            Mailer::sendAdmin(
                'New order ' . $orderNumber,
                email_wrap(
                    'New order received',
                    '<p><strong>' . e($orderNumber) . '</strong> — ' . money($order['total']) . '</p><p>Customer: ' . e($name) . ' (' . e($email) . ', ' . e($phone) . ')</p>',
                    ['url' => url('admin/order-view.php?id=' . $orderId), 'label' => 'Open in admin']
                )
            );
            return ['ok' => true, 'order' => $order];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof RuntimeException) {
                return ['ok' => false, 'message' => $e->getMessage()];
            }
            log_message('error', 'Order creation failed', ['error' => $e->getMessage()]);
            return ['ok' => false, 'message' => 'We could not place the order. Please try again or contact us.'];
        }
    }

    public static function remember(int $orderId): void
    {
        $recent = $_SESSION['recent_orders'] ?? [];
        if (!is_array($recent)) {
            $recent = [];
        }
        $recent[] = $orderId;
        $_SESSION['recent_orders'] = array_values(array_unique(array_map('intval', $recent)));
    }

    public static function can_view(int $orderId): bool
    {
        if (Auth::admin() !== null) {
            return true;
        }
        $recent = $_SESSION['recent_orders'] ?? [];
        if (is_array($recent) && in_array($orderId, array_map('intval', $recent), true)) {
            return true;
        }
        $order = Database::one('SELECT customer_id FROM orders WHERE id = ?', [$orderId]);
        $customerId = Auth::customerId();
        return $order !== null && $customerId !== null && (int) $order['customer_id'] === $customerId;
    }

    public static function find(int $orderId): ?array
    {
        $order = Database::one('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if ($order === null) {
            return null;
        }
        $order['items'] = Database::all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC', [$orderId]);
        $order['payments'] = Database::all('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC', [$orderId]);
        return $order;
    }

    public static function lookup(string $orderNumber, string $contact): ?array
    {
        $orderNumber = strtoupper(trim($orderNumber));
        $contact = trim($contact);
        $email = strtolower($contact);
        $digits = preg_replace('/\D/', '', $contact) ?? '';
        $order = Database::one(
            'SELECT * FROM orders WHERE order_number = ? AND (LOWER(email) = ? OR REPLACE(REPLACE(phone, " ", ""), "+", "") LIKE ?)',
            [$orderNumber, $email, $digits !== '' ? '%' . $digits . '%' : '__no_match__']
        );
        if ($order === null) {
            return null;
        }
        $order['items'] = Database::all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC', [$order['id']]);
        return $order;
    }

    public static function update_status(int $orderId, string $status, array $extra = []): array
    {
        $valid = ['pending', 'confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery', 'delivered', 'cancelled', 'return_requested', 'returned', 'refunded'];
        if (!in_array($status, $valid, true)) {
            return ['ok' => false, 'message' => 'Invalid order status.'];
        }
        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            $order = Database::one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            if ($order === null) {
                throw new RuntimeException('Order not found.');
            }
            if ($order['status'] === $status && !isset($extra['force'])) {
                $pdo->commit();
                return ['ok' => true, 'message' => 'No change.'];
            }

            $sets = ['status' => $status];
            if (!empty($extra['tracking_number'])) {
                $sets['tracking_number'] = trim((string) $extra['tracking_number']);
            }
            if (!empty($extra['tracking_url'])) {
                $sets['tracking_url'] = trim((string) $extra['tracking_url']);
            }
            if (isset($extra['admin_notes'])) {
                $sets['admin_notes'] = trim((string) $extra['admin_notes']);
            }
            if (in_array($status, ['shipped', 'out_for_delivery'], true) && empty($order['shipped_at'])) {
                $sets['shipped_at'] = date('Y-m-d H:i:s');
            }
            if ($status === 'delivered' && empty($order['delivered_at'])) {
                $sets['delivered_at'] = date('Y-m-d H:i:s');
            }
            if ($status === 'cancelled' && empty($order['cancelled_at'])) {
                $sets['cancelled_at'] = date('Y-m-d H:i:s');
            }
            if ($status === 'refunded') {
                $sets['payment_status'] = 'refunded';
            }

            if (in_array($status, ['cancelled', 'returned', 'refunded'], true) && (int) $order['stock_restored'] === 0) {
                $items = Database::all('SELECT variant_id, quantity, product_name FROM order_items WHERE order_id = ?', [$orderId]);
                foreach ($items as $item) {
                    if (empty($item['variant_id'])) {
                        continue;
                    }
                    Database::q(
                        'UPDATE product_inventory SET stock_qty = stock_qty + ? WHERE variant_id = ?',
                        [(int) $item['quantity'], (int) $item['variant_id']]
                    );
                    Database::insert('inventory_transactions', [
                        'variant_id' => (int) $item['variant_id'],
                        'change_qty' => (int) $item['quantity'],
                        'reason' => $status === 'cancelled' ? 'order_cancelled' : 'refund_restock',
                        'reference_type' => 'order',
                        'reference_id' => (string) $orderId,
                        'admin_id' => Auth::admin()['id'] ?? null,
                        'note' => 'Status: ' . $status,
                    ]);
                }
                $sets['stock_restored'] = 1;
            }

            Database::update('orders', $sets, 'id = ?', [$orderId]);
            if ($status === 'cancelled' && $order['payment_status'] === 'paid') {
                Notifications::admin('refund_due', 'Refund due for cancelled order ' . $order['order_number'], 'The order was prepaid. Record the refund after completing it in the payment dashboard.', 'orders.php');
            }
            $pdo->commit();

            $fresh = self::find($orderId);
            if ($fresh !== null) {
                self::sendOrderEmail($fresh, $status);
            }
            return ['ok' => true, 'message' => 'Order updated to ' . order_status_label($status) . '.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            log_message('error', 'Order status update failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            return ['ok' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Could not update the order.'];
        }
    }

    public static function mark_paid(int $orderId, string $gateway, string $paymentId, array $payload = []): array
    {
        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            $order = Database::one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            if ($order === null) {
                throw new RuntimeException('Order not found.');
            }
            if ($order['payment_status'] === 'paid') {
                $pdo->commit();
                return ['ok' => true, 'message' => 'Payment already recorded.'];
            }
            Database::q(
                'UPDATE payments SET status = "captured", signature_verified = 1, gateway_payment_id = ?, payload = ?
                 WHERE order_id = ? AND gateway = ? AND gateway_payment_id IS NULL ORDER BY id DESC LIMIT 1',
                [$paymentId, json_encode($payload, JSON_UNESCAPED_SLASHES), $orderId, $gateway]
            );
            Database::update('orders', [
                'payment_status' => 'paid',
                'payment_reference' => $paymentId,
                'status' => $order['status'] === 'pending' ? 'confirmed' : $order['status'],
            ], 'id = ?', [$orderId]);
            $pdo->commit();

            $fresh = self::find($orderId);
            if ($fresh !== null) {
                Database::update('payments', ['payload' => json_encode($payload, JSON_UNESCAPED_SLASHES)], 'order_id = ? AND gateway_payment_id = ?', [$orderId, $paymentId]);
                self::sendOrderEmail($fresh, 'paid');
            }
            Notifications::admin('payment_received', 'Payment received for ' . $order['order_number'], money($order['total']) . ' received via ' . $gateway . '.', 'order-view.php?id=' . $orderId);
            return ['ok' => true, 'message' => 'Payment recorded.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            log_message('error', 'Payment recording failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            return ['ok' => false, 'message' => 'Could not record the payment.'];
        }
    }

    public static function mark_failed(int $orderId, string $note = ''): void
    {
        Database::update('orders', ['payment_status' => 'failed'], 'id = ? AND payment_status <> "paid"', [$orderId]);
        Database::update('payments', ['status' => 'failed'], 'order_id = ? AND status = "created"', [$orderId]);
        if ($note !== '') {
            log_message('warning', 'Payment failed', ['order_id' => $orderId, 'note' => $note]);
        }
    }

    public static function sendOrderEmail(array $order, string $event): void
    {
        $email = (string) $order['email'];
        $site = setting('site_name', 'Daily Dose Export');
        $titles = [
            'placed' => 'We have received your order',
            'paid' => 'Payment received — order confirmed',
            'confirmed' => 'Your order is confirmed',
            'shipped' => 'Your order has been shipped',
            'delivered' => 'Your order has been delivered',
            'cancelled' => 'Your order has been cancelled',
            'refunded' => 'Your refund has been recorded',
            'packed' => 'Your order is packed',
            'processing' => 'Your order is being prepared',
        ];
        if (!isset($titles[$event])) {
            return;
        }
        $rows = '';
        foreach ($order['items'] as $item) {
            $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #E5DFD0">' . e($item['product_name']) . '<br><span style="font-size:12px;color:#737A73">' . e($item['variant_label']) . ' × ' . (int) $item['quantity'] . '</span></td>'
                . '<td align="right" style="padding:8px 0;border-bottom:1px solid #E5DFD0">' . money($item['line_total']) . '</td></tr>';
        }
        $tracking = '';
        if (!empty($order['tracking_number'])) {
            $tracking = '<p>Tracking number: <strong>' . e($order['tracking_number']) . '</strong>';
            if (!empty($order['tracking_url'])) {
                $tracking .= '<br><a href="' . e($order['tracking_url']) . '">Track your parcel</a>';
            }
            $tracking .= '</p>';
        }
        $paymentNote = $order['payment_method'] === 'razorpay' && $order['payment_status'] !== 'paid'
            ? '<p>Your order will be confirmed after successful payment.</p>'
            : ($order['payment_method'] === 'cod' ? '<p>Payment method: Cash on delivery (subject to confirmation).</p>' : '');
        $body = '<p>Hello ' . e($order['customer_name']) . ',</p>'
            . '<p>Order <strong>' . e($order['order_number']) . '</strong> — status: ' . e(order_status_label((string) $order['status'])) . '.</p>'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;margin:16px 0">' . $rows
            . '<tr><td style="padding:10px 0"><strong>Total</strong></td><td align="right" style="padding:10px 0"><strong>' . money($order['total']) . '</strong></td></tr></table>'
            . $paymentNote . $tracking
            . '<p style="font-size:13px;color:#737A73">' . e(full_address($order)) . '</p>';
        Mailer::send($email, $titles[$event] . ' — ' . $order['order_number'], email_wrap($titles[$event], $body, ['url' => url('account.php?tab=orders&order=' . $order['id']), 'label' => 'View order']));
    }
}
