# Dynamic Completion Pass — 2026-09-25

## Decision

Dynamic is entering a completion pass. The goal is to bring the modules already built from “works” to production-grade before expanding the roadmap.

This is not a freeze on fixes or required dependencies. It is a freeze on avoidable feature expansion.

## Definition of source-complete

For the agreed scope of an existing feature, verify:

1. Business rules and data semantics are correct.
2. Authorization and ownership boundaries are explicit.
3. Mutations are safe against duplicate/replayed requests where relevant.
4. Validation and error states are clear.
5. Large datasets are paginated, bounded or intentionally summarized.
6. English, Arabic and RTL copy/layout are complete.
7. Desktop and mobile-web behavior are usable and consistent.
8. Business logic is not trapped in Blade/browser-only code where future Android/iPhone clients will need it.
9. Regression tests cover the important success and failure paths.
10. Documentation describes what is implemented and what is intentionally deferred.

## Definition of release-complete

Source-complete is not the same as Production-ready.

Before release:
- run the integrated branch-head CI;
- deploy the exact commit to QAS;
- verify authenticated EN/AR desktop/mobile workflows with realistic records;
- verify permissions and destructive/financial/stock mutations;
- resolve QAS findings;
- complete payment/provider and infrastructure checks that require real environments;
- promote to Production only through the controlled exact-commit deploy/rollback process.

## Scope-control rule

New ideas go to the backlog unless one of these is true:
- they fix a security or correctness defect;
- they are a direct dependency of the active feature;
- deferring them would force near-term rework of the same architecture;
- they are required for the agreed definition of done.

This prevents an endless cycle where every nearly finished module creates three more modules before validation.

## Current checkpoints

- **Growth Workspace V2.4:** source performance/scale pass complete; independent pagination replaces hidden first-N truncation, Overview uses targeted health counts, and experiment-performance work is bounded by page. Hardening CI #1372 passed at `0f1317d` with 368 tests / 2487 assertions and frontend build.
- **Connected Identity Foundation:** collision-safe provider-neutral identity model is CI-green at `8fb4667`. Real Google/Facebook OAuth remains blocked on adding Laravel Socialite with a legitimate Composer lock update; do not hand-edit the lock.
- **Helpdesk V2:** SLA targets and bilingual reply templates are CI-green. Attachments, omnichannel ingestion and business-hours SLA calendars are expansion items, not blockers for the existing V2 scope.
- **Customer Account Statement V1:** existing scope already includes date/type filters, canonical drill-through, print, UTF-8 CSV, multi-currency separation and authorization tests; no fabricated running balance.
- **Explicit Admin Role Hardening:** the roleless-admin Super Admin fallback is already removed in source. Historical notes claiming otherwise are obsolete.

## Working order

1. Finish active source gaps with the highest security/data-correctness impact.
2. Close measurable performance/scale gaps in existing modules.
3. Close bilingual/RTL/responsive and consistency defects.
4. Reconcile documentation so historical notes cannot be mistaken for current gaps.
5. Run consolidated QAS acceptance across Admin, Customer, POS, Workforce and Growth.
6. Fix QAS findings.
7. Run release-readiness and Production promotion gates.
8. Resume deferred/new roadmap features.

## Mobile application rule

Dynamic is expected to gain Android and iPhone applications later. Current work should keep domain services, stable identifiers, idempotent mutations, authorization boundaries, notification concepts and provider-neutral identity suitable for a future API/mobile client. Do not start the native application during this completion pass.
