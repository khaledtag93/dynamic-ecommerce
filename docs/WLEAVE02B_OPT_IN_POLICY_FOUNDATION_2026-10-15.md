# Flowra W-LEAVE-02b — Opt-in Leave Policy Engine Foundation

Date: 2026-10-15
Branch: `sec03-framework-upgrade`
Source baseline: `b1c0b24a`
Prerequisite CI: [#38640323401](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38640323401) SUCCESS (prior W-LEAVE-02a revision).

## Scope (source only, no QAS or Production access)

W-ORG-01b seven-persona QAS acceptance remains **OPEN / DEFERRED**. Owner chose to resume the product roadmap. This independent bounded W-LEAVE-02b source slice implements configurable **per leave-type** request duration and explicit working-day/holiday counting; it does not invent company policy or change payroll/previous leave records.

### Default backwards-compatibility

- A reversible migration adds nullable policy metadata to `employee_leave_types`. **All existing types default to `duration_mode=legacy` and `counting_mode=calendar`.** Original inclusive calendar-day counting, required end date, leave overlap, balance-on-approval, and same-year restriction are preserved for those types.
- Fixed/flexible rules are **opt-in by a privileged Workforce Leave Type administrator**. Nothing backfills, updates, or recalculates old leave requests, adjustments, annual entitlements, or payroll snapshots.
- No policy is silently assumed: workday counting requires explicitly selected ISO weekdays (Monday=1…Sunday=7); holiday dates are explicit YYYY-MM-DD entries configured on the leave type. Without workday counting, holidays have no effect.
- Dates remain **local calendar dates** (not timestamps), with original same-year guard. No default weekends, holiday calendar, annual rollover, half-days, accrual, cap-on-request or employer-specific approval policy is introduced.

### New policy modes

- `legacy`: original inclusive calendar days; leave end date required.
- `fixed`: specify `fixed_duration_days` (1–365); server derives inclusive end date by counting chargeable calendar/workdays. Any client-supplied conflicting end date is rejected. On workday counting, a request may start on a non-working day; only explicitly eligible days count.
- `flexible`: specify `min_request_days` and `max_request_days` (1–365); server counts eligible calendar/workdays inclusively and rejects requests outside that charged-day interval. Zero-charge requests are rejected.
- Optional per-type `counting_mode=workdays` requires `working_weekdays` list; optional `holiday_dates` list excludes only declared holiday dates, and only for the configured workdays.
- The admin Leave Type editor exposes the policies and holiday list, with English/Arabic copy and precise server-side validation. The employee My Leave form preserves manual end-date entry for legacy/flexible and disables it for fixed duration. The server computes fixed end even without JavaScript.

### Tests and safety

- New `tests/Feature/WorkforceLeavePolicyEngineTest.php` has **8/8 PASS** on isolated MySQL, covering backward-compatible legacy counting, fixed-end tampering denial, configured Fri/weekend/holiday rollover, flexible min/max and empty-charge denial, non-working start, same-year boundary, malformed/incomplete admin configuration and preservation of historical requests.
- Existing Workforce Leave/Attendance/Scheduling/Payroll regression stayed GREEN. Full [GitHub CI #38642738390](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38642738390) on **`933f6393`**: **919 PHP tests PASS / 18,295 assertions**, including 8/8 new policy feature tests and 9/9 offline QA contract tests; MySQL schema migration, view/Blade checks and frontend stages SUCCESS.
- The local Windows PHP runtime has no known safe isolated MySQL testing database. **No local PHPUnit against an unknown database; no remote SSH or QAS/Production operation.**
- Explicit future boundaries: organization calendar/branch policy/approved holiday schedules, year-crossing, partial days, annual-balance reservations at request time, attachment requirements, policy-effective versions and historic snapshot semantics, W-APP-03 assigned approvals.
- **W-LEAVE-02b SOURCE + CI CLOSED** at `933f6393` / CI `#38642738390` GREEN. QAS authenticated UX and deployed SHA remain PENDING, W-ORG-01b stays OPEN/DEFERRED, and W-LEAVE-02 broader policy scope remains OPEN.

## Acceptance and next step

Commit sources on `sec03-framework-upgrade` once local syntax, JSON integrity and diff checks pass. Confirm isolated GitHub CI GREEN and log exact test/assertion counts. Do not deploy QAS/Production automatically. Later continue the next independent policy/approval slice with decisions explicitly configurable rather than hard-coded.
