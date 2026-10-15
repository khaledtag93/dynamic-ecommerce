# W-GAP-02 — Workforce Release Evidence Inventory

Date: 2026-10-15 | Baseline `2006c46d` | Read-only source/test audit; status is NOT production acceptance.

## Evidence rule

A checked-in PHPUnit test file is evidence of a regression specification, not proof each business rule is complete; a green full-suite CI proves tests pass on the tested commit, not completeness of missing workflows or authenticated QAS. Status labels below distinguish the evidenced foundation from unresolved release gates. `Implemented/Tested` means an existing bounded foundation plus named tests; `Policy Blocked`, `QAS Deferred` and `Unknown` override any implied release approval.

| Original DoD area | Source / service | Checked-in test evidence | Verified boundary and status | Open gate |
|---|---|---|---|---|
| Org reporting, descendant scope and anti-self review | `WorkforceAccessService`, `ReportingHierarchyService` | `WorkforceReportingHierarchyFoundationTest`, `WorkforceScopedAccessTest`, `WorkforceLeaveApprovalRoutingTest` | Implemented/Tested foundation | QAS Deferred: W-ORG-01b authenticated personas |
| Missing manager, stale hierarchy, reviewer visibility | `LeaveApprovalReadinessService`, `LeaveApprovalRoutingService` | `WorkforceLeaveApprovalReadinessTest`, `WorkforceLeaveApprovalRoutingTest` | Implemented/Tested bounded diagnostics | Policy Blocked: reassignment, delegation and escalation NOT implemented by diagnostics |
| Leave type duration, workdays, historical snapshots and validation | `LeavePolicyService`, `LeaveRequestService`, `LeaveBalanceService` | `WorkforceLeavePolicyEngineTest`, `WorkforceLeavePolicyPreviewTest`, `WorkforceLeaveHistoricalPolicySnapshotTest`, `WorkforceLeaveLocalDayBoundariesTest`, `WorkforceLeaveManagementTest` | Implemented/Tested foundation | Policy Blocked for unapproved jurisdiction/site rules, partial days and entitlement edge cases; UNKNOWN exact complete coverage |
| Employee profile and leave attachments / medical evidence privacy | No purpose-built attachment service or `*Document*` model confirmed in W-DOC-01 audit | P01–P14 in W-DOC-03/W-DOC-05 are SPECIFICATIONS, not runnable tests | Policy Blocked / Not Implemented | B1–B5, S1–S2, L1; private schema/storage, AV, endpoint authorization and retention absent |
| Scheduling interactions with approved leave and midnight shifts | `WorkShiftService`, `AttendanceRulesService` | `WorkforceSchedulingTest`, `WorkforceAttendanceRulesTest`, `WorkforceTimezoneAttendanceUxTest` | Implemented/Tested foundation | Policy Blocked: draft/publish conflict resolution and notifications; QAS acceptance not proven |
| Payroll leave-pay mapping, immutable snapshots and periods | `PayrollService`, `CompensationService` | `WorkforcePayrollTest`, `WorkforceLeaveHistoricalPolicySnapshotTest`, `WorkforceFoundationTest` | Implemented/Tested foundation | Policy Blocked: paid/unpaid mappings, retroactive policy, signed-off financial reconciliation; UNKNOWN full system coverage |
| Role persona matrix and workspace UX | `WorkforceAccessService` plus role permissions and UI controllers | `WorkforceDemoPersonaMatrixTest`, `WorkforceWorkspaceClosureTest`, `WorkforceBladeIntegrityTest` | Implemented/Tested offline foundations | QAS Deferred: authenticated environment acceptance; browser E2E with actual personnel not claimed |
| Notification delivery, retry, approval delegation / assignee state machine | Existing `LeaveRequestService` plus generic notification infrastructure | No dedicated executable notification/delegation workflow test named among inspected Workforce feature test files | UNKNOWN / Policy Blocked | Define authority, notification triggers, idempotency and acceptance before coding |

## CI evidence and non-equivalences

- W-APP-03e CI https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38661138767: PHPUnit 952 / 18,629 assertions, Workforce offline QA 9/9, browser tests 4/4 and frontend build successful.
- W-DOC-05 CI https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38663773472: PHPUnit 952 / 18,629 assertions, Workforce offline QA 9/9, browser tests 4/4 and frontend build successful.
- W-GAP-01 CI #38664336168 requires its own verified result before Source Audit + CI closure. Do not assume success.
- Document P01–P14 are NOT included as document-specific executable tests in these counts. No real sensitive document feature or policy approval is claimed.

## Roadmap disambiguation

Original W-DOC-04 refers to future attachments implementation; use `W-DOC-IMPL-04` to disambiguate from `W-DOC-04-REG` (Decision Register) and `W-DOC-05-TRACE` (synthetic design). Aliases are purely planning labels.

## Prioritized release blockers

1. W-ORG-01b QAS authenticated acceptance remains DEFERRED — no environment access was attempted.
2. Sensitive documents implementation blocked on B1–B5, S1–S2, L1 sign-offs and dedicated privacy architecture; never silently enable public uploads.
3. Approval delegation, escalation and notification workflow policies unapproved. Existing reviewer-readiness diagnostics do not authorize these behaviors.
4. Scheduling conflict, local holidays and payroll snapshot downstream decisions require policy and regression evidence before release claims.
5. Map remaining original audit findings to exact test method names and branch-specific CI history in a future audit; file-level mapping here is NOT line-level proof of all scenarios.

## Disposition

W-GAP-02 source evidence inventory COMPLETE as documentation only; CI gate pending its own commit run. No SQL migrations, schema, attachments, runtime writes, policy modifications, SSH, QAS or Production operations.
