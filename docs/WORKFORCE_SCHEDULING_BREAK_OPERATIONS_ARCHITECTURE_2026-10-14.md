# Flowra Workforce — Shift Planning, Live Operations and Break Policies
**Date:** 2026-10-14
**Phase:** architecture backlog with verified current-source gaps; NOT an implemented production feature.
**Baseline:** branch `sec03-framework-upgrade`, deployed QAS application `a83bc8f0`.

## Current baseline from the repository
- Each `employee_work_shifts` row **requires** `employee_profile_id`; a manager selects one employee per shift in `resources/views/admin/workforce/schedule/_form.blade.php`. No reusable `shift_templates` / demand slots / team assignments.
- `WorkShiftService` rejects overlapping shifts and approved leave and validates active employees. `mySchedule` displays *published* employee shifts only; drafts remain manager-only.
- `AttendanceService` exposes audited clock-in/out and manual break start/end. It blocks a second simultaneous break and clock-out during an active break, but no break allowance, planned slots, reminders, fairness/capacity or reason/status policy.
- `AttendanceRulesService::START_GRACE_MINUTES` and `END_GRACE_MINUTES` are **hardcoded at 5 minutes**; `assessShift` already distinguishes absent/not_clocked_in/upcoming/late/early/completed.
- Current Live status in My Time Clock is clocked in / on break / clocked out; **Ready/Not Ready** for operational work is not modeled independently.
- Existing Staff `Role` authorization controls page-level access. Organization reporting hierarchy and employee-scoped manager authorization are separate prerequisite workstreams.
- Existing Workforce local timezone is `Africa/Cairo` configured via `WORKFORCE_TIMEZONE` and stored timestamps UTC. Use UTC instants for events, local calendar rules and effective-dated policy for rostering.

## Domain boundaries and lifecycle

1. **Shift Template** (reusable, not assigned): name, branch/site, department/team/role coverage, local start and end wall-clock time, timezone, cross-midnight flag, recurrence / workdays, paid/unpaid break policy reference, min rest, grace tolerance, policy version. Example 13:00–21:00.
2. **Roster / Shift Instance**: dated occurrence from template; required headcount per role, open positions/vacancies, Draft → Published → Locked → Completed/Cancelled with explicit transitions and audited change notifications. Publish does not require knowing all assignees if unfilled capacity is allowed.
3. **Shift Assignment**: individual employee → instance, status proposed/confirmed/swapped/cancelled; unique no overlap, scoped manager/team authorization, approved leave conflict, holiday and local calendar, overtime/rest compliance, availability, hours capacity; recurring assignment generation has dry-run preview, idempotency and exception report.
4. **Attendance Session**: actual immutable clock in/out facts (UTC), corrections separately approved. Attendance MAY exist without a scheduled shift for businesses allowing unscheduled hours; policy controls allow/review/deny. Never silently turn attendance into schedule or edit recorded events.
5. **Operational Presence**: separate event stream Ready / Not Ready / On Break / Offline (heartbeat/last-seen is technical; physical presence requires explicit clock in). Reason codes (training, personal, coaching, lunch, system issue), allowed transitions, duration and permissions. Never conflate browser tab active, clocked in, and productive work.
6. **Planned Break Slot**: assignment-specific break schedule with type, local window, duration, paid/unpaid, flexibility/grace, optional capacity reservation. **Actual Break Session** remains separately timestamped for audit; correlate planned with actual, compute early/late/overrun/missed and authorized override.
7. **Operational Exception**: missed clock-in, late, missing break end, break overrun, no-show, sick-leave overlap, unavailable status threshold, roster unfilled, system/network outage; record severity, owner, employee response, resolution and audit.
8. **Notifications**: shift published/changed, reminder ahead of start, no-clock-in after grace, break approaching, break overrun, supervisor escalation, leave approved/cancelled leading to roster reconsideration. Use queue/outbox with idempotency keys, retries and delivery audit. Do not deliver private medical/ID details through generic notifications.

## Configurable policy UI — NOT only .env or hard-coded values

Expose settings through Admin → Workforce → Settings (not the White Label palette screen). White Label controls visual branding; Workforce Settings controls **business rules**. Rules are effective-dated, versioned, immutable once referenced by historical payroll snapshots; sensitive changes require roles and audit.
- Organization / Branch / Team: teams, locations, timezone, working calendars, holidays and local statutory rules.
- Templates and recurrence: local start/end, overnight shifts, desired coverage, assignment limits, auto-assignment vs manual, rest gaps and availability.
- Attendance: grace time, early/late threshold, max open shift, whether unscheduled clock-in allowed, exceptions and approval flows.
- Break policy: paid or unpaid, total minutes, number of breaks, each duration/time window, flexible vs fixed, maximum overrun and alerts, optional queue/capacity limit per department; overrides with reason, expiry and manager authorization.
- Operational states: allowed Ready/Not Ready codes, idle threshold if relevant, who changes statuses, auto-reset and reminders. Do not infer employee misconduct from a temporary absence, and do not collect invasive surveillance by default.
- Notifications & Escalation: timing of shift/break reminders, grace minutes, channels, weekend/quiet-hour policy, delegate when manager away, suppression and retries.
- Access & HR: roles plus reporting hierarchy, employee self-service, branch/team scope, document classes.
- Payroll: rounding, paid break treatment, overtime, holiday premium, reconciliation and lock rules.

**Policy resolution:** tenant/company default → branch/site → team/role → approved employee-specific exception. Explicit unset inherits. Effective start/end and version ID are attached to generated roster and payroll calculations. When tenant isolation is introduced, always enforce tenant ID on row models and queries.

## Concrete example for QA (not mandatory company policy)
- Shift Template 'Evening 13–21' has 8 hours wall-clock, 60 total minutes of breaks split as four 15-minute slots, e.g. 14:30, 16:00, 18:00, 19:30.
- Employee gets shift assignment only after publish; shows in My Schedule. Alert at shift start + configurable grace if no clock-in. If clocked in but Not Ready, show a distinct and explained state.
- Alert 5–10 minutes ahead of planned break, start/end stamped independently; 17-minute break in 15-minute slot -> +2 minutes variance and manager review according to policy, never erase 2 minutes.
- If break is paid, do not subtract it from paid hours even when out of productive Ready status. Gross attendance, break total, paid vs unpaid breaks, net worked and payroll payable time are distinct outputs.
- A manager on approved leave should have delegate/escalation defined separately from the staff member's operational status.

## Security, compliance, reliability gates
- Employee location/status telemetry minimized; explicit employee disclosure and role-bound visibility; reasonable retention, export and audit. Work laws, paid breaks and rest regulations vary by jurisdiction; product cannot choose universal legal defaults.
- Locked/approved schedules and payroll runs are immutable or change through tracked adjustments; race-safe assignment and break transitions.
- Incident/outage recovery: queue delayed, timezone/clock drift, missing worker events, offline kiosks; no duplicate alerts or phantom attendance.
- Store templates/slots/actuals separately, all times UTC with local timezone + policy version where needed. Guard DST gaps and repeated hours. Batch roster creation preview and rollback.
- EN/AR, RTL, mobile, accessibility, manual supervisor override and kiosk/POS role separation.
- No hard-coded legal penalties or automatic wage deductions for Not Ready; only policy-defined reviewed exceptions.

## Roadmap relationship / Delivery slices
- **W-ORG-01a** reporting chain foundation (create and validate manager links, audit, form, tests) comes first, then **W-ORG-01b** employee-scoped authorization of every Workforce read/write/download.
- **W-LEAVE-02** leave types/calendar/duration; **W-APP-03** assigned approvals and delegate routing; **W-DOC-04** secure multi-file attachments.
- **W-SCHED-05a** configurable Workforce Settings/policy versioning and shift templates, **05b** roster instances and bulk/individual assignment + publication notifications, **05c** staff-facing schedule and conflict-driven change flow, **05d** live presence and supervisor exceptions, **05e** break policy/planned slots/reminders/variance, **05f** team coverage reporting.
- **W-PAY-06** end-to-end leave/schedule/attendance/break-to-payroll reconciliation and locked snapshots.

For each coherent slice: inspect → implement → tests (including denial/edge conditions) → CI Green → DB backup and QAS smoke → checkpoint. Do not deploy new workflow behaviour to Production before controlled acceptance.
