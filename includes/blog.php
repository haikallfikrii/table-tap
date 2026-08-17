<?php
/**
 * Blog posts — file-based content in content/blog/*.php
 */

declare(strict_types=1);

function blogContentDir(): string
{
    return dirname(__DIR__) . '/content/blog';
}

/** @return list<array<string, mixed>> */
function blogAllPosts(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $dir = blogContentDir();
    $posts = [];
    if (!is_dir($dir)) {
        $cache = [];
        return $cache;
    }
    foreach (glob($dir . '/*.php') ?: [] as $file) {
        $data = require $file;
        if (!is_array($data) || empty($data['slug'])) {
            continue;
        }
        $posts[] = $data;
    }
    usort($posts, static function (array $a, array $b): int {
        $af = !empty($a['featured']) ? 1 : 0;
        $bf = !empty($b['featured']) ? 1 : 0;
        if ($af !== $bf) {
            return $bf <=> $af;
        }
        return strcmp((string) ($b['published'] ?? ''), (string) ($a['published'] ?? ''));
    });
    $cache = $posts;
    return $cache;
}

/** @return array<string, mixed>|null */
function blogPostBySlug(string $slug): ?array
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }
    foreach (blogAllPosts() as $post) {
        if (($post['slug'] ?? '') === $slug) {
            return $post;
        }
    }
    return null;
}

function blogField(array $post, string $key, string $lang): string
{
    $suffix = $lang === 'en' ? '_en' : '_my';
    $field = $key . $suffix;
    if (!empty($post[$field])) {
        return (string) $post[$field];
    }
    return (string) ($post[$key . '_my'] ?? $post[$key] ?? '');
}

function blogUrl(string $slug = ''): string
{
    return $slug === '' ? baseUrl('blog/') : baseUrl('blog/' . rawurlencode($slug));
}

function blogFormatDate(string $ymd, string $lang): string
{
    $ts = strtotime($ymd);
    if ($ts === false) {
        return $ymd;
    }
    if ($lang === 'en') {
        return date('j M Y', $ts);
    }
    $months = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mac', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Ogo', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Dis',
    ];
    $m = (int) date('n', $ts);
    return date('j', $ts) . ' ' . ($months[$m] ?? date('M', $ts)) . ' ' . date('Y', $ts);
}

function blogCategoryLabel(string $cat, string $lang): string
{
    $key = 'blog_cat_' . $cat;
    $label = t($key);
    return $label !== $key ? $label : ucfirst($cat);
}

/** @return list<string> */
function blogCategories(): array
{
    $cats = [];
    foreach (blogAllPosts() as $post) {
        $c = (string) ($post['category'] ?? '');
        if ($c !== '' && !in_array($c, $cats, true)) {
            $cats[] = $c;
        }
    }
    sort($cats);
    return $cats;
}

function blogReadingMinutes(string $html): int
{
    $text = trim(strip_tags($html));
    if ($text === '') {
        return 1;
    }
    $words = preg_match_all('/\S+/u', $text) ?: 0;
    return max(1, (int) ceil($words / 200));
}

function blogReadingLabel(string $html, string $lang): string
{
    $mins = blogReadingMinutes($html);
    if ($lang === 'en') {
        return $mins . ' min read';
    }
    return $mins . ' min baca';
}

/**
 * Inject heading IDs and build table of contents.
 *
 * @return array{html:string,toc:list<array{id:string,text:string,level:int}>}
 */
function blogEnhanceBody(string $html): array
{
    $toc = [];
    $i = 0;
    $enhanced = preg_replace_callback(
        '/<(h[23])([^>]*)>(.*?)<\/\1>/is',
        static function (array $m) use (&$toc, &$i): string {
            $level = (int) substr($m[1], 1);
            $text = trim(strip_tags($m[3]));
            if ($text === '') {
                return $m[0];
            }
            $id = 'sec-' . (++$i);
            $toc[] = ['id' => $id, 'text' => $text, 'level' => $level];
            $attrs = $m[2];
            if (preg_match('/\bid\s*=/', $attrs)) {
                return $m[0];
            }
            return '<' . $m[1] . ' id="' . $id . '"' . $attrs . '>' . $m[3] . '</' . $m[1] . '>';
        },
        $html
    );
    return ['html' => $enhanced ?? $html, 'toc' => $toc];
}

/** @return list<array<string, mixed>> */
function blogRelatedPosts(string $slug, string $category, int $limit = 3): array
{
    $related = [];
    foreach (blogAllPosts() as $post) {
        if (($post['slug'] ?? '') === $slug) {
            continue;
        }
        if ($category !== '' && ($post['category'] ?? '') === $category) {
            $related[] = $post;
        }
    }
    if (count($related) < $limit) {
        foreach (blogAllPosts() as $post) {
            if (($post['slug'] ?? '') === $slug) {
                continue;
            }
            $already = false;
            foreach ($related as $r) {
                if (($r['slug'] ?? '') === ($post['slug'] ?? '')) {
                    $already = true;
                    break;
                }
            }
            if (!$already) {
                $related[] = $post;
            }
            if (count($related) >= $limit) {
                break;
            }
        }
    }
    return array_slice($related, 0, $limit);
}
