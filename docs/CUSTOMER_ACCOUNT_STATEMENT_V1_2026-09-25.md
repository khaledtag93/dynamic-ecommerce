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

- Working implementation branch: `wip/customer-account-statement-v1`
- Initial implementation commit: `75e2f53`
- Production unchanged.
- Merge to `v42-clean-baseline` requires the current base Hardening CI to be green first.
