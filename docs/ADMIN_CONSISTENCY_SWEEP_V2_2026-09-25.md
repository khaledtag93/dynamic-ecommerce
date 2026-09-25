# Admin Consistency Sweep V2 — 2026-09-25

## Goal
Close remaining Admin V2 visual inconsistencies in high-traffic operational pages and catch runtime hazards discovered during the UI sweep.

## Updated workspaces
- Suppliers
- Coupons
- Promotions
- Import Jobs
- Admin Notifications

## Shared Admin V2 adoption
- Suppliers, Coupons, Promotions, Imports, and Notifications now use the shared Admin Stat Card component for summary/KPI presentation.
- Coupons, Promotions, and Imports were moved from legacy custom page headers to the shared Admin page-header component.
- Existing live-list search/filter/sort/pagination behavior is preserved.
- Notification inbox behavior and actions are unchanged.

## RTL consistency
- Supplier, Coupon, and Promotion action columns now use the shared logical RTL alignment helpers.
- Existing edit/delete protections and server-authoritative mutations are unchanged.

## Runtime rendering fixes
- Coupons: fixed invalid IlluminateSupportStr::limit reference.
- Coupons: fixed invalid AppModelsCoupon::TYPE_PERCENT reference.
- Promotions: fixed invalid IlluminateSupportStr::headline reference.
- All were replaced with valid fully-qualified PHP namespaces.

## Regression coverage
tests/Feature/AdminConsistencySweepV2Test.php verifies:
- shared headers/cards across the swept pages;
- valid Coupon/Promotion namespaces;
- RTL action alignment;
- removal of old manual summary-card markup.

## Release state
- Source: v42-clean-baseline.
- CI: pending branch-head verification.
- QAS: unchanged for this slice.
- Production: unchanged.
