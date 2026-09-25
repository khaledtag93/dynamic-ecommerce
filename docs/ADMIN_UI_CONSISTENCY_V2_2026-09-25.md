# Admin UI Consistency Foundation V2 — 2026-09-25

## Goal
Reduce cross-page visual drift by standardizing the most repeated admin KPI cards and switch controls at the shared-layout/component level instead of fixing each screen independently.

## Shared stat card
- Added resources/views/components/admin/stat-card.blade.php.
- Supports label, value, optional icon, optional help copy, optional meta slot, and semantic tone.
- Added a stable admin-stat-card--v2 contract while retaining the existing theme variables.
- Added no-icon layout support so analytics-style cards do not reserve empty icon space.
- Added optional success, warning, and danger accent treatments.

## Initial rollout
The shared component now powers KPI cards in:
- Analytics
- Permissions & Staff Roles
- Orders
- Deliveries
- Customers
- Purchases
- Payments
- Inventory
- Workforce Employees
- Workforce Schedule
- Workforce Attendance Corrections
- Workforce Leave

## Switch alignment
- Added a global form-check.form-switch layout contract in the admin layout.
- Neutralizes conflicting utility spacing such as pt-* / ms-* that previously caused inconsistent alignment.
- Uses one grid structure for the switch control, label, and optional help text.
- Uses logical/RTL-aware direction handling.
- Supports form-check-reverse without relying on physical left/right margins.
- Keeps switch behavior server-authoritative; this is presentation-only.

## Analytics consistency
- Analytics KPI cards no longer implement a separate KPI label/value/help visual language.
- Delta information is passed through the shared stat-card meta slot.
- Existing analytics calculations and filters are unchanged.

## Regression coverage
tests/Feature/AdminUiConsistencyV2Test.php verifies:
- the shared Stat Card component contract;
- adoption across the initial core admin workspaces;
- presence of the shared RTL-safe switch contract.

## Release state
- Source: v42-clean-baseline.
- CI: pending branch-head verification.
- QAS: unchanged for this slice.
- Production: unchanged.
