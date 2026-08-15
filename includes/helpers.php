<?php
/**
 * Helper functions — formatting, JSON responses, tokens, etc.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/shop.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatMoney(float|string $amount): string
{
    $c = getConfig();
    $decimals = (int) ($c['currency_decimals'] ?? 2);
    $symbol = $c['currency'] ?? 'RM';
    return $symbol . ' ' . number_format((float) $amount, $decimals, '.', ',');
}

function generateToken(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function baseUrl(string $path = ''): string
{
    $c = getConfig();
    $base = rtrim($c['app_url'] ?? '', '/');
    $path = ltrim($path, '/');
    return $path === '' ? $base : $base . '/' . $path;
}

function orderUrl(string|int $nomorMeja, string $token): string
{
    return baseUrl('public/order.php?meja=' . urlencode((string) $nomorMeja) . '&token=' . urlencode($token));
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $status = 400, array $extra = []): never
{
    jsonResponse(array_merge(['ok' => false, 'error' => $message], $extra), $status);
}

function requirePost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        jsonError('Method not allowed', 405);
    }
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function assetUrl(string $path): string
{
    $rel = ltrim($path, '/');
    $url = baseUrl('assets/' . $rel);
    $file = dirname(__DIR__) . '/assets/' . $rel;
    if (is_file($file)) {
        $url .= '?v=' . filemtime($file);
    }
    return $url;
}

function uploadPath(string $filename = ''): string
{
    $dir = dirname(__DIR__) . '/assets/uploads/menu';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $filename === '' ? $dir : $dir . '/' . $filename;
}

/**
 * Verify table access via nomor + token.
 * Returns table row + shop fields, or null.
 */
function findTableByAccess(string $nomorMeja, string $token): ?array
{
    $stmt = db()->prepare(
        "SELECT t.*, s.nama_kedai, s.slug, s.status AS shop_status,
                s.sst_enabled, s.sst_rate, s.package_id, s.fulfillment_mode,
                p.kod AS package_kod
         FROM tables t
         INNER JOIN shops s ON s.id = t.shop_id
         INNER JOIN packages p ON p.id = s.package_id
         WHERE t.nomor_meja = ? AND t.token_akses = ? AND t.status = 'aktif' AND s.status = 'aktif'
         LIMIT 1"
    );
    $stmt->execute([$nomorMeja, $token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getMenuGrouped(int $shopId, string $lang = 'my'): array
{
    $stmt = db()->prepare(
        "SELECT id, nama_my, nama_en, deskripsi_my, deskripsi_en, harga, kategori, foto_url, status_stok
         FROM menu_items
         WHERE shop_id = ? AND is_active = 1
         ORDER BY kategori ASC, urutan ASC, id ASC"
    );
    $stmt->execute([$shopId]);
    $items = $stmt->fetchAll();
    $ids = array_map(static fn($row) => (int) $row['id'], $items);
    $gallery = [];
    if ($ids !== []) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        try {
            $g = db()->prepare(
                "SELECT id, menu_item_id, foto_url
                 FROM menu_photos
                 WHERE shop_id = ? AND menu_item_id IN ($placeholders)
                 ORDER BY urutan ASC, id ASC"
            );
            $g->execute(array_merge([$shopId], $ids));
            foreach ($g->fetchAll() as $photo) {
                $gallery[(int) $photo['menu_item_id']][] = $photo;
            }
        } catch (Throwable $e) {
            $gallery = [];
        }
    }
    $grouped = ['makanan' => [], 'minuman' => []];

    foreach ($items as $item) {
        $item['nama'] = $lang === 'en' ? $item['nama_en'] : $item['nama_my'];
        $item['deskripsi'] = $lang === 'en' ? $item['deskripsi_en'] : $item['deskripsi_my'];
        $photos = [];
        if (!empty($item['foto_url'])) {
            $photos[] = $item['foto_url'];
        }
        foreach ($gallery[(int) $item['id']] ?? [] as $photo) {
            if (!empty($photo['foto_url']) && !in_array($photo['foto_url'], $photos, true)) {
                $photos[] = $photo['foto_url'];
            }
        }
        $item['gallery'] = $photos;
        $grouped[$item['kategori']][] = $item;
    }

    return $grouped;
}

/** Overall self-pickup track stage from order_items rows. */
function trackStageFromItems(array $items): string
{
    if ($items === []) {
        return 'queue';
    }
    $allDone = true;
    $anyReady = false;
    $anyCook = false;
    foreach ($items as $it) {
        $st = (string) ($it['status_item'] ?? '');
        if ($st !== 'dihantar') {
            $allDone = false;
        }
        if ($st === 'siap' || $st === 'diambil') {
            $anyReady = true;
        }
        if ($st === 'sedang_dimasak') {
            $anyCook = true;
        }
    }
    if ($allDone) {
        return 'done';
    }
    if ($anyReady) {
        return 'ready';
    }
    if ($anyCook) {
        return 'cooking';
    }
    return 'queue';
}
