#!/bin/bash

set -Eeuo pipefail
umask 077

#############################################
# Dynamic E-commerce System - QAS Deploy
# Safe one-command deployment for v42.tag-marketplace.com
#
# Usage:
#   ./deploy-qas.sh
#   ./deploy-qas.sh v42-clean-baseline
#############################################

APP_NAME="Tag Marketplace V42 QAS"
BRANCH="${1:-v42-clean-baseline}"

BASE_DIR="/home/u637857322/domains/tag-marketplace.com"
APP_DIR="$BASE_DIR/laravel_app_v42_rehearsal"
PUBLIC_DIR="$BASE_DIR/public_html/v42"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-https://v42.tag-marketplace.com}"
HEALTHCHECK_TIMEOUT="${HEALTHCHECK_TIMEOUT:-20}"
EXPECTED_DB="u637857322_v42_rehearsal"

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

log() {
    printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

fail() {
    log "❌ $*"
    exit 1
}

bring_up() {
    if [ -f "$APP_DIR/artisan" ]; then
        (
            cd "$APP_DIR"
            $PHP_BIN artisan up >/dev/null 2>&1 || true
        )
    fi
}

on_error() {
    local line_no="$1"
    local exit_code="$2"
    log "❌ QAS deploy failed at line $line_no with exit code $exit_code"
    bring_up
    exit "$exit_code"
}
trap 'on_error ${LINENO} $?' ERR

health_check() {
    local code
    code="$(curl -L -sS -o /dev/null -w '%{http_code}' --max-time "$HEALTHCHECK_TIMEOUT" "$HEALTHCHECK_URL" || true)"

    case "$code" in
        200|301|302)
            log "✅ QAS health check passed: HTTP $code"
            ;;
        *)
            fail "QAS health check failed: HTTP ${code:-unknown}"
            ;;
    esac
}

log "=================================================="
log "🚀 Starting QAS deploy"
log "🌿 Branch: $BRANCH"
log "🌐 URL: $HEALTHCHECK_URL"
log "=================================================="

[ -d "$APP_DIR" ] || fail "QAS app directory missing: $APP_DIR"
[ -d "$PUBLIC_DIR" ] || fail "QAS public directory missing: $PUBLIC_DIR"
[ -f "$APP_DIR/.env" ] || fail "QAS .env missing"
[ -f "$APP_DIR/artisan" ] || fail "QAS artisan missing"
[ -f "$APP_DIR/composer.json" ] || fail "QAS composer.json missing"

cd "$APP_DIR"

git rev-parse --is-inside-work-tree >/dev/null 2>&1 || fail "QAS app is not a Git worktree"
git remote get-url origin >/dev/null 2>&1 || fail "QAS Git origin is not configured"

grep -Eq '^APP_ENV=staging$' .env || fail "QAS safety check failed: APP_ENV must be staging"
grep -Eq '^APP_DEBUG=false$' .env || fail "QAS safety check failed: APP_DEBUG must be false"
grep -Eq "^DB_DATABASE=$EXPECTED_DB$" .env || fail "QAS safety check failed: unexpected DB_DATABASE"

chmod 600 .env

log "🖼 Configuring isolated QAS media root..."
if grep -q '^PUBLIC_ROOT_PATH=' .env; then
    sed -i "s|^PUBLIC_ROOT_PATH=.*|PUBLIC_ROOT_PATH=$PUBLIC_DIR|" .env
else
    printf '\nPUBLIC_ROOT_PATH=%s\n' "$PUBLIC_DIR" >> .env
fi
mkdir -p "$PUBLIC_DIR/uploads"
chmod 600 .env

log "⬇️ Fetching latest QAS code..."
git fetch --prune origin "+refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"

git rev-parse "origin/$BRANCH" >/dev/null 2>&1 || fail "Remote branch not found: origin/$BRANCH"

PREVIOUS_COMMIT="$(git rev-parse --short HEAD)"
TARGET_COMMIT="$(git rev-parse --short "origin/$BRANCH")"

log "🔖 Previous commit: $PREVIOUS_COMMIT"
log "🎯 Target commit:   $TARGET_COMMIT"

log "🚧 Enabling QAS maintenance mode..."
$PHP_BIN artisan down --retry=30 || true

log "🔄 Updating QAS worktree..."
git reset --hard "origin/$BRANCH"

log "🧪 Running source preflight checks..."
if grep -RInE --include='*.blade.php' '(AppModels|IlluminateSupport)[A-Za-z0-9_:]*' resources/views; then
    fail "Blade namespace preflight failed. Fix corrupted PHP class references before deploying."
fi

log "📦 Installing PHP dependencies..."
$COMPOSER_BIN install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress

log "🧭 Verifying Workforce routes..."
$PHP_BIN artisan route:list --name=workforce >/dev/null

log "🧹 Clearing Laravel caches..."
$PHP_BIN artisan optimize:clear

log "🔐 Verifying QAS database connection..."
ACTUAL_DB="$($PHP_BIN artisan tinker --execute='echo DB::selectOne("SELECT DATABASE() AS db")->db;' 2>/dev/null)"
[ "$ACTUAL_DB" = "$EXPECTED_DB" ] || fail "Connected database is '$ACTUAL_DB', expected '$EXPECTED_DB'"

log "🗄 Applying pending QAS migrations..."
$PHP_BIN artisan migrate --force

log "⚙️ Rebuilding QAS caches..."
$PHP_BIN artisan config:cache
$PHP_BIN artisan view:cache

log "♻️ Signaling QAS queue workers to reload application code..."
$PHP_BIN artisan queue:restart || true

log "📁 Syncing QAS public files..."
rsync -a --delete     --exclude='index.php'     --exclude='uploads'     --exclude='storage'     "$APP_DIR/public/" "$PUBLIC_DIR/"

UPLOAD_GUARD_SOURCE="$APP_DIR/ops/uploads.htaccess"
[ -f "$UPLOAD_GUARD_SOURCE" ] || fail "Upload execution guard missing: $UPLOAD_GUARD_SOURCE"
mkdir -p "$PUBLIC_DIR/uploads"
cp "$UPLOAD_GUARD_SOURCE" "$PUBLIC_DIR/uploads/.htaccess"
chmod 644 "$PUBLIC_DIR/uploads/.htaccess"

log "🔗 Rebuilding QAS front controller..."
INDEX_TMP="$PUBLIC_DIR/.index.php.$$.tmp"
cp "$APP_DIR/public/index.php" "$INDEX_TMP"

sed -i     -e "s|__DIR__.'/../storage/framework/maintenance.php'|__DIR__.'/../../laravel_app_v42_rehearsal/storage/framework/maintenance.php'|"     -e "s|__DIR__.'/../vendor/autoload.php'|__DIR__.'/../../laravel_app_v42_rehearsal/vendor/autoload.php'|"     -e "s|__DIR__.'/../bootstrap/app.php'|__DIR__.'/../../laravel_app_v42_rehearsal/bootstrap/app.php'|"     "$INDEX_TMP"

mv "$INDEX_TMP" "$PUBLIC_DIR/index.php"
chmod 644 "$PUBLIC_DIR/index.php"

log "🌐 Disabling QAS maintenance mode..."
$PHP_BIN artisan up

health_check

CURRENT_COMMIT="$(git rev-parse --short HEAD)"

log "=================================================="
log "✅ QAS READY"
log "🔖 Deployed commit: $CURRENT_COMMIT"
log "🌐 Test now: $HEALTHCHECK_URL"
log "=================================================="
