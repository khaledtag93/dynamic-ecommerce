# Product Cost Money Integrity Checkpoint — 2026-10-01

## Status

- Branch: `sec03-framework-upgrade`
- Application / CI / QAS SHA: `42990b774d9a1d42b2155133de3cf7b96a02a1c1`
- Commit: `fix: harden product cost money boundaries`
- Hardening CI: **#2396 Green**
- Full PHPUnit: **840 passed / 17,739 assertions**
- QAS application/static health: **HTTP 200**
- QAS maintenance: **OFF**
- QAS migrations: **none pending**
- QAS failed jobs: **0**
- QAS strict ops health: **Green**
- Production: **unchanged**
- Scope status: **CLOSED for source + full CI + deployed-QAS runtime evidence**

## Integrity boundary

The Product Cost Calculator now respects persisted decimal precision and range end to end:

- raw-material unit price, selling price, material unit price, and extra costs accept at most two decimal places and remain within `DECIMAL(12,2)`;
- material quantity accepts at most three decimal places and remains within `DECIMAL(12,3)`;
- calculations use `Brick\Math\BigDecimal` instead of binary floating-point arithmetic;
- each material line preserves the historical per-line money policy but rounds deterministically to cents with Half-Up;
- material, extra, total-cost, profit, and product cost snapshots are range-checked before persistence;
- profit margin is calculated from exact decimal profit / selling price and guarded against the `DECIMAL(8,2)` range;
- any derived overflow raises validation before persistence and the surrounding transaction restores the prior recipe/summary;
- `products.cost_price` remains the costing snapshot while `inventory_cost_price` remains independent inventory-valuation authority.

## Regression evidence

`CostCalculatorSemanticsTest` now covers:

- exact decimal material multiplication and Half-Up cent rounding;
- exact extra-cost summation, total cost, profit, and profit-margin calculation;
- rejection of money and quantity over-precision;
- aggregate derived-cost overflow with transaction rollback preserving the existing recipe;
- profit-margin overflow rejection before persistence;
- existing behavior that costing updates `cost_price` without overwriting `inventory_cost_price`.

Hardening CI #2396 passed the full suite: **840 tests / 17,739 assertions**, plus clean MySQL migration, syntax checks, Laravel boot/routes, Blade/config compilation, frontend audit, browser interaction tests, and production asset build.

## QAS runtime evidence

QAS was promoted from `1c1e386d` to `42990b77`.
Post-deploy verification confirmed exact HEAD, maintenance OFF, no pending migrations, scheduler and queue worker healthy, failed jobs 0, and strict ops health Green.

A transaction-wrapped runtime smoke passed against the deployed controller path:

- `0.050 × 0.10` produced `0.01` material cost;
- extra costs `0.10 + 0.20` produced `0.30`;
- total cost `0.31`, profit `9.69`, and margin `96.90` were exact;
- product cost snapshot became `0.31` while inventory valuation stayed `55.00`;
- selling-price over-precision was rejected;
- aggregate cost overflow was rejected and the previous summary remained intact;
- profit-margin overflow was rejected and the previous summary remained intact;
- raw-material price over-precision was rejected;
- the outer transaction rollback removed all smoke records.

## Next gate

Keep authenticated browser acceptance for Cost Calculator in the dedicated systematic QAS testing phase. Continue the Business Process & ERP Integrity audit at the next genuinely unclosed accounting/monetary boundary. Production release gates remain separate.
