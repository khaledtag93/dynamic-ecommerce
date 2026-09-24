# Workforce Shift Scheduling V1
Date: 2026-09-25
Working branch: `v42-clean-baseline`
Application source checkpoint: `ff423286`

## Goal

Add real employee work scheduling as a workforce concept separate from POS cash drawer shifts and attendance sessions.

## Data model

### `employee_work_shifts`
Each work shift stores:
- employee profile;
- scheduled start / end;
- status: Draft, Published, Cancelled;
- optional location;
- optional notes;
- published / cancelled timestamps;
- created-by / updated-by staff identity.

Cancelled shifts are retained for history rather than deleted.

## Safety rules

`WorkShiftService` owns scheduling mutations and uses database transactions.

Guards:
- only Active employees can receive a new work shift;
- overlapping non-cancelled shifts for the same employee are rejected;
- adjacent shifts are allowed when one ends exactly as the next starts;
- cancelled shifts cannot be edited;
- create/update/cancel actions are audited;
- manager schedule mutations remain protected by `workforce.manage`.

The overlap check is serialized by locking the employee before conflicting shift rows.

## Manager Schedule

The manager workspace supports:
- live employee/location search;
- shift-status filter;
- department filter;
- date range;
- per-page control;
- live pagination and URL/history fallback;
- Add / Edit / Cancel flow;
- KPI cards for today's published shifts, upcoming seven days, drafts and published history.

## Employee My Schedule

Staff with `workforce.clock` can open My Schedule.

Rules:
- only Published shifts appear;
- cancelled shifts disappear from employee planning but remain in manager history;
- draft shifts remain hidden until published;
- current and upcoming shifts show time, duration, location and notes;
- employee schedule is separate from the time clock and POS cash shifts.

## Schedule vs Actual foundation

Manager schedule rows compare each work shift against overlapping attendance sessions.

Current V1 comparison:
- planned start/end;
- actual clock-in/clock-out when an attendance session overlaps the shift;
- On time within a five-minute tolerance;
- early / late start variance;
- No attendance recorded for ended published shifts;
- Not started for future published shifts.

This is a comparison foundation only. It does not yet calculate absence penalties, overtime, breaks, or payroll.

## Permissions

No new permission slugs were required:
- `workforce.view` — manager schedule read;
- `workforce.manage` — create/update/cancel work shifts;
- `workforce.clock` — My Schedule.

## Regression coverage

`WorkforceSchedulingTest` covers:
- manager creation of published shifts;
- employee visibility of published shifts;
- Draft hidden until publish;
- overlapping shift rejection;
- adjacent shift allowance;
- non-active employee guard;
- cashier access to My Schedule but denial from manager schedule;
- cancelled historical record retention;
- cancelled shift edit protection;
- activity audit;
- live schedule filtering and escaping;
- Schedule-vs-Actual late-start comparison.

## Release status

- Application source checkpoint: `ff423286`.
- Branch-head CI: pending independent verification.
- QAS remains `0a08253`.
- Production unchanged.
- Consolidated EN/AR desktop/mobile QAS review remains deferred by owner.

## Next workforce slice

**Attendance Rules & Corrections V1**
- break sessions or break accounting;
- late / early / absence policy foundation;
- schedule-aware attendance status;
- manual correction request;
- manager approval/rejection;
- append-only correction audit rather than silent history edits.

Then:
**Leave types / requests / balances → Payroll foundation.**
