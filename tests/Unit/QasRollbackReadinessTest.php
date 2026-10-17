<?php

namespace Tests\Unit;

use App\Support\QasRollbackReadiness;
use PHPUnit\Framework\TestCase;

/**
 * Offline-only test evidence. No Laravel bootstrap, QAS connection, network,
 * database, shell, filesystem mutation or deployment is used.
 */
final class QasRollbackReadinessTest extends TestCase
{
    private const OLD_SHA = 'de180de6cae5f28dc867b752ab6f1d97baa71416';
    private const RELEASE_SHA = '56bb5e893973324322f67e6236ec7b49343b5c75';

    private function completeSyntheticEvidence(): array
    {
        return [
            'target_host' => 'v42.tag-marketplace.com',
            'app_path' => '/home/u637857322/domains/tag-marketplace.com/laravel_app_v42_rehearsal',
            'public_path' => '/home/u637857322/domains/tag-marketplace.com/public_html/v42',
            'source_evidence' => 'authorized_app_runtime',
            'app_env' => 'staging',
            'laravel_connected_database' => 'u637857322_v42_rehearsal',
            'current_runtime_sha' => self::RELEASE_SHA,
            'reviewed_release_sha' => self::RELEASE_SHA,
            'recovery_sha' => self::OLD_SHA,
            'ci_sha' => self::RELEASE_SHA,
            'ci_completed_success' => true,
            'worktree_clean' => true,
            'pending_migrations' => 0,
            'schema_compatible_with_recovery' => true,
            'verified_qas_code_assets_snapshot' => true,
            'protected_runtime_paths_verified' => true,
            'operator_channel_permitted' => true,
            'operator_approval_for_rollback' => true,
        ];
    }

    public function test_missing_evidence_fails_closed_without_execution(): void
    {
        $result = (new QasRollbackReadiness())->assess([]);

        $this->assertSame('BLOCKED', $result['status']);
        $this->assertFalse($result['execution_authorized']);
        $this->assertContains('runtime_identity_unverified', $result['blockers']);
        $this->assertContains('exact_release_sha_unverified', $result['blockers']);
        $this->assertNotEmpty($result['blockers']);
    }

    public function test_complete_synthetic_evidence_is_only_review_ready_not_permission_to_run(): void
    {
        $result = (new QasRollbackReadiness())->assess($this->completeSyntheticEvidence());

        $this->assertSame('REVIEW_READY_ONLY', $result['status']);
        $this->assertSame([], $result['blockers']);
        $this->assertFalse($result['execution_authorized']);
        $this->assertSame(self::OLD_SHA, $result['recovery_sha']);
        $this->assertSame('QAS_ONLY', $result['scope']);
        $this->assertSame('MANUAL_SEPARATE_APPROVAL_REQUIRED', $result['execution_gate']);
    }

    public function test_production_or_path_traversal_or_alternate_host_is_never_accepted(): void
    {
        $base = $this->completeSyntheticEvidence();
        foreach ([
            ['target_host', 'tag-marketplace.com'],
            ['target_host', 'v42.tag-marketplace.com.attacker.test'],
            ['app_path', '/home/u637857322/domains/tag-marketplace.com/laravel_app'],
            ['app_path', $base['app_path'].'/../laravel_app'],
            ['public_path', '/home/u637857322/domains/tag-marketplace.com/public_html'],
            ['public_path', '/tmp/qas'],
            ['laravel_connected_database', 'u637857322_tagmarketplace'],
            ['app_env', 'production'],
        ] as [$key, $unsafe]) {
            $facts = array_replace($base, [$key => $unsafe]);
            $result = (new QasRollbackReadiness())->assess($facts);
            $this->assertSame('BLOCKED', $result['status'], 'Unexpected acceptance of '.$key.'='.$unsafe);
            $this->assertFalse($result['execution_authorized']);
            $this->assertContains('runtime_identity_unverified', $result['blockers']);
        }
    }

    public function test_phpmyadmin_or_hpanel_git_proof_cannot_replace_laravel_runtime_identity(): void
    {
        foreach (['phpmyadmin', 'hpanel_git', 'env_file', 'historical_report', ''] as $untrusted) {
            $facts = $this->completeSyntheticEvidence();
            $facts['source_evidence'] = $untrusted;

            $result = (new QasRollbackReadiness())->assess($facts);
            $this->assertSame('BLOCKED', $result['status']);
            $this->assertContains('runtime_identity_unverified', $result['blockers']);
        }
    }

    public function test_recovery_and_release_shas_must_be_exact_never_branch_labels_or_prefixes(): void
    {
        foreach ([
            ['current_runtime_sha', 'v42-clean-baseline'],
            ['current_runtime_sha', substr(self::RELEASE_SHA, 0, 8)],
            ['reviewed_release_sha', self::OLD_SHA],
            ['reviewed_release_sha', '0000000000000000000000000000000000000000'],
            ['recovery_sha', self::RELEASE_SHA],
            ['recovery_sha', substr(self::OLD_SHA, 0, 8)],
            ['ci_sha', self::OLD_SHA],
            ['ci_completed_success', false],
        ] as [$key, $wrong]) {
            $facts = $this->completeSyntheticEvidence();
            $facts[$key] = $wrong;
            $result = (new QasRollbackReadiness())->assess($facts);
            $this->assertSame('BLOCKED', $result['status'], 'Unexpected SHA/CI acceptance: '.$key);
            $this->assertContains('exact_release_sha_unverified', $result['blockers']);
        }
    }

    public function test_pending_or_unverified_migrations_and_missing_recovery_snapshot_block(): void
    {
        foreach ([
            ['pending_migrations', 1],
            ['pending_migrations', null],
            ['pending_migrations', '0'],
            ['schema_compatible_with_recovery', false],
            ['verified_qas_code_assets_snapshot', false],
            ['worktree_clean', false],
            ['protected_runtime_paths_verified', false],
        ] as [$key, $wrong]) {
            $facts = $this->completeSyntheticEvidence();
            $facts[$key] = $wrong;
            $result = (new QasRollbackReadiness())->assess($facts);
            $this->assertSame('BLOCKED', $result['status'], 'Unexpected recovery acceptance: '.$key);
            $this->assertFalse($result['execution_authorized']);
        }
    }

    public function test_unapproved_or_blocked_channel_is_not_promotable(): void
    {
        foreach (['operator_channel_permitted', 'operator_approval_for_rollback'] as $key) {
            $facts = $this->completeSyntheticEvidence();
            $facts[$key] = false;
            $result = (new QasRollbackReadiness())->assess($facts);
            $this->assertSame('BLOCKED', $result['status']);
            $this->assertFalse($result['execution_authorized']);
            $this->assertContains('execution_scope_not_authorized', $result['blockers']);
        }
    }

    public function test_guard_ships_no_shell_database_remote_or_filesystem_executor(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/QasRollbackReadiness.php');

        $this->assertIsString($source);
        $this->assertStringContainsString('execution_authorized', $source);
        $this->assertStringContainsString("'PLAN_ONLY_NOT_IMPLEMENTED'", $source);
        $this->assertDoesNotMatchRegularExpression(
            '/\\b(?:exec|shell_exec|system|passthru|proc_open|file_put_contents|unlink|rename|mkdir|rmdir)\\s*\\(|(?:DB|Artisan|Process|Http|Mail|Storage|Queue|Schema)::|\\b(?:git reset|rsync --delete|artisan migrate)\\b/i',
            $source
        );
    }

    public function test_protected_file_inventory_is_explicit_and_contains_no_executable_commands(): void
    {
        $result = (new QasRollbackReadiness())->assess($this->completeSyntheticEvidence());
        $paths = $result['protected_paths'];

        foreach ([
            'laravel_app_v42_rehearsal/.env',
            'laravel_app_v42_rehearsal/storage/',
            'laravel_app_v42_rehearsal/scripts/qas-queue-cron.sh',
            'laravel_app_v42_rehearsal/scripts/qas-scheduler-cron.sh',
            'public_html/v42/uploads/',
            'public_html/v42/storage/',
            'public_html/v42/index.php',
            'public_html/v42/uploads/.htaccess',
        ] as $required) {
            $this->assertContains($required, $paths);
        }

        $encoded = json_encode($result, JSON_THROW_ON_ERROR);
        foreach (['git reset', 'rsync ', 'artisan ', 'migrate:', 'DROP ', 'DELETE ', 'queue:restart'] as $command) {
            $this->assertStringNotContainsString($command, $encoded);
        }
        $this->assertFalse($result['execution_authorized']);
    }
}
