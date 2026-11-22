<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

/**
 * Storefront JSON API.
 * Actions: cart.add, cart.update, cart.remove, coupon.apply, coupon.remove,
 *          wishlist.toggle, newsletter.subscribe, razorpay.create, razorpay.verify,
 *          payment.webhook.
 */

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');

function api_fail(string $message, int $status = 422, array $extra = []): never
{
    json_response(array_merge(['ok' => false, 'message' => $message], $extra), $status);
}

function api_ok(array $data = []): never
{
    json_response(['ok' => true, 'data' => $data]);
}

function api_rate_limit(string $bucket, string $action, int $limit, int $window): void
{
    if (!RateLimiter::hit($bucket, $action, $limit, $window)) {
        $seconds = max(1, RateLimiter::retryAfter($bucket, $action, $window));
        header('Retry-After: ' . $seconds);
        api_fail('Too many requests. Please wait a moment and try again.', 429);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    api_fail('This endpoint accepts POST requests only.', 405);
}

$action = isset($_GET['action']) && is_string($_GET['action']) ? trim($_GET['action']) : '';
$allowed = [
    'cart.add', 'cart.update', 'cart.remove', 'cart.get',
    'coupon.apply', 'coupon.remove',
    'wishlist.toggle', 'newsletter.subscribe',
    'razorpay.create', 'razorpay.verify', 'payment.webhook',
];
if (!in_array($action, $allowed, true)) {
    api_fail('Unknown action.', 404);
}

$rawBody = (string) file_get_contents('php://input');
if (strlen($rawBody) > 65536) {
    api_fail('Request is too large.', 413);
}
$payload = [];
if ($rawBody !== '') {
    $decoded = json_decode($rawBody, true);
    if (!is_array($decoded)) {
        api_fail('Invalid request body.', 400);
    }
    $payload = $decoded;
}

/* Razorpay webhooks are authenticated with an HMAC signature, not a browser session. */
if ($action === 'payment.webhook') {
    $signature = (string) ($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');
    try {
        $result = Payment::webhook($rawBody, $signature);
    } catch (Throwable $e) {
        log_message('error', 'Payment webhook error', ['error' => $e->getMessage()]);
        api_fail('Webhook could not be processed.', 500);
    }
    if (!$result['ok']) {
        api_fail($result['message'], 400);
    }
    api_ok(['message' => $result['message']]);
}

$token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_token'] ?? ''));
if ($token === '' || !Csrf::check($token)) {
    api_fail('Your session has expired. Please refresh the page and try again.', 419);
}

try {
    switch ($action) {
        case 'cart.add':
            $variantId = filter_var($payload['variant_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $quantity = filter_var($payload['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
            if ($variantId === false) {
                api_fail('Please choose a valid pack.');
            }
            $result = Cart::add((int) $variantId, $quantity === false ? 1 : (int) $quantity);
            if (!$result['ok']) {
                api_fail($result['message']);
            }
            api_ok([
                'message' => $result['message'],
                'cart_count' => Cart::count(),
            ]);

        case 'cart.update':
            $variantId = filter_var($payload['item_key'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $quantity = filter_var($payload['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]);
            if ($variantId === false || $quantity === false) {
                api_fail('Please choose a valid quantity.');
            }
            $result = Cart::updateQuantity((int) $variantId, (int) $quantity);
            if (!$result['ok']) {
                api_fail($result['message']);
            }
            api_ok([
                'message' => $result['message'],
                'cart_count' => Cart::count(),
            ]);

        case 'cart.remove':
            $variantId = filter_var($payload['item_key'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($variantId === false) {
                api_fail('This item could not be found.');
            }
            Cart::remove((int) $variantId);
            api_ok(['message' => 'Item removed.', 'cart_count' => Cart::count()]);

        case 'cart.get':
            $summary = Cart::summary();
            api_ok(['cart_count' => $summary['count']]);

        case 'coupon.apply':
            $code = strtoupper(trim((string) ($payload['code'] ?? '')));
            if ($code === '' || strlen($code) > 40) {
                api_fail('Please enter a coupon code.');
            }
            $items = Cart::detailedItems();
            if ($items === []) {
                api_fail('Add products to your cart before applying a coupon.');
            }
            $subtotal = 0.0;
            foreach ($items as $item) {
                $subtotal += (float) $item['line_total'];
            }
            $result = Coupon::evaluate($code, $items, $subtotal, Auth::customerId(), Auth::customer()['email'] ?? null);
            if (!$result['ok']) {
                api_fail($result['message'] !== '' ? $result['message'] : 'This coupon cannot be applied.');
            }
            $_SESSION['coupon_code'] = $code;
            api_ok([
                'message' => $result['message'],
                'code' => $code,
                'discount' => $result['discount'],
                'cart_count' => Cart::count(),
            ]);

        case 'coupon.remove':
            unset($_SESSION['coupon_code']);
            api_ok(['message' => 'Coupon removed.', 'cart_count' => Cart::count()]);

        case 'wishlist.toggle':
            $customerId = Auth::customerId();
            if ($customerId === null) {
                api_fail('Please log in to save items to your wishlist.', 401, [
                    'require_login' => true,
                    'login_url' => url('login.php?next=' . urlencode('account.php?tab=wishlist')),
                ]);
            }
            $productId = filter_var($payload['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($productId === false) {
                api_fail('This product could not be found.');
            }
            $exists = Database::one('SELECT id FROM products WHERE id = ? AND status = "published"', [$productId]);
            if ($exists === null) {
                api_fail('This product is not available.', 404);
            }
            $wishlistId = Database::value('SELECT id FROM wishlists WHERE customer_id = ?', [$customerId]);
            if ($wishlistId === null) {
                $wishlistId = Database::insert('wishlists', ['customer_id' => $customerId]);
            }
            $existing = Database::one(
                'SELECT id FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?',
                [$wishlistId, $productId]
            );
            if ($existing !== null) {
                Database::q('DELETE FROM wishlist_items WHERE id = ?', [$existing['id']]);
                $active = false;
            } else {
                Database::insert('wishlist_items', [
                    'wishlist_id' => (int) $wishlistId,
                    'product_id' => (int) $productId,
                ]);
                $active = true;
            }
            api_ok([
                'active' => $active,
                'wishlist_count' => count(Catalog::wishlist_ids($customerId)),
            ]);

        case 'newsletter.subscribe':
            api_rate_limit('newsletter:' . request_ip(), 'subscribe', 8, 3600);
            $email = strtolower(trim((string) ($payload['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
                api_fail('Please enter a valid email address.');
            }
            $existing = Database::one('SELECT id, status, token FROM newsletter_subscribers WHERE email = ?', [$email]);
            if ($existing === null) {
                $token = bin2hex(random_bytes(32));
                Database::insert('newsletter_subscribers', [
                    'email' => $email,
                    'status' => 'subscribed',
                    'token' => $token,
                ]);
                Mailer::send(
                    $email,
                    'Welcome to ' . setting('site_name', 'Daily Dose Export'),
                    email_wrap(
                        'You are on the list',
                        '<p>Thank you for subscribing to updates from ' . e(setting('site_name', 'Daily Dose Export')) . '.</p>'
                        . '<p>' . e(setting('newsletter_text', 'Occasional updates only. Unsubscribe anytime.')) . '</p>'
                        . '<p style="font-size:12px;color:#737A73">If you did not subscribe, you can <a href="' . e(url('newsletter-unsubscribe.php?token=' . $token)) . '">unsubscribe here</a>.</p>',
                        ['url' => url('shop.php'), 'label' => 'Explore the shop']
                    )
                );
            } elseif ($existing['status'] !== 'subscribed') {
                Database::update('newsletter_subscribers', ['status' => 'subscribed'], 'id = ?', [$existing['id']]);
            }
            api_ok(['message' => 'Thank you for subscribing. Check your inbox for a confirmation.']);

        case 'razorpay.create':
            if (!Payment::enabled()) {
                api_fail('Online payment is not available right now.');
            }
            $orderId = filter_var($payload['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($orderId === false || !Order::can_view((int) $orderId)) {
                api_fail('This order could not be found.', 404);
            }
            $order = Order::find((int) $orderId);
            if ($order === null) {
                api_fail('This order could not be found.', 404);
            }
            if ($order['payment_method'] !== 'razorpay') {
                api_fail('This order is not set up for online payment.');
            }
            if ($order['payment_status'] === 'paid') {
                api_ok(['paid' => true, 'redirect' => url('order-success.php?order=' . $order['id'] . '&payment=paid')]);
            }
            if ($order['status'] === 'cancelled') {
                api_fail('This order has been cancelled.');
            }
            $existing = Database::one(
                'SELECT gateway_order_id FROM payments WHERE order_id = ? AND gateway = "razorpay" AND gateway_order_id IS NOT NULL ORDER BY id DESC LIMIT 1',
                [$orderId]
            );
            if ($existing !== null) {
                api_ok([
                    'key_id' => RAZORPAY_KEY_ID,
                    'gateway_order_id' => $existing['gateway_order_id'],
                    'amount' => (int) round((float) $order['total'] * 100),
                    'order_number' => $order['order_number'],
                ]);
            }
            $gateway = Payment::createGatewayOrder($order);
            if (!$gateway['ok']) {
                api_fail($gateway['message']);
            }
            api_ok($gateway);

        case 'razorpay.verify':
            $orderId = filter_var($payload['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $gatewayOrderId = trim((string) ($payload['razorpay_order_id'] ?? ''));
            $paymentId = trim((string) ($payload['razorpay_payment_id'] ?? ''));
            $signature = trim((string) ($payload['razorpay_signature'] ?? ''));
            if ($orderId === false || $gatewayOrderId === '' || $paymentId === '' || $signature === '') {
                api_fail('Payment details are incomplete.');
            }
            if (!Order::can_view((int) $orderId)) {
                api_fail('This order could not be found.', 404);
            }
            $result = Payment::verifyAndCapture((int) $orderId, $gatewayOrderId, $paymentId, $signature);
            if (!$result['ok']) {
                api_fail($result['message']);
            }
            api_ok([
                'message' => $result['message'],
                'redirect' => url('order-success.php?order=' . $orderId . '&payment=paid'),
            ]);
    }
    api_fail('Unknown action.', 404);
} catch (Throwable $e) {
    log_message('error', 'API action failed', ['action' => $action, 'error' => $e->getMessage()]);
    $message = APP_DEBUG ? 'Server error: ' . $e->getMessage() : 'Something went wrong. Please try again.';
    api_fail($message, 500);
}
