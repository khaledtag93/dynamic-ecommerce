# W-DOC-04 — Sensitive Workforce Documents Approval Dependencies Register

Date: 2026-10-15 | Baseline: `83840ef8` | Documentation-only / no feature changes

## Purpose and sequencing

W-DOC-02 established a proposed threat model and W-DOC-03 specifies a draft privacy contract with fourteen FUTURE tests. Neither authorizes HR evidence access, data collection, a storage pipeline or processing of real medical/identity records. This register converts the unresolved questions into named approval gates so implementation can be planned without assuming any business, legal or security decision.

## Decision register (all OPEN; suggested reviewers, not assigned approvers)

| ID | Decision to approve | Suggested accountable reviewers | Blocking effect |
|---|---|---|---|
| B1 | Employee self metadata/download rights per General/Medical/Identity class and lawful purpose | Workforce product owner + Legal/Privacy | Any self-service document view |
| B2 | Manager minimal evidence metadata, medical restrictions, indirect-line behavior, former manager rights | Workforce product owner + HR + Security | Any manager evidence surface |
| B3 | Designated HR document-custodian privileges, segregation of duties, support/break-glass | HR governance + Security | Document review/admin APIs |
| B4 | Tenant, branch and cross-entity HR boundaries | Product owner + tenant security | Document query/data scope |
| B5 | Historical approvals, lost relationship, reclassification and stale access | HR + Privacy + Security | Access after status/role change |
| S1 | Private storage, encryption, key ownership, AV/DLP scanning, MIME/count limits, delivery/cache | Security/Platform | Any upload, persistence or download |
| S2 | Metadata minimization, audit event access/retention, incident response | Security + Privacy | Logging and audit implementation |
| L1 | Jurisdiction, medical/identity record retention, legal holds, deletion & subject access rights | Legal + Privacy | Data lifecycle/schema design |

## Decision-ready evidence package to request (no automatic collection)

For each ID capture: explicit decision text, applicable tenant/location, approver name/title, approval date, policy/document version, effective date, expiry or review date, exception process and testable acceptance conditions. Do not store medical files or identity content in this register. Until approved, mark the rule pending and enforce no new capability.

## Recommended next bounded work

Prepare redacted, synthetic-only adversarial acceptance criteria and a source-to-policy traceability map to connect P01–P14 to future policy gates. Do not implement a document resource model, migrations, endpoints, or new roles before B1–B5/S1–S2/L1 approval. A green CI run on this design-only document confirms repository health, not legal or security approval.

## Safety status

W-DOC-04 is planning documentation only and is not automatically started as implementation. W-ORG-01b QAS authenticated acceptance OPEN/DEFERRED. No SSH, QAS/Production writes, uploads/downloads, migrations, test data, or access policy activation.
