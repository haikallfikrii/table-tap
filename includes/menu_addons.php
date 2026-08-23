<?php
/**
 * Menu add-ons — pilihan (pick one) & tambahan (optional extras).
 */

declare(strict_types=1);

function menuAddonsTableExists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $exists = (bool) db()->query("SHOW TABLES LIKE 'menu_addons'")->fetch();
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

/** @return list<array<string, mixed>> */
function menuAddonsForItem(int $shopId, int $menuItemId, bool $activeOnly = true): array
{
    if (!menuAddonsTableExists() || $menuItemId <= 0) {
        return [];
    }
    $sql = 'SELECT id, menu_item_id, nama_my, nama_en, harga_delta, jenis, urutan, is_active
            FROM menu_addons
            WHERE shop_id = ? AND menu_item_id = ?';
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' ORDER BY jenis ASC, urutan ASC, id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute([$shopId, $menuItemId]);
    return $stmt->fetchAll() ?: [];
}

/** @return array<int, list<array<string, mixed>>> */
function menuAddonsGroupedByItem(int $shopId, array $menuItemIds, bool $activeOnly = true): array
{
    if (!menuAddonsTableExists() || $menuItemIds === []) {
        return [];
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $menuItemIds), static fn(int $id): bool => $id > 0)));
    if ($ids === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT id, menu_item_id, nama_my, nama_en, harga_delta, jenis, urutan, is_active
            FROM menu_addons
            WHERE shop_id = ? AND menu_item_id IN ($placeholders)";
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' ORDER BY menu_item_id ASC, jenis ASC, urutan ASC, id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute(array_merge([$shopId], $ids));
    $grouped = [];
    foreach ($stmt->fetchAll() as $row) {
        $grouped[(int) $row['menu_item_id']][] = $row;
    }
    return $grouped;
}

/** @param list<array<string, mixed>> $rows */
function menuAddonsForCustomer(array $rows, string $lang): array
{
    $choices = [];
    $extras = [];
    foreach ($rows as $row) {
        $entry = [
            'id' => (int) $row['id'],
            'nama' => $lang === 'en' ? (string) $row['nama_en'] : (string) $row['nama_my'],
            'harga_delta' => (float) $row['harga_delta'],
        ];
        if (($row['jenis'] ?? '') === 'pilihan') {
            $choices[] = $entry;
        } else {
            $extras[] = $entry;
        }
    }
    return ['choices' => $choices, 'extras' => $extras];
}

/** @param list<int> $addonIds @return list<array<string, mixed>> */
function validateMenuAddonsForItem(int $shopId, int $menuItemId, array $addonIds): array
{
    $addonIds = array_values(array_unique(array_filter(array_map('intval', $addonIds), static fn(int $id): bool => $id > 0)));
    if ($addonIds === []) {
        $all = menuAddonsForItem($shopId, $menuItemId, true);
        foreach ($all as $row) {
            if (($row['jenis'] ?? '') === 'pilihan') {
                jsonError(t('addon_choice_required'));
            }
        }
        return [];
    }

    $all = menuAddonsForItem($shopId, $menuItemId, true);
    $byId = [];
    foreach ($all as $row) {
        $byId[(int) $row['id']] = $row;
    }

    $picked = [];
    $choiceCount = 0;
    foreach ($addonIds as $aid) {
        if (!isset($byId[$aid])) {
            jsonError(t('addon_invalid'));
        }
        $row = $byId[$aid];
        if (($row['jenis'] ?? '') === 'pilihan') {
            $choiceCount++;
            if ($choiceCount > 1) {
                jsonError(t('addon_one_choice'));
            }
        }
        $picked[] = $row;
    }

    $hasChoices = false;
    foreach ($all as $row) {
        if (($row['jenis'] ?? '') === 'pilihan') {
            $hasChoices = true;
            break;
        }
    }
    if ($hasChoices && $choiceCount === 0) {
        jsonError(t('addon_choice_required'));
    }

    return $picked;
}

/** @param list<array<string, mixed>> $picked */
function formatOrderAddonNames(array $picked, string $lang): string
{
    if ($picked === []) {
        return '';
    }
    $parts = [];
    foreach ($picked as $row) {
        $parts[] = $lang === 'en' ? (string) $row['nama_en'] : (string) $row['nama_my'];
    }
    return implode(', ', $parts);
}

/** @param list<array<string, mixed>> $picked */
function addonPriceDelta(array $picked): float
{
    $sum = 0.0;
    foreach ($picked as $row) {
        $sum += (float) ($row['harga_delta'] ?? 0);
    }
    return round($sum, 2);
}

/** Replace all add-ons for a menu item from owner form rows. */
function saveMenuAddonsFromForm(PDO $pdo, int $shopId, int $menuItemId, array $post): void
{
    if (!menuAddonsTableExists() || $menuItemId <= 0) {
        return;
    }

    $pdo->prepare('DELETE FROM menu_addons WHERE shop_id = ? AND menu_item_id = ?')
        ->execute([$shopId, $menuItemId]);

    $insert = $pdo->prepare(
        'INSERT INTO menu_addons (shop_id, menu_item_id, nama_my, nama_en, harga_delta, jenis, urutan, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
    );

    foreach (['pilihan', 'tambahan'] as $jenis) {
        $namesMy = $post['addon_' . $jenis . '_my'] ?? [];
        $namesEn = $post['addon_' . $jenis . '_en'] ?? [];
        $prices = $post['addon_' . $jenis . '_harga'] ?? [];
        if (!is_array($namesMy)) {
            continue;
        }
        $n = count($namesMy);
        for ($i = 0; $i < $n; $i++) {
            $namaMy = trim((string) ($namesMy[$i] ?? ''));
            $namaEn = trim((string) ($namesEn[$i] ?? ''));
            if ($namaMy === '' && $namaEn === '') {
                continue;
            }
            if ($namaMy === '') {
                $namaMy = $namaEn;
            }
            if ($namaEn === '') {
                $namaEn = $namaMy;
            }
            $harga = (float) ($prices[$i] ?? 0);
            if ($harga < 0) {
                $harga = 0;
            }
            $insert->execute([$shopId, $menuItemId, $namaMy, $namaEn, $harga, $jenis, $i]);
        }
    }
}
