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

        $sumberCol = $pdo->query("SHOW COLUMNS FROM orders LIKE 'sumber_order'")->fetch();
        if (!$sumberCol) {
            $pdo->exec(
                "ALTER TABLE orders
                 ADD COLUMN sumber_order ENUM('qr','staf') NOT NULL DEFAULT 'qr' AFTER pickup_alert"
            );
        }

        $guestTokenCol = $pdo->query("SHOW COLUMNS FROM orders LIKE 'guest_token'")->fetch();
        if (!$guestTokenCol) {
            $pdo->exec(
                'ALTER TABLE orders ADD COLUMN guest_token VARCHAR(64) DEFAULT NULL AFTER nama_pelanggan'
            );
        }

        $orderModeCol = $pdo->query("SHOW COLUMNS FROM shops LIKE 'ordering_mode'")->fetch();
        if (!$orderModeCol) {
            $pdo->exec(
                "ALTER TABLE shops
                 ADD COLUMN ordering_mode ENUM('table','cafe') NOT NULL DEFAULT 'table' AFTER fulfillment_mode,
                 ADD COLUMN shop_token VARCHAR(64) DEFAULT NULL AFTER ordering_mode,
                 ADD COLUMN cafe_verify ENUM('email','none') NOT NULL DEFAULT 'email' AFTER shop_token"
            );
            try {
                $pdo->exec('ALTER TABLE shops ADD UNIQUE KEY uq_shop_token (shop_token)');
            } catch (Throwable $e) {
                // index may already exist
            }
        }

        $sessionIdCol = $pdo->query("SHOW COLUMNS FROM orders LIKE 'session_id'")->fetch();
        if (!$sessionIdCol) {
            $pdo->exec('ALTER TABLE orders ADD COLUMN session_id INT UNSIGNED DEFAULT NULL AFTER table_id');
            try {
                $pdo->exec('ALTER TABLE orders ADD KEY idx_orders_session (session_id)');
            } catch (Throwable $e) {
                // index may already exist
            }
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS customer_sessions (
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
                KEY idx_session_shop (shop_id, status, expires_at)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $orderEmailCol = $pdo->query("SHOW COLUMNS FROM orders LIKE 'customer_email'")->fetch();
        if (!$orderEmailCol) {
            $pdo->exec(
                'ALTER TABLE orders ADD COLUMN customer_email VARCHAR(255) DEFAULT NULL AFTER nama_pelanggan'
            );
        }

        $sessionEmailCol = $pdo->query("SHOW COLUMNS FROM customer_sessions LIKE 'contact_email'")->fetch();
        if (!$sessionEmailCol) {
            $pdo->exec(
                'ALTER TABLE customer_sessions ADD COLUMN contact_email VARCHAR(255) DEFAULT NULL AFTER contact_hash'
            );
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS verification_codes (
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
                KEY idx_verify_lookup (shop_id, destination_hash, consumed_at)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        // Never block login if a host cannot ALTER; features degrade until migrate.sql is imported.
    }
}
