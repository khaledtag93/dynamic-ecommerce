# Returns / RMA Integrity Checkpoint — 2026-09-28

## Branch and verification state
- Branch: `sec03-framework-upgrade`
- Latest verified application SHA: `9316a092`
- GitHub application check: completed / success
- Production remains unchanged.
- QAS has not been advanced to this branch head by this checkpoint.

## Pass status
**Returns / RMA Integrity Pass: CLOSED**

## Closed invariants

### Return quantity integrity
- Active RMAs reserve returnable quantities.
- Cancelled/rejected RMAs release reservation.
- Partial approval reserves only approved quantity.
- POS returns and RMA returns share cumulative quantity boundaries.
- Return quantity cannot exceed remaining sold quantity across both paths.

### Receive / restock integrity
- V1 requires full approved quantity before Received.
- Restock quantity cannot exceed received quantity.
- Restock fails safe when product/variant provenance is missing or mismatched.
- Restock movement is recorded exactly once.
- Received/restock quantities become immutable after physical receipt.
- Return request item history cannot be deleted through the model.
- Database-level RMA parent deletion cannot cascade away item history.

### Refund settlement integrity
- RMA refund cannot exceed received refund-line value.
- Storefront discount allocation is respected.
- POS net line value is not double-discounted.
- Refund ownership is constrained to the same order/RMA.
- Refund ledger is append-only at the model layer.
- Refund ledger entries cannot be modified or deleted after recording.
- Existing refund idempotency and order balance caps remain authoritative.

### Exchange settlement integrity
- Exchange order must be different from the original order.
- Exchange order must belong to the same customer.
- Cancelled orders cannot be used as exchange orders.
- One exchange order cannot settle multiple RMAs.
- Exchange-order reuse is guarded in the service and by a database unique constraint.
- Completed RMA exchange-order links are preserved with RESTRICT delete semantics.

### RMA lifecycle / history integrity
- Requested → Approved/Rejected/Cancelled only.
- Approved → Received only.
- Received → Completed only.
- Completed/Rejected/Cancelled are terminal.
- Terminal ReturnRequest records are immutable at the model layer.
- ReturnRequest history cannot be deleted through the model.
- RMA line lifecycle mutation is allowed only in its legitimate workflow phase.
- Historical ownership/provenance relationships remain preserved by composite foreign keys.

## Verified commit chain
- `de01501b` — reject cancelled exchange orders
- `69d91a6d` — prevent exchange order reuse across returns
- `df377ca5` — preserve RMA item history
- `ee9ea0c9` — preserve composite RMA history constraint
- `e5c5dea5` — preserve RMA exchange order history
- `211b2191` — make refund ledger append only
- `407f30aa` — lock RMA item lifecycle history
- `2fa498b2` — lock terminal RMA history
- `9316a092` — exercise RMA history DB constraint directly

Final application head `9316a092` was verified Green by GitHub CI.

## Next phase
Continue the Business Process & ERP Integrity Audit on the next meaningful domain boundary rather than extending RMA micro-rules.

Recommended next domain:
**Customer Account Statement / Accounting Evidence Integrity**
- reconcile orders, payments, refunds, returns, and statement balances from authoritative ledgers
- verify historical/date-bounded statement totals
- verify refund/return presentation consistency
- verify export/print use the same bounded dataset and totals
- verify no duplicated or missing ledger events under partial refunds and multiple payments

Keep QAS promotion separate until a deliberate deployment checkpoint is chosen.
