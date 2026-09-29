# Workforce Payroll Integrity Checkpoint — 2026-09-29

## Branch and verification state
- Branch: `sec03-framework-upgrade`.
- Final verified application SHA: `123fcbc9f10eb5fb6b6ed24bf628af877aa31ee6` (`123fcbc9`).
- Hardening CI #2250: Green on the exact application SHA.
- QAS is deployed on the exact same application SHA.
- QAS deployment applied all pending ERP-integrity migrations through `2026_09_29_001000_enable_compensation_history`, then completed with application HTTP 200, static asset HTTP 200 and maintenance OFF.
- Post-deploy verification confirmed remote HEAD `123fcbc9`, Home HTTP 200, Login HTTP 200, normal Admin redirect behavior and unauthenticated Payroll redirect to Login.
- Immutable repository tag: `qas-payroll-integrity-2026-09-29`.
- Production remains unchanged.
- **Payroll Integrity is CLOSED for the implemented source / CI / deployed-QAS integrity boundary.** Authenticated destructive/manual browser workflow acceptance remains a separate QAS acceptance gate.
- This checkpoint closes business-integrity rules for the implemented Payroll Foundation V1; it does not claim statutory payroll, banking, or accounting functionality.

## Compensation history authority
- Employee compensation is effective-dated history rather than one destructively overwritten row.
- A new effective date preserves the prior rate/currency/basis record and closes the prior open period when needed.
- Duplicate effective-start dates update the matching historical row rather than creating ambiguous duplicates.
- Explicit compensation periods cannot overlap.
- Retroactive payroll resolves the compensation record that actually covered the historical payroll period.
- A compensation change inside one payroll period is blocked from payroll generation until an explicit proration policy exists.
- Salary partial-period employment/compensation remains blocked rather than silently paying a full period.

## Compensation service boundary
- Pay basis is validated again inside the domain service.
- Currency is normalized and restricted to a three-letter ISO-style code.
- Effective dates are validated at the service boundary, including a required effective-from value.
- Base rate rejects scientific notation and more than two decimal places.
- Overtime multiplier is bounded and precision-limited when overtime is enabled.

## Payroll generation authority
- One payroll run is allowed per payroll period.
- Payroll cannot be generated before the period has ended.
- Open attendance sessions and open breaks touching the period block generation.
- Pending attendance corrections touching the period block generation.
- Pending leave requests touching the period block generation.
- Payroll entries snapshot effective attendance, approved leave, compensation, employee identity and policy flags.
- Historical payroll entries do not change when compensation master data changes later.

## Money integrity
- Payroll base rate and adjustment amounts align with `DECIMAL(14,2)` boundaries.
- Over-precision and scientific notation are rejected rather than silently rounded.
- Quantity and rate validation align with their database precision/scale limits.
- Adjustment and entry totals are recalculated using integer cents rather than floating-point summation.
- Deductions cannot drive net pay below zero.
- Payroll summary totals are also aggregated in exact cents.
- Totals remain separated by currency; no implicit FX conversion or mixed-currency total is produced.

## Finalization integrity
- Payroll lifecycle remains Draft → Approved → Paid.
- Approval closes the payroll period.
- Entries and adjustments become immutable after finalization at the model/domain boundary, not only in the UI.
- Closed payroll periods cannot be edited or deleted.
- Approved payroll runs cannot regress or be edited; the only allowed transition is Approved → Paid.
- Paid payroll runs are terminal and immutable.
- Draft adjustment changes remain allowed until approval.

## Explicit V1 boundaries
- `Paid` is an internal operator-confirmed payroll workflow state.
- `Paid` does not prove bank settlement, payroll-file acceptance, or employee-account receipt.
- The Admin payroll run explicitly discloses that boundary before and after marking a run Paid.
- No bank disbursement/reconciliation ledger is implemented for payroll yet.
- No statutory tax, social-insurance, withholding or jurisdiction-specific deduction engine is claimed.
- Paid/unpaid leave is snapshotted but is not automatically monetized without an explicit policy.
- Mid-period salary/rate proration remains intentionally unimplemented until a policy is defined.
- Current employee status is not treated as historical employment eligibility. Hire/termination dates remain the historical boundary available in V1; a future effective-dated employment-status model is needed if inactive-state history must affect retroactive payroll.

## CI and QAS evidence
- `ee0552c9` passed Hardening CI #2246 after the compensation-history migration ordering fix.
- `5b942214` payroll immutability itself passed the Workforce payroll suite; its branch CI #2247 was red only because an unrelated Purchase settlement test used a wall-clock-dependent idempotency date.
- `2f3ae79d` stabilizes that unrelated test by deriving the replay date from the original explicit timestamp; Hardening CI #2248 passed.
- `daa9b4b7` closed compensation service validation and exact-cent payroll summaries; Hardening CI #2249 passed.
- `123fcbc9` added the explicit Paid-vs-bank-settlement disclosure; Hardening CI #2250 passed.
- QAS was promoted from `523af8f8` to `123fcbc9` through the versioned `deploy-qas.sh sec03-framework-upgrade` path.
- The consolidated deployment applied 25 pending migrations without error and returned QAS to live mode.
- Automated/public QAS evidence is Green. The checklist items that require a signed-in human workflow remain explicitly pending and must not be inferred from CI/public health.

## Next action
Run the controlled authenticated Workforce / Payroll QAS acceptance pass against `123fcbc9`, using controlled test records only. Fix reproduced defects with regression coverage. After that, continue the next genuinely unclosed Business Process & ERP Integrity domain rather than adding Payroll V2 statutory depth without real policy.
