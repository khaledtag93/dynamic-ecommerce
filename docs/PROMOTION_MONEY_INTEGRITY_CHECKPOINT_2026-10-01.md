# Promotion Money Integrity Checkpoint — 2026-10-01

## Status

- Branch: `sec03-framework-upgrade`
- Application / CI / QAS SHA: `9043b6cebd2c3cdae14da8e0cb9e5ee948458072`
- Commit: `fix: harden promotion money boundaries`
- Hardening CI: **#2400 Green**
- Full PHPUnit: **852 passed / 17,769 assertions**
- QAS application/static health: **HTTP 200**
- QAS maintenance: **OFF**
- QAS migrations: **none pending**
- QAS failed jobs: **0**
- QAS strict ops health: **Green**
- Production: **unchanged**
- Scope status: **CLOSED for source + full CI + deployed-QAS runtime evidence**

## Integrity boundary

Promotion configuration and discount execution now preserve exact monetary semantics:

- `discount_value` and `min_subtotal` accept at most two decimal places and stay within `DECIMAL(12,2)`;
- percentage promotion validation compares exactly against `100.00%` without binary floating-point comparison;
- minimum-subtotal eligibility normalizes the commercial subtotal to exact cents before comparison;
- order percentage discounts use exact decimal math with explicit Half-Up cent rounding;
- fixed discounts use exact stored decimals and cannot exceed the order subtotal;
- category-percentage discounts sum only eligible line totals as exact cents before applying the percentage;
- Buy-X-Get-Y keeps the existing cheapest-eligible-unit policy while accumulating free-unit value in exact cents;
- best-discount selection compares exact decimal results;
- the final promotion discount is defensively clamped to `[0, subtotal]`, including protection from legacy percentage rules above 100%;
- the public engine result remains float-compatible only after the exact decimal result is finalized.

## Regression evidence

Existing `AdminPromotionHardeningTest` remained Green, preserving schedule, category targeting, Buy-X-Get-Y, best-discount selection, fixed clamp, and type-normalization semantics.

New `PromotionMoneyIntegrityTest` covers:

- Admin rejection of over-precision discount/minimum values;
- rejection of percentage values above `100.00%`;
- exact minimum-subtotal boundary with binary-noise input (`0.1 + 0.2`);
- one-cent-below threshold rejection;
- order-percentage half-cent rounding (`12.50% × 0.20 = 0.025 → 0.03`);
- category-percentage Half-Up rounding from exact eligible subtotal;
- exact-cent Buy-X-Get-Y cheapest-free-unit calculation;
- defensive subtotal clamp for a legacy `250.00%` rule.

Hardening CI #2400 passed the full suite: **852 tests / 17,769 assertions**, including both promotion test classes, clean MySQL migration, syntax checks, Laravel boot/routes, Blade/config compilation, frontend dependency audit, shared browser interaction tests, and production asset build.

## QAS runtime evidence

QAS was promoted from `86f1b182` to `9043b6ce`.

Post-deploy verification confirmed:

- exact deployed HEAD `9043b6cebd2c3cdae14da8e0cb9e5ee948458072`;
- maintenance OFF;
- no pending migrations;
- scheduler healthy;
- queue worker healthy;
- failed jobs 0;
- strict ops health Green.

A transaction-wrapped runtime smoke passed against the deployed Promotion controller/engine path:

- `0.1 + 0.2` qualified exactly at a `0.30` minimum threshold;
- `0.29` remained below the threshold;
- order percentage half-cent rounding produced `0.03`;
- category percentage used only the eligible exact subtotal and produced `0.03`;
- Buy 2 Get 1 selected the cheapest exact-cent free unit (`0.10`);
- a legacy `250.00%` rule was clamped to the `0.20` subtotal;
- Admin over-precision input was rejected;
- percentage `100.01` was rejected;
- rollback cleanup left no smoke promotion/category records behind.

## Next gate

Keep authenticated Promotion editor/cart/checkout browser acceptance in the dedicated systematic QAS testing phase. Continue the Business Process & ERP Integrity audit at the next genuinely unclosed accounting/monetary boundary. Production release gates remain separate.
