# Payment Captured Summary Money Integrity Checkpoint — 2026-10-01

## Status

- Branch: `sec03-framework-upgrade`
- Final application / CI / QAS SHA: `13c20685c36d5276bf64e46a9ecfa075ad73a12e`
- Primary application commit: `94cb9b30` — `fix: harden payment captured summary`
- Strict-MySQL compatibility commit: `13c20685` — `fix: align payment summary mysql grouping`
- Hardening CI #2424: **Green** on the primary exact-money revision.
- Hardening CI #2425: **Green** on the final strict-MySQL-compatible revision.
- QAS application/static health: **HTTP 200**
- QAS maintenance: **OFF**
- QAS migrations: **none pending**
- QAS failed jobs: **0**
- QAS scheduler / queue worker: **healthy**
- QAS strict ops health: **Green**
- Production: **unchanged**
- Scope status: **CLOSED for source + full CI + deployed-QAS runtime evidence**

## Integrity boundary

The Admin Payments **Captured amount** summary must report captured payment history under the correct currency and preserve exact two-decimal money.

Before this checkpoint, the database query grouped on the raw `payments.currency` value and only normalized the currency after aggregation. That created two business risks:

- a historical payment with a blank currency could be reported as EGP even when its owning Order was USD;
- case variants such as `usd` and `USD` could be aggregated separately and later displayed as duplicate USD rows.

## Currency and exact-money policy

The summary now derives one canonical currency before aggregation:

1. use the Payment currency when it is present;
2. otherwise fall back to the owning Order currency;
3. otherwise fall back to `EGP`;
4. trim whitespace and normalize the resulting currency to uppercase.

Captured money continues to include both `paid` and historically `refunded` Payment rows so the metric remains a capture-history metric rather than current outstanding cash.

`SUM(payments.amount)` stays in SQL DECIMAL arithmetic. The controller converts the database total to an exact two-decimal `BigDecimal` string with no binary-float business arithmetic.

## Strict MySQL finding and correction

The first application revision `94cb9b30` passed Hardening CI #2424 and deployed cleanly to QAS, but the dedicated QAS runtime smoke reproduced a MySQL `ONLY_FULL_GROUP_BY` failure when the raw currency expression was repeated in `GROUP BY`.

That QAS-only failure was not accepted as a closed checkpoint.

The follow-up revision `13c20685` groups and orders by the selected `statement_currency` alias instead. Hardening CI #2425 then passed fully, and the same QAS smoke that previously failed passed on the final revision.

This is retained as explicit regression evidence because CI success alone did not prove strict-QAS SQL compatibility for this query shape.

## Regression coverage

Focused coverage protects the following scenario:

- Order currency = `USD`, Payment currency blank, amount = `0.10`;
- another USD Order, Payment currency = `usd`, amount = `0.20`;
- exactly one resulting `USD` captured-summary row;
- exact captured total = `0.30`;
- no incorrect EGP fallback row;
- strict-MySQL-compatible grouping by `statement_currency`.

Existing coverage also continues to protect the policy that historically refunded captures remain included in Captured amount.

## QAS runtime evidence

QAS was first promoted from `21763310` to `94cb9b30`. The initial transaction-wrapped smoke correctly caught the strict-MySQL grouping defect.

After the compatibility fix and Hardening CI #2425 Green, QAS was promoted from `94cb9b30` to final revision `13c20685`.

The final transaction-wrapped smoke passed:

- blank Payment currency correctly fell back to the USD Order currency;
- lowercase `usd` normalized into the same USD group;
- exactly one USD captured-summary row remained;
- exact delta `0.10 + 0.20 = 0.30`;
- transaction rollback cleanup completed with no smoke records left behind;
- marker: `QAS_PAYMENT_CAPTURED_SUMMARY_SMOKE_GREEN`.

Post-deploy verification confirmed:

- exact remote HEAD `13c20685c36d5276bf64e46a9ecfa075ad73a12e`;
- application/static HTTP 200;
- maintenance OFF;
- no pending migrations;
- scheduler OK;
- queue worker OK;
- failed jobs 0;
- strict ops health Green.

## Next boundary

Continue the Business Process & ERP Integrity audit from exact verified QAS revision `13c20685`.

Do not reopen Payment Captured Summary money integrity without a reproduced defect. Re-scan remaining business-critical monetary calculations and select one genuinely unclosed boundary.

Authenticated browser acceptance remains queued for the dedicated systematic QAS testing/documentation phase. Production release gates remain separate.
