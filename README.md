# TableTap

QR ordering system for Malaysian F&B — customers scan a table QR, order from their phone, and the kitchen/cashier tablets update live.

**Live:** [tabletap.my](https://tabletap.my)

## Stack

- PHP 8 + MySQL/MariaDB (shared hosting friendly)
- Vanilla JS dashboards (kasir, dapur, waiter) with Bluetooth ESC/POS printing
- Multi-shop, packages (Mula / Standard / Pro), SST, takeaway, delivery, cafe mode

## Repo layout

| Path | Purpose |
|------|---------|
| `public/` | Customer order UI + APIs |
| `admin/` | Staff dashboards (owner, kasir, dapur, waiter) |
| `includes/` | Shared PHP helpers, i18n, schema patch |
| `database/` | Schema + incremental SQL migrations |
| `clients/` | Per-client QR print kits and invoices |
| `docs/` | Ops notes (print bridge, AI agent) |
| `apps/print-bridge/` | Optional LAN print bridge |

## Branches

| Branch | Use |
|--------|-----|
| `main` | Development / default |
| `production` | Live site (`tabletap.my`) |
| `staging` | Staging deploy |

## Local notes

1. Copy `config/config.example.php` → `config/config.php` and set DB credentials.
2. Import `database/schema.sql` (then any needed `migrate_*.sql` files).
3. Serve the project root with PHP or Apache/Nginx.

Do **not** commit `config/config.php`, `leads/`, or raw WhatsApp photo dumps.

## Docs

| Doc | Topic |
|-----|--------|
| [`docs/deploy.md`](docs/deploy.md) | Hostinger branches / pull |
| [`docs/roles.md`](docs/roles.md) | Owner, kasir, dapur, waiter |
| [`docs/cron.md`](docs/cron.md) | Cron + seed scripts |
| [`docs/meta-ads.md`](docs/meta-ads.md) | WhatsApp Meta Ads playbook |
| [`docs/meta-ads-checklist.md`](docs/meta-ads-checklist.md) | Pre-publish Ads checklist |
| [`docs/meta-ads-day4-review.md`](docs/meta-ads-day4-review.md) | Day-4 Ads review decisions |
| [`docs/seo.md`](docs/seo.md) | Sitemap, robots, blog SEO |
| [`tools/outreach/wa-balasan-demo.md`](tools/outreach/wa-balasan-demo.md) | WA reply scripts for ad leads |
| [`docs/print-bridge.md`](docs/print-bridge.md) | Wi-Fi ESC/POS bridge |
| [`docs/ai-agent/`](docs/ai-agent/) | ChatLM system prompt |
| [`SECURITY.md`](SECURITY.md) | Secrets & reporting |

## Client kits

See [`clients/README.md`](clients/README.md) for Big Tree Western, Port Santai, and how to print table QR packs.
