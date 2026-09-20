#!/bin/bash
set -Eeuo pipefail
umask 077

BASE_DIR="/home/u637857322/domains/tag-marketplace.com"
APP_DIR="$BASE_DIR/laravel_app"
PUBLIC_DIR="$BASE_DIR/public_html"
BACKUP_ROOT="$BASE_DIR/deploy_backups"
EXPECTED_DB="u637857322_tagmarketplace"
HEALTHCHECK_URL="https://tag-marketplace.com"

BACKUP_ID="${1:-}"
MODE="${2:-}"
DB_MODE="${3:-}"

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

log() {
    printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

fail() {
    log "❌ $*"
    exit 1
}

[ -n "$BACKUP_ID" ] || fail "Usage: ./rollback-prod.sh <backup-id> --dry-run|--execute [--with-db]"
[ "$MODE" = "--dry-run" ] || [ "$MODE" = "--execute" ] || fail "Second argument must be --dry-run or --execute"
[ -z "$DB_MODE" ] || [ "$DB_MODE" = "--with-db" ] || fail "Third argument may only be --with-db"

BACKUP_DIR="$BACKUP_ROOT/$BACKUP_ID"

[ -d "$BACKUP_DIR/app" ] || fail "Backup app directory not found: $BACKUP_DIR/app"
[ -d "$BACKUP_DIR/public" ] || fail "Backup public directory not found: $BACKUP_DIR/public"
[ -f "$APP_DIR/.env" ] || fail "Production .env not found"
[ -f "$APP_DIR/artisan" ] || fail "Production artisan not found"

cd "$APP_DIR"

APP_ENV_VALUE="$(grep -m1 '^APP_ENV=' .env | cut -d= -f2- | tr -d '\r' | tr -d '"')"
APP_DEBUG_VALUE="$(grep -m1 '^APP_DEBUG=' .env | cut -d= -f2- | tr -d '\r' | tr -d '"')"

[ "$APP_ENV_VALUE" = "production" ] || fail "APP_ENV is not production"
[ "$APP_DEBUG_VALUE" = "false" ] || fail "APP_DEBUG is not false"

ACTUAL_DB="$($PHP_BIN artisan tinker --execute='echo DB::selectOne("SELECT DATABASE() AS db")->db;' 2>/dev/null)"
[ "$ACTUAL_DB" = "$EXPECTED_DB" ] || fail "Connected DB mismatch: $ACTUAL_DB"

log "=================================================="
log "Dynamic V42 Production Rollback"
log "Backup: $BACKUP_ID"
log "Mode: $MODE"
log "Database restore: $([ "$DB_MODE" = "--with-db" ] && echo YES || echo NO)"
log "=================================================="

if [ "$MODE" = "--dry-run" ]; then
    log "APP rollback dry-run:"
    rsync -ani --delete \
        --exclude='.env' \
        --exclude='.env.backup_*' \
        --exclude='storage' \
        "$BACKUP_DIR/app/" "$APP_DIR/"

    log "PUBLIC rollback dry-run:"
    rsync -ani --delete \
        --exclude='uploads' \
        --exclude='storage' \
        --exclude='v42' \
        "$BACKUP_DIR/public/" "$PUBLIC_DIR/"

    if [ "$DB_MODE" = "--with-db" ]; then
        [ -s "$BACKUP_DIR/database.sql" ] || fail "database.sql missing or empty"
        log "DB snapshot available: $(du -h "$BACKUP_DIR/database.sql" | awk '{print $1}')"
        log "DB SHA256: $(sha256sum "$BACKUP_DIR/database.sql" | awk '{print $1}')"
    fi

    log "✅ DRY-RUN complete. Production has NOT been modified."
    exit 0
fi

if [ "$DB_MODE" = "--with-db" ]; then
    [ -s "$BACKUP_DIR/database.sql" ] || fail "database.sql missing or empty"

    printf 'Type RESTORE_DB to confirm database restoration: '
    read -r CONFIRM_DB
    [ "$CONFIRM_DB" = "RESTORE_DB" ] || fail "Database restore cancelled"
fi

MAINT_SECRET="$($PHP_BIN -r 'echo bin2hex(random_bytes(24));')"

log "Putting Production into maintenance mode..."
$PHP_BIN artisan down --secret="$MAINT_SECRET" --retry=60

log "Restoring application files..."
rsync -a --delete \
    --exclude='.env' \
    --exclude='.env.backup_*' \
    --exclude='storage' \
    "$BACKUP_DIR/app/" "$APP_DIR/"

log "Restoring public files..."
rsync -a --delete \
    --exclude='uploads' \
    --exclude='storage' \
    --exclude='v42' \
    "$BACKUP_DIR/public/" "$PUBLIC_DIR/"

cd "$APP_DIR"

if [ "$DB_MODE" = "--with-db" ]; then
    DB_HOST_B64="$($PHP_BIN artisan tinker --execute='echo base64_encode(config("database.connections.mysql.host"));' 2>/dev/null)"
    DB_USER_B64="$($PHP_BIN artisan tinker --execute='echo base64_encode(config("database.connections.mysql.username"));' 2>/dev/null)"
    DB_PASS_B64="$($PHP_BIN artisan tinker --execute='echo base64_encode(config("database.connections.mysql.password"));' 2>/dev/null)"

    DB_HOST="$(printf '%s' "$DB_HOST_B64" | base64 -d)"
    DB_USER="$(printf '%s' "$DB_USER_B64" | base64 -d)"
    DB_PASS="$(printf '%s' "$DB_PASS_B64" | base64 -d)"

    EMERGENCY_SNAPSHOT="$BACKUP_DIR/pre-db-restore-$(date '+%Y%m%d_%H%M%S').sql"

    log "Creating emergency DB snapshot before restore..."
    MYSQL_PWD="$DB_PASS" mysqldump \
        --single-transaction \
        --quick \
        --skip-lock-tables \
        --no-tablespaces \
        --default-character-set=utf8mb4 \
        --host="$DB_HOST" \
        --user="$DB_USER" \
        --result-file="$EMERGENCY_SNAPSHOT" \
        "$EXPECTED_DB"

    chmod 600 "$EMERGENCY_SNAPSHOT"

    log "Restoring database snapshot..."
    MYSQL_PWD="$DB_PASS" mysql \
        --host="$DB_HOST" \
        --user="$DB_USER" \
        "$EXPECTED_DB" < "$BACKUP_DIR/database.sql"

    unset DB_PASS DB_PASS_B64
fi

log "Refreshing Laravel caches..."
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan config:cache
$PHP_BIN artisan view:cache

log "Leaving maintenance mode..."
$PHP_BIN artisan up

HTTP_CODE="$(curl -L -sS -o /dev/null -w '%{http_code}' --max-time 20 "$HEALTHCHECK_URL" || true)"

case "$HTTP_CODE" in
    200|301|302)
        log "✅ Health check passed: HTTP $HTTP_CODE"
        ;;
    *)
        fail "Health check failed after rollback: HTTP $HTTP_CODE"
        ;;
esac

log "=================================================="
log "✅ ROLLBACK COMPLETE"
log "Backup restored: $BACKUP_ID"
log "Database restored: $([ "$DB_MODE" = "--with-db" ] && echo YES || echo NO)"
log "=================================================="
