# Project Status Internal

## Current State
- Product system stable
- Variants stable with default logic
- Images standardized on `image_path`
- Search and admin tables improved
- Cart and checkout are working
- Orders flow is working end-to-end
- Admin orders management is active
- Coupons system added
- Cancel and refund logic added

## What Was Added In This Round
### UI/UX polish
- Refined customer cart, checkout, success, and order details pages
- Improved badges, spacing, card hierarchy, and timeline sections
- Added submit loading states on customer and admin forms
- Strengthened admin sidebar spacing and hover/active behavior
- Improved admin orders table readability and refund visibility

### Business logic
- Coupon model, migration, admin CRUD, and cart/checkout application flow
- Order-level coupon snapshot storage
- User order cancellation with stock restore
- Admin order cancellation with stock restore
- Refund tracking table and admin refund recording flow
- New payment statuses:
  - partially_refunded
  - refunded

## Main Files Added
- `app/Models/Coupon.php`
- `app/Models/OrderRefund.php`
- `app/Services/Commerce/CouponService.php`
- `app/Services/Commerce/OrderActionService.php`
- `app/Http/Controllers/Admin/CouponController.php`
- `database/migrations/2026_03_28_010000_create_coupons_table.php`
- `database/migrations/2026_03_28_010100_add_coupon_cancel_refund_columns_to_orders_table.php`
- `database/migrations/2026_03_28_010200_create_order_refunds_table.php`
- `resources/views/admin/coupons/index.blade.php`
- `resources/views/admin/coupons/form.blade.php`

## Main Files Updated
- `app/Models/Order.php`
- `app/Services/Frontend/CartService.php`
- `app/Services/Frontend/CheckoutService.php`
- `app/Http/Controllers/Frontend/CartController.php`
- `app/Http/Controllers/Frontend/CheckoutController.php`
- `app/Http/Controllers/Admin/OrderController.php`
- `routes/web.php`
- `resources/views/frontend/cart/index.blade.php`
- `resources/views/frontend/checkout/index.blade.php`
- `resources/views/frontend/orders/index.blade.php`
- `resources/views/frontend/orders/show.blade.php`
- `resources/views/frontend/orders/success.blade.php`
- `resources/views/admin/orders/index.blade.php`
- `resources/views/admin/orders/show.blade.php`
- `resources/views/layouts/admin.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/inc/admin/sidebar.blade.php`

## Next Recommended Phase
1. Notifications
   - order placed email
   - status updated email
   - refund recorded email
2. Coupon rules v2
   - per-user limits
   - category/product restrictions
   - first-order-only support
3. Payment integration
   - Paymob / Stripe / PayPal
4. Analytics
   - revenue widgets
   - coupon usage analytics
   - refund analytics
