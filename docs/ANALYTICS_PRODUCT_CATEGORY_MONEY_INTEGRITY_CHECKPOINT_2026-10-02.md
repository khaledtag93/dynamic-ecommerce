# Analytics Product / Category Money Integrity Checkpoint — 2026-10-02

## Status

- Branch: `sec03-framework-upgrade`
- Application / CI / QAS SHA: `538ce8fc7aabb3ba8328746056f93995a5a2c0b4`
- Application commit: `538ce8fc` — `fix: harden product category analytics money`
- Hardening CI: **#2431 Green**
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

This checkpoint closes the money representation and arithmetic boundary for:

- Product Analytics drilldown totals;
- Top Products;
- Top Categories;
- raw-event product/category fallback paths;
- product drilldown Top Variants;
- revenue, realized COGS, profit and average revenue per purchase;
- margin percentages derived from exact money values.

The previously closed Analytics Dashboard headline-summary boundary remains separate and was re-smoked after this deployment.

## Reproduced defect

Before changing source, a transaction-wrapped probe ran against the then-current QAS application `d0059ee8`.

With two product rows / order economics of `0.10 + 0.20` revenue, `0.03 + 0.06` COGS and `0.07 + 0.14` profit:

- aggregated Product Drilldown revenue became `0.30000000000000004`;
- aggregated Product Drilldown profit became `0.21000000000000002`;
- average revenue per purchase became `0.15000000000000002`;
- Top Variant money was emitted as binary floats such as `0.3`;
- raw Product Drilldown showed the same binary-float drift;
- raw Top Product and Top Category money was emitted as floats;
- markers were `AGG_EXACT=NO`, `VARIANT_EXACT=NO`, and `RAW_EXACT=NO`;
- all probe rows were rolled back.

This was runtime evidence from deployed QAS, not a code-style-only refactor.

## Integrity policy

Product/category analytics now keep business money exact through the read/drilldown paths:

- daily Product totals use decimal summation instead of PHP float accumulation;
- raw product event buckets accumulate revenue, COGS and profit with `BigDecimal`;
- Product Drilldown revenue / COGS / profit use canonical two-decimal strings;
- average revenue per purchase uses exact decimal division with Half-Up cent rounding;
- Top Product and Top Category aggregates normalize SQL DECIMAL output to canonical two-decimal strings;
- raw Top Product / Category aggregation uses exact decimal sums;
- Top Variants remain integer-cent authoritative internally and no longer round-trip COGS/profit through binary floats;
- Top Variant revenue / realized revenue / COGS / profit return canonical two-decimal strings;
- margin percentages remain numeric ratios, but are derived from exact money inputs / integer cents.

Existing profitability-completeness, refunded-loss, ranking, quantity and weighted-margin semantics remain unchanged.

## Regression coverage

Focused automated coverage now protects exact small-value money across all closed paths:

- daily aggregate Product / Category path: revenue `0.30`, COGS `0.09`, profit `0.21`, ARPP `0.15`;
- raw-event Product / Category path: the same exact values;
- Top Product and Top Category: canonical two-decimal revenue / COGS / profit strings;
- Top Variant: exact revenue / realized revenue `0.30`, COGS `0.09`, profit `0.21`;
- weighted gross margin remains `70.0%`;
- profitability-completeness and historical-unknown behavior remain covered by existing regressions.

New tests:

- `test_product_and_category_analytics_preserve_exact_money_from_daily_aggregates`;
- `test_product_and_category_analytics_preserve_exact_money_from_raw_events`;
- `test_product_variant_analytics_preserves_exact_money`.

Local source syntax checks and `git diff --check` passed. The Windows CLI remains PHP 8.2 while the Laravel 13 dependency set requires PHP 8.3+, so the authoritative application regression gate is Hardening CI #2431; its complete PHPUnit suite passed.

## QAS runtime evidence

After Hardening CI #2431 passed, QAS was promoted from `d0059ee8` to `538ce8fc`.

The exact same transaction-wrapped probe that reproduced the defect then returned:

- Product Drilldown revenue `'0.30'`, COGS `'0.09'`, profit `'0.21'`, ARPP `'0.15'`;
- Top Product revenue / COGS / profit `'0.30' / '0.09' / '0.21'`;
- Top Category revenue / COGS / profit `'0.30' / '0.09' / '0.21'`;
- Top Variant revenue / COGS / profit `'0.30' / '0.09' / '0.21'`;
- `AGG_EXACT=YES`;
- `VARIANT_EXACT=YES`;
- `RAW_EXACT=YES`;
- `QAS_PRODUCT_CATEGORY_ANALYTICS_PROBE_DONE`;
- `ROLLBACK_OK`.

The earlier Analytics Dashboard Summary probe was re-run on `538ce8fc` as a non-regression check and remained exact on both daily and raw paths with `DAILY_EXACT=YES`, `RAW_EXACT=YES`, and rollback cleanup.

Post-deploy verification confirmed exact remote HEAD `538ce8fc7aabb3ba8328746056f93995a5a2c0b4`, maintenance OFF, no pending migrations, failed jobs 0, scheduler OK, queue worker OK and strict ops health Green. One non-failed database-queue job remained pending; no queue data was deleted or altered because the worker and strict health checks were healthy.

## Next boundary

Continue the Business Process & ERP Integrity audit from exact verified QAS revision `538ce8fc`.

Do not reopen Analytics Dashboard Summary or Product / Category / Variant money integrity without a reproduced defect. Treat the Analytics aggregation write path as a separate candidate boundary: inspect and reproduce an actual persistence defect before changing it. Controlled authenticated browser acceptance remains queued for the later documented testing phase. Production release gates remain separate.
