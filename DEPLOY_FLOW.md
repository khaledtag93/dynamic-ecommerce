# Deploy Flow

## Source-control workflow

`main` is the canonical production branch.

Normal change flow:

```bash
git checkout main
git pull
git checkout -b feature/<short-name>
# make and test changes
git add .
git commit -m "describe the change"
# review/merge to main
```

Do not commit `.env`, passwords, private keys, or production secrets.

## Production server

- App: `/home/u637857322/domains/tag-marketplace.com/laravel_app`
- Public: `/home/u637857322/domains/tag-marketplace.com/public_html`
- Backups: `/home/u637857322/domains/tag-marketplace.com/deploy_backups`
- Logs: `/home/u637857322/domains/tag-marketplace.com/deploy_logs`

## Manual deploy fallback

```bash
cd /home/u637857322/domains/tag-marketplace.com/laravel_app
chmod +x deploy.sh rollback.sh
./deploy.sh main
```

`deploy.sh` creates a backup, resets the server working tree to `origin/main`, preserves the server `.env`, installs Composer dependencies, runs migrations, rebuilds caches, syncs public assets, performs a health check, and automatically restores application files from the pre-deploy backup if deployment fails.

## Manual rollback fallback

```bash
cd /home/u637857322/domains/tag-marketplace.com/laravel_app
./rollback.sh
# or
./rollback.sh backup_YYYYMMDD_HHMMSS
```

`rollback.sh` restores application/public files while preserving the current server `.env` and uploads.

> Database schema/data are not automatically reversed by the file rollback. Any release containing destructive or non-backward-compatible migrations requires an explicit database rollback strategy.

## Controlled GitHub operations

Operational workflows are maintained on the dedicated `ops-control` branch. They use GitHub Actions secrets rather than credentials committed to source.

Required secrets:
- `PROD_SSH_HOST`
- `PROD_SSH_PORT`
- `PROD_SSH_USER`
- `PROD_SSH_PRIVATE_KEY`

The target production deploy always deploys the current `main` branch.
