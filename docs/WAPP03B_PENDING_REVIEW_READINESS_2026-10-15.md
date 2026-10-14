# Flowra W-APP-03b — Pending Leave Review Readiness

Date: 2026-10-15
Branch: `sec03-framework-upgrade`
Baseline checkpoint: `afbdcbb4`
Baseline [CI #38653785977](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38653785977): GREEN, 941 tests / 18,529 assertions, 9/9 offline QA contracts.

## Goal and security boundary

A reviewer can see, on the **existing permission-scoped, paginated Leave review list**, whether each Pending request:
- `mine` — can be approved/rejected by this signed-in reviewer using the existing `LeaveApprovalRoutingService`.
- `other` — another reviewer is eligible under the **current configured routing mode**; does not disclose who.
- `unassigned` — no eligible reviewer can currently be proven: in opt-in direct-manager mode, the configured active direct manager is missing or cannot act; in legacy scoped mode, no other authorized organization-wide reviewer or eligible reporting-chain ancestor exists.
- `configuration` — invalid routing mode or inconsistent/cyclic tree prevents a trustworthy determination. **Never silently misclassify unknown as unassigned.**

Readiness is a **purely read-only, point-in-time estimate**. It creates no assignments, approvals, audit records, fallback reviewers, escalation, notifications, role changes or new database tables. Approval/rejection authorization remains enforced at HTTP and transactional service layers. The legacy `scoped` mode is still the default. Opt-in `direct_manager` is **not activated**. No history, pending/approved statuses or leave balance formulas were changed.

## Implementation

- Added `LeaveApprovalReadinessService::forPage`: consumes only the page of requests already filtered via `WorkforceAccessService::restrictForeignKey`; compares current actor eligible employee IDs from the same routing service and resolves other reviewer existence without revealing identities. For scoped mode, considers a real organization-wide `workforce.scope.all` + `workforce.manage` user (including super admin) and valid manager ancestors. For direct-manager mode, uses only the active direct manager and the existing authoritative `canReview` guard. Missing manager never auto-falls back to org admin; invalid mode and cyclic chain are flagged for attention.
- Controller passes a page-local `reviewReadiness` into **both full Blade and X-Live-List partial**. Pending rows display the appropriate eligibility state; only `mine` can render the existing mutation forms. Approved/rejected/cancelled rows are excluded from pending readiness counters.
- Compact inline counts at the results table are explicitly labeled **“on this page”**, not global company-wide totals; counts respect the current list filters and pagination. No names or email of another eligible reviewer are emitted.
- Added six EN/AR strings for accessibility, counts and explanation.

## Tests and closure

`tests/Feature/WorkforceLeaveApprovalReadinessTest.php` provides eight isolated-MySQL feature tests for:
1. Legacy scoped org admin readiness and normal pending status.
2. Self-review denial with another legitimate ancestor; own scope only.
3. Proven unassigned legacy scoped self leave when there is no alternate reviewer.
4. Direct-manager opted-in chain and deliberate no-fallback even for org admin.
5. Inactive, permissionless or missing direct manager.
6. Invalid routing configuration is **attention**, never false unassigned.
7. Live list pagination count precisely 20 current-page entries; cross-team private leave reason never included.
8. Completed approvals/rejections excluded, with real authorized state transitions unchanged.

PHP lint, JSON translation validation and `git diff --check` passed locally. Source commit `d4d8a8e6` passed **full isolated-MySQL [CI #38655138345](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38655138345) GREEN**: **949 PHPUnit tests / 18,613 assertions**, **8/8** new readiness tests, **9/9** offline QA role contracts, migrations and frontend build SUCCESS. **W-APP-03b SOURCE + CI CLOSED.** Live QAS/Production were not accessed. **W-ORG-01b authenticated QAS acceptance stays OPEN/DEFERRED**; no SSH or bypass.

Potential future product decisions deliberately deferred: organization-wide unassigned work queue, actual reviewer assignment, delegation, out-of-office handling, SLA/escalation, multi-step approvals, reassignment and notifications. This page-local readiness does **not** claim to be an exhaustive organization-wide monitoring dashboard.
