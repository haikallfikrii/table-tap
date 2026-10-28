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

## Client kits

See [`clients/README.md`](clients/README.md) for Big Tree Western, Port Santai, and how to print table QR packs.
