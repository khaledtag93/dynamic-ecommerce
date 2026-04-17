# Release Checklist

Use this only when the project reaches public release / external sharing stage.

## Security
- Remove `.env` from release package
- Verify `.env.example`
- Rotate any exposed test secrets if needed
- Check payment credentials and debug flags

## App readiness
- `php artisan optimize:clear`
- `php artisan config:cache`
- `php artisan route:cache` if safe
- `php artisan view:cache` if safe
- `npm run build`

## Functional validation
- Homepage
- Product page
- Cart
- Checkout
- Payment flow
- Orders in admin
- Settings save
- Policy pages

## Assets
- Confirm images are loading from the intended public path
- Remove any unused public demo assets

## Documentation
- Update `PROJECT_MASTER_STATUS.md`
- Update `CURRENT_PHASE.md`
- Update known issues
- Confirm setup guide
