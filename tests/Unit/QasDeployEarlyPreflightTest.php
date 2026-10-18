<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Static/source-only regression contract. It NEVER invokes deploy-qas.sh,
 * artisan, SQL, shell, a remote executor, or QAS/Production.
 */
final class QasDeployEarlyPreflightTest extends TestCase
{
    private function source(): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/deploy-qas.sh');
        self::assertIsString($source);

        return $source;
    }

    public function test_explicit_qas_branch_and_full_target_sha_are_required_without_a_default_branch(): void
    {
        $source = $this->source();

        self::assertStringContainsString('BRANCH="${1:-}"', $source);
        self::assertStringContainsString('TARGET_SHA="${2:-}"', $source);
        self::assertStringContainsString('BRANCH" = "sec03-framework-upgrade"', $source);
        self::assertStringContainsString('TARGET_SHA" =~ ^[0-9a-f]{40}$', $source);
        self::assertStringNotContainsString('BRANCH="${1:-v42-clean-baseline}"', $source);
        self::assertStringContainsString('git reset --hard "$TARGET_SHA"', $source);
        self::assertStringNotContainsString('git reset --hard "origin/$BRANCH"', $source);
    }

    public function test_runtime_identity_and_existing_qas_worktree_are_verified_before_any_mutation(): void
    {
        $source = $this->source();

        self::assertStringContainsString('EXPECTED_DB="u637857322_v42_rehearsal"', $source);
        self::assertStringContainsString('ACTUAL_RUNTIME_IDENTITY=', $source);
        self::assertStringContainsString('app()->environment()', $source);
        self::assertStringContainsString('SELECT DATABASE()', $source);
        self::assertStringContainsString('staging|$EXPECTED_DB', $source);
        self::assertStringContainsString('git diff --quiet', $source);
        self::assertStringContainsString('git diff --cached --quiet', $source);
        self::assertStringContainsString('EXPECTED_PREVIOUS_SHA="16c2fdb612926db19214a051dabc395770cf0aa1"', $source);

        $identity = strpos($source, 'ACTUAL_RUNTIME_IDENTITY=');
        self::assertNotFalse($identity);

        foreach ([
            'chmod 600 .env',
            'sed -i',
            'mkdir -p "$PUBLIC_DIR/uploads"',
            'git fetch --prune origin',
            'git reset --hard "$TARGET_SHA"',
            'artisan down',
            'artisan migrate',
            'artisan queue:restart',
        ] as $candidate) {
            $position = strpos($source, $candidate);
            if ($position !== false) {
                self::assertGreaterThan($identity, $position, 'Mutation is reachable before runtime identity: '.$candidate);
            }
        }
    }

    public function test_previous_release_gate_accepts_only_last_operator_verified_qas_sha_in_offline_bash_fixture(): void
    {
        $source = $this->source();
        $expected = '16c2fdb612926db19214a051dabc395770cf0aa1';
        $obsolete = 'de180de6cae5f28dc867b752ab6f1d97baa71416';
        $guard = '[ "$PREVIOUS_COMMIT" = "$EXPECTED_PREVIOUS_SHA" ] || fail "BLOCKED: unexpected QAS pre-release SHA"';

        self::assertSame(1, preg_match('/^EXPECTED_PREVIOUS_SHA="([0-9a-f]{40})"$/m', $source, $matches));
        self::assertSame($expected, $matches[1]);
        self::assertNotSame($obsolete, $matches[1]);
        self::assertStringContainsString('PREVIOUS_COMMIT="$(git rev-parse HEAD)"', $source);
        self::assertStringContainsString($guard, $source);

        // Execute ONLY the extracted comparison against local synthetic SHAs.
        // Never source/execute deploy-qas.sh and never connect to QAS.
        foreach ([$expected => 0, $obsolete => 79, str_repeat('a', 40) => 79] as $previous => $exit) {
            $fixture = 'set -Eeuo pipefail'.PHP_EOL
                .'fail() { printf "%s\n" "$*"; exit 79; }'.PHP_EOL
                .'EXPECTED_PREVIOUS_SHA='.escapeshellarg($matches[1]).PHP_EOL
                .'PREVIOUS_COMMIT='.escapeshellarg($previous).PHP_EOL
                .$guard.PHP_EOL;
            $process = new \Symfony\Component\Process\Process(['bash', '-c', $fixture]);
            $process->run();
            self::assertSame($exit, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
            if ($exit !== 0) {
                self::assertStringContainsString('BLOCKED: unexpected QAS pre-release SHA', $process->getOutput());
            }
        }
    }

    public function test_qas_identity_worktree_migration_and_protected_data_guards_precede_mutation(): void
    {
        $source = $this->source();
        $fetch = strpos($source, 'git fetch --prune origin');
        $reset = strpos($source, 'git reset --hard "$TARGET_SHA"');
        $down = strpos($source, '"$PHP_BIN" artisan down --retry=30');
        self::assertNotFalse($fetch);
        self::assertNotFalse($reset);
        self::assertNotFalse($down);

        foreach ([
            'EXPECTED_DB="u637857322_v42_rehearsal"',
            'grep -Eq '."'".'^APP_ENV=staging    {
        $source = $this->source();
        self::assertStringContainsString('git fetch --prune origin "+refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"', $source);
        self::assertStringContainsString('RESOLVED_TARGET_SHA="$(git rev-parse "origin/$BRANCH")"', $source);
        self::assertStringContainsString('[ "$RESOLVED_TARGET_SHA" = "$TARGET_SHA" ]', $source);
        self::assertLessThan(strpos($source, 'artisan down'), strpos($source, 'RESOLVED_TARGET_SHA='));
        self::assertLessThan(strpos($source, 'git reset --hard "$TARGET_SHA"'), strpos($source, 'RESOLVED_TARGET_SHA='));
    }

    public function test_qas_owner_backup_waiver_does_not_weaken_runtime_file_protections(): void
    {
        $source = $this->source();

        // Only QAS skips new backup creation: never delete pre-existing snapshots.
        self::assertStringNotContainsString('qas_release_backups', $source);
        self::assertStringNotContainsString('SNAPSHOT_APP_DIR=', $source);
        self::assertStringNotContainsString('SNAPSHOT_PUBLIC_DIR=', $source);
        self::assertStringNotContainsString('mkdir -m 700 -p', $source);
        self::assertStringNotContainsString('rm -rf', $source);
        self::assertStringNotContainsString('git clean', $source);

        // Runtime data and destination scope remain strictly protected.
        self::assertStringContainsString('scripts/qas-queue-cron.sh', $source);
        self::assertStringContainsString('scripts/qas-scheduler-cron.sh', $source);
        self::assertStringContainsString('public_html/v42', $source);
        self::assertStringContainsString('laravel_app_v42_rehearsal', $source);
        self::assertStringContainsString('git ls-tree -r --name-only "$TARGET_SHA"', $source);
        self::assertStringContainsString("--exclude='uploads'", $source);
        self::assertStringContainsString("--exclude='storage'", $source);
        self::assertStringContainsString('git diff --quiet "$PREVIOUS_COMMIT" "$TARGET_SHA" -- ops/uploads.htaccess', $source);
    }

    public function test_qas_runtime_guards_still_run_before_git_reset_without_backup(): void
    {
        $source = $this->source();
        $reset = strpos($source, 'git reset --hard "$TARGET_SHA"');
        self::assertNotFalse($reset);

        foreach ([
            'PREVIOUS_COMMIT="$(git rev-parse HEAD)"',
            'PUBLIC_STORAGE_TARGET=',
            'git ls-tree -r --name-only "$TARGET_SHA"',
            'BASELINE_PUBLIC_STORAGE_TREE=',
            'TARGET_PUBLIC_STORAGE_TREE=',
            'git diff --quiet "$PREVIOUS_COMMIT" "$TARGET_SHA" -- ops/uploads.htaccess',
        ] as $guard) {
            $position = strpos($source, $guard);
            self::assertNotFalse($position, 'Missing protection: '.$guard);
            self::assertLessThan($reset, $position, 'Protection occurs too late: '.$guard);
        }
    }

    public function test_qas_script_does_not_call_production_scripts_or_switch_to_production_host(): void
    {
        $source = $this->source();
        self::assertStringContainsString('HEALTHCHECK_URL="https://v42.tag-marketplace.com"', $source);
        self::assertStringContainsString('APP_DIR="$BASE_DIR/laravel_app_v42_rehearsal"', $source);
        self::assertStringContainsString('PUBLIC_DIR="$BASE_DIR/public_html/v42"', $source);
        self::assertStringNotContainsString('https://tag-marketplace.com"', $source);
        self::assertStringNotContainsString('bash deploy.sh', $source);
        self::assertStringNotContainsString('bash rollback.sh', $source);
        self::assertStringNotContainsString('migrate:rollback', $source);
    }

    public function test_early_error_handler_cannot_toggle_qas_maintenance_and_storage_links_are_checked(): void
    {
        $source = $this->source();
        self::assertStringContainsString('MAINTENANCE_ENTERED=false', $source);
        self::assertStringContainsString('if [ "$MAINTENANCE_ENTERED" = true ]; then', $source);
        self::assertStringContainsString('MAINTENANCE_ENTERED=true', $source);
        self::assertStringContainsString('realpath -e "$PUBLIC_DIR/uploads"', $source);
        self::assertStringContainsString('realpath -e "$APP_DIR/storage/app"', $source);
        self::assertStringContainsString('PUBLIC_STORAGE_TARGET=', $source);
        self::assertStringContainsString('public storage points outside QAS', $source);
        self::assertStringContainsString('git diff --quiet "$PREVIOUS_COMMIT" "$TARGET_SHA" -- ops/uploads.htaccess', $source);
        self::assertStringNotContainsString('rsync -a --delete', $source);
    }

    public function test_unapproved_pending_migrations_do_not_run_automatically(): void
    {
        $source = $this->source();
        self::assertStringNotContainsString('artisan migrate --force', $source);
        self::assertStringContainsString('Pending migrations require separate approval', $source);
    }
}
."'".' .env',
            '[ "$ACTUAL_RUNTIME_IDENTITY" = "staging|$EXPECTED_DB" ]',
            '[ "$PREVIOUS_COMMIT" = "$EXPECTED_PREVIOUS_SHA" ]',
            'git diff --quiet || fail',
            'git diff --cached --quiet || fail',
            'MIGRATION_STATUS=',
            'Pending migrations require separate approval',
            'realpath -e "$PUBLIC_DIR/uploads"',
            'PUBLIC_STORAGE_TARGET=',
        ] as $guard) {
            $position = strpos($source, $guard);
            self::assertNotFalse($position, 'Missing prefetch safety guard: '.$guard);
            self::assertLessThan($fetch, $position, 'Safety guard must precede fetch: '.$guard);
        }

        foreach ([
            '[ "$RESOLVED_TARGET_SHA" = "$TARGET_SHA" ]',
            'git ls-tree -r --name-only "$TARGET_SHA" -- "$protected_path"',
            '[ "$BASELINE_PUBLIC_STORAGE_TREE" = "$TARGET_PUBLIC_STORAGE_TREE" ]',
            'git diff --quiet "$PREVIOUS_COMMIT" "$TARGET_SHA" -- ops/uploads.htaccess',
        ] as $guard) {
            $position = strpos($source, $guard);
            self::assertNotFalse($position, 'Missing prerest safety guard: '.$guard);
            self::assertLessThan($down, $position, 'Safety guard must precede maintenance: '.$guard);
            self::assertLessThan($reset, $position, 'Safety guard must precede reset: '.$guard);
        }

        self::assertStringContainsString('APP_DIR="$BASE_DIR/laravel_app_v42_rehearsal"', $source);
        self::assertStringContainsString('PUBLIC_DIR="$BASE_DIR/public_html/v42"', $source);
        self::assertStringContainsString('BRANCH" = "sec03-framework-upgrade"', $source);
        self::assertStringContainsString('TARGET_SHA" =~ ^[0-9a-f]{40}    {
        $source = $this->source();
        self::assertStringContainsString('git fetch --prune origin "+refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"', $source);
        self::assertStringContainsString('RESOLVED_TARGET_SHA="$(git rev-parse "origin/$BRANCH")"', $source);
        self::assertStringContainsString('[ "$RESOLVED_TARGET_SHA" = "$TARGET_SHA" ]', $source);
        self::assertLessThan(strpos($source, 'artisan down'), strpos($source, 'RESOLVED_TARGET_SHA='));
        self::assertLessThan(strpos($source, 'git reset --hard "$TARGET_SHA"'), strpos($source, 'RESOLVED_TARGET_SHA='));
    }

    public function test_qas_owner_backup_waiver_does_not_weaken_runtime_file_protections(): void
    {
        $source = $this->source();

        // Only QAS skips new backup creation: never delete pre-existing snapshots.
        self::assertStringNotContainsString('qas_release_backups', $source);
        self::assertStringNotContainsString('SNAPSHOT_APP_DIR=', $source);
        self::assertStringNotContainsString('SNAPSHOT_PUBLIC_DIR=', $source);
        self::assertStringNotContainsString('mkdir -m 700 -p', $source);
        self::assertStringNotContainsString('rm -rf', $source);
        self::assertStringNotContainsString('git clean', $source);

        // Runtime data and destination scope remain strictly protected.
        self::assertStringContainsString('scripts/qas-queue-cron.sh', $source);
        self::assertStringContainsString('scripts/qas-scheduler-cron.sh', $source);
        self::assertStringContainsString('public_html/v42', $source);
        self::assertStringContainsString('laravel_app_v42_rehearsal', $source);
        self::assertStringContainsString('git ls-tree -r --name-only "$TARGET_SHA"', $source);
        self::assertStringContainsString("--exclude='uploads'", $source);
        self::assertStringContainsString("--exclude='storage'", $source);
        self::assertStringContainsString('git diff --quiet "$PREVIOUS_COMMIT" "$TARGET_SHA" -- ops/uploads.htaccess', $source);
    }

    public function test_qas_runtime_guards_still_run_before_git_reset_without_backup(): void
    {
        $source = $this->source();
        $reset = strpos($source, 'git reset --hard "$TARGET_SHA"');
        self::assertNotFalse($reset);

        foreach ([
            'PREVIOUS_COMMIT="$(git rev-parse HEAD)"',
            'PUBLIC_STORAGE_TARGET=',
            'git ls-tree -r --name-only "$TARGET_SHA"',
            'BASELINE_PUBLIC_STORAGE_TREE=',
            'TARGET_PUBLIC_STORAGE_TREE=',
            'git diff --quiet "$PREVIOUS_COMMIT" "$TARGET_SHA" -- ops/uploads.htaccess',
        ] as $guard) {
            $position = strpos($source, $guard);
            self::assertNotFalse($position, 'Missing protection: '.$guard);
            self::assertLessThan($reset, $position, 'Protection occurs too late: '.$guard);
        }
    }

    public function test_qas_script_does_not_call_production_scripts_or_switch_to_production_host(): void
    {
        $source = $this->source();
        self::assertStringContainsString('HEALTHCHECK_URL="https://v42.tag-marketplace.com"', $source);
        self::assertStringContainsString('APP_DIR="$BASE_DIR/laravel_app_v42_rehearsal"', $source);
        self::assertStringContainsString('PUBLIC_DIR="$BASE_DIR/public_html/v42"', $source);
        self::assertStringNotContainsString('https://tag-marketplace.com"', $source);
        self::assertStringNotContainsString('bash deploy.sh', $source);
        self::assertStringNotContainsString('bash rollback.sh', $source);
        self::assertStringNotContainsString('migrate:rollback', $source);
    }

    public function test_early_error_handler_cannot_toggle_qas_maintenance_and_storage_links_are_checked(): void
    {
        $source = $this->source();
        self::assertStringContainsString('MAINTENANCE_ENTERED=false', $source);
        self::assertStringContainsString('if [ "$MAINTENANCE_ENTERED" = true ]; then', $source);
        self::assertStringContainsString('MAINTENANCE_ENTERED=true', $source);
        self::assertStringContainsString('realpath -e "$PUBLIC_DIR/uploads"', $source);
        self::assertStringContainsString('realpath -e "$APP_DIR/storage/app"', $source);
        self::assertStringContainsString('PUBLIC_STORAGE_TARGET=', $source);
        self::assertStringContainsString('public storage points outside QAS', $source);
        self::assertStringContainsString('git diff --quiet "$PREVIOUS_COMMIT" "$TARGET_SHA" -- ops/uploads.htaccess', $source);
        self::assertStringNotContainsString('rsync -a --delete', $source);
    }

    public function test_unapproved_pending_migrations_do_not_run_automatically(): void
    {
        $source = $this->source();
        self::assertStringNotContainsString('artisan migrate --force', $source);
        self::assertStringContainsString('Pending migrations require separate approval', $source);
    }
}
, $source);
        self::assertStringContainsString("--exclude='uploads'", $source);
        self::assertStringContainsString("--exclude='storage'", $source);
        self::assertStringNotContainsString('artisan migrate --force', $source);
        self::assertStringNotContainsString('artisan queue:restart', $source);
        self::assertStringNotContainsString('qas_release_backups', $source);
    }

    public function test_target_revision_is_pinned_and_checked_before_maintenance_or_reset(): void
    {
        $source = $this->source();
        self::assertStringContainsString('git fetch --prune origin "+refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"', $source);
        self::assertStringContainsString('RESOLVED_TARGET_SHA="$(git rev-parse "origin/$BRANCH")"', $source);
        self::assertStringContainsString('[ "$RESOLVED_TARGET_SHA" = "$TARGET_SHA" ]', $source);
        self::assertLessThan(strpos($source, 'artisan down'), strpos($source, 'RESOLVED_TARGET_SHA='));
        self::assertLessThan(strpos($source, 'git reset --hard "$TARGET_SHA"'), strpos($source, 'RESOLVED_TARGET_SHA='));
    }

    public function test_qas_owner_backup_waiver_does_not_weaken_runtime_file_protections(): void
    {
        $source = $this->source();

        // Only QAS skips new backup creation: never delete pre-existing snapshots.
        self::assertStringNotContainsString('qas_release_backups', $source);
        self::assertStringNotContainsString('SNAPSHOT_APP_DIR=', $source);
        self::assertStringNotContainsString('SNAPSHOT_PUBLIC_DIR=', $source);
        self::assertStringNotContainsString('mkdir -m 700 -p', $source);
        self::assertStringNotContainsString('rm -rf', $source);
        self::assertStringNotContainsString('git clean', $source);

        // Runtime data and destination scope remain strictly protected.
        self::assertStringContainsString('scripts/qas-queue-cron.sh', $source);
        self::assertStringContainsString('scripts/qas-scheduler-cron.sh', $source);
        self::assertStringContainsString('public_html/v42', $source);
        self::assertStringContainsString('laravel_app_v42_rehearsal', $source);
        self::assertStringContainsString('git ls-tree -r --name-only "$TARGET_SHA"', $source);
        self::assertStringContainsString("--exclude='uploads'", $source);
        self::assertStringContainsString("--exclude='storage'", $source);
        self::assertStringContainsString('git diff --quiet "$PREVIOUS_COMMIT" "$TARGET_SHA" -- ops/uploads.htaccess', $source);
    }

    public function test_qas_runtime_guards_still_run_before_git_reset_without_backup(): void
    {
        $source = $this->source();
        $reset = strpos($source, 'git reset --hard "$TARGET_SHA"');
        self::assertNotFalse($reset);

        foreach ([
            'PREVIOUS_COMMIT="$(git rev-parse HEAD)"',
            'PUBLIC_STORAGE_TARGET=',
            'git ls-tree -r --name-only "$TARGET_SHA"',
            'BASELINE_PUBLIC_STORAGE_TREE=',
            'TARGET_PUBLIC_STORAGE_TREE=',
            'git diff --quiet "$PREVIOUS_COMMIT" "$TARGET_SHA" -- ops/uploads.htaccess',
        ] as $guard) {
            $position = strpos($source, $guard);
            self::assertNotFalse($position, 'Missing protection: '.$guard);
            self::assertLessThan($reset, $position, 'Protection occurs too late: '.$guard);
        }
    }

    public function test_qas_script_does_not_call_production_scripts_or_switch_to_production_host(): void
    {
        $source = $this->source();
        self::assertStringContainsString('HEALTHCHECK_URL="https://v42.tag-marketplace.com"', $source);
        self::assertStringContainsString('APP_DIR="$BASE_DIR/laravel_app_v42_rehearsal"', $source);
        self::assertStringContainsString('PUBLIC_DIR="$BASE_DIR/public_html/v42"', $source);
        self::assertStringNotContainsString('https://tag-marketplace.com"', $source);
        self::assertStringNotContainsString('bash deploy.sh', $source);
        self::assertStringNotContainsString('bash rollback.sh', $source);
        self::assertStringNotContainsString('migrate:rollback', $source);
    }

    public function test_early_error_handler_cannot_toggle_qas_maintenance_and_storage_links_are_checked(): void
    {
        $source = $this->source();
        self::assertStringContainsString('MAINTENANCE_ENTERED=false', $source);
        self::assertStringContainsString('if [ "$MAINTENANCE_ENTERED" = true ]; then', $source);
        self::assertStringContainsString('MAINTENANCE_ENTERED=true', $source);
        self::assertStringContainsString('realpath -e "$PUBLIC_DIR/uploads"', $source);
        self::assertStringContainsString('realpath -e "$APP_DIR/storage/app"', $source);
        self::assertStringContainsString('PUBLIC_STORAGE_TARGET=', $source);
        self::assertStringContainsString('public storage points outside QAS', $source);
        self::assertStringContainsString('git diff --quiet "$PREVIOUS_COMMIT" "$TARGET_SHA" -- ops/uploads.htaccess', $source);
        self::assertStringNotContainsString('rsync -a --delete', $source);
    }

    public function test_unapproved_pending_migrations_do_not_run_automatically(): void
    {
        $source = $this->source();
        self::assertStringNotContainsString('artisan migrate --force', $source);
        self::assertStringContainsString('Pending migrations require separate approval', $source);
    }
}
