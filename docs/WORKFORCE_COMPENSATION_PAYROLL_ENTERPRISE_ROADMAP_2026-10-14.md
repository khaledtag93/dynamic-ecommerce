# Flowra Workforce — Compensation, Payroll & Incentives (Enterprise Scope)
**Date:** 2026-10-14 | **Status:** design/audit backlog, NOT fully implemented or approved for Production.
**Current stack:** Laravel 13 / Livewire 4; QAS application baseline `bedb9fc0` at start of work.

## Ground truth: what exists in code

- `EmployeeCompensation` supports hourly or salary basis, currency, effective_from/effective_to, overtime eligibility and multiplier. `CompensationService` refuses overlapping compensation periods.
- `PayrollPeriod/Run/Entry` support drafting, approval, paid states, historical rate snapshots and employee self-service payslips with explicit self ownership and approved/paid only.
- `PayrollAdjustment` supports **manual** overtime, allowance, bonus and deduction lines with reason and quantity/rate, audited calculation in exact cents; non-Draft modifications are prohibited. Net cannot go negative.
- `PayrollService` explicitly sets `overtime_auto_calculation=false`, `unpaid_leave_auto_deduction=false`, `salary_proration_auto_calculation=false`. Do not claim rules already automated.
- There is **no enterprise profit-sharing/commission pool allocation and settlement module**. Compensation/payroll permissions exist separately from Workforce page permissions; stronger data-scope and duty separation are necessary.

## Target domain modules

1. **Compensation Contracts**: effective-dated base salary/hourly rate, currency, grade/band, branch/job/union policy inheritance, seasonal/part-time/contractor variants, raise/promotion history, approval and reason, freeze on approved payroll.
2. **Pay Elements Catalog**: configurable earning/deduction classifications. Base, shift differential, overtime, holiday premium, on-call, commission, sales incentive, bonus, allowance, reimbursement, advance recovery, voluntary deduction, mandatory statutory deduction, benefit/employer contribution. Specify gross/taxable/pensionable inclusion per jurisdiction separately; do not infer from label.
3. **Time & Rules**: overtime only from approved eligible attendance and configured threshold (per day/week/period), explicit worked hours vs presence vs paid breaks, rest policies, rounding and grace, legal limits and approval override, no wage penalties from 'Not Ready' by default.
4. **Absences/Leave**: paid, unpaid, partial-pay and protected absence effects; local workday calendar, shift coverage, negative leave balance policy, entitlements/carryover and adjustments.
5. **Bonuses/Incentives**: recurring/fixed/spontaneous, KPI/goal-based or manager-awarded; effective campaign, eligible cohort, caps, currency, evidence, calculation preview, multi-step approval, idempotent payout.
6. **Commissions**: credit for order vs collected funds vs fulfilled order based on company policy; sale attribution and splits, returns/refunds/chargebacks clawback policy, exclusions, periodic settlement and reconciliation.
7. **Profit Sharing**: revenue is not profit. Pool sourced from **approved realized net profit after returns, COGS and eligible expenses**, with explicit period/branch/tenant, allocation basis (hours, points, role, proportional salary or fixed percentages), eligibility, caps, reserve/holdback, late returns/restatement policy, multi-approver authorization, versioned settlement and audit. Never auto-payout from unreviewed Gross Sales charts.
8. **Deductions**: lawful/statutory taxes, insurance, retirement, loan/advance, asset recovery, garnishment, documented adjustments; require employee notice/consent where applicable, thresholds/maximums and appeal/review, never automatic disciplinary deductions without legal validation.
9. **Payroll Pipeline**: timesheet review → approved attendance/overtime → leave & absences → compensation snapshot → pay elements → calculation preview → variance/reconciliation → finance/HR dual-control approval → immutable run → export/disbursement → receipts/payslips → adjustments via new corrective run, never mutate paid history.
10. **Localization and SaaS**: **Payroll Policy Settings** per jurisdiction/tenant/branch with effective version and non-overlapping overrides, local tax plugins/adapters (do not hard-code one country's legal assumptions), exchange rate policy, bank transfer export, currency-specific rounding, accessibility, Arabic/English, and tenant-scoped data isolation.

## Permissions / privacy design

- Employee: own approved payslip and own accepted compensation disclosure, **not others' compensation or salary inputs**.
- Team Supervisor: team scheduling/attendance/approved leave workflow and approval justification; can see labor *coverage*, not automatic access to direct reports' salary, tax ID or medical evidence.
- HR Compensation Manager: compensation bands, change proposals, entitlement settings, employee legal/classification details on granted scope; no automatic bank payment authority.
- Payroll Specialist: prepare payroll and review line math; only assigned payroll group/branch; never approve their own payroll batch or alter a paid run.
- Finance Approver: approve totals/reconciliation and authorize payouts; limited need-to-know personal/medical detail; full approvals logged.
- Admin Security / Auditor: grant roles, read audit metadata under policy, not unrestricted access to sensitive pay values by being a site designer.
- All pay list queries/exports, run views, adjustment mutation endpoints and APIs must enforce `tenant + employment scope + action + field classification`, not CSS hiding.

## Dynamic Admin navigation / configuration

**Admin → Workforce → Settings →** Organization, Working Calendar, Shift Templates, Attendance, Breaks, Leave, Approvals & Delegation, Notifications, Payroll Rules, Pay Elements, Incentive Policies, Commission & Profit Share, Document Visibility & Retention, Integrations. Do not mix business settings with White Label branding.
- Each setting: owner scope (tenant → branch → team → employee override), effective date/timezone, immutable policy version, 'preview impact', validation with conflict detection, approvals, change audit, policy inheritance, safe defaults.
- Policy changes never silently rewrite already approved/paid payroll periods. Use controlled backfill and reconciliation.

## Priority execution roadmap after W-ORG-01b

- **W-PAY-07a Security & responsibility separation:** employee payslips, role-based field visibility, payroll collection scope, reasoned change logs and approval-of-own changes denial.
- **W-PAY-07b Compensation evolution:** effective raises, promotion bands, salary history, proration policy, HR approvals, retroactive adjustment handling.
- **W-PAY-07c Rules/config engine:** pay element catalogue, configured overtime by day/week & shift, paid-break treatment, holiday and leave premiums, unit tests of exact cents and lawful caps.
- **W-PAY-07d Variable pay:** bonus, commission, allocation, approvals, refunds/returns clawbacks, append-only financial source references, multi-currency reconciliation.
- **W-PAY-07e Profit sharing:** business profit authority, cost allocations/overheads, holdback/revisions, fair eligibility, payout ledger, scenario tests.
- **W-PAY-07f Compliance & launch:** payroll reports, bank export under credentials vault, sandbox payroll reconciliation, local counsel/payroll-provider legal review, EN/AR and multi-branch UAT.

## Research benchmark

- [ADP Payroll Processing](https://www.adp.com/resources/articles-and-insights/articles/p/payroll-processing.aspx) describes overtime, paid/unpaid break definitions and statutory deductions as explicit payroll requirements.
- [Deputy location timesheet settings](https://help.deputy.com/hc/en-au/articles/4657642198159-Customize-your-Timesheet-settings-by-Location) demonstrates permission and location-specific clock/break/overtime exception configuration; it does not mandate copying its workflows.
- [UKG Pro WFM access profiles](https://developer.ukg.com/proplatform/docs/developer-console-quick-start) supports distinct functional access profiles rather than treating a screen-visible action as sufficient authorization.

**Required DoD:** all calculations reproducible from snapshots, payroll input provenance, unauthorized role tests including direct URL/export, no negative net pay, concurrency/idempotency tests, audit on changes, no auto-posting without approvals, CI green, QAS isolated reconciliation and jurisdictional compliance signoff.
