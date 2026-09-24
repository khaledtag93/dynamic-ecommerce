# Cost Calculator UI/UX polish — 2026-09-24

Working branch: `v42-clean-baseline`

## Goal

Polish the existing V42 Cost Calculator without changing its recipe, material-cost, extra-cost, profit, or profit-margin calculations.

## Implemented

- Replaced the legacy orange workflow/summary styling with configurable admin theme tokens.
- Added lightweight section shortcuts for Raw Materials, Product Recipe, and Profit Calculation when a product is selected.
- Reused the shared admin stat-card language for the four live cost/profit summary cards.
- Added standard submit-loading behavior to material creation and product-cost save forms.
- Replaced the raw-material browser `confirm()` prompt with the shared in-app confirmation flow.
- Kept all existing cost calculation JavaScript and backend formulas unchanged.
- Added English/Arabic copy for the new workspace navigation and delete confirmation.
- Extended `CostCalculatorSemanticsTest` with focused UI regression coverage while retaining the profit-margin semantics test.

## Validation state

- Source implementation: complete for this polish iteration.
- Automated regression coverage: added and passing.
- Code-head CI: **passed** on `2a237e1` — Hardening CI run `36005416310`.
- Authenticated English/Arabic desktop/mobile QAS review: pending.
- Production: unchanged.

## QAS focus

1. Open the calculator with no selected product and verify Materials / Recipe navigation.
2. Select a product and verify the Profit Calculation shortcut appears.
3. Add/remove material and expense rows and confirm live totals are unchanged.
4. Save a recipe and verify total cost, profit, margin, and product cost price match the prior calculation behavior.
5. Delete a reusable raw material through the in-app confirmation flow.
6. Review English/Arabic layout, table overflow, and narrow-width behavior.
