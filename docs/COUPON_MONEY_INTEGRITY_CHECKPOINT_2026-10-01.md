# Coupon Money Integrity Checkpoint — 2026-10-01

## Status

- Branch: `sec03-framework-upgrade`
- Application / CI / QAS SHA: `86f1b1822a9303f06be2cee35644c2447371af9e`
- Commit: `fix: harden coupon money boundaries`
- Hardening CI: **#2398 Green**
- Full PHPUnit: **845 passed / 17,754 assertions**
- QAS application/static health: **HTTP 200**
- QAS maintenance: **OFF**
- QAS migrations: **none pending**
- QAS failed jobs: **0**
- QAS strict ops health: **Green**
- Production: **unchanged**
- Scope status: **CLOSED for source + full CI + deployed-QAS runtime evidence**

## Integrity boundary

Coupon monetary configuration and calculation now follow the persisted decimal contract end to end:

- coupon `value`, `min_order_amount`, and `max_discount_amount` accept at most two decimal places and remain within `DECIMAL(12,2)`;
- percentage coupons cannot exceed exactly `100.00%` without relying on binary float comparison;
- coupon subtotal eligibility normalizes the commercial subtotal to exact cents before comparing the minimum threshold;
- fixed discounts, percentage discounts, maximum-discount caps, and subtotal clamps use `Brick\Math\BigDecimal`;
- percentage discount fractions are rounded to cents with explicit Half-Up policy;
- the public discount API remains float-compatible only after the exact decimal result has been finalized, preserving existing Cart/Checkout callers without retaining float arithmetic in coupon calculation;
- `CouponService` uses the same exact threshold rule as `Coupon::calculateDiscount()`, preventing eligibility/calculation disagreement.

## Regression evidence

`CouponMoneyIntegrityTest` covers:

- Admin rejection of over-precision coupon value, minimum-order amount, and maximum-discount amount;
- rejection of a percentage above `100.00%`;
- exact minimum-subtotal boundary with binary-noise input (`0.1 + 0.2`);
- one-cent-below threshold rejection;
- percentage half-cent rounding (`12.50% × 0.20 = 0.025 → 0.03`);
- exact maximum-discount cap behavior;
- fixed-discount clamp to the subtotal.

Hardening CI #2398 passed the full suite: **845 tests / 17,754 assertions**, including `CouponMoneyIntegrityTest`, clean MySQL migration, syntax checks, Laravel boot/routes, Blade/config compilation, frontend dependency audit, shared browser interaction tests, and production asset build.

## QAS runtime evidence

QAS was promoted from `42990b77` to `86f1b182`.

Post-deploy verification confirmed:

- exact deployed HEAD `86f1b1822a9303f06be2cee35644c2447371af9e`;
- maintenance OFF;
- no pending migrations;
- scheduler healthy;
- queue worker healthy;
- failed jobs 0;
- strict ops health Green.

A transaction-wrapped runtime smoke passed against the deployed Coupon model/controller path:

- `0.1 + 0.2` qualified exactly at a `0.30` minimum threshold;
- `0.29` remained ineligible;
- a fixed `0.05` discount applied exactly at the threshold;
- `12.50%` of `0.20` rounded Half-Up to `0.03`;
- a percentage coupon respected an exact `0.03` cap;
- a `1.00` fixed coupon clamped to a `0.20` subtotal;
- Admin over-precision input was rejected;
- percentage `100.01` was rejected;
- rollback cleanup left no smoke coupon records behind.

## Next gate

Keep authenticated Coupon editor/cart/checkout browser acceptance in the dedicated systematic QAS testing phase. Continue the Business Process & ERP Integrity audit at the next genuinely unclosed accounting/monetary boundary. Production release gates remain separate.
