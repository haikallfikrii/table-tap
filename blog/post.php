<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/i18n.php';
require_once dirname(__DIR__) . '/includes/public_layout.php';

$lang = currentLang();
$slug = trim((string) ($_GET['slug'] ?? ''));
$post = blogPostBySlug($slug);

if ($post === null) {
    http_response_code(404);
    publicLayoutStart([
        'title' => t('blog_not_found') . ' — TableTap',
        'description' => t('blog_not_found'),
        'path' => 'blog/' . rawurlencode($slug),
        'noindex' => true,
        'extra_css' => ['css/blog.css'],
    ]);
    echo '<div class="wrap blog-wrap"><h1>' . e(t('blog_not_found')) . '</h1><p><a href="' . e(blogUrl()) . '">' . e(t('blog_back')) . '</a></p></div>';
    publicLayoutEnd();
    exit;
}

$postTitle = blogField($post, 'title', $lang);
$excerpt = blogField($post, 'excerpt', $lang);
$rawBody = blogField($post, 'body', $lang);
$enhanced = blogEnhanceBody($rawBody);
$body = $enhanced['html'];
$toc = $enhanced['toc'];
$date = blogFormatDate((string) ($post['published'] ?? ''), $lang);
$category = (string) ($post['category'] ?? '');
$readLabel = blogReadingLabel($rawBody, $lang);
$path = 'blog/' . $slug;
$canonicalUrl = baseUrl($path);
$related = blogRelatedPosts($slug, $category, 3);
$waShare = 'https://wa.me/?text=' . rawurlencode($postTitle . ' — ' . $canonicalUrl);

publicLayoutStart([
    'title' => $postTitle . ' — TableTap',
    'description' => $excerpt,
    'path' => $path,
    'type' => 'article',
    'extra_css' => ['css/blog.css'],
    'extra_js' => ['js/blog.js'],
    'body_class' => 'blog-single',
    'json_ld' => static function () use ($post, $lang, $postTitle, $canonicalUrl): void {
        seoJsonLdBlogPost($post, $lang);
        seoJsonLdBreadcrumbs([
            ['name' => t('blog_home'), 'url' => baseUrl('')],
            ['name' => t('lp_nav_blog'), 'url' => blogUrl()],
            ['name' => $postTitle, 'url' => $canonicalUrl],
        ]);
    },
]);
?>
<div class="wrap blog-single-wrap">
  <div class="blog-single-layout">
    <?php if ($toc !== []): ?>
    <aside class="blog-toc-aside reveal" aria-label="<?= e(t('blog_toc')) ?>">
      <div class="blog-toc-panel" id="blog-toc-panel">
        <p class="blog-toc-title"><?= e(t('blog_toc')) ?></p>
        <nav id="blog-toc-nav">
          <ol>
            <?php foreach ($toc as $item): ?>
              <li class="blog-toc-l<?= (int) $item['level'] ?>">
                <a href="#<?= e($item['id']) ?>"><?= e($item['text']) ?></a>
              </li>
            <?php endforeach; ?>
          </ol>
        </nav>
      </div>
    </aside>
    <?php endif; ?>

    <div class="blog-single-main">
      <nav class="blog-breadcrumb reveal" aria-label="Breadcrumb">
        <a href="<?= e(baseUrl('')) ?>"><?= e(t('blog_home')) ?></a>
        <span aria-hidden="true">/</span>
        <a href="<?= e(blogUrl()) ?>"><?= e(t('lp_nav_blog')) ?></a>
        <span aria-hidden="true">/</span>
        <span aria-current="page"><?= e($postTitle) ?></span>
      </nav>

    <article class="blog-article reveal" id="blog-article">
      <header class="blog-article-head">
        <div class="blog-article-meta">
          <?php if ($category !== ''): ?>
            <a class="blog-tag" href="<?= e(blogUrl()) ?>?cat=<?= e(rawurlencode($category)) ?>"><?= e(blogCategoryLabel($category, $lang)) ?></a>
          <?php endif; ?>
          <time datetime="<?= e((string) ($post['published'] ?? '')) ?>"><?= e($date) ?></time>
          <span class="blog-read-time"><?= e($readLabel) ?></span>
        </div>
        <h1><?= e($postTitle) ?></h1>
        <p class="blog-lead"><?= e($excerpt) ?></p>
        <div class="blog-share">
          <span><?= e(t('blog_share')) ?>:</span>
          <a class="blog-share-btn" href="<?= e($waShare) ?>" target="_blank" rel="noopener noreferrer" title="WhatsApp">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.5.5 0 00.611.611l4.458-1.495A11.953 11.953 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.012-1.378l-.358-.213-2.642.886.886-2.575-.233-.374A9.818 9.818 0 1112 21.818z"/></svg>
            WhatsApp
          </a>
          <button type="button" class="blog-share-btn" id="blog-copy-link" data-url="<?= e($canonicalUrl) ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <?= e(t('blog_copy_link')) ?>
          </button>
        </div>
      </header>

      <div class="blog-body" id="blog-body">
        <?= $body ?>
      </div>

      <footer class="blog-article-foot">
        <div class="blog-cta blog-cta-inline">
          <h2><?= e(t('blog_cta_title')) ?></h2>
          <p><?= e(t('blog_cta_desc')) ?></p>
          <a class="btn btn-primary" href="https://wa.me/601125352270?text=<?= rawurlencode(t('lp_wa_start')) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('lp_cta_start')) ?></a>
        </div>
      </footer>
    </article>
    </div>
  </div>

  <?php if ($related !== []): ?>
  <section class="blog-related reveal" aria-labelledby="blog-related-title">
    <h2 id="blog-related-title"><?= e(t('blog_related')) ?></h2>
    <div class="blog-related-grid">
      <?php foreach ($related as $rel): ?>
        <?php
          $rSlug = (string) ($rel['slug'] ?? '');
          $rTitle = blogField($rel, 'title', $lang);
          $rExcerpt = blogField($rel, 'excerpt', $lang);
          $rCat = (string) ($rel['category'] ?? '');
        ?>
        <article class="blog-related-card">
          <?php if ($rCat !== ''): ?>
            <span class="blog-tag"><?= e(blogCategoryLabel($rCat, $lang)) ?></span>
          <?php endif; ?>
          <h3><a href="<?= e(blogUrl($rSlug)) ?>"><?= e($rTitle) ?></a></h3>
          <p><?= e(mb_strimwidth($rExcerpt, 0, 120, '…')) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <p class="blog-back-link reveal"><a href="<?= e(blogUrl()) ?>">← <?= e(t('blog_back')) ?></a></p>
</div>
<?php
publicLayoutEnd();
