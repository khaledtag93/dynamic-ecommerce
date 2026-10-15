# W-APP-03d - Hierarchy Readiness Diagnostics

Date: 2026-10-15
Branch: `sec03-framework-upgrade`
Source commit: `0b3181cef02e93b5bb47b4291e677296660396f9`
CI run: https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38659785216

## Scope

Scoped-mode approval-readiness discovery no longer treats a dangling manager profile reference as proof of `unassigned`. It reports `configuration` (uncertain hierarchy) without changing the central reviewer-authorization gate or any approval mutation. Existing cyclic/overdeep chain detection remains `configuration`. No global queue, reviewer identity disclosure, reassignment, delegation, escalation or policy activation.

## Regression

Added tests in `WorkforceLeaveApprovalReadinessTest.php` for a cyclic reporting chain with self-review forbidden and a permissionless ancestor who cannot approve a request. The existing eight readiness tests retain legacy/global/direct-manager, cross-team scoped pagination, bad config, missing/inactive manager and status-transition coverage. Local PHP lint of changed files and `git diff --check` passed. Full isolated CI pending final result at documentation authoring; do not mark Source+CI closed unless run succeeds.

## Environment and limitations

No local unverified database used, no SSH retry, QAS/Production writes or deployment. W-ORG-01b authenticated QAS acceptance remains OPEN/DEFERRED. Testing is isolated CI; no live operational acceptance claim.

## Next candidate after green CI

W-APP-03e: read-only review of hierarchy uncertainty when an otherwise eligible ancestor exists after a cyclic/broken link, with targeted permission regression. Do not broaden product workflow without approved business semantics.
