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
- Added `StorefrontExperienceTest` covering the real search route, matching results, local image fallback, removal of the duplicate shortcut strip, non-demo default contact settings, Checkout payment/toggle behavior, and Product Detail gallery semantics.

## Validation state

- Source implementation: complete for this foundation iteration.
- Automated regression coverage: added.
- Branch-head CI: pending.
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
