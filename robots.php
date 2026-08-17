<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

header('Content-Type: text/plain; charset=utf-8');

$base = rtrim(appBaseUrl(), '/');

echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /admin/\n";
echo "Disallow: /cron/\n";
echo "Disallow: /includes/\n";
echo "Disallow: /config/\n";
echo "Disallow: /content/\n";
echo "Disallow: /database/\n";
echo "\n";
echo "Sitemap: {$base}/sitemap.xml\n";
