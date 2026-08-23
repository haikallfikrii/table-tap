-- Shop hours + cash shifts (buka / tutup)
ALTER TABLE shops
  ADD COLUMN IF NOT EXISTS hours_enabled TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS open_time TIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS close_time TIME DEFAULT NULL;

CREATE TABLE IF NOT EXISTS cash_shifts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  shop_id INT UNSIGNED NOT NULL,
  business_date DATE NOT NULL,
  opened_at DATETIME NOT NULL,
  closed_at DATETIME DEFAULT NULL,
  opened_by INT UNSIGNED DEFAULT NULL,
  closed_by INT UNSIGNED DEFAULT NULL,
  opening_float DECIMAL(10,2) NOT NULL DEFAULT 0,
  close_cash DECIMAL(10,2) DEFAULT NULL,
  close_tng DECIMAL(10,2) DEFAULT NULL,
  close_bank DECIMAL(10,2) DEFAULT NULL,
  close_other DECIMAL(10,2) DEFAULT NULL,
  close_notes VARCHAR(500) DEFAULT NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  PRIMARY KEY (id),
  KEY idx_cash_shifts_shop (shop_id, status),
  KEY idx_cash_shifts_date (shop_id, business_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
