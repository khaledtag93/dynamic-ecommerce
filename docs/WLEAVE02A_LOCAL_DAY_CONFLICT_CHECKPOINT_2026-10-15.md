# Flowra W-LEAVE-02a — Local Calendar Shift/Leave Conflict Integrity

Date: 2026-10-15
Branch: `sec03-framework-upgrade`
Base checkpoint: `bd31a74e` (CI [38637806904](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38637806904) GREEN)

## Scope and intent

Resume the Flowra product roadmap while **W-ORG-01b authenticated QAS acceptance remains OPEN / BLOCKED** (remote tooling safety). This slice addresses the documented **W-11 date-boundary defect** in `docs/WORKFORCE_ENTERPRISE_E2E_AUDIT_2026-10-14.md` without inventing leave-policy rules or requiring any QAS/Production access.

Business invariant: an inclusive employee leave date range is a set of **local workforce calendar days**. Work shifts are UTC instants represented by a half-open interval `[starts_at, ends_at)`. A shift conflicts with approved leave iff it overlaps that local calendar window converted to UTC. An end exactly at the next local midnight does not consume that following date.

## Implementation

- `LeaveRequestService::approve` uses `WorkforceClock::localDayUtcBounds` to convert first and last leave days to the UTC half-open range. The SQL check remains `starts_at < endExclusiveUtc` and `ends_at > startUtc`, and cancelled shifts stay excluded. The approval, balance and audit state machines otherwise remain unchanged.
- `WorkShiftService::guardNoApprovedLeave` consistently interprets stored shift timestamps as UTC before converting their inclusive covered local dates with `WorkforceClock::timezone()`. Subtract one microsecond from exclusive shift end to avoid counting the next calendar date at exactly midnight. Keep scoped overlap and status gates unchanged.
- No new DB schema or new leave type columns, no change to annual leave entitlements, workweek rules, holidays, paid/unpaid policies, or historical approved leave/payroll snapshots.

## Regression evidence and expected examples

New `tests/Feature/WorkforceLeaveLocalDayBoundariesTest.php` contains four DB-backed tests using synthetic test-only accounts:
1. **Cairo previous UTC day:** UTC Oct 13 22:00 is Cairo Oct 14 01:00 and blocks Oct 14 approval. UTC Oct 14 22:00 belongs to Cairo Oct 15 and does not block Oct 14 approval.
2. **Exact midnight edges:** Cairo Oct 14 bounds are UTC Oct 13 21:00 to Oct 14 21:00; a shift ending at the start and another starting at the end are accepted, while a shift within the bounds is rejected.
3. **Spring DST:** New York Mar 8, 2026 has a 23-hour local day, UTC 05:00 to next day UTC 04:00. A late local-day shift is denied, but following-day shift is allowed.
4. **Fall DST:** New York Nov 1, 2026 has a 25-hour local day, UTC 04:00 to next day UTC 05:00. A late shift blocks approval; cancellation allows the approval.

The local Windows PHP environment exposes only the `mysql` PDO driver (no isolated local SQLite). The three changed PHP files pass `php -l` and `git diff --check` locally. GitHub Actions [#38639696072](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38639696072) on **`dbb3edfb`** completed **SUCCESS** against isolated MySQL: **911 tests / 18,243 assertions**, including all **4/4** new `WorkforceLeaveLocalDayBoundariesTest` cases and **9/9** offline QA contract tests. No unknown local/QAS DB used.

## Status and remaining roadmap work

- **W-LEAVE-02a source changes:** **SOURCE + CI CLOSED** on `dbb3edfb` / CI `#38639696072` Green. Authorized QAS acceptance remains pending (not claimed).
- **W-LEAVE-02 overall:** OPEN. The remainder (fixed/flexible duration policies, holiday and workday calendars, partial-day decisions, balance reservation policies, cross-year semantics) requires explicit product policy design before behavioral changes. Follow with W-APP-03 approval routing/OOO.
- **W-ORG-01b:** OPEN / DEFERRED for authenticated QAS acceptance, not silently marked CLOSED.
- **Production:** unchanged. QAS: unchanged. No SSH or alternate transport attempted.
