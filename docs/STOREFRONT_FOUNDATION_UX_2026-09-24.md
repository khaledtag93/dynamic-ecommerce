# Storefront Foundation UX hardening — 2026-09-24

Working branch: `v42-clean-baseline`

## Goal

Make the customer storefront feel production-ready and marketplace-neutral before deeper Product / Cart / Checkout polishing.

## Implemented

- Removed the duplicated post-hero shopping-shortcut strip; the hero keeps one concise shortcut row instead of repeating the same navigation twice.
- Replaced electronics-only default messaging across the storefront shell, homepage builder, hero, product sections, promo banners, trust blocks, SEO fallback text, search placeholders, and footer with marketplace-neutral wording.
- Preserved explicitly customized store content: runtime cleanup only replaces exact known legacy/default wording.
- Removed fake contact defaults such as `support@example-store.com`, `+20 100 000 0000`, `Cairo, Egypt`, and `https://example-store.com` from code/seeding.
- Added a safe exact-match migration that removes only known demo contact/legacy marketing values from `website_settings`; real customized values are untouched.
- Added a real public `/search` route and results page. Desktop/mobile header search now searches visible products instead of submitting an ignored `q` parameter to the home page.
- Search supports:
  - product name / description / translations
  - availability filter
  - on-sale filter
  - latest / price / name sorting
  - pagination
- Added a local `public/images/storefront-placeholder.svg` asset and removed external `via.placeholder.com` dependencies from product cards, product detail, category quick view, cart, and checkout.
- Removed the product-card fallback category label `Electronics`; missing categories no longer invent a category.
- Aligned remaining product-card hover/stock accents with storefront theme tokens.
- Added Arabic/English copy for the new generic storefront and search experience.
- Checkout payment signals now render from the actually enabled payment methods instead of hard-coded Visa / Mastercard / Cash labels.
- The checkout billing-address switch now uses an aligned storefront toggle card with explanatory copy.
- Product Detail now uses `object-fit: contain` for primary/thumbnails to avoid cropping merchandise imagery, and Quick Facts reports the real gallery-media count instead of counting the local placeholder.
- Paymob result pages no longer expose raw provider/exception messages to customers. Technical failure details remain stored in payment metadata/logs for admin diagnostics; the storefront shows a safe retry-oriented message.
- Contact business hours now render through the localized settings fallback instead of bypassing fallback with locale-specific raw keys.
- Category Quick View now uses `object-fit: contain` so merchandise imagery is not cropped.
- Notifications hide the Mark all as read action when there are no unread notifications.
- Cart item removal now uses the shared storefront confirmation modal; offer chips use storefront theme tokens instead of hard-coded blue styling.
- Default legal Privacy / Terms / Refund / Shipping bodies are now blank rather than publishing assumed legal commitments. Public legal pages show a clear unpublished state with a support link until reviewed policy text is configured.
- Store Content & Policies now warns admins to publish reviewed business/jurisdiction-specific policy copy, and its seven contact/cancellation toggles use the shared aligned switch layout.
- Added/extended automated regression coverage for search, local image fallback, checkout payment/toggle behavior, product imagery, account order actions, safe Paymob errors, contact-hours fallback, notification bulk-action visibility, cart confirmation, safe legal defaults, and aligned Store Content switches.

## Validation state

- Source implementation: complete for the current foundation + safety iteration.
- Automated regression coverage: expanded.
- Branch-head CI: pending for the latest Store Content switch regression commit.
- Authenticated English/Arabic desktop/mobile QAS review: pending.
- Production: unchanged.

## QAS focus

1. Open Home in English and Arabic and confirm the first screen no longer repeats shortcut rows.
2. Verify configured/custom marketing content still wins over defaults.
3. Search from desktop and mobile header; confirm the query reaches `/search` and results match the catalog.
4. Test search filters and sorting with multiple products, out-of-stock products, and discounted products.
5. Confirm pagination preserves query/filter parameters.
6. Open products/categories/cart/checkout with missing images and confirm the local placeholder renders without third-party requests.
7. Confirm contact/footer areas do not show fake email/phone/address/website values when settings are blank.
8. Review responsive/RTL behavior for the new search page and header search fields.
9. Open Checkout with different payment-method combinations and confirm only enabled methods are advertised above the selector.
10. Check the billing-address toggle alignment on desktop/mobile and verify turning it off still reveals the existing billing fields.
11. Open a product with no media and one with multiple images; confirm the local placeholder is not counted as gallery media and product imagery is not cropped.
12. Open a Paymob failure result and confirm no raw provider/exception message is visible to the customer.
13. Verify Contact business hours in Arabic when only English hours are configured; fallback should render cleanly.
14. Open Category Quick View and confirm product images are contained rather than cropped.
15. Verify Notifications hides Mark all as read when there is nothing unread.
16. Remove a cart item and verify the in-app confirmation flow.
17. Leave each legal policy body blank and confirm the public page shows the unpublished state rather than assumed legal text.
18. Review Store Content & Policies toggles in English/Arabic and desktop/mobile; all seven switches should stay aligned.
