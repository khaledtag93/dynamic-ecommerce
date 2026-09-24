# Storefront customer experience cleanup — 2026-09-24

Working branch: `v42-clean-baseline`

## Goal

Improve the customer-facing storefront without disturbing checkout, cart, pricing, stock, or payment business logic. This iteration focuses on removing internal/demo-like copy, keeping theme settings effective on the homepage, and tightening English/Arabic direction behavior.

## Implemented

### Product detail
- Removed customer-facing internal wording about stock being sourced from product settings.
- Replaced it with concise checkout-facing availability guidance.
- Removed the technical `Gallery images` count from Quick facts.
- Removed the fallback `More product details will be added soon.`; the Product details panel now renders only when an actual description exists.
- Quick facts now focus on customer-useful availability, available quantity, category, and purchase options.

### Checkout
- Kept the existing aligned billing-address toggle because its spacing/control alignment is already correct.
- Replaced overly technical or promotional labels with clearer customer wording:
  - Delivery ready → Shipping details
  - Last-minute boost → You may also like
  - Personalized offers → Available offers
  - Encrypted checkout messaging → Order details reviewed before submission
  - Clear payment choice → Payment method shown clearly
  - Shipping reviewed before payment → Delivery address confirmed before order
- No payment-method, totals, address, coupon, promotion, or order submission behavior was changed.

### Home hero / branding
- Replaced hard-coded dark-blue/orange hero campaign surfaces with the existing storefront brand tokens.
- Hero viewport, campaign gradients, CTA shadowing, shortcut cards, and shortcut icons now follow configured primary/secondary/accent/surface colors.
- Corrected hero navigation direction: LTR uses normal previous-left / next-right chevrons; RTL flips the direction automatically.
- Corrected the primary CTA arrow to point forward in LTR and flip in RTL.

### Cart
- Replaced customer-visible internal recommendation labels `Personalized offers`, `Smart offers`, and `Return path` with `Available offers`, `Suggested offers`, and `Recently viewed`.
- Recommendation/upsell selection logic is unchanged.

### Payment/account safety
- Order Details and Order Success no longer render raw payment-provider `checkout_error` values to customers.
- Customer pages now show safe retry/support guidance instead of technical provider responses.
- Order Details no longer exposes raw provider status codes as the primary customer-facing payment status.
- Detailed gateway diagnostics remain an admin/log concern.

### Regression coverage
- Updated `StorefrontExperienceTest` for the product-detail cleanup and checkout reassurance copy.
- Added coverage for the brand-token hero styling and RTL hero navigation behavior.
- Extended payment-error coverage so Paymob Result, Order Details, and Order Success all reject raw gateway error output.

## Validation state

- Source implementation: complete for this iteration.
- Automated regression coverage: updated and passing.
- Code-head CI: **passed** on `d13e6a3` — Hardening CI run `36014798313`.
- Authenticated English/Arabic desktop/mobile QAS review: pending.
- Production: unchanged.

## QAS focus

1. Home page with the default brand theme and at least one alternate Branding & Appearance theme.
2. Hero slider with multiple slides in English and Arabic; verify previous/next direction and touch/mobile behavior.
3. Product without a description: no placeholder Product details block should appear.
4. Product with variants: price, stock, quantity limits, Add to cart, Buy now, and bundle behavior must remain unchanged.
5. Checkout: billing toggle alignment, payment options, addresses, totals, offers, and Place order.
6. English/Arabic wording and RTL spacing at mobile widths.
7. Simulate a QAS online-payment initiation failure and confirm no raw provider error/status is exposed on Paymob Result, Order Details, or Order Success.
8. Review Cart offer/recommendation labels in English and Arabic and confirm recommendation contents are unchanged.
