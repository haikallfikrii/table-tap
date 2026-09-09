-- TableTap: Print Bridge — queue for Wi-Fi / LAN ESC/POS printers.
-- Prefer includes/schema_patch.php on deploy; this file is for phpMyAdmin.

CREATE TABLE IF NOT EXISTS `print_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` INT UNSIGNED NOT NULL,
  `station_kod` VARCHAR(40) NOT NULL,
  `jenis` VARCHAR(20) NOT NULL DEFAULT 'kitchen',
  `order_id` INT UNSIGNED DEFAULT NULL,
  `payload_format` ENUM('json','escpos_base64') NOT NULL DEFAULT 'json',
  `payload` MEDIUMTEXT NOT NULL,
  `status` ENUM('pending','printing','done','failed') NOT NULL DEFAULT 'pending',
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `last_error` VARCHAR(255) DEFAULT NULL,
  `claim_token` CHAR(32) DEFAULT NULL,
  `claimed_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `printed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_print_jobs_queue` (`shop_id`, `status`, `id`),
  KEY `idx_print_jobs_order` (`shop_id`, `order_id`, `jenis`),
  KEY `idx_print_jobs_claim` (`claim_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Skip any ALTER that already exists on this host.

ALTER TABLE `shops`
  ADD COLUMN `print_bridge_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `printer_beep_kasir`,
  ADD COLUMN `print_bridge_token` VARCHAR(64) DEFAULT NULL AFTER `print_bridge_enabled`,
  ADD COLUMN `print_bridge_seen_at` DATETIME DEFAULT NULL AFTER `print_bridge_token`;

ALTER TABLE `shops`
  ADD UNIQUE KEY `uq_print_bridge_token` (`print_bridge_token`);
