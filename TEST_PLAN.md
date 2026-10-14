# Test Plan

## Execution ownership and acceptance standard (2026-10-14)

This test plan is extended by the standing [Assistant-led QA & Acceptance Charter](docs/ASSISTANT_LED_QA_AND_ACCEPTANCE_CHARTER_2026-10-14.md). The assistant is responsible for running and reporting automated tests, CI, QAS backend/API and available authenticated browser journeys as far as tools and test access permit; the product owner is **not** the default manual tester for every page. Keep unexecuted or unavailable test types explicitly BLOCKED, not PASS.

Applies across all Flowra Admin, Storefront, POS, workforce/HR/payroll, financial and future SaaS areas. Every slice needs an E2E scenario matrix with expected vs actual results, persona/role, EN/AR/RTL where applicable, evidence, exact source/QAS SHAs and defects.

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
No move to the next phase before applicable critical flows have been **executed and verified** on the active baseline, using automated QAS/browser checks wherever feasible and a clearly documented human acceptance check only where necessary. CI-only PASS is not QAS acceptance; record any BLOCKED checks explicitly.
