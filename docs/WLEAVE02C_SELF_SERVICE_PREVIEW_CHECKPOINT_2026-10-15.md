# Flowra W-LEAVE-02c — Self-Service Leave Policy and Balance Preview

**Date:** 2026-10-15
**Source baseline:** `6e6cb99b` · isolated GitHub CI [38643359924](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38643359924) **GREEN** (919 tests / 18,295 assertions)
**Branch:** `sec03-framework-upgrade`
**QAS:** Not accessed; W-ORG-01b QAS authenticated acceptance stays **OPEN / DEFERRED**. Production untouched.

## Slice scope

Employees can request an optional **policy and projected balance preview** from their My Leave page, before submitting a leave request. The preview returns the server-authoritative chargeable days and resolved start/end, available and already pending balances, current projected balance, and estimated projected balance after a hypothetical new pending request.

### Key invariants

- New `POST /admin/workforce/my-leave/preview` is under the existing authenticated `permission:workforce.clock` route group, CSRF protected, returns JSON `Cache-Control: private, no-store`; it does not select an employee from payload. Own `User::employeeProfile()` is the only employee scope.
- Preview uses the **exact same input validation and LeavePolicyService** as submission. Eligibility checks (missing/inactive profile, inactive type) and pending/approved overlap checks are shared with the request method. The preview never creates a leave request, reserves or adjusts balance, issues approval, changes existing rows or logs an action.
- `LeaveBalanceService::balance` already defines `available = entitlement + adjustments - approved` and `projected_available = available - pending`. The new estimate is `projected_after_request = projected_available - requested_days`, without persistently applying it; response numbers are rounded to two decimals. Dates are resolved from the requested policy for the selected year.
- A low current available balance is **informational**, not a new rejection rule: present system allows pending submission while only leave approval enforces sufficient available balance. Nothing changes historical requests, entitlement snapshots or payroll.
- Legacy leave types retain original inclusive calendar-day math. Fixed/flexible, explicit workdays/holidays and same-year checks use W-LEAVE-02b exactly as stored. Clients cannot supply calculated days or projected balances; server ignores untrusted IDs. Real submission and approval re-evaluate live state independently.
- The My Leave screen has a separate non-submitting Preview button, accessible status container and localized output. It clears stale estimates on type/date changes; errors are written via `textContent`, not injected HTML. Form submission continues to work without JavaScript.
- The preview does not expose employee IDs, role names, salary or other users' records. No QAS or Production dependencies.

### Validation

The new `tests/Feature/WorkforceLeavePolicyPreviewTest.php` adds **8 DB-backed feature tests** for:
1. Authentication and `workforce.clock` authorization.
2. Own-user scope regardless of forged employee/user IDs, historical approved/pending balance projection and no write.
3. Fixed-workday holiday calculation and matching actual submission.
4. Flexible leave rules and failed validation without writing requests.
5. Same overlap refusal as actual request.
6. Inactive employee, disabled leave type, missing profile.
7. Insufficient available balance warns without imposing a new submit rule.
8. Recomputed projected balances after separate pending state changes; preview does not reserve.

Local PHP lint/JSON parses/diff checks: PASS. **Full MySQL-backed tests and CI remain pending until new commit.** No local unidentified database or remote QAS executed.

### Source/CI closure gate

- Run isolated GitHub Actions `hardening-ci.yml` including migrations, full PHPUnit, auth tests and frontend/Blade builds.
- If CI fails: fix in this same slice and repeat until Green. Record precise SHA, CI run and tests/assertions only after verification.
- Source+CI closure does not mean a live QAS acceptance; W-ORG-01b remains deferred, and future W-LEAVE-02 options (policy-effective dates, company/branch calendars, half-days, accrual and balance reservation) require explicit policy decisions.
