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


## V42 hardening checkpoint — 2026-09-20
### Newly closed
- duplicate checkout protection using transactional cart locking
- atomic inventory decrement / oversell protection
- atomic coupon usage-limit enforcement
- order cancellation idempotency and row-level serialization
- refund race / over-refund protection
- payment/refund-ledger consistency
- permissions self-escalation path
- deploy-center safe default and mandatory verified health check
- clean MySQL migration compatibility for growth learning index
- GitHub Actions hardening CI established

### CI status
A production-like GitHub Actions build on PHP 8.2 + MySQL 8 is green for the V42 hardening branch. It validates Composer, PHP syntax, a clean migration, Laravel boot/routes, config/views, the existing PHPUnit suite, and the frontend production build.

### Still required before Production
- rotate credentials that were historically committed
- execute a Paymob sandbox/test end-to-end transaction and negative-path callback checks
- rehearse deploy/rollback against the target server or staging-like environment
- validate the explicit database recovery procedure using the pre-migration SQL snapshot
- run final smoke checks, then promote V42 to `main`

### Additional V42 hardening — 2026-09-20
- custom role deletion now refuses assigned roles, preventing foreign-key cascade from leaving admins in implicit legacy Super Admin state
- permission-role deletion now has targeted automated regression coverage
- remaining tracked Livewire temporary files and product runtime uploads were removed from Git and their runtime storage paths are ignored

### Financial integrity follow-up — 2026-09-20
- Paymob/gateway status transitions are now row-locked and transactional, closing the simultaneous paid-vs-failed callback race
- order payment-state synchronization is row-locked
- permanent order hard-delete is disabled to retain financial and inventory audit history
- targeted tests now cover terminal paid state, retained cancelled orders, and assigned-role deletion protection


### Final V42 code-hardening checkpoint — 2026-09-20
Additional closures verified by green CI:
- full-refund Payment ledger synchronization and terminal refunded state
- consistent Order → Payment lock ordering for payment/refund concurrency
- checkout transaction rollback regression coverage for late failures
- cancellation inventory-restock audit movements without changing current product cost valuation
- correct Profit Margin semantics in Cost Calculator (profit / selling price)
- deploy backup secret minimization (`.env` excluded from app snapshot; private file creation)
- manual rollback maintenance mode + HTTPS health verification
- Paymob callback raw-payload/full-URL log minimization

The latest production-like GitHub Actions run for code head `b9a46a6` passed the complete pipeline on PHP 8.2 + MySQL 8, including the expanded PHPUnit regression suite and frontend production build.

Historical Git inspection confirms that `.env` existed in the older repository history before the hardening removal commit. Credential rotation therefore remains a mandatory P0 release gate even though V42 no longer tracks the file.


### Recovered legacy deployment/payment notes — 2026-09-20
Recovered from the user's old local notes (non-secret facts only):
- Paymob Merchant ID: `1147230`
- Paymob Test Integration ID previously used: `5596653`
- Paymob legacy IFrame previously used: `1024107`
- Production Laravel app path historically used: `/home/u637857322/domains/tag-marketplace.com/laravel_app`
- Production public webroot historically used: `/home/u637857322/domains/tag-marketplace.com/public_html`
- Historical SSH port used: `65002`
- Historical deploy routine used `deploy.sh` / `rollback.sh` from the Laravel app directory.

Security note:
- The recovered notes also contained plaintext provider, database, SSH, and account credentials.
- No credential values are copied into this repository documentation.
- Treat all historical credentials from those notes as exposed and rotate them before Production promotion.
- The recovered Paymob Integration ID / IFrame ID are identifiers, not secrets, and may be used for compatibility diagnostics.


### Gmail evidence for Paymob account state — 2026-09-20
Connected Gmail history confirms:
- Paymob welcomed the merchant account on 2026-03-31 after dashboard onboarding.
- Multiple Paymob TEST card transactions were executed on 2026-03-31 for EGP 98.00 through the hosted IFRAME flow; all surfaced transaction-status emails were declined.
- Starting 2026-04-01, Paymob repeatedly sent "Document Resubmission Required" onboarding emails for Merchant ID `1147230`.
- The same resubmission notice was still being sent as recently as 2026-09-18.
- Gmail search found no Paymob email confirming successful verification, activation, or Live-mode approval.
- Gmail search also found no sent support thread to `support@paymob.com` or `support@weaccept.co`.

Operational conclusion:
- The merchant account was integrated enough for Test IFRAME transactions, but onboarding/verification appears to have remained incomplete.
- This account state likely contributes to current dashboard limitations and is a separate concern from Laravel integration correctness.
- Continue V42 release hardening independently; do not block non-payment readiness work on Paymob verification.


### Production server discovery — 2026-09-20
- SSH login to Hostinger succeeded.
- The historical application path `/home/u637857322/domains/tag-marketplace.com/laravel_app` exists.
- Running `git status --short --branch` inside that directory returned: `fatal: not a git repository`.
- Therefore the current production Laravel directory is not a Git worktree. Do not run fetch/pull/reset/checkout there until the actual deployment layout is inspected and a safe migration/rehearsal plan is chosen.
