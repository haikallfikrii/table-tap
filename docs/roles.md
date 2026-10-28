# Staff roles

TableTap users belong to a shop (except `master`). After login, `roleHome()` sends each role to its dashboard.

| Role | Home | Typical job |
|------|------|-------------|
| `master` | `admin/master/` | Platform admin across shops |
| `owner` | `admin/owner/` | Menu, settings, history, shifts, reports |
| `kasir` | `admin/kasir.php` | Live orders, mark paid, staff order, print |
| `dapur` | `admin/dapur.php` | Hot kitchen ticket board |
| `minuman` | `admin/minuman.php` | Drinks / bar ticket board |
| `waiter` | `admin/waiter.php` | Floor runner / pickup |

## Stations

Pro shops can attach `dapur` / `minuman` users to a **station** (e.g. Western). Tickets route by `station_id_saat_order` on each line item. Kasir can also act as a print hub that prints every station’s slip.

## Permissions notes

- Cancelling orders is **owner-only** (API rejects kasir).
- Cash drawer / auto-print on paid are shop printer prefs set by the owner.
- Staff key-in orders (`sumber_order = staf`) skip the guest table burst limit so Grab/Foodpanda entry is not blocked.
