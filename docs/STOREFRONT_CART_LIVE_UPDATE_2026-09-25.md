# Storefront Cart Live Quantity Update — 2026-09-25

## Goal

Close an existing completion-pass UX gap on the storefront cart: quantity changes should update the cart without a full-page reload while preserving server authority, ownership checks, stock limits, validation and a no-JavaScript fallback.

## Implemented

- Cart quantity PATCH can now return a JSON response for enhanced requests while keeping the existing redirect response for normal form submissions.
- The server remains authoritative for:
  - cart ownership;
  - minimum quantity;
  - stock clamping;
  - coupon/promotion calculation;
  - subtotal/discount/tax/total calculation.
- Quantity changes update in place:
  - accepted quantity;
  - item line total;
  - cart item count;
  - subtotal;
  - coupon discount;
  - promotion discount;
  - tax;
  - total before shipping.
- The old automatic `requestSubmit()` full-page refresh was replaced with a progressive `fetch` flow.
- The normal submit button remains available when JavaScript is unavailable; JavaScript hides it only after live enhancement is active.
- Live feedback uses an ARIA status region and bilingual success/error copy.
- Fixed an existing presentation bug where the cart rendered the combined discount under “Coupon discount” and then rendered Promotion again. Coupon and promotion discounts now use their separate canonical summary buckets.
- Added Arabic copy for the live-update failure state.

## Safety boundaries

- No checkout, order, payment, stock movement or reservation behavior was changed.
- `CartService::updateQuantity()` still performs the ownership check and stock clamp.
- The enhanced client does not calculate business totals locally; it renders values returned by the server.
- Normal form submission remains a fallback.

## Regression coverage

`tests/Feature/StorefrontCartLiveUpdateTest.php` covers:

- owned live JSON quantity update and returned canonical summary;
- cross-user ownership denial;
- progressive-enhancement hooks;
- no-JavaScript fallback contract;
- separate coupon/promotion display buckets;
- Arabic live-update error copy.

## Source commits

- `485bcbf` — live cart JSON response
- `beeb4b9` — no-reload cart quantity UI
- `998c7fe` — initial live-update regression coverage
- `668f1a7` — Arabic failure copy
- `482f1a3` — Arabic regression assertion

## Release status

- Source: implemented on `v42-clean-baseline`.
- CI: pending branch-head verification.
- QAS: not yet claimed for this slice.
- Production: unchanged.

## QAS acceptance

- Verify fast repeated quantity edits.
- Verify stock-clamped quantity is reflected in the input.
- Verify coupon and promotion rows appear/disappear correctly as quantity changes.
- Verify totals remain correct in English and Arabic.
- Verify mobile layout and RTL status feedback.
- Verify browser refresh after live updates shows the same persisted cart state.
