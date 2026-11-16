# Architecture overview (ringkas)

```text
Customer phone (QR)
    → public/order.php + api/submit_order.php
        → MySQL orders / order_items
            → admin kasir / dapur / minuman / waiter (poll JSON)
                → Bluetooth ESC/POS atau print_jobs (Print Bridge)
```

| Lapisan | Path |
|---------|------|
| Customer | `public/` |
| Staff UI | `admin/` |
| Shared PHP | `includes/` |
| Schema | `database/` + `schema_patch.php` |
| i18n | `includes/lang/my.php`, `en.php` |
| Marketing site | `index.php`, `content/blog/` |
| Client print kits | `clients/` |

Branches: `main` (dev) · `production` (tabletap.my) · `staging`.
