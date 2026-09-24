# MASTER PROJECT STATUS
## Dynamic E-commerce System (Tag Marketplace)

> Living status: start with [the documentation guide](docs/README.md) for the current implementation/CI/QAS/Production ledger and the dated audit links. Historical checkpoints below are retained as evidence; their “next step” statements describe the date they were written.

## Current state

- **Expanded product direction (2026-09-24):** UI/UX/design-system modernization, POS/cashier, barcode scanning, invoice/receipt, employee attendance/shifts/leave/payroll, and expanded delivery operations are now tracked in `docs/PRODUCT_ROADMAP_2026-09-24.md`. These are phased roadmap items; they are not yet deployed features.
- **Official application baseline:** V42
- **Baseline description:** Cost Calculator refactor + Arabic/English translation updates
- **Current working branch:** `v42-clean-baseline`
- **Current phase:** Incremental commercial-readiness fixes on the V42 working line, with CI → QAS → review → Production promotion per exact commit
- **GitHub `main`:** still on the older baseline and has not yet been promoted to the validated V42 branch
- **Production:** V42 routine deployment flow is validated; exact application commit `95e9f50` was deployed successfully with HTTP 200
- **Production domain:** `tag-marketplace.com`
- **QAS deployment checkpoint (2026-09-24):** The operator reported uploading the clean working-line head targeted at `a1e8c57` after confirming a clean server checkout, staging environment and rehearsal database, creating a pre-deploy database snapshot, and checking the remote branch SHA. The public QAS login returned HTTP 200 with expected Arabic content afterward. Server deployed HEAD and authenticated customer/account behavior have not been independently verified; the account/address-book English/Arabic desktop/mobile review remains open. The backup is held outside the repository at `deploy_backups/qas_before_account_a1e8c57_20260924.sql` (157,110 bytes; SHA-256 `e1a0f552f1c84eaae56aa0a714bb798d8c559a1dd96802339086e43598b5de86`).

## Latest working-line update — 2026-09-23
- An admin daily-work UX batch is CI-verified in source, pending QAS review: the default dashboard now shows four clearly defined 30-day metrics, permission-scoped priorities, workspaces and recent records. Its controller no longer computes the unused deep-dive panels. The sidebar no longer queries order/coupon/supplier counts on every render. Topbar/search and order actions respect route permissions.
- **Branding & Appearance V2 (2026-09-24):** source implementation now adds the `professional_commerce` neutral default direction, visual preset cards, reapply-preset behavior, a focused core palette with Advanced controls, a more representative live preview, unsaved-change state/protection, semantic admin success/warning/danger colors, supported badge-style choices, and Arabic/English strings. During the pass, homepage visibility handling was repaired for Featured categories, Manual featured products and Trust blocks. Automated coverage was added in `tests/Feature/BrandingSettingsExperienceTest.php`. Detailed scope/QAS checks: `docs/BRANDING_APPEARANCE_V2_2026-09-24.md`. Final branch-head CI, authenticated QAS review and Production deployment remain separate gates.
- A shared accessible section navigator splits product editing, branding and content settings into focused sections while retaining one form and both product save controls. Order details now have section links and an even four-card status grid. The section helper selects the first error section and preserves the active tab across Livewire updates; this behavior requires authenticated desktop/mobile Arabic/English QAS review, including variants and uploads.
- Admin code revision `9fe1a96` passed [CI run 35919171926](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/35919171926): PHP syntax, clean MySQL migration, Blade compilation, 48 tests (208 assertions), frontend build. These changes have **not** been visually verified in QAS or deployed to Production. The [documentation ledger](docs/README.md) and [Admin UX QAS checklist](docs/ADMIN_UX_QAS_CHECKLIST.md) track the next gate.
- Batch 0 code now requires an explicit non-owner staff role when a customer receives admin access, assigns/removes that role transactionally, and prevents changing an owner through the customer profile. Customer-list access editing moved to the profile to simplify the table.
- The product page no longer creates synthetic ratings, reviews, sales, viewer and save counts; repeated unverified reassurance blocks were removed. The purchase/variant controls remain, and the initial stock note uses the selected variant stock.
- Growth validation demo seed/clear is now restricted to local, testing and QAS/staging at the HTTP and service layers; its operations controls are hidden in Production. Existing demo records have not been audited or removed.
- Focused promotion/demotion, permission-boundary and Production demo-guard regression tests were added. Code revision `f7ff4e8` passed [Hardening CI run 35916058338](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/35916058338): PHP syntax, clean MySQL migration, Blade compilation, 45 PHPUnit tests and frontend build. **QAS visual verification and Production deployment are still pending**; neither environment is recorded as running this batch.
- The pre-existing roleless-admin Super Admin fallback is still active. This is a **partial authorization fix**; inventory current admins, designate and explicitly assign the owner, then remove the fallback with migration/rollback tests. Other P0 release gates in the audits remain open.
- The source audit and UI review are dated baselines, and their later implementation states are tracked in the [documentation guide](docs/README.md). Update this section and the ledger for every subsequent batch.

## Deliveries V2 hardening checkpoint — 2026-09-24
- Delivery mutations now use a row-locked transactional service with an explicit transition matrix for shipping and store-pickup flows.
- `shipped_at` and `delivered_at` now follow lifecycle rules instead of being rewritten on ordinary metadata saves; out-for-delivery requires prior shipment evidence.
- Customer database notifications and WhatsApp delivery updates are emitted only when the delivery status actually changes, so metadata-only saves and concurrent replays do not duplicate status messaging.
- The order delivery editor disables invalid next states, shows shipment/delivery timestamps, and requires in-app confirmation before a real status change. English/Arabic copy and focused regression coverage were added.
- Detailed scope and QAS checks: [`docs/DELIVERIES_V2_2026-09-24.md`](docs/DELIVERIES_V2_2026-09-24.md).
- Source implementation is complete for this slice; branch-head CI, authenticated Arabic/English QAS review, and Production promotion remain separate gates. Production is unchanged.

## Catalog Admin V2 checkpoint — 2026-09-24
- Catalog administration has expanded beyond Products into Categories, Brands, Attributes, and Attribute Values with operational queues, stronger filters, usage/health metrics, safer destructive actions, and editor workflow improvements.
- Category editing now enforces unique slugs, image type/size limits, translation-field validation, and protects categories linked to products from deletion.
- **Category bilingual hardening (2026-09-24):** canonical/base category fields now read raw stored values instead of locale-translated accessors, preventing an Arabic admin edit from overwriting the canonical record. Translation panes now use correct RTL/LTR direction with field-level errors, and clearing an optional Arabic translation removes the stale translation row. Regression coverage was added; branch-head CI/QAS remain pending. See `docs/CATEGORY_BILINGUAL_HARDENING_2026-09-24.md`.
- Brand management now surfaces empty/linked records and uses guarded deletion with a backend recheck for product dependencies.
- Attribute and Attribute Value management is variant-aware: attributes or values referenced by product variants are protected from deletion, while usage counts and cleanup queues are visible to admins.
- Arabic/English catalog-admin localization is now part of the ongoing definition of done. New strings introduced during the V2 work are being registered in English and translated into Arabic as the related screens are upgraded.
- Automated coverage has started for the upgraded catalog workflows. Final release readiness still requires green branch-head CI plus authenticated QAS review in English and Arabic before Production promotion.

## Analytics & Insights V2 checkpoint — 2026-09-24
- The Analytics overview now uses a shorter decision-first hierarchy: four primary KPIs → decision read → trends → funnel/period comparison → commercial drilldowns.
- Repeated operator/depth/pattern/storytelling layers were removed while preserving the underlying calculations, reporting windows, funnels, comparisons and drilldowns.
- The same consistency pass now covers Growth and Offers: duplicated growth signal/executive layers and offers hero/operator-summary layers were removed, while campaign/rule/product signals and coupon/promotion detail remain available.
- Growth Insights now reuses the shared admin metric-card language, and undefined legacy Growth semantic color variables were replaced with the defined admin theme tokens.
- Daily detail remains available under an expandable More diagnostics section instead of filling the default page.
- Shared Analytics navigation and report surfaces now use configurable admin theme tokens instead of hard-coded orange styling; the duplicate on-screen export summary table is hidden while CSV/print behavior remains available.
- English/Arabic hierarchy copy and a focused `AdminAnalyticsExperienceTest` were added. Detailed scope/QAS checks: `docs/ANALYTICS_INSIGHTS_V2_2026-09-24.md`.
- Source implementation and automated regression verification are complete for this iteration. Hardening CI run `36004327589` passed at `8b477d8`; authenticated English/Arabic desktop/mobile QAS review remains required. Production is unchanged.

## WhatsApp workspace UX checkpoint — 2026-09-24
- The long WhatsApp administration screen now uses focused Overview / Channel & queue / Provider & templates / Test tools / Logs navigation instead of reading as one oversized white panel.
- Five channel/queue switches now use aligned toggle cards and shared admin switch sizing; delivery/message summary metrics use the shared admin stat-card language.
- Provider/template/test/resend/log workflows and server-managed credential handling remain functionally unchanged.
- English/Arabic workspace copy and focused regression coverage were added. See `docs/WHATSAPP_WORKSPACE_UX_2026-09-24.md`.
- Source implementation and automated regression verification are complete for this UI iteration. Hardening CI run `36005416310` passed at `2a237e1`; authenticated English/Arabic desktop/mobile QAS review remains required. Production is unchanged.

## Cost Calculator UX checkpoint — 2026-09-24
- The existing V42 Cost Calculator received a focused UI polish without changing recipe/cost/profit/margin logic.
- Legacy orange workflow styling now follows configurable admin theme tokens; live cost summary cards reuse the shared admin stat-card language.
- Raw Materials / Product Recipe / Profit Calculation shortcuts were added, and raw-material deletion now uses the shared in-app confirmation flow instead of browser `confirm()`.
- English/Arabic copy and focused UI regression coverage were added. See `docs/COST_CALCULATOR_UX_2026-09-24.md`.
- Source implementation and automated regression verification are complete for this polish iteration. Hardening CI run `36005416310` passed at `2a237e1`; authenticated English/Arabic desktop/mobile QAS review remains required. Production is unchanged.

## Storefront Foundation UX checkpoint — 2026-09-24
- Customer-facing defaults are now marketplace-neutral instead of electronics-specific, while exact customized content remains untouched.
- Duplicate homepage shortcut navigation was removed; the hero keeps one concise shortcut row.
- Header search now uses a real `/search` product-results flow with availability/offer/sort filters and pagination instead of submitting an ignored home-page query.
- Known fake contact defaults are removed from code/seeding and safely scrubbed only on exact legacy-value matches.
- Product/category/cart/checkout missing-image fallbacks now use a local storefront SVG instead of `via.placeholder.com`.
- Checkout now advertises only enabled payment methods and uses an aligned billing-address toggle; Product Detail preserves uncropped merchandise imagery and reports the true gallery count.
- English/Arabic copy and focused `StorefrontExperienceTest` coverage were added. See `docs/STOREFRONT_FOUNDATION_UX_2026-09-24.md`.
- Source implementation is complete for this foundation iteration; branch-head CI and authenticated English/Arabic desktop/mobile QAS review remain required. Production is unchanged.

## Storefront safety & content checkpoint — 2026-09-24
- The storefront foundation now also hardens customer-facing support/payment/legal flows: raw Paymob errors are hidden, localized contact-hours fallback is respected, Category Quick View preserves product image ratio, Notifications hides empty bulk actions, and Cart removal uses the shared in-app confirmation.
- Default Privacy / Terms / Refund / Shipping bodies no longer publish assumed legal commitments; blank policies show an explicit unpublished state until reviewed content is configured.
- Store Content & Policies now carries a legal publishing caution and all seven contact/cancellation toggles use the shared aligned switch treatment.
- Regression coverage was expanded across these flows. Shared product cards are now theme-aware, Category result count duplication is fixed, and Contact hides empty business detail panels while linking configured WhatsApp numbers directly. Detailed scope/QAS checks: `docs/STOREFRONT_FOUNDATION_UX_2026-09-24.md`.
- Hardening CI run `36011846373` passed at `473a119e`. Production remains unchanged; authenticated English/Arabic desktop/mobile QAS is still required before promotion.

## Storefront customer UX checkpoint — 2026-09-24
- Customer-facing Product Detail copy was cleaned up: internal stock/settings wording and the technical gallery count were removed, and empty descriptions no longer show placeholder `coming soon` copy.
- Checkout keeps its already-correct aligned billing switch, while customer reassurance/offer labels were simplified to clearer production wording without changing payment/order logic.
- The Home hero now follows configurable storefront brand tokens instead of hard-coded dark-blue/orange campaign colors, and previous/next/CTA arrow direction is correct for both LTR and RTL.
- Customer Order Details and Order Success now hide raw gateway `checkout_error`/provider-status details and use safe retry/support guidance instead.
- Cart recommendation labels were cleaned up so internal-style wording (`Personalized offers`, `Smart offers`, `Return path`) is no longer exposed to customers; recommendation logic is unchanged.
- English/Arabic copy and focused storefront regression coverage were updated. See `docs/STOREFRONT_CUSTOMER_UX_2026-09-24.md`.
- Source implementation and automated regression verification are complete for this iteration. Hardening CI run `36014798313` passed at `d13e6a3`; authenticated English/Arabic desktop/mobile QAS review remains required. Production is unchanged.

## Customer account and address book checkpoint — 2026-09-24
- A customer account overview, editable name/email with current-password protection for email changes, and a separate password-change form are implemented in source.
- Customer-owned saved addresses now have shipping/billing defaults with serialized mutations and fallback on deletion. Checkout can prefill a selected address and the billing default; each placed order retains an independent address snapshot. The billing-same-as-shipping toggle now submits its off state explicitly.
- English/Arabic account and address-book copy, compact navigation, in-app deletion confirmation, and focused ownership/profile/default/checkout regression tests were added. See `docs/CUSTOMER_ACCOUNT_ADDRESS_BOOK_2026-09-24.md`.
- Hardening CI run `36019574401` passed at application commit `05c2c51`: PHP syntax, clean MySQL migration, Blade compile, 115 tests (579 assertions), and frontend build. Authenticated English/Arabic desktop/mobile QAS remains pending. `main` and Production are unchanged.

## Purchases V2 receipt hardening checkpoint — 2026-09-24
- The next working-line batch serializes receipt under a purchase row lock and transaction, permits only ordered nonempty purchases, validates every product/variant line before stock changes, and treats repeated receipt as an informational replay.
- Admin purchase creation validates product–variant ownership under lock; awaiting count and receive buttons only reflect ordered purchases. The form now requires variants where appropriate and escapes dynamic content. EN/AR confirmations, errors and regression coverage are included. See [`docs/PURCHASES_V2_HARDENING_2026-09-24.md`](docs/PURCHASES_V2_HARDENING_2026-09-24.md).
- This new batch is not covered by the account QAS upload. Branch-head CI and authenticated QAS review are separate gates; Production and `main` are unchanged.

## Product Admin commercial UX checkpoint — 2026-09-24
- Product catalog management now has combined search/status/category/brand/content-readiness/inventory/featured filters, catalog-health counters, actionable low-stock and out-of-stock views, inline simple-product pricing/quantity controls, and clearer stock/content/featured state badges.
- Bulk operations now cover storefront visibility and featured merchandising. Activation intentionally keeps the current non-blocking content-readiness policy; incomplete content is advisory until barcode/variant/retail publication rules are finalized.
- Single-product and bulk destructive deletion now use explicit guarded confirmation state and in-app confirmation modals instead of immediate deletion/browser-only prompts. Managed product images remain part of the deletion flow.
- Regression coverage now exercises product editor retail identifiers, advisory readiness, bulk visibility, featured actions, and guarded bulk deletion. Additional bulk-visibility tests are currently running in CI at working-line head; do not treat this Product Admin checkpoint as QAS-verified until the final branch-head CI and authenticated visual checks pass.
- Product Admin remains source-complete for this iteration but not Production-promoted. Next gate: green branch-head CI, authenticated QAS desktop/mobile + Arabic/English review, then resolve any findings before moving the module to release-ready status.

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


### Production filesystem inspection — 2026-09-20
At `/home/u637857322/domains/tag-marketplace.com/laravel_app`:
- Laravel application files are present, including `artisan`, `composer.json`, `vendor/`, `storage/`, `.env`, `deploy.sh`, and `rollback.sh`.
- No `.git/` directory is present; this is a deployed file snapshot, not a Git checkout.
- Most application files are dated around 2026-04-17, while `.env` is server-local.
- Do not convert this live production directory into a Git worktree in place before a controlled rehearsal/backup plan.
- Preferred next step: inspect server runtime/tooling and free space, then prepare a separate V42 rehearsal directory so production remains untouched.


### Production runtime check — 2026-09-20
- Hostinger CLI PHP version: `PHP 8.3.33` (NTS) with Zend OPcache.
- This satisfies the application's Composer requirement (`php ^8.1`) at the runtime version level.


### Production Composer check — 2026-09-20
- Hostinger Composer version: `2.9.8`.
- Composer is running under PHP `8.3.33` from `/opt/alt/php83/usr/bin/php`.


### Production PHP extension check — 2026-09-20
Required runtime extensions were confirmed present on Hostinger CLI PHP 8.3.33:
- bcmath
- curl
- dom
- intl
- mbstring
- pdo_mysql
- xml / SimpleXML / xmlreader / xmlwriter
- zip

This clears the PHP-extension portion of the server preflight.


### Production Laravel runtime check — 2026-09-20
Hostinger production runtime currently reports:
- Application: Tag Marketplace
- Laravel: 10.48.29
- PHP: 8.3.33
- Composer: 2.9.8
- Environment: production
- Maintenance mode: OFF
- APP_DEBUG/runtime debug mode: ENABLED

Security/release note:
- Debug mode being enabled in production is a release blocker and should be changed to disabled before the next production deployment.
- Do not change it blindly mid-audit; verify the current server .env values first, then update in a controlled step and clear/rebuild config cache.


### Production debug configuration fix — 2026-09-20
- Confirmed production `.env` had `APP_DEBUG=true`.
- Created a server-side backup: `.env.backup_before_debug_fix`.
- Updated production `.env` to `APP_DEBUG=false`.
- Next step is to rebuild Laravel config cache and verify runtime reports Debug Mode disabled.


### Production debug verification — 2026-09-20
- Rebuilt Laravel config cache after changing production `APP_DEBUG=false`.
- Verified with `php artisan about --only=environment` that runtime Debug Mode is now OFF.
- Production remains out of maintenance mode.


### Production database connectivity check — 2026-09-20
- `php artisan migrate:status` completed successfully on production, confirming Laravel can connect to the configured database.
- Every migration file currently present in the deployed production snapshot is marked Ran.
- The V42 hardening branch contains three newer migration files not present in this production snapshot yet:
  - `2026_06_24_000000_create_cost_calculator_tables.php`
  - `2026_09_20_000000_normalize_growth_offer_learning_index.php`
  - `2026_09_20_235900_scrub_plaintext_provider_secrets.php`
- These must only be applied during the controlled V42 deployment/rehearsal after a database snapshot; do not run them on the current production snapshot now.


### Production env permission hardening — 2026-09-20
- Confirmed production `.env` and the temporary debug-fix backup were mode `644`.
- Changed both to mode `600`.
- Verified owner remains `u637857322`.
- This removes group/other read access from files containing production secrets.


### Production public webroot inspection — 2026-09-20
- `/home/u637857322/domains/tag-marketplace.com/public_html` exists and contains the deployed public assets.
- Visible entries include `index.php`, `.htaccess`, `build/`, `assets/`, `admin/`, `storage/`, and `uploads/`.
- The current deployment uses a split layout: Laravel application code under `laravel_app`, with web-facing public assets under `public_html`.
- Next check: inspect `public_html/index.php` to confirm exactly which Laravel application path it boots.


### Production bootstrap linkage confirmed — 2026-09-20
- `public_html/index.php` requires `../laravel_app/vendor/autoload.php`.
- It boots `../laravel_app/bootstrap/app.php`.
- Therefore the live webroot is explicitly wired to the sibling `laravel_app` directory.
- Any rehearsal must use a separate directory and must not repoint `public_html/index.php` until the release gate is approved.


### Production health check after hardening — 2026-09-20
- After setting `APP_DEBUG=false`, rebuilding config cache, and tightening `.env` permissions, the live site returned `HTTP 200` from `https://tag-marketplace.com`.
- Current production remains healthy after the server-side safety fixes.
- Next phase: prepare a separate V42 rehearsal directory; do not repoint `public_html` or run V42 migrations yet.


### Production Node.js check — 2026-09-20
- `node` is not installed/available in the Hostinger SSH shell.
- This is not a production deploy blocker for the current V42 flow because frontend assets are built in CI and `public/build` is committed/deployed with the application.
- The hardened `deploy.sh` does not require Node/npm on production.
- Do not install Node on the live server solely for deployment unless the deployment strategy changes.


## Post-deployment cleanup — 2026-09-23
- Routine QAS -> Production promotion was already validated end-to-end with application commit `95e9f50`.
- Two homepage labels used only as visible deployment markers were still present after validation: `V42 Special Offers` and `V42 Popular Products`.
- Cleanup commit `41a2f99` restores the intended customer-facing labels: `Today offers` and `Popular now`.
- Cleanup commit is pushed to `v42-clean-baseline` and is **not yet recorded as QAS/Production deployed**.
- Next operational step: deploy `41a2f99` to QAS, browser-verify, then Production dry-run + exact-commit execution if approved.
- After the cleanup is verified in Production, promote the validated V42 history to `main` so source control matches the production release line.


## Full project audit — 2026-09-23
- A repository-wide architecture, commercial-readiness, security, UX, bilingual, performance, testing, and operations audit is recorded in `docs/FULL_PROJECT_AUDIT_2026-09-23.md`.
- Immediate priority sequence: explicit owner/Super Admin authorization; remove fabricated storefront social proof; close credential-rotation evidence; real storefront search; shipping/tax; unpaid-online-order stock release; Paymob E2E; returns/reviews/addresses; then architecture/framework modernization.
- Latest audited `v42-clean-baseline` CI is green.

## UI/UX and commercial-readiness addendum — 2026-09-23
- Detailed screen and journey review: `docs/UI_UX_AND_COMMERCIAL_READINESS_REVIEW_2026-09-23.md`; baseline inspected at `c1f2146` on `v42-clean-baseline`.
- Live Production storefront inspection found `Moble`/`Toolsssss`, an unrelated product description, default support email/phone, mixed Arabic/English text, fabricated review and activity counts, repeated single-product merchandising, and the still-deployed V42 marker labels. A no-match header search returned the ordinary homepage.
- Additional code finding: the EGP 600 free-shipping progress message is disconnected from the current zero-valued shipping calculation; “best sellers” may use non-paid order items and falls back to newest products; authenticated Growth Operations exposes demo-data seed/clear without an environment guard.
- Admin UX implementation order: section the Livewire product form while preserving its state/save behavior; focus order-detail actions; separate branding workspaces; shorten the dashboard's default operator view. Growth already provides an example of focused subpages. Admin visual/mobile behavior still needs authenticated QAS review.
- Before real commercial traffic, close public-content/trust and authorization gates; then implement search, honest shipping/tax totals and unpaid-order stock lifecycle. Measure search, checkout, payment, fulfillment, support and Web Vitals against non-demo baselines rather than assuming a design change guarantees sales.
- This addendum is documentation only. No application code, QAS, Production, database, payment configuration, or public content was changed by the review. The cleanup commit `41a2f99` remains not recorded as deployed; `main` remains on the older baseline until separately reconciled.


## Admin Commerce Operations V2 — 2026-09-24
The current `v42-clean-baseline` branch now includes a broad operational-admin pass across the commercial back office. This work is branch-level only unless a later deployment checkpoint explicitly says otherwise.

- **Catalog / Products:** operational filters, readiness and inventory queues, catalog health KPIs, guarded delete flows, inline simple-product editing, featured controls, saved views, and advisory content readiness. Product activation intentionally remains non-blocking while retail/barcode/variant policy is finalized.
- **Categories / Brands / Attributes:** usage and content-coverage filters, guarded destructive actions, improved editor workspaces, and attribute-value operational visibility. Attribute-value scoped editing and in-use rename integrity remain follow-up items.
- **Orders / Inventory / Purchases / Suppliers:** action queues, financial and inventory context, improved procurement/supplier workspaces, bilingual UI coverage, and safer operational presentation. Purchase receive idempotency/transaction semantics and deeper order transition/refund tests remain release-hardening work.
- **Customers:** retention workspace with buyer/no-order/repeat/high-value views, customer revenue context, net spend and average-order detail metrics, and improved account/access presentation.
- **Coupons / Promotions:** promotion operations queues and builder UX, percentage validation, schedule visibility, real PromotionEngine regression coverage, required category targeting for category-percentage rules, and normalization that clears stale fields when promotion type changes.
- **Deliveries / Payments:** action/exception queues and reconciliation-oriented detail UI. Delivery transition rules and payment transition/idempotency hardening remain explicit follow-up items.
- **Localization:** English and Arabic strings were expanded across the touched admin workspaces; touched legacy screens should continue to be checked for untranslated strings during each subsequent batch.
- **Localization / consistency:** English and Arabic coverage was expanded across touched workspaces. Attribute Values now follows the same operational Admin V2 language for headers, metrics, protected actions, search/empty states, loading feedback, and destructive confirmations.
- **Verified CI:** Product/Promotion regression suite was clean at run `35932953241`. Attribute Value integrity fixes and their final UI/i18n batch are verified green at run `35934209260` on commit `1672bc9a`.

## Attribute Values integrity + UX closure — 2026-09-24
- Scoped edit/save prevents a value ID from another attribute from being edited or reassigned through the current attribute workspace.
- Variant usage remains keyed by attribute + textual value; therefore an in-use value cannot be renamed because that would silently desynchronize existing variant records.
- In-use values cannot be deleted. Unused values remain editable/deletable and same-attribute duplicates remain rejected.
- Total / in-use / unused KPIs are calculated from the complete attribute value set rather than the current search result.
- Added `AdminAttributeValueIntegrityTest` coverage for cross-attribute access, protected rename/delete, normal unused rename/delete, and duplicate rejection.
- UI now communicates the lock directly, disables invalid edit/delete actions, distinguishes search-no-results from a genuinely empty attribute, and shows save progress.
- Missing English/Arabic copy for the workspace and protection states was completed.
- Final Attribute Values batch CI: **green**, run `35934209260`, head `1672bc9a`.

### Next hardening sequence
1. Harden delivery transition/status/timestamp/notification rules.
2. Verify purchase receiving transaction/idempotency and inventory movement behavior.
3. Continue Settings/branding/colors UX overhaul.
4. Build employee operations, POS/cashier, barcode workflows, and detailed invoice printing as separate coherent product batches.

> Standing Definition of Done for each batch: business safety + UI/UX + Admin V2 consistency + EN/AR localization + regression tests + documentation + verified CI.


## Payments V2 hardening closure — 2026-09-24
- Explicit safe manual transition allow-list; refunded is terminal and paid cannot be manually downgraded.
- Gateway callbacks are row-locked and replay-safe; terminal paid/refunded states reject stale downgrades.
- Regression coverage includes invalid manual regression, callback replay idempotency, and late-failure protection after paid confirmation.
- Payment Details locks terminal financial states, exposes only safe next states, points returned-money handling to the order refund workflow, and surfaces HMAC plus gateway reconciliation evidence.
- English/Arabic safety and reconciliation copy completed for the touched payment workflow.
- Final Payments V2 CI: **green**, run `35935259115`, head `48fc7c0d`.


## Admin UI/UX consistency backlog — 2026-09-24
These are cross-admin requirements, not isolated screen fixes, and should be applied progressively to every touched admin workspace.

- **Theme system:** redesign the current admin themes/colors; the existing palette is not the desired quality bar. Build a more polished, cohesive theme/token system rather than page-specific color patches.
- **Page structure:** long or visually flat screens must be divided into clear sections/cards/workspaces. The Brand create/edit screen is a concrete example of a form that currently feels insufficiently structured.
- **KPI/header cards:** redesign the recurring header metric cards (for example Open/Closed/count cards) into a stronger reusable component and migrate the improved pattern across admin pages instead of fixing individual screens.
- **Switch alignment:** audit toggle/switch controls globally. Current switches can sit outside or misalign with their label/form row, especially in RTL; fix the shared layout/component so the correction propagates consistently.
- **Analytics & Insights:** treat this as a major redesign target. The current long page lacks hierarchy and does not consistently follow the Admin V2 visual language; improve information architecture, sectioning, metric presentation, spacing, responsive behavior, and shared components.
- **Categories:** explicitly audit Arabic/English parity, mixed-language copy, RTL/LTR layout, labels, validation, empty/loading states, and terminology.
- **Consistency rule:** every subsequent admin batch must reuse the same page shell, section/card language, KPI components, filters, actions, spacing, typography, states, confirmations, and bilingual/RTL behavior. Avoid one-off UI patterns unless the workflow genuinely requires them.
- **Quality rule:** when an old screen is touched, fix obvious UI/UX, localization, RTL, and consistency defects encountered in that screen rather than preserving them as legacy debt.
