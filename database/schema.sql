-- TableTap Database Schema (multi-tenant)
-- MySQL 5.7+ / MariaDB 10.3+ (Hostinger shared hosting)
-- Charset: utf8mb4
--
-- HOSTINGER IMPORT:
-- 1. Buka phpMyAdmin → pilih database anda (cth: u451240370_tabletap)
-- 2. Tab Import → pilih file ini → Go
-- JANGAN jalankan CREATE DATABASE — DB sudah dibuat di hPanel.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Jika import via CLI dan perlu pilih DB secara eksplicit, buka komen baris berikut:
-- USE `u451240370_tabletap`;

DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `menu_items`;
DROP TABLE IF EXISTS `tables`;
DROP TABLE IF EXISTS `expenses`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `shops`;
DROP TABLE IF EXISTS `packages`;

-- --------------------------------------------------------
-- Packages (retensi histori order)
-- retention_days NULL = simpan selamanya
-- --------------------------------------------------------
CREATE TABLE `packages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kod` VARCHAR(40) NOT NULL,
  `nama_my` VARCHAR(100) NOT NULL,
  `nama_en` VARCHAR(100) NOT NULL,
  `retention_days` INT UNSIGNED DEFAULT NULL COMMENT 'NULL = forever',
  `harga_bulanan` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_package_kod` (`kod`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Shops (kedai)
-- --------------------------------------------------------
CREATE TABLE `shops` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_kedai` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(80) NOT NULL,
  `package_id` INT UNSIGNED NOT NULL,
  `sst_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `sst_rate` DECIMAL(5,2) NOT NULL DEFAULT 6.00 COMMENT 'Percent, e.g. 6.00 = 6%',
  `status` ENUM('aktif', 'tidak_aktif', 'suspended') NOT NULL DEFAULT 'aktif',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_slug` (`slug`),
  KEY `idx_shop_status` (`status`),
  CONSTRAINT `fk_shops_package`
    FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Users
-- role: master (platform) | owner | kasir | dapur | minuman
-- shop_id NULL only for master
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` INT UNSIGNED DEFAULT NULL,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('master', 'owner', 'kasir', 'dapur', 'minuman') NOT NULL,
  `nama_paparan` VARCHAR(100) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  KEY `idx_users_shop` (`shop_id`),
  CONSTRAINT `fk_users_shop`
    FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Meja
-- --------------------------------------------------------
CREATE TABLE `tables` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` INT UNSIGNED NOT NULL,
  `nomor_meja` VARCHAR(20) NOT NULL,
  `token_akses` VARCHAR(64) NOT NULL,
  `status` ENUM('aktif', 'tidak_aktif') NOT NULL DEFAULT 'aktif',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_nomor_meja` (`shop_id`, `nomor_meja`),
  UNIQUE KEY `uq_token_akses` (`token_akses`),
  CONSTRAINT `fk_tables_shop`
    FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Menu
-- --------------------------------------------------------
CREATE TABLE `menu_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` INT UNSIGNED NOT NULL,
  `nama_my` VARCHAR(150) NOT NULL,
  `nama_en` VARCHAR(150) NOT NULL,
  `deskripsi_my` VARCHAR(500) DEFAULT NULL,
  `deskripsi_en` VARCHAR(500) DEFAULT NULL,
  `harga` DECIMAL(10,2) NOT NULL,
  `kategori` ENUM('makanan', 'minuman') NOT NULL,
  `foto_url` VARCHAR(255) DEFAULT NULL,
  `status_stok` ENUM('tersedia', 'habis') NOT NULL DEFAULT 'tersedia',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `urutan` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shop_kategori_stok` (`shop_id`, `kategori`, `status_stok`, `is_active`),
  CONSTRAINT `fk_menu_shop`
    FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Orders
-- --------------------------------------------------------
CREATE TABLE `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` INT UNSIGNED NOT NULL,
  `table_id` INT UNSIGNED NOT NULL,
  `waktu_order` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status_order` ENUM('menunggu', 'diproses', 'selesai', 'dibatalkan') NOT NULL DEFAULT 'menunggu',
  `status_bayar` ENUM('belum_bayar', 'lunas') NOT NULL DEFAULT 'belum_bayar',
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `sst_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `sst_jumlah` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_harga` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `waktu_lunas` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shop_table_status` (`shop_id`, `table_id`, `status_bayar`, `status_order`),
  KEY `idx_shop_waktu` (`shop_id`, `waktu_order`),
  KEY `idx_polling` (`shop_id`, `id`, `status_order`),
  KEY `idx_retention` (`shop_id`, `status_bayar`, `waktu_order`),
  CONSTRAINT `fk_orders_shop`
    FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_orders_table`
    FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Order Items
-- --------------------------------------------------------
CREATE TABLE `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `menu_item_id` INT UNSIGNED NOT NULL,
  `qty` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `catatan` VARCHAR(255) DEFAULT NULL,
  `status_item` ENUM('menunggu', 'sedang_dimasak', 'selesai') NOT NULL DEFAULT 'menunggu',
  `harga_saat_order` DECIMAL(10,2) NOT NULL,
  `nama_saat_order_my` VARCHAR(150) NOT NULL,
  `nama_saat_order_en` VARCHAR(150) NOT NULL,
  `kategori_saat_order` ENUM('makanan', 'minuman') NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_status_kategori` (`status_item`, `kategori_saat_order`),
  KEY `idx_polling_items` (`id`, `status_item`),
  CONSTRAINT `fk_order_items_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_menu`
    FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Expenses
-- --------------------------------------------------------
CREATE TABLE `expenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_id` INT UNSIGNED NOT NULL,
  `kategori` VARCHAR(100) NOT NULL,
  `jumlah` DECIMAL(10,2) NOT NULL,
  `tanggal` DATE NOT NULL,
  `catatan` VARCHAR(500) DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shop_tanggal` (`shop_id`, `tanggal`),
  CONSTRAINT `fk_expenses_shop`
    FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_expenses_user`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- Seed
-- Default password semua akaun seed: password123
-- TUKAR SEGERA selepas login pertama!
-- --------------------------------------------------------

INSERT INTO `packages` (`kod`, `nama_my`, `nama_en`, `retention_days`, `harga_bulanan`) VALUES
('basic',   'Asas (30 hari)',     'Basic (30 days)',     30,  29.00),
('standard','Standard (60 hari)', 'Standard (60 days)',  60,  49.00),
('pro',     'Pro (selamanya)',    'Pro (forever)',       NULL, 99.00);

-- Master platform admin (tiada shop_id)
INSERT INTO `users` (`shop_id`, `username`, `password_hash`, `role`, `nama_paparan`) VALUES
(NULL, 'master', '$2y$12$pIALBj8IQ6paaAFA1bMB/.TQmxDfHrgRphurpmOcs03DUz5BB7PPe', 'master', 'Master Admin');

-- Demo kedai
INSERT INTO `shops` (`nama_kedai`, `slug`, `package_id`, `sst_enabled`, `sst_rate`, `status`) VALUES
('Kedai Demo', 'kedai-demo', 2, 0, 6.00, 'aktif');

SET @shop_id = LAST_INSERT_ID();

INSERT INTO `users` (`shop_id`, `username`, `password_hash`, `role`, `nama_paparan`) VALUES
(@shop_id, 'owner',   '$2y$12$pIALBj8IQ6paaAFA1bMB/.TQmxDfHrgRphurpmOcs03DUz5BB7PPe', 'owner',   'Pemilik Demo'),
(@shop_id, 'kasir',   '$2y$12$pIALBj8IQ6paaAFA1bMB/.TQmxDfHrgRphurpmOcs03DUz5BB7PPe', 'kasir',   'Kasir'),
(@shop_id, 'dapur',   '$2y$12$pIALBj8IQ6paaAFA1bMB/.TQmxDfHrgRphurpmOcs03DUz5BB7PPe', 'dapur',   'Dapur'),
(@shop_id, 'minuman', '$2y$12$pIALBj8IQ6paaAFA1bMB/.TQmxDfHrgRphurpmOcs03DUz5BB7PPe', 'minuman', 'Minuman');

INSERT INTO `tables` (`shop_id`, `nomor_meja`, `token_akses`, `status`) VALUES
(@shop_id, '1', SHA2(CONCAT('meja1-', UUID()), 256), 'aktif'),
(@shop_id, '2', SHA2(CONCAT('meja2-', UUID()), 256), 'aktif'),
(@shop_id, '3', SHA2(CONCAT('meja3-', UUID()), 256), 'aktif'),
(@shop_id, '4', SHA2(CONCAT('meja4-', UUID()), 256), 'aktif'),
(@shop_id, '5', SHA2(CONCAT('meja5-', UUID()), 256), 'aktif');

INSERT INTO `menu_items` (`shop_id`, `nama_my`, `nama_en`, `deskripsi_my`, `deskripsi_en`, `harga`, `kategori`, `status_stok`, `urutan`) VALUES
(@shop_id, 'Nasi Lemak', 'Nasi Lemak', 'Nasi lemak lengkap dengan sambal, telur & kacang', 'Coconut rice with sambal, egg & peanuts', 8.00, 'makanan', 'tersedia', 1),
(@shop_id, 'Nasi Goreng Kampung', 'Kampung Fried Rice', 'Nasi goreng berempah gaya kampung', 'Spicy kampung-style fried rice', 9.50, 'makanan', 'tersedia', 2),
(@shop_id, 'Mee Goreng', 'Fried Noodles', 'Mee kuning digoreng dengan sayur', 'Yellow noodles stir-fried with vegetables', 8.50, 'makanan', 'tersedia', 3),
(@shop_id, 'Ayam Goreng', 'Fried Chicken', 'Ayam goreng rangup (2 ketul)', 'Crispy fried chicken (2 pieces)', 7.00, 'makanan', 'tersedia', 4),
(@shop_id, 'Teh Tarik', 'Teh Tarik', 'Teh tarik panas', 'Hot pulled tea', 2.50, 'minuman', 'tersedia', 1),
(@shop_id, 'Kopi O', 'Kopi O', 'Kopi hitam panas', 'Hot black coffee', 2.00, 'minuman', 'tersedia', 2),
(@shop_id, 'Air Bandung', 'Bandung Drink', 'Minuman sirap bandung sejuk', 'Chilled bandung syrup drink', 3.00, 'minuman', 'tersedia', 3),
(@shop_id, 'Limau Ais', 'Iced Lemon', 'Air limau ais', 'Iced lemon drink', 3.50, 'minuman', 'tersedia', 4);
