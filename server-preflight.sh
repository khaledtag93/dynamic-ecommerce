#!/bin/bash

set -Eeuo pipefail
umask 077

#############################################
# Dynamic E-commerce System - Server Preflight
# Read-only validation before deploy rehearsal.
#
# Usage:
#   ./server-preflight.sh
#############################################

BASE_DIR="/home/u637857322/domains/tag-marketplace.com"
APP_DIR="$BASE_DIR/laravel_app"
PUBLIC_DIR="$BASE_DIR/public_html"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-https://tag-marketplace.com}"
HEALTHCHECK_TIMEOUT="${HEALTHCHECK_TIMEOUT:-20}"

PASS=0
WARN=0
FAIL=0

ok() {
    PASS=$((PASS + 1))
    printf '✅ %s\n' "$*"
}

warn() {
    WARN=$((WARN + 1))
    printf '⚠️ %s\n' "$*"
}

bad() {
    FAIL=$((FAIL + 1))
    printf '❌ %s\n' "$*"
}

check_command() {
    local cmd="$1"

    if command -v "$cmd" >/dev/null 2>&1; then
        ok "Command available: $cmd"
    else
        bad "Missing required command: $cmd"
    fi
}

printf '==================================================\n'
printf 'Dynamic E-commerce - Server Preflight\n'
printf '==================================================\n'

[ -d "$BASE_DIR" ] && ok "Base directory exists" || bad "Base directory missing: $BASE_DIR"
[ -d "$APP_DIR" ] && ok "Laravel app directory exists" || bad "Laravel app directory missing: $APP_DIR"
[ -d "$PUBLIC_DIR" ] && ok "Public webroot exists" || bad "Public webroot missing: $PUBLIC_DIR"
[ -f "$APP_DIR/.env" ] && ok "Server .env exists" || bad "Server .env missing"
[ -f "$APP_DIR/artisan" ] && ok "Laravel artisan exists" || bad "artisan missing"
[ -f "$APP_DIR/composer.json" ] && ok "composer.json exists" || bad "composer.json missing"

for cmd in git rsync curl mysqldump; do
    check_command "$cmd"
done

if command -v "$PHP_BIN" >/dev/null 2>&1; then
    PHP_VERSION="$($PHP_BIN -r 'echo PHP_VERSION;' 2>/dev/null || true)"
    if [ -n "$PHP_VERSION" ]; then
        ok "PHP available: $PHP_VERSION"
    else
        bad "PHP exists but version could not be read"
    fi
else
    bad "PHP binary not available: $PHP_BIN"
fi

if command -v "$COMPOSER_BIN" >/dev/null 2>&1; then
    COMPOSER_VERSION="$($COMPOSER_BIN --version --no-ansi 2>/dev/null | head -n1 || true)"
    [ -n "$COMPOSER_VERSION" ] && ok "$COMPOSER_VERSION" || bad "Composer version could not be read"
else
    bad "Composer binary not available: $COMPOSER_BIN"
fi

REQUIRED_EXTENSIONS=(mbstring dom xml xmlwriter pdo_mysql bcmath intl curl zip)
if command -v "$PHP_BIN" >/dev/null 2>&1; then
    PHP_MODULES="$($PHP_BIN -m 2>/dev/null | tr '[:upper:]' '[:lower:]')"

    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if printf '%s\n' "$PHP_MODULES" | grep -Fxq "$ext"; then
            ok "PHP extension enabled: $ext"
        else
            bad "Missing PHP extension: $ext"
        fi
    done
fi

if [ -d "$APP_DIR" ]; then
    if git -C "$APP_DIR" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
        ok "Laravel app is a Git worktree"

        CURRENT_BRANCH="$(git -C "$APP_DIR" branch --show-current 2>/dev/null || true)"
        CURRENT_COMMIT="$(git -C "$APP_DIR" rev-parse --short HEAD 2>/dev/null || true)"
        [ -n "$CURRENT_BRANCH" ] && ok "Current branch: $CURRENT_BRANCH" || warn "Could not determine current branch"
        [ -n "$CURRENT_COMMIT" ] && ok "Current commit: $CURRENT_COMMIT" || warn "Could not determine current commit"

        if git -C "$APP_DIR" remote get-url origin >/dev/null 2>&1; then
            ok "Git origin remote is configured"
        else
            bad "Git origin remote is not configured"
        fi

        if [ -n "$(git -C "$APP_DIR" status --porcelain 2>/dev/null)" ]; then
            warn "Server worktree has local changes; deploy.sh will reset them"
        else
            ok "Server worktree is clean"
        fi
    else
        bad "Laravel app directory is not a Git worktree"
    fi
fi

for path in     "$APP_DIR/storage"     "$APP_DIR/storage/framework"     "$APP_DIR/storage/logs"     "$APP_DIR/bootstrap/cache"; do
    if [ -d "$path" ] && [ -w "$path" ]; then
        ok "Writable Laravel path: $path"
    elif [ -d "$path" ]; then
        bad "Laravel path is not writable: $path"
    else
        warn "Laravel path does not exist yet: $path"
    fi
done

if [ -f "$APP_DIR/artisan" ] && command -v "$PHP_BIN" >/dev/null 2>&1; then
    if (
        cd "$APP_DIR"
        $PHP_BIN artisan about --only=environment >/dev/null 2>&1
    ); then
        ok "Laravel boots successfully with server environment"
    else
        bad "Laravel failed to boot with server environment"
    fi

    if (
        cd "$APP_DIR"
        $PHP_BIN artisan migrate:status >/dev/null 2>&1
    ); then
        ok "Application can connect to the configured database"
    else
        bad "Database connectivity / migration status check failed"
    fi
fi

if command -v curl >/dev/null 2>&1; then
    HTTP_CODE="$(curl -L -sS -o /dev/null -w '%{http_code}' --max-time "$HEALTHCHECK_TIMEOUT" "$HEALTHCHECK_URL" || true)"

    case "$HTTP_CODE" in
        200|301|302)
            ok "HTTPS health endpoint reachable: HTTP $HTTP_CODE"
            ;;
        000|"")
            warn "HTTPS health endpoint could not be reached from this server"
            ;;
        *)
            warn "HTTPS health endpoint returned HTTP $HTTP_CODE"
            ;;
    esac
fi

printf '==================================================\n'
printf 'Preflight summary: %s passed, %s warnings, %s failed\n' "$PASS" "$WARN" "$FAIL"
printf '==================================================\n'

if [ "$FAIL" -gt 0 ]; then
    exit 1
fi

exit 0
