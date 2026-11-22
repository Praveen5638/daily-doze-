-- LOCAL DEVELOPMENT / QA ONLY — never import into production.
-- These are placeholder prices and stock so the cart and checkout can be exercised.
-- Import only after schema.sql and seed.sql, and replace with real admin-entered data.
UPDATE product_variants
SET price = ROUND(59 + (COALESCE(weight_grams, 100) / 100) * 38, 0),
    mrp = ROUND((59 + (COALESCE(weight_grams, 100) / 100) * 38) * 1.2, 0);

UPDATE product_inventory SET stock_qty = 100, low_stock_threshold = 10;

-- Development coupon (not for production).
INSERT INTO coupons (code, description, type, value, min_order_amount, max_discount, usage_limit, per_customer_limit, applies_to, status)
VALUES ('DDE10', 'Local QA coupon — 10 percent off', 'percent', 10.00, 499.00, 200.00, 100, 3, 'all', 'active');
