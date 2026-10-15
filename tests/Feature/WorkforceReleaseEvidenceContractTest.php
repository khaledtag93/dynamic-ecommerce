<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Protects the source-evidence mapping; does not assert QAS or policy acceptance.
 */
final class WorkforceReleaseEvidenceContractTest extends TestCase
{
    public function test_named_privacy_and_reviewer_regressions_remain_traceable(): void
    {
        $file = file_get_contents(dirname(__DIR__).'/Feature/WorkforceLeaveApprovalReadinessTest.php');
        self::assertIsString($file);
        foreach ([
            'test_live_list_readiness_only_counts_current_scoped_page_and_hides_other_team',
            'test_cyclic_scoped_hierarchy_reports_configuration_without_self_review',
            'test_stale_direct_manager_reference_is_configuration_without_cross_team_disclosure',
        ] as $method) {
            self::assertStringContainsString('function '.$method.'(', $file);
        }
    }

    public function test_leave_policy_and_scope_regressions_remain_traceable(): void
    {
        $policy = file_get_contents(__DIR__.'/WorkforceLeavePolicyEngineTest.php');
        $scope = file_get_contents(__DIR__.'/WorkforceScopedAccessTest.php');
        self::assertIsString($policy);
        self::assertIsString($scope);
        self::assertStringContainsString('function test_fixed_calendar_duration_is_server_authoritative_and_mismatched_payload_is_rejected(', $policy);
        self::assertStringContainsString('function test_policy_updates_do_not_rewrite_existing_leave_requests(', $policy);
        self::assertStringContainsString('function test_shift_and_leave_approvals_are_denied_for_other_teams_and_self(', $scope);
    }

    public function test_evidence_inventory_explicitly_retains_release_gates(): void
    {
        $inventory = file_get_contents(dirname(__DIR__, 2).'/docs/WGAP02_WORKFORCE_RELEASE_EVIDENCE_INVENTORY_2026-10-15.md');
        self::assertIsString($inventory);
        foreach (['Policy Blocked', 'QAS Deferred', 'UNKNOWN', 'P01', 'P14', 'W-ORG-01b'] as $gate) {
            self::assertStringContainsString($gate, $inventory);
        }
    }
}
