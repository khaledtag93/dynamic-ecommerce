# v2 Cleanup Report — Batch 1

## Applied now
- Removed duplicate migration: `2026_03_26_013757_create_product_variants_table.php`
- Removed duplicate migration: `2026_03_26_020128_add_has_variants_to_products_table.php`
- Removed duplicate middleware file: `app/Http/Middleware/AminMiddleware.php`
- Added missing product SKU migration: `2026_03_27_210000_add_sku_to_products_table.php`
- Tightened product model to match the actual database columns currently used in the project
- Simplified checkout stock decrement for non-variant products to always use `products.quantity`
- Standardized `ProductImage` URL accessor to prefer `image_path` first and restored `image_url` append

## Why these changes were prioritized
1. Duplicate migrations were creating schema ambiguity and future maintenance risk.
2. The extra middleware file was dead weight and a naming trap.
3. Product search and admin UI already expect `products.sku`, so the database now supports that expectation directly.
4. Checkout previously tried to decrement non-existent columns (`stock_quantity` / `qty`) in some paths.
5. Product images were carrying two path fields; the accessor now prefers the more explicit canonical path field.

## Recommended next cleanup batch
- Consolidate product pricing rules further so product-level pricing is only used when `has_variants = false`
- Normalize image writes so future saves always write both fields consistently or reduce to one canonical field
- Add tests for:
  - product search by SKU
  - cart add/update/remove
  - checkout stock decrement
  - duplicate product image copy
- Unify admin table UX across categories/brands/attributes using the stable product table pattern

## Safe command after you review
```bash
php artisan migrate:fresh
```

If you want seed data after that, we should prepare clean seeders next instead of reusing inconsistent old rows.


## Batch 2 cleanup applied
- Removed redundant migrations:
  - `2026_03_24_185931_add_sort_order_to_product_images_table.php`
  - `2026_03_24_190625_alter_product_images_table_add_missing_columns.php`
  - `2025_06_02_224406_add_quantity_to_products_table.php`
  - `2025_06_02_225610_add_fields_to_products_table.php`
- Standardized product image writes to `image_path` first in `ProductService`.
- Updated `Product` accessors to stop relying on `offer_price` and to prefer `image_path` for main image resolution.
- Fixed `product_attribute_values` schema to store generic attribute values without `product_id`.
- Updated `ProductAttributeValue` model and Livewire attribute values component to match the corrected schema.
