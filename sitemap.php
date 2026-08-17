<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/blog.php';

header('Content-Type: application/xml; charset=utf-8');

$base = rtrim(appBaseUrl(), '/');
$today = date('Y-m-d');

/** @return list<array{loc:string,lastmod:string,changefreq:string,priority:string}> */
function sitemapEntries(): array
{
    global $base, $today;
    $entries = [
        [
            'loc' => $base . '/',
            'lastmod' => $today,
            'changefreq' => 'weekly',
            'priority' => '1.0',
        ],
        [
            'loc' => $base . '/blog/',
            'lastmod' => $today,
            'changefreq' => 'weekly',
            'priority' => '0.8',
        ],
    ];
    foreach (blogAllPosts() as $post) {
        $slug = (string) ($post['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $entries[] = [
            'loc' => $base . '/blog/' . rawurlencode($slug),
            'lastmod' => (string) ($post['updated'] ?? $post['published'] ?? $today),
            'changefreq' => 'monthly',
            'priority' => '0.7',
        ];
    }
    return $entries;
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach (sitemapEntries() as $row): ?>
  <url>
    <loc><?= htmlspecialchars($row['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></loc>
    <lastmod><?= htmlspecialchars($row['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></lastmod>
    <changefreq><?= htmlspecialchars($row['changefreq'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></changefreq>
    <priority><?= htmlspecialchars($row['priority'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
