# Workforce Leave Management V1
Date: 2026-09-25
Working branch: `v42-clean-baseline`
Application source checkpoint: `c6df3fc2`

## Goal

Add an auditable leave-management foundation that stays consistent with employee work schedules and can later feed payroll without embedding jurisdiction-specific assumptions into employee records.

## Data model

### `employee_leave_types`
Merchant-configurable leave policy:
- normalized unique code;
- English name and optional Arabic name;
- paid / unpaid flag;
- default annual entitlement days;
- active / inactive state;
- notes.

Leave types are retained rather than deleted so historical requests and adjustments keep their policy reference.

### `employee_leave_requests`
Each request stores:
- employee;
- leave type;
- start / end dates;
- requested days;
- optional reason;
- Pending / Approved / Rejected / Cancelled status;
- reviewer identity / notes / timestamp;
- employee cancellation timestamp.

### `employee_leave_adjustments`
Append-only balance history:
- employee;
- leave type;
- year;
- Opening balance / Carryover / Adjustment type;
- signed day delta;
- required reason;
- actor identity.

Balance history is not rewritten in place.

## Balance model

`LeaveBalanceService` calculates per employee / leave type / year:

- entitlement = leave type default annual entitlement;
- adjustments = append-only signed balance adjustments;
- used = Approved leave days;
- pending = Pending leave days;
- available = entitlement + adjustments - used;
- projected available = available - pending.

The current V1 uses the leave type's configured default entitlement as the base for the selected year. A future payroll/HR-hardening slice should snapshot entitlement policy by year if historical policy changes must not affect past-year reporting.

## Request rules

Employee leave requests:
- require a linked employee profile;
- require an active leave type;
- allow Active or On Leave employee status;
- count inclusive calendar days;
- cannot end before they start;
- cannot cross calendar years in V1;
- reject overlapping Pending or Approved requests for the same employee;
- remain Pending until manager review;
- can be cancelled by the employee only while Pending.

All request/cancel actions are audited.

## Approval rules

Manager approval:
- applies only to Pending requests;
- requires enough available leave balance;
- is blocked while any non-cancelled work shift overlaps the requested leave dates;
- requires the manager to cancel or move conflicting shifts first;
- is audited with reviewer identity and optional notes.

Manager rejection is also one-time, Pending-only, and audited.

## Schedule consistency

Leave and scheduling now protect each other in both directions:

1. Leave approval is blocked if a non-cancelled work shift overlaps.
2. Creating or updating a work shift is blocked if Approved leave overlaps.

The lock order was standardized around Employee before Leave Request / Work Shift rows to reduce concurrent lock-inversion risk.

## Balance adjustments

Managers can record Opening balance, Carryover, credit or debit adjustments.

Guards:
- zero-day adjustments are rejected;
- every adjustment requires a reason;
- an adjustment cannot make available balance negative;
- adjustments are append-only;
- every adjustment is audited.

The Balance Adjustment workspace also shows the recent adjustment history with employee, leave type, year, adjustment type, signed days, reason, actor and timestamp.

## UX

### My Leave
Employees can:
- view Available / Projected / Entitlement / Used / Pending balances for a selected year;
- submit a leave request;
- review their request history;
- cancel their own Pending requests.

### Leave Review
Managers get:
- live employee/reason search;
- status / leave-type / per-page filters;
- Pending / Currently on leave / Approved / Rejected KPIs;
- live pagination;
- visible schedule conflict counts;
- Approve / Reject actions with optional review notes.

### Leave Types
A separate policy workspace manages:
- code;
- English / Arabic name;
- paid/unpaid policy;
- default annual entitlement;
- active state;
- notes.

No jurisdiction-specific leave entitlement is auto-seeded.

### Balance Adjustment
A separate focused workspace handles:
- employee;
- leave type;
- year;
- adjustment type;
- signed days;
- reason;
- recent append-only adjustment history.

## Permissions

Existing workforce permission boundaries are reused:
- `workforce.clock`: My Leave, submit request, cancel own Pending request;
- `workforce.view`: Leave Review and Leave Types read access;
- `workforce.manage`: approve/reject, balance adjustment, leave type create/update.

## Regression coverage

`WorkforceLeaveManagementTest` covers:
- leave type creation, normalized code and audit;
- entitlement + adjustments + approved usage + pending projection;
- inclusive-day request calculation;
- overlapping request rejection;
- cross-year request rejection;
- schedule-conflict approval guard;
- Approved leave blocking later shift creation;
- insufficient-balance approval guard;
- append-only adjustment and audit;
- negative-balance adjustment protection;
- own-Pending cancellation only;
- live manager Leave Review, output escaping and permission isolation.

## Release status

- Application source checkpoint: `c6df3fc2`.
- Branch-head CI: pending independent verification; no workflow/status was visible when checked.
- The earlier Workforce Foundation + Shift Scheduling QAS recovery/upload was operator-confirmed after the MySQL index-name fix.
- Attendance Rules & Corrections V1 and Leave Management V1 are **not yet claimed on QAS**.
- Production remains unchanged.
- Authenticated EN/AR desktop/mobile acceptance remains deferred to the consolidated QAS review.

## Next workforce slice

**Payroll Foundation V1**
- employment compensation profile separate from general Employee Profile;
- salary / hourly pay basis;
- pay periods;
- attendance/leave input snapshot;
- overtime/allowance/bonus/deduction ledger;
- draft / approved / paid payroll-run lifecycle;
- immutable payroll calculation snapshot and audit trail;
- payslip foundation.

Tax, social-insurance and statutory formulas must remain configurable and are not to be activated from assumptions.
