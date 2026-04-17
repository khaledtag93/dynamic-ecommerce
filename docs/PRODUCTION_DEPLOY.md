# Production Deploy Guide (V2)

This project uses a safe one-click deploy flow for Hostinger.

## Server Structure

```text
/home/u637857322/domains/tag-marketplace.com/laravel_app
/home/u637857322/domains/tag-marketplace.com/public_html
```

## What `deploy.sh` V2 does

- Creates a backup before every deploy
- Fetches the latest code from GitHub
- Restores the server `.env` after `git reset --hard`
- Runs `composer install`
- Runs `php artisan migrate --force`
- Clears Laravel caches and rebuilds config cache
- Syncs `laravel_app/public` to `public_html`
- Keeps `public_html/uploads` untouched
- Keeps `public_html/index.php` untouched
- Keeps `public_html/.htaccess` untouched
- Runs an HTTP health check after deploy
- Automatically rolls back code/public files if deploy fails after backup
- Writes a deploy log file
- Cleans old backups automatically and keeps the latest 5 by default

## Important Rules

- Build frontend assets locally, not on the server
- Do not run `npm install` or `npm run build` on production
- Always push to GitHub before running deploy on the server
- Keep `.env` only on the server during active development
- Rollback restores code and public files only, not the database state

## Standard Deploy Steps

### 1) On your local machine

```bash
npm run build
git add .
git commit -m "your update message"
git push origin main
```

> `npm install` is only needed when dependencies changed or the local machine does not have them yet.

### 2) On the server

```bash
cd /home/u637857322/domains/tag-marketplace.com/laravel_app
chmod +x deploy.sh rollback.sh
./deploy.sh
```

## Health Check

By default, deploy uses:

```bash
https://tag-marketplace.com
```

You can temporarily override it for one deploy:

```bash
HEALTHCHECK_URL="https://tag-marketplace.com" ./deploy.sh
```

## Backup Location

```bash
/home/u637857322/domains/tag-marketplace.com/deploy_backups
```

## Log Location

```bash
/home/u637857322/domains/tag-marketplace.com/deploy_logs
```

## Manual Rollback

### Show backups

```bash
ls -1 /home/u637857322/domains/tag-marketplace.com/deploy_backups
```

### Roll back to a specific backup

```bash
cd /home/u637857322/domains/tag-marketplace.com/laravel_app
./rollback.sh 20260406_193200
```

### Roll back to the last successful backup automatically

```bash
cd /home/u637857322/domains/tag-marketplace.com/laravel_app
./rollback.sh
```

## Notes

- If `deploy.sh` fails after backup creation, it attempts automatic rollback.
- If `curl` is not available on the server, the HTTP health check is skipped.
- The backup retention count can be changed with an environment variable:

```bash
KEEP_BACKUPS=7 ./deploy.sh
```
