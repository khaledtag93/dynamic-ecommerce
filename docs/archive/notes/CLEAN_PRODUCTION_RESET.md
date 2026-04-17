# Clean Production Reset Notes

## What changed in this version
- Unified product, category, cart, order, variant, and branding media URLs to use `public/uploads/*`.
- Removed runtime dependency on `storage:link` for storefront and admin image rendering.
- Category uploads now save as `category/<filename>`.
- Product uploads now save as `products/<filename>`.
- Branding uploads now save as `branding/<filename>`.
- Default storefront name changed to **Tag Marketplace**.
- Cleared compiled Blade views so production can regenerate fresh templates.

## Expected public folder structure
- `public/uploads/products`
- `public/uploads/category`
- `public/uploads/branding`
- `public/uploads/brands`
- `public/uploads/settings`

## Existing database compatibility
- Existing category values like `image = filename.jpg` still work.
- Existing category values like `image = category/filename.jpg` also work.
- Existing product image values like `products/filename.jpg` work.

## Recommended server deployment structure
- Laravel core outside `public_html`
- Only contents of Laravel `public/` inside `public_html`
- Update `public/index.php` and `.htaccess` paths accordingly
- Do not run `php artisan storage:link` for this version
