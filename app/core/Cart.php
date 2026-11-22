<?php
declare(strict_types=1);

final class Cart
{
    private static function customerCartId(bool $create = true): ?int
    {
        $customerId = Auth::customerId();
        if ($customerId === null) {
            return null;
        }
        $id = Database::value('SELECT id FROM carts WHERE customer_id = ?', [$customerId]);
        if ($id === null && $create) {
            $id = Database::insert('carts', ['customer_id' => $customerId]);
        }
        return $id === null ? null : (int) $id;
    }

    public static function raw(): array
    {
        if (Auth::customerId() !== null) {
            $cartId = self::customerCartId(false);
            if ($cartId === null) {
                return [];
            }
            $rows = Database::all('SELECT variant_id, quantity FROM cart_items WHERE cart_id = ?', [$cartId]);
            $raw = [];
            foreach ($rows as $row) {
                $raw[(int) $row['variant_id']] = (int) $row['quantity'];
            }
            return $raw;
        }
        $raw = $_SESSION['guest_cart'] ?? [];
        return is_array($raw) ? array_map('intval', $raw) : [];
    }

    private static function setQuantity(int $variantId, int $quantity): void
    {
        if (Auth::customerId() !== null) {
            $cartId = self::customerCartId(true);
            if ($cartId === null) {
                return;
            }
            if ($quantity <= 0) {
                Database::q('DELETE FROM cart_items WHERE cart_id = ? AND variant_id = ?', [$cartId, $variantId]);
                return;
            }
            Database::q(
                'INSERT INTO cart_items (cart_id, variant_id, quantity) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)',
                [$cartId, $variantId, $quantity]
            );
            return;
        }
        if (!isset($_SESSION['guest_cart']) || !is_array($_SESSION['guest_cart'])) {
            $_SESSION['guest_cart'] = [];
        }
        if ($quantity <= 0) {
            unset($_SESSION['guest_cart'][$variantId]);
        } else {
            $_SESSION['guest_cart'][$variantId] = $quantity;
        }
    }

    public static function add(int $variantId, int $quantity = 1): array
    {
        $quantity = max(1, min(99, $quantity));
        $variant = Database::one(
            'SELECT v.id, v.price, v.status, v.pack_size, p.status AS product_status, p.name,
                    COALESCE(i.stock_qty, 0) AS stock_qty
             FROM product_variants v JOIN products p ON p.id = v.product_id
             LEFT JOIN product_inventory i ON i.variant_id = v.id
             WHERE v.id = ?',
            [$variantId]
        );
        if ($variant === null || $variant['status'] !== 'active' || $variant['product_status'] !== 'published') {
            return ['ok' => false, 'message' => t('cart.flash_unavailable', 'This product is not available right now.')];
        }
        if ($variant['price'] === null) {
            return ['ok' => false, 'message' => t('cart.flash_price_enquiry', 'This pack is priced on request. Please send us an enquiry.')];
        }
        $existing = (int) (self::raw()[$variantId] ?? 0);
        $newQuantity = $existing + $quantity;
        if ($newQuantity > (int) $variant['stock_qty']) {
            $available = (int) $variant['stock_qty'];
            return [
                'ok' => false,
                'message' => $available > 0
                    ? sprintf(t('cart.flash_only_of_pack', 'Only %1$d pack(s) of %2$s are available.'), $available, $variant['pack_size'])
                    : t('cart.flash_out_of_stock', 'This pack is currently out of stock.'),
            ];
        }
        self::setQuantity($variantId, $newQuantity);
        return ['ok' => true, 'message' => t('cart.flash_added', 'Added to cart.'), 'count' => self::count()];
    }

    public static function updateQuantity(int $variantId, int $quantity): array
    {
        if ($quantity <= 0) {
            self::setQuantity($variantId, 0);
            return ['ok' => true, 'message' => t('cart.flash_removed', 'Item removed.'), 'count' => self::count()];
        }
        $quantity = min(99, $quantity);
        $variant = Database::one(
            'SELECT COALESCE(i.stock_qty, 0) AS stock_qty, v.pack_size FROM product_variants v
             LEFT JOIN product_inventory i ON i.variant_id = v.id WHERE v.id = ?',
            [$variantId]
        );
        if ($variant === null) {
            return ['ok' => false, 'message' => t('cart.flash_no_longer', 'This product is no longer available.')];
        }
        if ($quantity > (int) $variant['stock_qty']) {
            return ['ok' => false, 'message' => sprintf(t('cart.flash_only', 'Only %d pack(s) available.'), (int) $variant['stock_qty'])];
        }
        self::setQuantity($variantId, $quantity);
        return ['ok' => true, 'message' => t('cart.flash_updated', 'Cart updated.'), 'count' => self::count()];
    }

    public static function remove(int $variantId): void
    {
        self::setQuantity($variantId, 0);
        if (self::count() === 0) {
            unset($_SESSION['coupon_code']);
        }
    }

    public static function clear(): void
    {
        if (Auth::customerId() !== null) {
            $cartId = self::customerCartId(false);
            if ($cartId !== null) {
                Database::q('DELETE FROM cart_items WHERE cart_id = ?', [$cartId]);
            }
        }
        unset($_SESSION['guest_cart'], $_SESSION['coupon_code']);
    }

    public static function mergeGuestCart(): void
    {
        $guest = $_SESSION['guest_cart'] ?? [];
        if (!is_array($guest) || $guest === []) {
            return;
        }
        foreach ($guest as $variantId => $quantity) {
            self::add((int) $variantId, (int) $quantity);
        }
        unset($_SESSION['guest_cart']);
    }

    public static function count(): int
    {
        $count = 0;
        foreach (self::raw() as $quantity) {
            $count += (int) $quantity;
        }
        return $count;
    }

    public static function detailedItems(): array
    {
        $raw = self::raw();
        if ($raw === []) {
            return [];
        }
        $ids = array_keys($raw);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::all(
            "SELECT v.id AS variant_id, v.product_id, v.pack_size, v.price, v.mrp, v.sku, v.status AS variant_status,
                    COALESCE(i.stock_qty, 0) AS stock_qty, i.low_stock_threshold,
                    p.name, p.slug, p.status AS product_status, p.category_id,
                    c.name AS category_name,
                    (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id
                        ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS primary_image
             FROM product_variants v
             JOIN products p ON p.id = v.product_id
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN product_inventory i ON i.variant_id = v.id
             WHERE v.id IN ({$placeholders})",
            $ids
        );
        $items = [];
        foreach ($rows as $row) {
            $quantity = (int) ($raw[(int) $row['variant_id']] ?? 0);
            if ($quantity <= 0) {
                continue;
            }
            $price = $row['price'] !== null ? (float) $row['price'] : null;
            $lineTotal = $price !== null ? round($price * $quantity, 2) : 0.0;
            $purchasable = $price !== null
                && $row['variant_status'] === 'active'
                && $row['product_status'] === 'published'
                && (int) $row['stock_qty'] > 0;
            $items[] = [
                'variant_id' => (int) $row['variant_id'],
                'product_id' => (int) $row['product_id'],
                'name' => $row['name'],
                'slug' => $row['slug'],
                'pack_size' => $row['pack_size'],
                'sku' => $row['sku'],
                'price' => $price,
                'mrp' => $row['mrp'] !== null ? (float) $row['mrp'] : null,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
                'stock_qty' => (int) $row['stock_qty'],
                'category_id' => (int) $row['category_id'],
                'category_name' => $row['category_name'],
                'image' => $row['primary_image'],
                'purchasable' => $purchasable,
                'stock_issue' => $purchasable && $quantity > (int) $row['stock_qty'],
            ];
        }
        return $items;
    }

    public static function calculate(array $items, ?string $couponCode = null, ?string $email = null, ?int $customerId = null): array
    {
        $subtotal = 0.0;
        $mrpTotal = 0.0;
        foreach ($items as $item) {
            $subtotal += (float) $item['line_total'];
            if ($item['mrp'] !== null) {
                $mrpTotal += (float) $item['mrp'] * (int) $item['quantity'];
            }
        }
        $subtotal = round($subtotal, 2);

        $couponResult = Coupon::evaluate($couponCode, $items, $subtotal, $customerId, $email);
        $discount = $couponResult['ok'] ? (float) $couponResult['discount'] : 0.0;

        $baseShipping = max(0, (float) setting('shipping_flat_rate', '0'));
        $thresholdRaw = setting('free_shipping_threshold', '');
        $threshold = $thresholdRaw !== '' ? (float) $thresholdRaw : 0.0;
        $shipping = $baseShipping;
        if ($threshold > 0 && ($subtotal - $discount) >= $threshold) {
            $shipping = 0.0;
        }

        $taxPercent = max(0, (float) setting('tax_percent', '0'));
        $tax = round(($subtotal - $discount) * ($taxPercent / 100), 2);
        $total = round($subtotal - $discount + $shipping + $tax, 2);

        return [
            'subtotal' => $subtotal,
            'mrp_total' => round($mrpTotal, 2),
            'savings' => max(0, round($mrpTotal - $subtotal, 2)),
            'discount' => $discount,
            'shipping' => $shipping,
            'tax' => $tax,
            'tax_percent' => $taxPercent,
            'total' => max(0, $total),
            'coupon' => $couponResult['coupon'],
            'coupon_ok' => $couponResult['ok'],
            'coupon_message' => $couponResult['message'],
            'free_shipping_threshold' => $threshold,
            'free_shipping_remaining' => $threshold > 0 ? max(0, round($threshold - ($subtotal - $discount), 2)) : 0.0,
        ];
    }

    public static function summary(?string $couponCode = null, ?string $email = null, ?int $customerId = null): array
    {
        $items = self::detailedItems();
        $couponCode ??= $_SESSION['coupon_code'] ?? null;
        $totals = self::calculate($items, $couponCode, $email, $customerId);
        $issues = [];
        foreach ($items as $item) {
            if ($item['price'] === null) {
                $issues[] = $item['name'] . ' (' . $item['pack_size'] . ') is priced on request. Please remove it or send an enquiry.';
            } elseif (!$item['purchasable']) {
                $issues[] = $item['name'] . ' (' . $item['pack_size'] . ') is currently unavailable.';
            } elseif ($item['stock_issue']) {
                $issues[] = 'Only ' . $item['stock_qty'] . ' pack(s) of ' . $item['name'] . ' (' . $item['pack_size'] . ') are available.';
            }
        }
        $totals['items'] = $items;
        $totals['issues'] = $issues;
        $totals['count'] = self::count();
        return $totals;
    }
}
