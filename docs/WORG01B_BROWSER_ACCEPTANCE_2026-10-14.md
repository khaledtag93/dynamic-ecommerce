# W-ORG-01b — Browser Acceptance Evidence (Partial)
Date: 2026-10-14
Target: https://v42.tag-marketplace.com
Deployed application SHA verified beforehand: `a62ad480` (QAS health healthy).
Test runner: Playwright Core 1.56.1, local headless Google Chrome, installed in user-local Temp directory, **outside the project**. No change to Flowra npm dependencies or Production.

## Executed tests

| ID | Scenario | Viewport | Expected | Actual | Result |
| --- | --- | --- | --- | --- | --- |
| BROW-01 | Unauthenticated `/admin/workforce/employees` | Desktop 1366x800 | Login redirect, login form rendered | Final URL `/login`, form present, HTTP 200 | PASS |
| BROW-02 | Switch login to Arabic through `/locale/ar?redirect=%2Flogin` | Desktop 1366x800 | Login form, RTL direction | Password input present, `html dir=rtl`, HTTP 200 | PASS |
| BROW-03 | Unauthenticated `/admin/workforce/employees` | Mobile 390x844 | Login redirect, login form rendered | Final URL `/login`, form present, HTTP 200 | PASS |
| BROW-04 | Switch login to Arabic | Mobile 390x844 | Login form, RTL direction | Password input present, `html dir=rtl`, HTTP 200 | PASS |

Screenshots captured in local ignored `storage/logs/`: `worg01b_login_desktop.png`, `worg01b_login_ar_desktop.png`, `worg01b_login_mobile.png`, `worg01b_login_ar_mobile.png`, plus `worg01b_guest_*.png` from the committed portable re-run. Screenshots are evidence of page rendering only; their existence is not a visual usability signoff.

**Repeatable test source:** `tests/browser/workforce-guest-smoke.cjs`. Requires Node, Playwright Core installed outside the repository and Chrome; set `FLOWRA_PLAYWRIGHT_MODULE` to its module directory, optionally set `FLOWRA_CHROME_PATH` and `FLOWRA_QAS_URL`, then execute `node tests/browser/workforce-guest-smoke.cjs`. This script is read-only, guest-only, and was rerun successfully (4/4) on the deployed QAS revision.

## Blocked gates

| ID | Scenario | Status | Reason |
| --- | --- | --- | --- |
| ROLE-01 | Team Supervisor A sees direct/indirect reports, not Team B | BLOCKED | No isolated QAS demo personas created |
| ROLE-02 | Team B denies Team A direct detail/update URL | BLOCKED | Requires distinct authenticated accounts |
| ROLE-03 | Employee own time-clock/schedule/leave; denied manager lists | BLOCKED | Requires distinct authenticated accounts |
| ROLE-04 | Operations Manager global scope, policy settings | BLOCKED | Requires authenticated QAS role matrix |
| ROLE-05 | Finance salary access without Team Supervisor salary leakage | BLOCKED | Requires authenticated QAS roles and exact access checks |
| ROLE-06 | EN/AR, RTL, phone & desktop inside authenticated Workforce | BLOCKED | Same prerequisites |

A guarded database-backup script for QA-fixture creation was prepared but its attempted remote execution was **blocked by a tool safety check**. No QA seed script was executed. As a result **no QA account, employee profile, shift, attendance, leave request or compensation data was created or modified** in this acceptance run. All three unused temporary PHP fixture/backup scripts were subsequently removed from the QAS server after the blocked operation; no credentials were generated. Avoid bypassing the safety check: resolve the approved backup/test-data workflow before performing QAS fixture writes.

## How to finish acceptance

1. Verify approved method for protected QAS backup and its success, plus safe fixture creation/cleanup.
2. Provision seven QA-only, random-password, `@flowra-qa.invalid` accounts: Alpha Manager, Alpha Lead, Alpha Worker, Beta Manager, Beta Worker, Operations Manager and Finance Manager. Link two independent reporting trees and isolated QA-only shift/leave/attendance records.
3. Run the role matrix authenticated in Playwright in both languages and representative desktop/mobile viewports; test list, stats, live fragments, direct GET routes, write rejection, denied self approval, salary and HR field boundaries.
4. Verify no real data changed, clean only identified fixtures, and record cleanup proof.
5. Fix defects, rerun CI on changed code, re-deploy QAS if needed and rerun affected browser flows; then document PASS/FAIL evidence.
6. Mark W-ORG-01b CLOSED only when the applicable functional/browser/role gates and owner's policy acceptance are actually complete.

This is intentionally **PARTIAL** evidence and does not replace the previous 905-test CI Green state or claim full role-based acceptance.

## Read-only QAS backup safety recheck (2026-10-14, resumed session)

- **Scope:** no DB write, account provisioning, table migration, restoration, cleanup or Production operation was performed. All commands sent to QAS in this recheck were read-only; the local Playwright runner refreshed ignored screenshots and this evidence document was edited locally.
- **Source checkout:** `sec03-framework-upgrade` at `c6c98890` (documentation/browser-smoke source). **Deployed QAS application:** still `a62ad480` as confirmed with remote `git rev-parse --short HEAD`.
- **QAS health:** `php artisan ops:health --strict --no-ansi` returned Scheduler OK and Queue worker OK, database driver, 1 pending job, **0 failed jobs**. `php artisan migrate:status` shows the Workforce scope permissions migration `2026_10_14_180000_add_workforce_scope_permissions` Ran [21]. No schema or deploy action occurred.
- **Prior server-only baseline dump:** `deploy_backups/qas_before_workforce_scoped_access_20261014_164209.sql` exists, size **7,065,795 bytes**, permissions **0600**, modified **2026-10-14 16:42:09 UTC**. Read-only SHA-256 was computed; the dump contains SQL creation statements and an end-of-dump completion marker. Backup directory and `mysqldump` command are available. Dump contents and credentials were not printed or copied into Git.
- **Safety gate verdict: NOT CLEARED.** This is a *pre-deployment* backup, **not an independently confirmed fresh pre-fixture backup**. Integrity/format and file presence alone do not prove successful restoration. An approved current snapshot/dump, verification of restore on an isolated disposable target, an agreed rollback/cleanup process, and resolution of the prior remote tool safety block are still required. Do not bypass that safety check or seed personas yet.
- **Playwright rerun:** `node tests/browser/workforce-guest-smoke.cjs` via user-local Playwright Core on headless Chrome against `https://v42.tag-marketplace.com` exited **0**, with guest desktop/mobile and Arabic RTL desktop/mobile **4/4 PASS**. Screenshots were refreshed in the ignored local `storage/logs/` folder; screenshot existence is not visual signoff.
- **Code-level role regression:** GitHub Actions Hardening workflow run `38599716356`, application job `success` on `a62ad480`, recorded **905 tests / 18,188 assertions**. `tests/Feature/WorkforceScopedAccessTest.php` covers subtree versus outsider, direct-route mutation denial, leave/shift cross-team denial, self-approval denial and separate payroll permission. This run was inspected, not newly retriggered.
- **ROLE-01 through ROLE-06:** remain **BLOCKED**, not PASS; no approved synthetic QAS users exist for authenticated browser journeys. **W-ORG-01b remains OPEN.** No Production change.

**Next authorized action:** establish the approved, restorable QAS pre-fixture backup/rollback route without bypassing the safety restriction; only then provision namespaced synthetic personas, run authenticated role journeys and cleanup, and capture PASS/FAIL evidence before any closure decision.
