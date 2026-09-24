# Workforce Attendance Rules & Corrections V1
Date: 2026-09-25
Working branch: `v42-clean-baseline`
Application source checkpoint: `b7fe238d`

## Goal

Turn raw clock-in/out records into an auditable attendance workflow that can later feed leave and payroll without silently rewriting historical time.

## Break tracking

Added `employee_attendance_breaks`.

Rules:
- break can start only while an attendance session is open;
- only one active break is allowed per attendance session;
- clock-out is blocked while a break remains open;
- break start/end are transaction-safe and audited;
- closed break duration contributes to total break minutes;
- net worked time = effective attendance duration - break minutes.

## Immutable recorded attendance

`employee_attendance_sessions.clock_in_at` and `clock_out_at` remain the recorded source history.

Approved corrections do **not** overwrite these columns.

The session now exposes:
- recorded clock-in/out;
- effective clock-in/out;
- gross effective duration;
- break minutes;
- net worked minutes.

This preserves a stable audit trail while giving downstream rules/payroll a corrected effective value.

## Correction workflow

Added `employee_attendance_corrections`.

A correction stores:
- attendance session and employee;
- previous effective clock-in/out snapshot;
- requested clock-in/out;
- employee reason;
- Pending / Approved / Rejected status;
- manager review notes;
- reviewer identity and timestamp.

Rules:
- employee can request correction only for their own attendance;
- open sessions cannot be corrected until clock-out;
- only one Pending correction is allowed per session at a time;
- requested clock-out must be later than requested clock-in;
- manager review is one-time; non-pending requests cannot be reviewed again;
- approval changes effective attendance through an overlay relation;
- rejection leaves effective attendance unchanged;
- request/approve/reject events are audited.

## Attendance rules

Added `AttendanceRulesService`.

V1 policy:
- start grace: 5 minutes;
- end grace: 5 minutes;
- start assessment: early / on time / late;
- end assessment: early departure / on time / after shift / session open;
- ended published shift with no attendance: absent;
- started published shift with no attendance yet: not clocked in;
- future published shift: upcoming;
- draft/cancelled shifts are not treated as attendance exceptions.

The rule output also carries gross minutes, break minutes and net worked minutes.

Policy values are deliberately centralized in the service so they can later move to merchant-configurable Workforce Settings without scattering magic numbers through controllers/views.

## UX

### My Time Clock
- current Clocked in / On break / Clocked out state;
- Start Break / End Break;
- open-break clock-out guard;
- recent Recorded vs Effective attendance;
- break minutes and Net worked;
- Pending / Corrected status;
- employee correction request entry point.

### Correction Request
- recorded time;
- current effective time when an earlier correction exists;
- requested corrected start/end;
- required reason;
- pending-request protection;
- clear message that submission does not change attendance until manager approval.

### Manager Attendance Review
- Recorded and Effective time side-by-side;
- break minutes;
- net worked;
- correction status;
- original notes preserved.

### Manager Correction Review
- live search/filter/pagination;
- Pending requests first;
- previous effective time vs requested time;
- required server-side permission boundaries;
- Approve / Reject with optional notes;
- full historical request rows retained.

### Work Schedule
Schedule rows now use `AttendanceRulesService` rather than duplicate inline calculations.

They show:
- effective actual attendance;
- corrected indicator;
- late / early start;
- early departure / after-shift;
- absence/not-clocked-in/upcoming states;
- break minutes and net worked.

## Permissions

No new permission slugs were introduced:
- `workforce.clock`: personal break controls and correction request;
- `workforce.view`: attendance/correction review pages;
- `workforce.manage`: approve/reject corrections.

## Regression coverage

`WorkforceAttendanceRulesTest` covers:
- break lifecycle;
- duplicate-break guard;
- open-break clock-out guard;
- break audit events;
- net worked calculation;
- correction ownership;
- closed-session requirement;
- one-pending-request rule;
- approval without raw-history rewrite;
- rejection preserving effective time;
- one-time review;
- activity audit;
- late / early-departure / absence assessment;
- live correction review and escaping;
- cashier denial from manager correction workspace.

## Release status

- Application source checkpoint: `b7fe238d`.
- Branch-head CI: pending independent verification.
- The prior Workforce Foundation + Shift Scheduling QAS recovery/upload was operator-confirmed after the MySQL index-name fix; exact authenticated feature acceptance remains deferred.
- This Attendance Rules & Corrections slice is not yet claimed on QAS.
- Production unchanged.

## Next workforce slice

**Leave Management V1**
- leave types;
- employee leave requests;
- manager approve/reject;
- overlap protection against published work shifts where policy requires;
- annual entitlement and opening balance foundation;
- pending / approved / used / available balances;
- append-only leave adjustments and audit history.

Then: **Payroll Foundation**.
