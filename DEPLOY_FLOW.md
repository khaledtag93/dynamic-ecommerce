# Deploy Flow

## Local workflow

```bash
npm run build
git add .
git commit -m "your message"
git push origin main
```

## Server manual deploy

```bash
cd /home/u637857322/domains/tag-marketplace.com/laravel_app
chmod +x deploy.sh rollback.sh
./deploy.sh
```

## Server rollback

```bash
cd /home/u637857322/domains/tag-marketplace.com/laravel_app
./rollback.sh
# or
./rollback.sh backup_YYYYMMDD_HHMMSS
```

## Important directories

- App: `/home/u637857322/domains/tag-marketplace.com/laravel_app`
- Public: `/home/u637857322/domains/tag-marketplace.com/public_html`
- Backups: `/home/u637857322/domains/tag-marketplace.com/deploy_backups`
- Logs: `/home/u637857322/domains/tag-marketplace.com/deploy_logs`

## Notes

- `.env` is preserved from the server.
- `public_html/uploads` is preserved.
- Health check defaults to `https://tag-marketplace.com`.
- The latest successful backup path is stored in `deploy_backups/latest_successful_backup.txt`.
