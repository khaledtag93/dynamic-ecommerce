# Test Plan

## Critical business flows

### 1. Catalog
- Category listing loads correctly in Arabic and English
- Product listing renders the correct main image
- Product details show price, variants, and add-to-cart actions correctly

### 2. Cart
- Add product to cart
- Update quantity
- Remove item
- Apply coupon
- Recalculate totals correctly

### 3. Checkout
- Submit order with cash on delivery
- Submit order with online payment
- Validate required fields in Arabic and English

### 4. Orders
- Order appears in customer account
- Order appears in admin dashboard
- Cancel flow behaves correctly when enabled
- Refund records appear correctly in admin

### 5. Payments
- Paymob redirect works
- Callback updates payment and order status
- Failed/declined flows show correct messaging
- Refresh does not create duplicate provider records

### 6. Permissions
- Admin pages are protected
- Role updates work from the customer admin pages
- Restricted users cannot access protected routes

## Recommended execution order
1. Catalog
2. Cart
3. Checkout
4. Payments
5. Orders
6. Permissions

## Rule
No move to the next phase before the critical flows above are manually validated on the active baseline.
