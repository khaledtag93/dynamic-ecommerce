# POS Customer Attach V1

Date: 2026-09-24  
Branch: `v42-clean-baseline`  
Verified application head: `9129998`  
Hardening CI: `36046473889`

## Goal

Let a cashier attach an existing customer account to an in-store POS sale without granting broader customer-management access, while preserving a fast walk-in sale path.

## Implemented

- POS carts now support an optional `customer_user_id` foreign-key reference.
- The cashier POS workspace includes a focused customer lookup.
- Lookup:
  - requires at least 2 characters
  - searches customer name or email
  - returns at most 8 results
  - exposes name and email only
  - excludes staff/admin accounts
- A cashier can attach a customer account to their own open POS cart.
- The attached customer can be removed to return the cart to walk-in mode.
- Attached customer selection survives Hold / Resume.
- Clearing a POS cart removes the attached customer together with other transient sale data.
- Attach/detach actions are recorded in the admin activity log.
- Walk-in sales continue to support an optional manual customer name and remain unlinked to a customer account.

## Checkout integrity

When a customer account is attached:

1. Checkout locks and re-reads the customer account inside the existing sale transaction.
2. The account must still be a customer account at checkout time.
3. Manual customer-name input cannot override the attached account.
4. The completed Order records:
   - `user_id` = attached customer ID
   - customer-name snapshot
   - customer-email snapshot
   - POS metadata customer reference
5. Shipping/billing address data is not invented for a counter sale.
6. Inventory, payment, profit and replay-safety rules remain the existing POS rules.

If the attached account is converted to a staff/admin account before checkout, checkout is rejected before Order, Payment or Inventory Movement writes.

## Customer account experience

Because the POS Order is linked through `orders.user_id`, the purchase appears in the customer's existing **My Orders** history.

Customer-facing handling was hardened for this case:

- POS orders are labelled as in-store purchases in account history.
- Order Details shows an **In-store purchase / Store pickup** panel instead of an empty Shipping Address.
- POS Cash instructions state that payment was made at the store counter.
- POS Card Terminal instructions state that payment was made by card terminal at the store counter.
- Completed POS orders remain terminal and do not expose storefront cancellation behavior.
- POS receipt and admin Sale Summary show the attached customer account email when present.

## Privacy / permission boundary

- `pos.manage` provides only the limited customer lookup required for checkout.
- POS lookup does not expose saved addresses, order history, spend, roles, internal notes or customer-management actions.
- Staff/admin accounts are excluded from the lookup and rejected by the attach service.
- Cart ownership guards prevent one cashier from attaching/detaching customer accounts on another cashier's active cart.

## Deliberate V1 boundaries

- POS does not create new customer accounts yet.
- POS does not edit customer profiles.
- POS does not use saved shipping/billing addresses for counter sales.
- No loyalty/reward logic is added.
- No phone-number lookup is added because phone is currently address-level data rather than an account field.
- Customer merge/deduplication remains a separate future capability.

## Automated coverage

`PosCashierTest` and storefront regression coverage verify:

1. Customer search returns customer accounts by name/email while excluding staff.
2. Attach stores the customer on the cashier cart and creates an audit entry.
3. Checkout links the final POS Order to the customer account and snapshots name/email.
4. Manual customer-name input cannot override an attached account.
5. Walk-in POS sales remain unlinked.
6. Another cashier cannot attach a customer to somebody else's cart.
7. Staff accounts cannot be attached.
8. Detach returns the cart to walk-in mode and creates an audit entry.
9. Checkout rechecks customer eligibility and rolls back safely if the account changed to staff.
10. A linked POS sale renders correctly in the customer's Order Details without blank shipping/courier copy.
11. POS Card Terminal payment instructions are correct in the customer account area.

An intermediate CI run exposed a Blade class-reference regression in the new customer Order Details branch. It was corrected before closure.

Final Hardening CI run `36046473889` passed at application head `9129998` with **180 tests (1094 assertions)**, clean MySQL migration, Laravel boot/routes, Blade/config compilation and frontend production build.

## Consolidated QAS focus

1. Search an existing customer by partial name and email.
2. Confirm staff accounts never appear in POS lookup.
3. Attach a customer and verify the customer card.
4. Hold and resume the sale and confirm attachment persists.
5. Detach and confirm walk-in name becomes editable again.
6. Complete attached Cash and Card Terminal sales.
7. Compare Order `user_id`, customer snapshots, payment, inventory movement and receipt.
8. Log in as the attached customer and verify the POS purchase in My Orders.
9. Verify POS Order Details shows in-store/store-pickup copy and no blank shipping address.
10. Verify Arabic/English desktop/mobile/RTL behavior and customer-search ergonomics.
11. Verify a different cashier cannot mutate the first cashier's customer attachment.

QAS application HEAD remains `0a08253`. Production and `main` remain unchanged.
