# Catalog Price Money Integrity Checkpoint — 2026-10-01

## Status

- Branch: `sec03-framework-upgrade`
- Application / CI / QAS SHA: `cff53feae877021818ba4073d860cdbaca248d30`
- Commit: `fix: harden catalog price money boundaries`
- Hardening CI: **#2402 Green**
- Full PHPUnit: **860 passed / 17,806 assertions**
- QAS application/static health: **HTTP 200**
- QAS maintenance: **OFF**
- QAS migrations: **none pending**
- QAS failed jobs: **0**
- QAS strict ops health: **Green**
- Production: **unchanged**
- Scope status: **CLOSED for source + full CI + deployed-QAS runtime evidence**

## Integrity boundary

Catalog selling-price inputs now match the persisted `DECIMAL(10,2)` contract across all active Admin write paths:

- simple-product `base_price` and `sale_price` accept at most two decimal places and cannot exceed `99,999,999.99`;
- variant `price` and `sale_price` use the same precision/range boundary;
- bulk variant pricing validates the same boundary before mutating variant rows;
- inline Product-list base/sale edits use the same boundary;
- simple sale-vs-base and variant sale-vs-price comparisons use exact `BigDecimal` values instead of binary floats;
- variant normalization preserves decimal strings rather than converting prices to floats before persistence;
- generated/new variants preserve the entered decimal representation;
- the existing business rule remains unchanged: sale price may equal, but may not exceed, the corresponding regular price.

This slice intentionally does not change `cost_price` / `inventory_cost_price` authority; those are covered by the Product Cost and inventory-valuation integrity scopes.

## Regression evidence

New `CatalogPriceMoneyIntegrityTest` covers:

- simple Product editor rejection of base/sale over-precision;
- exact acceptance and persistence of the `DECIMAL(10,2)` maximum;
- rejection above the database range;
- variant price/sale over-precision rejection;
- bulk variant over-precision rejection before row mutation;
- exact variant sale-price equality;
- inline base/sale over-precision rejection without persistence;
- exact inline base/sale equality at the same cent.

Existing `AdminProductEditorExperienceTest` and `CatalogStockAuditTest` remained Green, preserving Product/Variant editor, stock, inventory-history, and structural integrity behavior.

Hardening CI #2402 passed the full suite: **860 tests / 17,806 assertions**, plus clean MySQL migration, syntax checks, Laravel boot/routes, Blade/config compilation, frontend dependency audit, shared browser interaction tests, and production asset build.

## QAS runtime evidence

QAS was promoted from `9043b6ce` to `cff53fea`.

Post-deploy verification confirmed:

- exact deployed HEAD `cff53feae877021818ba4073d860cdbaca248d30`;
- maintenance OFF;
- no pending migrations;
- scheduler healthy;
- queue worker healthy;
- failed jobs 0;
- strict ops health Green.

A transaction-wrapped runtime smoke against the deployed ProductForm rules/business methods and database passed:

- exact `99,999,999.99` simple price validated;
- simple base/sale over-precision was rejected;
- `100,000,000.00` was rejected as above `DECIMAL(10,2)` capacity;
- equal-cent simple sale/base was accepted and a higher sale was rejected;
- variant price/sale over-precision was rejected;
- exact variant schema maximum validated;
- variant normalization preserved `0.30` / `0.20` exactly;
- equal-cent variant comparison was accepted and a higher sale was rejected;
- product and variant maximum values persisted exactly;
- rollback cleanup removed all smoke records.

## Next gate

Keep authenticated Product editor / variants / inline-price browser acceptance in the dedicated systematic QAS testing phase. Continue the Business Process & ERP Integrity audit at the next genuinely unclosed accounting/monetary boundary. Production release gates remain separate.
