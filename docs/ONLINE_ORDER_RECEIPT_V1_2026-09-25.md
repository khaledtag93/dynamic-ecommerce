# Online Order Receipt V1 — 2026-09-25

## Goal

Give customers and authorized order staff a clean printable record of an online order without pretending the document is a jurisdiction-specific tax or fiscal invoice.

## Scope

- Added a shared A4/browser-print Order Receipt view for persisted orders.
- Customers can open the receipt only for orders owned by their authenticated account.
- Admin access stays behind the existing `orders.view` permission boundary.
- Customer Order Details and Order Success now expose **Print receipt**.
- Admin Order Details exposes the same receipt from the back office.
- The receipt uses the persisted order number as its reference.
- Store identity/contact information comes from existing Store Settings.
- Item names, variants, SKU, quantity, unit price, line total, order totals, coupon, refunds, payment state/reference, delivery details and notes are rendered from persisted order data.
- The receipt is read-only and cannot create or modify orders, payments, refunds, delivery state, reservations or inventory.

## Financial semantics

This V1 is intentionally an **order receipt**, not a tax/fiscal invoice.

The document explicitly states that limitation so merchants do not mistake it for a compliant tax document before the merchant jurisdiction, registration details, numbering requirements, tax/VAT rules and required legal fields are defined.

Tax is displayed only when a persisted order already contains a non-zero tax amount. No tax is calculated by the receipt.

## Currency fidelity

The touched Order Details, Order Success and Admin Order Details surfaces now render the order's persisted currency instead of hard-coded EGP.

The customer Order Details item total also uses the persisted `line_total` field.

## Access and safety

- Customer route: authenticated order owner only.
- Admin route: existing Admin middleware + `orders.view`.
- No public token or guessable unauthenticated receipt route was introduced.
- No financial, stock or fulfillment mutation exists in the receipt flow.
- Existing server-side order/payment/refund records remain authoritative.

## Localization / RTL

- The receipt uses the current application locale and document direction.
- Core receipt labels and the non-fiscal disclaimer are available in Arabic and English.
- Print CSS is A4-focused and removes the on-screen toolbar during printing.

## Regression coverage

`tests/Feature/OnlineOrderReceiptTest.php` verifies:

- owner-only customer receipt access;
- authorized Admin receipt access;
- persisted non-EGP currency rendering;
- refund/net total rendering;
- item snapshot rendering;
- explicit non-tax/fiscal disclaimer;
- Arabic receipt copy.

## QAS acceptance checks

1. Open an online order from My Orders in English and Arabic and print/preview the receipt.
2. Confirm order number, items, currency, totals, refunds and payment status match Order Details exactly.
3. Confirm another customer cannot open the receipt URL.
4. Confirm an Admin with `orders.view` can print the same order record.
5. Verify A4 browser print/PDF output in LTR and RTL with no clipped columns.
6. Verify the receipt is clearly described as non-tax/non-fiscal until the Tax/VAT policy is implemented.

## Release state

- Source: `v42-clean-baseline`.
- Automated CI: pending branch-head verification at this checkpoint.
- QAS: not yet claimed for this slice.
- Production: unchanged.
