# V42 Rehearsal Continuation Checkpoint

Last consolidated: 2026-09-20

This file is the handoff/checkpoint for continuing the Dynamic e-commerce V42 hardening/rehearsal work in a new chat. It intentionally contains no passwords, API keys, HMAC secrets, SSH passwords, or other secret values.

## Branch / release state

- Hardening branch: `v42-clean-baseline`.
- Rehearsal application clone was frozen at commit `e30a192`.
- Production `main` was intentionally not merged/deployed during this rehearsal work.
- Treat any commits after `e30a192` as documentation-only unless separately verified.
- Do not deploy to production or merge to `main` until the rehearsal gates below are complete.

## Production baseline verified before rehearsal

- Live Laravel application remains in the existing production application directory; the live webroot `public_html/index.php` explicitly boots the sibling production Laravel app.
- The live production application directory is a deployed snapshot, not a Git worktree.
- Production `APP_DEBUG` was changed from enabled to disabled, Laravel config cache rebuilt, and runtime verification confirmed Debug Mode OFF.
- Production `.env` and the temporary debug-fix backup were hardened to mode `600`.
- Production writable Laravel directories (`storage`, `storage/framework`, `storage/logs`, `bootstrap/cache`) were verified writable.
- Production Laravel database connectivity was verified with `migrate:status`; every migration present in the deployed production snapshot was already Ran.
- Production health check after the safety changes returned HTTP 200.
- Server runtime observed during this rehearsal:
  - PHP 8.3.33
  - Composer 2.9.8
  - MariaDB server 11.8.9
  - Node.js is not available in the SSH shell; this is not a deployment blocker because frontend assets are built in CI / committed under `public/build`.
- The existing production app must not be converted into a Git checkout in place during rehearsal.

## V42 rehearsal application

- Separate rehearsal application directory exists: `laravel_app_v42_rehearsal`.
- It was cloned from `v42-clean-baseline` and verified clean at commit `e30a192`.
- `composer install --no-dev --prefer-dist --optimize-autoloader` completed successfully on the server.
- Rehearsal environment is isolated from production:
  - `APP_ENV=staging`
  - `APP_DEBUG=false`
  - separate APP_KEY generated
  - cache/session configured for isolated non-production behavior
  - mail disabled via array mailer
  - WhatsApp disabled
  - deploy center disabled
  - `APP_NAME="Tag Marketplace V42"`
  - `APP_URL=https://v42.tag-marketplace.com`
  - `FORCE_HTTPS=true`
- Rehearsal DB credentials are stored only in the rehearsal server `.env`; do not copy them into Git or chat.

## V42 rehearsal database

- A separate Hostinger MariaDB database/user was created specifically for V42 rehearsal.
- Production DB is not used by the rehearsal.
- Rehearsal DB server is MariaDB 11.8.9.
- A real Laravel authentication check succeeded against the rehearsal DB.
- `php artisan migrate:fresh --force` completed successfully on the rehearsal MariaDB database.
- All migrations passed, including:
  - `2026_06_24_000000_create_cost_calculator_tables.php`
  - `2026_09_20_000000_normalize_growth_offer_learning_index.php`
  - `2026_09_20_235900_scrub_plaintext_provider_secrets.php`
- Earlier SQLite rehearsal was abandoned because migration `2026_04_03_130000_update_product_related_unique_index_for_aov_manager.php` uses foreign-key/index operations SQLite does not support. This is not a MariaDB production issue.
- A prior rehearsal DB password with special characters caused dotenv parsing trouble. It was replaced with a strong hex-only rehearsal password. Do not reintroduce or expose the old value.

## Rehearsal webroot / subdomain

- Hostinger subdomain created: `v42.tag-marketplace.com`.
- Hostinger document root is `public_html/v42`.
- V42 public assets were copied from the rehearsal Laravel `public/` directory into `public_html/v42`.
- `public_html/v42/index.php` was changed to boot `laravel_app_v42_rehearsal` for:
  - maintenance file
  - `vendor/autoload.php`
  - `bootstrap/app.php`
- A backup of the original copied V42 index exists as `public_html/v42/index.php.backup_before_rehearsal_link`.
- The production root `public_html/index.php` was not changed.

## DNS / Cloudflare

- Authoritative DNS is Cloudflare, not Hostinger.
- Authoritative nameservers:
  - `braden.ns.cloudflare.com`
  - `daphne.ns.cloudflare.com`
- Production root and `www` records are Cloudflare-proxied A records to the Hostinger origin.
- A new `A` record for `v42` was created in Cloudflare and is currently **DNS only** for clean rehearsal troubleshooting.
- Direct query to the Cloudflare authoritative nameserver returned the expected origin address for `v42.tag-marketplace.com`.
- The Hostinger server resolver initially still returned `DNS_NOT_READY`; treat that as resolver cache/propagation, not a missing Cloudflare record.
- Do not change the production root/www DNS records during V42 rehearsal.

## Current web / SSL state — exact stopping point

- HTTP routing was tested directly to the Hostinger origin with the V42 Host header and returned **HTTP 200 OK**.
- That proves Hostinger routing -> `public_html/v42` -> V42 `index.php` -> `laravel_app_v42_rehearsal` -> Laravel is working.
- HTTPS direct-to-origin test currently fails with a TLS internal error because the new V42 certificate is not ready yet.
- Hostinger SSL screen shows:
  - production domain Lifetime SSL: Active
  - `v42.tag-marketplace.com` Lifetime SSL: **Installing**
- **Current blocker: wait for V42 SSL status to change from Installing to Active.**
- Do not click Cancel on the V42 SSL installation.

## Next steps after SSL becomes Active

1. Verify DNS resolver now sees `v42.tag-marketplace.com`.
2. Run HTTPS health check:
   `curl -I -L --max-time 20 https://v42.tag-marketplace.com`
   and require a successful 2xx/expected redirect chain.
3. Open the V42 site in a browser and perform the first staging smoke test.
4. Keep Cloudflare `v42` DNS-only until origin HTTPS is verified. After successful origin HTTPS testing, decide whether to switch V42 to Cloudflare Proxied and re-test.
5. Rehearsal DB is intentionally fresh/empty. Add only controlled test/seed data needed for functional smoke tests; do not copy production secrets.
6. Run critical application smoke flows on V42 before production:
   - home/catalog/product rendering
   - authentication/admin access
   - cart/checkout/order creation
   - stock/coupon concurrency-sensitive paths
   - cancel/refund/payment-ledger behavior
   - Growth/Admin authorization boundaries
   - provider-secret screens do not render plaintext secrets
7. Paymob end-to-end remains a separate parked gate. The application code has Unified Checkout + legacy fallback hardening, but merchant onboarding/verification and live dashboard state still need completion/verification before production payment signoff.
8. Before final production deployment, prepare a deployment path compatible with the fact that the current live `laravel_app` is not a Git worktree. Do not run the hardened `deploy.sh` against the current snapshot until this release-layout issue is intentionally resolved.
9. Final production gate must include file backup, DB snapshot, credential rotation for previously exposed credentials, environment verification, migration review, HTTPS health check, and rollback readiness.

## Operational rules for continuation

- Guide server work one command at a time; explain what each command is checking/changing and stop for output.
- Never request or print full `.env`.
- Never paste passwords/secrets into chat or Git.
- Never run `migrate:fresh` against production.
- Never touch production `public_html/index.php`, production DB, or `main` merely to test V42.
- Prefer read-only checks before mutations.
- Keep production health intact while V42 is exercised independently.


## SSL / HTTPS gate passed — 2026-09-20

- Hostinger Lifetime SSL for `v42.tag-marketplace.com` became Active.
- Normal HTTPS request now succeeds:
  - `curl -I -L --max-time 20 https://v42.tag-marketplace.com`
  - result: `HTTP/2 200`
- HTTPS response confirms secure Laravel cookies and Hostinger/LiteSpeed serving the V42 staging application.
- The V42 QAS/Staging URL is now live and ready for browser smoke testing:
  - `https://v42.tag-marketplace.com`
- Current next step: open QAS in a browser and start functional smoke tests before any production promotion.


## Browser smoke gate passed — 2026-09-20

- Opened `https://v42.tag-marketplace.com` successfully in a desktop browser.
- The V42 storefront rendered normally over HTTPS.
- Header, search, language controls, login/cart actions, and home hero content were visibly rendered.
- QAS/Staging is now confirmed usable interactively in a browser.
- Next functional smoke step: authentication/admin access, then cart/checkout/order flows.


## Owner / Super Admin product rule — 2026-09-20

- Product/security decision: each installation has exactly one store owner account with full Super Admin authority. For the developer's own installation, that owner account is Khaled's account.
- Customer self-registration must always create a normal customer, never an admin.
- The store owner may later create/promote staff admins, but those staff accounts must receive explicit limited roles/permissions and must never implicitly become Super Admin.
- Current code still contains a legacy fallback where a `role_as=1` user with no attached role is treated as Super Admin. This does not match the owner-only rule and is now a P0 authorization hardening item before production promotion.
- Target model: Super Admin must be explicit (the system `super_admin` role / owner bootstrap), while all other admins require an assigned staff role. Removing a staff role must not elevate privileges.
- QAS currently has the owner account bootstrapped manually for smoke testing; this is acceptable for staging only. The permanent bootstrap flow must be formalized before final production release / customer delivery.


## One-command QAS deployment workflow — 2026-09-20

- Added `deploy-qas.sh` for routine staging/QAS promotion.
- Intended daily workflow:
  1. make and review a small change,
  2. commit/push it to `v42-clean-baseline`,
  3. run `./deploy-qas.sh` on the rehearsal server,
  4. script updates the rehearsal checkout, installs PHP dependencies, verifies the rehearsal DB identity, runs only pending migrations, refreshes caches/public assets, restores the QAS front controller linkage, and performs an HTTPS health check,
  5. script prints `QAS READY` and the deployed commit,
  6. user validates the change on `https://v42.tag-marketplace.com`.
- The QAS deploy script explicitly refuses to proceed unless `APP_ENV=staging`, `APP_DEBUG=false`, and the configured DB name matches the dedicated V42 rehearsal database.
- It never targets the production application directory or production webroot.
- Production promotion remains a separate later workflow and must deploy the exact QAS-approved commit with production backup/DB snapshot/rollback gates.


## QAS one-command deployment validated — 2026-09-21

- End-to-end QAS deployment workflow was successfully proven with a visible homepage text change.
- Test change: `Today offers` -> `V42 Special Offers` in `resources/views/frontend/index.blade.php`.
- Tested commit: `9a51f50`.
- CI completed successfully before deployment.
- Server deployment completed successfully with `bash deploy-qas.sh`.
- QAS health check returned HTTP 200.
- Browser verification confirmed the new text was visible on `https://v42.tag-marketplace.com`.
- Therefore the routine flow is now validated: code change -> GitHub -> CI green -> one-command QAS deploy -> browser verification.
