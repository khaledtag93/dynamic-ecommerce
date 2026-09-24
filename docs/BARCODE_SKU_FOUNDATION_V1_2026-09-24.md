# Barcode / SKU Foundation V1

Date: 2026-09-24  
Branch: `v42-clean-baseline`

## Goal

Prepare the catalog for reliable inventory scanning and future POS work without changing Production or introducing a new barcode format policy prematurely.

## Implemented

- Existing product and variant barcode fields remain optional.
- Product and variant SKU values now use the same cross-catalog uniqueness rule in the active product editor.
- Barcode uniqueness remains enforced across products and variants.
- Duplicate identifiers inside one product/variant payload are rejected before save.
- Exact SKU lookup is available beside the existing exact barcode resolver.
- Identifier lookup refuses to guess when legacy duplicate data makes a result ambiguous.
- Catalog search finds a parent product by a variant SKU or variant barcode.
- Duplicating a product clears the copied SKU and barcode and keeps copied stock at zero, preventing a new sellable record from inheriting the original retail identity.
- Product editor guidance explains that SKU/barcode identifiers must stay unique for inventory/POS reliability.
- English and Arabic strings and focused regression coverage are included.

## Deliberate compatibility decisions

- No new database-wide unique constraint is added to `products.barcode` or `products.sku` in this slice. Historical data may contain collisions, and a migration must not fail or silently rewrite merchant identifiers.
- Variant SKU already has a database unique constraint and variant barcode has a database unique constraint on the current working line.
- Cross-table uniqueness (product vs variant) is enforced in the active catalog editor and lookup ambiguity is explicitly detected.
- Barcode values are not restricted to numeric GTIN only. This keeps existing/internal merchant barcode formats compatible while GS1/EAN/UPC policy remains configurable future work.

## Automated checks

Focused tests cover exact SKU lookup, product/variant collision rejection, parent catalog search by variant identifier, safe product duplication, and the existing exact-barcode ambiguity behavior.

## Consolidated QAS checks

1. Create/edit a simple product with unique SKU and barcode in English and Arabic.
2. Confirm duplicate SKU/barcode messages are clear and field-scoped.
3. Create variants with unique identifiers and confirm duplicates are blocked.
4. Search the Products workspace using a variant SKU and barcode.
5. Duplicate an identified product and verify the copy opens with blank SKU/barcode and zero stock.
6. Verify RTL layout and mobile field alignment.

Production and `main` remain unchanged until the normal CI → QAS → review gate is completed.
