# W-DOC-05 — Synthetic Security Acceptance and Policy Traceability

Date: 2026-10-15 | Baseline `c17a9cb7` | DESIGN SPECIFICATION ONLY

## Boundary

This document maps the W-DOC-03 proposed tests P01–P14 to unresolved approvals in W-DOC-04. The data are hypothetical and use invented tenant, account and object identifiers only; no real medical, identity, employment or user data. NONE of these tests have been executed against a document feature. There is no authorized document schema, storage pipeline, download endpoint or business access policy yet.

## Test fixtures (future isolated test environment ONLY)

- Tenant `tenant-A` and independent `tenant-B`; employee `emp-A1` and unrelated `emp-A2`; non-member `emp-B1`.
- Managers `mgr-A-active` and `mgr-A-former`; a separate generic admin `admin-A`; suspended custodian `hr-A-revoked`; potential dedicated custodian `hr-A-pending` (no actual role grants).
- Opaque synthetic file IDs `doc-general-1`, `doc-medical-1`, `doc-identity-1`; versions `v1/v2`, no real file bytes or attachment names.
- Policy inputs remain placeholders marked `PENDING`; do not turn draft matrix entries into allow rules until approval.

## Traceability matrix — proposed acceptance, never actual pass

| Case | Synthetic scenario / privacy assertion | Decision dependencies | Gate |
|---|---|---|---|
| P01 | `emp-A1` requests own general metadata; response projects only approved fields and never storage key or filename | B1, B4, S2, L1 | Owner metadata policy pending |
| P02 | `emp-A2` or unrelated manager probes known `doc-general-1`; no bytes or existence leak | B2, B4, S1, S2 | Tenant and relationship scope pending |
| P03 | `emp-B1` requests `tenant-A` document via list/export/direct ID; all denied | B4, S1, S2 | Tenant boundary pending |
| P04 | `mgr-A-former` reuses former valid URL after a manager change; no access after reassignment | B2, B5, S1, S2 | Revocation semantics pending |
| P05 | Manager with general workforce permission requests medical or identity content; no implicit permission inheritance | B2, B3, S1 | Document-specific grants pending |
| P06 | `admin-A` enumerates identities; generic admin privileges do not imply access | B3, B4, S2 | Custodian role policy pending |
| P07 | Pending leave reviewer sees at most explicitly approved evidence-present status, never diagnosis | B2, B3, S2, L1 | Evidence disclosure pending |
| P08 | General doc is reclassified as Medical; old list and signed link must not bypass new class | B5, S1, S2 | Reclassification policy pending |
| P09 | `hr-A-revoked` tries cached link after role removal; fail closed before content delivery | B3, B5, S1 | Revocation and delivery gates pending |
| P10 | Fictional path traversal / MIME mismatch; ingest quarantines invalid bytes, retrieval cannot traverse | S1, S2 | Storage/validation architecture pending |
| P11 | Synthetic deletion request under legal hold; apply approved hold/retention precedence | L1, S2 | Legal retention policy pending |
| P12 | Denied and successful synthetic access produce minimal structured audit evidence without content | B3, S2, L1 | Audit policy pending |
| P13 | Search/page/count hides unrelated employee/tenant records, including counts and error differences | B2, B4, S2 | Visibility projection pending |
| P14 | Role removed between request and content streaming; authorization checked at delivery boundary | B3, B5, S1, S2 | Concurrency and revocation pending |

## Cross-cutting negative contract

No role can infer a document from predictable IDs, status codes, timings, counts or filenames. Do not assume a 403 vs 404 choice until Security approves information-disclosure behavior. Explicit authn, tenant context and a document-specific purpose grant must be verified at every access point. No generic admin or current manager gets medical bytes by default. Zero sensitive content in logs, analytics, email or URLs.

## Acceptance definition after decisions are approved

1. Written sign-off B1–B5, S1–S2 and L1 is linked to an immutable policy revision and named authority; until then, every dependent scenario is BLOCKED / NOT RUN.
2. Separate future implementation slice creates only authorized private storage, class-aware authorization, isolated fixture builders and minimal endpoints under approved constraints.
3. Run P01–P14 as isolated automated DB/service/HTTP tests against synthetic fixtures, plus browser tests for leak-free projections; capture failures with masked outputs and prove cross-tenant denials.
4. Independent security review checks IDOR enumeration, caching, URL exposure, malicious bytes/quarantine and scope revocation. CI Green alone is not policy or QAS approval.
5. QAS authentication/acceptance occurs only after explicit release authorization. W-ORG-01b acceptance stays DEFERRED.

## Project checkpoint

W-DOC-05 is documentation-only and requires no runtime data changes. Do not claim the future tests passed. No schema, upload/download implementation, SSH, QAS/Production writes, live role grants, or new approval policy. The W-DOC-04 decision register remains OPEN pending sign-off.
