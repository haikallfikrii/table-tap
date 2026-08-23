<?php
/**
 * Stream shop DuitNow QR with correct Content-Type for Safari iOS.
 * GET: shop_id=…  OR  shop=slug&token=delivery_or_shop_token
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/helpers.php';

$shopId = (int) ($_GET['shop_id'] ?? 0);
$slug = trim((string) ($_GET['shop'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));

$shop = null;
if ($shopId > 0) {
    $shop = findShopById($shopId);
} elseif ($slug !== '' && $token !== '') {
    $shop = findShopByDeliveryAccess($slug, $token) ?: findShopByAccess($slug, $token);
}

if (!$shop || empty($shop['duitnow_qr_url'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'QR not found';
    exit;
}

$path = shopDuitnowQrPath($shop);
if ($path === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'QR file missing';
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path) ?: 'image/png';
if (!str_starts_with($mime, 'image/')) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        default => 'image/png',
    };
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
