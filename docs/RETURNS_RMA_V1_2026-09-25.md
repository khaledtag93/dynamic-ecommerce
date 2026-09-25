# Returns / RMA V1 — 2026-09-25

## Scope
Returns / RMA V1 adds a controlled customer-to-admin return lifecycle on the V42 working line.

### Customer flow
- Authenticated customers can open **My Returns**.
- A return can be requested only for the customer's own paid delivered/completed order.
- Requests select explicit order items, quantities, reason and requested resolution (refund or exchange).
- Existing active return quantities reserve the remaining returnable quantity so duplicate requests cannot exceed the original purchased quantity.
- Customers can inspect status and audit notes and may cancel only while the request is still Requested.

### Admin flow
- Returns & RMA uses the existing `orders.view` / `orders.manage` boundaries.
- The manager list is live/no-reload for search, status and pagination.
- Lifecycle: Requested → Approved → Received → Completed, with Requested → Rejected and customer Requested → Cancelled side paths.
- Approval records accepted quantities without restoring inventory.
- Receiving requires the complete approved quantity in V1 and records an explicit restock quantity per line. Unsellable returned units can therefore be received without being added back to stock.
- Restock uses `return_restock` inventory movements and keeps the RMA reference in movement metadata.
- Completion can record a refund, an exchange order, or completion notes.

## Financial and ownership guards
- RMA refunds use the canonical Order refund ledger and write `return_request_id` on the refund record.
- Refund amount is capped by the value of received items whose requested resolution is Refund.
- The normal remaining order refundable balance is still enforced by `OrderActionService`.
- An exchange order must be different from the original order and belong to the same customer.
- Lifecycle mutations are row-locked and audit logged.

## Data
New tables:
- `return_requests`
- `return_request_items`

Existing `order_refunds` gains nullable `return_request_id`.

All new MySQL foreign-key/index names are explicit and short.

## Verification
Focused regression coverage: `tests/Feature/ReturnRequestWorkflowTest.php`.

The suite covers:
1. customer request → approve → receive/restock → complete/refund;
2. prevention of RMA over-refund beyond received refundable item value;
3. prevention of linking an exchange order owned by another customer.

## Release state
- Source implementation: working branch `v42-clean-baseline`.
- QAS: not claimed for this slice until the normal consolidated deployment/review checkpoint.
- Production: unchanged.
- Tax/VAT treatment, carrier-issued return labels, partial receiving across multiple warehouse events, and automated exchange-order creation remain later policy/product slices.
