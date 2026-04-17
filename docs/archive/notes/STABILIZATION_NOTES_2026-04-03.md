# Phase 1 Stabilization Notes — 2026-04-03

## Changes applied

1. **SetLocale middleware hardening**
   - Wrapped the `website_settings` lookup in a `try/catch` block.
   - This prevents whole-site 500 errors when the database is temporarily unavailable, not migrated yet, or misconfigured.

2. **Product image placeholder hardening**
   - Replaced the broken fallback path `assets/images/placeholder.png` with an inline SVG placeholder.
   - This avoids broken image URLs when a product image is missing.

3. **Store name localization behavior**
   - Removed `store_name` from `config/store.php` translatable settings.
   - Result: switching Arabic/English no longer changes the project/store name automatically.

## Review status

- PHP syntax check passed on the modified files.
- Because the execution environment here has no database driver enabled, full runtime DB-backed browser flow testing was not possible inside the container.
- The applied fixes are safe, targeted stabilization changes only.
