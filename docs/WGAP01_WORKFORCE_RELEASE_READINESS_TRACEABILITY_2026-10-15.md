# W-GAP-01 — Workforce Release Readiness and Traceability Audit

Date: 2026-10-15 | SOURCE/ROADMAP AUDIT ONLY | Baseline `d361009d`

## Roadmap name collision and scope correction

The original `docs/WORKFORCE_ENTERPRISE_E2E_AUDIT_2026-10-14.md` uses `W-DOC-04` as its **implementation** workstream for personnel and leave attachments (models, private storage, scan, downloads and retention). A later documentation-only slice is also named `W-DOC-04 Decision Register`. They are NOT the same deliverable. Preserve both historical references and use unambiguous labels:

- `W-DOC-IMPL-04` = original roadmap's future real attachments **implementation**; BLOCKED on approvals B1–B5/S1–S2/L1; no runtime code authored.
- `W-DOC-04-REG` = completed **decision-register design** (`0ca73e06`), Design+CI evidence `c17a9cb7`, run #38663305033 GREEN.
- `W-DOC-05-TRACE` = completed synthetic privacy acceptance traceability **design** (`d361009d`), CI #38663773472 GREEN.

These names are disambiguation aliases, NOT renaming migrations, routes or any already released app identifiers.

## Release gates: verified evidence vs remaining gaps

| Area | Evidence available | Incomplete gate / not claimed |
|---|---|---|
| W-ORG-01 Reporting foundation | Existing `WorkforceAccessService` scoped access, reviewer/hierarchy regression source + CI checks | W-ORG-01b authenticated QAS acceptance DEFERRED; no production sign-off |
| W-LEAVE-02 policy | Existing policy services and automated CI coverage | Any jurisdiction/company-specific policy change requires Business decision and test evidence; not universally complete |
| W-APP-03 approvals | W-APP-03e source CI 952 tests on run #38661138767, reviewer hierarchy privacy diagnostics | Delegation, escalation, assignee state machine and formal policy remain separate unapproved work |
| W-DOC-IMPL-04 attachments | W-DOC-01 to W-DOC-05 documentation, threat/access matrix, P01–P14 synthetic future-test specification | NO feature: private document schema/store/scan/upload/download, actual P01–P14 tests or policy approvals |
| W-SCHED-05 scheduling | Scheduling and leave foundations exist in source | Draft vs publish conflict and effective policy decisions remain to audit/approve; no new behavior here |
| W-PAY-06 payroll | Workforce payroll foundation/source integrity and CI exist | Paid/unpaid leave, holiday rules, snapshot interaction and acceptance require explicit sign-off; no history mutation |

CI GREEN means source/repository gates passed in isolated automation, NOT authenticated QAS acceptance, privacy/legal sign-off or functional delivery of unimplemented attachment flows.

## Safest next bounded slice

`W-GAP-02` — read-only **release evidence inventory and unresolved contract mapping**: inspect existing Workforce tests and service gates, map each original roadmap DoD criterion to exact source and named CI regression evidence, and distinguish unknown, unimplemented, blocked-on-policy, blocked-on-QAS. This needs no policy approval as long as it remains source/documentation-only. No code behavior changes, schemas, uploads/downloads, seeded personal/medical information, or deployment.

## Non-goals and controls

No SSH, production or QAS writes, app feature implementation, access grants, changing leave/schedule/payroll policy, or financial backfill. W-ORG-01b remains DEFERRED. Any status beyond SOURCE+CI must be proven independently.
