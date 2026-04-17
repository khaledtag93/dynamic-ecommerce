# QA Checklist

Use this checklist before moving to the next phase.

## Admin
- Login works
- Dashboard loads without errors
- Categories create/edit/delete works
- Brands create/edit/delete works
- Products create/edit/delete works
- Variant save/update works
- Product main image and gallery work
- Orders list loads and filters work
- Coupons create/edit/apply logic works
- Settings save correctly
- Permissions pages load and save correctly
- Notifications page loads
- Growth page loads without fatal errors
- Analytics page loads without fatal errors

## Frontend
- Homepage loads
- Category pages load
- Product page loads
- Add to cart works
- Cart update/remove works
- Checkout works
- COD order works
- Contact / policy pages load
- Order success page loads
- Customer notifications load

## Payments
- Paymob redirect starts correctly
- Callback route responds correctly
- Failed payment does not break order flow
- Success payment updates statuses correctly
- Payment result page renders correctly

## Operations
- Suppliers CRUD works
- Purchases save correctly
- Inventory movements reflect properly

## Translation
- Core admin labels show correctly in Arabic and English
- Frontend headers/buttons show correctly in Arabic and English
- No obvious hardcoded text in newly added pages

## Stability
- No fatal errors in logs after smoke testing
- No broken image paths
- No missing route errors
- No missing translation key noise on main pages
