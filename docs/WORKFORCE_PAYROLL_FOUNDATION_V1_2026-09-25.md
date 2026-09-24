# Workforce Payroll Foundation V1
Date: 2026-09-25
Working branch: `v42-clean-baseline`
Application source checkpoint: `b51a93e6`

## Goal

Add a payroll foundation that consumes the existing Workforce attendance and leave domains without introducing unverified tax, social-insurance, statutory-deduction, leave-pay, overtime, or salary-proration assumptions.

The V1 priority is an auditable payroll ledger and lifecycle, not jurisdiction-specific payroll law.

## Data model

### `employee_compensations`

Current employee compensation profile:
- employee;
- pay basis: Salary per payroll period / Hourly;
- base rate;
- ISO-style 3-letter currency;
- effective from / optional effective to;
- overtime eligibility;
- optional reference overtime multiplier;
- notes.

There is one current compensation profile per employee.

Historical payroll does not depend on later edits to this table because each payroll entry stores an immutable compensation snapshot.

### `payroll_periods`

Stores:
- period name;
- start / end;
- optional pay date;
- Open / Closed status;
- notes.

Payroll periods cannot overlap.

### `payroll_runs`

One payroll run per period:
- Draft;
- Approved;
- Paid.

Audit fields:
- creator;
- approver;
- payer;
- approved timestamp;
- paid timestamp.

### `payroll_entries`

One employee entry per payroll run.

Each entry snapshots:
- employee code;
- employee name;
- pay basis;
- base rate;
- currency;
- attendance-session count;
- effective net attendance minutes;
- paid leave days;
- unpaid leave days;
- base pay;
- overtime;
- allowances;
- bonuses;
- deductions;
- gross pay;
- net pay;
- calculation metadata.

The JSON calculation snapshot also records:
- payroll period dates;
- compensation source ID;
- attendance-session IDs;
- approved leave request IDs/context;
- explicit calculation-policy flags.

### `payroll_adjustments`

Explicit pay components:
- Overtime;
- Allowance;
- Bonus;
- Deduction.

Each component stores:
- label;
- amount;
- optional quantity;
- optional rate;
- required reason;
- actor.

Adjustments are mutable only while the payroll run is Draft.

## Permissions

New scoped permissions:
- `workforce.payroll.self`
- `workforce.payroll.view`
- `workforce.payroll.manage`

Default access:
- Super Admin: all payroll permissions through the global permission set.
- Finance Manager: self + view + manage.
- Operations Manager: self only for personal payslips; no payroll-view/manage access by default.
- Cashier: self only.
- Support Agent: self only.

This deliberately separates sensitive payroll access from ordinary `workforce.manage`.

## Compensation semantics

### Salary
`base_rate` = fixed base amount for one complete payroll period.

V1 does **not** auto-prorate partial-period salary.

If Salary compensation, hire date, termination date, or compensation effective dates cover only part of the payroll period, generation is rejected rather than silently paying the full salary.

### Hourly
`base_rate` = amount per effective net attendance hour.

Base pay:
`net_work_minutes / 60 × base_rate`.

Effective attendance already includes:
- approved attendance corrections;
- break subtraction.

## Stable-input generation gate

Payroll cannot generate until the payroll period has ended.

Only employees with effective compensation are considered.

For included employees, generation is blocked while the period contains:
- open attendance sessions;
- open attendance breaks;
- Pending attendance corrections;
- Pending leave requests.

Unrelated employees without effective compensation do not block the run.

## Leave handling

Approved leave is snapshotted as:
- paid leave days;
- unpaid leave days.

V1 does not automatically:
- add pay for paid leave;
- deduct pay for unpaid leave.

Those monetary rules remain explicit pay components until merchant/jurisdiction policy is configured.

## Overtime handling

Compensation can flag an employee as overtime eligible and can store a reference overtime multiplier.

V1 does not auto-calculate overtime.

Overtime pay is an explicit payroll component and is rejected when the payroll snapshot says the employee is not overtime eligible.

## Payroll calculation

Base pay:
- Salary: fixed payroll-period base amount.
- Hourly: effective net attendance hours × hourly rate.

Credits:
- overtime;
- allowances;
- bonuses.

Gross:
`base + overtime + allowances + bonuses`.

Net:
`gross - deductions`.

A deduction that would make Net negative is rejected and rolled back.

Amounts from different currencies are never merged into one summary total. Payroll Run totals are grouped by currency.

## Lifecycle

### Draft
- generated from stable inputs;
- snapshots employee/compensation/attendance/leave inputs;
- payroll components can be added or removed;
- totals recalculate transactionally.

### Approved
- entries/components become immutable;
- approver/timestamp recorded;
- payroll period closes.

### Paid
- only an Approved run can become Paid;
- payer/timestamp recorded;
- lifecycle cannot regress through the V1 UI/service.

## Payslips

Manager Payroll view:
- run overview;
- grouped totals by currency;
- per-employee entry;
- component detail;
- frozen input snapshot.

Employee My Payslips:
- Draft payroll never appears;
- only Approved/Paid entries are visible;
- ownership is enforced server-side;
- one employee cannot open another employee's payslip;
- printable payslip view included.

Payslips explicitly state that statutory tax/social-insurance formulas are not automatically applied in V1.

## Audit

Audited actions include:
- compensation create/update;
- payroll period create;
- payroll run generate;
- payroll adjustment add/remove;
- payroll approve;
- payroll paid.

## Locking and immutability

Payroll mutation order is standardized around:
**Run → Entry → Adjustment**

Payroll generation locks:
- payroll period;
- effective compensation rows.

Historical entries remain unchanged after compensation edits.

## Regression coverage

`WorkforcePayrollTest` covers:
- default payroll permission scopes;
- compensation save/currency normalization/audit;
- period overlap rejection;
- hourly calculation using approved corrections and breaks;
- paid-leave snapshot without automatic monetization;
- salary fixed-period amount;
- compensation snapshot immutability after later profile change;
- pending correction/leave generation blockers;
- unfinished-period generation blocker;
- partial-period salary proration guard;
- overtime/allowance/bonus/deduction recalculation;
- negative-net protection;
- Draft → Approved → Paid lifecycle;
- post-approval adjustment immutability;
- audit events;
- self-payslip isolation;
- Draft payslip hiding.

Existing `WorkforceBladeIntegrityTest` recursively covers Payroll Blade files too.

## Known V1 boundaries

Not automatic in V1:
- statutory tax;
- social insurance;
- jurisdiction-specific payroll deductions;
- salary proration;
- paid-leave monetization;
- unpaid-leave deductions;
- automatic overtime calculation;
- payroll bank/payment-file export;
- government filing;
- multi-entity payroll.

These require explicit operating-jurisdiction and merchant policy.

## Release status

- Application source checkpoint: `b51a93e6`.
- No GitHub workflow run/status was visible for this head when checked.
- CI remains Pending.
- Current QAS Workforce state is not accepted: the latest reported QAS state had generic 500 pages after an interrupted migration/deploy and required the FK-name + Blade-namespace recovery source to be redeployed.
- Payroll Foundation V1 is not claimed on QAS.
- Production unchanged.

## Next step

**Workforce QAS integration and consolidated validation**

Deploy the complete repaired Workforce source, run pending migrations, compile views/routes, and validate:
- Employees;
- Schedule;
- Attendance;
- Corrections;
- Leave;
- Compensation;
- Payroll;
- My Schedule;
- My Leave;
- My Time Clock;
- My Payslips;
- EN/AR;
- desktop/mobile;
- permission isolation.

After Workforce integration is stable, return to the high-priority commerce gaps before adding Payroll V2 statutory depth.
