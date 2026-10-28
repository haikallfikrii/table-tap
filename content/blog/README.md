# Marketing blog posts

Each file returns a PHP array consumed by `includes/blog.php` and the landing/blog pages.

## Posts

| Slug | Topic |
|------|--------|
| `qr-pesanan-meja-malaysia` | How to set up QR table ordering (featured) |
| `tingkatkan-jualan-menu-digital` | Upselling with a digital menu |
| `sst-kedai-makan-qr-ordering` | SST on F&B bills with QR ordering |
| `waiter-vs-ambil-sendiri` | Waiter mode vs self-pickup |
| `masalah-operasi-fnb-qr` | Operational pain points QR solves |
| `kos-sistem-pesanan-qr` | Cost of QR ordering vs heavy POS |
| `warung-nasi-kampung-moden-tanpa-pos-mahal` | Modern warung without expensive POS |

## Fields

Common keys: `slug`, `published`, `updated`, `category`, `title_my` / `title_en`, `keywords_*`, body HTML or markdown sections (see an existing post).

Keep copy bilingual-ready; the site language toggle picks `*_my` vs `*_en`.
