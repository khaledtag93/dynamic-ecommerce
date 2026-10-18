#!/bin/bash

set -Eeuo pipefail
umask 077

#############################################
# Dynamic E-commerce System - QAS Deploy
# Safe one-command deployment for v42.tag-marketplace.com
#
# Source-only hardening; QAS execution requires a separate approval.
# Usage (only after separately authorized operational preflight):
#   ./deploy-qas.sh sec03-framework-upgrade <exact-reviewed-40-char-SHA>
# No default branch or unpinned release.
#############################################

APP_NAME="Tag Marketplace V42 QAS"
BRANCH="${1:-}"
TARGET_SHA="${2:-}"
EXPECTED_PREVIOUS_SHA="16c2fdb612926db19214a051dabc395770cf0aa1"

BASE_DIR="/home/u637857322/domains/tag-marketplace.com"
APP_DIR="$BASE_DIR/laravel_app_v42_rehearsal"
PUBLIC_DIR="$BASE_DIR/public_html/v42"
HEALTHCHECK_URL="https://v42.tag-marketplace.com"
HEALTHCHECK_TIMEOUT="${HEALTHCHECK_TIMEOUT:-20}"
EXPECTED_DB="u637857322_v42_rehearsal"

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
# Early failures must not change QAS maintenance mode.
MAINTENANCE_ENTERED=false

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
    if [ "$MAINTENANCE_ENTERED" = true ]; then
        bring_up
    fi
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
log "🎯 Required SHA: $TARGET_SHA"
log "🌐 URL: $HEALTHCHECK_URL"
log "=================================================="

[ "$#" -eq 2 ] || fail "BLOCKED: explicit QAS branch and reviewed SHA are mandatory"
[ "$BRANCH" = "sec03-framework-upgrade" ] || fail "BLOCKED: wrong QAS source branch"
[[ "$TARGET_SHA" =~ ^[0-9a-f]{40}$ ]] || fail "BLOCKED: exact full lowercase SHA is required"

[ -d "$APP_DIR" ] || fail "QAS app directory missing: $APP_DIR"
[ -d "$PUBLIC_DIR" ] || fail "QAS public directory missing: $PUBLIC_DIR"
[ -f "$APP_DIR/.env" ] || fail "QAS .env missing"
[ -f "$APP_DIR/artisan" ] || fail "QAS artisan missing"
[ -f "$APP_DIR/composer.json" ] || fail "QAS composer.json missing"

[ "$(realpath -e "$APP_DIR")" = "$APP_DIR" ] || fail "BLOCKED: redirected QAS app path"
[ "$(realpath -e "$PUBLIC_DIR")" = "$PUBLIC_DIR" ] || fail "BLOCKED: redirected QAS public path"

cd "$APP_DIR"

git rev-parse --is-inside-work-tree >/dev/null 2>&1 || fail "QAS app is not a Git worktree"
git remote get-url origin >/dev/null 2>&1 || fail "QAS Git origin is not configured"
[ "$(git rev-parse --show-toplevel)" = "$APP_DIR" ] || fail "BLOCKED: wrong Git worktree"

grep -Eq '^APP_ENV=staging$' .env || fail "QAS safety check failed: APP_ENV must be staging"
grep -Eq '^APP_DEBUG=false$' .env || fail "QAS safety check failed: APP_DEBUG must be false"
grep -Eq "^DB_DATABASE=$EXPECTED_DB$" .env || fail "QAS safety check failed: unexpected DB_DATABASE"


# Existing runtime-side read-only probe, not to be run via any blocked
# tool route. Future QAS execution requires separate official approval.
# No chmod/sed/mkdir/fetch/reset/maintenance/DB writes precede this check.
ACTUAL_RUNTIME_IDENTITY="$("$PHP_BIN" artisan tinker --execute='echo app()->environment()."|".DB::selectOne("SELECT DATABASE() AS db")->db;' 2>/dev/null)" || fail "BLOCKED: authorized Laravel runtime identity unavailable"
[ "$ACTUAL_RUNTIME_IDENTITY" = "staging|$EXPECTED_DB" ] || fail "BLOCKED: Laravel runtime environment/database mismatch"

PREVIOUS_COMMIT="$(git rev-parse HEAD)"
[ "$PREVIOUS_COMMIT" = "$EXPECTED_PREVIOUS_SHA" ] || fail "BLOCKED: unexpected QAS pre-release SHA"
git diff --quiet || fail "BLOCKED: QAS worktree has tracked modifications"
git diff --cached --quiet || fail "BLOCKED: QAS worktree has staged changes"

# An unchanged migration source tree is not proof that QAS has none pending.
# This is an authorized-read-only future gate, never a migration execution.
MIGRATION_STATUS="$("$PHP_BIN" artisan migrate:status --no-ansi 2>/dev/null)" || fail "BLOCKED: migration status unavailable"
[ -n "$MIGRATION_STATUS" ] || fail "BLOCKED: empty migration status"
if printf '%s\n' "$MIGRATION_STATUS" | grep -Eiq 'Pending|Not Run'; then
    fail "Pending migrations require separate approval"
fi
printf '%s\n' "$MIGRATION_STATUS" | grep -Eq 'Ran|Yes' || fail "BLOCKED: migration state unverified"

# Protect already-existing QAS-only runtime data, cron, guards and symlinks.
[ -f "$APP_DIR/scripts/qas-queue-cron.sh" ] || fail "BLOCKED: missing QAS queue cron"
[ -f "$APP_DIR/scripts/qas-scheduler-cron.sh" ] || fail "BLOCKED: missing QAS scheduler cron"
[ -d "$APP_DIR/storage/app" ] || fail "BLOCKED: missing QAS app storage"
[ -d "$PUBLIC_DIR/uploads" ] || fail "BLOCKED: missing QAS uploads"
[ -f "$PUBLIC_DIR/uploads/.htaccess" ] || fail "BLOCKED: missing QAS uploads guard"
[ -e "$PUBLIC_DIR/storage" ] || [ -L "$PUBLIC_DIR/storage" ] || fail "BLOCKED: missing QAS public storage"
[ -f "$PUBLIC_DIR/index.php" ] || fail "BLOCKED: missing QAS front controller"

# Fail closed if user-data directories or cron scripts redirect to Production.
[ "$(realpath -e "$PUBLIC_DIR/uploads")" = "$PUBLIC_DIR/uploads" ] || fail "BLOCKED: uploads path redirects outside QAS"
[ "$(realpath -e "$APP_DIR/storage/app")" = "$APP_DIR/storage/app" ] || fail "BLOCKED: application storage redirects outside QAS"
for cron_path in "$APP_DIR/scripts/qas-queue-cron.sh" "$APP_DIR/scripts/qas-scheduler-cron.sh"; do
    [ "$(realpath -e "$cron_path")" = "$cron_path" ] || fail "BLOCKED: QAS cron redirects elsewhere"
done
PUBLIC_STORAGE_TARGET="$(realpath -e "$PUBLIC_DIR/storage")" || fail "BLOCKED: storage target missing"
case "$PUBLIC_STORAGE_TARGET" in
    "$PUBLIC_DIR/storage"|"$APP_DIR/storage/"*) ;;
    *) fail "BLOCKED: public storage points outside QAS" ;;
esac

# QAS-only owner waiver: do not create, require, delete or overwrite release
# backups. Existing snapshots, if any, are untouched. No rollback guarantee.
# Runtime uploads, storage, crons and upload execution guard remain protected.

log "⬇️ Fetching only the approved QAS source branch..."
git fetch --prune origin "+refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"
RESOLVED_TARGET_SHA="$(git rev-parse "origin/$BRANCH")" || fail "BLOCKED: target Git reference missing"
[ "$RESOLVED_TARGET_SHA" = "$TARGET_SHA" ] || fail "BLOCKED: branch tip does not match exact reviewed SHA"

# Untracked runtime-owned paths must never become tracked by the release.
for protected_path in scripts/qas-queue-cron.sh scripts/qas-scheduler-cron.sh storage/app public/uploads; do
    if [ -n "$(git ls-tree -r --name-only "$TARGET_SHA" -- "$protected_path")" ]; then
        fail "BLOCKED: target Git tree collides with QAS runtime data"
    fi
done

# BEGIN_QAS_PUBLIC_STORAGE_TREE_GUARD
# public/storage already contains a Git-tracked product image in the
# approved baseline. Allow it ONLY when the whole tracked tree is identical
# in the currently checked-out baseline and the exact target release.
# Reject missing paths, type changes, additions, removals and content changes.
BASELINE_PUBLIC_STORAGE_TREE="$(git rev-parse --verify "$PREVIOUS_COMMIT:public/storage" 2>/dev/null)" || fail "BLOCKED: baseline public/storage Git tree missing"
TARGET_PUBLIC_STORAGE_TREE="$(git rev-parse --verify "$TARGET_SHA:public/storage" 2>/dev/null)" || fail "BLOCKED: target public/storage Git tree missing"
[ "$(git cat-file -t "$BASELINE_PUBLIC_STORAGE_TREE" 2>/dev/null)" = tree ] || fail "BLOCKED: baseline public/storage is not a tree"
[ "$(git cat-file -t "$TARGET_PUBLIC_STORAGE_TREE" 2>/dev/null)" = tree ] || fail "BLOCKED: target public/storage is not a tree"
[ "$BASELINE_PUBLIC_STORAGE_TREE" = "$TARGET_PUBLIC_STORAGE_TREE" ] || fail "BLOCKED: tracked public/storage Git tree changed"
# END_QAS_PUBLIC_STORAGE_TREE_GUARD

# Never silently change the upload execution guard in this release.
git diff --quiet "$PREVIOUS_COMMIT" "$TARGET_SHA" -- ops/uploads.htaccess || fail "BLOCKED: upload guard source changed"

log "🔖 Verified previous commit: $PREVIOUS_COMMIT"
log "🎯 Verified target commit: $TARGET_SHA"

log "🚧 Enabling QAS maintenance mode..."
MAINTENANCE_ENTERED=true
"$PHP_BIN" artisan down --retry=30

log "🔄 Updating QAS worktree..."
git reset --hard "$TARGET_SHA"
[ "$(git rev-parse HEAD)" = "$TARGET_SHA" ] || fail "BLOCKED: wrong SHA after reset"

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

# No automatic migration or database change in QAS deploy.
log "✅ Schema is not modified by this script"

log "⚙️ Rebuilding QAS caches..."
$PHP_BIN artisan config:cache
$PHP_BIN artisan view:cache

# Queue restart may dispatch external work. Requires separate operator approval.

log "📁 Syncing QAS public files..."
rsync -a --exclude='index.php' --exclude='uploads' --exclude='storage' "$APP_DIR/public/" "$PUBLIC_DIR/"

log "🔐 Normalizing QAS public web permissions..."
find "$PUBLIC_DIR" \( -path "$PUBLIC_DIR/uploads" -o -path "$PUBLIC_DIR/storage" \) -prune -o -type d -exec chmod 755 {} +
find "$PUBLIC_DIR" \( -path "$PUBLIC_DIR/uploads" -o -path "$PUBLIC_DIR/storage" \) -prune -o -type f -exec chmod 644 {} +

UPLOAD_GUARD_SOURCE="$APP_DIR/ops/uploads.htaccess"
[ -f "$UPLOAD_GUARD_SOURCE" ] || fail "BLOCKED: upload execution guard source missing"
cmp -s "$UPLOAD_GUARD_SOURCE" "$PUBLIC_DIR/uploads/.htaccess" || fail "BLOCKED: upload guard change requires separate review"

log "🔗 Rebuilding QAS front controller..."
INDEX_TMP="$PUBLIC_DIR/.index.php.$$.tmp"
cp "$APP_DIR/public/index.php" "$INDEX_TMP"

sed -i     -e "s|__DIR__.'/../storage/framework/maintenance.php'|__DIR__.'/../../laravel_app_v42_rehearsal/storage/framework/maintenance.php'|"     -e "s|__DIR__.'/../vendor/autoload.php'|__DIR__.'/../../laravel_app_v42_rehearsal/vendor/autoload.php'|"     -e "s|__DIR__.'/../bootstrap/app.php'|__DIR__.'/../../laravel_app_v42_rehearsal/bootstrap/app.php'|"     "$INDEX_TMP"

mv "$INDEX_TMP" "$PUBLIC_DIR/index.php"
chmod 644 "$PUBLIC_DIR/index.php"

log "🌐 Disabling QAS maintenance mode..."
$PHP_BIN artisan up

health_check

ASSET_HEALTHCHECK_URL="$HEALTHCHECK_URL/admin/js/off-canvas.js"
ASSET_HEALTHCHECK_CODE="$(curl -L -sS -o /dev/null -w '%{http_code}' --max-time "$HEALTHCHECK_TIMEOUT" "$ASSET_HEALTHCHECK_URL" || true)"
[ "$ASSET_HEALTHCHECK_CODE" = "200" ] || fail "QAS static asset health check failed: $ASSET_HEALTHCHECK_URL returned HTTP ${ASSET_HEALTHCHECK_CODE:-unknown}"
log "✅ QAS static asset health check passed: HTTP $ASSET_HEALTHCHECK_CODE"

CURRENT_COMMIT="$(git rev-parse HEAD)"
[ "$CURRENT_COMMIT" = "$TARGET_SHA" ] || fail "BLOCKED: final release SHA mismatch"

log "=================================================="
log "✅ QAS web code updated; background workers and full acceptance still require separate authorization"
log "🔖 Deployed commit: $CURRENT_COMMIT"
log "🌐 Test now: $HEALTHCHECK_URL"
log "=================================================="
