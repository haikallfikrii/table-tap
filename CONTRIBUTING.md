# Contributing

This repo is the TableTap product codebase. Prefer small, reviewable commits that do one thing.

## Workflow

1. Work on `main`.
2. Keep commits meaningful (feature / fix / docs) — avoid empty “contribution farming” commits.
3. Push `main`, then fast-forward `production` / `staging` when the change should go live (see `docs/deploy.md`).

## Before you commit

- Do not add `config/config.php`, `.env`, or real `cron_secret` values.
- Do not add `leads/` phone lists or WhatsApp image dumps.
- Run a quick sanity check on touched PHP (`php -l path/to/file.php`) when practical.

## Code style

- PHP: `declare(strict_types=1);`, shared helpers in `includes/`.
- Customer/staff strings: add keys in both `includes/lang/my.php` and `en.php`.
- Schema changes: prefer a `database/migrate_*.sql` plus a matching patch in `includes/schema_patch.php` when older live DBs must self-heal.

## Questions

WhatsApp: https://wa.me/601125352270
