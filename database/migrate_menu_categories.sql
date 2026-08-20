-- TableTap: customer menu categories (Pro custom tabs)
-- Prefer includes/schema_patch.php on deploy; this file is for phpMyAdmin.

CREATE TABLE IF NOT EXISTS `menu_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` INT UNSIGNED NOT NULL,
  `kod` VARCHAR(40) NOT NULL,
  `nama_my` VARCHAR(80) NOT NULL,
  `nama_en` VARCHAR(80) NOT NULL,
  `kind` ENUM('makanan', 'minuman') NOT NULL DEFAULT 'makanan',
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `urutan` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_menu_cat_kod` (`shop_id`, `kod`),
  KEY `idx_menu_cat_shop` (`shop_id`, `is_active`, `urutan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `menu_items`
  ADD COLUMN `menu_category_id` INT UNSIGNED DEFAULT NULL AFTER `station_id`;

INSERT INTO `menu_categories` (`shop_id`, `kod`, `nama_my`, `nama_en`, `kind`, `is_system`, `urutan`, `is_active`)
SELECT s.id, 'makanan', 'Makanan', 'Food', 'makanan', 1, 1, 1
FROM `shops` s
WHERE NOT EXISTS (
  SELECT 1 FROM `menu_categories` mc WHERE mc.shop_id = s.id AND mc.kod = 'makanan'
);

INSERT INTO `menu_categories` (`shop_id`, `kod`, `nama_my`, `nama_en`, `kind`, `is_system`, `urutan`, `is_active`)
SELECT s.id, 'minuman', 'Minuman', 'Drinks', 'minuman', 1, 2, 1
FROM `shops` s
WHERE NOT EXISTS (
  SELECT 1 FROM `menu_categories` mc WHERE mc.shop_id = s.id AND mc.kod = 'minuman'
);

UPDATE `menu_items` mi
INNER JOIN `menu_categories` mc
  ON mc.shop_id = mi.shop_id
 AND mc.kod = mi.kategori
SET mi.menu_category_id = mc.id
WHERE mi.menu_category_id IS NULL;
