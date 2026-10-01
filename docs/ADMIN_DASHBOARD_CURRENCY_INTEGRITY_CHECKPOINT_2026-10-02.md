# Admin Dashboard Currency Integrity Checkpoint — 2026-10-02

## Status

- Branch: `sec03-framework-upgrade`
- Application / CI / QAS SHA: `8a7e541426cd235c307874a1813b3858148616a5`
- Application commit: `8a7e5414` — `fix: keep dashboard currencies separate`
- Hardening CI: **#2433 Green**
- QAS application/static health: **HTTP 200**
- QAS maintenance: **OFF**
- QAS migrations: **none pending**
- QAS failed jobs: **0**
- QAS scheduler / queue worker: **healthy**
- QAS strict ops health: **Green**
- QAS queue observation: **1 non-failed database job pending during post-deploy verification; worker remained healthy**
- Production: **unchanged**
- Scope status: **CLOSED for source + full CI + deployed-QAS runtime evidence**

## Scope

This checkpoint closes the currency-integrity boundary for the Admin Dashboard order KPIs and recent-order totals:

- Gross order value is aggregated and displayed separately per currency.
- Average order value is calculated separately inside each currency bucket.
- Order count and paid-order share remain count-based global KPIs.
- Recent Orders display each order's own normalized currency instead of a hard-coded EGP label.
- Blank/missing order currency retains the existing EGP fallback.

## Reproduced defect

Before changing source, a transaction-wrapped probe ran against QAS application `538ce8fc`.

The current QAS data had two recent EGP orders totaling `10000.00`. The probe temporarily added:

- one `EGP 1.00` order;
- one `USD 2.00` order.

The actual Admin Dashboard controller then returned:

- Gross order value: `EGP 10,003.00`;
- Average order value: `EGP 2,500.75`.

The probe independently confirmed the temporary rows were two separate currency buckets:

- EGP: `1.00`;
- USD: `2.00`.

Therefore the old dashboard was numerically adding different currencies and labeling the combined result as EGP. The transaction rolled back all probe rows.

## Integrity policy

The Admin Dashboard now treats currency as part of the financial identity of an amount:

- the 30-day order window groups by normalized currency before summing `grand_total`;
- currency normalization uses uppercase trimmed currency with EGP fallback;
- monetary totals remain SQL DECIMAL strings through aggregation;
- per-currency AOV uses `BigDecimal` division with two-decimal Half-Up rounding;
- financial KPI display keeps each currency bucket visible rather than converting or combining currencies;
- no implicit FX conversion is performed;
- recent-order rows use each order's actual normalized currency.

This preserves the existing dashboard meaning: the KPI still covers all order statuses in the last 30 days, while no longer pretending unlike currencies are additive.

## Regression coverage

`AdminDashboardExperienceTest` now includes:

- `test_dashboard_financial_kpis_keep_currencies_separate`.

The scenario verifies:

- EGP `0.14 + 0.15 = 0.29`;
- USD `2.00` remains separate;
- Gross Order Value is `EGP 0.29 | USD 2.00`;
- EGP AOV `0.29 / 2 = 0.145` rounds Half-Up to `EGP 0.15`;
- USD AOV remains `USD 2.00`;
- the dashboard never renders the invalid combined `EGP 2.29`;
- Recent Orders render EGP and USD labels from the individual orders.

Local PHP syntax checks and `git diff --check` passed. The authoritative PHP 8.3 / MySQL gate is Hardening CI #2433; its complete PHPUnit suite, clean MySQL migration, Laravel boot/routes, browser interaction tests, frontend audit and production build all passed.

## QAS runtime evidence

QAS was promoted from `538ce8fc` to exact application SHA `8a7e541426cd235c307874a1813b3858148616a5`.

The same transaction-wrapped currency probe then returned:

- Actual Gross card: `EGP 10,001.00 | USD 2.00`;
- Expected Gross card: `EGP 10,001.00 | USD 2.00`;
- Actual AOV card: `EGP 3,333.67 | USD 2.00`;
- Expected AOV card: `EGP 3,333.67 | USD 2.00`;
- `MIXED_CURRENCY_PRESENT=YES`;
- `DASHBOARD_CURRENCY_EXACT=YES`;
- `ROLLBACK_OK`.

Post-deploy verification confirmed exact remote HEAD `8a7e541426cd235c307874a1813b3858148616a5`, maintenance OFF, no pending migrations, failed jobs 0, scheduler OK, queue worker OK and strict ops health Green.

## Analytics aggregation write-path inspection

Before opening this dashboard boundary, the Analytics Aggregation write path was inspected separately on QAS `538ce8fc`.

A transaction-wrapped probe exercised the real `AnalyticsAggregationService::aggregateDay()` persistence path with:

- `0.10 + 0.20` money values;
- a Half-Up AOV case of `0.29 / 2 = 0.145`;
- daily revenue, discount, shipping and AOV persistence;
- per-product revenue, realized COGS and profit persistence.

The persisted database values were exact canonical DECIMAL values and the probe returned `PERSISTED_EXACT=YES` with rollback cleanup. No persistence defect was reproduced, so that service was intentionally left unchanged.

## Next boundary

Continue the Business Process & ERP Integrity audit from exact verified QAS revision `8a7e5414`.

Do not reopen the Admin Dashboard currency boundary or the Analytics aggregation write path without new reproduced evidence. Controlled authenticated browser acceptance remains queued for the later documented testing phase. Production release gates remain separate.
