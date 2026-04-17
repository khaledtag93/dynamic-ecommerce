# Known Issues / Cleanup Targets

## High priority
- Automated tests are still minimal compared to project size.
- Analytics UI needs a stronger final dashboard pass.
- Growth/admin UX needs clearer organization and polish.
- Payment production hardening is still required.
- Arabic/English coverage needs a systematic audit.

## Important technical cautions
- Do not remove unknown legacy assets unless usage is confirmed.
- Avoid risky refactors during cleanup.
- Existing `.env` stays during active development ZIP deliveries.
- Public/release packaging should happen in a separate release-prep pass.

## Payment-specific follow-up
- duplicate prevention
- callback reliability
- failed/success result clarity
- retry flow quality
- admin log readability

## Quality follow-up
- add smoke tests for cart, checkout, orders, coupons, permissions, and payment callbacks
- improve README and docs as the single source of truth
