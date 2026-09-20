#!/bin/bash
set -Eeuo pipefail
umask 077

BASE_DIR="/home/u637857322/domains/tag-marketplace.com"
SOURCE_DIR="$BASE_DIR/laravel_app_v42_rehearsal"
APP_DIR="$BASE_DIR/laravel_app"
PUBLIC_DIR="$BASE_DIR/public_html"
BACKUP_ROOT="$BASE_DIR/deploy_backups"
LOG_DIR="$BASE_DIR/deploy_logs"
RELEASE_ROOT="$BASE_DIR/deploy_releases"

EXPECTED_DB="u637857322_tagmarketplace"
HEALTHCHECK_URL="https://tag-marketplace.com"

TARGET_COMMIT="${1:-}"
MODE="${2:-}"

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

BACKUP_READY=0
DB_SNAPSHOT_READY=0
PROD_MUTATED=0
MAINTENANCE_ACTIVE=0
DB_HOST=""
DB_PORT=""
DB_USER=""
DB_PASS=""
BACKUP_DIR=""
COOKIE_FILE=""

log() {
    printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

fail() {
    log "❌ $*"
    return 1
}

on_error() {
    local STATUS="$1"
    local LINE="$2"

    trap - ERR
    set +e

    log "❌ Deployment failed at line $LINE (exit $STATUS)"

    if [ "$MODE" = "--execute" ] && [ "$PROD_MUTATED" -eq 1 ] && [ "$BACKUP_READY" -eq 1 ]; then
        log "⚠ Automatic rollback starting..."

        cd "$APP_DIR" 2>/dev/null || true

        if [ "$MAINTENANCE_ACTIVE" -ne 1 ]; then
            "$PHP_BIN" artisan down --retry=60 >/dev/null 2>&1 || true
            MAINTENANCE_ACTIVE=1
        fi

        log "Restoring application files..."
        rsync -a --delete \
    --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r \
            --exclude='.env' \
            --exclude='.env.backup_*' \
            --exclude='storage' \
            "$BACKUP_DIR/app/" "$APP_DIR/" || true

        log "Restoring public files..."
        rsync -a --delete \
    --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r \
            --exclude='uploads' \
            --exclude='storage' \
            --exclude='v42' \
            "$BACKUP_DIR/public/" "$PUBLIC_DIR/" || true

        if [ "$DB_SNAPSHOT_READY" -eq 1 ] && [ -s "$BACKUP_DIR/database.sql" ] && [ -n "$DB_PASS" ]; then
            log "Restoring pre-deploy database snapshot..."
            MYSQL_PWD="$DB_PASS" mysql \
                --host="$DB_HOST" \
                --user="$DB_USER" \
                "$EXPECTED_DB" < "$BACKUP_DIR/database.sql" || true
        fi

        cd "$APP_DIR" 2>/dev/null || true
        "$PHP_BIN" artisan optimize:clear >/dev/null 2>&1 || true
        "$PHP_BIN" artisan config:cache >/dev/null 2>&1 || true
        "$PHP_BIN" artisan view:cache >/dev/null 2>&1 || true
        "$PHP_BIN" artisan up >/dev/null 2>&1 || true
        MAINTENANCE_ACTIVE=0

        ROLLBACK_HTTP="$(
            curl -L -sS -o /dev/null -w '%{http_code}' \
                --max-time 20 "$HEALTHCHECK_URL" 2>/dev/null || true
        )"

        log "Rollback health check: HTTP ${ROLLBACK_HTTP:-unknown}"
        log "⚠ Automatic rollback finished. Review logs before retrying."
    elif [ "$MODE" = "--execute" ] && [ "$MAINTENANCE_ACTIVE" -eq 1 ]; then
        cd "$APP_DIR" 2>/dev/null || true
        "$PHP_BIN" artisan up >/dev/null 2>&1 || true
        MAINTENANCE_ACTIVE=0
        log "Production maintenance mode cleared."
    fi

    [ -n "$COOKIE_FILE" ] && rm -f "$COOKIE_FILE"
    unset DB_PASS 2>/dev/null || true

    exit "$STATUS"
}

trap 'on_error $? $LINENO' ERR
trap 'rm -f "${LOG_PIPE:-}" "${COOKIE_FILE:-}"' EXIT

[ -n "$TARGET_COMMIT" ] || fail "Usage: ./deploy-prod.sh <commit> --dry-run|--execute"
[ "$MODE" = "--dry-run" ] || [ "$MODE" = "--execute" ] || fail "Second argument must be --dry-run or --execute"

for CMD in git tar rsync mysqldump mysql curl base64 "$PHP_BIN" "$COMPOSER_BIN"; do
    command -v "$CMD" >/dev/null 2>&1 || fail "Required command not found: $CMD"
done

[ -d "$SOURCE_DIR/.git" ] || fail "Source Git repository not found"
[ -d "$APP_DIR" ] || fail "Production app directory not found"
[ -d "$PUBLIC_DIR" ] || fail "Production public directory not found"
[ -f "$APP_DIR/.env" ] || fail "Production .env not found"
[ -f "$APP_DIR/artisan" ] || fail "Production artisan not found"

mkdir -p "$BACKUP_ROOT" "$LOG_DIR" "$RELEASE_ROOT"

cd "$SOURCE_DIR"

git cat-file -e "${TARGET_COMMIT}^{commit}" 2>/dev/null || fail "Commit not found: $TARGET_COMMIT"

TARGET_COMMIT_FULL="$(git rev-parse "${TARGET_COMMIT}^{commit}")"
TARGET_COMMIT_SHORT="$(git rev-parse --short "$TARGET_COMMIT_FULL")"

APP_ENV_VALUE="$(grep -m1 '^APP_ENV=' "$APP_DIR/.env" | cut -d= -f2- | tr -d '\r' | tr -d '"')"
APP_DEBUG_VALUE="$(grep -m1 '^APP_DEBUG=' "$APP_DIR/.env" | cut -d= -f2- | tr -d '\r' | tr -d '"')"
ENV_DB_VALUE="$(grep -m1 '^DB_DATABASE=' "$APP_DIR/.env" | cut -d= -f2- | tr -d '\r' | tr -d '"')"

[ "$APP_ENV_VALUE" = "production" ] || fail "APP_ENV is not production"
[ "$APP_DEBUG_VALUE" = "false" ] || fail "APP_DEBUG is not false"
[ "$ENV_DB_VALUE" = "$EXPECTED_DB" ] || fail "Production .env DB mismatch: $ENV_DB_VALUE"

ACTUAL_DB="$(
    cd "$APP_DIR"
    "$PHP_BIN" artisan tinker \
        --execute='echo DB::selectOne("SELECT DATABASE() AS db")->db;' \
        2>/dev/null
)"

[ "$ACTUAL_DB" = "$EXPECTED_DB" ] || fail "Connected production DB mismatch: $ACTUAL_DB"

PRE_HTTP="$(
    curl -L -sS -o /dev/null -w '%{http_code}' \
        --max-time 20 "$HEALTHCHECK_URL" || true
)"

case "$PRE_HTTP" in
    200|301|302) ;;
    *) fail "Production health check failed before deploy: HTTP $PRE_HTTP" ;;
esac

BUILD_ID="$(date '+%Y%m%d_%H%M%S')_${TARGET_COMMIT_SHORT}"
RELEASE_DIR="$RELEASE_ROOT/$BUILD_ID"
PROD_INDEX="$RELEASE_ROOT/${BUILD_ID}_production_index.php"
BACKUP_DIR="$BACKUP_ROOT/$BUILD_ID"
LOG_FILE="$LOG_DIR/deploy_${BUILD_ID}.log"

LOG_PIPE="/tmp/dynamic-deploy-${BUILD_ID}.pipe"
rm -f "$LOG_PIPE"
mkfifo "$LOG_PIPE"
tee -a "$LOG_FILE" < "$LOG_PIPE" &
TEE_PID=$!
exec > "$LOG_PIPE" 2>&1

log "=================================================="
log "Dynamic V42 Production Deploy"
log "Mode: $MODE"
log "Target commit: $TARGET_COMMIT_FULL"
log "Production DB: $ACTUAL_DB"
log "Current health: HTTP $PRE_HTTP"
log "Release ID: $BUILD_ID"
log "=================================================="

log "Building isolated release..."
mkdir -p "$RELEASE_DIR"

cd "$SOURCE_DIR"
git archive "$TARGET_COMMIT_FULL" | tar -x -C "$RELEASE_DIR"

cd "$RELEASE_DIR"

"$COMPOSER_BIN" install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction \
    --no-progress

"$PHP_BIN" artisan --version

cp "$RELEASE_DIR/public/index.php" "$PROD_INDEX"

sed -i \
    -e "s#__DIR__.'/../storage/#__DIR__.'/../laravel_app/storage/#g" \
    -e "s#__DIR__.'/../vendor/autoload.php'#__DIR__.'/../laravel_app/vendor/autoload.php'#g" \
    -e "s#__DIR__.'/../bootstrap/app.php'#__DIR__.'/../laravel_app/bootstrap/app.php'#g" \
    "$PROD_INDEX"

"$PHP_BIN" -l "$PROD_INDEX"

grep -q "laravel_app/storage/logs/local_boot_trace.log" "$PROD_INDEX" || fail "Production index trace path was not rewritten"
grep -q "laravel_app/storage/framework/maintenance.php" "$PROD_INDEX" || fail "Production index maintenance path was not rewritten"
grep -q "laravel_app/vendor/autoload.php" "$PROD_INDEX" || fail "Production index vendor path was not rewritten"
grep -q "laravel_app/bootstrap/app.php" "$PROD_INDEX" || fail "Production index bootstrap path was not rewritten"

log "✅ Release built successfully"

if [ "$MODE" = "--dry-run" ]; then
    log "=================================================="
    log "APP deployment dry-run:"
    rsync -ani --delete \
        --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r \
        --exclude='.env' \
        --exclude='.env.backup_*' \
        --exclude='storage' \
        "$RELEASE_DIR/" "$APP_DIR/"

    log "PUBLIC deployment dry-run:"
    rsync -ani --delete \
        --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r \
        --exclude='index.php' \
        --exclude='uploads' \
        --exclude='storage' \
        --exclude='v42' \
        "$RELEASE_DIR/public/" "$PUBLIC_DIR/"

    log "Production index replacement prepared:"
    sha256sum "$PROD_INDEX"

    log "✅ DRY-RUN COMPLETE"
    log "Production has NOT been modified."
    exit 0
fi

log "Creating pre-deploy file backup..."
mkdir -p "$BACKUP_DIR/app" "$BACKUP_DIR/public"

rsync -a \
    --exclude='storage' \
    "$APP_DIR/" "$BACKUP_DIR/app/"

rsync -a \
    --exclude='uploads' \
    --exclude='storage' \
    --exclude='v42' \
    "$PUBLIC_DIR/" "$BACKUP_DIR/public/"

[ -f "$BACKUP_DIR/app/.env" ] && chmod 600 "$BACKUP_DIR/app/.env"

BACKUP_READY=1

{
    echo "backup_id=$BUILD_ID"
    echo "target_commit=$TARGET_COMMIT_FULL"
    echo "created_at=$(date '+%Y-%m-%d %H:%M:%S')"
    echo "pre_deploy_http=$PRE_HTTP"
    echo "database=$EXPECTED_DB"
} > "$BACKUP_DIR/metadata.txt"

chmod 600 "$BACKUP_DIR/metadata.txt"

log "✅ File backup complete"

cd "$APP_DIR"

MAINT_SECRET="$("$PHP_BIN" -r 'echo bin2hex(random_bytes(24));')"
COOKIE_FILE="/tmp/dynamic-prod-deploy-${BUILD_ID}.cookie"

log "Putting Production into maintenance mode..."
"$PHP_BIN" artisan down --secret="$MAINT_SECRET" --retry=60
MAINTENANCE_ACTIVE=1

MAINT_HTTP="$(
    curl -sS -o /dev/null -w '%{http_code}' \
        --max-time 20 "$HEALTHCHECK_URL" || true
)"

[ "$MAINT_HTTP" = "503" ] || fail "Maintenance mode verification failed: HTTP $MAINT_HTTP"

log "✅ Maintenance mode verified: HTTP 503"

DB_HOST_B64="$("$PHP_BIN" artisan tinker --execute='echo base64_encode(config("database.connections.mysql.host"));' 2>/dev/null)"
DB_PORT_B64="$("$PHP_BIN" artisan tinker --execute='echo base64_encode(config("database.connections.mysql.port"));' 2>/dev/null)"
DB_USER_B64="$("$PHP_BIN" artisan tinker --execute='echo base64_encode(config("database.connections.mysql.username"));' 2>/dev/null)"
DB_PASS_B64="$("$PHP_BIN" artisan tinker --execute='echo base64_encode(config("database.connections.mysql.password"));' 2>/dev/null)"

DB_HOST="$(printf '%s' "$DB_HOST_B64" | base64 -d)"
DB_PORT="$(printf '%s' "$DB_PORT_B64" | base64 -d)"
DB_USER="$(printf '%s' "$DB_USER_B64" | base64 -d)"
DB_PASS="$(printf '%s' "$DB_PASS_B64" | base64 -d)"

[ -n "$DB_HOST" ] || fail "DB host is empty"
[ -n "$DB_PORT" ] || fail "DB port is empty"
[ -n "$DB_USER" ] || fail "DB username is empty"
[ -n "$DB_PASS" ] || fail "DB password is empty"

log "Creating pre-migration database snapshot..."

MYSQL_PWD="$DB_PASS" mysqldump \
    --single-transaction \
    --quick \
    --skip-lock-tables \
    --no-tablespaces \
    --default-character-set=utf8mb4 \
    --host="$DB_HOST" \
    --user="$DB_USER" \
    --result-file="$BACKUP_DIR/database.sql" \
    "$EXPECTED_DB"

[ -s "$BACKUP_DIR/database.sql" ] || fail "Database snapshot is empty"

chmod 600 "$BACKUP_DIR/database.sql"
DB_SNAPSHOT_READY=1

DB_SHA256="$(sha256sum "$BACKUP_DIR/database.sql" | awk '{print $1}')"
echo "database_sha256=$DB_SHA256" >> "$BACKUP_DIR/metadata.txt"

log "✅ DB snapshot complete: $DB_SHA256"

log "Deploying application files..."
PROD_MUTATED=1

rsync -a --delete \
    --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r \
    --exclude='.env' \
    --exclude='.env.backup_*' \
    --exclude='storage' \
    "$RELEASE_DIR/" "$APP_DIR/"

cd "$APP_DIR"

"$PHP_BIN" artisan optimize:clear

POST_SYNC_DB="$(
    "$PHP_BIN" artisan tinker \
        --execute='echo DB::selectOne("SELECT DATABASE() AS db")->db;' \
        2>/dev/null
)"

[ "$POST_SYNC_DB" = "$EXPECTED_DB" ] || fail "DB mismatch after code sync: $POST_SYNC_DB"

log "Running database migrations..."
"$PHP_BIN" artisan migrate --force

log "Building Laravel caches..."
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan view:cache

log "Syncing public assets..."
rsync -a --delete \
    --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r \
    --exclude='index.php' \
    --exclude='uploads' \
    --exclude='storage' \
    --exclude='v42' \
    "$APP_DIR/public/" "$PUBLIC_DIR/"

install -m 644 "$PROD_INDEX" "$PUBLIC_DIR/index.php"

"$PHP_BIN" -l "$PUBLIC_DIR/index.php"

log "Testing new release through maintenance secret..."

SECRET_HTTP="$(curl -sS -c "$COOKIE_FILE" -o /dev/null -w "%{http_code}" --max-time 20 "$HEALTHCHECK_URL/$MAINT_SECRET" || true)"

[ "$SECRET_HTTP" = "302" ] || fail "Maintenance secret request failed: HTTP $SECRET_HTTP"
log "✅ Maintenance secret accepted: HTTP $SECRET_HTTP"

BYPASS_HTTP=""
for ATTEMPT in 1 2 3 4 5; do
    BYPASS_HTTP="$(curl -sS -L -c "$COOKIE_FILE" -b "$COOKIE_FILE" -o /dev/null -w "%{http_code}" --max-time 20 "$HEALTHCHECK_URL" || true)"

    case "$BYPASS_HTTP" in
        200|301|302)
            log "✅ Maintenance-bypass health check passed: HTTP $BYPASS_HTTP (attempt $ATTEMPT)"
            break
            ;;
        *)
            log "⚠ Maintenance-bypass attempt $ATTEMPT returned HTTP ${BYPASS_HTTP:-unknown}"
            [ "$ATTEMPT" -lt 5 ] && sleep 2
            ;;
    esac
done

case "$BYPASS_HTTP" in
    200|301|302) ;;
    *) fail "Maintenance-bypass health check failed after 5 attempts: HTTP ${BYPASS_HTTP:-unknown}" ;;
esac

log "Leaving maintenance mode..."
"$PHP_BIN" artisan up
MAINTENANCE_ACTIVE=0

FINAL_HTTP="$(
    curl -L -sS -o /dev/null -w '%{http_code}' \
        --max-time 20 "$HEALTHCHECK_URL" || true
)"

case "$FINAL_HTTP" in
    200|301|302)
        log "✅ Production health check passed: HTTP $FINAL_HTTP"
        ;;
    *)
        fail "Final Production health check failed: HTTP $FINAL_HTTP"
        ;;
esac

rm -f "$COOKIE_FILE"
COOKIE_FILE=""

unset DB_PASS DB_PASS_B64

log "=================================================="
log "✅ PRODUCTION DEPLOY COMPLETE"
log "🔖 Commit: $TARGET_COMMIT_SHORT"
log "💾 Backup: $BACKUP_DIR"
log "🗄 DB snapshot SHA256: $DB_SHA256"
log "🌐 Health: HTTP $FINAL_HTTP"
log "📝 Log: $LOG_FILE"
log "=================================================="
