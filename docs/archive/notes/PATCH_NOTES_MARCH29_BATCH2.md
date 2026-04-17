# Patch Notes — March 29 Batch 2

## Admin UI and Arabic cleanup
- Unified product index styling closer to categories.
- Removed several raw English strings from Arabic admin pages.
- Fixed labels and copy in customers, promotions, brands, categories, coupons, and products.

## Brands
- Replaced modal add/edit flow with inline form inside the page.
- Added safer delete guard for brands linked to products.

## White-label
- Expanded settings screen into grouped sections for:
  - theme preset
  - global identity
  - customer branding
  - admin branding
  - images
  - live preview
- Added 3 theme presets:
  - Sunset Bakery
  - Midnight Luxury
  - Fresh Market
- Added separate admin logo support.
- Added customer/admin color controls and live preview.
- Added customer card radius and badge style controls.
- Added homepage section toggles.

## Layout integration
- Customer layout now reads more branding keys.
- Admin layout now reads branding colors for header/sidebar and shared palette.
- Admin navbar supports dedicated admin logo fallback.

## Notes
- `php artisan view:cache` could not run in this environment because the PHP DOM extension is missing.
- PHP syntax checks passed for the modified PHP files.
