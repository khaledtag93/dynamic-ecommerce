# Customer Commercial KPI Money Integrity Checkpoint — 2026-10-01

## Status

- Branch: `sec03-framework-upgrade`
- Application / CI / QAS SHA: `2176331064824e11c509b190d1aaeab5c1e3395d`
- Application commit: `21763310` — `fix: harden customer commercial kpis`
- Hardening CI: **#2422 Green**
- QAS application/static health: **HTTP 200**
- QAS maintenance: **OFF**
- QAS migrations: **none pending**
- QAS failed jobs: **0**
- QAS scheduler / queue worker: **healthy**
- QAS strict ops health: **Green**
- Production: **unchanged**
- Scope status: **CLOSED for source + full CI + deployed-QAS runtime evidence**

## Integrity boundary

Customer commercial KPI calculations in Admin Customers now preserve money as exact decimals/cents instead of binary floats.

Covered values:

- per-customer realized spend on the Customer list;
- aggregate customer revenue grouped by currency;
- Customer profile gross realized order value;
- recorded refunds;
- net realized spend;
- average realized order value.

Business meaning and ranking policy were intentionally preserved. The existing high-value ranking remains currency-specific and SQL-authoritative; this checkpoint hardens monetary composition and presentation inputs without changing who qualifies as commercially realized.

## Exact-money policy

- Stored two-decimal money is normalized with exact decimal arithmetic and no silent re-rounding.
- Net realized spend is composed in integer cents.
- Average order value divides integer cents by realized-order count and rounds to the nearest cent with Half-Up semantics.
- Canonical KPI money leaves the controller as two-decimal strings.
- Display-only `number_format` remains presentation formatting rather than a source of business arithmetic.

## Strict MySQL compatibility

The three customer KPI aggregation paths now group by their selected aliases instead of repeating expression-based raw grouping:

- per-user spend uses `statement_currency`;
- aggregate customer revenue uses `currency`;
- Customer profile spend uses `currency`.

A regression protects these alias-based groupings so QAS strict-MySQL behavior remains aligned with CI.

## Regression coverage

Focused coverage now protects:

- exact customer revenue strings by currency;
- exact per-customer realized spend;
- exact profile gross/refund/net money;
- exact small-value composition;
- Half-Up average-order rounding, including `0.10 / 4 = 0.03`;
- existing currency-safe high-value ranking behavior;
- existing commercially-realized order policy;
- strict-MySQL alias grouping.

## QAS runtime evidence

QAS was promoted from `65ce46b9` to `21763310` after Hardening CI #2422 passed.

Transaction-wrapped runtime smoke passed:

- list realized spend exactly `0.10`;
- aggregate customer revenue exactly `0.10`;
- profile gross exactly `0.20`;
- profile refund exactly `0.10`;
- profile net exactly `0.10`;
- profile average order value exactly `0.03` using Half-Up rounding;
- rollback cleanup with no smoke records left behind.

Post-deploy verification confirmed:

- exact remote HEAD `2176331064824e11c509b190d1aaeab5c1e3395d`;
- application/static HTTP 200;
- maintenance OFF;
- no pending migrations;
- scheduler and queue worker healthy;
- failed jobs 0;
- strict ops health Green.

## Next boundary

Continue the Business Process & ERP Integrity audit from exact verified QAS revision `21763310`.

Do not reopen Customer Account Statement or Customer Commercial KPI money integrity without a reproduced defect. Re-scan the remaining business-critical monetary calculations and select one genuinely unclosed boundary.

Authenticated browser acceptance remains queued for the dedicated systematic QAS testing phase. Production release gates remain separate.
