# W-DOC-01 — Workforce Sensitive Document Architecture & Access Audit

Date: 2026-10-15. Branch: `sec03-framework-upgrade`.
Scope: read-only source audit only. No new database schema, upload or download endpoint, data writes, live acceptance or policy change.

## Observations grounded in checkout

- Existing Workforce leave domain uses `EmployeeLeaveRequest`, `EmployeeProfile`, `LeaveRequestService`, and approval-routing/access services; no dedicated `*Document*` model or controller was found by filename within application Models and Controllers.
- `config/filesystems.php` exposes a default `local` disk rooted at `storage/app`, while the separately named `public` disk serves public uploads using a public URL and visibility. This configuration does NOT by itself establish secure Workforce evidence storage or authorized downloads.
- `docs/WORKFORCE_ENTERPRISE_E2E_AUDIT_2026-10-14.md` identifies W-07: missing employee/leave multi-document relation and private review flow. This remains an open design gap; no live service coverage is implied by the source-only audit.
- Existing Workforce scope and leave reviewer eligibility services are useful foundations but are not an attachment-specific authorization or retention policy.

## Required design gates before implementation

1. Define classification: ordinary employment files versus identity and medical evidence; establish purpose-specific permissions, including when direct supervisors may see minimal metadata but not content.
2. Require private/non-web-accessible storage, opaque identifiers, no guessed public URLs, per-request authenticated download authorization, explicit cross-team and self-service access matrix, logging without sensitive content, and defense against enumeration/IDOR.
3. Specify validated file size/count, content type and magic-byte validation, scanning/quarantine, encryption needs, immutable/audited revisions, retention and deletion semantics per jurisdiction.
4. Define treatment of inactive employees, manager changes, prior approvers, suspended accounts, and QAS synthetic fixtures; do not infer these business/legal decisions.
5. Before code: threat model and agreed business classification; then narrowly scoped schema/API/UI design, tests for cross-team denial and privacy leaks, isolated CI, and separately authorized environment acceptance.

## Current disposition

W-DOC-01 READ-ONLY AUDIT COMPLETE; IMPLEMENTATION NOT AUTHORIZED/NOT STARTED. No QAS/Production access, SSH, uploads or DB mutations. W-ORG-01b authenticated QAS acceptance remains OPEN/DEFERRED.
