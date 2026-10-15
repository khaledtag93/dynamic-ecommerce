# W-GAP-04 — Coverage Evidence Accuracy

Baseline `84b21108`; source-only audit; no policy or QAS acceptance.

| DoD | Exact method (`tests/Feature/`) | Evidence | Open gap |
|---|---|---|---|
| Reporting-tree privacy | `WorkforceScopedAccessTest::test_supervisor_can_only_see_own_reporting_tree_in_workforce_lists_and_metrics` | Direct Regression | QAS Deferred |
| Deny self / cross-team approvals | `WorkforceScopedAccessTest::test_shift_and_leave_approvals_are_denied_for_other_teams_and_self` | Direct Regression | QAS Deferred |
| Hierarchy rejects cycles | `WorkforceReportingHierarchyFoundationTest::test_cycles_self_management_and_non_manager_assignment_are_rejected` | Direct Regression | Historical policy Unknown |
| Invalid direct manager excluded | `WorkforceLeaveApprovalReadinessTest::test_missing_inactive_or_permissionless_direct_manager_is_not_counted_as_eligible` | Direct Regression | Delegation Policy Blocked |
| Fixed leave authoritative | `WorkforceLeavePolicyEngineTest::test_fixed_calendar_duration_is_server_authoritative_and_mismatched_payload_is_rejected` | Direct Regression | Other policy variants Unknown |
| Draft schedule not published | `WorkforceSchedulingTest::test_draft_shift_is_hidden_from_employee_schedule_until_published` | Direct Regression | Leave/shift conflict resolution Missing Test; Policy Blocked |
| Hourly payroll snapshot | `WorkforcePayrollTest::test_hourly_payroll_uses_effective_attendance_breaks_and_leave_snapshot_without_auto_monetizing_leave` | Direct Regression | Paid leave mapping Policy Blocked |
| Sensitive document P01-P14 | Future test specifications only | Missing Test; Policy Blocked | B1-B5, S1-S2, L1 approvals |
| Delegation escalation notification lifecycle | No direct method verified | Missing Test; Policy Blocked | Requires business approval |
| Authenticated persona acceptance | `WorkforceDemoPersonaMatrixTest` offline | Indirect Evidence; QAS Deferred | W-ORG-01b still open |

Direct Regression is a named bounded automated test, not full functional signoff. Indirect Evidence is insufficient proof of the exact release criterion. Missing Test indicates no verified direct executable test. Policy Blocked means unresolved decisions; QAS Deferred means no authenticated acceptance. New test checks exact source method references and gate labels only. No feature behavior, schema, upload/download, SSH, QAS or Production writes.
