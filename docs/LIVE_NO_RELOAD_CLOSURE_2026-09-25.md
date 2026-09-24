# Live / No-Reload Interaction Closure
Date: 2026-09-25
Working branch: `v42-clean-baseline`
Application source checkpoint: `eedd109`

## Goal

Close the cross-product migration of safe read-side search, filtering, queue navigation, sorting and pagination away from unnecessary full-page reloads while preserving server-authoritative business mutations.

## Covered Admin surfaces

- POS product/customer lookup: live autocomplete.
- Manager Cash Shift Review: live search, status filters and pagination.
- Orders: live search, status/payment/method filters, queues, sorting and pagination.
- Customers: live search, role/activity/value filters, queues and pagination.
- Suppliers: live search, status/usage filters, queues, sorting and pagination.
- Purchases: live search, status/supplier filters, awaiting-receipt queue and pagination.
- Inventory Movement Explorer: live search, movement type/source filters and pagination.
- Payments: live search, status/method filters, attention/failed queues and pagination.
- Coupons: live search, type/status/usage filters, queues, sorting and pagination.
- Promotion Rules: live search, type/status/schedule filters, schedule queues, sorting and pagination.
- Deliveries: live search, delivery status/method filters, queues and pagination.
- Categories: live search, visibility/usage/readiness filters, cleanup queues, sorting and pagination.
- Import Jobs: live pagination; KPI counts are global rather than page-local.
- Products: already operates through Livewire and is therefore not migrated to the server-fragment helper.

## Covered customer/storefront surfaces

- Catalog Search: debounced live search, availability/offer/sort filters and pagination.
- Category browsing: debounced category search, availability/offer/sort filters and pagination.
- Category Quick View uses delegated click handling so dynamically replaced product cards keep working.
- My Orders: live pagination while order-detail and cancellation mutations remain normal server requests.
- Customer Notifications: live pagination while read/read-all mutations remain normal authenticated PATCH requests.
- Address Book has no list filtering/pagination to migrate; create/edit/delete remain normal explicit mutations.

## Interaction contract

The reusable server-rendered helper:
- requests HTML fragments with `X-Live-List: 1`;
- cancels stale requests;
- keeps shareable query URLs and browser Back/Forward;
- debounces text search;
- updates select filters immediately;
- intercepts same-route pagination and explicit live links;
- retains the original GET request as the no-JavaScript fallback;
- exposes loading, success, error and full-page fallback states;
- does not bypass route authorization or backend validation;
- keeps Blade escaping on replaced result content.

## Intentionally not converted to optimistic/live mutations

The following stay server-authoritative by design:
- payment status changes and refunds;
- delivery status/tracking changes;
- purchase receiving;
- inventory adjustments and stock mutations;
- POS checkout/discount/returns/shift mutations;
- coupon/promotion/category/supplier destructive changes;
- customer order cancellation;
- notification read/read-all writes;
- role/permission changes;
- settings, deployment and other operational POST/PATCH/DELETE actions.

The goal is no unnecessary reload for safe read navigation, not client-side ownership of business state.

## Additional fixes found during migration

- Coupon Type sorting now maps to the actual `type` column instead of silently falling back to ID.
- Payment list links to Orders and Payment Settings are permission-aware.
- Customer revenue aggregation avoids hydrating every user aggregate.
- Inventory overview cards no longer become stale or filter-dependent during live navigation.
- Import KPIs are global rather than based on the current pagination page.
- Category Quick View survives live product-grid replacement.

## Regression coverage

Focused tests now include:
- `AdminOrderLiveListTest`
- `AdminCustomerLiveListTest`
- `AdminSupplierLiveListTest`
- `AdminPurchaseLiveListTest`
- `AdminInventoryLiveListTest`
- `AdminPaymentLiveListTest`
- `AdminCouponLiveListTest`
- `AdminPromotionLiveListTest`
- `AdminDeliveryLiveListTest`
- `AdminCategoryLiveListTest`
- `StorefrontLiveCatalogTest`
- `LiveListClosureTest`
- POS shift/live lookup coverage in `PosCashierTest`

## Release status

- Source implementation checkpoint: `eedd109`.
- Branch-head CI: pending independent verification at the time of this note.
- QAS: last verified application revision remains `0a08253`; this closure is not claimed on QAS.
- Production: unchanged.
- Manual authenticated EN/AR desktop/mobile review remains part of the owner's later consolidated QAS phase.

## Phase decision

The live/no-reload migration is closed as a source-development phase. Future screens should follow this standard when touched, but the project should now move to the next product-development priority instead of continuing a broad reload sweep.
