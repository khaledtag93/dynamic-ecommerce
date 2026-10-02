# Admin Orders Row Payment & Currency Integrity Checkpoint — 2026-10-02

## Status

- Branch: `sec03-framework-upgrade`
- Final application / CI / QAS SHA: `94a1facad7e5eca2b45c624ac83c438cec2d2870`
- Currency-label commit: `e42f2fba` — `fix: preserve order row currencies`
- Final ledger-backed commit: `94a1faca` — `fix: derive order row net paid from ledger`
- Hardening CI #2435: **Green** for the currency-label slice
- Hardening CI #2436: **Green** on the final application revision
- QAS application/static health: **HTTP 200**
- QAS maintenance: **OFF**
- QAS migrations: **none pending**
- QAS failed jobs: **0**
- QAS scheduler / queue worker: **healthy**
- QAS strict ops health: **Green**
- QAS queue observation: **1 non-failed database job pending during verification; worker remained healthy**
- Production: **unchanged**
- Scope status: **CLOSED for source + full CI + deployed-QAS runtime evidence**

## Scope

This checkpoint closes row-level financial display integrity in Admin Orders:

- Total uses the order's normalized currency instead of a hard-coded EGP label.
- Refunded uses the same normalized order currency.
- Net paid uses the same normalized order currency.
- Lowercase currency codes are normalized for display; blank currency keeps the existing EGP fallback.
- Net paid is derived from captured Payment ledger rows minus processed OrderRefund ledger rows.
- Unpaid orders with no captured payments therefore show zero net paid instead of the theoretical order value.
- The calculation is prepared in the controller without per-row N+1 ledger queries.

## Reproduced currency defect

On QAS application `8a7e5414`, a transaction-wrapped probe created a temporary order with:

- currency `USD`;
- grand total `3.00`;
- refund total `1.00`.

The actual Admin Orders controller + list partial rendered:

- Total as `EGP 3.00`;
- Refunded as `EGP 1.00`;
- Net paid as `EGP 2.00`;
- `ADMIN_ORDERS_CURRENCY_DEFECT=YES`.

The probe rolled all temporary data back.

Commit `e42f2fba` normalized the order currency and reused it for all three row values. Hardening CI #2435 passed and QAS was promoted to that revision.

## Reproduced net-paid defect

After the currency label fix was on QAS `e42f2fba`, the probe was extended with a second temporary order:

- currency `USD`;
- grand total `4.00`;
- payment status `unpaid`;
- captured Payment rows: `0`;
- refund total `0.00`.

The Admin Orders row still rendered `Net paid: USD 4.00`.

Runtime markers were:

- `UNPAID_PAYMENT_COUNT=0`;
- `UNPAID_SHOWS_NET_PAID_4=YES`;
- `ADMIN_ORDERS_NET_PAID_DEFECT=YES`;
- `ROLLBACK_OK`.

This proved that the old row formula represented order value less refunds, not money actually captured.

## Final integrity policy

Final `94a1faca` makes row-level Net paid ledger-backed:

- captured amount is the sum of Payment rows with `paid` or `refunded` lifecycle status;
- processed refund amount is the sum of OrderRefund ledger rows;
- both aggregates are loaded with the paginated Order query, avoiding N+1 queries;
- decimal aggregates are normalized to two decimals with `BigDecimal`;
- Net paid is captured minus recorded refunds and is clamped at zero to preserve the prior non-negative UI contract;
- the Blade row consumes the prepared ledger-derived value rather than recomputing money from `grand_total`;
- Total and Refunded remain order-value fields, while Net paid now represents payment-ledger evidence.

## Regression coverage

`AdminOrderLiveListTest` includes:

- `test_order_rows_use_normalized_currency_and_ledger_backed_net_paid`.

The scenario verifies both full-page and live-list rendering:

- lowercase `usd` is displayed as `USD`;
- captured payments `0.10 + 0.20 = 0.30`;
- recorded refund `0.10`;
- Total renders `USD 0.30`;
- Refunded renders `USD 0.10`;
- Net paid renders `USD 0.20`;
- the invalid EGP label is absent;
- a separate unpaid USD `4.00` order with no payments renders `Net paid: USD 0.00`, not `USD 4.00`.

Local PHP syntax checks and `git diff --check` passed. The authoritative PHP 8.3 / MySQL gate is Hardening CI #2436; its complete PHPUnit suite, clean MySQL migration, Laravel boot/routes, Blade compile, browser interaction tests, frontend audit and production build all passed.

## Final QAS runtime evidence

QAS was promoted from `e42f2fba` to exact application SHA `94a1facad7e5eca2b45c624ac83c438cec2d2870`.

The transaction-wrapped probe then returned:

- `ORDER_CURRENCY=USD`;
- `HAS_EGP_TOTAL=NO`;
- `HAS_USD_TOTAL=YES`;
- `HAS_EGP_REFUND=NO`;
- `HAS_USD_REFUND=YES`;
- `HAS_EGP_NET=NO`;
- `HAS_USD_NET=YES`;
- `ADMIN_ORDERS_CURRENCY_DEFECT=NO`;
- `UNPAID_PAYMENT_COUNT=0`;
- `UNPAID_SHOWS_NET_PAID_4=NO`;
- `ADMIN_ORDERS_NET_PAID_DEFECT=NO`;
- `ROLLBACK_OK`.

Post-deploy verification confirmed exact remote HEAD `94a1facad7e5eca2b45c624ac83c438cec2d2870`, maintenance OFF, no pending migrations, failed jobs 0, scheduler OK, queue worker OK and strict ops health Green.

## Next boundary

Continue the Business Process & ERP Integrity audit from exact verified QAS revision `94a1faca`.

Do not reopen Admin Orders row payment/currency integrity, Admin Dashboard currency integrity, or already-closed money boundaries without new reproduced evidence. Controlled authenticated browser acceptance remains queued for the dedicated documented testing phase. Production release gates remain separate.
