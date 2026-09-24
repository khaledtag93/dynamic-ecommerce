# POS Discounts V1

Date: 2026-09-24  
Branch: `v42-clean-baseline`

## Goal

Add controlled POS discounts without weakening cashier ownership, inventory safety, payment accounting, profit snapshots, or receipt accuracy.

## Scope

- Adds a dedicated `pos.discount` permission.
- Super Admin inherits the permission and Operations Manager receives it by default.
- The Cashier system role keeps `pos.manage` only and cannot apply discounts by default.
- Supports line-level and whole-sale discounts.
- Supports fixed EGP amounts and percentage discounts.
- Requires a reason for every applied discount.
- Allows the cart owner to remove a discount even if discount permission is later removed.
- Hold / Resume preserves discounts because they are stored on the persistent POS cart and cart items.
- Clear sale removes cart-level discount state together with the current items.
- Checkout rechecks current prices, stock, cart ownership and discount permission under the existing transaction/lock boundary.
- Checkout recalculates discount amounts from locked current prices rather than trusting browser totals.
- Order-level discount is proportionally allocated across order items so item totals/profit snapshots reconcile to the final order total.
- Order, Order Items, Payment and audit metadata retain discount snapshots and reasons.
- Customer receipt shows discount amounts but never prints the internal discount reason.

## Accounting semantics

- `Order.subtotal`: gross total before POS discounts.
- `Order.discount_total`: line discounts + sale discount.
- `Order.grand_total`: final amount payable.
- `Payment.amount`: final grand total.
- `Order.cost_total`: unchanged inventory cost snapshot.
- `Order.profit_total`: final grand total minus cost total.
- `OrderItem.line_total`: final allocated net line total after line discount and proportional sale-discount share.
- `OrderItem.profit_amount`: final net line total minus line cost.

## Safety rules

- A fixed discount cannot exceed its current eligible total.
- Percentage discounts cannot exceed 100%.
- A second sale-level discount cannot be applied if line discounts have already reduced the eligible total to zero.
- An applied discount cannot be checked out after the cashier loses `pos.discount`; the discount must be removed or the sale completed by an authorized account.
- Discount updates are owner-only and serialized through the same open-cart lock used by other POS mutations.
- Discount application does not change stock.
- Checkout remains replay-safe and inventory writes occur only once.

## Verification

Automated regression coverage is included for:
- Cashier-role discount denial.
- Combined line + sale discount totals.
- Payment amount, cash change and profit snapshots after discount.
- Discount audit entries.
- Required reasons.
- Excessive fixed/percentage guards.

Branch-head Hardening CI: pending.

## Consolidated QAS focus

1. Verify Cashier cannot open/apply discount controls.
2. Verify an authorized manager can apply fixed and percentage line discounts.
3. Verify whole-sale fixed and percentage discount behavior.
4. Hold and resume a discounted sale and confirm values persist.
5. Remove line/sale discounts and confirm totals recover exactly.
6. Complete discounted Cash and Card Terminal sales.
7. Compare POS screen subtotal/discount/total with Order, Payment, Sale Summary and receipt.
8. Verify receipt prints discount amounts but not the internal reason.
9. Verify English/Arabic, desktop/mobile and RTL layout.
10. Verify discount controls remain usable with several cart lines.

QAS remains on the previously verified `0a08253` until the consolidated deployment phase. Production and `main` remain unchanged.
