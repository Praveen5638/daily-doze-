<?php
declare(strict_types=1);

final class Catalog
{
    private static function baseSelect(): string
    {
        return "SELECT p.*, c.name AS category_name, c.slug AS category_slug,
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id
                ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS primary_image,
            (SELECT MIN(v.price) FROM product_variants v
                WHERE v.product_id = p.id AND v.status = 'active' AND v.price IS NOT NULL) AS min_price,
            (SELECT MIN(v.mrp) FROM product_variants v
                WHERE v.product_id = p.id AND v.status = 'active' AND v.price IS NOT NULL) AS min_mrp,
            (SELECT SUM(i.stock_qty) FROM product_variants v
                JOIN product_inventory i ON i.variant_id = v.id
                WHERE v.product_id = p.id AND v.status = 'active') AS stock_total,
            (SELECT v.id FROM product_variants v
                WHERE v.product_id = p.id AND v.status = 'active' AND v.price IS NOT NULL
                ORDER BY v.is_default DESC, v.price ASC, v.id ASC LIMIT 1) AS default_variant_id,
            (SELECT v.sku FROM product_variants v
                WHERE v.product_id = p.id AND v.status = 'active' AND v.price IS NOT NULL
                ORDER BY v.is_default DESC, v.price ASC, v.id ASC LIMIT 1) AS default_sku,
            (SELECT AVG(r.rating) FROM reviews r WHERE r.product_id = p.id AND r.status = 'approved') AS rating_avg,
            (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id AND r.status = 'approved') AS review_count
            FROM products p JOIN categories c ON c.id = p.category_id";
    }

    public static function categories(bool $activeOnly = true): array
    {
        $sql = 'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.status = "published") AS product_count
                FROM categories c';
        if ($activeOnly) {
            $sql .= ' WHERE c.status = "active"';
        }
        return Database::all($sql . ' ORDER BY c.sort_order ASC, c.name ASC');
    }

    public static function category_by_slug(string $slug): ?array
    {
        return Database::one('SELECT * FROM categories WHERE slug = ? AND status = "active"', [$slug]);
    }

    public static function featured_products(int $limit = 8): array
    {
        $limit = max(1, min(24, $limit));
        return Database::all(
            self::baseSelect() . ' WHERE p.status = "published" AND p.is_featured = 1
             ORDER BY p.updated_at DESC LIMIT ' . $limit
        );
    }

    public static function new_products(int $limit = 8): array
    {
        $limit = max(1, min(24, $limit));
        return Database::all(
            self::baseSelect() . ' WHERE p.status = "published"
             ORDER BY p.is_new DESC, p.created_at DESC LIMIT ' . $limit
        );
    }

    public static function product_by_slug(string $slug): ?array
    {
        $product = Database::one(
            self::baseSelect() . ' WHERE p.slug = ? AND p.status = "published" LIMIT 1',
            [$slug]
        );
        if ($product === null) {
            return null;
        }
        $product['images'] = self::images((int) $product['id']);
        $product['variants'] = self::variants((int) $product['id']);
        $product['rating_avg'] = (float) ($product['rating_avg'] ?? 0);
        $product['review_count'] = (int) ($product['review_count'] ?? 0);
        return $product;
    }

    public static function related_products(int $categoryId, int $excludeId, int $limit = 4): array
    {
        $limit = max(1, min(12, $limit));
        return Database::all(
            self::baseSelect() . ' WHERE p.status = "published" AND p.category_id = ? AND p.id <> ?
             ORDER BY p.is_featured DESC, p.updated_at DESC LIMIT ' . $limit,
            [$categoryId, $excludeId]
        );
    }

    public static function images(int $productId): array
    {
        return Database::all(
            'SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC',
            [$productId]
        );
    }

    public static function variants(int $productId): array
    {
        return Database::all(
            'SELECT v.*, COALESCE(i.stock_qty, 0) AS stock_qty, COALESCE(i.low_stock_threshold, 10) AS low_stock_threshold
             FROM product_variants v LEFT JOIN product_inventory i ON i.variant_id = v.id
             WHERE v.product_id = ? AND v.status = "active"
             ORDER BY v.weight_grams ASC, v.price ASC, v.id ASC',
            [$productId]
        );
    }

    public static function search(array $filters): array
    {
        $where = ['p.status = "published"'];
        $params = [];

        $categorySlug = trim((string) ($filters['category'] ?? ''));
        if ($categorySlug !== '') {
            $where[] = 'c.slug = ?';
            $params[] = $categorySlug;
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $where[] = 'p.product_type = ?';
            $params[] = $type;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        $q = mb_substr($q, 0, 100);
        if ($q !== '') {
            $terms = preg_split('/\s+/', $q, 4) ?: [];
            foreach ($terms as $term) {
                if ($term === '') {
                    continue;
                }
                $like = '%' . $term . '%';
                $where[] = '(p.name LIKE ? OR p.search_aliases LIKE ? OR p.keywords LIKE ? OR p.short_description LIKE ? OR c.name LIKE ?)';
                array_push($params, $like, $like, $like, $like, $like);
            }
        }

        $minPrice = isset($filters['min_price']) && $filters['min_price'] !== '' ? max(0, (float) $filters['min_price']) : null;
        $maxPrice = isset($filters['max_price']) && $filters['max_price'] !== '' ? max(0, (float) $filters['max_price']) : null;
        if ($minPrice !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.status = "active" AND v.price IS NOT NULL AND v.price >= ?)';
            $params[] = $minPrice;
        }
        if ($maxPrice !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.status = "active" AND v.price IS NOT NULL AND v.price <= ?)';
            $params[] = $maxPrice;
        }

        $pack = trim((string) ($filters['pack'] ?? ''));
        if ($pack !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.status = "active" AND v.pack_size = ?)';
            $params[] = $pack;
        }

        if (($filters['availability'] ?? '') === 'in_stock') {
            $where[] = 'EXISTS (SELECT 1 FROM product_variants v JOIN product_inventory i ON i.variant_id = v.id
                        WHERE v.product_id = p.id AND v.status = "active" AND i.stock_qty > 0)';
        }

        $sort = (string) ($filters['sort'] ?? 'featured');
        $orders = [
            'featured' => 'p.is_featured DESC, p.is_new DESC, p.updated_at DESC',
            'newest' => 'p.created_at DESC',
            'price_asc' => 'min_price IS NULL ASC, min_price ASC, p.name ASC',
            'price_desc' => 'min_price IS NULL ASC, min_price DESC, p.name ASC',
            'name' => 'p.name ASC',
            'popular' => '(SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.product_id = p.id) DESC, p.is_featured DESC',
        ];
        $orderBy = $orders[$sort] ?? $orders['featured'];

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = 12;
        $whereSql = implode(' AND ', $where);

        $total = (int) Database::value(
            "SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id WHERE {$whereSql}",
            $params
        );
        $items = Database::all(
            self::baseSelect() . " WHERE {$whereSql} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params
        );

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'per_page' => $perPage,
        ];
    }

    public static function price_bounds(): array
    {
        return Database::one(
            'SELECT MIN(v.price) AS min_price, MAX(v.price) AS max_price
             FROM product_variants v JOIN products p ON p.id = v.product_id
             WHERE v.status = "active" AND v.price IS NOT NULL AND p.status = "published"'
        ) ?? ['min_price' => null, 'max_price' => null];
    }

    public static function pack_sizes(): array
    {
        return array_column(
            Database::all(
                'SELECT v.pack_size FROM product_variants v JOIN products p ON p.id = v.product_id
                 WHERE v.status = "active" AND p.status = "published"
                 GROUP BY v.pack_size
                 ORDER BY MIN(v.weight_grams) ASC, v.pack_size ASC'
            ),
            'pack_size'
        );
    }

    public static function product_types(): array
    {
        return array_column(
            Database::all(
                'SELECT DISTINCT p.product_type FROM products p
                 WHERE p.status = "published" AND p.product_type IS NOT NULL AND p.product_type <> ""
                 ORDER BY p.product_type ASC'
            ),
            'product_type'
        );
    }

    public static function reviews(int $productId, int $limit = 8, int $offset = 0): array
    {
        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);
        return Database::all(
            "SELECT name, rating, title, body, is_verified, created_at FROM reviews
             WHERE product_id = ? AND status = 'approved'
             ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}",
            [$productId]
        );
    }

    public static function testimonials(int $limit = 6): array
    {
        $limit = max(1, min(12, $limit));
        return Database::all(
            "SELECT r.name, r.rating, r.title, r.body, r.created_at, p.name AS product_name, p.slug AS product_slug
             FROM reviews r JOIN products p ON p.id = r.product_id
             WHERE r.status = 'approved' AND CHAR_LENGTH(r.body) > 20
             ORDER BY r.created_at DESC LIMIT {$limit}"
        );
    }

    public static function wishlist_ids(int $customerId): array
    {
        return array_map('intval', array_column(
            Database::all(
                'SELECT wi.product_id FROM wishlist_items wi JOIN wishlists w ON w.id = wi.wishlist_id WHERE w.customer_id = ?',
                [$customerId]
            ),
            'product_id'
        ));
    }

    public static function products_by_ids(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return Database::all(
            self::baseSelect() . " WHERE p.status = 'published' AND p.id IN ({$placeholders})
             ORDER BY FIELD(p.id, {$placeholders})",
            array_merge($ids, $ids)
        );
    }

    public static function sitemap_products(): array
    {
        return Database::all(
            'SELECT p.slug, p.updated_at FROM products p WHERE p.status = "published" ORDER BY p.updated_at DESC'
        );
    }
}
