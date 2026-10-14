# Flowra W-LEAVE-02d — Historical Policy Snapshot Integrity

Date: 2026-10-15
Branch: `sec03-framework-upgrade`
Starting checkpoint: `12363cdb`
Prior CI: [#38646030979](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38646030979) GREEN (927 tests / 18,402 assertions).

## Evidence-based decision

The pre-existing `employee_leave_requests` schema already stores inclusive `starts_on`, `ends_on` and charged `requested_days`. Submission resolves the live leave-type policy once, then saves those values. Approval uses **stored requested days**, not live re-resolution, and checks current available balance and overlapping shifts. Editing the live type therefore should not re-date, re-charge or alter approval records.

Previously **no immutable evidence of the leave-type policy values used at submission** was stored, so changing the live type could make it impossible to explain why historical requests were charged a given duration.

## Source changes in this bounded slice

- Add nullable JSON `employee_leave_requests.policy_snapshot` via a reversible migration. **No backfill**: pre-existing requests remain NULL because their original policies cannot be reconstructed safely from today's configuration.
- At every **new** leave submission, after the existing server-side policy/eligibility/overlap checks, save an immutable, versioned snapshot with the leave-type code, fixed/flexible/legacy duration mode, calendar/workdays mode, configured duration limits, ISO weekdays and explicit holiday dates. Snapshots have no employee identifier, passwords, salary or free-text reason; user/employee privacy is unchanged.
- The model rejects changes to a previously recorded snapshot during Eloquent updates, while permitting the existing pending→approved/rejected/cancelled lifecycle. The snapshot is **not** used to re-compute historical leave or to reset balances.
- Existing request dates, charged days and statuses remain authoritative for approval and used/pending sums. Existing `LeaveBalanceService` computation and current-entitlement semantics are unchanged; this slice does **not** introduce retroactive financial reconciliation or a new entitlement-policy model.
- Show a minimal "Policy at submission" summary on the employee's own My Leave and the existing scoped manager/reviewer leave list. For older rows with NULL policy snapshot, show "Historical policy details unavailable" instead of attributing the current rules. Show a short bilingual admin form note explaining the immutable history.
- **Effective dates:** Reviewed explicitly, but **no date-based policy activation, retroactive migration, revision selection, carry-forward or bulk rewriting** is introduced. Selecting rules by a past/future effective date and freezing financial entitlement would require an owner-approved policy, separate versioned-schema design and dedicated tests. This is a deliberately held product decision.

## Regression gates

New `tests/Feature/WorkforceLeaveHistoricalPolicySnapshotTest.php` includes 6 isolated MySQL feature tests:
1. Fixed/workday/holiday request before and after a privileged live-type edit; approval and balance use originally charged days.
2. Approved legacy request keeps immutable original evidence after a policy change.
3. Old request with NULL snapshot is not inferred/backfilled and remains approvable.
4. Attempted policy snapshot mutation fails closed without altering the stored evidence.
5. Own-employee privacy, reviewer list permission gate, and absence from unrelated employee's view.
6. Preview remains read-only and ignores attempted forged policy snapshot on real submission.

No QAS/Production access, remote SSH, alternate transport, local unknown DB queries or credential transfers used. Local PHP lint, translation JSON integrity and `git diff --check`: PASS. The source commit `438c3dff` passed **full isolated-MySQL [CI #38651114216](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38651114216)**: **933 PHPUnit tests / 18,460 assertions**, including **6/6** new historical policy tests, **9/9** offline QA contract tests, Blade compilation and frontend build SUCCESS.

**Status:** W-LEAVE-02d **SOURCE + CI CLOSED** at `438c3dff`, run `#38651114216` GREEN. Formal live QAS acceptance remains PENDING. W-ORG-01b authenticated QAS acceptance still **OPEN / DEFERRED**.
