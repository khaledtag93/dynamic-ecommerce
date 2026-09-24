# Workforce Foundation V1
Date: 2026-09-25
Working branch: `v42-clean-baseline`
Application source checkpoint: `b0fc6d20`

## Goal

Create a durable workforce domain before adding schedules, leave, payroll, deductions, and leave balances. Authentication and permissions stay on `users` / roles; employment and attendance data live in dedicated workforce records.

## Data model

### `employee_profiles`
One workforce profile per authenticated staff user.

Core fields:
- linked `user_id` with a unique, restricted foreign key;
- unique normalized `employee_code`;
- job title and department;
- employment type: full time, part time, contractor;
- employment status: active, on leave, inactive, terminated;
- hire date / termination date;
- phone and notes.

The linked user account is not duplicated or replaced. Role and permission management remains independent.

### `employee_attendance_sessions`
Append-style attendance sessions containing:
- employee profile;
- clock-in timestamp;
- optional clock-out timestamp;
- source;
- clock-in / clock-out notes.

Attendance is deliberately separate from POS cash drawer shifts. A staff member can have a cash shift and an attendance session, but the two records represent different business concepts.

## Time clock safety

`AttendanceService` uses database transactions and row locking.

Guards:
- staff without an employee profile cannot clock in;
- only Active employees can start a new attendance session;
- a second open attendance session is rejected;
- clock-out requires an existing open session;
- manager cannot change an employee to On leave / Inactive / Terminated while attendance is still open;
- clock-in and clock-out actions create admin activity audit records.

No automatic clock-in or clock-out is tied to POS yet. That policy is deferred until schedule/attendance rules are designed.

## Permissions

New permissions:
- `workforce.view` — employee directory and attendance review;
- `workforce.manage` — create/update workforce profiles;
- `workforce.clock` — personal time clock.

Default role behavior:
- Super Admin: all workforce permissions through the existing all-permissions definition.
- Operations Manager: view + manage + clock.
- Cashier: clock only.
- Support Agent: clock only.
- Finance Manager: clock only.

Custom roles can receive the same permissions through the existing role/permission system.

## Admin UX

### Employee Directory
- live debounced search across name, email, employee code, job title, department, and phone;
- status, employment type, department and per-page filters;
- live pagination with URL/history and no-JavaScript GET fallback;
- KPI cards for total, active, on leave and currently clocked-in employees;
- employment and attendance state visible in the table;
- employee creation only links existing staff accounts;
- employee edit keeps the linked user identity stable.

### Attendance Review
- live search by employee identity;
- open / closed status filter;
- work-date filter;
- live pagination;
- KPI cards for open sessions, today's clock-ins, today's completed sessions, and employee profile count.

### My Time Clock
- current attendance state;
- clock-in or clock-out action;
- optional notes;
- latest 10 attendance sessions;
- safe setup message when a staff account has not yet been linked to an employee profile.

## POS integration

Manager Cash Shift Review now:
- eager-loads the linked workforce profile when available;
- shows employee code and department below cashier identity;
- can search cash shifts by employee code or department in addition to cashier name/email.

This integration is informational only. POS checkout is not blocked by attendance status in V1.

## Regression coverage

`WorkforceFoundationTest` covers:
- default permission scope by role;
- staff-only employee linking;
- one employee profile per user;
- employee code normalization;
- one-open-session clock-in guard;
- clock-out guard;
- activity audit entries;
- non-active employee clock-in prevention;
- manager protection against deactivating a clocked-in employee;
- live Employee Directory filtering and escaping;
- live Attendance Review filtering and escaping;
- cashier denial from manager workforce screens;
- safe missing-profile time clock state;
- POS cash shift workforce identity integration.

## Release status

- Application source checkpoint: `b0fc6d20`.
- Branch-head CI: pending independent verification at the time of this note.
- QAS: last verified application revision remains `0a08253`.
- Production: unchanged.
- Manual authenticated EN/AR desktop/mobile review is deferred to the consolidated QAS phase.

## Follow-on status

**Shift Scheduling V1 is now implemented in source** at `ff423286`; see `WORKFORCE_SHIFT_SCHEDULING_V1_2026-09-25.md`.

Next:
**Attendance Rules & Corrections V1 → leave balances/requests → payroll/deductions.**
