-- TableTap: work stations (kitchen / drinks / Pro custom)
-- Prefer includes/schema_patch.php on deploy; this file is for phpMyAdmin.

CREATE TABLE IF NOT EXISTS `stations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` INT UNSIGNED NOT NULL,
  `kod` VARCHAR(40) NOT NULL,
  `nama_my` VARCHAR(80) NOT NULL,
  `nama_en` VARCHAR(80) NOT NULL,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `urutan` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_station_kod` (`shop_id`, `kod`),
  KEY `idx_stations_shop` (`shop_id`, `is_active`, `urutan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Skip any ALTER that already exists on this host.

ALTER TABLE `menu_items`
  ADD COLUMN `station_id` INT UNSIGNED DEFAULT NULL AFTER `kategori`;

ALTER TABLE `order_items`
  ADD COLUMN `station_id_saat_order` INT UNSIGNED DEFAULT NULL AFTER `kategori_saat_order`;

ALTER TABLE `users`
  ADD COLUMN `station_id` INT UNSIGNED DEFAULT NULL AFTER `role`;

INSERT INTO `stations` (`shop_id`, `kod`, `nama_my`, `nama_en`, `is_system`, `urutan`, `is_active`)
SELECT s.id, 'dapur', 'Dapur', 'Kitchen', 1, 1, 1
FROM `shops` s
WHERE NOT EXISTS (
  SELECT 1 FROM `stations` st WHERE st.shop_id = s.id AND st.kod = 'dapur'
);

INSERT INTO `stations` (`shop_id`, `kod`, `nama_my`, `nama_en`, `is_system`, `urutan`, `is_active`)
SELECT s.id, 'minuman', 'Minuman', 'Drinks', 1, 2, 1
FROM `shops` s
WHERE NOT EXISTS (
  SELECT 1 FROM `stations` st WHERE st.shop_id = s.id AND st.kod = 'minuman'
);

UPDATE `menu_items` mi
INNER JOIN `stations` s
  ON s.shop_id = mi.shop_id
 AND s.kod = IF(mi.kategori = 'minuman', 'minuman', 'dapur')
SET mi.station_id = s.id
WHERE mi.station_id IS NULL;

UPDATE `order_items` oi
INNER JOIN `orders` o ON o.id = oi.order_id
INNER JOIN `stations` s
  ON s.shop_id = o.shop_id
 AND s.kod = IF(oi.kategori_saat_order = 'minuman', 'minuman', 'dapur')
SET oi.station_id_saat_order = s.id
WHERE oi.station_id_saat_order IS NULL;
