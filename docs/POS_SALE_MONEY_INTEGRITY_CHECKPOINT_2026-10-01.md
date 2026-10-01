# POS Sale Money Integrity Checkpoint — 2026-10-01

## Status

- Branch: `sec03-framework-upgrade`
- Application / CI / QAS SHA: `80bfcd42e1762eb709062564a7b72bf2d4b468fa`
- Core hardening commit: `0b1b529c` — `fix: harden pos sale discount and tender money`
- Compatibility fix: `80bfcd42` — `fix: preserve empty pos discount semantics`
- Hardening CI: **#2417 Green**
- QAS application/static health: **HTTP 200**
- QAS maintenance: **OFF**
- QAS migrations: **none pending**
- QAS failed jobs: **0**
- QAS scheduler / queue worker: **healthy**
- QAS strict ops health: **Green**
- Production: **unchanged**
- Scope status: **CLOSED for source + full CI + deployed-QAS runtime evidence**

## Integrity boundary

POS sale pricing, discounts, checkout tender and persisted sale totals are now cent-authoritative.

- POS monetary request inputs reject over-precision instead of silently rounding;
- discount values preserve decimal strings through the controller boundary;
- cash tender preserves exact decimal input through checkout validation;
- product and variant current-price resolution avoids float conversion before cent normalization;
- line subtotal, line discount, order discount, grand total and change are calculated in integer cents;
- percentage discounts use exact decimal arithmetic and Half-Up cent rounding;
- line allocation remains deterministic when an order-level discount must be distributed;
- exact small-value composition such as `0.10 + 0.20 = 0.30` is preserved end to end;
- invalid over-precision fails before order mutation;
- empty discount state remains a valid no-discount state after the exact-decimal hardening;
- existing stock, ownership, cashier and sale lifecycle rules were not reopened.

## Regression coverage

Focused regressions cover:

- POS discount input over-precision rejection;
- cash-received over-precision rejection;
- rejection before sale/order mutation;
- exact `0.10 + 0.20 = 0.30` subtotal;
- exact 5% discount with `0.015` rounded Half-Up to `0.02`;
- exact `0.28` grand total;
- exact `0.02` change from `0.30` tender;
- exact persisted Order subtotal/discount/grand total;
- deterministic allocated line totals `0.09` and `0.19`;
- exact persisted Payment amount;
- exact change and discount metadata;
- compatibility for empty/no-discount carts.

The local Windows checkout cannot execute the authoritative full PHPUnit suite because the local CLI PHP version is below the repository runtime requirement. Hardening CI remains the full-suite gate.

## CI evidence

Hardening CI #2417 passed on the exact application SHA `80bfcd42e1762eb709062564a7b72bf2d4b468fa`.

The gate completed successfully after the compatibility follow-up that preserves empty discount semantics.

## QAS runtime evidence

QAS was promoted from `1e90c5c3` to `80bfcd42`.

Post-deploy verification confirmed:

- exact remote HEAD `80bfcd42e1762eb709062564a7b72bf2d4b468fa`;
- application/static HTTP 200;
- maintenance OFF;
- no pending migrations;
- scheduler and queue worker healthy;
- failed jobs 0;
- strict ops health Green.

A transaction-wrapped deployed runtime smoke passed:

- first unit price snapshot `0.10`;
- second unit price snapshot `0.20`;
- over-precision discount rejected;
- subtotal exactly `0.30`;
- 5% discount exactly `0.02`;
- grand total exactly `0.28`;
- over-precision cash tender rejected with no order mutation;
- successful checkout persisted exact order/payment/line totals;
- change due exactly `0.02`;
- transaction rollback cleanup succeeded.

## Next boundary

Continue the Business Process & ERP Integrity money audit from exact verified QAS revision `80bfcd42`.

Re-scan remaining business-critical monetary calculations and choose one genuinely unclosed boundary. Do not reopen POS Sale, POS Cash Shift, Cart/Checkout, Coupon, Promotion, Shipping, Fulfillment Payment, Payment Capture/Reconciliation, Refund Entry or Catalog Price integrity without a reproduced defect.

Authenticated browser acceptance remains queued for the dedicated systematic QAS testing phase. Production release gates remain separate.
