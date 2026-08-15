-- Menu gallery (Pro) + longer descriptions
CREATE TABLE IF NOT EXISTS `menu_photos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` INT UNSIGNED NOT NULL,
  `menu_item_id` INT UNSIGNED NOT NULL,
  `foto_url` VARCHAR(255) NOT NULL,
  `urutan` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_menu_photos_item` (`shop_id`, `menu_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `menu_items`
  MODIFY COLUMN `deskripsi_my` TEXT NULL,
  MODIFY COLUMN `deskripsi_en` TEXT NULL;
