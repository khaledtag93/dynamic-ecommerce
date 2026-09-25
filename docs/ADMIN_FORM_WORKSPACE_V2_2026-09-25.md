# Admin Form Workspace V2 — 2026-09-25

## Goal
Bring the main create/edit flows for Coupons, Promotions, and Suppliers onto the same Admin V2 form structure without changing validation, business rules, or persistence.

## Coupons
- Replaced the legacy custom page header with the shared Admin page-header component.
- Split the main coupon editor into focused shared tabs:
  - Offer
  - Limits & eligibility
  - Schedule & notes
- Preserved the existing coupon preview panel and live preview JavaScript.
- Preserved the same POST/PUT action, fields, validation, activation switch, limits, and schedule semantics.

## Promotions
- Replaced the legacy custom page header with the shared Admin page-header component.
- Split the promotion editor into focused shared tabs:
  - Rule
  - Eligibility
  - Schedule & status
- Preserved buy-X-get-Y field toggling and disabling behavior.
- Preserved category targeting, discount rules, priority, schedule, activation, and server-side validation.

## Suppliers
- Supplier Create/Edit already used the shared Admin page header and structured two-column form.
- Removed the local switch-input sizing override so Supplier visibility now fully follows the global Admin switch contract.
- Supplier profile, availability, purchasing history safety, and validation are unchanged.

## Arabic / RTL
- Added Arabic labels for all new Coupon/Promotion editor sections.
- Shared section-tabs retain keyboard and RTL-aware navigation behavior.

## Regression coverage
tests/Feature/AdminFormWorkspaceV2Test.php verifies:
- Coupon shared header, tabs, and preview preservation;
- Promotion shared header, tabs, and buy-X-get-Y behavior marker;
- Supplier global switch-contract usage;
- Arabic labels for all new form sections.

## Release state
- Source: v42-clean-baseline.
- CI: pending branch-head verification.
- QAS: unchanged for this slice.
- Production: unchanged.
