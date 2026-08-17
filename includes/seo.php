<?php
/**
 * SEO helpers — meta tags, JSON-LD, Google Search Console verification.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/** Stable brand image URL — no cache-bust query string (Google needs a fixed favicon URL). */
function brandIconUrl(string $file): string
{
    return rtrim(appBaseUrl(), '/') . '/assets/img/brand/' . ltrim($file, '/');
}

/** Favicon + PWA icons for Google Search, Safari, and browser tabs. */
function seoFaviconLinks(): void
{
    $base = rtrim(appBaseUrl(), '/');
    ?>
  <link rel="icon" href="<?= e($base) ?>/favicon.ico" sizes="48x48">
  <link rel="icon" type="image/png" sizes="48x48" href="<?= e(brandIconUrl('tabletap-icon-48.png')) ?>">
  <link rel="icon" type="image/png" sizes="96x96" href="<?= e(brandIconUrl('tabletap-icon-96.png')) ?>">
  <link rel="icon" type="image/png" sizes="192x192" href="<?= e(brandIconUrl('tabletap-icon-192.png')) ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= e(brandIconUrl('apple-touch-icon.png')) ?>">
  <link rel="manifest" href="<?= e($base) ?>/site.webmanifest">
    <?php
}

/** @param array{title:string,description:string,path?:string,image?:string,type?:string,robots?:string,noindex?:bool} $opts */
function seoHead(array $opts): void
{
    $c = getConfig();
    $base = rtrim(appBaseUrl(), '/');
    $path = ltrim((string) ($opts['path'] ?? ''), '/');
    $canonical = $path === '' ? $base . '/' : $base . '/' . $path;
    $title = (string) ($opts['title'] ?? ($c['app_name'] ?? 'TableTap'));
    $desc = (string) ($opts['description'] ?? '');
    $image = (string) ($opts['image'] ?? assetUrl('img/brand/tabletap-icon-512.png'));
    if (!str_starts_with($image, 'http')) {
        $image = $base . '/' . ltrim($image, '/');
    }
    $type = (string) ($opts['type'] ?? 'website');
    $robots = (string) ($opts['robots'] ?? 'index, follow, max-image-preview:large');
    if (!empty($opts['noindex'])) {
        $robots = 'noindex, nofollow';
    }
    $verify = trim((string) ($c['google_site_verification'] ?? ''));
    ?>
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($desc) ?>">
  <meta name="robots" content="<?= e($robots) ?>">
  <link rel="canonical" href="<?= e($canonical) ?>">
  <?php if ($verify !== ''): ?>
  <meta name="google-site-verification" content="<?= e($verify) ?>">
  <?php endif; ?>
  <meta property="og:title" content="<?= e($title) ?>">
  <meta property="og:description" content="<?= e($desc) ?>">
  <meta property="og:url" content="<?= e($canonical) ?>">
  <meta property="og:image" content="<?= e($image) ?>">
  <meta property="og:type" content="<?= e($type) ?>">
  <meta property="og:site_name" content="<?= e($c['app_name'] ?? 'TableTap') ?>">
  <meta property="og:locale" content="<?= e(currentLang() === 'en' ? 'en_MY' : 'ms_MY') ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($title) ?>">
  <meta name="twitter:description" content="<?= e($desc) ?>">
  <meta name="twitter:image" content="<?= e($image) ?>">
    <?php
}

function seoJsonLd(array $data): void
{
    echo '<script type="application/ld+json">' . json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) . '</script>' . "\n";
}

/** @param array<int, array{0:string,1:string}> $faqs question/answer pairs */
function seoJsonLdOrganization(string $logoUrl): void
{
    $c = getConfig();
    $base = rtrim(appBaseUrl(), '/');
    seoJsonLd([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $c['app_name'] ?? 'TableTap',
        'url' => $base,
        'logo' => $logoUrl,
        'description' => 'Sistem pesanan QR untuk kedai makan kecil di Malaysia.',
        'areaServed' => 'MY',
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'contactType' => 'sales',
            'telephone' => '+60-11-2535-2270',
            'availableLanguage' => ['Malay', 'English'],
        ],
    ]);
}

function seoJsonLdWebSite(): void
{
    $c = getConfig();
    $base = rtrim(appBaseUrl(), '/');
    seoJsonLd([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $c['app_name'] ?? 'TableTap',
        'url' => $base,
        'inLanguage' => ['ms-MY', 'en-MY'],
    ]);
}

function seoJsonLdSoftwareApp(): void
{
    $c = getConfig();
    $base = rtrim(appBaseUrl(), '/');
    seoJsonLd([
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => $c['app_name'] ?? 'TableTap',
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Web',
        'url' => $base,
        'offers' => [
            '@type' => 'Offer',
            'price' => '29',
            'priceCurrency' => 'MYR',
            'description' => 'Percubaan percuma 2 minggu. Pakej dari RM 29/bulan.',
        ],
        'description' => 'Pelanggan imbas QR di meja, pesanan terus ke dapur, cashier dan kaunter minuman.',
    ]);
}

/** @param array<int, array{0:string,1:string}> $faqs */
function seoJsonLdFaq(array $faqs): void
{
    $items = [];
    foreach ($faqs as $pair) {
        $items[] = [
            '@type' => 'Question',
            'name' => $pair[0],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $pair[1],
            ],
        ];
    }
    seoJsonLd([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $items,
    ]);
}

/** @param array<string, mixed> $post */
function seoJsonLdBlogPost(array $post, string $lang): void
{
    require_once __DIR__ . '/blog.php';
    $base = rtrim(appBaseUrl(), '/');
    $slug = (string) ($post['slug'] ?? '');
    $body = blogField($post, 'body', $lang);
    $cat = (string) ($post['category'] ?? '');
    seoJsonLd([
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => blogField($post, 'title', $lang),
        'description' => blogField($post, 'excerpt', $lang),
        'datePublished' => (string) ($post['published'] ?? ''),
        'dateModified' => (string) ($post['updated'] ?? $post['published'] ?? ''),
        'wordCount' => blogReadingMinutes($body) * 200,
        'articleSection' => $cat !== '' ? blogCategoryLabel($cat, $lang) : null,
        'keywords' => blogField($post, 'keywords', $lang) ?: null,
        'author' => [
            '@type' => 'Organization',
            'name' => getConfig()['app_name'] ?? 'TableTap',
            'url' => $base,
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => getConfig()['app_name'] ?? 'TableTap',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => assetUrl('img/brand/tabletap-icon-512.png'),
            ],
        ],
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $base . '/blog/' . $slug,
        ],
        'url' => $base . '/blog/' . $slug,
        'inLanguage' => $lang === 'en' ? 'en-MY' : 'ms-MY',
    ]);
}

/** @param list<array{name:string,url?:string}> $items */
function seoJsonLdBreadcrumbs(array $items): void
{
    $list = [];
    $pos = 1;
    foreach ($items as $item) {
        $entry = [
            '@type' => 'ListItem',
            'position' => $pos++,
            'name' => $item['name'],
        ];
        if (!empty($item['url'])) {
            $entry['item'] = $item['url'];
        }
        $list[] = $entry;
    }
    seoJsonLd([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $list,
    ]);
}

/** @param list<array<string, mixed>> $posts */
function seoJsonLdBlogListing(array $posts, string $lang): void
{
    require_once __DIR__ . '/blog.php';
    $base = rtrim(appBaseUrl(), '/');
    $elements = [];
    $pos = 1;
    foreach ($posts as $post) {
        $slug = (string) ($post['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $elements[] = [
            '@type' => 'ListItem',
            'position' => $pos++,
            'url' => $base . '/blog/' . $slug,
            'name' => blogField($post, 'title', $lang),
        ];
    }
    seoJsonLd([
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => t('blog_index_title'),
        'description' => t('blog_index_desc'),
        'numberOfItems' => count($elements),
        'itemListElement' => $elements,
    ]);
}
