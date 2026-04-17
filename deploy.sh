#!/bin/bash

set -Eeuo pipefail

#############################################
# Dynamic E-commerce System - Production Deploy
# Hostinger-safe one-click deploy
#
# Usage:
#   ./deploy.sh
#   ./deploy.sh main
#############################################

APP_NAME="Tag Marketplace"
BRANCH="${1:-main}"

BASE_DIR="/home/u637857322/domains/tag-marketplace.com"
APP_DIR="$BASE_DIR/laravel_app"
PUBLIC_DIR="$BASE_DIR/public_html"
BACKUP_ROOT="$BASE_DIR/deploy_backups"
LOG_DIR="$BASE_DIR/deploy_logs"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
CURRENT_BACKUP_DIR="$BACKUP_ROOT/backup_$TIMESTAMP"
LOG_FILE="$LOG_DIR/deploy_$TIMESTAMP.log"
LATEST_BACKUP_FILE="$BACKUP_ROOT/latest_successful_backup.txt"
KEEP_BACKUPS="${KEEP_BACKUPS:-10}"

KEEP_ENV_FILE="$APP_DIR/.env"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-https://tag-marketplace.com}"
HEALTHCHECK_TIMEOUT="${HEALTHCHECK_TIMEOUT:-20}"

mkdir -p "$BACKUP_ROOT" "$LOG_DIR"
exec >> "$LOG_FILE" 2>&1

PREVIOUS_COMMIT="unknown"
NEW_COMMIT="unknown"
ROLLBACK_NEEDED="false"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"
}

fail() {
    log "❌ $*"
    exit 1
}

restore_env() {
    if [ -f "$CURRENT_BACKUP_DIR/meta/.env.backup" ]; then
        cp "$CURRENT_BACKUP_DIR/meta/.env.backup" "$KEEP_ENV_FILE"
    fi
}

rollback_from_current_backup() {
    log "↩️ Starting automatic rollback from current backup..."

    [ -d "$CURRENT_BACKUP_DIR" ] || fail "Automatic rollback failed: backup directory not found: $CURRENT_BACKUP_DIR"
    [ -f "$KEEP_ENV_FILE" ] || fail "Current .env not found: $KEEP_ENV_FILE"

    local env_tmp="/tmp/tag_marketplace_env_${TIMESTAMP}_$$.backup"
    cp "$KEEP_ENV_FILE" "$env_tmp"

    rsync -a --delete \
        --exclude='.env' \
        "$CURRENT_BACKUP_DIR/app/" "$APP_DIR/"

    cp "$env_tmp" "$KEEP_ENV_FILE"
    rm -f "$env_tmp"

    rsync -a --delete \
        --exclude='uploads' \
        "$CURRENT_BACKUP_DIR/public/" "$PUBLIC_DIR/"

    mkdir -p "$APP_DIR/storage/framework/cache/data"
    mkdir -p "$APP_DIR/storage/framework/sessions"
    mkdir -p "$APP_DIR/storage/framework/views"
    mkdir -p "$APP_DIR/storage/logs"
    mkdir -p "$APP_DIR/bootstrap/cache"
    chmod -R ug+rw "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" || true

    cd "$APP_DIR"
    $COMPOSER_BIN install --no-dev --prefer-dist --optimize-autoloader --no-interaction || true
    $PHP_BIN artisan optimize:clear || true
    $PHP_BIN artisan config:cache || true

    log "✅ Automatic rollback completed."
}

on_error() {
    local line_no="$1"
    local exit_code="$2"

    log "❌ Deploy failed at line $line_no with exit code $exit_code"

    if [ "$ROLLBACK_NEEDED" = "true" ]; then
        rollback_from_current_backup || true
    fi

    log "📝 Check deploy log: $LOG_FILE"
    exit "$exit_code"
}
trap 'on_error ${LINENO} $?' ERR

health_check() {
    log "🩺 Running health check: $HEALTHCHECK_URL"

    if command -v curl >/dev/null 2>&1; then
        local http_code
        http_code="$(curl -L -k -s -o /dev/null -w '%{http_code}' --max-time "$HEALTHCHECK_TIMEOUT" "$HEALTHCHECK_URL" || true)"

        if [ "$http_code" = "200" ] || [ "$http_code" = "301" ] || [ "$http_code" = "302" ]; then
            log "✅ Health check passed with HTTP $http_code"
            return 0
        fi

        fail "Health check failed. HTTP status: ${http_code:-unknown}"
    fi

    log "⚠️ curl is not installed. Skipping HTTP health check."
}

cleanup_old_backups() {
    log "🧹 Cleaning old backups, keeping latest $KEEP_BACKUPS"

    [ -d "$BACKUP_ROOT" ] || return 0

    local list_file="/tmp/tag_marketplace_backups_${TIMESTAMP}_$$.txt"
    ls -1dt "$BACKUP_ROOT"/backup_* 2>/dev/null > "$list_file" || true

    local count=0
    while IFS= read -r backup_path; do
        count=$((count + 1))

        if [ "$count" -le "$KEEP_BACKUPS" ]; then
            continue
        fi

        if [ -d "$backup_path" ]; then
            log "🗑 Removing old backup: $backup_path"
            rm -rf "$backup_path"
        fi
    done < "$list_file"

    rm -f "$list_file"
}

log "=================================================="
log "🚀 Starting deploy for: $APP_NAME"
log "📅 Timestamp: $TIMESTAMP"
log "🌿 Branch: $BRANCH"
log "📝 Log file: $LOG_FILE"
log "=================================================="

[ -d "$APP_DIR" ] || fail "APP_DIR not found: $APP_DIR"
[ -d "$PUBLIC_DIR" ] || fail "PUBLIC_DIR not found: $PUBLIC_DIR"
[ -f "$KEEP_ENV_FILE" ] || fail ".env file not found: $KEEP_ENV_FILE"

cd "$APP_DIR"
git rev-parse --is-inside-work-tree >/dev/null 2>&1 || fail "This is not a valid git repository: $APP_DIR"

PREVIOUS_COMMIT="$(git rev-parse --short HEAD || echo 'unknown')"

mkdir -p "$CURRENT_BACKUP_DIR/app" "$CURRENT_BACKUP_DIR/public" "$CURRENT_BACKUP_DIR/meta"

log "📦 Creating backup before deploy..."
rsync -a \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='storage/logs' \
    --exclude='storage/framework/cache/data' \
    --exclude='storage/framework/sessions' \
    --exclude='storage/framework/views' \
    "$APP_DIR/" "$CURRENT_BACKUP_DIR/app/"

rsync -a \
    --exclude='uploads' \
    "$PUBLIC_DIR/" "$CURRENT_BACKUP_DIR/public/"

cp "$KEEP_ENV_FILE" "$CURRENT_BACKUP_DIR/meta/.env.backup"
echo "$PREVIOUS_COMMIT" > "$CURRENT_BACKUP_DIR/meta/current_commit.txt"
touch "$CURRENT_BACKUP_DIR/meta/backup_completed.txt"

log "✅ Backup created at: $CURRENT_BACKUP_DIR"

ROLLBACK_NEEDED="true"

log "⬇️ Fetching latest code..."
git fetch origin

log "🔄 Resetting working tree to origin/$BRANCH ..."
git reset --hard "origin/$BRANCH"
NEW_COMMIT="$(git rev-parse --short HEAD || echo 'unknown')"

log "♻️ Restoring server .env ..."
restore_env

log "📦 Installing composer dependencies..."
$COMPOSER_BIN install --no-dev --prefer-dist --optimize-autoloader --no-interaction

log "🛠 Preparing Laravel writable directories..."
mkdir -p "$APP_DIR/storage/framework/cache/data"
mkdir -p "$APP_DIR/storage/framework/sessions"
mkdir -p "$APP_DIR/storage/framework/views"
mkdir -p "$APP_DIR/storage/logs"
mkdir -p "$APP_DIR/bootstrap/cache"
chmod -R ug+rw "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" || true

log "🧹 Clearing caches..."
$PHP_BIN artisan optimize:clear

log "🗄 Running migrations..."
$PHP_BIN artisan migrate --force

log "⚙️ Caching config..."
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache || true
$PHP_BIN artisan view:cache || true

log "📁 Syncing public assets to public_html ..."
rsync -a \
    --delete \
    --exclude='index.php' \
    --exclude='.htaccess' \
    --exclude='uploads' \
    --exclude='storage' \
    "$APP_DIR/public/" "$PUBLIC_DIR/"

health_check

echo "$CURRENT_BACKUP_DIR" > "$LATEST_BACKUP_FILE"
cleanup_old_backups

ROLLBACK_NEEDED="false"

log "=================================================="
log "✅ Deploy completed successfully"
log "🔖 Previous commit: $PREVIOUS_COMMIT"
log "🔖 Current  commit: $NEW_COMMIT"
log "💾 Backup saved at: $CURRENT_BACKUP_DIR"
log "📝 Deploy log: $LOG_FILE"
log "=================================================="
