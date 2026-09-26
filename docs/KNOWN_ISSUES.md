# Known Issues / Production Hardening Targets

## Authoritative audit checkpoint — 2026-09-26

- Current audit and execution priorities: [Production foundation audit (Arabic)](PRODUCTION_FOUNDATION_AUDIT_2026-09-26_AR.md).
- Reviewed source: `e0420a31986ef11cb23c22b1b75dc56078a2389a` on `v42-clean-baseline`.
- Exact-head [Hardening CI 36208521493](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36208521493) passed: **438 tests / 13,843 assertions**, clean MySQL migration, route/Blade/config checks and frontend build.
- Latest operator-recorded QAS checkpoint targets `5e2a393a`; server HEAD was not independently read during this audit. Growth changes through `9526d275` are not recorded as deployed. The later `e0420a3` changes are documentation-only.
- Production was not changed or reverified. Authenticated Admin/POS/Workforce and mobile/physical-device acceptance remain open; public QAS desktop sampling is documented in the audit.
- New release priorities: vulnerable/outdated dependencies and upload handling; locale redirect restriction; historical secret-rotation evidence; payment E2E; database restore and scheduler/queue verification. Then storefront media/content/localization/accessibility, POS live actions, bounded statements and consolidated QAS acceptance.
- A product detail image failed to load in the current QAS sample; this is a separate observation from the previously resolved category-media-root issue. Do not assume the old root cause has recurred.
- No application code or deployment changed in this audit. Findings are **open**, not fixed. Continue completion before expansion, preserving the future Android/iPhone architecture constraint.

### Historical checkpoints below

Older source/QAS/CI/“next” statements below describe their own dates. They do not override the checkpoint above. Use the new audit to distinguish implemented features from pending acceptance and genuinely unimplemented scope.


## P0 — Must close before Production
- **Credential rotation:** secrets/credentials that previously existed in tracked Git history must be considered exposed. Removing `.env` from the current tree does not remove historical exposure; rotate affected credentials before Production.
- Verify Paymob callback/HMAC validation and payment idempotency.
- Verify authorization and privilege-escalation paths.

## High priority
- Automated tests are still small compared with project size.
- Verify stock, coupon, refund, and order concurrency/idempotency.
- Verify Cost Calculator formulas and profit/reporting semantics.
- Payment production hardening still requires end-to-end validation.
- Arabic/English coverage needs a systematic final audit.

## Baseline cleanup completed
- `.env` removed from the V42 tracked tree.
- `vendor/` and `node_modules/` removed from tracking.
- local uploads, logs and sessions removed from tracking.
- local SQLite DB and backup artifacts removed from tracking.
- generated Laravel cache files removed from tracking.

## Payment-specific follow-up
- duplicate prevention
- callback/HMAC reliability
- failed/success result clarity
- retry flow quality
- admin log readability
- duplicate provider/order protection on retries and refreshes

## Quality follow-up
- smoke tests for cart, checkout, orders, coupons, permissions, and payment callbacks
- deployment + rollback rehearsal before Production
- final release checklist and environment verification


## Closed in V42 hardening pass (2026-09-20)
- Paymob callback HMAC was calculated but not enforced — **fixed**.
- Paymob callback payment integrity checks (amount/currency/integration) — **added**.
- Paymob iframe checkout helper had infinite recursion — **fixed**.
- Late/replayed gateway callbacks could downgrade a paid payment — **guarded**.
- Users with `customers.manage` could promote accounts through `role_as` — role changes now require **super admin**.
- Growth controller constructor performed DB work during route discovery — **fixed**.
- Clean Git clone could miss required Laravel runtime directories — **fixed with tracked placeholders**.
- Paymob callback/API logs retained excessive sensitive payload data — **redacted/minimized**.

## Still open before Production
- Rotate all credentials that ever appeared in Git history.
- Run full PHPUnit suite in an environment with required PHP extensions.
- Run end-to-end Paymob test transaction against test credentials and verify both processed and response callbacks.
- Verify refund/cancel/stock/coupon concurrency and idempotency.
- Validate deploy + rollback on the target server/staging environment.


## Closed in concurrency / release-hardening pass (2026-09-20)
- stale checkout snapshot / duplicate double-submit risk — **protected with cart row locks inside the order transaction**
- inventory overselling race — **protected with conditional atomic decrement**
- coupon last-use race — **protected with conditional atomic increment**
- duplicate cancellation restoring stock twice — **made idempotent under an order row lock**
- concurrent refund overrun — **serialized and recalculated under an order row lock**
- payment sync overriding partial/full refund ledger state — **fixed**
- manual Payment=refunded bypassing OrderRefund ledger — **blocked**
- permissions manager clearing a role into implicit legacy super-admin — **blocked; staff-role administration requires super admin**
- Deploy Center default-on configuration — **changed to opt-in**
- deploy health check with disabled TLS verification / optional curl — **hardened**
- MySQL oversized growth-learning composite unique index — **fixed and compatibility migration added**

## CI checkpoint
- Hardening CI added using PHP 8.2 and MySQL 8.
- Clean MySQL migration now succeeds.
- Laravel boot/routes/config/view compilation succeeds.
- Current PHPUnit suite and frontend production build succeed.
- Automated business regression coverage is still insufficient and remains an open quality target.

## Production blocker still open
- Database rollback is not automated. Current rollback restores code/public files but not schema/data. Production deploy must not be approved until database backup/rollback strategy is validated.

## Additional issues closed (2026-09-20)
- deleting an assigned custom staff role could cascade-detach admins and turn them into implicit legacy Super Admins — **blocked with transactional role locking and assigned-user validation**
- permissions UI still exposed an empty legacy fallback role option even though the controller rejected it — **removed**
- four runtime/user-upload files remained tracked under `storage/app/livewire-tmp` and `storage/app/public` — **removed and paths explicitly ignored**
- added targeted regression coverage for assigned custom-role deletion

## Financial/destructive-action issues closed (2026-09-20)
- simultaneous gateway callbacks could both read a pending payment and race paid vs failed writes — **fixed with transactional Payment row locking**
- order payment-state synchronization could interleave with other financial mutations — **serialized on the Order row**
- permanent deletion of a cancelled order could cascade-delete payments/refunds and sever inventory history — **hard deletion disabled in backend and removed from the admin UI**
- automated regression tests added for terminal paid state and retained cancelled orders
