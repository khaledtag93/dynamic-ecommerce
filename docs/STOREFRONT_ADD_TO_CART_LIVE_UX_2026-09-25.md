# Storefront Add-to-Cart Live UX — 2026-09-25

## Goal

Remove avoidable full-page reloads from the primary storefront Add to Cart action while keeping cart business rules server-authoritative.

## Implemented

- Product cards use progressive Add to Cart without page reload.
- Product detail Add to Cart uses the same shared progressive behavior.
- Category Quick View Add to Cart uses the same shared progressive behavior; Quick View Buy Now keeps normal Checkout navigation.
- The enhancement uses delegated submit handling, so product cards replaced by live search/category results continue to work without reinitialization.
- The header cart badge updates from the server-confirmed cart count.
- A shared accessible storefront feedback surface shows success and validation/network errors.
- The server returns the accepted cart-item quantity and canonical cart count.
- Stock limits, product/variant selection, cart ownership/session behavior and behavioral tracking remain in the existing server path.
- No business totals are calculated in JavaScript.
- Normal POST/redirect behavior remains the no-JavaScript fallback.
- Buy Now intentionally keeps normal server navigation to Checkout.
- Bundle add remains on its existing navigation flow in this slice.

## Safety and architecture

- `CartService::add()` remains the single cart mutation path.
- Service-generated `ValidationException` responses remain JSON for enhanced requests and redirect/errors for normal forms.
- The browser does not auto-retry failed POSTs, avoiding accidental duplicate additions after uncertain network outcomes.
- A `storefront:cart-updated` event is emitted after a confirmed update so future storefront components can observe canonical cart state without coupling to this script.

## Regression coverage

`StorefrontAddToCartLiveTest` covers:

- server-confirmed live add;
- repeated add with stock clamping;
- canonical header count response;
- normal redirect fallback;
- product-card/product-detail enhancement hooks;
- delegated handler and Buy Now bypass contract.

## Release status

- Source: implemented on `v42-clean-baseline`.
- Base Add-to-Cart implementation: Hardening CI #1402 passed at `72b8528` with 383 tests / 2596 assertions plus clean migration, routes, Blade compilation and frontend production build.
- Quick View consistency follow-up: branch-head CI pending.
- QAS: not yet claimed for this slice.
- Production: unchanged.

## QAS acceptance

- Add simple products from Home/Search/Category cards without reload.
- Add from Category Quick View without reload and verify Quick View Buy Now still navigates to Checkout.
- Add a selected variant and quantity from Product Details without reload.
- Verify header cart count updates immediately and matches Cart after refresh.
- Verify out-of-stock/stock-clamped behavior and server validation feedback.
- Verify live catalog replacement followed by Add to Cart.
- Verify Buy Now still navigates to Checkout.
- Verify EN/AR/RTL and mobile feedback placement.
