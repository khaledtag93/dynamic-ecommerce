# POS Hold / Resume V1

Date: 2026-09-24  
Branch: `v42-clean-baseline`  
Verified application head: `8bf77cc`  
Hardening CI: `36044608065`

## Goal

Let a cashier pause an in-progress POS sale, immediately start another sale, and safely return to the paused cart later without reserving inventory or writing an order/payment before checkout.

## Implemented

- POS carts now support a dedicated `held` status.
- Held carts store:
  - optional hold label
  - held timestamp
  - optional customer name
  - sale notes
- A cashier can hold only their own non-empty open POS cart.
- Holding a sale:
  - clears the active `open_token`
  - moves the cart to the held queue
  - preserves cart items, quantities, customer name and notes
  - creates an admin activity-log entry
  - does **not** create an Order or Payment
  - does **not** change inventory
- Visiting POS after holding creates a fresh active cart for the same cashier.
- The POS workspace shows the cashier's held sales with label/date/unit count/current stored cart total and Resume / Discard actions.
- Resume is cashier-owned and transaction-protected.
- Resume serializes on the cashier user row so concurrent resume requests cannot safely produce two active carts for one cashier.
- If the cashier already has an active cart with items, resume is blocked until that sale is held or cleared.
- If the current active cart is empty, it is safely abandoned and the selected held cart becomes the cashier's active cart.
- Discard marks the held cart abandoned without changing inventory or deleting historical cart data.
- Clear cart now also removes a stale hold label after a resumed sale.
- English/Arabic copy and validation states are included.

## Integrity model

1. A held sale never reserves stock.
2. A held sale never creates an Order, Payment or Inventory Movement.
3. Only the owning cashier can hold, resume or discard their POS cart through this workflow.
4. Resume uses a database transaction plus cashier-row locking to serialize competing resume requests.
5. A non-empty active cart cannot be silently replaced by another held cart.
6. The unique cashier `open_token` remains the single-active-cart invariant.
7. Stock, product/variant validity and current prices are still rechecked by the existing POS logic before the final sale is written.
8. Discard is operational cleanup only; it does not affect stock.

## Deliberate V1 boundaries

- Held carts do **not** reserve stock or freeze prices.
- The total shown in the held queue is the stored cart snapshot for orientation; checkout remains authoritative.
- No cross-cashier transfer or manager takeover is included yet.
- No automatic held-cart expiry/cleanup policy is included yet.
- No offline synchronization is included.
- Customer attachment to an existing customer account is a later POS slice; V1 preserves the optional customer name only.

## Automated coverage

`PosCashierTest` now also verifies:

1. Hold preserves items/customer/notes and writes no sale/payment/inventory mutation.
2. POS creates a fresh open cart after the previous cart is held.
3. Resume safely replaces only an empty current cart.
4. Resume preserves the held cart contents and does not touch inventory.
5. Resume is blocked when the current POS sale contains items.
6. Another cashier cannot resume or discard somebody else's held sale.
7. Discard moves the held cart to abandoned state without stock/order/payment changes.
8. Hold/resume/discard activity-log actions are attributed to the cashier.

Hardening CI run `36044608065` passed at application head `8bf77cc` with **177 tests (1047 assertions)**, clean MySQL migration, Laravel boot/routes, Blade/config compilation and frontend production build.

## Consolidated QAS focus

1. Scan several items, enter optional customer name/notes and hold the sale.
2. Confirm the held card shows the expected label, units and stored total.
3. Confirm a new empty cashier cart is available immediately.
4. Build a second active sale and verify Resume is blocked while it contains items.
5. Hold or clear the active sale, then resume the original held sale.
6. Confirm items, quantities, customer name and notes return correctly.
7. Change catalog price/stock while a sale is held and confirm final checkout rechecks current values/availability.
8. Repeatedly click Resume from competing tabs and confirm only one active cart exists.
9. Verify another cashier cannot resume/discard the held sale.
10. Discard a held sale and confirm no Order, Payment or Inventory Movement is created.
11. Review English/Arabic desktop/mobile layout, RTL alignment and scanner focus after hold/resume.

QAS application HEAD remains `0a08253`. Production and `main` remain unchanged.
