# Flowra W-APP-03 — Approval Routing Foundation

Date: 2026-10-15
Branch: `sec03-framework-upgrade`
Starting checkpoint: `afe65e66` / [CI #38651633408](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38651633408) GREEN (933 tests / 18,460 assertions).

## Current behavior and verified architecture

- `EmployeeProfile.manager_employee_profile_id` provides a validated, cycle-resistant reporting tree. `ReportingHierarchyService` manages assignments; `WorkforceAccessService` already applies explicit org-wide `workforce.scope.all`, descendant-scope access, team-level `workforce.manage` and the self-review prohibition.
- `LeaveController::approve/reject` previously validated reviewers at the HTTP boundary, but `LeaveRequestService` itself did not check reviewer permissions. The Leave review table displayed Approve/Reject for *every* pending row whenever the viewer had `workforce.manage`, even when a cross-team/self submission would be denied upon PATCH. Current pending/approved/rejected balance and shift guards remain authoritative.

## This single bounded slice

- Add `LeaveApprovalRoutingService`, the shared routing decision used in both existing review HTTP endpoints, the **transactional domain service** and per-row UI action visibility.
- The default `config('workforce.leave_approval.routing_mode') = scoped` preserves exactly the current reviewer rules: an authorized reviewer with `workforce.manage` may review permitted descendants or anyone if `workforce.scope.all` is explicitly granted, **but never their own leave**. No database migration or policy-default change.
- Offer an **explicitly opt-in** `direct_manager` routing setting under `WORKFORCE_LEAVE_APPROVAL_ROUTING_MODE` (for owner approval later). In this mode, only an *active direct reporting manager* with `workforce.manage` may review, including no org-admin exception. An absent manager, inactive manager, insufficient role or invalid configured mode fails closed, leaving requests pending. The mode is intentionally NOT activated in any QAS/Production environment. No implicit delegate, OOO replacement, escalation, auto-approval or timeout policy.
- Use a single reviewer-eligibility calculation for the **page's already scoped, paginated employee set**; show action buttons only for authorized pending rows, including live partial page refreshes. Keep read/list visibility separate from write/review rights. A pending row without review permission shows a neutral waiting state, never an active review button. The server always re-checks authorization before mutations and inside the transaction to resist direct service calls.
- No change to leave policy effective dates, historical leave policy snapshots, approved/requested days, entitlement balances, request assignment history, payroll, compensation or attendance.
- New admin leave review copy explains that reviewer actions are scoped and no delegation/escalation runs, with EN/AR translations.

## Regression matrix

New `tests/Feature/WorkforceLeaveApprovalRoutingTest.php` covers:
1. Default scoped org admin without employee profile: approve/reject continue to work; approved/rejected terminal transitions preserved.
2. Hierarchical team manager: allowed descendants, denied other teams and own leave; UI shows only applicable actions.
3. Opt-in direct-manager mode: immediate manager yes, indirect ancestor no, even though broader read scope remains.
4. No reporting manager: no assumed org-admin fallback, both writes blocked and status unchanged.
5. Inactive direct manager and role without `workforce.manage`: deny.
6. Invalid/unapproved routing mode fails closed.
7. Direct service invocation cannot bypass controller reviewer authorization.
8. Cross-team employee privacy: no unrelated reason shown and forged-ID mutation is denied.

PHP lint, translation JSON parsing and Git diff checked locally before commit. Isolated GitHub MySQL + full hardening CI must be **GREEN** before asserting W-APP-03 SOURCE/CI CLOSED. Real QAS authenticated role acceptance, W-ORG-01b, remains OPEN/DEFERRED.

## Product decisions explicitly deferred

Routing mode must only be explicitly activated by an approved tenant/organization configuration change after business sign-off. Per-team or per-leave-type routing, historical assignment snapshots, backup delegates during OOO, multi-level approval, reassignment on manager change, escalation SLA, notifications and organizational fallback rules are **not designed or activated** by this foundation. A live switch from `scoped` to `direct_manager` would change which people can review **pending** requests; it must never be enabled without separate operational acceptance and a defined policy for unassigned employees.

**No SSH retries, QAS/Production writes or deployments.** No unknown local DB test execution.
