# Phase 6.5 — Remote Deploy Center

This implementation is **remote-only**.

## How it works

- The admin Deploy Center page never runs shell scripts directly from the operator browser flow.
- The page calls a **protected remote executor endpoint** over HTTP.
- The executor validates a shared-secret HMAC signature and timestamp.
- Only after validation does the server run `deploy.sh` or `rollback.sh`.
- Logs and backup lists are also read from the remote server through the same protected channel.

## Required environment values

```env
ALLOW_DEPLOY_CENTER=true
DEPLOY_WORKSPACE_PATH=/home/u637857322/domains/tag-marketplace.com/laravel_app
DEPLOY_SCRIPT_PATH=/home/u637857322/domains/tag-marketplace.com/laravel_app/deploy.sh
ROLLBACK_SCRIPT_PATH=/home/u637857322/domains/tag-marketplace.com/laravel_app/rollback.sh
DEPLOY_LOGS_PATH=/home/u637857322/domains/tag-marketplace.com/deploy_logs
DEPLOY_BACKUPS_PATH=/home/u637857322/domains/tag-marketplace.com/deploy_backups
DEPLOY_REMOTE_BASE_URL=https://tag-marketplace.com
DEPLOY_REMOTE_SECRET=change-this-to-a-long-random-secret
```

## Remote executor endpoints

- `GET /internal/deploy-center/status`
- `POST /internal/deploy-center/execute`

Both endpoints require:

- `X-Deploy-Timestamp`
- `X-Deploy-Signature`

The signature is computed as:

`HMAC_SHA256(timestamp + "\n" + raw_request_body, DEPLOY_REMOTE_SECRET)`

## Notes

- Set the same `DEPLOY_REMOTE_SECRET` on the instance that shows the admin page and on the server that executes deploy commands.
- If the admin panel runs on the same production host, keep `DEPLOY_REMOTE_BASE_URL=${APP_URL}`.
- If you open the same project locally and want the buttons to control production, point `DEPLOY_REMOTE_BASE_URL` to the production site and use the same secret.
- If the remote executor is unreachable or the signature is invalid, Deploy and Rollback stay operator-safe and fail without executing anything.
