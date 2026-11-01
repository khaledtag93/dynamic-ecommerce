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
EXPECTED_PREVIOUS_SHA="0b54e9f16670ca7ae1fae8cc5a0cabd4bdff349e"

BASE_DIR="/home/u637857322/domains/tag-marketplace.com"
APP_DIR="$BASE_DIR/laravel_app_v42_rehearsal"
PUBLIC_DIR="$BASE_DIR/public_html/v42"
HEALTHCHECK_URL="https://v42.tag-marketplace.com"
HEALTHCHECK_TIMEOUT="${HEALTHCHECK_TIMEOUT:-20}"
EXPECTED_DB="u637857322_v42_rehearsal"

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
# Recovery is attempted only after the pinned baseline and QAS gates pass.
# No backup or guaranteed rollback is implied by this script.
MAINTENANCE_ENTERED=false
RELEASE_MUTATION_STARTED=false
RELEASE_COMPLETE=false
PREVIOUS_COMMIT=""
DEPLOY_PHASE="preflight"

log() {
    printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

fail() {
    log "❌ $*"
    exit 1
}

# Best-effort CODE/DEPENDENCY restoration to the pinned QAS baseline.
# No DB restore, runtime-data backup, or broad public-file rollback is done.
# Every recovery command is checked, including the final QAS health response.
# HTTP health deliberately accepts only QAS HTTP 200 and never follows redirects.
recover_previous_release() {
    [ "$PREVIOUS_COMMIT" = "$EXPECTED_PREVIOUS_SHA" ] || return 1
    [ "$(realpath -e "$APP_DIR" 2>/dev/null)" = "$APP_DIR" ] || return 1

    (
        cd "$APP_DIR" || exit 1
        "$PHP_BIN" artisan down --retry=30 || exit 1
        git reset --hard "$PREVIOUS_COMMIT" || exit 1
        [ "$(git rev-parse HEAD)" = "$PREVIOUS_COMMIT" ] || exit 1
        "$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress || exit 1
        "$PHP_BIN" artisan optimize:clear || exit 1
        "$PHP_BIN" artisan config:cache || exit 1
        "$PHP_BIN" artisan view:cache || exit 1
    ) || return 1

    (cd "$APP_DIR" && "$PHP_BIN" artisan up) || return 1

    local recovery_http_code
    recovery_http_code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time "$HEALTHCHECK_TIMEOUT" "$HEALTHCHECK_URL" || true)"
    case "$recovery_http_code" in
        200) return 0 ;;
        *)
            # Do not advertise recovery when the old release is not healthy.
            (cd "$APP_DIR" && "$PHP_BIN" artisan down --retry=30) || true
            return 1
            ;;
    esac
}

# EXIT also runs for explicit fail()/exit and failed post-deploy health checks;
# an ERR-only trap would miss those exit paths and leave unsafe partial state.
on_exit() {
    local original_exit_code=$?
    trap - EXIT
    [ "$original_exit_code" -ne 0 ] || return 0

    log "❌ QAS release stopped during $DEPLOY_PHASE (exit $original_exit_code)"
    [ "$MAINTENANCE_ENTERED" = true ] || exit "$original_exit_code"

    if [ "$RELEASE_MUTATION_STARTED" = true ]; then
        log "↩️ Attempting pinned QAS code/dependency recovery..."
        if recover_previous_release; then
            log "✅ Previous QAS SHA restored and HTTP health confirmed; deployment still FAILED"
        else
            log "🚫 BLOCKED: recovery could not be verified; operator intervention required"
            log "🚫 Keep QAS isolated until code, dependencies, caches and health are checked"
        fi
    else
        # No release files/dependencies were changed; restore only maintenance.
        if (cd "$APP_DIR" && "$PHP_BIN" artisan up); then
            log "✅ QAS maintenance cleared before any release mutation"
        else
            log "🚫 BLOCKED: unable to clear QAS maintenance; operator action required"
        fi
    fi

    exit "$original_exit_code"
}
trap on_exit EXIT

health_check() {
    local code
    code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time "$HEALTHCHECK_TIMEOUT" "$HEALTHCHECK_URL" || true)"

    case "$code" in
        200)
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

# Source-only asset policy: public/ is identical across these pinned commits.
# On QAS there is NO npm/asset build, public-file sync, chmod or index rewrite.
BASELINE_PUBLIC_TREE="$(git rev-parse --verify "$PREVIOUS_COMMIT:public" 2>/dev/null)" || fail "BLOCKED: baseline public/ tree missing"
TARGET_PUBLIC_TREE="$(git rev-parse --verify "$TARGET_SHA:public" 2>/dev/null)" || fail "BLOCKED: target public/ tree missing"
[ "$(git cat-file -t "$BASELINE_PUBLIC_TREE")" = tree ] || fail "BLOCKED: baseline public/ is not a tree"
[ "$(git cat-file -t "$TARGET_PUBLIC_TREE")" = tree ] || fail "BLOCKED: target public/ is not a tree"
[ "$BASELINE_PUBLIC_TREE" = "$TARGET_PUBLIC_TREE" ] || fail "BLOCKED: public files changed; separately reviewed asset release required"

# Verify actual QAS build assets are still byte-identical to their pinned source.
[ -d "$PUBLIC_DIR/build" ] || fail "BLOCKED: QAS build directory missing"
[ -f "$PUBLIC_DIR/build/manifest.json" ] || fail "BLOCKED: QAS Vite manifest missing"
[ "$(realpath -e "$PUBLIC_DIR/build")" = "$PUBLIC_DIR/build" ] || fail "BLOCKED: public/build redirects outside QAS"
[ -f "$APP_DIR/public/build/manifest.json" ] || fail "BLOCKED: source Vite manifest missing"
[ "$(realpath -e "$PUBLIC_DIR/index.php")" = "$PUBLIC_DIR/index.php" ] || fail "BLOCKED: QAS front controller redirects outside QAS"
grep -Fq 'laravel_app_v42_rehearsal' "$PUBLIC_DIR/index.php" || fail "BLOCKED: QAS front controller does not reference the rehearsal app"

while IFS= read -r tracked_asset; do
    case "$tracked_asset" in public/build/*) ;; *) fail "BLOCKED: invalid tracked build asset path" ;; esac
    local_asset="$APP_DIR/$tracked_asset"
    live_asset="$PUBLIC_DIR/${tracked_asset#public/}"
    [ -f "$local_asset" ] && [ -f "$live_asset" ] || fail "BLOCKED: required QAS build asset missing"
    [ "$(realpath -e "$live_asset")" = "$live_asset" ] || fail "BLOCKED: QAS build asset redirects outside QAS"
    cmp -s "$local_asset" "$live_asset" || fail "BLOCKED: QAS build asset differs from pinned source"
done < <(git ls-tree -r --name-only "$PREVIOUS_COMMIT" -- public/build)

# Protect QAS upload execution guard before maintenance, never after mutation.
git diff --quiet "$PREVIOUS_COMMIT" "$TARGET_SHA" -- ops/uploads.htaccess || fail "BLOCKED: upload guard source changed"
[ -f "$APP_DIR/ops/uploads.htaccess" ] || fail "BLOCKED: upload guard source missing"
cmp -s "$APP_DIR/ops/uploads.htaccess" "$PUBLIC_DIR/uploads/.htaccess" || fail "BLOCKED: QAS upload guard mismatch"

git diff --quiet "$PREVIOUS_COMMIT" "$TARGET_SHA" -- database/migrations resources/views || fail "BLOCKED: schema or view change requires separate approval"

log "🔖 Verified previous commit: $PREVIOUS_COMMIT"
log "🎯 Verified target commit: $TARGET_SHA"

# No maintenance, tracked-file or public-file mutation occurs before these gates.
# The earlier git fetch updates only local Git remote-tracking metadata.
DEPLOY_PHASE="maintenance"
log "🚧 Enabling QAS maintenance mode..."
MAINTENANCE_ENTERED=true
"$PHP_BIN" artisan down --retry=30

DEPLOY_PHASE="git reset"
RELEASE_MUTATION_STARTED=true
log "🔄 Updating pinned QAS application code..."
git reset --hard "$TARGET_SHA"
[ "$(git rev-parse HEAD)" = "$TARGET_SHA" ] || fail "BLOCKED: wrong SHA after reset"

DEPLOY_PHASE="source preflight"
if grep -RInE --include='*.blade.php' '(AppModels|IlluminateSupport)[A-Za-z0-9_:]*' resources/views; then
    fail "Blade namespace preflight failed"
fi

DEPLOY_PHASE="composer install"
log "📦 Installing pinned PHP dependencies..."
"$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress

DEPLOY_PHASE="route verification"
"$PHP_BIN" artisan route:list --name=workforce >/dev/null

DEPLOY_PHASE="laravel caches"
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan view:cache

# Public/ was checked before maintenance and is intentionally never changed.
# No npm, Vite build, rsync, chmod, index rewrite, migration, queue restart or backup.

DEPLOY_PHASE="maintenance exit"
"$PHP_BIN" artisan up

DEPLOY_PHASE="HTTP health"
health_check

ASSET_HEALTHCHECK_URL="$HEALTHCHECK_URL/admin/js/off-canvas.js"
ASSET_HEALTHCHECK_CODE="$(curl -sS -o /dev/null -w '%{http_code}' --max-time "$HEALTHCHECK_TIMEOUT" "$ASSET_HEALTHCHECK_URL" || true)"
[ "$ASSET_HEALTHCHECK_CODE" = "200" ] || fail "QAS static asset health check failed: HTTP ${ASSET_HEALTHCHECK_CODE:-unknown}"
log "✅ QAS static asset health check passed: HTTP $ASSET_HEALTHCHECK_CODE"

[ "$(git rev-parse HEAD)" = "$TARGET_SHA" ] || fail "BLOCKED: final release SHA mismatch"

RELEASE_COMPLETE=true
log "=================================================="
log "✅ QAS source updated; public/build unchanged; M3/M4 and background acceptance remain OPEN"
log "🔖 Deployed source commit: $TARGET_SHA"
log "🌐 Verify independently: $HEALTHCHECK_URL"
log "=================================================="
