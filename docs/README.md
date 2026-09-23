# Dynamic documentation guide

Updated: 2026-09-23. Working line: `v42-clean-baseline`.

The documentation is a living reference for the V42 application and release process. It covers the findings known from source inspection and the recorded environment checks; it is not a claim that every workflow has been implemented, visually verified, or deployed. Treat a dated audit as evidence from its stated snapshot and use the status record below for what changed afterward.

## Start here

| Need | Document | How to use it |
| --- | --- | --- |
| Current version, environment state, release blockers | [Master project status](../PROJECT_MASTER_STATUS.md) | Update this with every code or deployment batch. It is the primary current-state record. |
| Scope, severity, source evidence, acceptance criteria | [Full project audit](FULL_PROJECT_AUDIT_2026-09-23.md) | Findings are a dated baseline; read the current-status note at its top before using an older statement. |
| Expanded product direction and implementation sequence | [Product roadmap](PRODUCT_ROADMAP_2026-09-24.md) | Tracks UI/UX, POS, barcode, invoices, workforce and delivery expansion as phased future work. |
| Admin screens and customer journeys | [UI/UX and commercial-readiness review](UI_UX_AND_COMMERCIAL_READINESS_REVIEW_2026-09-23.md) | Use its screen-by-screen acceptance checks for focused QAS reviews. |
| Admin daily-work QAS tasks | [Admin UX QAS checklist](ADMIN_UX_QAS_CHECKLIST.md) | Record authenticated Arabic/English desktop/mobile evidence against the exact application commit. |
| Exact release and rollback procedure | [Production deploy](PRODUCTION_DEPLOY.md), [release checklist](RELEASE_CHECKLIST.md), [QAS checklist](QA_CHECKLIST.md) | Follow the exact-commit CI → QAS → visual review → Production dry-run/deploy sequence. |
| Payment validation | [Paymob E2E runbook](PAYMOB_E2E_RUNBOOK.md) | Record real sandbox callback, failure and duplicate-path evidence before closure. |
| Historical cleanup | [Archive notes index](ARCHIVE_NOTES_INDEX.md) and `archive/` | Historical context only; do not treat old reports as live status. |

## Current implementation and validation ledger

| Work | Source state | CI | QAS | Production | What remains |
| --- | --- | --- | --- | --- | --- |
| V42 deployed application | Exact deployed commit `95e9f50` in the master record | Previously verified | Previously verified | Verified HTTP 200 | Reconcile later source changes by exact commit. |
| Homepage deployment-marker cleanup | Commit `41a2f99` on the working line | See Actions | Not recorded as deployed | Not recorded as deployed | QAS visual check, then controlled Production promotion. |
| Batch 0: access, product-page trust, demo-data guard | Code revision `f7ff4e8` on `v42-clean-baseline`: require a non-`super_admin` staff role for promotion; block owner edits in customer management; remove fabricated product counts/reviews and repeated reassurance blocks; restrict demo seed/clear to local/testing/staging in both web and service paths | [Hardening CI run 35916058338](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/35916058338) passed PHP syntax, clean MySQL migration, Blade compilation, 45 tests and frontend build | Not deployed/verified | Not deployed | Explicit owner migration/fallback removal, authentic review workflow, actual content review, QAS visual check. |
| Admin daily-work UX | Code revision `9fe1a96` on `v42-clean-baseline`: compact operator dashboard; shared keyboard-accessible section tabs on product, branding and content forms; permission-aware search/navigation; streamlined sidebar; order-detail section links and action permissions. One existing save form remains per editor. | [Admin UX code CI run 35919171926](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/35919171926) passed PHP syntax, clean MySQL migration, Blade compilation, 48 tests (208 assertions) and frontend build | Not deployed/visually verified | Not deployed | [QAS checklist](ADMIN_UX_QAS_CHECKLIST.md): test Livewire uploads/variants, validation focus, Arabic/English RTL and mobile width; publish-readiness hints, deeper analytics simplification and remaining page consistency remain open. |

The roleless-admin Super Admin fallback still exists for pre-existing accounts. The customer promotion path no longer creates new roleless admins, but the owner policy is **partially fixed** until existing admin accounts are inventoried and the designated owner receives an explicit role. Do not infer that a green CI run closes that release blocker.

## Update rule for each batch

1. Record the changed behavior, relevant tests and exact branch/commit in the master status. Keep deployment status separate from repository state.
2. Mark each audit finding as open, partial, verified in CI, verified in QAS, or verified in Production. Preserve its dated source evidence rather than rewriting history.
3. Link a successful CI run and the exact QAS commit after tests. Record desktop/mobile and Arabic/English visual evidence for UI work.
4. Record Production commit, smoke checks and rollback readiness only after the controlled release; update `main` only after source-control reconciliation.
5. Add newly discovered requirements with evidence, priority, owner/decision needed, acceptance check and dependencies. Never insert credentials, production customer data or unverified claims into this repository.

## Next implementation slices

1. Finish owner policy after inspecting real admin accounts and deciding the explicit owner-transfer procedure. Audit existing demo records, published content and support details in Production.
2. Implement functioning search and pricing/shipping/tax rules, then the unpaid-order inventory lifecycle and Paymob end-to-end validation.
3. Validate the admin sectioning, order-detail actions and shorter dashboard with real QAS records; then add publish-readiness checks and simplify deeper analytics and remaining admin pages.
4. Complete the customer funnel with real reviews/returns/addresses and measurable bilingual/mobile accessibility checks.

Each slice is reviewable on its own and follows CI → QAS → user review → Production. The roadmap is updated as new evidence appears; no document should imply a future feature is already live.
