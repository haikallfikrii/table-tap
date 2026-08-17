<?php

declare(strict_types=1);

/**
 * Serve the brand favicon via PHP so Hostinger/LiteSpeed does not
 * return a cached static 404 for /favicon.ico (same pattern as robots.txt).
 */

$dir = __DIR__ . '/assets/img/brand';
$candidates = [
    ['file' => $dir . '/favicon.ico', 'type' => 'image/x-icon'],
    ['file' => $dir . '/tabletap-icon-48.png', 'type' => 'image/png'],
    ['file' => $dir . '/tabletap-icon-192.png', 'type' => 'image/png'],
];

foreach ($candidates as $item) {
    if (!is_file($item['file'])) {
        continue;
    }
    header('Content-Type: ' . $item['type']);
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . (string) filesize($item['file']));
    readfile($item['file']);
    exit;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Favicon not found.\n";
