<?php
declare(strict_types=1);

final class Coupon
{
    public static function evaluate(?string $code, array $items, float $subtotal, ?int $customerId = null, ?string $email = null): array
    {
        $empty = ['ok' => false, 'coupon' => null, 'discount' => 0.0, 'message' => ''];
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return $empty;
        }
        if ($subtotal <= 0) {
            return array_merge($empty, ['message' => 'Add products to your cart before applying a coupon.']);
        }

        $coupon = Database::one('SELECT * FROM coupons WHERE code = ? AND status = "active"', [$code]);
        if ($coupon === null) {
            return array_merge($empty, ['message' => 'This coupon code is not valid.']);
        }
        $now = date('Y-m-d H:i:s');
        if (!empty($coupon['starts_at']) && $coupon['starts_at'] > $now) {
            return array_merge($empty, ['message' => 'This coupon is not active yet.']);
        }
        if (!empty($coupon['expires_at']) && $coupon['expires_at'] < $now) {
            return array_merge($empty, ['message' => 'This coupon has expired.']);
        }
        if ((float) $coupon['min_order_amount'] > $subtotal) {
            return array_merge($empty, [
                'message' => 'This coupon needs a minimum order of ' . money($coupon['min_order_amount']) . '.',
            ]);
        }
        if ($coupon['usage_limit'] !== null) {
            $used = (int) Database::value('SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = ?', [$coupon['id']]);
            if ($used >= (int) $coupon['usage_limit']) {
                return array_merge($empty, ['message' => 'This coupon has reached its usage limit.']);
            }
        }
        if ($coupon['per_customer_limit'] !== null && (int) $coupon['per_customer_limit'] > 0) {
            $conditions = [];
            $params = [$coupon['id']];
            if ($customerId !== null) {
                $conditions[] = 'customer_id = ?';
                $params[] = $customerId;
            }
            if ($email !== null && $email !== '') {
                $conditions[] = 'email = ?';
                $params[] = strtolower(trim($email));
            }
            if ($conditions !== []) {
                $usedByCustomer = (int) Database::value(
                    'SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = ? AND (' . implode(' OR ', $conditions) . ')',
                    $params
                );
                if ($usedByCustomer >= (int) $coupon['per_customer_limit']) {
                    return array_merge($empty, ['message' => 'You have already used this coupon the maximum number of times.']);
                }
            }
        }

        $eligible = 0.0;
        foreach ($items as $item) {
            if (($item['price'] ?? null) === null) {
                continue;
            }
            if ($coupon['applies_to'] === 'category' && (int) $item['category_id'] !== (int) $coupon['category_id']) {
                continue;
            }
            if ($coupon['applies_to'] === 'product' && (int) $item['product_id'] !== (int) $coupon['product_id']) {
                continue;
            }
            $eligible += (float) $item['price'] * (int) $item['quantity'];
        }
        if ($eligible <= 0) {
            return array_merge($empty, ['message' => 'This coupon does not apply to the items in your cart.']);
        }

        $discount = $coupon['type'] === 'percent'
            ? $eligible * ((float) $coupon['value'] / 100)
            : min((float) $coupon['value'], $eligible);
        if ($coupon['max_discount'] !== null && (float) $coupon['max_discount'] > 0) {
            $discount = min($discount, (float) $coupon['max_discount']);
        }
        $discount = round(min($discount, $subtotal), 2);

        return [
            'ok' => true,
            'coupon' => $coupon,
            'discount' => $discount,
            'message' => 'Coupon ' . $coupon['code'] . ' applied — you saved ' . money($discount) . '.',
        ];
    }
}
