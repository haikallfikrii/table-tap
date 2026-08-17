<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

$base = rtrim(appBaseUrl(), '/');
$name = (string) (getConfig()['app_name'] ?? 'TableTap');

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=86400');

echo json_encode([
    'name' => $name,
    'short_name' => $name,
    'description' => 'Sistem pesanan QR all-in-one untuk F&B Malaysia',
    'start_url' => $base . '/',
    'scope' => $base . '/',
    'display' => 'standalone',
    'background_color' => '#fbf8f4',
    'theme_color' => '#e85d04',
    'icons' => [
        [
            'src' => $base . '/assets/img/brand/tabletap-icon-48.png',
            'sizes' => '48x48',
            'type' => 'image/png',
        ],
        [
            'src' => $base . '/assets/img/brand/tabletap-icon-192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
        ],
        [
            'src' => $base . '/assets/img/brand/tabletap-icon-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
