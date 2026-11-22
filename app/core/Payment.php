<?php
declare(strict_types=1);

final class Payment
{
    public static function enabled(): bool
    {
        return setting('razorpay_enabled', '0') === '1'
            && RAZORPAY_KEY_ID !== ''
            && RAZORPAY_KEY_SECRET !== '';
    }

    public static function createGatewayOrder(array $order): array
    {
        if (!self::enabled()) {
            return ['ok' => false, 'message' => 'Online payment is not available right now.'];
        }
        $payload = [
            'amount' => (int) round((float) $order['total'] * 100),
            'currency' => 'INR',
            'receipt' => (string) $order['order_number'],
            'notes' => [
                'order_id' => (string) $order['id'],
                'order_number' => (string) $order['order_number'],
            ],
        ];
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 25,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $status < 200 || $status >= 300) {
            log_message('error', 'Razorpay order creation failed', ['status' => $status, 'error' => $error, 'response' => $response]);
            return ['ok' => false, 'message' => 'Could not start the online payment. Please try again or choose cash on delivery.'];
        }
        $data = json_decode((string) $response, true);
        if (!is_array($data) || empty($data['id'])) {
            log_message('error', 'Razorpay response missing order id', ['response' => $response]);
            return ['ok' => false, 'message' => 'The payment gateway returned an unexpected response.'];
        }

        $existing = Database::one(
            'SELECT id FROM payments WHERE order_id = ? AND gateway = "razorpay" AND gateway_payment_id IS NULL ORDER BY id DESC LIMIT 1',
            [$order['id']]
        );
        if ($existing !== null) {
            Database::update('payments', [
                'gateway_order_id' => $data['id'],
                'amount' => (float) $order['total'],
                'payload' => json_encode($data, JSON_UNESCAPED_SLASHES),
            ], 'id = ?', [$existing['id']]);
        } else {
            Database::insert('payments', [
                'order_id' => (int) $order['id'],
                'gateway' => 'razorpay',
                'gateway_order_id' => $data['id'],
                'amount' => (float) $order['total'],
                'currency' => 'INR',
                'status' => 'created',
                'payload' => json_encode($data, JSON_UNESCAPED_SLASHES),
            ]);
        }

        return [
            'ok' => true,
            'gateway_order_id' => $data['id'],
            'key_id' => RAZORPAY_KEY_ID,
            'amount' => $payload['amount'],
            'order_number' => $order['order_number'],
        ];
    }

    public static function verifySignature(string $gatewayOrderId, string $paymentId, string $signature): bool
    {
        if (RAZORPAY_KEY_SECRET === '' || $gatewayOrderId === '' || $paymentId === '' || $signature === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $gatewayOrderId . '|' . $paymentId, RAZORPAY_KEY_SECRET);
        return hash_equals($expected, $signature);
    }

    public static function verifyAndCapture(int $orderId, string $gatewayOrderId, string $paymentId, string $signature): array
    {
        $order = Database::one('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if ($order === null) {
            return ['ok' => false, 'message' => 'Order not found.'];
        }
        if ($order['payment_status'] === 'paid') {
            return ['ok' => true, 'message' => 'Payment already recorded.'];
        }
        $payment = Database::one(
            'SELECT * FROM payments WHERE order_id = ? AND gateway = "razorpay" AND gateway_order_id = ? ORDER BY id DESC LIMIT 1',
            [$orderId, $gatewayOrderId]
        );
        if ($payment === null) {
            return ['ok' => false, 'message' => 'Payment record not found for this order.'];
        }
        if (!self::verifySignature($gatewayOrderId, $paymentId, $signature)) {
            log_message('warning', 'Razorpay signature verification failed', ['order_id' => $orderId]);
            return ['ok' => false, 'message' => 'Payment verification failed. If money was deducted, please contact us with your order number.'];
        }
        return Order::mark_paid($orderId, 'razorpay', $paymentId, [
            'razorpay_order_id' => $gatewayOrderId,
            'razorpay_payment_id' => $paymentId,
            'verified_at' => date('c'),
        ]);
    }

    public static function webhook(string $rawBody, string $signature): array
    {
        if (RAZORPAY_WEBHOOK_SECRET === '') {
            return ['ok' => false, 'message' => 'Webhook secret is not configured.'];
        }
        $expected = hash_hmac('sha256', $rawBody, RAZORPAY_WEBHOOK_SECRET);
        if (!hash_equals($expected, $signature)) {
            return ['ok' => false, 'message' => 'Invalid webhook signature.'];
        }
        $data = json_decode($rawBody, true);
        if (!is_array($data) || empty($data['event'])) {
            return ['ok' => false, 'message' => 'Invalid webhook payload.'];
        }
        if (!in_array($data['event'], ['payment.captured', 'order.paid'], true)) {
            return ['ok' => true, 'message' => 'Event ignored.'];
        }
        $paymentEntity = $data['payload']['payment']['entity'] ?? [];
        $orderId = (int) ($paymentEntity['notes']['order_id'] ?? ($paymentEntity['order_id'] ?? 0));
        if ($orderId <= 0 && !empty($paymentEntity['order_id'])) {
            $orderId = (int) Database::value('SELECT order_id FROM payments WHERE gateway_order_id = ? LIMIT 1', [$paymentEntity['order_id']]);
        }
        $paymentId = (string) ($paymentEntity['id'] ?? '');
        if ($orderId <= 0 || $paymentId === '') {
            return ['ok' => false, 'message' => 'Could not match webhook to an order.'];
        }
        return Order::mark_paid($orderId, 'razorpay', $paymentId, [
            'webhook' => true,
            'event' => $data['event'],
            'razorpay_payment_id' => $paymentId,
        ]);
    }
}
