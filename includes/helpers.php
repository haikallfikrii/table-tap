<?php
/**
 * Helper functions — formatting, JSON responses, tokens, etc.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

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
    return baseUrl('assets/' . ltrim($path, '/'));
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
 * Returns table row or null.
 */
function findTableByAccess(string $nomorMeja, string $token): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM tables WHERE nomor_meja = ? AND token_akses = ? AND status = ? LIMIT 1'
    );
    $stmt->execute([$nomorMeja, $token, 'aktif']);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getMenuGrouped(string $lang = 'my'): array
{
    $stmt = db()->query(
        "SELECT id, nama_my, nama_en, deskripsi_my, deskripsi_en, harga, kategori, foto_url, status_stok
         FROM menu_items
         WHERE is_active = 1
         ORDER BY kategori ASC, urutan ASC, id ASC"
    );
    $items = $stmt->fetchAll();
    $grouped = ['makanan' => [], 'minuman' => []];

    foreach ($items as $item) {
        $item['nama'] = $lang === 'en' ? $item['nama_en'] : $item['nama_my'];
        $item['deskripsi'] = $lang === 'en' ? $item['deskripsi_en'] : $item['deskripsi_my'];
        $grouped[$item['kategori']][] = $item;
    }

    return $grouped;
}
