-- TableTap Database Schema
-- MySQL 5.7+ / MariaDB 10.3+ (compatible with Hostinger shared hosting)
-- Charset: utf8mb4 for Malay + English text support

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `tabletap`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `tabletap`;

-- --------------------------------------------------------
-- Meja / Tables
-- --------------------------------------------------------
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `menu_items`;
DROP TABLE IF EXISTS `tables`;
DROP TABLE IF EXISTS `expenses`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `tables` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor_meja` VARCHAR(20) NOT NULL,
  `token_akses` VARCHAR(64) NOT NULL,
  `status` ENUM('aktif', 'tidak_aktif') NOT NULL DEFAULT 'aktif',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nomor_meja` (`nomor_meja`),
  UNIQUE KEY `uq_token_akses` (`token_akses`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Menu Items
-- --------------------------------------------------------
CREATE TABLE `menu_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_my` VARCHAR(150) NOT NULL,
  `nama_en` VARCHAR(150) NOT NULL,
  `deskripsi_my` VARCHAR(500) DEFAULT NULL,
  `deskripsi_en` VARCHAR(500) DEFAULT NULL,
  `harga` DECIMAL(10,2) NOT NULL,
  `kategori` ENUM('makanan', 'minuman') NOT NULL,
  `foto_url` VARCHAR(255) DEFAULT NULL,
  `status_stok` ENUM('tersedia', 'habis') NOT NULL DEFAULT 'tersedia',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Soft delete flag',
  `urutan` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_kategori_stok` (`kategori`, `status_stok`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Orders
-- status_order: menunggu | diproses | selesai | dibatalkan
-- status_bayar: belum_bayar | lunas
-- --------------------------------------------------------
CREATE TABLE `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_id` INT UNSIGNED NOT NULL,
  `waktu_order` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status_order` ENUM('menunggu', 'diproses', 'selesai', 'dibatalkan') NOT NULL DEFAULT 'menunggu',
  `status_bayar` ENUM('belum_bayar', 'lunas') NOT NULL DEFAULT 'belum_bayar',
  `total_harga` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `waktu_lunas` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_table_status` (`table_id`, `status_bayar`, `status_order`),
  KEY `idx_waktu_order` (`waktu_order`),
  KEY `idx_polling` (`id`, `status_order`),
  CONSTRAINT `fk_orders_table`
    FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Order Items
-- status_item: menunggu | sedang_dimasak | selesai
-- --------------------------------------------------------
CREATE TABLE `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `menu_item_id` INT UNSIGNED NOT NULL,
  `qty` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `catatan` VARCHAR(255) DEFAULT NULL,
  `status_item` ENUM('menunggu', 'sedang_dimasak', 'selesai') NOT NULL DEFAULT 'menunggu',
  `harga_saat_order` DECIMAL(10,2) NOT NULL COMMENT 'Snapshot harga saat order',
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
-- Expenses (pengeluaran)
-- --------------------------------------------------------
CREATE TABLE `expenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kategori` VARCHAR(100) NOT NULL,
  `jumlah` DECIMAL(10,2) NOT NULL,
  `tanggal` DATE NOT NULL,
  `catatan` VARCHAR(500) DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tanggal` (`tanggal`),
  KEY `idx_kategori` (`kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Users (staff)
-- role: owner | kasir | dapur | minuman
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('owner', 'kasir', 'dapur', 'minuman') NOT NULL,
  `nama_paparan` VARCHAR(100) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add FK for expenses.created_by after users exists
ALTER TABLE `expenses`
  ADD CONSTRAINT `fk_expenses_user`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- Seed data
-- Default password for all seed users: password123
-- CHANGE THESE IMMEDIATELY after first login!
-- --------------------------------------------------------

-- Default password for all seed users: password123
INSERT INTO `users` (`username`, `password_hash`, `role`, `nama_paparan`) VALUES
('owner',   '$2y$12$pIALBj8IQ6paaAFA1bMB/.TQmxDfHrgRphurpmOcs03DUz5BB7PPe', 'owner',   'Pemilik'),
('kasir',   '$2y$12$pIALBj8IQ6paaAFA1bMB/.TQmxDfHrgRphurpmOcs03DUz5BB7PPe', 'kasir',   'Kasir'),
('dapur',   '$2y$12$pIALBj8IQ6paaAFA1bMB/.TQmxDfHrgRphurpmOcs03DUz5BB7PPe', 'dapur',   'Dapur'),
('minuman', '$2y$12$pIALBj8IQ6paaAFA1bMB/.TQmxDfHrgRphurpmOcs03DUz5BB7PPe', 'minuman', 'Minuman');

-- Sample tables (tokens are random hex — regenerate in admin after import)
INSERT INTO `tables` (`nomor_meja`, `token_akses`, `status`) VALUES
('1', SHA2(CONCAT('meja1-', UUID()), 256), 'aktif'),
('2', SHA2(CONCAT('meja2-', UUID()), 256), 'aktif'),
('3', SHA2(CONCAT('meja3-', UUID()), 256), 'aktif'),
('4', SHA2(CONCAT('meja4-', UUID()), 256), 'aktif'),
('5', SHA2(CONCAT('meja5-', UUID()), 256), 'aktif');

-- Sample menu
INSERT INTO `menu_items` (`nama_my`, `nama_en`, `deskripsi_my`, `deskripsi_en`, `harga`, `kategori`, `status_stok`, `urutan`) VALUES
('Nasi Lemak', 'Nasi Lemak', 'Nasi lemak lengkap dengan sambal, telur & kacang', 'Coconut rice with sambal, egg & peanuts', 8.00, 'makanan', 'tersedia', 1),
('Nasi Goreng Kampung', 'Kampung Fried Rice', 'Nasi goreng berempah gaya kampung', 'Spicy kampung-style fried rice', 9.50, 'makanan', 'tersedia', 2),
('Mee Goreng', 'Fried Noodles', 'Mee kuning digoreng dengan sayur', 'Yellow noodles stir-fried with vegetables', 8.50, 'makanan', 'tersedia', 3),
('Ayam Goreng', 'Fried Chicken', 'Ayam goreng rangup (2 ketul)', 'Crispy fried chicken (2 pieces)', 7.00, 'makanan', 'tersedia', 4),
('Teh Tarik', 'Teh Tarik', 'Teh tarik panas', 'Hot pulled tea', 2.50, 'minuman', 'tersedia', 1),
('Kopi O', 'Kopi O', 'Kopi hitam panas', 'Hot black coffee', 2.00, 'minuman', 'tersedia', 2),
('Air Bandung', 'Bandung Drink', 'Minuman sirap bandung sejuk', 'Chilled bandung syrup drink', 3.00, 'minuman', 'tersedia', 3),
('Limau Ais', 'Iced Lemon', 'Air limau ais', 'Iced lemon drink', 3.50, 'minuman', 'tersedia', 4);
