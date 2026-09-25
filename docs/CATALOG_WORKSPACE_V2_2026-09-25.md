# Catalog Workspace V2 — 2026-09-25

## Goal
Bring Brands, Attributes, Attribute Values, and the Products workspace onto the same Admin V2 visual and RTL contract without changing catalog CRUD, Livewire business logic, inventory behavior, or product form persistence.

## Brands
- Replaced the legacy custom page header with the shared Admin page-header component.
- Replaced repeated KPI markup with the shared Admin Stat Card component.
- Preserved Livewire search, visibility filters, usage filters, pagination, inline create/edit, and protected delete behavior.
- Added logical RTL alignment for the Actions column.

## Attributes
- Replaced the legacy header with the shared Admin page-header component.
- Replaced KPI cards with the shared Admin Stat Card component.
- Preserved coverage queues, search, pagination, inline create/edit, protected delete behavior, and values navigation.
- Added logical RTL alignment for action controls.

## Attribute Values
- Replaced the legacy header with the shared Admin page-header component.
- Replaced KPI cards with the shared Admin Stat Card component.
- Preserved search, inline create/edit, variant-usage protection, and deletion safety.
- Added logical RTL alignment for action controls.

## Products index
- Converted the passive Catalog / Active / Hidden health cards to the shared Admin Stat Card component.
- Kept Needs content / Low stock / Out of stock as interactive Livewire filters while applying the same Admin Stat Card V2 visual contract.
- Removed browser-native confirm() dialogs from bulk Activate and Hide actions.
- Added in-app Bootstrap confirmation modals while preserving the existing bulkSetStatus Livewire methods.
- Added logical RTL alignment to the product Actions column.

## Product form
- Existing Product Editor section tabs remain unchanged because the form already uses the shared section-tabs pattern.
- Fixed a mixed-language SEO label by replacing hard-coded 'Meta' + translated 'Description' with the complete Meta Description translation key.
- Variants, pricing, inventory, media, related-product, barcode/SKU, and save logic are unchanged.

## Arabic / RTL
- Added missing Arabic catalog cleanup/status copy for Brands and Attributes.
- Added Arabic copy for the new Product bulk activation/hide confirmation flow.
- Reused shared logical RTL helpers introduced by the Categories workspace.

## Regression coverage
tests/Feature/CatalogWorkspaceV2Test.php verifies:
- shared Page Header and Stat Card adoption in Brands / Attributes / Attribute Values;
- logical RTL Actions alignment across catalog tables;
- shared product health-card visual contract;
- removal of browser confirm() for Product bulk status changes;
- in-app confirmation modal presence and original Livewire action preservation;
- complete Meta Description translation key;
- key Arabic catalog labels.

## Release state
- Source: v42-clean-baseline.
- CI: pending branch-head verification.
- QAS: unchanged for this slice.
- Production: unchanged.
