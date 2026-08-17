<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/i18n.php';
require_once dirname(__DIR__) . '/includes/public_layout.php';

$lang = currentLang();
$posts = blogAllPosts();
$categories = blogCategories();
$featured = $posts[0] ?? null;
$rest = $featured !== null && !empty($featured['featured']) ? array_slice($posts, 1) : $posts;
if ($featured !== null && empty($featured['featured'])) {
    $rest = $posts;
    $featured = null;
}

$title = t('blog_index_title') . ' — ' . (getConfig()['app_name'] ?? 'TableTap');
$desc = t('blog_index_desc');

publicLayoutStart([
    'title' => $title,
    'description' => $desc,
    'path' => 'blog/',
    'extra_css' => ['css/blog.css'],
    'extra_js' => ['js/blog.js'],
    'json_ld' => static function () use ($lang, $posts): void {
        seoJsonLd([
            '@context' => 'https://schema.org',
            '@type' => 'Blog',
            'name' => t('blog_index_title'),
            'description' => t('blog_index_desc'),
            'url' => blogUrl(),
            'inLanguage' => $lang === 'en' ? 'en-MY' : 'ms-MY',
            'publisher' => [
                '@type' => 'Organization',
                'name' => getConfig()['app_name'] ?? 'TableTap',
            ],
        ]);
        seoJsonLdBreadcrumbs([
            ['name' => t('blog_home'), 'url' => baseUrl('')],
            ['name' => t('lp_nav_blog')],
        ]);
        seoJsonLdBlogListing($posts, $lang);
    },
]);
?>
<div class="wrap blog-index-wrap">
  <header class="blog-hero reveal">
    <span class="kicker"><?= e(t('blog_kicker')) ?></span>
    <h1><?= e(t('blog_index_title')) ?></h1>
    <p class="blog-lead"><?= e(t('blog_index_desc')) ?></p>
  </header>

  <div class="blog-toolbar reveal" role="search">
    <label class="blog-search" for="blog-search">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3-3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      <input type="search" id="blog-search" placeholder="<?= e(t('blog_search_ph')) ?>" autocomplete="off">
    </label>
    <div class="blog-filters" role="group" aria-label="<?= e(t('blog_filter_label')) ?>">
      <button type="button" class="blog-filter active" data-filter="all"><?= e(t('blog_filter_all')) ?></button>
      <?php foreach ($categories as $cat): ?>
        <button type="button" class="blog-filter" data-filter="<?= e($cat) ?>"><?= e(blogCategoryLabel($cat, $lang)) ?></button>
      <?php endforeach; ?>
    </div>
  </div>

  <p class="blog-no-results" id="blog-no-results" hidden><?= e(t('blog_no_results')) ?></p>

  <?php if ($featured !== null): ?>
    <?php
      $fSlug = (string) ($featured['slug'] ?? '');
      $fTitle = blogField($featured, 'title', $lang);
      $fExcerpt = blogField($featured, 'excerpt', $lang);
      $fBody = blogField($featured, 'body', $lang);
      $fCat = (string) ($featured['category'] ?? '');
      $fDate = blogFormatDate((string) ($featured['published'] ?? ''), $lang);
    ?>
    <article class="blog-featured reveal" data-category="<?= e($fCat) ?>" data-title="<?= e(mb_strtolower($fTitle)) ?>" data-excerpt="<?= e(mb_strtolower($fExcerpt)) ?>">
      <div class="blog-featured-badge"><?= e(t('blog_featured')) ?></div>
      <div class="blog-featured-inner">
        <div class="blog-featured-meta">
          <?php if ($fCat !== ''): ?>
            <span class="blog-tag"><?= e(blogCategoryLabel($fCat, $lang)) ?></span>
          <?php endif; ?>
          <time datetime="<?= e((string) ($featured['published'] ?? '')) ?>"><?= e($fDate) ?></time>
          <span class="blog-read-time"><?= e(blogReadingLabel($fBody, $lang)) ?></span>
        </div>
        <h2><a href="<?= e(blogUrl($fSlug)) ?>"><?= e($fTitle) ?></a></h2>
        <p><?= e($fExcerpt) ?></p>
        <a class="btn btn-primary" href="<?= e(blogUrl($fSlug)) ?>"><?= e(t('blog_read_more')) ?> →</a>
      </div>
    </article>
  <?php endif; ?>

  <?php if ($rest === [] && $featured === null): ?>
    <p class="blog-empty"><?= e(t('blog_empty')) ?></p>
  <?php else: ?>
    <div class="blog-grid" id="blog-grid">
      <?php foreach ($rest as $post): ?>
        <?php
          $slug = (string) ($post['slug'] ?? '');
          $postTitle = blogField($post, 'title', $lang);
          $excerpt = blogField($post, 'excerpt', $lang);
          $body = blogField($post, 'body', $lang);
          $cat = (string) ($post['category'] ?? '');
          $date = blogFormatDate((string) ($post['published'] ?? ''), $lang);
        ?>
        <article class="blog-card reveal" data-category="<?= e($cat) ?>" data-title="<?= e(mb_strtolower($postTitle)) ?>" data-excerpt="<?= e(mb_strtolower($excerpt)) ?>">
          <div class="blog-card-top">
            <?php if ($cat !== ''): ?>
              <span class="blog-tag"><?= e(blogCategoryLabel($cat, $lang)) ?></span>
            <?php endif; ?>
            <time datetime="<?= e((string) ($post['published'] ?? '')) ?>"><?= e($date) ?></time>
          </div>
          <h2><a href="<?= e(blogUrl($slug)) ?>"><?= e($postTitle) ?></a></h2>
          <p><?= e($excerpt) ?></p>
          <div class="blog-card-foot">
            <span class="blog-read-time"><?= e(blogReadingLabel($body, $lang)) ?></span>
            <a class="blog-read" href="<?= e(blogUrl($slug)) ?>"><?= e(t('blog_read_more')) ?> →</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <aside class="blog-cta reveal">
    <div class="blog-cta-icon" aria-hidden="true">🍽️</div>
    <h2><?= e(t('lp_cta_title')) ?></h2>
    <p><?= e(t('lp_cta_sub')) ?></p>
    <a class="btn btn-primary btn-lg" href="https://wa.me/601125352270?text=<?= rawurlencode(t('lp_wa_start')) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('lp_cta_start')) ?></a>
  </aside>
</div>
<?php
publicLayoutEnd();
