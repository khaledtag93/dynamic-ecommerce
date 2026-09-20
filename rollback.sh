#!/bin/bash

set -Eeuo pipefail
umask 077

#############################################
# Dynamic E-commerce System - Rollback Script
# Restores code + public assets from backup
# Keeps current .env and uploads intact
# Database snapshots are preserved for explicit/manual recovery only
#
# Usage:
#   ./rollback.sh
#   ./rollback.sh backup_20260416_173000
#   ./rollback.sh /full/path/to/backup
#############################################

BASE_DIR="/home/u637857322/domains/tag-marketplace.com"
APP_DIR="$BASE_DIR/laravel_app"
PUBLIC_DIR="$BASE_DIR/public_html"
BACKUP_ROOT="$BASE_DIR/deploy_backups"
LOG_DIR="$BASE_DIR/deploy_logs"
LATEST_BACKUP_FILE="$BACKUP_ROOT/latest_successful_backup.txt"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
LOG_FILE="$LOG_DIR/rollback_$TIMESTAMP.log"

TARGET_BACKUP="${1:-}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-https://tag-marketplace.com}"
HEALTHCHECK_TIMEOUT="${HEALTHCHECK_TIMEOUT:-20}"

mkdir -p "$LOG_DIR"
exec >> "$LOG_FILE" 2>&1

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"
}

health_check() {
    log "🩺 Running rollback health check: $HEALTHCHECK_URL"

    command -v curl >/dev/null 2>&1 || {
        log "❌ curl is required for the rollback health check."
        return 1
    }

    local http_code
    http_code="$(curl -L -sS -o /dev/null -w '%{http_code}' --max-time "$HEALTHCHECK_TIMEOUT" "$HEALTHCHECK_URL" || true)"

    if [ "$http_code" = "200" ] || [ "$http_code" = "301" ] || [ "$http_code" = "302" ]; then
        log "✅ Rollback health check passed with HTTP $http_code"
        return 0
    fi

    log "❌ Rollback health check failed. HTTP status: ${http_code:-unknown}"
    return 1
}

if [ -z "$TARGET_BACKUP" ]; then
    if [ -f "$LATEST_BACKUP_FILE" ]; then
        TARGET_BACKUP="$(cat "$LATEST_BACKUP_FILE")"
    else
        log "❌ Usage: ./rollback.sh backup_name"
        log "📂 Available backups:"
        ls -1 "$BACKUP_ROOT" 2>/dev/null || true
        exit 1
    fi
fi

if [ -d "$TARGET_BACKUP" ]; then
    BACKUP_DIR="$TARGET_BACKUP"
else
    BACKUP_DIR="$BACKUP_ROOT/$TARGET_BACKUP"
fi

if [ ! -d "$BACKUP_DIR" ]; then
    log "❌ Backup not found: $BACKUP_DIR"
    log "📂 Available backups:"
    ls -1 "$BACKUP_ROOT" 2>/dev/null || true
    exit 1
fi

if [ ! -f "$APP_DIR/.env" ]; then
    log "❌ Current .env not found in $APP_DIR"
    exit 1
fi

CURRENT_ENV_TEMP="/tmp/tag_marketplace_env_$.backup"
cp "$APP_DIR/.env" "$CURRENT_ENV_TEMP"
chmod 600 "$CURRENT_ENV_TEMP"

log "=================================================="
log "↩️ Starting rollback"
log "📦 Backup: $BACKUP_DIR"
log "📝 Log file: $LOG_FILE"
log "=================================================="

log "🚧 Enabling Laravel maintenance mode..."
if [ -f "$APP_DIR/artisan" ]; then
    (
        cd "$APP_DIR"
        $PHP_BIN artisan down --retry=60 || true
    )
fi

log "📁 Restoring application files..."
rsync -a --delete \
    --exclude='.env' \
    "$BACKUP_DIR/app/" "$APP_DIR/"

cp "$CURRENT_ENV_TEMP" "$APP_DIR/.env"
rm -f "$CURRENT_ENV_TEMP"

log "🌐 Restoring public files..."
rsync -a --delete \
    --exclude='uploads' \
    "$BACKUP_DIR/public/" "$PUBLIC_DIR/"

cd "$APP_DIR"

log "🛠 Preparing Laravel writable directories..."
mkdir -p "$APP_DIR/storage/framework/cache/data"
mkdir -p "$APP_DIR/storage/framework/sessions"
mkdir -p "$APP_DIR/storage/framework/views"
mkdir -p "$APP_DIR/storage/logs"
mkdir -p "$APP_DIR/bootstrap/cache"
chmod -R ug+rw "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" || true

log "📦 Installing composer dependencies..."
$COMPOSER_BIN install --no-dev --prefer-dist --optimize-autoloader --no-interaction

log "🧹 Clearing caches..."
$PHP_BIN artisan optimize:clear || true

log "⚙️ Rebuilding config cache..."
$PHP_BIN artisan config:cache || true
$PHP_BIN artisan route:cache || true
$PHP_BIN artisan view:cache || true

log "🌐 Disabling Laravel maintenance mode..."
$PHP_BIN artisan up

if ! health_check; then
    log "⚠️ Rollback files were restored but the application failed its health check."
    $PHP_BIN artisan down --retry=60 || true
    exit 1
fi

log "=================================================="
log "✅ Rollback completed"
if [ -s "$BACKUP_DIR/database/database.sql" ]; then
    log "🗄 Database snapshot available: $BACKUP_DIR/database/database.sql"
fi
log "⚠️ Database schema/data are NOT automatically rolled back."
log "⚠️ Restore a database snapshot only as an explicit recovery action after validating data-loss impact."
log "📝 Rollback log: $LOG_FILE"
log "=================================================="
