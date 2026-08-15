<?php
/**
 * Idempotent schema patches for existing Hostinger databases.
 */

declare(strict_types=1);

function ensureAppSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $hasSound = $pdo->query("SHOW COLUMNS FROM shops LIKE 'sound_mode'")->fetch();
        if (!$hasSound) {
            $pdo->exec(
                "ALTER TABLE shops
                 ADD COLUMN sound_mode ENUM('until_cleared','count','duration') NOT NULL DEFAULT 'until_cleared' AFTER sst_rate,
                 ADD COLUMN sound_repeat_count TINYINT UNSIGNED NOT NULL DEFAULT 8 AFTER sound_mode,
                 ADD COLUMN sound_duration_sec SMALLINT UNSIGNED NOT NULL DEFAULT 45 AFTER sound_repeat_count,
                 ADD COLUMN sound_interval_ms SMALLINT UNSIGNED NOT NULL DEFAULT 900 AFTER sound_duration_sec,
                 ADD COLUMN sound_volume TINYINT UNSIGNED NOT NULL DEFAULT 100 AFTER sound_interval_ms"
            );
        }

        $roleCol = $pdo->query("SHOW COLUMNS FROM users WHERE Field = 'role'")->fetch();
        if ($roleCol && stripos((string) $roleCol['Type'], 'waiter') === false) {
            $pdo->exec(
                "ALTER TABLE users
                 MODIFY COLUMN role ENUM('master','owner','kasir','dapur','minuman','waiter') NOT NULL"
            );
        }

        $itemCol = $pdo->query("SHOW COLUMNS FROM order_items WHERE Field = 'status_item'")->fetch();
        $type = (string) ($itemCol['Type'] ?? '');
        if ($itemCol && stripos($type, 'siap') === false) {
            $pdo->exec(
                "ALTER TABLE order_items
                 MODIFY COLUMN status_item ENUM('menunggu','sedang_dimasak','selesai','siap','diambil','dihantar') NOT NULL DEFAULT 'menunggu'"
            );
            $pdo->exec(
                "UPDATE order_items SET status_item = 'dihantar' WHERE status_item = 'selesai'"
            );
            $pdo->exec(
                "ALTER TABLE order_items
                 MODIFY COLUMN status_item ENUM('menunggu','sedang_dimasak','siap','diambil','dihantar') NOT NULL DEFAULT 'menunggu'"
            );
        }

        $hidangCol = $pdo->query("SHOW COLUMNS FROM orders LIKE 'jenis_hidang'")->fetch();
        if (!$hidangCol) {
            $pdo->exec(
                "ALTER TABLE orders
                 ADD COLUMN jenis_hidang ENUM('dine_in','takeaway') NOT NULL DEFAULT 'dine_in' AFTER status_bayar"
            );
        }

        $fulfillCol = $pdo->query("SHOW COLUMNS FROM shops LIKE 'fulfillment_mode'")->fetch();
        if (!$fulfillCol) {
            $pdo->exec(
                "ALTER TABLE shops
                 ADD COLUMN fulfillment_mode ENUM('waiter','self_pickup') NOT NULL DEFAULT 'waiter' AFTER sst_rate"
            );
        }

        $nameCol = $pdo->query("SHOW COLUMNS FROM orders LIKE 'nama_pelanggan'")->fetch();
        if (!$nameCol) {
            $pdo->exec(
                "ALTER TABLE orders
                 ADD COLUMN nama_pelanggan VARCHAR(40) DEFAULT NULL AFTER jenis_hidang"
            );
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS menu_photos (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                shop_id INT UNSIGNED NOT NULL,
                menu_item_id INT UNSIGNED NOT NULL,
                foto_url VARCHAR(255) NOT NULL,
                urutan INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_menu_photos_item (shop_id, menu_item_id)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $descCol = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'deskripsi_my'")->fetch();
        if ($descCol && stripos((string) $descCol['Type'], 'varchar') !== false) {
            $pdo->exec(
                'ALTER TABLE menu_items
                 MODIFY COLUMN deskripsi_my TEXT NULL,
                 MODIFY COLUMN deskripsi_en TEXT NULL'
            );
        }

        $alertCol = $pdo->query("SHOW COLUMNS FROM orders LIKE 'pickup_alert'")->fetch();
        if (!$alertCol) {
            $pdo->exec(
                'ALTER TABLE orders
                 ADD COLUMN pickup_alert TINYINT(1) NOT NULL DEFAULT 1 AFTER nama_pelanggan'
            );
        }
    } catch (Throwable $e) {
        // Never block login if a host cannot ALTER; features degrade until migrate.sql is imported.
    }
}
