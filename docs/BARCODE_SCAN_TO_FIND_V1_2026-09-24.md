# Barcode Scan-to-Find V1

Date: 2026-09-24  
Branch: `v42-clean-baseline`

## Goal

Add the first scanner-driven inventory workflow on top of the catalog identifier foundation, while keeping the implementation reusable for future POS work.

## Implemented

- New Inventory scanner workspace at the admin inventory permission boundary.
- Scanner input is compatible with USB/Bluetooth HID keyboard-mode barcode scanners: keep the input focused, scan, and the scanner Enter suffix submits the lookup.
- Lookup uses the shared exact barcode resolver; it does not use fuzzy product search.
- Exact simple-product barcode matches show the current stock and link directly to the counted-stock adjustment workflow.
- Exact variant barcode matches identify that exact variant and link directly to its counted-stock adjustment workflow.
- A parent product barcode on a variant product never selects a variant automatically. The operator must explicitly choose the physical variant.
- Legacy product/variant barcode collisions are blocked from stock actions and surfaced as an operator-safe duplicate-identifier warning.
- Unknown barcodes show a no-match state and offer catalog search instead of silently creating or changing stock.
- Inventory and Adjust Stock headers now expose the scanner workspace.
- English/Arabic copy and focused regression tests are included.

## Deliberate V1 boundaries

- Camera scanning is not included yet.
- Barcode creation/label printing is not included yet.
- Scanner lookup does not mutate stock by itself; all changes still pass through the existing stale-count protected Inventory Adjustment service.
- The workflow does not auto-create products from unknown barcodes.
- POS/cart mutations are not part of this batch. The shared exact resolver and scanner UX pattern are intended to be reused there later.

## Automated coverage

Focused tests cover:
1. Inventory permission boundary and scanner-ready state.
2. Exact simple product match.
3. Exact variant match.
4. Parent barcode requiring explicit variant choice.
5. Unknown barcode no-match behavior.
6. Legacy cross-table duplicate barcode safety.

Hardening CI run `36036425394` passed at application head `fea30ea`: PHP syntax, Bash syntax, clean MySQL migration, Laravel boot/routes, config + Blade compilation, **147 tests (794 assertions)**, and frontend production build. `InventoryBarcodeScanTest` passed in the integrated suite.

## Consolidated QAS checks

1. Connect/use a common HID scanner or type a barcode and press Enter.
2. Verify a simple product opens the correct stock action.
3. Verify a variant barcode opens that exact variant.
4. Verify a parent barcode never chooses a variant automatically.
5. Verify unknown and duplicate barcodes cannot reach a stock mutation.
6. Verify repeated scans keep the input usable and focused.
7. Review English/Arabic desktop/mobile layout and RTL alignment.

Production and `main` remain unchanged until the normal CI → QAS → review gate is completed.
