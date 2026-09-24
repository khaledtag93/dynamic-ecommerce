# Purchases V2 receipt hardening — 2026-09-24

Working branch: `v42-clean-baseline`. Following the QAS upload, the operator provided server-side `git rev-parse HEAD` output matching `0a08253d61c26f7be841de93c9e5b7ad2d7efdfe` on 2026-09-24. The public login returned HTTP 200. Authenticated purchase behavior has not yet been reviewed. Production is unchanged.

## Behavior

- Receiving locks the current purchase row and runs the status check, item validation, inventory movements and final status update in one database transaction. A received order returns an informational replay result without adding stock again. Only ordered purchases with items can be received.
- Every line is checked before stock changes. Deleted products, missing/deleted variants, variants belonging to another product, and variant products without a selected variant block the entire receipt. A failed receipt leaves the order ordered and inventory unchanged.
- Simple products increase product quantity; selected variants increase variant stock. Cost and expiration details remain attached to the relevant inventory target/movement. Each movement records its purchase item ID for traceability.
- Order creation validates the product–variant relationship inside a transaction while locking the referenced catalog rows, so a cross-product variant or absent variant for a variant product cannot be saved through the admin form.
- The admin list counts only ordered purchases as awaiting receipt. Only ordered records show the receive action, which uses the existing in-app confirmation. A replay shows a warning rather than a second success. The create form requires a variant where applicable and escapes dynamic option/input content.
- English and Arabic strings cover the new confirmations and validation states.

## Verification

- `PurchaseReceivingHardeningTest` covers repeated receipt, multi-line balances, selected-variant stock, draft/cancelled/empty rejection, all-or-nothing invalid lines, missing/mismatched variants, invalid admin creation and replay feedback.
- [Hardening CI run 36023870549](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36023870549) passed at code commit `e723b0c`: PHP syntax, clean MySQL migration, Blade compilation, 122 tests (628 assertions), and frontend build. Final application head `0a08253` also passed [Hardening CI run 36024229399](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36024229399) and matches the QAS server HEAD supplied by the operator. Authenticated admin QAS review is pending. Production and `main`: unchanged.

## QAS review focus

1. Create a simple-product purchase and a variant-product purchase. Check supplier, line costs, totals and bilingual form errors for missing or cross-product variants.
2. Confirm receipt on an ordered record from list and detail views. Check exact before/after quantity, cost, expiration date and one movement per line.
3. Repeat the receive HTTP action and verify the informational message, unchanged quantities and movement count. Check draft/cancelled records have no receive button and their direct receive requests are rejected.
4. Check English/Arabic desktop/mobile layout, confirmation, keyboard focus, RTL alignment and form validation restoration.

If old ordered records contain deleted products or invalid variant links, they require data repair before receipt; this batch intentionally does not silently skip those lines.
