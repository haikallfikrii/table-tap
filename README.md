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
| [`docs/architecture-overview.md`](docs/architecture-overview.md) | High-level request flow |
| [`docs/onboarding-new-shop.md`](docs/onboarding-new-shop.md) | New shop go-live checklist |
| [`docs/troubleshooting-order-fail.md`](docs/troubleshooting-order-fail.md) | Failed order submit playbook |
| [`docs/printer-kasir.md`](docs/printer-kasir.md) | Kasir Bluetooth printer tips |
| [`docs/faq-owner.md`](docs/faq-owner.md) | Short owner FAQ |
| [`docs/daily-ops-checklist.md`](docs/daily-ops-checklist.md) | Daily business + commit habit |
| [`docs/support-whatsapp-playbook.md`](docs/support-whatsapp-playbook.md) | Support WA triage templates |
| [`docs/staff-training-script.md`](docs/staff-training-script.md) | 15-min staff training script |
| [`docs/menu-photo-guidelines.md`](docs/menu-photo-guidelines.md) | Menu photo guidelines |
| [`docs/github-daily-habit.md`](docs/github-daily-habit.md) | Keep GitHub green with real commits |
| [`docs/cash-change-kasir.md`](docs/cash-change-kasir.md) | Kasir cash tendered / change sheet |
| [`docs/shift-closing.md`](docs/shift-closing.md) | Shift + daily closing report |
| [`docs/delivery-cod-duitnow.md`](docs/delivery-cod-duitnow.md) | Delivery COD / DuitNow kasir flow |
| [`docs/packages-feature-map.md`](docs/packages-feature-map.md) | Mula / Standard / Pro feature map |
| [`docs/split-bill.md`](docs/split-bill.md) | Kasir split bill flow |
| [`docs/cafe-mode.md`](docs/cafe-mode.md) | Cafe / counter QR sessions |
| [`docs/sst-settings.md`](docs/sst-settings.md) | SST enable + rate behavior |
| [`docs/guest-token-privacy.md`](docs/guest-token-privacy.md) | Guest token isolation on orders |
| [`docs/self-pickup.md`](docs/self-pickup.md) | Self-pickup fulfillment mode |
| [`docs/sound-alerts.md`](docs/sound-alerts.md) | Dashboard sound modes |
| [`docs/e-receipt.md`](docs/e-receipt.md) | E-receipt / SMTP notes |
| [`docs/table-qr-print.md`](docs/table-qr-print.md) | Printing table QR kits |
| [`docs/staff-order.md`](docs/staff-order.md) | Staff key-in orders (Grab/phone) |
| [`docs/hours-open-close.md`](docs/hours-open-close.md) | Shop open/close hours |
| [`docs/i18n-keys.md`](docs/i18n-keys.md) | my/en language key pairs |
| [`docs/schema-cache.md`](docs/schema-cache.md) | Schema cache on shared hosting |
| [`docs/meta-ads.md`](docs/meta-ads.md) | WhatsApp Meta Ads playbook |
| [`docs/meta-ads-checklist.md`](docs/meta-ads-checklist.md) | Pre-publish Ads checklist |
| [`docs/meta-ads-day4-review.md`](docs/meta-ads-day4-review.md) | Day-4 Ads review decisions |
| [`docs/seo.md`](docs/seo.md) | Sitemap, robots, blog SEO |
| [`docs/marketing/`](docs/marketing/) | Flow/Veo ads, demo runbook, pricing & creative tests |
| [`tools/outreach/wa-balasan-demo.md`](tools/outreach/wa-balasan-demo.md) | WA reply scripts for ad leads |
| [`docs/print-bridge.md`](docs/print-bridge.md) | Wi-Fi ESC/POS bridge |
| [`docs/ai-agent/`](docs/ai-agent/) | ChatLM system prompt |
| [`SECURITY.md`](SECURITY.md) | Secrets & reporting |

## Client kits

See [`clients/README.md`](clients/README.md) for Big Tree Western, Port Santai, and how to print table QR packs.
