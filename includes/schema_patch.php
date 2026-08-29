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

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS stations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                shop_id INT UNSIGNED NOT NULL,
                kod VARCHAR(40) NOT NULL,
                nama_my VARCHAR(80) NOT NULL,
                nama_en VARCHAR(80) NOT NULL,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                urutan INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_shop_station_kod (shop_id, kod),
                KEY idx_stations_shop (shop_id, is_active, urutan)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $menuStationCol = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'station_id'")->fetch();
        if (!$menuStationCol) {
            $pdo->exec('ALTER TABLE menu_items ADD COLUMN station_id INT UNSIGNED DEFAULT NULL AFTER kategori');
            try {
                $pdo->exec('ALTER TABLE menu_items ADD KEY idx_menu_station (shop_id, station_id)');
            } catch (Throwable $e) {
                // index may already exist
            }
        }

        $orderStationCol = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'station_id_saat_order'")->fetch();
        if (!$orderStationCol) {
            $pdo->exec('ALTER TABLE order_items ADD COLUMN station_id_saat_order INT UNSIGNED DEFAULT NULL AFTER kategori_saat_order');
            try {
                $pdo->exec('ALTER TABLE order_items ADD KEY idx_status_station (status_item, station_id_saat_order)');
            } catch (Throwable $e) {
                // index may already exist
            }
        }

        $userStationCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'station_id'")->fetch();
        if (!$userStationCol) {
            $pdo->exec('ALTER TABLE users ADD COLUMN station_id INT UNSIGNED DEFAULT NULL AFTER role');
        }

        require_once __DIR__ . '/stations.php';
        ensureAllShopStations($pdo);
        backfillMenuAndOrderStations($pdo);

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS menu_categories (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                shop_id INT UNSIGNED NOT NULL,
                kod VARCHAR(40) NOT NULL,
                nama_my VARCHAR(80) NOT NULL,
                nama_en VARCHAR(80) NOT NULL,
                kind ENUM('makanan', 'minuman') NOT NULL DEFAULT 'makanan',
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                urutan INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_shop_menu_cat_kod (shop_id, kod),
                KEY idx_menu_cat_shop (shop_id, is_active, urutan)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $menuCatCol = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'menu_category_id'")->fetch();
        if (!$menuCatCol) {
            $pdo->exec('ALTER TABLE menu_items ADD COLUMN menu_category_id INT UNSIGNED DEFAULT NULL AFTER station_id');
            try {
                $pdo->exec('ALTER TABLE menu_items ADD KEY idx_menu_category (shop_id, menu_category_id)');
            } catch (Throwable $e) {
                // index may already exist
            }
        }

        require_once __DIR__ . '/menu_categories.php';
        ensureAllShopMenuCategories($pdo);
        backfillMenuItemCategories($pdo);

        $orderMenuCatCol = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'menu_category_kod_saat_order'")->fetch();
        if (!$orderMenuCatCol) {
            $pdo->exec(
                'ALTER TABLE order_items
                 ADD COLUMN menu_category_kod_saat_order VARCHAR(40) DEFAULT NULL AFTER station_id_saat_order,
                 ADD COLUMN menu_category_nama_my_saat_order VARCHAR(80) DEFAULT NULL AFTER menu_category_kod_saat_order,
                 ADD COLUMN menu_category_nama_en_saat_order VARCHAR(80) DEFAULT NULL AFTER menu_category_nama_my_saat_order'
            );
            $pdo->exec(
                "UPDATE order_items oi
                 INNER JOIN menu_items mi ON mi.id = oi.menu_item_id
                 INNER JOIN menu_categories mc ON mc.id = mi.menu_category_id
                 SET oi.menu_category_kod_saat_order = mc.kod,
                     oi.menu_category_nama_my_saat_order = mc.nama_my,
                     oi.menu_category_nama_en_saat_order = mc.nama_en
                 WHERE oi.menu_category_kod_saat_order IS NULL"
            );
        }

        $splitCol = $pdo->query("SHOW COLUMNS FROM orders LIKE 'split_from_order_id'")->fetch();
        if (!$splitCol) {
            $pdo->exec(
                'ALTER TABLE orders
                 ADD COLUMN split_from_order_id INT UNSIGNED DEFAULT NULL AFTER sumber_order'
            );
            try {
                $pdo->exec('ALTER TABLE orders ADD KEY idx_orders_split_from (split_from_order_id)');
            } catch (Throwable $e) {
                // index may already exist
            }
        }

        // Contact verify: allow phone collect + email+phone combo
        $cafeVerifyCol = $pdo->query("SHOW COLUMNS FROM shops WHERE Field = 'cafe_verify'")->fetch();
        if ($cafeVerifyCol && stripos((string) ($cafeVerifyCol['Type'] ?? ''), 'email_phone') === false) {
            $pdo->exec(
                "ALTER TABLE shops
                 MODIFY COLUMN cafe_verify ENUM('email','phone','email_phone','none') NOT NULL DEFAULT 'email'"
            );
        }

        $deliveryEnabled = $pdo->query("SHOW COLUMNS FROM shops LIKE 'delivery_enabled'")->fetch();
        if (!$deliveryEnabled) {
            $pdo->exec(
                "ALTER TABLE shops
                 ADD COLUMN delivery_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER cafe_verify,
                 ADD COLUMN delivery_token VARCHAR(64) DEFAULT NULL AFTER delivery_enabled,
                 ADD COLUMN pay_counter TINYINT(1) NOT NULL DEFAULT 1 AFTER delivery_token,
                 ADD COLUMN pay_cod TINYINT(1) NOT NULL DEFAULT 1 AFTER pay_counter,
                 ADD COLUMN pay_duitnow TINYINT(1) NOT NULL DEFAULT 1 AFTER pay_cod,
                 ADD COLUMN duitnow_qr_url VARCHAR(255) DEFAULT NULL AFTER pay_duitnow,
                 ADD COLUMN hold_kitchen_until_paid TINYINT(1) NOT NULL DEFAULT 1 AFTER duitnow_qr_url"
            );
        }

        $delPhone = $pdo->query("SHOW COLUMNS FROM shops LIKE 'delivery_require_phone'")->fetch();
        if (!$delPhone && $pdo->query("SHOW COLUMNS FROM shops LIKE 'delivery_enabled'")->fetch()) {
            $pdo->exec(
                'ALTER TABLE shops
                 ADD COLUMN delivery_require_phone TINYINT(1) NOT NULL DEFAULT 0 AFTER hold_kitchen_until_paid'
            );
        }

        $hidangCol2 = $pdo->query("SHOW COLUMNS FROM orders WHERE Field = 'jenis_hidang'")->fetch();
        if ($hidangCol2 && stripos((string) ($hidangCol2['Type'] ?? ''), 'delivery') === false) {
            $pdo->exec(
                "ALTER TABLE orders
                 MODIFY COLUMN jenis_hidang ENUM('dine_in','takeaway','delivery') NOT NULL DEFAULT 'dine_in'"
            );
        }

        $payMethodCol = $pdo->query("SHOW COLUMNS FROM orders LIKE 'payment_method'")->fetch();
        if (!$payMethodCol) {
            $pdo->exec(
                "ALTER TABLE orders
                 ADD COLUMN phone VARCHAR(20) DEFAULT NULL AFTER nama_pelanggan,
                 ADD COLUMN alamat VARCHAR(500) DEFAULT NULL AFTER phone,
                 ADD COLUMN payment_method ENUM('counter','cod','duitnow') NOT NULL DEFAULT 'counter' AFTER alamat,
                 ADD COLUMN payment_proof_url VARCHAR(255) DEFAULT NULL AFTER payment_method,
                 ADD COLUMN payment_proof_status ENUM('none','uploaded','rejected','confirmed') NOT NULL DEFAULT 'none' AFTER payment_proof_url"
            );
            try {
                $pdo->exec('ALTER TABLE orders ADD KEY idx_orders_payment (shop_id, payment_method, payment_proof_status)');
            } catch (Throwable $e) {
                // ignore
            }
        }

        $hoursEnabled = $pdo->query("SHOW COLUMNS FROM shops LIKE 'hours_enabled'")->fetch();
        if (!$hoursEnabled) {
            $pdo->exec(
                'ALTER TABLE shops
                 ADD COLUMN hours_enabled TINYINT(1) NOT NULL DEFAULT 0,
                 ADD COLUMN open_time TIME DEFAULT NULL,
                 ADD COLUMN close_time TIME DEFAULT NULL'
            );
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS cash_shifts (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS menu_addons (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                shop_id INT UNSIGNED NOT NULL,
                menu_item_id INT UNSIGNED NOT NULL,
                nama_my VARCHAR(80) NOT NULL,
                nama_en VARCHAR(80) NOT NULL,
                harga_delta DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                jenis ENUM('pilihan', 'tambahan') NOT NULL DEFAULT 'tambahan',
                urutan INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_menu_addons_item (shop_id, menu_item_id, is_active, urutan)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $printPaidCol = $pdo->query("SHOW COLUMNS FROM shops LIKE 'kasir_print_on_paid'")->fetch();
        if (!$printPaidCol) {
            $pdo->exec(
                'ALTER TABLE shops
                 ADD COLUMN kasir_print_on_paid TINYINT(1) NOT NULL DEFAULT 1 AFTER sound_volume,
                 ADD COLUMN printer_beep_kitchen TINYINT UNSIGNED NOT NULL DEFAULT 4 AFTER kasir_print_on_paid,
                 ADD COLUMN printer_beep_kasir TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER printer_beep_kitchen'
            );
        }
    } catch (Throwable $e) {
        // Never block login if a host cannot ALTER; features degrade until migrate.sql is imported.
    }
}
