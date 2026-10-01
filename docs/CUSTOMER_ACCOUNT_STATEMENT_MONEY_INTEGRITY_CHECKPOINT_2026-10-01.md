# Customer Account Statement Money Integrity Checkpoint — 2026-10-01

## Status

- Branch: `sec03-framework-upgrade`
- Application / CI / QAS SHA: `65ce46b99f674e99ce900bb22f8714f1fb937154`
- Exact-money commit: `96f2f18a` — `fix: preserve exact customer statement money`
- Strict-MySQL compatibility commit: `65ce46b9` — `fix: support strict mysql statement grouping`
- Hardening CI: **#2420 Green**
- QAS application/static health: **HTTP 200**
- QAS maintenance: **OFF**
- QAS migrations: **none pending**
- QAS failed jobs: **0**
- QAS scheduler / queue worker: **healthy**
- QAS strict ops health: **Green**
- Production: **unchanged**
- Scope status: **CLOSED for source + full CI + deployed-QAS runtime evidence**

## Integrity boundary

Customer Account Statement money is now preserved as canonical decimal money instead of binary floats across statement aggregation, movement hydration and CSV export.

- Order, captured-payment and refund totals remain separated by recorded currency;
- SQL `SUM(...)` results are normalized directly from decimal text to canonical two-decimal strings;
- order, payment and refund movement amounts remain canonical two-decimal strings;
- CSV export writes those canonical amounts directly instead of converting through `float` + `number_format`;
- return movements remain non-monetary and keep `amount = null`;
- statement semantics remain unchanged: this is commercial activity, not a debit/credit ledger or running balance;
- existing pagination and bounded Print/CSV behavior remain unchanged.

## Strict MySQL defect reproduced and closed

The first deployed exact-money revision `96f2f18a` passed Hardening CI #2419, but the QAS runtime smoke reproduced a strict-MySQL `ONLY_FULL_GROUP_BY` failure in the currency aggregation query.

QAS rejected the expression-based `GROUP BY COALESCE(...currency...)` even though the selected expression matched. The fix groups all three aggregates by the selected alias `statement_currency`:

- Orders;
- Payments;
- Refunds.

A source regression protects all three alias-based groupings. The final revision `65ce46b9` passed Hardening CI #2420 and the same QAS smoke then passed.

## Regression coverage

Focused coverage now protects:

- exact order total `0.10 + 0.20 = 0.30`;
- exact captured-payment total `0.10 + 0.20 = 0.30`;
- exact refund total `0.10`;
- canonical movement amounts for Orders, Payments and Refunds;
- exact CSV output without float conversion;
- zero-value physical-return refund exclusion;
- captured-payment status filtering;
- customer ownership and date semantics;
- per-currency separation;
- paginated UI with full database summary;
- bounded Print and CSV exports;
- strict-MySQL grouping by the selected currency alias.

## QAS runtime evidence

The final revision `65ce46b9` was deployed after Hardening CI #2420 passed.

Post-deploy verification confirmed:

- exact remote HEAD `65ce46b99f674e99ce900bb22f8714f1fb937154`;
- application/static HTTP 200;
- maintenance OFF;
- no pending migrations;
- scheduler and queue worker healthy;
- failed jobs 0;
- strict ops health Green.

The transaction-wrapped runtime smoke passed:

- order total exactly `0.30`;
- captured payment total exactly `0.30`;
- refund total exactly `0.10`;
- order/payment/refund counts;
- canonical order movement amounts `0.10` and `0.20`;
- canonical payment movement amounts `0.10` and `0.20`;
- canonical refund movement amount `0.10`;
- CSV exact `0.10` and `0.20` output;
- rollback cleanup with no smoke records left behind.

## Next boundary

Continue the Business Process & ERP Integrity money audit from exact verified QAS revision `65ce46b9`.

The remaining float-based customer index/show commercial KPI summaries are a separate candidate boundary and were intentionally not mixed into Account Statement closure. Re-scan and choose the next genuinely unclosed business-critical money boundary.

Authenticated browser acceptance remains queued for the dedicated systematic QAS testing phase. Production release gates remain separate.
