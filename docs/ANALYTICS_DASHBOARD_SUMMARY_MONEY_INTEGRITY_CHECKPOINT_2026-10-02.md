# Analytics Dashboard Summary Money Integrity Checkpoint — 2026-10-02

## Status

- Branch: `sec03-framework-upgrade`
- Application / CI / QAS SHA: `d0059ee81a96a615053403079cce199a007533fb`
- Application commit: `d0059ee8` — `fix: harden analytics summary money`
- Hardening CI: **#2429 Green**
- QAS application/static health: **HTTP 200**
- QAS maintenance: **OFF**
- QAS migrations: **none pending**
- QAS failed jobs: **0**
- QAS scheduler / queue worker: **healthy**
- QAS strict ops health: **Green**
- QAS queue observation: **1 non-failed database job pending during both post-deploy health checks; worker remained healthy**
- Production: **unchanged**
- Scope status: **CLOSED for source + full CI + deployed-QAS runtime evidence**

## Scope

This checkpoint closes only the Analytics Dashboard summary-money boundary:

- summary revenue / realized revenue;
- discount total;
- shipping total;
- average order value (AOV);
- current/previous/delta money values used by period comparison;
- both persisted daily-aggregate and raw-event fallback paths.

Product/category drilldown profitability and ranking remain separate boundaries and were not silently folded into this slice.

## Reproduced defect

Before changing source, a transaction-wrapped probe ran against the then-current QAS application `3ed0bfda`.

Using `0.10 + 0.20` revenue and two orders, both summary paths reproduced binary-float drift:

- daily aggregates: revenue `0.30000000000000004`, realized revenue `0.30000000000000004`, AOV `0.15000000000000002`;
- raw events: revenue `0.30000000000000004`, realized revenue `0.30000000000000004`, AOV `0.15000000000000002`;
- markers: `DAILY_EXACT=NO` and `RAW_EXACT=NO`;
- all probe data was rolled back.

This was runtime evidence of an actual money-integrity defect, not a style-only refactor.

## Integrity policy

Summary money now stays in decimal form instead of being accumulated through binary floats:

- revenue, discount and shipping sums use `BigDecimal`;
- summary money is normalized to canonical two-decimal strings;
- realized revenue preserves the same exact two-decimal representation;
- AOV divides exact realized revenue by order count and rounds Half-Up to cents;
- period-comparison current/previous/delta values for revenue and AOV are calculated from exact decimals;
- percentage/rate metrics remain numeric because they are ratios, not stored money.

The existing business meaning of the dashboard was not changed. This boundary changes monetary representation and arithmetic precision only.

## Regression coverage

Focused automated coverage protects both data paths with exact small-value money:

- `0.10 + 0.20 = 0.30` revenue;
- discount total `0.01 + 0.02 = 0.03`;
- shipping total `0.03 + 0.04 = 0.07`;
- two-order AOV `0.30 / 2 = 0.15`;
- realized revenue remains exactly `0.30`;
- revenue comparison current `0.30`, previous `0.00`, delta `0.30`;
- AOV comparison current `0.15`.

The local Windows PHP CLI is 8.2 while the Laravel 13 dependency set requires PHP 8.3+, so local Artisan PHPUnit could not boot. Source syntax checks passed locally; Hardening CI #2429 on the project PHP 8.3 runtime ran the complete PHPUnit regression suite successfully.

## QAS runtime evidence

After Hardening CI #2429 passed, QAS was promoted from `3ed0bfda` to `d0059ee8`.

The same transaction-wrapped probe that reproduced the defect then returned:

- daily revenue `'0.30'` and AOV `'0.15'`;
- raw-event revenue `'0.30'` and AOV `'0.15'`;
- exact discount `'0.03'` and shipping `'0.07'` on both paths;
- `DAILY_EXACT=YES`;
- `RAW_EXACT=YES`;
- `QAS_ANALYTICS_SUMMARY_PROBE_DONE`;
- `ROLLBACK_OK`.

The probe also printed a float-cast diagnostic representation on purpose; those diagnostic binary values are not the returned money representation after the fix.

Post-deploy verification confirmed exact remote HEAD `d0059ee81a96a615053403079cce199a007533fb`, maintenance OFF, no pending migrations, failed jobs 0, scheduler OK, queue worker OK and strict ops health Green. One non-failed database-queue job remained pending during two checks; no queue data was deleted or altered because the worker and strict health checks were healthy.

## Next boundary

Continue the Business Process & ERP Integrity audit from exact verified QAS revision `d0059ee8`.

Do not reopen this Analytics Dashboard summary-money boundary without a reproduced defect. Keep product/category analytics drilldowns as a separate candidate boundary. Controlled authenticated browser acceptance remains queued for the later systematic testing/documentation phase, where scenario inputs, validations, expected results, actual results and Passed/Failed evidence will be recorded. Production release gates remain separate.
