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
    } catch (Throwable $e) {
        // Never block login if a host cannot ALTER; features degrade until migrate.sql is imported.
    }
}
