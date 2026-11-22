-- Daily Dose Export — MySQL 8+ schema
-- Import with: mysql -u USER -p DB_NAME < database/schema.sql
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs, inventory_transactions, notifications, password_resets,
  team_members, banners, cms_pages, website_settings, contact_messages, newsletter_subscribers,
  reviews, enquiry_items, enquiries, shipping_zones, shipping_methods, coupon_usage, coupons,
  payments, order_items, orders, wishlist_items, wishlists, cart_items, carts,
  product_inventory, product_variants, product_images, products, categories,
  customer_addresses, customers, admin_permissions, admin_roles, admins, rate_limits;

CREATE TABLE admin_roles (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  slug VARCHAR(60) NOT NULL,
  description VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id TINYINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admins_email (email),
  KEY idx_admins_role (role_id),
  CONSTRAINT fk_admins_role FOREIGN KEY (role_id) REFERENCES admin_roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_permissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id TINYINT UNSIGNED NOT NULL,
  permission VARCHAR(80) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_role_permission (role_id, permission),
  CONSTRAINT fk_permissions_role FOREIGN KEY (role_id) REFERENCES admin_roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active','disabled','pending_deletion') NOT NULL DEFAULT 'active',
  email_verified_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customers_email (email),
  KEY idx_customers_phone (phone),
  KEY idx_customers_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_addresses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NOT NULL,
  label VARCHAR(60) NULL,
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  address_type ENUM('home','office','other') NOT NULL DEFAULT 'home',
  house VARCHAR(120) NOT NULL,
  street VARCHAR(190) NOT NULL,
  landmark VARCHAR(190) NULL,
  city VARCHAR(120) NOT NULL,
  state VARCHAR(120) NOT NULL,
  pincode VARCHAR(12) NOT NULL,
  country VARCHAR(80) NOT NULL DEFAULT 'India',
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_addresses_customer (customer_id),
  CONSTRAINT fk_addresses_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL,
  description TEXT NULL,
  image VARCHAR(255) NULL,
  meta_title VARCHAR(190) NULL,
  meta_description VARCHAR(320) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug),
  KEY idx_categories_parent (parent_id),
  KEY idx_categories_status (status),
  CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(200) NOT NULL,
  product_type VARCHAR(80) NULL,
  short_description VARCHAR(500) NULL,
  description MEDIUMTEXT NULL,
  ingredients TEXT NULL,
  packaging_info TEXT NULL,
  storage_instructions TEXT NULL,
  origin VARCHAR(190) NULL,
  manufacturer_info TEXT NULL,
  nutritional_info TEXT NULL,
  allergen_info TEXT NULL,
  fssai_info VARCHAR(255) NULL,
  net_quantity_info VARCHAR(190) NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_new TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('published','draft','archived') NOT NULL DEFAULT 'draft',
  tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  meta_title VARCHAR(190) NULL,
  meta_description VARCHAR(320) NULL,
  keywords VARCHAR(500) NULL,
  search_aliases VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_slug (slug),
  KEY idx_products_category (category_id),
  KEY idx_products_status (status),
  KEY idx_products_featured (is_featured, status),
  KEY idx_products_new (is_new, status),
  FULLTEXT KEY ft_products_search (name, short_description, keywords, search_aliases),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_images (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  alt_text VARCHAR(190) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_images_product (product_id),
  CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_variants (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  sku VARCHAR(80) NOT NULL,
  pack_size VARCHAR(60) NOT NULL,
  weight_grams INT NULL,
  price DECIMAL(10,2) NULL,
  mrp DECIMAL(10,2) NULL,
  cost_price DECIMAL(10,2) NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_variants_sku (sku),
  KEY idx_variants_product (product_id, status),
  CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_inventory (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  variant_id INT UNSIGNED NOT NULL,
  stock_qty INT NOT NULL DEFAULT 0,
  reserved_qty INT NOT NULL DEFAULT 0,
  low_stock_threshold INT NOT NULL DEFAULT 10,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inventory_variant (variant_id),
  CONSTRAINT fk_inventory_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE,
  CONSTRAINT chk_inventory_stock CHECK (stock_qty >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE carts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NULL,
  session_id VARCHAR(128) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_carts_customer (customer_id),
  KEY idx_carts_session (session_id),
  CONSTRAINT fk_carts_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cart_id INT UNSIGNED NOT NULL,
  variant_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cart_variant (cart_id, variant_id),
  KEY idx_cart_items_variant (variant_id),
  CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts (id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wishlists (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wishlists_customer (customer_id),
  CONSTRAINT fk_wishlists_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wishlist_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  wishlist_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wishlist_product (wishlist_id, product_id),
  CONSTRAINT fk_wishlist_items_list FOREIGN KEY (wishlist_id) REFERENCES wishlists (id) ON DELETE CASCADE,
  CONSTRAINT fk_wishlist_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coupons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(40) NOT NULL,
  description VARCHAR(255) NULL,
  type ENUM('fixed','percent') NOT NULL,
  value DECIMAL(10,2) NOT NULL,
  min_order_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  max_discount DECIMAL(10,2) NULL,
  usage_limit INT UNSIGNED NULL,
  per_customer_limit INT UNSIGNED NOT NULL DEFAULT 1,
  applies_to ENUM('all','category','product') NOT NULL DEFAULT 'all',
  category_id INT UNSIGNED NULL,
  product_id INT UNSIGNED NULL,
  starts_at DATETIME NULL,
  expires_at DATETIME NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_coupons_code (code),
  KEY idx_coupons_status (status),
  CONSTRAINT fk_coupons_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
  CONSTRAINT fk_coupons_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shipping_methods (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  base_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  free_over_amount DECIMAL(10,2) NULL,
  estimated_days VARCHAR(60) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shipping_zones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  states TEXT NOT NULL,
  rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  cod_enabled TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_number VARCHAR(30) NOT NULL,
  idempotency_key CHAR(64) NULL,
  customer_id INT UNSIGNED NULL,
  customer_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  shipping_full_name VARCHAR(120) NOT NULL,
  shipping_phone VARCHAR(20) NOT NULL,
  shipping_address_type ENUM('home','office','other') NOT NULL DEFAULT 'home',
  shipping_house VARCHAR(120) NOT NULL,
  shipping_street VARCHAR(190) NOT NULL,
  shipping_landmark VARCHAR(190) NULL,
  shipping_city VARCHAR(120) NOT NULL,
  shipping_state VARCHAR(120) NOT NULL,
  shipping_pincode VARCHAR(12) NOT NULL,
  shipping_country VARCHAR(80) NOT NULL DEFAULT 'India',
  subtotal DECIMAL(10,2) NOT NULL,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  tax_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(10,2) NOT NULL,
  coupon_id INT UNSIGNED NULL,
  coupon_code VARCHAR(40) NULL,
  status ENUM('pending','confirmed','processing','packed','shipped','out_for_delivery','delivered','cancelled','return_requested','returned','refunded') NOT NULL DEFAULT 'pending',
  payment_status ENUM('unpaid','pending','paid','failed','refunded','partially_refunded') NOT NULL DEFAULT 'unpaid',
  payment_method ENUM('cod','razorpay') NOT NULL DEFAULT 'cod',
  payment_reference VARCHAR(120) NULL,
  stock_restored TINYINT(1) NOT NULL DEFAULT 0,
  admin_notes TEXT NULL,
  customer_notes TEXT NULL,
  tracking_number VARCHAR(120) NULL,
  tracking_url VARCHAR(255) NULL,
  shipped_at DATETIME NULL,
  delivered_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_number (order_number),
  UNIQUE KEY uq_orders_idempotency (idempotency_key),
  KEY idx_orders_customer (customer_id),
  KEY idx_orders_status (status),
  KEY idx_orders_created (created_at),
  KEY idx_orders_lookup (email, phone),
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
  CONSTRAINT fk_orders_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  variant_id INT UNSIGNED NULL,
  product_name VARCHAR(190) NOT NULL,
  variant_label VARCHAR(60) NOT NULL,
  sku VARCHAR(80) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  mrp DECIMAL(10,2) NULL,
  quantity INT UNSIGNED NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_order_items_order (order_id),
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  gateway VARCHAR(40) NOT NULL,
  gateway_order_id VARCHAR(120) NULL,
  gateway_payment_id VARCHAR(120) NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'INR',
  status ENUM('created','authorized','captured','failed','refunded') NOT NULL DEFAULT 'created',
  signature_verified TINYINT(1) NOT NULL DEFAULT 0,
  payload JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_payments_order (order_id),
  KEY idx_payments_gateway (gateway_order_id),
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coupon_usage (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  coupon_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  order_id BIGINT UNSIGNED NULL,
  email VARCHAR(190) NULL,
  discount_amount DECIMAL(10,2) NOT NULL,
  used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_coupon_usage_coupon (coupon_id),
  KEY idx_coupon_usage_email (email),
  CONSTRAINT fk_coupon_usage_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE CASCADE,
  CONSTRAINT fk_coupon_usage_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE enquiries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference VARCHAR(24) NOT NULL,
  type ENUM('wholesale','export','contact') NOT NULL DEFAULT 'wholesale',
  name VARCHAR(120) NOT NULL,
  company VARCHAR(190) NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  country VARCHAR(80) NOT NULL DEFAULT 'India',
  city VARCHAR(120) NULL,
  product_interest VARCHAR(255) NULL,
  quantity VARCHAR(120) NULL,
  packaging VARCHAR(190) NULL,
  buyer_type ENUM('retail','wholesale','export') NOT NULL DEFAULT 'wholesale',
  destination_port VARCHAR(190) NULL,
  message TEXT NULL,
  status ENUM('new','contacted','quotation_sent','negotiation','converted','closed') NOT NULL DEFAULT 'new',
  admin_notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_enquiries_reference (reference),
  KEY idx_enquiries_status (status),
  KEY idx_enquiries_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE enquiry_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  enquiry_id BIGINT UNSIGNED NOT NULL,
  product_name VARCHAR(190) NOT NULL,
  quantity VARCHAR(120) NULL,
  packaging VARCHAR(190) NULL,
  notes VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_enquiry_items_enquiry (enquiry_id),
  CONSTRAINT fk_enquiry_items_enquiry FOREIGN KEY (enquiry_id) REFERENCES enquiries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  order_id BIGINT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NULL,
  rating TINYINT UNSIGNED NOT NULL,
  title VARCHAR(190) NULL,
  body TEXT NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reviews_product (product_id, status),
  KEY idx_reviews_status (status),
  CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
  CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE newsletter_subscribers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  status ENUM('subscribed','unsubscribed') NOT NULL DEFAULT 'subscribed',
  token CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_newsletter_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NULL,
  subject VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('new','read','replied','closed') NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contact_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE website_settings (
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  setting_group VARCHAR(40) NOT NULL DEFAULT 'general',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cms_pages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(140) NOT NULL,
  body MEDIUMTEXT NULL,
  meta_title VARCHAR(190) NULL,
  meta_description VARCHAR(320) NULL,
  status ENUM('published','draft') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cms_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE banners (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(190) NULL,
  subtitle VARCHAR(255) NULL,
  image VARCHAR(255) NULL,
  link_url VARCHAR(255) NULL,
  placement ENUM('home_announcement','home_hero','home_mid','shop_top') NOT NULL DEFAULT 'home_mid',
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE team_members (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  position VARCHAR(120) NOT NULL,
  bio TEXT NULL,
  image VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NULL,
  admin_id INT UNSIGNED NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_password_token (token_hash),
  KEY idx_password_customer (customer_id),
  CONSTRAINT fk_password_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
  CONSTRAINT fk_password_admin FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NULL,
  type VARCHAR(40) NOT NULL,
  title VARCHAR(190) NOT NULL,
  message VARCHAR(500) NULL,
  link VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_admin (admin_id, is_read),
  CONSTRAINT fk_notifications_admin FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(60) NULL,
  entity_id VARCHAR(60) NULL,
  details JSON NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_admin (admin_id),
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_transactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  variant_id INT UNSIGNED NOT NULL,
  change_qty INT NOT NULL,
  reason ENUM('order_reserved','order_cancelled','return_restock','refund_restock','manual_adjustment','stock_received') NOT NULL,
  reference_type VARCHAR(40) NULL,
  reference_id VARCHAR(60) NULL,
  admin_id INT UNSIGNED NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_invtx_variant (variant_id),
  KEY idx_invtx_reference (reference_type, reference_id),
  CONSTRAINT fk_invtx_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE,
  CONSTRAINT fk_invtx_admin FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket_key VARCHAR(190) NOT NULL,
  action VARCHAR(60) NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  window_start DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rate_bucket (bucket_key, action),
  KEY idx_rate_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
