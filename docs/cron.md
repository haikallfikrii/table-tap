# Cron & one-shot admin scripts

All scripts under `cron/` require `?key=` matching `cron_secret` in `config/config.php`.

Prefer calling the **live domain** you intend to affect (`tabletap.my` for production).

## Scheduled

| Script | When | What |
|--------|------|------|
| `purge_history.php` | Daily | Deletes old paid orders past each shop’s package retention |

Example Hostinger cron:

```text
curl -s "https://tabletap.my/cron/purge_history.php?key=YOUR_CRON_SECRET"
```

## One-shot / rare

| Script | What |
|--------|------|
| `seed_big_tree_western.php` | Refresh Big Tree menu, stations, staff, tables (idempotent upsert) |
| `set_category_station.php` | Bulk-assign a menu category to a kitchen station |
| `fix_timezone_once.php` | One-time +8h shift of order timestamps (marker in `storage/`) |

`set_category_station.php` params: `shop`, `category`, `station` (slug / username / id and kod fragments).

## Safety

- Never commit the real `cron_secret`.
- Seed scripts can change live menu photos and staff passwords for demo accounts — run only when intended.
- `admin/dbcheck.php` is separate (also secret-gated) for connectivity / diagnostics, not a cron job.
