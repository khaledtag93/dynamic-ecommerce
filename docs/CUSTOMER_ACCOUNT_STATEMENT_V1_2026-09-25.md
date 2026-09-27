# Customer Account Statement V1 — 2026-09-25

## Purpose

Customer Account Statement V1 gives Admin a date-based commercial movement history for one customer without pretending Dynamic already has a formal customer accounting ledger.

## Canonical sources

The statement reads existing source records only:
- Orders;
- captured Payments (records with a real `paid_at`);
- Order Refunds;
- Return Requests.

No new financial ledger rows are invented.

## Semantics

- Order rows describe commercial order value and current order status.
- Payment rows describe actual captured-payment events.
- Refund rows describe recorded returned-money events.
- Return rows are operational events and intentionally have no fabricated monetary amount.
- Totals are grouped by the currency already stored on the source record.
- Different currencies are never added together or converted.
- V1 intentionally does **not** show debit/credit semantics or a running balance. Those require a real customer financial ledger with explicit posting rules.

## Admin UX

From Customer Details, authorized staff can open **Account statement**.

The workspace includes:
- movement-type filter;
- From / To date filter;
- counts for Orders, Captured Payments, Refunds, and Returns;
- per-currency source totals;
- chronological movement table;
- drill-through links to the canonical Order, Payment, or Return record;
- printable statement view;
- UTF-8 CSV export using the same filters.

The default period is the last 12 months to keep normal reads bounded.

## Authorization

All statement routes remain inside the existing `customers.manage` Admin permission boundary.

Customer-facing access is not introduced in V1.

## Regression coverage

`CustomerAccountStatementTest` covers:
- canonical order/payment/refund/return movements;
- no-running-balance contract;
- movement type and period filtering;
- separated multi-currency presentation;
- CSV export headers;
- Admin authorization boundary.

## Release state

- Initial implementation commit: `75e2f53`
- Merged to `v42-clean-baseline` at `347845b`.
- Follow-up authorization-test alignment completed at `962cce1`.
- Hardening CI #1363 passed on `962cce1`.
- Production unchanged; QAS acceptance remains separate.


## Production scalability hardening — 2026-09-27

The statement no longer materializes all matching Orders, Payments, Refunds, and Returns in PHP memory.

- The on-screen timeline uses a database UNION index query and hydrates only the current page (50 movements).
- Counts are calculated in SQL for the complete filtered period.
- Currency totals are grouped and summed in SQL for the complete filtered period.
- Print is intentionally bounded to 500 movements.
- CSV export is intentionally bounded to 5,000 movements and returns row-limit/truncation response headers.
- The UI and printable view disclose truncation rather than silently implying that a bounded document is complete.
- Composite indexes were added for the customer/date access paths used by Orders, Payments, Refunds, and Return Requests.

This keeps memory use bounded even when a customer has a long transaction history while preserving canonical source semantics and the no-running-balance contract.
