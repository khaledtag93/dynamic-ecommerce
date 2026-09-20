# MASTER PROJECT STATUS
## Dynamic E-commerce System (Tag Marketplace)

## Current state
- **Official application baseline:** V42
- **Baseline description:** Cost Calculator refactor + Arabic/English translation updates
- **Current working branch:** `v42-clean-baseline`
- **Current phase:** Production hardening
- **GitHub `main`:** still the older V40 baseline until V42 passes hardening
- **Production:** unchanged; no V42 deployment yet
- **Production domain:** `tag-marketplace.com`

## V42 baseline status
- Clean V42 Git baseline completed and verified.
- Expected V42 source files matched the source snapshot by Git blob hash after intentional exclusions.
- Dependency/runtime/local-data artifacts are no longer tracked in the V42 branch.
- `.env` is no longer tracked in the V42 branch.
- Local DB, logs, sessions, uploads, generated Laravel cache files, and backup files are excluded from the baseline.

## Completed / strongly implemented
### Commerce core
- storefront, categories, brands, products, variants and attributes
- cart, checkout, orders and coupons
- cancellation/refund foundations
- Arabic/English translation foundation

### Operations
- suppliers and purchases
- inventory movement foundations
- cost/profit foundations
- Cost Calculator module introduced in V42

### Payments
- payment records/settings
- Paymob gateway foundation
- callback/result flows
- payment admin visibility

### Growth / intelligence foundations
- analytics events and daily stats
- growth automation rules
- message templates/logs
- audience segments
- experiments/validation foundations
- attribution/cohort/customer-scoring foundations

### Platform/admin foundations
- roles/permissions foundations
- notifications and settings
- branding/content controls
- deployment and rollback scripts

## Production-hardening priorities
1. **P0 — Credential rotation:** credentials previously committed to Git history must be treated as exposed and rotated before Production.
2. Verify payment callback/HMAC behavior and idempotency.
3. Verify authorization and privilege-escalation paths.
4. Verify concurrency behavior for stock, coupons, refunds, and orders.
5. Verify Cost Calculator formulas and reporting semantics.
6. Add/repair automated CI coverage for critical flows.
7. Execute deployment, health-check, and rollback validation.
8. Promote V42 to `main` only after verification.

## Planned / not yet closed
- WhatsApp/SMS multi-channel expansion
- deeper analytics/dashboard polish
- broader Arabic/English consistency pass
- HR/POS/barcode/receipt capabilities
- wishlist/comparison/smart search/customer feature closure
- subscription/trial/renewal controls
- deeper AI personalization
- SaaS multi-tenancy and mobile-app path

## Release rule
No commercial handoff or Production deployment is considered complete until the production-hardening priorities are closed or explicitly accepted with documented risk.
