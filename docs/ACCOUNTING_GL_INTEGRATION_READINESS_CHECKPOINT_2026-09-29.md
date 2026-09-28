# Accounting / General Ledger Integration Readiness Checkpoint — 2026-09-29

## Branch and verification state
- Branch: `sec03-framework-upgrade`
- Latest verified application SHA: `0da6af31`
- No application code change was required for this readiness boundary.
- Production remains unchanged.
- QAS has not been advanced to this branch head by this checkpoint.

## Pass status
**Accounting / General Ledger Integration Readiness: CLOSED for current scope**

This checkpoint does **not** mean a General Ledger or accounting module is implemented.

## Current boundary
Flowra currently contains authoritative operational records for commerce and ERP-adjacent workflows, but no journal-posting engine.

No implemented domain was found for:
- Chart of Accounts
- Journal entries
- Debit / credit postings
- Accounting periods
- Posted / unposted journal batches
- Trial balance
- General Ledger
- Accounts Receivable subledger
- Accounts Payable subledger as formal accounting documents
- Bank reconciliation
- Tax ledger
- Financial statements generated from journal postings

## Operational ledgers vs accounting ledger
The application uses the word `ledger` for append-only or authoritative operational evidence such as:
- payment records
- refund records
- purchase receipt item history
- inventory movement history
- supplier settlement history

These are business-event ledgers, not double-entry accounting journals.

Customer Account Statement is intentionally a commercial activity statement and is explicitly tested as **not an accounting ledger**.

## Authoritative event boundaries for future accounting integration

### Sales / revenue source events
Future accounting postings should derive from authoritative order/payment/delivery events rather than dashboard aggregates.
Relevant operational evidence includes:
- order commercial totals
- payment capture ledger
- delivery / completion state
- refund ledger
- sales channel and currency

### Refunds / sales reversals
Use `order_refunds` as the authoritative refund event stream.
Do not derive refund postings only from `orders.refund_total`; that field is a summary snapshot.

### Inventory / COGS
Use immutable inventory movement and lot movement provenance as the operational source.
Do not treat product current cost or inventory quantity snapshots as historical accounting postings.

### Purchasing / supplier liability
Future AP posting logic should derive from:
- purchase receipt history
- accepted received value
- supplier settlement history
- receipt reversals

Do not infer payable accounting entries from purchase header totals alone.

### Payroll
Future payroll accounting integration should post only from finalized payroll results/snapshots.
Draft payroll calculations, attendance rows, or editable compensation records must not be treated as posted accounting entries.

## Analytics boundary
Analytics, profit dashboards, realized revenue metrics, and reporting aggregates are management/reporting outputs.
They are not a substitute for:
- journal entries
- trial balance
- period close
- statutory financial statements

Future GL integration must consume authoritative domain events, not reverse-engineer accounting from analytics tables.

## Integration design boundary
When accounting is introduced, use an explicit posting layer such as:
- domain event / posting event identifier
- source type and source id
- posting date
- accounting period
- currency
- debit lines
- credit lines
- immutable posted state
- reversal linkage
- idempotency key
- audit actor/source
- external accounting integration reference where relevant

Posting must be idempotent and reversible through explicit reversal entries rather than destructive edits.

## Current readiness conclusion
The current architecture is safe to extend toward accounting because operational financial evidence is increasingly:
- order-owned
- append-only where required
- historically preserved
- currency-aware
- idempotent on critical mutations
- separated from analytics summaries

The project should not claim General Ledger capability until explicit journal/posting models exist.

## Next phase
Continue the Business Process & ERP Integrity Audit on the next meaningful domain boundary.

Recommended next domain:
**Cash / Bank Reconciliation & Treasury Readiness**
- distinguish payment capture from actual cash/bank settlement
- review POS cash reconciliation boundaries already present
- inspect bank-transfer confirmation semantics
- identify gateway settlement/reconciliation gaps
- avoid treating provider payment status as bank settlement evidence

Keep QAS promotion separate until a deliberate deployment checkpoint is chosen.
