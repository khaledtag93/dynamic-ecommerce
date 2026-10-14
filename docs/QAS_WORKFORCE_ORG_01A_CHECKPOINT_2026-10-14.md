# QAS Workforce Reporting Hierarchy Foundation — W-ORG-01a

Date: 2026-10-14
Active branch: `sec03-framework-upgrade`
Verified QAS application SHA: `bedb9fc0`
CI: https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38595362470 — **Green**, 901 tests and 18,150 assertions.
Production unchanged.

## Implemented
- New optional, indexed, self-referencing `employee_profiles.manager_employee_profile_id` foreign key. The migration is non-destructive and existing employees are left unassigned.
- `EmployeeProfile.reportingManager()` and `directReports()` relationships.
- `ReportingHierarchyService::assignManager()` row-locks reporting changes, detects self-assignment / management cycles and invalid manager status or permissions, and audits old/new manager IDs.
- `ReportingHierarchyService::reportsTo()` provides a read-only chain predicate for later scoped access policies.
- Employee create/edit form now includes optional Direct Manager selector, eligible managers only, with EN/AR messages; existing non-manager fields continue to work.
- Tests cover create/edit, hierarchy chain traversal, audit, self/cyclic/unauthorized/inactive manager denial, and non-manager inability to edit employee reporting lines.

## QAS verification
- Deployed with existing `deploy-qas.sh sec03-framework-upgrade`. Application + static HTTP 200, maintenance OFF.
- Migration `2026_10_14_170000_add_employee_reporting_manager` status **Ran [20]**.
- Read-only database smoke: `ORG_SCHEMA=PASS`; `EMPLOYEE_PROFILES=1`; `ASSIGNED_REPORTING_MANAGERS=0`. No original employee row reassigned.
- `ops:health --strict`: scheduler OK, queue worker OK, database queue, failed jobs 0.
- Pre-deploy private backup: `deploy_backups/qas_before_org_hierarchy_20261014_161023.sql` (7,057,561 bytes), saved only on server. Never check it into Git.
- Private one-time scripts cleaned after the smoke.
- Authenticated UI/RTL/mobile manual acceptance still required for the manager selector and reporting visibility.

## Explicitly NOT completed by this slice
- **W-ORG-01b** employee-scoped read/write/data/document authorization. Current global `workforce.manage` permission is NOT yet a verified per-employee approval scope. The new reporting relationship is preparatory, not evidence that existing leave approval endpoints are correctly scoped.
- Assigned leave reviewers, delegation/upward escalation and notifications (W-APP-03), document access (W-DOC-04), leave duration policies (W-LEAVE-02).
- Shift templates/unassigned vacancies, bulk assignment, live Ready/Not Ready operations, company-configured grace/break budgets/planned slots/reminders (W-SCHED-05).
- Historical or production payroll recomputation.

## Next execution boundary
W-ORG-01b: systematically inventory Workforce list/detail/mutation/document endpoints; introduce employee-scope policy (self, direct manager, manager chain, HR override) and test unauthorized cross-team reads/writes before allowing hierarchy-driven leave approvals. The scheduler/break policy architecture is separately documented at `docs/WORKFORCE_SCHEDULING_BREAK_OPERATIONS_ARCHITECTURE_2026-10-14.md`.
