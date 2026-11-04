# SEO — landing & blog

## Public URLs

| Path | Source |
|------|--------|
| `/` | `index.php` landing |
| `/blog/` | blog index |
| `/blog/{slug}` | posts under `content/blog/` |
| `/sitemap.xml` | `sitemap.php` (rewritten) |
| `/robots.txt` | `robots.php` |

`sitemap.php` lists the homepage, blog index, and every post from `blogAllPosts()` with `lastmod` from `updated` / `published`.

## Checklist when adding a blog post

1. Add `content/blog/{slug}.php` with `slug`, `published`, `updated`, `title_my` / `title_en`.
2. Confirm it appears in `blogAllPosts()`.
3. Hard-refresh `https://tabletap.my/sitemap.xml` after deploy.
4. Optional: request indexing in Google Search Console.

## Do not index

Staff/admin paths and table order URLs with tokens should stay `noindex` via `seoMeta()` options where used.
