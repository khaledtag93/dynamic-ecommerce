# Deliveries V2 hardening — 2026-09-24

Working branch: `v42-clean-baseline`

## Scope

This slice hardens the existing delivery workflow without expanding it into the future carrier/driver platform described in the product roadmap.

## Implemented in source

- Delivery status mutations now run through `App\Services\Commerce\DeliveryService`.
- The target order is re-read with a database row lock inside a transaction before the transition is accepted.
- Delivery transitions are constrained instead of allowing arbitrary status jumps.
- Standard/express shipping flow:
  - Pending -> Preparing or Cancelled
  - Preparing -> Shipped or Cancelled
  - Shipped -> Out for delivery, Delivered, or Returned
  - Out for delivery -> Delivered or Returned
  - Delivered -> Returned
  - Returned/Cancelled are terminal
- Store pickup avoids shipping-only states:
  - Pending -> Preparing or Cancelled
  - Preparing -> Delivered or Cancelled
  - Delivered -> Returned
- Entering Shipped records `shipped_at` once.
- Out for delivery requires an existing `shipped_at` timestamp.
- Entering Delivered records `delivered_at` once.
- Metadata-only saves do not rewrite `delivered_at`.
- A post-delivery Returned transition preserves the delivery timestamp as historical evidence.
- Cancelling before shipment clears shipment/delivery timestamps.
- Database delivery notifications and WhatsApp delivery updates are sent only when the delivery status actually changes.
- Concurrent repeated requests are serialized; a replay that finds the requested status already current becomes a metadata-only update and does not re-notify.
- The order delivery editor disables invalid next statuses, shows shipment/delivery timestamps, and asks for an in-app confirmation before an actual status change.
- New delivery-hardening copy is registered in English and Arabic.

## Automated coverage

`tests/Feature/DeliveryHardeningTest.php` covers:
- metadata-only updates preserving `delivered_at` and sending no status notification
- blocked status skipping
- the shipped-at precondition for out-for-delivery
- lifecycle timestamps and one notification/WhatsApp dispatch per real transition
- Returned preserving historical timestamps
- Store Pickup excluding Shipped/Out-for-delivery states

## Verification state

- Source implementation: complete for this slice.
- Branch-head CI: pending at the time this note was written.
- QAS: not yet authenticated/visually verified.
- Production: unchanged.

## QAS acceptance checks

1. Standard shipping: Pending -> Preparing -> Shipped -> Out for delivery -> Delivered.
2. Confirm invalid regressions/skips are unavailable in the selector and rejected server-side.
3. Confirm a metadata-only edit (courier/tracking/ETA/notes) does not send a customer delivery notification or WhatsApp delivery update.
4. Confirm Delivered keeps its original timestamp after later metadata edits.
5. Confirm Delivered -> Returned keeps the historical delivered timestamp.
6. Store Pickup: Pending -> Preparing -> Delivered; shipping-only statuses must stay unavailable.
7. Check the confirmation modal, status copy, timestamps, responsive layout, Arabic translation and RTL behavior.
8. Confirm notification/WhatsApp behavior with the channel enabled and disabled.

## Out of scope / later roadmap

- shipping zones/rates/service levels
- driver assignment
- carrier integrations and tracking links
- pick/pack milestones
- delivery attempts/rescheduling/proof-of-delivery
- return-to-origin and COD reconciliation
