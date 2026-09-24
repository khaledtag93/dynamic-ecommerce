# Barcode Label Printing V1

Date: 2026-09-24  
Branch: `v42-clean-baseline`

## Goal

Turn existing catalog barcodes into real printable stock/retail labels without introducing an external barcode runtime dependency or changing catalog identifiers.

## Implemented

- Internal Code 128B renderer generates inline SVG barcodes from existing printable-ASCII catalog identifiers.
- The renderer calculates the Code 128B checksum and uses official module-width symbol patterns including Start B and Stop.
- Inventory scanner results now expose Print Barcode Label for exact simple-product or exact-variant matches.
- Parent products with variants require an explicit variant before a stock-level label can be printed.
- Label preview supports 50 × 30 mm, 60 × 40 mm and 70 × 40 mm physical dimensions.
- Operators can request 1–100 copies and optionally show/hide the price.
- Labels include product name, variant context where applicable, machine-readable barcode, human-readable barcode, SKU and optional price.
- Browser print CSS preserves label dimensions in millimetres and hides admin chrome during printing.
- Missing barcodes and identifiers outside Code 128B printable ASCII produce a safe non-printable state instead of fabricated or transformed identifiers.
- No stock mutation, product mutation or barcode generation occurs from the print workflow.
- English/Arabic copy and focused unit/feature regression coverage are included.

## Compatibility and safety decisions

- No Composer/npm barcode dependency was introduced.
- The barcode renderer does not rewrite identifiers into GTIN/EAN/UPC. Existing values remain source-of-truth.
- Code 128B V1 intentionally supports printable ASCII values. Legacy identifiers with unsupported characters remain visible but cannot be printed until corrected.
- Prices are shown as the current numeric product/variant price only when enabled. Currency/legal formatting remains part of the broader commerce configuration work.
- Printer calibration remains controlled by the browser/printer driver; the UI advises 100% / Actual size.

## Automated coverage

Focused coverage verifies:
1. Known Code 128B values and checksum.
2. SVG bar geometry output.
3. Blank/non-ASCII rejection.
4. Inventory permission boundary.
5. Requested copy count and millimetre size.
6. Variant product exact-variant requirement.
7. Variant ownership protection.
8. Variant barcode/SKU/price output.
9. Missing/unsupported barcode safe state.

## Consolidated QAS checks

1. Print 50 × 30, 60 × 40 and 70 × 40 labels at 100% / Actual size.
2. Scan the printed barcode back through Barcode Scan-to-Find.
3. Verify leading zeros remain intact.
4. Verify a variant label resolves to the same exact variant.
5. Check 1, multiple and high copy counts against the intended label printer/page stock.
6. Verify price show/hide.
7. Verify EN/AR admin controls and that printed barcode geometry remains LTR and scan-safe.
8. Confirm browser headers/footers are disabled in the operator print setup.

Production and `main` remain unchanged until the normal CI → QAS → review gate is completed.
