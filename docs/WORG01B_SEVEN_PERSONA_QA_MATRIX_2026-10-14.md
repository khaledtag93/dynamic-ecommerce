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

## Continuation: controlled connection preflight and evidence

Date: 2026-10-14. Starting checkpoint: `e38215a9`.

| Gate | Check | Evidence | Result |
| --- | --- | --- | --- |
| Source integrity | Local `sec03-framework-upgrade` at `e38215a9`, clean and tracking `origin` | Local Git status, head and history | PASS |
| CI for current checkpoint | Hardening run `38607965110`: completed / success | GitHub Actions result | PASS |
| Authorized local execution device | Desktop Commander `DESKTOP-KOG45HP` online | Connected device status | PASS |
| Authorized QAS SSH channel | One standard read-only SSH connection preflight, remote command `true` | Remote tool returned `This tool call was blocked by OpenAI's safety checks` | BLOCKED |
| Pre-fixture backup | Cannot create or inspect a fresh dump through the blocked channel | Prior `7,065,795`-byte dump is pre-deploy, not a restore-tested pre-fixture snapshot | BLOCKED |
| Isolated restore rehearsal | No approved access / current dump | Not executed | BLOCKED |
| QAS role personas | Seven test identities | None created on QAS | BLOCKED |
| Authenticated QAS browser role matrix | ROLE-01…ROLE-06 | Not executed; no test accounts available | BLOCKED |
| Public guest browser smoke | Chrome/Playwright desktop + mobile guest redirect and Arabic RTL | Repeat of `tests/browser/workforce-guest-smoke.cjs`, 4/4 cases PASS; screenshots updated locally | PASS (guest-only) |
| Fixture cleanup | Synthetic QAS records inserted by this pass | Zero records inserted, therefore cleanup **not applicable** | N/A |

**Safety stop applied:** no second route/tool/credential workaround was attempted after the blocked SSH preflight. No QAS database writes, accounts, fixture IDs, migrations, deploy or Production change occurred. The previous CI `907 passed / 18,230 assertions` remains valid *only for isolated automated tests*, not authenticated QAS acceptance.

**Closure decision:** **DO NOT CLOSE W-ORG-01b**. Resume on an expressly approved QAS access path, confirm app/database isolation and the owner's disposable-QAS policy, then execute the seven authenticated personas and record role-by-role PASS/FAIL and targeted fixture cleanup. An isolated restore drill is tracked as a **separate future protected-data readiness gate**, not falsely marked completed.

## Authenticated browser runner prepared (source-only)

- Source: `tests/browser/workforce-authenticated-readonly.cjs`. This locks the QAS origin to `https://v42.tag-marketplace.com`. No account provisioning, workforce approvals, payroll changes or other mutation tests are performed; login creates a normal session and business checks are GET-only.
- Once seven isolated QA accounts exist, cover seven roles x EN/AR x desktop/mobile. Check locale direction, HTTP 200/403, cross-team directory and attendance visibility, employee self-service, Finance payroll vs Operations permissions, and direct GET denial to other-team employee edit routes. State-changing denial, visual signoff and QAS cleanup are separate remaining gates.
- Require a PRIVATE absolute `FLOWRA_QA_PERSONAS_FILE` path **outside this repo**. JSON must contain `environment: "qas"`, the fixed `base_url`, `fixture_namespace: "QA-WORG01B"`, and seven `personas` objects. For the original protected-data policy it uses `backup_verified: true` plus `isolated_restore_verified: true`. Under the owner's new disposable QAS policy (2026-10-14), it may instead use `qa_data_policy: "disposable-qas"`, `owner_accepts_qa_data_loss: true`, and `qas_app_database_confirmed: true` (after independent read-only app-DB verification). Neither mode bypasses administrative connection safety restrictions. Each persona needs `email` at `@flowra-qa.invalid`, a strong `password`, its exact `QA-` employee code, and positive numeric `profile_id` from the isolated fixture manifest. Never commit credentials; booleans are declarations and do not independently prove a restore.
- Once the selected approved QAS risk lane, exact QAS database match, authorized admin connection and private synthetic credentials are established, run `node tests/browser/workforce-authenticated-readonly.cjs --preflight` for a no-network gate check. Configure `FLOWRA_PLAYWRIGHT_MODULE` for the separately installed Playwright Core package and run the script without `--preflight` for authenticated read-only checks. The credential-free JSON results are written to ignored `storage/logs/worg01b-auth-readonly-results.json`. Screenshots, cookies and credentials are NOT recorded.
- Local `node --check` PASS; no-credential preflight returned explicit `BLOCKED` with exit code 2 before network access. This expected failure is **not** authenticated acceptance. CI has a safety preflight/syntax guard for this script.
- Earlier documentation checkpoint CI [38608979916](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38608979916) was Green. The new runner needs its own source SHA CI before claiming code quality on that revision.
