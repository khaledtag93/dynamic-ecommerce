# Production Operations

This branch is reserved for operational triggers only.

## Deploy
Changing `.ops/deploy-trigger.txt` triggers the Production Deploy workflow.
The server runs:

```bash
cd /home/u637857322/domains/tag-marketplace.com/laravel_app
./deploy.sh main
```

The deploy script creates a backup, pulls `origin/main`, installs production Composer dependencies,
runs migrations, rebuilds caches, syncs public assets, performs a health check, and automatically
rolls back application files if deployment fails.

## Rollback
Changing `.ops/rollback-trigger.txt` triggers the Production Rollback workflow.
The server runs `./rollback.sh`, which restores the latest successful application/public backup.

> Important: rollback.sh does not automatically reverse database migrations or production data changes.

## Required GitHub Actions secrets
- `PROD_SSH_HOST`
- `PROD_SSH_PORT`
- `PROD_SSH_USER`
- `PROD_SSH_PRIVATE_KEY`

Do not store passwords, private keys, .env values, or production credentials in repository files.
