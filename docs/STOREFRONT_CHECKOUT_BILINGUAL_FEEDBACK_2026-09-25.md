# Storefront Checkout Bilingual Feedback Hardening — 2026-09-25

## Goal

Close customer-facing English-only flash messages in Checkout without changing checkout, payment, shipping, inventory or order semantics.

## Implemented

- Empty-cart redirect feedback now uses Laravel translation lookup.
- Standard order-success flash feedback now uses Laravel translation lookup.
- Online-payment handoff success feedback now uses Laravel translation lookup.
- Added Arabic translations for all three customer-facing states.
- No redirect target, payment method, shipping quote, order placement, stock reservation or payment-provider logic changed.

## Currency policy finding

Checkout currently treats `EGP` as part of the commerce domain contract, not merely presentation:

- shipping quote JSON returns EGP;
- `CheckoutService` persists new storefront orders with `currency = EGP`;
- storefront cart/checkout product and summary presentation also assumes EGP.

This slice deliberately does **not** replace those labels with a configurable-looking value while the underlying order/payment domain remains fixed. A future currency-policy slice must define the platform/merchant currency source and propagate it consistently through product pricing, cart, shipping, orders, payments, refunds, receipts, POS and reporting before multi-currency claims are made.

## Regression coverage

`StorefrontCheckoutBilingualFeedbackTest` covers:

- Arabic empty-cart redirect feedback;
- translation-aware checkout success source contract;
- Arabic success/payment-handoff copy.

## Release status

- Source: implemented on `v42-clean-baseline`.
- CI: branch-head verification required.
- QAS: not yet claimed for this slice.
- Production: unchanged.

## QAS acceptance

- Open Checkout with an empty cart in EN/AR and verify the Cart feedback.
- Place COD/non-online test orders in EN/AR and verify localized success feedback.
- Exercise the online-payment redirect path in the configured non-production provider environment and verify localized handoff feedback.
- Confirm no change to order currency, shipping quote currency, payment amount or stock lifecycle.
