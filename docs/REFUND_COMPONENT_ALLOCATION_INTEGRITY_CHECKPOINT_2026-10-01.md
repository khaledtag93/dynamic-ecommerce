# Refund Component Allocation Integrity Checkpoint — 2026-10-01

## Status

- Branch: `sec03-framework-upgrade`
- Application / CI / QAS SHA: `657970ae8343c8523e00be2270c75781cd82720b`
- Hardening CI: **#2392 Green**
- Full PHPUnit: **834 passed / 17,702 assertions**
- Production: **unchanged**
- Scope status: **CLOSED for source + full CI + deployed-QAS runtime evidence**
- Authenticated browser acceptance remains a separate gate.

## Integrity boundary

Flowra now distinguishes total money returned from the commercial value being reversed:

- `refund_total` = all cash returned from captured funds.
- `commercial_refund_total` = refund against commercial order value only.
- Refund ledger rows persist component allocation for merchandise, shipping, tax, and payment excess.
- Payment-excess refunds do not reduce realized commercial revenue.
- Automatic order allocation preserves merchandise → shipping → tax → payment-excess sequencing.
- Legacy refund rows without allocation preserve merchandise-first compatibility.
- Product revenue allocation is reduced only by the merchandise refund component.
- Specific component refunds cannot exceed the component's remaining value.

## QAS promotion evidence

QAS was promoted from `496d65e5` to `657970ae`.
The deploy applied migration:
`2026_09_30_020000_add_allocation_to_order_refunds_table`.

Post-deploy evidence:
- application health: HTTP 200
- static asset health: HTTP 200- maintenance: OFF
- migration status: Ran
- scheduler: OK
- queue worker: OK
- failed jobs: 0
- strict ops health: Green

The single pending queue row observed during verification was `App\Jobs\RecordQueueHeartbeat` with attempts 0. It is the expected queue-worker health heartbeat, not a business backlog item.

## Reconciliation evidence

`commerce:reconcile-refund-ledger --after-id=0 --limit=500` was run in dry-run mode only.

Result:
`scanned=1 | changed=0 | refund-snapshot-changes=0 | commercial-refund-snapshot-changes=0 | payment-status-changes=0 | applied=0`

No `--apply` was required.

## QAS runtime evidence

A transaction-wrapped refund runtime smoke passed:
- automatic merchandise-first allocation continuing into shipping;
- tax remains untouched when the requested amount is exhausted;
- realized commercial revenue reflects only commercial refund value;
- product revenue is reduced by merchandise refund only;
- over-allocation of a selected component is blocked;
- payment-excess allocation is isolated from merchandise;
- payment-excess refund preserves commercial revenue;
- stored allocation reloads correctly;
- rollback cleanup left no smoke orders behind.

The existing transaction-wrapped QAS runtime smoke also passed Storefront/POS discount-profit behavior, Offers refunded-loss rendering, Paymob currency preflight, and rollback cleanup.

## Next gate

Run controlled authenticated browser acceptance on exact QAS revision `657970ae` for Order Details refund-history rendering/allocation selector plus the outstanding discounted checkout, POS, Offers and Paymob journeys. Then continue the next genuinely unclosed ERP/accounting boundary. Production release gates remain separate.