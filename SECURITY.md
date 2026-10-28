# Security notes

## Secrets

- `config/config.php` is **gitignored**. Only `config/config.example.php` is in the repo.
- `cron_secret` protects `cron/*` and diagnostic endpoints such as `admin/dbcheck.php`.
- Database passwords and SMTP keys live only on the server.

## Public attack surface

- Table access uses `meja` + long `token_akses` (not sequential guessable IDs alone).
- Customer order APIs are rate-limited per table / session (burst windows).
- Staff actions require a logged-in session and role checks (`requireLogin` / `requireLoginApi`).

## Uploads & PII

- Customer payment proofs and menu photos go under `storage/uploads/` (not wiped by git deploy).
- Do not commit `leads/` CSVs or outreach lists with phone numbers.
- Prefer curated dish images under `clients/*/reco/` instead of raw WhatsApp dumps.

## Reporting

If you find a vulnerability in TableTap, contact WhatsApp [+6011-2535 2270](https://wa.me/601125352270) privately — please do not open a public issue with exploit details.
