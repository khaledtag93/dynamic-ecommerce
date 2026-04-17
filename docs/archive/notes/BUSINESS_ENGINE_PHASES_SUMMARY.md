# Business Engine Upgrade Summary

Implemented on top of the uploaded stabilization version.

## Added foundations
- Suppliers
- Supplier item costing
- Purchases and purchase receiving
- Inventory movements ledger
- Payment records foundation
- Promotion rules engine
- White-label settings storage
- Import jobs draft structure
- Cost/profit fields on orders and order items
- Expiration support on products and variants

## Integrated flows
- Checkout now creates payment records
- Checkout now snapshots cost/profit into order items
- Checkout now writes inventory OUT movements
- Purchase receiving writes inventory IN movements
- Cart summary now supports automatic promotions in addition to coupons

## Admin sections added
- Suppliers
- Purchases
- Inventory
- Payments
- Promotions
- White-label settings
- Import jobs

## Notes
- This round focuses on foundation + working business structure.
- Gateway-specific online payment execution is intentionally left provider-ready.
- CSV parsing/import execution is intentionally left as a future operational layer, but the import job structure and mapping foundation are ready.
