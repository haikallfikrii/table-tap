<?php
/**
 * Serve persisted uploads from storage/uploads/ (not wiped on git deploy).
 * GET: f=storage/uploads/menu/example.jpg
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/helpers.php';

$rel = trim((string) ($_GET['f'] ?? ''));
$rel = ltrim(str_replace(['..', '\\'], '', $rel), '/');

if ($rel === '' || !str_starts_with($rel, 'storage/uploads/')) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    exit;
}

$path = resolveUploadPath($rel);
if ($path === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path) ?: 'application/octet-stream';
if ($mime === 'application/octet-stream') {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'heic', 'heif' => 'image/heic',
        'pdf' => 'application/pdf',
        default => 'application/octet-stream',
    };
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
