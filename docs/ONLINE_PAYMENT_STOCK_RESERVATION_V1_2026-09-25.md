# Online Payment Stock Reservation V1
Date: 2026-09-25
Working branch: `v42-clean-baseline`
Application source checkpoint: `e58e82e9`

## Goal

Prevent online-payment orders from holding inventory indefinitely or converting inventory into a final sale before payment is confirmed.

V1 introduces an explicit stock-reservation lifecycle:
- Reserved
- Committed
- Released
- Expired

The implementation keeps existing physical stock semantics simple: inventory is decremented when an online reservation is created so concurrent checkout cannot oversell it. The reservation ledger distinguishes reserved stock from committed sale stock. Release/expiry restores inventory exactly once.

## Reservation ledger

New table: `order_stock_reservations`

Each reservation stores:
- order;
- order item;
- product / optional variant;
- quantity;
- status;
- reserved timestamp;
- expiry timestamp;
- committed timestamp;
- released timestamp;
- release reason;
- metadata.

One reservation exists per order item.

All FK/index/unique names are explicit and short for MySQL compatibility.

## Inventory movements

New movement types:
- `order_reservation`
- `reservation_release`

Existing:
- `order_out` remains the normal committed stock movement for COD / bank transfer / POS and other non-online flows.
- `refund_restock` remains the ordinary post-sale cancellation/refund restock movement.

The Inventory Movement Explorer now presents human-readable reservation movement labels.

## Online checkout

Online-payment checkout:
1. creates the Order and Order Items;
2. creates Reserved stock rows;
3. immediately decreases physical stock using `order_reservation`;
4. creates the Payment record;
5. clears the Cart only if the full transaction succeeds.

Any later checkout failure rolls the complete DB transaction back, including stock/reservations.

Non-online payment methods preserve their previous `order_out` behavior.

## Reservation duration

Payment Settings now includes:
`payment_stock_reservation_minutes`

Allowed range:
- minimum: 5 minutes;
- maximum: 1440 minutes;
- default: 30 minutes.

The setting is merchant-configurable and is not hard-coded into checkout behavior.

## Payment lifecycle integration

### Pending / Authorized
A valid online stock reservation must exist.

If a previously released/expired order re-enters the payment flow:
- inventory is re-reserved atomically;
- if stock is no longer available, payment retry is blocked before opening the gateway.

### Paid
A valid reservation is committed without a second stock decrease.

This preserves inventory quantity while making the reservation immutable as sold stock.

### Failed
Reserved inventory is released exactly once.

Replayed failure callbacks do not duplicate inventory restoration.

## Retry behavior

Before Paymob redirect/retry:
- the order is checked for cancellation;
- stock availability is revalidated;
- released/expired reservation rows are re-reserved if stock is available;
- payment returns to Pending;
- reservation expiry is refreshed.

The first payment-session start and a later retry are logged as separate event types:
- `payment_session_started`
- `payment_retry_started`

If stock is unavailable, the customer remains on the order/payment flow and receives an explicit stock-unavailable error instead of reaching the payment gateway.

## Automatic expiry

New command:
`payments:expire-stock-reservations`

Scheduler:
- every five minutes;
- `withoutOverlapping()`.

When an online reservation expires:
- Reserved inventory is restored;
- reservation becomes Expired;
- pending/authorized Payment becomes Failed with provider status `reservation_expired`;
- Order payment status becomes Failed;
- order metadata records expiry;
- admin activity log records the release;
- normal payment status notification flow is used.

Operational dependency: the existing Laravel scheduler runner must remain active on the server.

## Late paid callback after expiry

A late successful gateway callback is financial truth and is not silently rejected.

V1 behavior:
1. attempt to re-reserve the released stock atomically;
2. if stock is available, reserve + commit normally;
3. if stock is no longer available:
   - Payment is still recorded as Paid;
   - Order payment status becomes Paid;
   - no negative inventory is created;
   - Order/Payment metadata receive a `paid_without_fulfillable_reservation` exception;
   - admin activity log records the stock exception;
   - Admin Order Detail surfaces an urgent stock-review warning;
   - customer Paymob result page explains that payment is preserved but fulfillment requires inventory review.

This avoids both false payment state and silent overselling.

## Cancellation safety

Online order cancellation is reservation-aware:
- Reserved → release inventory once and then skip normal restock;
- Released / Expired → skip restock because stock is already restored;
- Committed → use normal cancellation restock because the reservation represented a completed sale;
- legacy online orders without reservation rows retain the existing cancellation behavior.

This prevents double-restock.

## Blade namespace hardening

During this slice an existing corrupted `IlluminateSupportStr` reference was found outside Workforce.

Protection was generalized:
- QAS deploy preflight scans **all** Blade views for corrupted `AppModels...` / `IlluminateSupport...` patterns;
- the historical `WorkforceBladeIntegrityTest` now recursively scans the entire `resources/views` tree.

This closes the previously Workforce-only guard gap.

## Regression coverage

New `OnlineStockReservationTest` covers:
- online checkout reservation;
- inventory reservation movement;
- Paid commit without second stock decrease;
- Failed release;
- replayed Failed idempotency;
- cancellation after release without double restock;
- retry re-reservation;
- retry rejection when stock was consumed elsewhere;
- automatic expiry command;
- Payment/Order failure status on expiry;
- late Paid callback after expiry;
- paid/no-stock exception behavior and audit log.

Updated:
- `CheckoutIdempotencyTest` constructor wiring;
- `BusinessIntegrityHardeningTest` OrderActionService constructor wiring;
- project-wide Blade namespace integrity scope.

## Known V1 boundaries

- This V1 applies reservation expiry specifically to **online payment**.
- Bank Transfer keeps the existing manual pending-order behavior because its business timeout policy is not yet defined.
- No separate `on_hand` vs `reserved` inventory columns are introduced; the reservation ledger plus inventory movements provide the distinction.
- No automatic refund is attempted for the rare late-paid/no-stock exception; financial truth is retained and the order is escalated for fulfillment/refund review.
- Reservation expiry depends on the Laravel scheduler being run by the hosting environment.

## Release status

- Application source checkpoint: `e58e82e9`.
- CI: Pending; no workflow run/status was visible for this head when checked.
- Not yet deployed to QAS.
- Production unchanged.

## Next critical-commerce slice

**Returns / RMA V1**

Required direction:
- line-level return quantities;
- reasons;
- Requested / Approved / Rejected / Received / Completed lifecycle;
- restock decision;
- refund/exchange linkage;
- no inventory restoration before physical receipt/approved restock decision;
- full audit trail.
