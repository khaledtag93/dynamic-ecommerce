<?php

namespace App\Support;

/**
 * QAS-only rollback readiness contract for offline/source review.
 *
 * NO executor, shell, network, filesystem, Laravel app, database or provider
 * calls. Caller-provided facts are untrusted attestations: a passing synthetic
 * assessment is NEVER authorization to execute a rollback.
 *
 * The actual operator/hosting approval channel remains separately BLOCKED.
 */
final class QasRollbackReadiness
{
    public const RECOVERY_SHA = 'de180de6cae5f28dc867b752ab6f1d97baa71416';

    private const QAS_HOST = 'v42.tag-marketplace.com';
    private const QAS_APP_PATH = '/home/u637857322/domains/tag-marketplace.com/laravel_app_v42_rehearsal';
    private const QAS_PUBLIC_PATH = '/home/u637857322/domains/tag-marketplace.com/public_html/v42';
    private const QAS_DB = 'u637857322_v42_rehearsal';

    /**
     * These runtime-owned QAS files must be inventoried and protected by a
     * separately approved code/asset recovery procedure. This is not a sync
     * command and does not certify any file has been backed up.
     */
    private const PROTECTED_PATHS = [
        'laravel_app_v42_rehearsal/.env',
        'laravel_app_v42_rehearsal/storage/',
        'laravel_app_v42_rehearsal/storage/app/',
        'laravel_app_v42_rehearsal/scripts/qas-queue-cron.sh',
        'laravel_app_v42_rehearsal/scripts/qas-scheduler-cron.sh',
        'public_html/v42/uploads/',
        'public_html/v42/storage/',
        'public_html/v42/index.php',
        'public_html/v42/uploads/.htaccess',
    ];

    /**
     * Pure validation of operator-supplied, non-sensitive metadata ONLY.
     * Even all-green facts result in REVIEW_READY_ONLY, never execution GO.
     *
     * @param array<string, mixed> $facts
     * @return array<string, mixed>
     */
    public function assess(array $facts): array
    {
        $blockers = [];

        if (
            ($facts['target_host'] ?? null) !== self::QAS_HOST
            || ($facts['app_path'] ?? null) !== self::QAS_APP_PATH
            || ($facts['public_path'] ?? null) !== self::QAS_PUBLIC_PATH
            || ($facts['app_env'] ?? null) !== 'staging'
            || ($facts['laravel_connected_database'] ?? null) !== self::QAS_DB
            || ($facts['source_evidence'] ?? null) !== 'authorized_app_runtime'
        ) {
            $blockers[] = 'runtime_identity_unverified';
        }

        $running = $facts['current_runtime_sha'] ?? null;
        $reviewed = $facts['reviewed_release_sha'] ?? null;
        $ci = $facts['ci_sha'] ?? null;
        $recovery = $facts['recovery_sha'] ?? null;
        if (
            ! $this->validSha($running)
            || ! $this->validSha($reviewed)
            || ! $this->validSha($ci)
            || ! $this->validSha($recovery)
            || $running !== $reviewed
            || $reviewed !== $ci
            || $reviewed === self::RECOVERY_SHA
            || $recovery !== self::RECOVERY_SHA
            || ($facts['ci_completed_success'] ?? null) !== true
        ) {
            $blockers[] = 'exact_release_sha_unverified';
        }

        if (($facts['worktree_clean'] ?? null) !== true) {
            $blockers[] = 'worktree_unverified';
        }

        // Source compare has no newly added migration files, but this does
        // NOT prove that no older migrations are pending on QAS.
        if (
            ($facts['pending_migrations'] ?? null) !== 0
            || ($facts['schema_compatible_with_recovery'] ?? null) !== true
        ) {
            $blockers[] = 'database_compatibility_unverified';
        }

        if (
            ($facts['verified_qas_code_assets_snapshot'] ?? null) !== true
            || ($facts['protected_runtime_paths_verified'] ?? null) !== true
        ) {
            $blockers[] = 'qas_recovery_assets_unverified';
        }

        if (
            ($facts['operator_channel_permitted'] ?? null) !== true
            || ($facts['operator_approval_for_rollback'] ?? null) !== true
        ) {
            $blockers[] = 'execution_scope_not_authorized';
        }

        return [
            'scope' => 'QAS_ONLY',
            'status' => $blockers === [] ? 'REVIEW_READY_ONLY' : 'BLOCKED',
            // ALWAYS false: no execution capability is shipped here.
            'execution_authorized' => false,
            'execution_gate' => 'MANUAL_SEPARATE_APPROVAL_REQUIRED',
            'recovery_sha' => self::RECOVERY_SHA,
            'blockers' => $blockers,
            'protected_paths' => self::PROTECTED_PATHS,
            'database_rollback' => 'NOT_INCLUDED',
            'automatic_migrations' => 'NOT_INCLUDED',
            'automatic_queue_restart' => 'NOT_INCLUDED',
            'automatic_provider_send' => 'NOT_INCLUDED',
            'code_assets_restore' => 'PLAN_ONLY_NOT_IMPLEMENTED',
        ];
    }

    private function validSha(mixed $sha): bool
    {
        return is_string($sha) && preg_match('/\\A[a-f0-9]{40}\\z/D', $sha) === 1;
    }
}
