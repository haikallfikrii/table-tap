-- TableTap — waiter role + owner sound settings (existing Hostinger DB)
-- phpMyAdmin: pilih database → Import file ini.
-- Selamat diimport semula: app juga patch schema secara automatik.

ALTER TABLE `shops`
  ADD COLUMN IF NOT EXISTS `sound_mode` ENUM('until_cleared','count','duration') NOT NULL DEFAULT 'until_cleared',
  ADD COLUMN IF NOT EXISTS `sound_repeat_count` TINYINT UNSIGNED NOT NULL DEFAULT 8,
  ADD COLUMN IF NOT EXISTS `sound_duration_sec` SMALLINT UNSIGNED NOT NULL DEFAULT 45,
  ADD COLUMN IF NOT EXISTS `sound_interval_ms` SMALLINT UNSIGNED NOT NULL DEFAULT 900,
  ADD COLUMN IF NOT EXISTS `sound_volume` TINYINT UNSIGNED NOT NULL DEFAULT 100;

ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('master','owner','kasir','dapur','minuman','waiter') NOT NULL;

ALTER TABLE `order_items`
  MODIFY COLUMN `status_item` ENUM('menunggu','sedang_dimasak','selesai','siap','diambil','dihantar') NOT NULL DEFAULT 'menunggu';

UPDATE `order_items` SET `status_item` = 'dihantar' WHERE `status_item` = 'selesai';

ALTER TABLE `order_items`
  MODIFY COLUMN `status_item` ENUM('menunggu','sedang_dimasak','siap','diambil','dihantar') NOT NULL DEFAULT 'menunggu';
