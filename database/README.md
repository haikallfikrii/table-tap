# Database

## Fresh install

Import **`schema.sql`** first (creates packages, shops, tables, menu, orders, stations, etc.).

On Hostinger: open phpMyAdmin → select the app database → Import.

## Incremental migrations

Run these only on older databases that were created before a feature shipped. New installs that used a recent `schema.sql` usually already include the columns.

| File | Adds |
|------|------|
| `migrate_takeaway.sql` | Takeaway / jenis hidang |
| `migrate_fulfillment.sql` | Waiter vs self-pickup |
| `migrate_waiter_sound.sql` | Dashboard sound settings |
| `migrate_guest_token.sql` | Guest order token |
| `migrate_cafe_mode.sql` | Cafe sessions + OTP |
| `migrate_delivery.sql` | Delivery + payment proof fields |
| `migrate_stations.sql` | Kitchen stations |
| `migrate_menu_categories.sql` | Customer-facing menu categories |
| `migrate_menu_addons.sql` | Menu add-ons |
| `migrate_menu_gallery.sql` | Extra dish photos |
| `migrate_split_bill.sql` | Split bill |
| `migrate_shift.sql` | Cash shift open/close |
| `migrate_order_limits.sql` | Burst / cart limits per shop |
| `migrate_printer_settings.sql` | Kasir print-on-paid + beeps |
| `migrate_print_bridge.sql` | Wi-Fi print bridge queue |

`set_western_to_minuman.sql` is a one-off data fix for a demo shop — not a general migration.

## Runtime patches

`includes/schema_patch.php` also applies missing columns/tables idempotently on first DB connect. Prefer checked-in SQL for deliberate upgrades; the patch is a safety net for shared hosting.
