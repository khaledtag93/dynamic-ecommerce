# Cart & Checkout Money Composition Checkpoint — 2026-10-01

## Status

- Branch: `sec03-framework-upgrade`
- Application / CI / QAS SHA: `24022133dbf3a72f06fe4b399c4cc15504ebed1a`
- Application commit: `24022133` — `fix: harden cart checkout money composition`
- Hardening CI: **#2411 Green**
- Full PHPUnit: **870 passed / 17,852 assertions**
- QAS application/static health: **HTTP 200**
- QAS maintenance: **OFF**
- QAS migrations: **none pending**
- QAS failed jobs: **0**
- QAS strict ops health: **Green**
- Production: **unchanged**
- Scope status: **CLOSED for source + full CI + deployed-QAS runtime evidence**

## Integrity boundary

Cart and checkout order-total composition are now cent-authoritative while preserving existing API/UI numeric shapes:

- cart subtotal is derived from stored unit-price cents × integer quantity;
- coupon discount is normalized to cents and capped to merchandise subtotal;
- promotion discount preserves the existing coupon-first stacking order but is capped to the remaining subtotal after coupon discount;
- combined effective discount can no longer exceed merchandise subtotal;
- cart total is composed from integer cents and floored at zero;
- checkout normalizes subtotal, discount, tax and shipping to cents before composing the final order total;
- checkout persists canonical two-decimal subtotal, discount, shipping, tax and grand-total values;
- shipping quote still receives numeric subtotal/discount inputs, but those values are derived from canonical cents;
- payment creation therefore inherits the exact persisted order grand total;
- no coupon, promotion or shipping-engine policy was reopened or changed beyond bounding the combined effective discount to the merchandise subtotal.

## Regression coverage

Focused regressions now cover:

- `0.10 × 3 = 0.30` cart subtotal and total without binary-float drift;
- stacked coupon `0.20` + promotion `0.20` on subtotal `0.30`, producing effective coupon `0.20` + promotion `0.10`, total discount `0.30` and cart total `0.00`;
- checkout persistence of `subtotal = 0.30`, `discount = 0.00`, `shipping = 0.00`, `grand_total = 0.30`;
- generated Payment amount exactly matching the persisted order total;
- persisted order-item line total exactly `0.30`.

The local Windows checkout could not execute PHPUnit because its CLI PHP is 8.2.28 while the current repository requires PHP >= 8.3. Syntax checks and `git diff --check` passed locally; Hardening CI is the authoritative full-suite gate.
## CI evidence

Hardening CI #2411 passed on the exact application SHA with:

- **870 tests / 17,852 assertions**
- clean MySQL migration
- PHP/Bash syntax
- Laravel boot/routes
- Blade/config compilation
- dependency audits
- shared browser interaction tests
- frontend production build

## QAS runtime evidence

QAS was promoted from `c88c9bd4` to `24022133`.

Post-deploy verification confirmed:
- exact remote HEAD `24022133dbf3a72f06fe4b399c4cc15504ebed1a`
- application/static HTTP 200
- maintenance OFF
- no pending migrations
- scheduler and queue worker healthy
- failed jobs 0
- strict ops health Green
A transaction-wrapped runtime smoke passed on deployed services:

- stacked cart subtotal `0.30`;
- coupon retained `0.20`;
- promotion was capped to the remaining `0.10`;
- combined discount was capped to `0.30`;
- stacked total was exact `0.00`;
- checkout persisted subtotal `0.30`, discount `0.00`, shipping `0.00` and grand total `0.30`;
- created Payment inherited amount `0.30`;
- created OrderItem persisted line total `0.30`;
- outer rollback removed all smoke records.

## Next boundary

Continue the Business Process & ERP Integrity money audit from this exact checkpoint. Re-scan remaining business-critical monetary calculations and choose one genuinely unclosed boundary. Do not reopen Cart/Checkout, Coupon, Promotion, Shipping, Fulfillment Payment or Payment Capture/Reconciliation money integrity without a reproduced defect.

Authenticated browser acceptance remains queued for the dedicated systematic QAS testing phase. Production release gates remain separate.
