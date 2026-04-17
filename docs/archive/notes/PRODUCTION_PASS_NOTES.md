# Production pass notes

## Stability and translation cleanup
- Fixed the Blade/Livewire translation bug pattern that was injecting translated labels into property names and `wire:model` bindings.
- Cleaned the product index bulk-selection section so it no longer causes syntax errors.
- Added more Arabic translations for admin/customer pages and shared UI text.
- Updated locale middleware to respect the saved default locale from website settings.

## White-label and theme system
- Expanded theme tokens for more complete control over background, surfaces, borders, soft areas, table header color, table hover color, and admin card colors.
- Added support for saving the current customized colors as a new custom theme.
- Improved branding image URL resolution so stored paths are converted to real preview URLs more reliably.
- Updated the branding screen preview and controls to cover the extra theme tokens.

## Purchases UX
- Added dynamic purchase item rows with add/remove actions.
- Added purchase details page and route.
- Improved purchase validation to avoid empty repeated item rows causing noisy validation errors.

## Customer cart UX
- Wrapped add-to-cart stock errors so the customer gets a friendly flash error instead of a raw exception page.

## Notes
- PHP lint passed for modified PHP controllers/helpers/middleware.
- Full Laravel artisan view compilation was not available in this environment because the DOM PHP extension is missing here.
