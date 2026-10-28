# Deploy

TableTap runs on Hostinger shared hosting. Git remotes map to site branches.

## Branches

1. Develop and commit on **`main`**.
2. Fast-forward **`production`** from `main` for [tabletap.my](https://tabletap.my).
3. Fast-forward **`staging`** the same way when needed.

Example (after commits are on `main`):

```bash
git push origin main
git checkout production && git merge --ff-only main && git push origin production
git checkout staging && git merge --ff-only main && git push origin staging
git checkout main
```

Then in hPanel → Git → **Pull / Deploy** for the site that tracks that branch (if auto-deploy is not already hooked up).

## After deploy

- Hard-refresh staff tablets (kasir / dapur) so `?v=filemtime` assets reload.
- Config stays only on the server (`config/config.php` is gitignored).
- Writable runtime dirs: `storage/uploads/`, `storage/cache/`, `storage/logs/`.

## Do not deploy

- `leads/` outreach CSVs (personal / phone data)
- Local-only print experiments under `clients/` unless intentionally publishing a kit
