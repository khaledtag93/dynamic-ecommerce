# Known Issues / Production Hardening Targets

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
