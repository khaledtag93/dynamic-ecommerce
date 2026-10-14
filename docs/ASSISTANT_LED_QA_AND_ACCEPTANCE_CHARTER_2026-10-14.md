# Flowra — Assistant-led QA & Acceptance Charter
**Adopted:** 2026-10-14 | **Scope:** all Flowra modules and environments | **Status:** standing execution agreement

## Purpose and ownership

Flowra development includes proving real end-to-end behavior, not only delivering code or obtaining a Green CI badge. **The assistant owns planning, building, running, fixing and reporting as much QA automation and QAS verification as the connected tools and safe access permit.** Do not routinely hand every page to the product owner for manual testing.

The product owner is primarily responsible for approving business policy, judging usability, prioritizing product trade-offs, and providing final acceptance where human observation or authorization is required. Owner input may also be needed for credentials, a blocked login/MFA, human-verifiable hardware, third-party payment confirmation, or regulatory/legal decisions. Never characterize unperformed user acceptance as complete.

Apply this agreement to **Admin, Storefront, POS, Workforce/HR, Payroll, catalog, purchasing, inventory, fulfillment, payments/refunds, analytics, SaaS/multi-tenant and future modules**. Preserve source compatibility with Laravel 13, Livewire 4, bilingual/RTL, responsive/mobile, and company-configurable policies.

## Required sequence per coherent delivery slice

1. **Inspect**: establish exact local/remote branch and commit, current implemented logic, known defects, business requirements and relevant permissions, including connected upstream/downstream modules.
2. **Design test matrix first**: identify happy path, invalid inputs, boundary conditions, unauthorized roles, direct-route attempts, concurrency/replay, failure/recovery, data integrity, localization/RTL, mobile view and cross-module impact. Use cases are explicit, not implied by test counts.
3. **Implement & unit/integration tests**: change the smallest coherent boundary; create regressions proving every meaningful behavior and business invariant. Check current app/runtime compatibility.
4. **CI**: push the exact source SHA, obtain actual Green checks, examine any failures, repair and rerun. A queued or partially passing run is NOT Green.
5. **QAS preflight**: confirm isolated QAS URL, environment, deployed SHA, migrations, queue/scheduler, storage paths and rollback. Back up before schema/data changes. Keep Production unchanged without an independent, authorized release.
6. **Isolated demo fixtures**: generate realistic but **synthetic, reversible and namespaced** personas/scenarios, never modify private real employee/customer/account data to simulate QA. Include roles and reporting lines, multi-branch/tenant boundaries, sensitive-data denials, and downstream outcomes. Capture IDs and cleanup strategy.
7. **Automated QAS functional & browser acceptance**: use available HTTP/API, database read-only checks and browser automation (e.g. Playwright when installed and authenticated) to follow real user journeys. Assert both screen outcome and persisted business state, with audit, notification and side-effect checks. For JS/live components, check updates, error handling and duplicate submissions, not merely status 200.
8. **Visual/accessibility/locale review**: where browser automation/screenshot inspection is available, check EN/AR, RTL/LTR, desktop/mobile, theme/white-label variants, keyboard/focus, empty/error/loading states and permission-dependent navigation. If unavailable, **declare the limitation** and request only the smallest necessary owner-supplied screenshot or targeted visual check.
9. **Repair and retest**: capture defects with severity, route, persona, steps, expected vs actual, evidence, linked commit. Rerun the affected scenario and all relevant regressions, CI and QAS smoke after each fix.
10. **Close with evidence**: publish source SHA, CI run/conclusion, QAS deployed SHA, migration/health status, scenarios run, PASS/FAIL/BLOCKED results, unresolved issues and next action in the repo. Production checks are separate.

### Responsibilities

| Activity | Default execution owner | Completion evidence |
| --- | --- | --- |
| Code review, business-rule mapping, unit/integration and authorization tests | Assistant | Tests/CI output, scenario matrix |
| QAS HTTP/API, read-only DB and controlled workflow smoke | Assistant via authorized tools | Exact QAS SHA, checks and expected/actual |
| QAS browser automation and screenshot review | Assistant when browser/tool/auth access is available | Automated steps, screenshots/traces/results |
| Test-data generation and cleanup | Assistant with isolated demo-only data safeguards | Fixture version, identities, cleanup proof |
| Financial/regulated live actions (real charge, payout, emails to real recipients) | Only with explicit user authorization and controlled test scope | Authorization + provider/sandbox evidence |
| Product-policy choices and final business/visual acceptance | Product owner with concise assistant findings | Decision/approval or accurately recorded pending status |
| External legal/payroll compliance signoff | Qualified domain reviewer | Explicit separate signoff |

## Cross-module minimum assertions

- **Permission/ownership:** every role and identity; list, detail, aggregates, URL and export access; own vs subordinate vs other-team vs cross-tenant; least privilege; never assume a hidden button protects a URL.
- **Financial correctness:** exact currency/cent math; discounts/taxes/refunds/returns/stock/COGS/profit provenance; idempotent payments; payroll/overtime/leave deductions/bonuses cannot bypass approval or mutate locked snapshots.
- **Operations:** employee absence and shift conflict, alert/escalation routing, leave and manager delegation, planned/actual breaks, queue retry/duplicates, stock reservations, cancellation/recovery.
- **Experience:** bilingual UI and messages, RTL, responsive touch, accessible controls, active/hover states, loader/empty/error handling, browser history and Livewire 4 preservation.
- **Configuration:** company/tenant/branch/role-specific setting applied consistently in UI, services, queue jobs, audit and calculations with effective dates. No hard-coded policy disguised as configurable.

## Status and truth rules

- **PLANNED:** design/backlog only.
- **SOURCE COMPLETE:** implementation and relevant local/CI tests exist; not equivalent to QAS verified.
- **CI GREEN:** all required checks pass on exact source SHA, but QAS acceptance remains separate.
- **QAS TECHNICALLY VERIFIED:** deployment/database/health/API smoke passed; no claim that browser UX or user journeys were exercised unless they were.
- **QAS FUNCTIONALLY ACCEPTED:** authenticated role and E2E scenarios have PASS evidence on exact deployed SHA; relevant visual checks performed or explicitly assigned.
- **CLOSED:** no blocking defects in scope, all applicable gates passed, owner policy decisions recorded. Reopen on a newly discovered defect or new requirement.
- **BLOCKED / PARTIALLY VERIFIED:** unavailable account/browser/provider/test-data or untested path. Record exactly what could not be tested; never invent PASS.

A Green CI, HTTP 200, a migration marked Ran, or a written test plan **alone is insufficient** to close a flow. Never declare "fully production-ready" or "everything tested" without end-to-end and release gate evidence.

## Practical tool/credential safety

- Confirm connected laptop/server access before relying on it; reconnect failures are blockers, not implicit success.
- Browser automation depends on installed automation runtime, authorized test accounts, access to QAS and ability to observe/render. Do not claim visual browser coverage until actual traces/screenshots exist.
- Keep secrets out of Git, terminal transcripts, fixtures, screenshots and evidence summaries; use approved secret handling. Do not bypass MFA/CAPTCHA or weaken Production security for testing.
- Isolate QAS storage/payment/notifications and suppress real external effects unless explicitly authorized.
- Before destructive/migration tests create backups and validate scope; cleanup only known demo fixtures, preserving all historical user and financial data.

## Artifact conventions for every slice

Maintain a compact test ledger:
`ID | Module | Persona/Locale/Viewport | Scenario | Expected | Actual | Status | Evidence/Defect`

Checkpoint format:
`Source SHA | CI URL + result | QAS SHA | Env/Backup | Passed scenarios | Failed/Blocked scenarios | Visual coverage | Owner signoff | Release gate | Next step`

Store the ledger/checkpoint under `docs/`; link or summarize it in `CURRENT_PHASE.md` and `PROJECT_MASTER_STATUS.md` when status changes. Do not substitute chat claims for repository evidence.

## Interaction and continuation convention

- Finish a completed work/checkpoint with a compact **next-action control** when supported, prefilled with the concrete next slice and any outstanding gates. Otherwise give the exact copy-ready next-step instruction.
- The button only sends a new turn after the user clicks; it never creates a background run or attests that a test has passed. Keep user decisions separate from routine assistant-led testing work.
- Use rich controls only when they make collaboration easier; prioritize test execution, exact paths/commits, readable code artifacts and clear evidence over decoration.

## Current first application of this charter

- W-ORG-01b Workforce scope application was deployed to QAS at `a62ad480` with Green CI (905 tests / 18,188 assertions), but **authenticated cross-persona browser QAS acceptance remains outstanding**, because QAS currently has only one employee profile.
- Next: create isolated demo reporting teams and role accounts, execute employee/manager/operations/payroll access matrix using actual authenticated QAS routes and role-specific UI, record evidence, fix regressions, then change the phase to CLOSED only if all required gates pass.
- Continue all other delivery slices under this standing charter, not as one-off manual testing requests.
