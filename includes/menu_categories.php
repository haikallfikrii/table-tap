<?php
/**
 * Customer-facing menu categories (Pro: Western, Burger, Top picks, …).
 * Separate from kitchen stations — categories = what guests see; stations = where tickets go.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function menuCategoriesTableExists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $exists = (bool) db()->query("SHOW TABLES LIKE 'menu_categories'")->fetch();
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

function menuCategoryColumnExists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $exists = (bool) db()->query("SHOW COLUMNS FROM menu_items LIKE 'menu_category_id'")->fetch();
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

function maxCustomMenuCategories(): int
{
    return 12;
}

function categoryKodFromName(string $name): string
{
    $kod = strtolower(trim($name));
    $kod = preg_replace('/[^a-z0-9]+/i', '-', $kod) ?? '';
    $kod = trim($kod, '-');
    if ($kod === '' || in_array($kod, ['makanan', 'minuman'], true)) {
        $kod = 'kategori-' . bin2hex(random_bytes(3));
    }
    return substr($kod, 0, 40);
}

/** @return list<array<string,mixed>> */
function shopMenuCategories(int $shopId, bool $activeOnly = true): array
{
    if (!menuCategoriesTableExists()) {
        return [];
    }
    ensureShopMenuCategories($shopId);
    $sql = 'SELECT * FROM menu_categories WHERE shop_id = ?';
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' ORDER BY urutan ASC, id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute([$shopId]);
    return $stmt->fetchAll() ?: [];
}

function findShopMenuCategory(int $shopId, int $categoryId): ?array
{
    if (!menuCategoriesTableExists() || $categoryId <= 0) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM menu_categories WHERE id = ? AND shop_id = ? LIMIT 1');
    $stmt->execute([$categoryId, $shopId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function menuCategoryByKod(int $shopId, string $kod): ?array
{
    if (!menuCategoriesTableExists()) {
        return null;
    }
    ensureShopMenuCategories($shopId);
    $stmt = db()->prepare('SELECT * FROM menu_categories WHERE shop_id = ? AND kod = ? LIMIT 1');
    $stmt->execute([$shopId, $kod]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function menuCategoryLabel(array $category, string $lang = 'my'): string
{
    $name = $lang === 'en'
        ? (string) ($category['nama_en'] ?: $category['nama_my'] ?? '')
        : (string) ($category['nama_my'] ?: $category['nama_en'] ?? '');
    return $name !== '' ? $name : (string) ($category['kod'] ?? 'Menu');
}

function menuCategoryKind(array $category): string
{
    $kind = (string) ($category['kind'] ?? 'makanan');
    return $kind === 'minuman' ? 'minuman' : 'makanan';
}

function resolveMenuCategoryId(int $shopId, int $postedId): ?int
{
    if (!menuCategoriesTableExists() || !menuCategoryColumnExists()) {
        return null;
    }
    if ($postedId > 0) {
        $cat = findShopMenuCategory($shopId, $postedId);
        if ($cat && (int) $cat['is_active'] === 1) {
            return (int) $cat['id'];
        }
    }
    $fallback = menuCategoryByKod($shopId, 'makanan');
    return $fallback ? (int) $fallback['id'] : null;
}

function countCustomMenuCategories(int $shopId): int
{
    if (!menuCategoriesTableExists()) {
        return 0;
    }
    $stmt = db()->prepare('SELECT COUNT(*) FROM menu_categories WHERE shop_id = ? AND is_system = 0');
    $stmt->execute([$shopId]);
    return (int) $stmt->fetchColumn();
}

function ensureShopMenuCategories(int $shopId, ?PDO $pdo = null): void
{
    if ($shopId <= 0 || !menuCategoriesTableExists()) {
        return;
    }
    $pdo = $pdo ?? db();
    $chk = $pdo->prepare("SELECT kod FROM menu_categories WHERE shop_id = ? AND kod IN ('makanan','minuman')");
    $chk->execute([$shopId]);
    $have = [];
    foreach ($chk->fetchAll() as $row) {
        $have[(string) $row['kod']] = true;
    }
    $ins = $pdo->prepare(
        'INSERT INTO menu_categories (shop_id, kod, nama_my, nama_en, kind, is_system, urutan, is_active)
         VALUES (?, ?, ?, ?, ?, 1, ?, 1)'
    );
    if (empty($have['makanan'])) {
        $ins->execute([$shopId, 'makanan', 'Makanan', 'Food', 'makanan', 1]);
    }
    if (empty($have['minuman'])) {
        $ins->execute([$shopId, 'minuman', 'Minuman', 'Drinks', 'minuman', 2]);
    }
}

function ensureAllShopMenuCategories(?PDO $pdo = null): void
{
    if (!menuCategoriesTableExists()) {
        return;
    }
    $pdo = $pdo ?? db();
    foreach ($pdo->query('SELECT id FROM shops')->fetchAll() as $row) {
        ensureShopMenuCategories((int) $row['id'], $pdo);
    }
}

function backfillMenuItemCategories(?PDO $pdo = null): void
{
    $pdo = $pdo ?? db();
    if (!menuCategoriesTableExists() || !menuCategoryColumnExists()) {
        return;
    }
    $pdo->exec(
        "UPDATE menu_items mi
         INNER JOIN menu_categories mc
           ON mc.shop_id = mi.shop_id
          AND mc.kod = mi.kategori
         SET mi.menu_category_id = mc.id
         WHERE mi.menu_category_id IS NULL"
    );
}
