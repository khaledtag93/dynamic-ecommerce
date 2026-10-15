# W-APP-03e - Direct Manager Hierarchy Uncertainty

Source-only bounded slice (2026-10-15) on sec03-framework-upgrade.

`LeaveApprovalReadinessService` now distinguishes an absent configured direct manager (`unassigned`) from a stale non-null manager FK (`configuration`) in optional direct-manager mode. This is a display-only read-side diagnostic; `canReview` still controls mutations, no reviewer identity disclosure or fallback.

New isolated-MySQL feature regression simulates a legacy dangling manager FK with foreign-key validation restored in finally, checks configuration classification and unauthorized approval denial, and confirms a cashier cannot read a different team's private reason. Existing scoped, cycle, legacy and page privacy regressions remain.

No SSH, QAS/Production writes, deployments or policy changes. W-ORG-01b QAS authenticated acceptance remains OPEN/DEFERRED. CI proof and SHA must be appended only after confirmed success.
