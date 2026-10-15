# W-APP-03c - Scoped Reviewer Authorization Consistency

Date: 2026-10-15
Source commit: `e69ae8c1a0f8f4fbb4a6807d4585e1749a51f0a2`
Branch: `sec03-framework-upgrade`
CI: https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38657591947 - completed/success.

## Scope

`LeaveApprovalReadinessService::otherReviewerExists()` now verifies scoped ancestor candidates with the authoritative `LeaveApprovalRoutingService::canReview` gate rather than just `workforce.manage` permission. No change to approval mutation rules, routing mode, assignment, escalation, delegation or privacy boundaries.

## Verification

PHP syntax and `git diff --check` passed locally. The clean-MySQL isolated GitHub Actions application job completed successfully on exact source SHA; PHPUnit, offline Workforce QA contracts, browser checks, frontend build and migrations succeeded. Existing 8 readiness feature cases cover legacy global/scoped, self-review denial, missing/inactive/permissionless manager, invalid routing, cross-team privacy, paginated counts and approval transitions. Exact PHPUnit totals were not separately captured from this run.

## Closure / limits

W-APP-03c SOURCE + CI CLOSED. W-ORG-01b authenticated QAS Acceptance remains OPEN/DEFERRED. No SSH, QAS/Production writes, deployments or unverified local database tests.

## Next proposed slice

W-APP-03d: targeted read-only hierarchy diagnostics for cycles, broken ancestor references, stale reviewer permissions and configuration uncertainty, with isolated feature regressions. No assignment, automatic escalation, delegated review, policy activation or global queue without separate approval.
