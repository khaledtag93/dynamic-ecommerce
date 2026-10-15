<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class WorkforceCoverageEvidenceAccuracyTest extends TestCase
{
    public function test_direct_evidence_names_resolve_to_real_tests(): void
    {
        $references = [
            'WorkforceScopedAccessTest' => [
                'test_supervisor_can_only_see_own_reporting_tree_in_workforce_lists_and_metrics',
                'test_shift_and_leave_approvals_are_denied_for_other_teams_and_self',
            ],
            'WorkforceReportingHierarchyFoundationTest' => ['test_cycles_self_management_and_non_manager_assignment_are_rejected'],
            'WorkforceLeaveApprovalReadinessTest' => ['test_missing_inactive_or_permissionless_direct_manager_is_not_counted_as_eligible'],
            'WorkforceLeavePolicyEngineTest' => ['test_fixed_calendar_duration_is_server_authoritative_and_mismatched_payload_is_rejected'],
            'WorkforceSchedulingTest' => ['test_draft_shift_is_hidden_from_employee_schedule_until_published'],
            'WorkforcePayrollTest' => ['test_hourly_payroll_uses_effective_attendance_breaks_and_leave_snapshot_without_auto_monetizing_leave'],
        ];
        $document = file_get_contents(dirname(__DIR__, 2).'/docs/WGAP04_COVERAGE_EVIDENCE_ACCURACY_2026-10-15.md');
        self::assertIsString($document);
        foreach ($references as $class => $methods) {
            $source = file_get_contents(__DIR__.'/'.$class.'.php');
            self::assertIsString($source);
            foreach ($methods as $method) {
                self::assertStringContainsString('function '.$method.'(', $source);
                self::assertStringContainsString($class.'::'.$method, $document);
            }
        }
    }

    public function test_missing_policy_and_acceptance_gates_stay_explicit(): void
    {
        $document = file_get_contents(dirname(__DIR__, 2).'/docs/WGAP04_COVERAGE_EVIDENCE_ACCURACY_2026-10-15.md');
        self::assertIsString($document);
        foreach (['Direct Regression', 'Indirect Evidence', 'Missing Test', 'Policy Blocked', 'QAS Deferred', 'W-ORG-01b'] as $label) {
            self::assertStringContainsString($label, $document);
        }
    }
}
