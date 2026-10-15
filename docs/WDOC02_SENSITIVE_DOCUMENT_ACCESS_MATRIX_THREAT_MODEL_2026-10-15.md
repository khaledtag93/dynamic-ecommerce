# W-DOC-02 — Sensitive Workforce Document Access Matrix & Threat Model

Date: 2026-10-15 | Branch: `sec03-framework-upgrade` | Source baseline: `37231afb`
Status: DESIGN / SOURCE REVIEW ONLY — NO FEATURE ENABLEMENT

## Grounded boundaries

`WorkforceAccessService::canView` provides employee-scope access and `canManage` provides a role-plus-scope guard. Neither proves authorization to access a particular sensitive document. Leave approval routing and page-local readiness are separate from evidence access. `config/filesystems.php` separates default local storage from an explicitly public upload disk, but the presence of a local disk does not guarantee a confidential document lifecycle. W-DOC-01 found no purpose-built Workforce document model/controller by filename in the reviewed areas.

## Proposed least-privilege matrix (NOT approved policy)

Codes: SELF = own records only; SCOPE = active authorized reporting relationship for this employee; HR = dedicated named HR/document permission (not generic admin or `workforce.manage`); NONE = deny; PENDING = business/security decision. Metadata means minimal non-sensitive status/type/date only, never filename/notes if those leak medical or identity information. Any download must separately check exact document, subject, classification, current purpose and role. A page listing is never a download grant.

| Actor | General metadata | General download | Medical metadata | Medical download | Identity metadata | Identity download |
|---|---|---|---|---|---|---|
| Employee (authenticated owner) | SELF proposed | SELF proposed, with restrictions | SELF proposed | SELF proposed, with restrictions | SELF proposed | SELF proposed, with restrictions |
| Direct manager with current scope | SCOPE proposed for approval purpose | PENDING | Minimal evidence-present flag only, PENDING | NONE by default, exception requires approval | NONE by default | NONE |
| Indirect manager | PENDING, default NONE | NONE | NONE | NONE | NONE | NONE |
| Dedicated HR/document custodian | HR, purpose-limited | HR, purpose-limited | HR, purpose-limited | HR, purpose-limited | HR, purpose-limited | HR, purpose-limited |
| Generic Admin / super admin | NONE by default (no implicit document grant) | NONE | NONE | NONE | NONE | NONE |
| Unrelated team, inactive user, external actor | NONE | NONE | NONE | NONE | NONE | NONE |

No existing role or permission is asserted to implement this matrix. HR is a proposed responsibility, not a claim that Flowra currently contains an HR security role. Break-glass access is not designed or enabled.

## Identity and lifecycle rules to decide before implementation

- Scope MUST be evaluated at request time. Reporting-manager changes or permission revocation invalidate earlier review access and any pre-issued content links; distinguish previously lawful review audit from future read rights.
- Self-service access requires binding the authenticated account to the actual employee profile and specific document, never taking `employee_id` from a trusted-looking query parameter.
- Read/list/download/preview/thumbnail/search/export all need explicit independent authorization, pagination scoping and minimal metadata projection. No public URL, CDN, mail attachment, generic media helper or permanent signed URL can bypass authorization.
- Classification must be per document and optionally per version. Medical attachments, identity documents and payroll/disciplinary evidence must never inherit broad manager scope from generic Employee Profile access.
- Storage keys must be opaque, outside public web roots, with restricted backup access. Audit access decisions by actor, subject, document identifier, purpose, outcome and time; never store content, identity number, medical description, original filename or signed URL in logs.
- Retention, legal hold, versioning, employee departure, account suspension, organization/tenant boundaries, manager reassignment, malware response and cryptographic deletion require an approved policy before schema/API work.

## Threat model and preventive gates

| Threat | Attack surface | Proposed control / test |
|---|---|---|
| IDOR / predictable document IDs | download, preview, list endpoints | server resolves document + owner; enforce separate per-document policy; cross-team 403/404 equivalence without record existence leaks |
| Role or scope drift | changed supervisor / removed roles | re-evaluate relationship and document-purpose permission on every request; reject old manager |
| Public exposure / traversal | storage links, CDN, path parameters | private disk, opaque keys, no client storage path, canonicalized safe resolution and deny traversal |
| Sensitive metadata disclosure | page counts, search, response headers, filenames | classification-specific projections, no guessed names/medical tags in untrusted UI or headers |
| Signed-link replay | copied preview URL or browser cache | short-lived, subject-bound authorization on retrieval; no-store headers; revoke on scope change |
| Malicious upload / unsafe rendering | extensions, polyglots, scanners | require magic-byte/type/size/count validation, quarantine/AV checks, attachment disposition, CSP |
| Cross-tenant / cross-branch access | ID filters, queued exports, notification payload | tenant/branch ownership + role + purpose; deny and test every bypass path |
| Insider and support abuse | admin panel, backup, event logs | dedicated custodian authorization; audit and alert; no blanket super-admin evidence access |
| Deletion or immutable history loss | purge and retention jobs | documented retention, hold, secure purge and immutable audit evidence |

## Proposed isolated test matrix for a FUTURE authorized implementation

Owner own document positive; unrelated employee and supervisor negative; active direct manager minimal metadata only; indirect manager denies medical/identity; dedicated HR purpose-limited; generic admin denies by default; stale manager scope immediately denied; revoked role denied; deleted/suspended user denied; tenant crossover denied; ID enumeration denies without leaking existence; downloads never return `public` URL; caching no-store; uploaded file format mismatch quarantined; audit events redact content. These are DESIGN test cases only and were NOT run.

## Business, Legal and Security decisions BLOCKING implementation

1. Who is the named HR/document custodian and which real product roles or permissions represent that responsibility per tenant? Is self-download permitted for each class?
2. Which evidence, if any, may a direct manager download versus seeing only a boolean evidence-present status? Define legitimate purpose and consent basis.
3. What legal jurisdiction, retention periods, legal holds, deletion rules and subject-access workflows apply to each class? Define storage region and encryption/key ownership.
4. What is the behavior when manager changes, employment ends, a request is rejected, or an approver loses access? Define whether and how historic records can be reviewed.
5. Which scans, file types, maximum sizes/counts, DLP response, incident alerts and audit review cadence are required? Who handles break-glass review?
6. What tenant / site / branch boundaries apply, and is cross-entity HR authority ever permitted?

## Checkpoint / non-goals

W-DOC-02 DESIGN REVIEW COMPLETE; policy approvals PENDING. No migrations, endpoints, file writes, uploads, runtime DB operations, QAS or Production actions, SSH, role assignments or changed approval routes. W-ORG-01b authenticated QAS acceptance remains OPEN/DEFERRED. Next bounded slice: W-DOC-03 approved privacy contract + isolated policy-test specification, only AFTER answers to the decisions above; do not silently implement permissive access defaults.
