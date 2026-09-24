# CURRENT PHASE
## V42 Production Hardening

### Official baseline
- **V42** — Cost Calculator refactor + Arabic/English translation updates
- Clean Git baseline completed on branch `v42-clean-baseline`.
- Baseline verification: all expected V42 source files matched by Git blob hash after intentional runtime/security exclusions.

### Current phase
- Production hardening before promotion to `main` and before any Production deploy.

### Completed baseline work
- Removed tracked `.env`.
- Removed tracked dependency/runtime/local-data artifacts including `vendor/`, `node_modules/`, local uploads, logs, sessions, Laravel cache files, local SQLite DB, and backup files.
- Tightened `.gitignore`.
- Preserved application source/assets required by V42.
- Kept `main` and Production unchanged.

### Current hardening gate
1. Rotate any credentials that have previously appeared in Git history.
2. Verify payment callback/HMAC validation and payment idempotency.
3. Verify authorization and privilege-escalation paths.
4. Verify stock, coupon, refund, and order concurrency/idempotency.
5. Verify Cost Calculator formulas and reporting semantics.
6. Run automated tests and critical manual regression flows.
7. Validate deployment and rollback procedure.
8. Promote the verified baseline to `main` only after the gate is green.

### Release rule
No Production deployment until the hardening gate is completed or a remaining risk is explicitly documented and accepted.


### Hardening progress — 2026-09-20
Closed in code:
- clean-clone Laravel runtime directories are preserved with tracked `.gitkeep` files
- Growth controller no longer performs database writes/queries during route discovery
- Paymob iframe URL recursion fixed
- Paymob callback HMAC is mandatory and enforced for state-changing callbacks
- Paymob callback amount, currency, and integration ID are checked against the expected payment
- paid/refunded gateway states are protected from late callback downgrades
- admin role changes require a super admin
- Paymob logs no longer retain full callback payloads/secrets/PII

Verification completed:
- V42 baseline hash verification: zero missing and zero mismatched expected source files
- PHP syntax scan: 298 files, zero syntax errors
- Laravel route discovery: 187 routes loaded successfully
- Paymob HMAC smoke test: valid GET/POST signatures accepted; invalid signature rejected
- Paymob iframe URL smoke test passed

Environment limitation during verification:
- PHPUnit/config/view cache commands cannot be fully executed in the current test container because required PHP extensions (DOM, mbstring, XML/XMLWriter) are unavailable there. This does not close the testing gate; CI/server verification remains required.


### Hardening checkpoint — Green CI (2026-09-20)
The V42 hardening branch now has a successful GitHub Actions production-like build on MySQL 8.

Closed in this checkpoint:
- checkout cart rows are locked during order creation to prevent duplicate orders from double-submit
- stock decrement is atomic and refuses overselling
- coupon usage limits are consumed atomically
- order cancel/status/refund mutations serialize on the order row
- repeated cancellation cannot restore stock twice
- concurrent refunds cannot exceed the remaining refundable balance
- manual payment status changes cannot bypass the order refund ledger
- refund ledger state takes precedence during order payment-status synchronization
- staff-role management is restricted to super admins and cannot clear an admin into implicit legacy super-admin state
- Deploy Center is opt-in instead of enabled by default
- production deploy health checks require curl and verified TLS
- MySQL clean migration failure caused by an oversized growth-learning unique index was fixed
- compatibility migration added for existing databases

Latest successful CI evidence:
- Composer validation/install: passed
- PHP syntax scan: passed
- MySQL 8 `migrate:fresh`: passed
- Laravel boot + route discovery: passed
- config cache + Blade view compile: passed
- current PHPUnit suite: passed
- npm install + Vite production build: passed

Important limitation:
- the current PHPUnit suite is still very small, so targeted regression tests for the hardened business rules remain required.

### Additional hardening — 2026-09-20
- custom staff roles cannot be deleted while assigned to admin accounts; deletion is serialized with a database row lock to prevent cascade-based privilege escalation
- the permissions UI no longer offers an empty legacy-role assignment option; legacy fallback admins are mapped to the explicit Super Admin role when saved
- added a regression test covering assigned-role deletion protection
- removed remaining tracked runtime/user uploads from `storage/app/livewire-tmp` and `storage/app/public` and added explicit Git ignore rules for both paths

### Financial/destructive-action hardening follow-up — 2026-09-20
- gateway payment callbacks now serialize on the Payment row so competing paid/failed callbacks cannot race from the same stale pending state
- order payment-status synchronization now serializes on the Order row
- permanent order deletion is disabled in both backend and admin UI to preserve payment, refund, coupon, inventory, and audit history
- added regression coverage for paid-payment terminal behavior and order retention
- CI verification for this follow-up is pending on the latest branch commit


### Final code-hardening pass — 2026-09-20
Verified and closed on `v42-clean-baseline`:
- financial mutation lock ordering is standardized as Order → Payment to avoid callback/refund lock inversion
- a full refund now aligns the Payment ledger to `refunded`; partial refunds keep the original paid transaction while the Order remains `partially_refunded`
- late gateway failure callbacks cannot downgrade a fully refunded payment
- checkout rollback coverage proves that a late business-rule failure rolls back the Order, items, Payment, inventory movements, stock decrement, and cart mutation together
- cancellation restocking now creates exactly one `refund_restock` inventory movement and repeated cancellation cannot duplicate it
- cancellation restock records historical movement cost without overwriting the product's current cost valuation
- Cost Calculator `profit_margin` now uses profit / selling price; the old profit / cost formula was markup, not margin
- deploy backups no longer contain a duplicate app-level `.env`; deploy/rollback files use private `umask 077` handling
- manual rollback now enters maintenance mode, restores files, rebuilds caches, brings the app up, and requires a verified HTTPS health check
- rollback/deploy temporary environment copies are process-unique and private
- Paymob callback failure logging no longer stores the raw callback payload or full callback URL
- Paymob callback service results expose callback shape only, not the raw payload

Latest verification:
- GitHub Actions head `b9a46a6`: **green**
- PHP syntax: passed
- Bash syntax: passed
- MySQL 8 clean migration: passed
- Laravel boot/routes: passed
- config cache + Blade compile: passed
- PHPUnit including new hardening regressions: passed
- frontend production build: passed

### Remaining release gate
The remaining blockers are now operational/external rather than known code-hardening failures:
1. Rotate all credentials that may have appeared in historical Git, including production DB, APP_KEY where appropriate, SMTP, Paymob, WhatsApp/Meta, and deploy shared secrets actually in use.
2. Execute a real Paymob sandbox/test end-to-end payment: initiate, success callback, failed/declined callback, delayed callback, refresh/retry, and duplicate callback behavior.
3. Rehearse deploy on the target server/staging-like environment, confirm pre-migration database snapshot creation, and rehearse file rollback plus explicit database recovery procedure.
4. Run final production smoke checks, then promote V42 to `main`.
5. Deploy Production only after the above gate is green.


### Paymob account discovery — 2026-09-20
- New dashboard, Test mode: Payment Integrations table is empty.
- Old dashboard, Test mode: Developers -> Payment Integrations is also empty.
- Attempting to create a new Non-Shopify / MIGS / EGP Test integration returns: "Cannot create more than 1 test integration with the same gateway type and currency."
- This means the account backend recognizes an existing MIGS/EGP Test integration even though neither dashboard currently renders it.
- Next diagnostic: inspect Developers -> Iframes in the old dashboard for the previously used iframe/integration linkage. If no legacy artifact is visible there either, treat this as a Paymob account-side integration visibility/state issue and open a support case rather than creating another integration.


### Paymob legacy artifact confirmed — 2026-09-20
Old dashboard -> Developers -> Iframes shows two Test-mode IFrames:
- IFrame `1024106` — `Installment_Discount`
- IFrame `1024107` — `My new card Iframe`

This confirms the account still retains legacy checkout artifacts even though both old and new Payment Integrations pages render no integration rows. The next diagnostic is to open/edit IFrame `1024107` and inspect which payment integration(s) it is linked to. Do not create another MIGS/EGP Test integration while Paymob reports that one already exists.


### Paymob iframe edit inspection — 2026-09-20
- Old dashboard IFrame `1024107` ("My new card Iframe") was opened in edit mode.
- The edit screen exposes only presentation/customization fields (Name, Description, HTML, JavaScript, CSS).
- No Payment Integration ID or MIGS linkage is exposed from the IFrame editor.
- Conclusion: the hidden MIGS/EGP Test integration cannot be recovered from the IFrame UI. Since both Payment Integrations pages are empty while creation is blocked as a duplicate, this is now treated as a Paymob account-side hidden/orphan integration state.
- Next action: Paymob support/account manager should reveal, restore, or reset the Test MIGS/EGP integration. Do not create/duplicate/delete IFrames as a workaround.


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

### Deliveries V2 hardening — 2026-09-24
Source implementation on `v42-clean-baseline` now:
- serializes delivery mutations with a row lock inside a transaction
- enforces valid delivery-status transitions, with a separate store-pickup path
- records `shipped_at` / `delivered_at` once according to lifecycle state
- requires shipment evidence before Out for delivery
- avoids duplicate database/WhatsApp status notifications on metadata-only saves and repeated requests
- disables invalid status choices in the admin order view and confirms real status changes in-app
- adds focused delivery regression tests and Arabic/English copy

Verification state:
- branch-head CI: pending
- authenticated QAS visual/behavior review: pending
- Production: unchanged

See `docs/DELIVERIES_V2_2026-09-24.md` for acceptance checks.

### Customer account and address book — 2026-09-24
The working branch now contains customer profile/password management and saved shipping/billing addresses with ownership checks, transactional default changes, checkout prefill and independent order address snapshots. It also corrects the checkout billing switch's off-state submission. See `docs/CUSTOMER_ACCOUNT_ADDRESS_BOOK_2026-09-24.md`.

Verification: Hardening CI run `36019574401` passed at application commit `05c2c51` (115 tests, 579 assertions). Authenticated English/Arabic desktop/mobile QAS remains pending. `main` and Production unchanged.

### QAS account upload and Purchases V2 work — 2026-09-24
The operator reported the QAS upload targeting `a1e8c57` after a clean-checkout/environment/remote-SHA check and a 157,110-byte rehearsal database snapshot. Public QAS login responded HTTP 200 in Arabic. Exact server HEAD and authenticated account/address-book checks remain unverified.

The next working-line code batch hardens purchase receiving and product–variant validation with bilingual admin confirmation and focused regression coverage. See `docs/PURCHASES_V2_HARDENING_2026-09-24.md`. Hardening CI run `36023870549` passed at `e723b0c` (122 tests, 628 assertions), and final application head `0a08253` passed run `36024229399`. After the QAS upload, the operator supplied server-side `git rev-parse HEAD` output matching `0a08253d61c26f7be841de93c9e5b7ad2d7efdfe`; public login returned HTTP 200. Authenticated purchase/account reviews remain pending; `main` and Production unchanged.

### Continued development with consolidated manual QAS review — 2026-09-24
The owner deferred manual QAS tests until a later consolidated review phase. Each code slice still receives automated CI, and no unreviewed QAS behavior is marked accepted for Production.

The next source slice adds a counted-stock adjustment workspace with product/variant selection, stale-count protection, one signed movement and admin attribution per real change, bilingual UI and regression tests. See `docs/INVENTORY_ADJUSTMENT_2026-09-24.md`. Hardening CI run `36028959639` passed at code commit `50cb707` (128 tests, 675 assertions). QAS remains on `0a08253` and does not include this batch. `main` and Production are unchanged.

### Catalog stock audit — 2026-09-24
The product editor now records opening and changed simple/variant stock as signed inventory movements with admin activity inside the catalog transaction. It checks loaded counts and variant membership under lock, blocks structural changes that would discard stock, and refreshes variant IDs after save. Catalog quick quantity edits use the counted-stock service; copies start at zero stock. New copy and audit guidance is bilingual. See `docs/CATALOG_STOCK_AUDIT_2026-09-24.md` and `tests/Feature/CatalogStockAuditTest.php`.

Verification: automated CI pending for this source batch; consolidated manual QAS review remains deferred by the owner. QAS application HEAD is still the operator-confirmed `0a08253`; `main` and Production are unchanged.


### Barcode / SKU Foundation V1 — 2026-09-24
Source implementation on `v42-clean-baseline` now:
- enforces SKU uniqueness across product and variant records in the active product editor
- keeps barcode uniqueness across the same catalog scope
- adds exact SKU resolution alongside exact barcode resolution, with ambiguity protection for legacy collisions
- lets catalog search match variant SKU/barcode and return the parent product
- clears SKU/barcode on product duplication while keeping copied stock at zero
- adds EN/AR guidance, regression tests and a focused implementation note

Verification state:
- Hardening CI `36035395851`: passed at `05eb13c` with 141 tests (762 assertions) plus frontend production build
- consolidated authenticated QAS review: deferred by owner
- QAS application HEAD: still `0a08253`
- Production and `main`: unchanged

See `docs/BARCODE_SKU_FOUNDATION_V1_2026-09-24.md`.


### Barcode Scan-to-Find V1 — 2026-09-24
Source implementation on `v42-clean-baseline` now:
- adds a permission-scoped Inventory scanner workspace for HID keyboard-mode barcode scanners
- resolves exact product/variant barcodes through the shared identifier service
- links exact matches to the existing stale-count protected stock adjustment workflow
- requires explicit variant choice when a parent barcode identifies a variant product
- blocks unknown or ambiguous legacy barcodes from stock actions
- adds Inventory/Adjust Stock navigation, EN/AR copy, regression tests and implementation notes

Verification state:
- Hardening CI `36036425394`: passed at application head `fea30ea` with 147 tests (794 assertions) plus frontend production build
- consolidated authenticated QAS review: deferred by owner
- QAS application HEAD: still `0a08253`
- Production and `main`: unchanged

See `docs/BARCODE_SCAN_TO_FIND_V1_2026-09-24.md`.


### Barcode Label Printing V1 — 2026-09-24
Source implementation on `v42-clean-baseline` now:
- renders dependency-free Code 128B SVG from stored product/variant barcodes
- adds real-size 50×30, 60×40 and 70×40 mm print previews
- supports 1–100 copies and optional current-price display
- links exact scanner matches to label printing
- requires exact variant selection for variant products
- keeps missing/non-ASCII legacy identifiers visible but non-printable instead of rewriting them
- adds EN/AR copy, Code 128 unit tests, label workflow feature tests and implementation notes

Verification state:
- Hardening CI `36037456794`: passed at application head `83d5ee6` with 156 tests (826 assertions) plus frontend production build
- physical print/rescan QAS review: deferred to consolidated owner review
- QAS application HEAD: still `0a08253`
- Production and `main`: unchanged

See `docs/BARCODE_LABEL_PRINTING_V1_2026-09-24.md`.


### Purchase Barcode Receiving V1 — 2026-09-24
- Persists verified quantity per purchase line with last scanner metadata.
- Each exact barcode scan verifies one unit without changing stock.
- Parent variant-product barcodes, unknown/out-of-purchase items and ambiguous identifiers are rejected.
- Duplicate purchase lines require explicit line selection instead of guessing.
- Undo-one correction and ordered-quantity caps are enforced.
- Barcode-verified final receipt rechecks progress under locks before the existing replay-safe stock receipt.
- Manual receiving remains available as a protected fallback.
- Hardening CI 36038635115 passed at application head 0a2ceb9 with 163 tests (889 assertions) plus frontend production build.
- QAS remains on 0a08253; consolidated authenticated review is deferred. Production and main are unchanged.


### POS / Cashier Foundation V1 — 2026-09-24
Application implementation verified at `ef9797d`:
- dedicated `pos.manage` permission and Cashier system role
- persistent per-cashier POS cart
- exact barcode scan for simple products and variants
- stock-capped quantity changes with stale-write protection
- Cash and Card Terminal checkout
- server-side locked price/cost/stock recheck
- canonical POS Order, Order Items, paid Payment, Inventory Movements, profit snapshot and cashier audit entry
- completed-cart replay safety
- cashier-owned Sale Summary and recent-sales access
- EN/AR UI and POS regression coverage

Verification:
- Hardening CI `36041425089`: passed
- 173 tests / 969 assertions
- clean MySQL migration, Laravel routes/boot, Blade/config compilation and frontend production build passed
- consolidated authenticated QAS: deferred by owner
- QAS application HEAD: `0a08253`
- Production and `main`: unchanged

Next intended POS slice: formal Receipt / Invoice V1 on top of the verified sale ledger.


### POS Receipt Printing V1 — 2026-09-24
Application implementation verified at `bca25c1`:
- dedicated Print receipt action from the completed POS Sale Summary
- read-only receipt rendering under the existing cashier-ownership / broader order-review permission boundary
- 58 mm, 80 mm and A4 browser-print formats with safe 80 mm fallback
- stable V1 receipt reference reuses the recorded POS order number
- store identity/contact fields reuse existing Store Settings
- printed values come from recorded Order / Order Items / Payment data; viewing or printing does not mutate stock, payments or orders
- EN/AR receipt copy and focused receipt-safety regression coverage

Verification:
- Hardening CI `36042936219`: passed at application head `bca25c1`
- 174 tests / 983 assertions
- clean MySQL migration, Laravel routes/boot, Blade/config compilation and frontend production build passed
- consolidated authenticated and physical-printer QAS: deferred by owner
- QAS application HEAD: `0a08253`
- Production and `main`: unchanged

Deliberate boundary: this is a sales receipt, not a fiscal/tax invoice. Formal invoice work still requires verified legal/tax identity, configured tax rules and jurisdiction-appropriate numbering.


### POS Hold / Resume V1 — 2026-09-24
Application implementation verified at `8bf77cc`:
- dedicated held POS cart state with optional label and held timestamp
- cashier-owned Hold / Resume / Discard workflow
- customer name and sale notes preserved across hold/resume
- held queue visible inside the POS workspace
- one active cart invariant protected by the existing unique open token plus cashier-row serialization during resume
- resume blocked while the current active cart contains items
- empty active cart safely abandoned when a held sale is resumed
- hold/resume/discard create cashier-attributed activity-log entries
- no stock, Order, Payment or Inventory Movement mutation while holding/resuming/discarding
- EN/AR UI and focused regression coverage

Verification:
- Hardening CI `36044608065`: passed at application head `8bf77cc`
- 177 tests / 1047 assertions
- clean MySQL migration, Laravel routes/boot, Blade/config compilation and frontend production build passed
- consolidated authenticated QAS: deferred by owner
- QAS application HEAD: `0a08253`
- Production and `main`: unchanged

Deliberate boundary: held carts do not reserve stock or freeze prices. Checkout remains authoritative and rechecks current stock/prices.


### POS Customer Attach V1 — 2026-09-24
Application implementation verified at `9129998`:
- limited POS customer lookup by name/email with 2-character minimum and 8-result cap
- customer-only results; staff/admin accounts are excluded and rejected by the service
- cashier-owned Attach / Detach customer workflow
- attached account persists through Hold / Resume and is cleared by full cart Clear
- checkout locks and revalidates the attached customer before writing the sale
- completed POS Order links through `user_id` and snapshots customer name/email
- walk-in sales remain supported and unlinked
- customer My Orders correctly handles linked POS purchases as in-store/store-pickup orders
- POS Cash/Card Terminal customer payment instructions corrected
- receipt and Sale Summary surface attached customer identity
- EN/AR copy, audit entries and focused POS/storefront regression coverage

Verification:
- Hardening CI `36046473889`: passed at application head `9129998`
- 180 tests / 1094 assertions
- clean MySQL migration, Laravel routes/boot, Blade/config compilation and frontend production build passed
- consolidated authenticated QAS: deferred by owner
- QAS application HEAD: `0a08253`
- Production and `main`: unchanged

Deliberate boundary: POS lookup exposes name/email only and does not grant customer-management, address, order-history or profile-edit capabilities.


### POS shift review live list — 2026-09-24
- Manager Cash Shift Review now has debounced cashier name/email search, immediate status filters, and pagination that update its server-rendered results without a full-page reload.
- The same permission-protected route and query serve both the normal HTML page and a small results fragment. Without JavaScript, the GET form, Reset link and pagination links still work.
- A reusable `public/admin/js/live-list.js` helper cancels stale requests, preserves shareable query URLs and browser Back/Forward, announces loading/results/errors, and provides a full-page fallback on failed requests.
- Result rows and notes remain Blade-escaped. Global shift KPI cards remain unfiltered and are refreshed on a normal page load; the live fragment only replaces the filtered table/count/pagination.
- Focused regression coverage checks manager authorization on full/fragment requests, identical filtered records, and escaped notes. The previous branch CI at `a4fbfe9` failed five POS tests; this working batch corrects stale role/search/schema assertions, an unreachable missing-variant validation message, and a Sale Summary Blade parse error. A second compiled-Blade parse error in Customer Address Book was corrected as well.
- Local verification: POS 32 tests / 330 assertions and Customer Account 3 tests / 58 assertions passed in a separate SQLite test worktree with temporary database-compatibility adjustments to three MySQL-only expressions/migrations; no such test-only changes are in the application branch. All 166 compiled Blade views passed PHP syntax lint, as did changed PHP files; Node syntax, EN/AR JSON parsing and `git diff --check` passed.
- After explicit owner approval, the GitHub connection published identical source trees on `v42-clean-baseline` as `bb10f96` (shift review) and `19a4b8c` (Orders). GitHub assigned new commit IDs because its API recorded new commit metadata; the final tree matches local `f69d5cb` exactly. [Hardening CI 36059356857](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36059356857) passed on MySQL 8 at `19a4b8c` with 197 tests / 1248 assertions and frontend build. QAS application HEAD was last operator-confirmed at `0a08253`; no new QAS/Production deployment is claimed. Consolidated authenticated EN/AR QAS review remains deferred by the owner.

### Admin Orders live list — 2026-09-24
- The reusable live-list helper now handles same-route queue and sort links as well as GET search, filter and pagination controls. Successful navigation synchronizes the form with the shareable URL; Back/Forward, no-JavaScript GET navigation and the full-page error fallback remain available.
- Admin Orders serves the same server-filtered rows in its full page and results-only HTML fragment. Queue shortcuts and sort links update their selected state with the result table. Global revenue/status cards remain unfiltered and update on a normal page load.
- The `orders.view` route boundary applies equally to full and fragment reads. The list no longer renders a quick-status edit form for read-only staff or unrelated navigation links without permission; status mutations continue through the existing `orders.manage` server route and confirmation feedback. Injected quick-status forms still show a saving state.
- The fragment skips the full-page revenue/refund aggregates. Both views retain Blade-escaped customer data and EN/AR status/error copy.
- Two focused feature tests passed locally (29 assertions) in the separate SQLite compatibility test worktree. All compiled Blade templates passed PHP lint, along with changed PHP files, JavaScript syntax and `git diff --check` in the application checkout. Integrated MySQL CI passed at `19a4b8c` in run `36059356857` (197 tests / 1248 assertions).
- Explicit owner approval superseded the earlier automatic publication block. The GitHub connection recreated the two local source trees exactly as `bb10f96` and `19a4b8c`; the final source tree is identical to local `f69d5cb`. QAS was last confirmed at `0a08253`; `main` and Production are unchanged, and consolidated manual QAS review stays deferred.

### Admin Customers live list — 2026-09-25
- Admin Customers now uses the shared live-list pattern for debounced name/email search, role/activity/value filters, per-page changes, quick customer queues and pagination without a full-page reload.
- The same permission-protected route serves a small Blade results fragment for live requests while preserving the normal GET form, shareable URL, browser Back/Forward and full-page fallback when JavaScript is unavailable or a request fails.
- Live fragment requests skip the global KPI/revenue block; linked-customer revenue on the full page now uses a direct order aggregate instead of loading every user aggregate into PHP.
- Shared live-list link delegation now occurs at the stable workspace root so live queue shortcuts can sit outside the replaceable result container while pagination and sort links keep the existing behavior.
- Focused regression coverage was added in `AdminCustomerLiveListTest` for authorization, filters, fragment/full-page parity and escaped customer data. Application source revision is `51a3418`; branch-head CI is still pending verification. QAS remains at the previously recorded `0a08253`, and Production is unchanged.
