# W-ORG-01b — Team-scoped Workforce Authorization QAS Checkpoint
**Date:** 2026-10-14
**Branch:** `sec03-framework-upgrade`
**Application / CI / QAS SHA:** `a62ad480`
**Hardening CI:** https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38599716356 — **Green, 905 tests and 18,188 assertions**.
**QAS:** https://v42.tag-marketplace.com — app/static HTTP 200, maintenance OFF.
**Production:** unchanged.

## Implementation delivered

- `App\Services\Workforce\WorkforceAccessService` resolves a supervisor's reporting tree including direct/indirect reports; unauthorized/no-profile actors fail closed. Uses a recursive CTE and UNION distinct to prevent looping through corrupt legacy cycles.
- Separate explicit `workforce.scope.all` permission. Existing Operations Manager and Super Admin receive organization-wide workforce access for backward compatibility; new default `Team Supervisor` role has workforce.view/manage/clock but **no global scope and no compensation/payroll visibility**. Roles/permissions remain editable in the existing permissions administration.
- Employee Directory search, pagination, stats, departments and edit routes enforce reporting scope. Only organization admins may create employee profiles and change reporting structure; team supervisors may edit authorized reports, not self or other teams, and cannot reassign the manager by a forged request.
- Work Shifts list, counts, filters, staff choices, create/edit/cancel/update enforce authorized subject. Existing and requested employee are both checked on reassignment.
- Attendance sessions list, counters and void action restricted to authorized team; sensitive void action also refuses actor reviewing their own attendance.
- Attendance corrections and leave requests list/counts/review mutation endpoints scoped per employee. Reviewers cannot approve their own leave or correction regardless of administrator status.
- Leave adjustments and Leave Type settings (including organization-wide usage statistics) are organization-admin-only; Team Supervisor menu options are hidden but every endpoint still checks on the server.
- Salary and individual payroll access remain governed by separate `workforce.payroll.view/manage/self` permissions. These **are not implicitly granted** to a Team Supervisor.

## Verified

- Database pre-migration backup was created *before* deploy, server only: `deploy_backups/qas_before_workforce_scoped_access_20261014_164209.sql` (7,065,795 bytes, 0600). No employee/payroll data migrated or rewritten.
- Deployed by `bash deploy-qas.sh sec03-framework-upgrade`. Migration `2026_10_14_180000_add_workforce_scope_permissions` Ran [21].
- QAS read-only smoke:
  - `WORKFORCE_SCOPE_SCHEMA=PASS`
  - `TEAM_SUPERVISOR_ROLE=PRESENT`
  - `TEAM_GLOBAL_EMPLOYEE_ACCESS=NO_PASS`
  - `TEAM_SALARY_VISIBILITY=NO_PASS`
  - `OPERATIONS_ADMIN_GLOBAL_SCOPE=YES_PASS`
  - `EMPLOYEE_PROFILE_COUNT=1`
  - `SMOKE=PASS`
- `php artisan ops:health --strict` Scheduler OK; Queue Worker OK; zero failed jobs.
- CI scenarios include subordinate/grandchild visibility, non-team denial of list/update/shift/leave actions, organizational override, no salary route access, policy-settings access restriction and self-approval denial.
- Developer computer's CLI PHP is 8.2, incompatible with Laravel 13 project's PHP >=8.3 requirement. Syntax scans passed locally; Laravel integration tests executed in compatible CI.

## Limits / outstanding

- QAS currently has **one employee profile**, so multi-person authenticated browser acceptance (Team Supervisor vs employee vs Operations Manager vs payroll user) requires new isolated Demo test personas and reporting links. The CI integration tests are Green, but manual EN/AR/RTL/mobile QAS acceptance remains open.
- A direct manager still sees basic employee contact and HR profile fields within scope. Fine-grained classifications (personal IDs, medical, banking, salary-change data) need additional **field-level access and private-document policy** before W-DOC-04.
- Existing Finance Manager has organization-wide payroll permissions on payroll endpoints. Per-branch/tenant payroll collection scope and dual-control approvals remain **W-PAY-07a** (not solved by team scope); do not claim all HR/Payroll confidentiality complete.
- Shift templates/rosters, break policy, live Ready/Not Ready states, approvals delegation, notifications and configurable leave rules are separate planned slices. Documentation:
  - `docs/WORKFORCE_ENTERPRISE_E2E_AUDIT_2026-10-14.md`
  - `docs/WORKFORCE_SCHEDULING_BREAK_OPERATIONS_ARCHITECTURE_2026-10-14.md`
  - `docs/WORKFORCE_COMPENSATION_PAYROLL_ENTERPRISE_ROADMAP_2026-10-14.md`
- Payroll V1 currently handles manual bonuses/allowances/deductions/overtime; automated overtime/unpaid-leave proration/profit-sharing are not implemented.

## Next coherent slice

Build isolated Demo employee/manager/HR/Finance personas and perform authenticated QAS role-matrix acceptance first; then proceed to **W-LEAVE-02 policy engine** and **W-APP-03 manager-assigned approvals**, securing leave attachments at W-DOC-04. Add Payroll Field & Scope Security **W-PAY-07a** before company-wide compensation/bonus/profit-share rollouts. For every slice preserve DoD: inspect → implement → tests/CI → QAS isolated backup/verify → checkpoint.
