-- Cafe mode: shared shop QR, per-customer sessions, email OTP anti-spam
-- Run once on existing Hostinger DB (schema_patch.php also applies on connect).

ALTER TABLE shops
  ADD COLUMN ordering_mode ENUM('table','cafe') NOT NULL DEFAULT 'table' AFTER fulfillment_mode,
  ADD COLUMN shop_token VARCHAR(64) DEFAULT NULL AFTER ordering_mode,
  ADD COLUMN cafe_verify ENUM('email','none') NOT NULL DEFAULT 'email' AFTER shop_token;

ALTER TABLE shops ADD UNIQUE KEY uq_shop_token (shop_token);

CREATE TABLE IF NOT EXISTS customer_sessions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  shop_id INT UNSIGNED NOT NULL,
  session_token VARCHAR(64) NOT NULL,
  table_id INT UNSIGNED NOT NULL,
  nama_pelanggan VARCHAR(40) NOT NULL,
  contact_hash CHAR(64) DEFAULT NULL,
  verified_at DATETIME DEFAULT NULL,
  expires_at DATETIME NOT NULL,
  last_order_at DATETIME DEFAULT NULL,
  status ENUM('pending','active','blocked') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_session_token (session_token),
  KEY idx_session_shop (shop_id, status, expires_at),
  CONSTRAINT fk_session_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  CONSTRAINT fk_session_table FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS verification_codes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  shop_id INT UNSIGNED NOT NULL,
  channel ENUM('email') NOT NULL DEFAULT 'email',
  destination_hash CHAR(64) NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME DEFAULT NULL,
  ip_hash CHAR(64) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_verify_lookup (shop_id, destination_hash, consumed_at),
  CONSTRAINT fk_verify_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders ADD COLUMN session_id INT UNSIGNED DEFAULT NULL AFTER table_id;
ALTER TABLE orders ADD KEY idx_orders_session (session_id);
