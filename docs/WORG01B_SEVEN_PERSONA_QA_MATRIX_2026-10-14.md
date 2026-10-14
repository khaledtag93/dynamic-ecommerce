# W-ORG-01b — Seven-Persona Authorization Matrix and QAS Gate

Date: 2026-10-14
Source branch: `sec03-framework-upgrade`
CI-target source: `f0392366`
Last directly verified QAS application: `a62ad480` (different from test-only source HEAD).

## Scope and owner decision

- The owner explicitly permits **synthetic, disposable QAS test data** for the current testing period, with the expectation that QAS will eventually be reset before clean onboarding.
- This is **not** authorization to erase existing user/customer/employee data indiscriminately, weaken Production controls, bypass connection safety checks, or publish QAS credentials.
- The direct QAS SSH action in this resumed session was **blocked by tool safety checks**. No alternate access attempt, QAS fixture creation, restore, data mutation, deployment or Production change was carried out.
- The prior server-only baseline dump was present and recognizable but is not a verified, restorable, current *pre-fixture* snapshot. **Backup Safety Gate remains BLOCKED.**

## Seven isolated test identities (never use real employee data)

| Key | Role | Hierarchy | Expected Workforce scope | Payroll |
| --- | --- | --- | --- | --- |
| Alpha Manager | Team Supervisor | Root A | Self + Alpha Lead + Alpha Worker | No staff payroll |
| Alpha Lead | Team Supervisor | Reports to Alpha Manager | Self + Alpha Worker | No staff payroll |
| Alpha Worker | Cashier/employee | Reports to Alpha Lead | Self service only | Own payslips only |
| Beta Manager | Team Supervisor | Root B | Self + Beta Worker | No staff payroll |
| Beta Worker | Cashier/employee | Reports to Beta Manager | Self service only | Own payslips only |
| Operations Manager | Operations Manager | Separate root | Organization-wide Workforce | No staff payroll |
| Finance Manager | Finance Manager | Separate root | Payroll granted, Workforce directory denied | Staff payroll access |

For actual QAS seeding, isolate accounts with the reserved test email domain `@flowra-qa.invalid`, namespaced `QA-` employee identifiers, random strong passwords stored outside Git, and a deletion manifest containing exact inserted IDs. Do not rely on bulk-deleting generic employee or account records.

## Test evidence already implemented

- `tests/Feature/WorkforceDemoPersonaMatrixTest.php` creates all seven synthetic identities using Laravel's `RefreshDatabase` testing isolation. This test runs against the CI test database, **not QAS**.
- It checks both reporting trees, direct/indirect report visibility, team-specific attendance, employee self-service, unauthorized directory/payroll/leave policy access, organization-wide Operations access, Finance payroll access, cross-team edit denial, and self leave approval denial.
- A local `php -l` syntax check passed. **Hardening CI Green on source `f0392366`**: [run 38607285162](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38607285162), application job success, **907 passed / 18,230 assertions**. The `WorkforceDemoPersonaMatrixTest` suite specifically PASS (2 tests). These are isolated CI PHPUnit results, **not** authenticated QAS acceptance.

## Mandatory steps before QAS functional closure

1. Restore an approved direct QAS connection through the standard authorized tool pathway; do not bypass any safety restriction.
2. Confirm no current live/legitimate QAS data will be overwritten. Create a current **pre-fixture** backup using an approved database-aware method and verify a sample restoration on a fully isolated, disposable database (no writes to QAS during restore proof).
3. Record dump timestamp/size/checksum in private evidence, database version compatibility, rollback command/procedure and cleanup IDs. Never commit dump, password or customer data.
4. Seed only the seven `QA-` identities, their explicit reporting links, representative shifts/attendance/leave records and relevant compensation demo rows. Keep outbound emails/payments/notifications disabled or sandboxed for fixtures.
5. Execute authenticated Playwright 7-role matrix on QAS EN/AR/RTL desktop and mobile; validate both allowed pages and 403 direct GET/mutation routes, list totals and cross-team data isolation.
6. Record exact deployment SHA, tests with expected vs actual, screenshots/traces without sensitive data, side effects, and SQL read-only row-count proof. Clean up using the saved inserted-ID manifest only.
7. Mark W-ORG-01b CLOSED only once all applicable role/business acceptance gates are actually proven and agreed.

**Status:** QAS authenticated role acceptance OPEN/BLOCKED. Production unchanged.
