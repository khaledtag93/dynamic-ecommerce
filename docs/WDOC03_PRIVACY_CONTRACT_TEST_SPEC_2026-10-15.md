# W-DOC-03 — Workforce Sensitive Documents Privacy Contract & Test Specification

Date: 2026-10-15 | Baseline: `8c4049a4` | Status: PROPOSED CONTRACT, NOT IMPLEMENTED/APPROVED

## Security invariants (implementation acceptance criteria, not existing functionality)

1. Deny by default: possession of an employee ID, document ID, `workforce.manage`, `workforce.scope.all`, super-admin status, or a valid leave-review action never implicitly grants document bytes or sensitive metadata.
2. Every list, metadata, preview, download, thumbnail, export and audit-log detail request must authenticate and independently authorize actor, tenant, employee, classification, specific document/version, operation and permitted purpose against current relationships.
3. Separate scoped employee visibility from document authority. Employee access to their own documents, manager evidence status, and HR document custody must each be explicit business decisions implemented in a dedicated document policy; do not reuse generic `WorkforceAccessService::canView` as sufficient proof.
4. No document content, original filename, identity number, diagnosis, signed link or storage path in list counters, search, errors, audit payloads, URLs, analytics, browser caches or outbound notifications. Minimal evidence-present flag only if policy explicitly grants it.
5. Object keys are opaque and stored privately outside web/CDN/public upload roots. Never redirect to permanent public links; every delivery rechecks authorization, sends safe disposition, cache controls and prevents cross-tenant and traversal access.
6. Approval status, reporting-line changes, HR role removal, employee termination, account suspension and document reclassification must be re-evaluated on each request; no historical approval produces perpetual viewing rights.
7. Scanning/quarantine, strict type and magic-byte checks, size/count ceilings, encryption/key ownership, retention holds and purge must be specified and approved before accepting a single real attachment.
8. All access attempts and modifications produce privacy-minimized audit evidence. Logs preserve actor/document opaque ID, purpose, decision, timestamp and policy version; never copy sensitive content or filenames.

## Operation-by-role contract draft (subject to approvals)

| Actor | General metadata | General bytes | Medical metadata | Medical bytes | Identity metadata | Identity bytes |
|---|---|---|---|---|---|---|
| Employee own account | Proposed allow minimal | Pending | Proposed allow minimal | Pending | Proposed allow minimal | Pending |
| Current direct manager | Pending minimal | Deny default | Pending evidence-present only | Deny default | Deny default | Deny default |
| Indirect manager | Deny default | Deny | Deny | Deny | Deny | Deny |
| Designated HR custodian | Pending purpose-scoped | Pending | Pending purpose-scoped | Pending | Pending purpose-scoped | Pending |
| Generic administrator | Deny default | Deny | Deny | Deny | Deny | Deny |
| Unrelated employee, ex-manager, suspended user, cross-tenant principal | Deny | Deny | Deny | Deny | Deny | Deny |

`Pending` means blocked for implementation until Business/Security approval, not a runtime behavior choice. These are proposed invariants and deny-by-default starting assumptions, not an approved access policy.

## Isolated verification scenarios for future authorized implementation

| ID | Setup | Operation | Required assertion |
|---|---|---|---|
| P01 | owner + general document | metadata | only allowed projected fields; no object key, private filename or sensitive attributes |
| P02 | unrelated manager and employee | list/direct GET with known foreign ID | deny without content, existence or metadata leakage |
| P03 | employee in tenant A and document in tenant B | list/download/export | deny; independent tenant predicate |
| P04 | former manager with prior approval and copied document URL | preview/download after reporting line change | deny under current privileges; old URL cannot bypass |
| P05 | manager granted `workforce.manage` but no document grant | identity/medical GET | deny even though general employee view is permitted |
| P06 | generic super-admin without custodian grant | direct ID enumeration | deny all sensitive classes |
| P07 | reviewer with pending leave | evidence-present flag | only minimal boolean if approved for purpose, never medical diagnosis |
| P08 | document reclassified general to medical | cached URL/list | subsequent request denied unless medical permission granted |
| P09 | suspended/revoked HR credential | download | deny immediately; no long-lived signed-link bypass |
| P10 | malformed/traversal object key and MIME/polyglot | stored file retrieval/ingest | cannot escape storage root; quarantine before any distribution |
| P11 | legal hold versus deletion request | retention workflow | documented hold wins; privacy-safe audit records decision |
| P12 | negative and positive accesses | logs/responses | actor/purpose/outcome recorded with no original filenames/medical contents |
| P13 | pagination/search/count API | filtered list | counts computed after actor/document-scope filter, no other-team existence leakage |
| P14 | concurrent role revocation | authorized start then content retrieval | authorization at final byte-delivery boundary, fail closed |

These are NOT executed tests: there is no authorized Workforce document feature to exercise yet. Avoid false `PASS` claims.

## Mandatory Business / Security sign-offs before any source implementation

- B1: Employee self-service visibility/download per General/Medical/Identity classification and legal basis.
- B2: Direct and indirect managers' minimum metadata/evidence permissions, special sick-leave review purpose, and handling an unavailable/changed manager.
- B3: Named HR custodian permissions separated from generic admins; approved support/break-glass procedure and segregation of duties.
- B4: Tenant/branch isolation rules, cross-entity HR cases and purpose-of-use restrictions.
- S1: Private storage topology, key ownership, encryption, scanning pipeline, permitted formats/limits, secure delivery and cache behavior.
- S2: Audit record retention/access, incident response, DLP and logs redaction rules.
- L1: Legal jurisdictions, classification-specific retention, deletion, legal hold, subject data access and expired employment conditions.
- B5: Historical approvals, revoked manager access and classification changes: whether any limited historical view is lawful; default remains no implicit rights.

## Implementation gate and non-goals

W-DOC-03 is a read-only privacy contract / test SPECIFICATION. No schema, upload or download endpoint, authorization feature flag, migration, test fixture with sensitive records, QAS/Production connection or data write is authorized. Any next implementation must be separately scoped after decisions B1–B5, S1–S2 and L1. W-ORG-01b authenticated QAS acceptance remains OPEN/DEFERRED. Prohibited SSH is not used.
