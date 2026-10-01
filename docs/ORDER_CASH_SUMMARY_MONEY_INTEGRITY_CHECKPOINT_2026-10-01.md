# Order Cash Summary Money Integrity Checkpoint — 2026-10-01

## Status

- Branch: `sec03-framework-upgrade`
- Application / CI / QAS SHA: `3ed0bfda62f9004eb2b0f8addb7390850c3aa5c5`
- Application commit: `3ed0bfda` — `fix: harden order cash summary`
- Hardening CI: **#2427 Green**
- QAS application/static health: **HTTP 200**
- QAS maintenance: **OFF**
- QAS migrations: **none pending**
- QAS failed jobs: **0**
- QAS scheduler / queue worker: **healthy**
- QAS strict ops health: **Green**
- Production: **unchanged**
- Scope status: **CLOSED for source + full CI + deployed-QAS runtime evidence**

## Why this previously closed boundary was reopened

Order Cash Summary had been recorded as closed in the earlier project state. It was not reopened because of a code-style preference.

A read-only QAS probe invoked the real Admin Orders controller and reproduced a current strict-MySQL failure before the finance cards could render. MySQL rejected the currency aggregation with error 1055 because the raw expression-based `GROUP BY` was not accepted under the deployed `ONLY_FULL_GROUP_BY` behavior.

## Integrity policy

The Admin Orders finance cards now derive canonical currencies before aggregation:

- captured payments use Payment currency when present, then Order currency, then `EGP`;
- refund totals use Order currency, then `EGP`;
- currency text is trimmed and normalized to uppercase before grouping;
- both aggregates group and order by the selected `statement_currency` alias for strict-MySQL compatibility.

SQL keeps `SUM(...)` in database DECIMAL arithmetic. Net collected and refund values are then composed with `BigDecimal` at exact two-decimal scale rather than binary `float` plus `round` arithmetic.

Business semantics remain unchanged: the Paid total card is the payment-ledger captured total less recorded refunds, kept separate by currency.

## Regression coverage

Focused automated coverage now protects:

- EGP captured `1.00` with refund `0.25` produces exact net `0.75`;
- USD payment `0.10` with blank Payment currency falls back to the USD Order;
- another USD payment `0.20` with lowercase `usd` joins the same USD group;
- USD refund `0.10` produces exact USD net `0.20`;
- refund totals remain separated by currency;
- both aggregates use the strict-MySQL-compatible `statement_currency` alias;
- the previous float/round business arithmetic is absent from this summary path.

## QAS runtime evidence

Before the fix, the read-only probe reproduced the deployed defect in `OrderController@index` with MySQL error 1055 on the captured-payment currency grouping. This was the reproduced defect that legitimately reopened the boundary.

After Hardening CI #2427 passed, QAS was promoted from `13c20685` to `3ed0bfda`.

The final transaction-wrapped runtime smoke passed:

- exact USD net delta `0.10 + 0.20 - 0.10 = 0.20`;
- exact USD refund delta `0.10`;
- EGP remained separate with exact net `0.75` and refund `0.25`;
- blank Payment currency correctly fell back to the USD Order currency;
- lowercase `usd` normalized into the same USD group;
- transaction rollback cleanup completed with no smoke rows left behind;
- marker: `QAS_ORDER_CASH_SUMMARY_SMOKE_GREEN`.

Post-deploy verification confirmed exact remote HEAD `3ed0bfda62f9004eb2b0f8addb7390850c3aa5c5`, maintenance OFF, no pending migrations, failed jobs 0, scheduler OK, queue worker OK and strict ops health Green.

## Next boundary

Continue the Business Process & ERP Integrity audit from exact verified QAS revision `3ed0bfda`.

Do not reopen Order Cash Summary money integrity again without another reproduced defect. Controlled authenticated browser acceptance remains queued for the later systematic testing and documentation phase, where scenario inputs, validations, expected results, actual results and Passed/Failed evidence will be documented. Production release gates remain separate.
