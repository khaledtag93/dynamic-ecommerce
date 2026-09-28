# Customer Account Statement / Accounting Evidence Integrity Checkpoint — 2026-09-29

## Branch and verification state
- Branch: `sec03-framework-upgrade`
- Latest verified application SHA: `f112d05d`
- GitHub application check: completed / success
- Production remains unchanged.
- QAS has not been advanced to this branch head by this checkpoint.

## Pass status
**Customer Account Statement / Accounting Evidence Integrity: CLOSED**

## Closed invariants

### Customer ownership authority
- Orders are scoped by `orders.user_id`.
- Payment movements are scoped through their owning order.
- Refund movements are scoped through their owning order.
- Return movements are now scoped through their owning order rather than trusting `return_requests.user_id` alone.
- Legacy/corrupt RMA customer fields cannot leak a return into another customer's statement.

### Payment evidence semantics
- A payment is shown as captured only when:
  - `paid_at` exists, and
  - status is `paid` or `refunded`.
- Failed or pending legacy/corrupt payments with a stray `paid_at` do not enter captured totals, counts, or movement rows.
- Refunded payments remain valid capture evidence; refund movements are represented separately.

### Historical/date-bounded consistency
- Order movements use `placed_at` with `created_at` fallback only when `placed_at` is null.
- Payment movements use `paid_at`.
- Refund movements use `processed_at` with `created_at` fallback for legacy rows only when `processed_at` is null.
- Return movements use `requested_at` with `created_at` fallback only when `requested_at` is null.
- Summary counts/totals and movement index use the same period semantics for each movement type.

### Currency and totals integrity
- Totals remain grouped by recorded currency.
- No implicit FX conversion is performed.
- Order value, captured payments, and processed refunds remain distinct statement measures.
- Statement intentionally does not invent a running accounting balance.

### Pagination / print / export consistency
- Interactive statement rows are paginated while summary totals/counts remain full-period database summaries.
- Print uses a bounded dataset with an explicit truncation warning when the limit is exceeded.
- CSV export uses a bounded dataset and exposes row-limit, matching-row, and truncation metadata headers.
- Print/export use the same statement service and filtering semantics as the interactive view.

### CSV export safety
- Formula-capable text cells are neutralized before CSV output.
- Text beginning (after optional whitespace) with `=`, `+`, `-`, or `@` is exported as text rather than a spreadsheet formula.
- Numeric amount fields and timestamps remain structured values.

## Verified commit chain
- `9023be8b` — scope return statements by order owner
- `581c7a23` — require captured payment evidence in statements
- `f112d05d` — neutralize spreadsheet formulas in statement CSV

Final application head `f112d05d` was verified Green by GitHub CI.

## Next phase
Continue the Business Process & ERP Integrity Audit on the next meaningful domain boundary.

Recommended next domain:
**Customer Credit / Receivables & Payment Allocation Readiness**
- verify whether Flowra currently has a real receivables concept or only order/payment/refund activity
- identify whether partial/multiple payment allocation needs explicit ledger semantics
- avoid inventing AR balances until invoices/credits/allocations are modeled authoritatively
- prepare accounting integration boundaries without turning the commercial statement into a general ledger

Keep QAS promotion separate until a deliberate deployment checkpoint is chosen.
