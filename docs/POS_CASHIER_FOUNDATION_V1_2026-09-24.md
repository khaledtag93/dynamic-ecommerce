# POS / Cashier Foundation V1

Date: 2026-09-24  
Branch: `v42-clean-baseline`  
Verified application head: `ef9797d`  
Hardening CI: `36041425089`

## Goal

Add a commercial-grade cashier foundation that reuses the catalog, barcode, inventory, order, payment, and profit ledgers without routing counter sales through the storefront checkout flow.

## Implemented

- Dedicated `pos.manage` permission and a system `cashier` role with dashboard + POS access only.
- Dedicated admin Point of Sale workspace and sidebar entry.
- Persistent open POS cart per cashier in the database.
- Exact barcode scan through the shared product identifier resolver.
- One accepted scan adds one unit; variant products require the exact variant barcode.
- Cart quantity is capped by current stock and protected by an expected-quantity stale-write guard.
- Remove-item and clear-cart actions are cashier-owned and lock-protected.
- Checkout supports:
  - POS Cash
  - POS Card Terminal
- Cash checkout requires received cash to cover the final locked total and records change due.
- Card-terminal checkout does not require a cash amount.
- Final sale re-locks the cashier cart, cart items, products, and variants and recalculates current prices/costs before writing.
- A POS sale creates the canonical Order / Order Items / Payment / Inventory Movement records so existing operational reporting remains reusable.
- POS orders are marked with `sales_channel = pos`.
- POS sale orders are completed/paid and pickup/delivered at the counter.
- Selected order contact/shipping columns are nullable so POS does not invent fake customer emails, phones, or addresses.
- Inventory deduction happens only during final checkout, not during scanning or cart editing.
- Completed-cart replay returns the original sale and cannot duplicate the order, payment, or inventory deduction.
- Sale completion creates a cashier audit-log entry.
- Sale Summary access is limited to the owning cashier unless the user has broader order-review access.
- English/Arabic UI copy and focused regression coverage are included.

## Integrity model

1. Scanner lookup never guesses between ambiguous legacy identifiers.
2. Product/variant activity and current barcode are rechecked while locked when scanning.
3. Cart quantities cannot exceed current stock at scan/update time.
4. Checkout locks the cart and every sale target before validating stock.
5. Prices and costs are recalculated from current locked catalog records at checkout.
6. Inventory is deducted through the existing conditional `InventoryService::decrease()`.
7. Order, items, payment, inventory movements, completed cart state, and audit entry are written inside one database transaction.
8. Completed-cart checkout is replay-safe.

## Deliberate V1 boundaries

- No discount/coupon UI is included yet.
- No partial/tender-split payment is included yet.
- Card-terminal V1 records an operator-confirmed paid terminal transaction; direct terminal API integration is future work.
- POS returns/refunds continue to rely on the existing order/payment refund architecture and will get a dedicated cashier UX later.
- The Sale Summary is not yet presented as a formal fiscal receipt/invoice.
- Formal receipt/invoice printing, receipt numbering/configuration, and printer formatting are the next intended slice.

## Automated coverage

`PosCashierTest` verifies:

1. Cashier role isolation from broader order management.
2. Customer denial.
3. Simple barcode scan without inventory mutation.
4. Exact variant-barcode requirement.
5. Scan quantity stock cap.
6. Stale quantity and stock-overflow protection.
7. Atomic cash checkout, paid ledger, inventory movement, profit snapshot, audit log, and replay safety.
8. Insufficient-cash rollback.
9. Card-terminal checkout without cash input.
10. Sale-summary ownership/access controls.

Hardening CI run `36041425089` passed at application head `ef9797d` with **173 tests (969 assertions)**, clean MySQL migration, Laravel boot/routes, Blade/config compilation, and frontend production build.

## Consolidated QAS focus

1. Log in as a Cashier-role account and verify POS access without Orders/Settings access.
2. Scan simple and variant labels repeatedly and verify one scan = one unit.
3. Verify out-of-stock and quantity-cap handling.
4. Change quantities and test stale-tab protection.
5. Complete Cash sale and verify total, cash received, change, order, payment, inventory movement, and profit snapshot.
6. Complete Card Terminal sale and verify paid ledger/provider.
7. Repeat the checkout request and confirm no duplicate sale/stock/payment.
8. Review recent sales and cashier ownership boundaries.
9. Review EN/AR desktop/mobile layout, RTL and scanner focus.
10. Confirm storefront checkout payment methods remain unchanged.

QAS application HEAD remains `0a08253`. Production and `main` remain unchanged.
