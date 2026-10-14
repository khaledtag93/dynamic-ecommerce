# Flowra Workforce — Enterprise End-to-End Gap Audit & Execution Roadmap

Date: 2026-10-14
Baseline branch: `sec03-framework-upgrade`
Audited application/QAS baseline: `a83bc8f0` (working/docs HEAD `a228faec` at audit start).
Status: **Architecture / functional audit, not production closure**. No data mutations or QAS deployment were done for this audit.

## Executive finding

The existing Workforce V1 is a functioning CRUD and transactional foundation. It is **not** yet an enterprise-level, employee-scoped HR approval system. The most material blockers are reporting hierarchy, assigned approver authority, distinct leave policies and calendars, secure personnel and medical evidence, notification delivery, and end-to-end policy/Payroll integration.

## Code-grounded observations (verified from current checkout)

| ID | Finding | Grounding | Severity |
|---|---|---|---|
| W-01 | Created shifts were Draft, so absent from My Schedule by design | `WorkShiftController::mySchedule()` filters `status=published`; `WorkShiftService` supports draft/publish and scheduling tests assert hiding | UX, not data loss |
| W-02 | Employee does not identify a direct manager/supervisor or branch team hierarchy | `EmployeeProfile` + `create_employee_profiles_table` contain user, code, department string, employment status; no manager relation | Critical |
| W-03 | Leave approval is controlled by global `workforce.manage`, not a per-subordinate scope | `routes/web.php`, `LeaveController::index/approve/reject`, `LeaveRequestService::approve/reject`; no actual assigned-approver identity guard in service | Critical/security |
| W-04 | Leave type `default_annual_entitlement_days` is an annual allowance, **not a per-request max/fixed duration** | `EmployeeLeaveType`, `LeaveTypeController`, `LeaveRequestService::request` | High |
| W-05 | A 2-day request is accepted Pending even if the type annual entitlement is one day; approval checks available balance later | `LeaveRequestService::request/approve` and existing tests; UI lacks pre-submit policy feedback | High/UX |
| W-06 | Leave is inclusive calendar days, no company/branch holiday calendar, workday pattern or cross-year request | `LeaveRequestService::normalizeRange` and `diffInDays()+1`; current service rejects cross-year | High/policy |
| W-07 | Leave request and employee profile have no multi-file document relation or secure upload/review flow | `EmployeeLeaveRequest`, `EmployeeProfile`, migrations, controllers and Workforce UI | Critical/privacy |
| W-08 | Generic notification infrastructure exists, but Workforce LeaveRequestService has no assignment notification on submit/review | `LeaveRequestService` actions only create + audit; generic commerce Notification Center exists | High/workflow |
| W-09 | Shift creation/update checks active employee, approved leave and overlapping shifts. Leave approval checks conflicting shifts and sufficient balance | `WorkShiftService`, `LeaveRequestService`, `WorkforceLeaveManagementTest` | Present foundation |
| W-10 | Leave approval blocks even Draft shifts. Needs deliberate policy: cancellation/reschedule, visibility and transactional conflict resolution | `LeaveRequestService::approve` uses `status != cancelled` | High/workflow |
| W-11 | Date boundaries for shift/leave checks use UTC timestamps converted to UTC calendar dates in some places; workforce display now uses local Africa/Cairo | `WorkShiftService::guardNoApprovedLeave` and `LeaveRequestService::approve` vs `WorkforceClock` | High/correctness, DST/cross-midnight |
| W-12 | Approver delegation, manager-on-leave escalation, employee/manager self-approval exclusion, department/branch scope, approvals audit history absent | No manager relation / no approver records | Critical |
| W-13 | Dynamic workforce time display, UTC storage, audited attendance Void and Payroll snapshot guard were delivered in previous verified slice | `QAS_WORKFORCE_TIMEZONE_ATTENDANCE_UX_CHECKPOINT_2026-10-14.md` | Delivered; manual UI acceptance still pending |

## Proposed domain architecture

**Reporting line, scoped access:**
- `EmployeeProfile.manager_employee_profile_id` nullable FK to another employee, plus explicit branch/location membership (branch-specific permissions later). Enforce not self and no cycles, prevent inactive/missing manager on configured workflows, index hierarchy. HR/admin can correct with full audit.
- Provide manager/subordinates/team tree UI. Use an authorization service/policy that resolves relationship and delegated approver. **Role + relationship** are both required. Global `workforce.manage` is not proof that a specific employee is in the manager's scope.
- Separate employee self-service, direct manager, upper management and HR/security roles. Only authorized personnel can browse employee records; roles and team access must be checked server-side for every endpoint/download.

**Approval routing:**
- New `leave_approval_tasks` or `leave_approval_steps` row(s): request_id, step, assigned_approver_employee_id, delegated_from, status, decided_by, timestamps, reason, policy version. Audit transitions, prevent double decisions, self approval, bypass and concurrent approve/reject.
- Resolve a route at submission (direct manager, then optionally skip absent/unavailable manager using dated approved leave or configured delegate, then manager's manager, with HR fallback). Do not rely only on employee.status=on_leave.
- Support out-of-office delegate, reroute audit, reminders/escalation SLA, in-app notification and email optional. Never silently drop the request if the manager hierarchy is missing.
- Reviewer's scope must be revalidated server side (including attachments) after reassignment and before each decision.

**Leave policy engine (configurable, by company/branch/location where relevant):**
- Explicit leave type `duration_mode`: `fixed`, `range`, `flexible`; `fixed_duration_days` or min/max-per-request; `counting_mode` calendar/workdays; `yearly_allowance` vs `unlimited`/non-balance type; paid flag; eligibility; notice and retroactive rules; support `attachment_requirement` optional/required/conditional; cancellation policy; holiday treatment.
- Fixed duration: employee selects start; server calculates and persists end; UI shows derived end read-only. Never trust a disabled HTML field as an authoritative value. Calculate using employee workweek/holiday calendar and handle DST/year crossover.
- Flexible sick leave: employee chooses dates subject to applicable maximum/policy and required evidence when applicable. Document access restricted by purpose and role.
- Public holidays: generally define via official **company holiday calendar** (not an arbitrary leave request) with applicability per region/site and effective dates. Optional employee leave categories remain independent.
- Validate at **request and approval**, including other Pending/Approved blocks and projected entitlement, overlapping leave, permitted duration and evidence. Separate allowance in year from max length of a request.
- Base charging on scheduled working days/company calendar, partial days/shifts where required. Do not silently change existing historical leave balances/payroll snapshots.

**Conflicts and downstream dependencies:**
- Schedule Publish is the employee-visible boundary; checks active employment, holiday applicability, approved/pending leave warnings, eligible branch, rest/time limits, overlap/cross-midnight/timezone, and notifications.
- Approved leave must never coexist with a published conflicting shift. For existing published shift, block with actionable 'reschedule/cancel' workflow or atomically resolve under authorized policy; never silently delete a shift. Draft conflict rules should be deliberately chosen and tested.
- Attendance clock, break, corrections, overtime and Payroll must respect effective shifts, approved leave, holiday/working-day policy and end-to-end locking; preserve immutable financial snapshots and audit changes.

**Sensitive documents:**
- Dedicated `employee_documents` and `leave_request_documents` (or unified typed relation) with many attachments per entity, classification, metadata, uploader, version/expiry, hashes, deletion/retention audit.
- Store on **private/non-public storage**, no public URL or predictable path; guarded download/preview endpoints and signed short-lived delivery when appropriate; MIME/magic-byte validation, extension/size/count limits, malware scan/quarantine, safe filenames, download logging.
- Employee IDs, certificates and medical notes need differentiated role- and purpose-specific visibility (direct manager may see only the evidence needed to decide a sick leave; HR can handle identity documents; 'all managers above' must not automatically expose sensitive health/ID documents).
- Encrypt at rest where feasible, avoid putting medical/ID content in notification email or generic logs, apply retention/deletion rules by jurisdiction.

## Slice-based delivery sequence (each: inspect → schema/service → tests → CI Green → QAS smoke → checkpoint)

1. **W-ORG-01 — Reporting hierarchy / authorization foundation**: supervisor tree, self/descendant scoping, cycle prevention, effective manager, HR override, no retroactive mutation. Tests: reporting chain, cycle, unauthorized employee access, manager changes, branch boundaries.
2. **W-LEAVE-02 — Leave policy engine & validation**: fixed/flexible per-request length, annual entitlement and projected balance, holiday/workday calendar, server-computed end, year-crossing, partial days decisions. Tests: 1-day fixed rejects 2-day payload, flexible accepted within policy, weekends/holidays, DST, insufficient requested balance.
3. **W-APP-03 — Approval assignment/OOO delegation & notifications**: direct manager, upper manager/delegate when unavailable, audited state machine, assignee-only decisions, notification inbox/read state + optional email, idempotency. Tests: manager absent, missing chain, self approval, races, spoofed approval, cross-team privacy.
4. **W-DOC-04 — Personnel & leave attachments**: multi-files, classification, private storage, scanned preview/download with policy checks, retention and audit. Tests: invalid MIME, oversized, malicious paths, non-subordinate denial, unauthorized document ID enumeration, file replacement, logging.
5. **W-SCHED-05 — Scheduling/leave/attendance consistency**: explicit Draft vs Publish UX, constrained Publish, employee notification, team calendar, conflict decision/reschedule flow, effective local-time checks, attendance integration. Tests: conflict across midnight, approved and pending leave interactions, shift boundaries, leave revocation.
6. **W-PAY-06 — Financial integration & acceptance**: leave pay/no-pay mapping, holiday rules, overtime, effective worked hours, snapshot freezing/reconciliation, shift/leave edits after payroll, closed periods and concurrency; EN/AR RTL and mobile E2E acceptance. Do not backfill/recalculate historic payroll without a documented signed-off policy.

## DoD / release gates (mandatory)

- No employee can approve own leave, a request outside authorized scope, or access another employee's sensitive document.
- Per-request leave policy is validated server side, not just HTML inputs; error is actionable in EN/AR.
- Start/end counting handles local calendars, weekends/holidays and cross-midnight shifts consistently with defined policy.
- Workflow stores assignment, decision, delegation and justification with audit; notifications are transactional/idempotent and retry safely.
- Approved leave and published shifts never overlap without explicitly captured override/resolution policy.
- Private file storage, download authorization tests, malicious file handling, access logging and retention procedures.
- Existing attendance/leave/payroll tests stay Green; new rule-by-rule tests plus unauthorized-role and concurrent-action coverage.
- Database migration reversibility and performance indexes reviewed; read-only QAS precheck, backup, deploy, health, and employee/manager/HR UI acceptance. Production gates remain separate.
- Manual review in Arabic and English, RTL, desktop and mobile, with real manager/employee personas on **isolated demo test data only**.

## Next committed action

Start **W-ORG-01** as the first implementation boundary; hierarchy and scoped authorization are prerequisites for approvals and private documents. Retain the existing QAS data unchanged while investigating. The current screenshots contain two Draft shifts; publishing one can verify the existing schedule behavior without database/architecture changes. An explicit company leave policy for holidays, weekend days, employee leave allowance and evidence handling is needed before activating leave engine behavior.
