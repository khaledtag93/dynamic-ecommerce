# Blade Cleanup Report

This cleanup pass focused on:

- removing nested translation patterns like `__('{{ __('...') }}')`
- replacing risky or broken Blade translation strings
- wrapping key visible UI strings in `__()` so they can be translated
- adding Arabic translations for newly introduced storefront/admin strings

## Files updated

- `resources/views/livewire/admin/product/product-form.blade.php`
- `resources/views/frontend/sections/trust-blocks.blade.php`
- `resources/views/frontend/sections/partials/product-card.blade.php`
- `resources/views/frontend/index.blade.php`
- `resources/views/frontend/sections/promo-banners.blade.php`
- `lang/ar.json`

## Notes

This pass removes the currently detected nested translation syntax issues in Blade templates and localizes the most visible recent additions.
If more legacy marketing copy is introduced later, it should also be wrapped in `__()` and added to `lang/ar.json`.
