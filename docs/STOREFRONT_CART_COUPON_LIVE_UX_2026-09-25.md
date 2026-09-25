# Storefront Cart Coupon Live UX — 2026-09-25

## Goal

Close the remaining cart coupon reload/localization gap without moving coupon business rules into the browser.

## Implemented

- Apply Coupon and Remove Coupon support progressive JSON responses while preserving normal POST/DELETE redirect fallbacks.
- The cart updates coupon code/label, coupon discount, promotion discount, subtotal, tax and total in place from the server-confirmed cart summary.
- Coupon application/removal does not increment usage. Existing checkout logic remains responsible for consuming coupon usage.
- The coupon box keeps both states in the server-rendered DOM and switches them after a confirmed response, avoiding a full-page refresh.
- Coupon input is cleared only after a successful apply.
- Validation and network failures use the existing accessible cart live-status area.
- Coupon business validation messages in `CouponService` now use Laravel translation keys instead of hard-coded English literals.
- Added Arabic translations for invalid/missing/inactive/scheduled/exhausted/minimum-subtotal/non-applicable coupon errors.

## Safety

- `CouponService` remains the canonical validation/session layer.
- `CartService::summary()` remains the canonical discount/total calculation layer.
- The browser never calculates coupon or promotion discounts.
- No payment, order, inventory, reservation or usage-count mutation was added.

## Regression coverage

`StorefrontCartCouponLiveTest` covers:

- live apply with canonical subtotal/discount/total;
- live remove and session cleanup;
- Arabic invalid-code response;
- progressive-enhancement form hooks;
- separate coupon/promotion discount presentation;
- translation-aware service source contract.

## Release status

- Source: prepared for `v42-clean-baseline`.
- CI: Hardening CI #1404 passed at `c0855b9` with 386 tests / 2624 assertions plus clean migration, routes, Blade compilation and frontend production build.
- QAS: not yet claimed.
- Production: unchanged.

## QAS acceptance

- Apply valid fixed and percentage coupons without page reload.
- Remove an applied coupon without page reload.
- Verify totals and separate coupon/promotion rows update correctly.
- Verify invalid/inactive/expired/usage-limit/minimum-subtotal messages in EN/AR.
- Verify normal page refresh preserves the applied coupon through the existing session contract.
- Verify mobile/RTL input, feedback and summary layout.
