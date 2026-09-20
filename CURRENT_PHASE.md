# CURRENT PHASE
## V42 Clean Baseline & Production Hardening

### Official baseline
- **V42** — Cost Calculator refactor + Arabic/English translation updates

### Phase label
- Production hardening before the next feature wave

### Goal
Turn the latest working local build into the single canonical Git baseline, remove secrets from source control, make deploy/rollback repeatable, and close critical release blockers before continuing feature expansion.

### Current workstream
1. Promote V42 to a clean Git baseline.
2. Stop tracking `.env` and keep secrets in local/server/GitHub Secrets only.
3. Replace folder-per-version development with one Git repository and traceable commits/tags.
4. Validate production deploy and rollback through controlled automation.
5. Re-run the critical security/business audit and close P0 blockers.
6. Expand automated and manual regression coverage.

### Known review items before commercial release
- payment callback/HMAC validation and payment idempotency
- authorization/role-escalation paths
- stock, coupon, refund, and order concurrency/idempotency
- secrets and environment configuration
- cost/profit calculations and reporting semantics
- test/CI coverage and release verification

These items are review targets until individually verified and closed.

### Next feature phase
- Resume planned product development only after the production-hardening gate is green.
