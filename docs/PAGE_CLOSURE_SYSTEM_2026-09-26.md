# PAGE CLOSURE SYSTEM — 2026-09-26

## Goal
Turn Dynamic into a polished, production-grade platform by using the upcoming full application pass as a one-time closure opportunity rather than a superficial visual sweep.

## Execution order
1. **Inventory and risk** — enumerate user-facing routes, navigation entries, roles, shared components, page variants and cross-page journeys. Record the source/CI/QAS/Production SHA ledger and existing P0/P1 findings.
2. **Global Foundation** — establish reusable Admin/Storefront shells, design system, shared components, forms, feedback states, bilingual/RTL rules, responsive behavior, accessibility, motion and Livewire interaction standards; accept shared patterns on representative real pages.
3. **Page-by-Page Closure** — review every Admin and Customer page against the full Definition of Done below, including POS and Workforce surfaces. Keep release-blocking security/operations work active in parallel.
4. **Journey and consolidated QAS** — verify authenticated EN/AR, RTL/LTR, desktop/mobile and cross-page business flows on the exact deployed application revision.
5. **Independent audit and release review** — close acceptance findings, rerun affected regressions, and present reproducible buyer evidence. Production remains unchanged until separate security, payment and operations gates are satisfied.

## Page statuses
- **OPEN** — not yet fully reviewed.
- **IN REVIEW** — active full-spectrum review/fix/testing is underway.
- **CLOSED** — all relevant checks passed; reopen only for a new requirement or newly discovered defect/regression.

Source implementation, CI and QAS are separate evidence fields, not extra status names. `CLOSED` requires the relevant authenticated QAS checks on the deployed application SHA. A later shared-layer change triggers targeted regression checks on affected pages and reopens them if a defect is found.

## Coverage ledger

Before claiming all pages are closed, build a page inventory from Laravel `route:list` on a working PHP environment, actual Admin/Customer navigation, conditional views, modals and role variants. Map route name(s), view/component, owning feature, roles, linked business journey and status. A Blade-file count is not a page count. Reconcile the inventory against the [QAS checklist](COMPLETION_PASS_QAS_CHECKLIST_2026-09-25.md) and update it when new routes or conditional workspaces are discovered.

The [initial source inventory](PAGE_INVENTORY_2026-09-26.md) and its CSV seed cover literal GET declarations, with explicit limits; they do not substitute for the framework/live reconciliation above.

For each page, keep the evidence card specified in the [buyer-grade execution plan](BUYER_GRADE_EXECUTION_PLAN_2026-09-26_AR.md): exact source and QAS SHAs, CI result, locales/directions, phone/tablet/desktop, keyboard/zoom, normal and failure states, direct permission/ownership checks, representative data and performance, outstanding findings and reviewer/date. A source-complete page awaiting QAS remains `IN REVIEW`.

## Definition of Done for every page
Every relevant dimension must be reviewed, even when no change is required:

- Product completeness: missing capabilities, confusing flows, unnecessary content and dead-end states.
- Information architecture: correct section grouping/order, hierarchy, navigation and action placement.
- Visual quality: typography, spacing, cards, tables, forms, icons, colors, density, consistency and trust.
- Interaction quality: eliminate unnecessary reloads, scroll-to-top jumps, stale values and prototype-like extra clicks.
- Live behavior: use Livewire 4/progressive live interactions where useful for search, autocomplete, filters, pagination, calculations, inline actions and partial refreshes.
- State preservation: retain useful scroll, focus, filters, pagination and workspace context where practical.
- Validation and feedback: precise inline errors, server-authoritative validation, loading/disabled states, success/error feedback, confirmations and safe destructive actions.
- EN/AR + RTL/LTR: no mixed untranslated UI, broken direction/alignment or inconsistent terminology.
- Responsive/mobile: desktop, tablet and phone layouts; usable touch targets; future Android/iPhone readiness.
- Accessibility: keyboard/focus behavior, labels, semantic controls, contrast and basic screen-reader support.
- Security and permissions UX: authorization remains server-side; do not expose misleading actions; protect sensitive/destructive workflows.
- Performance: bounded queries, pagination, lazy/deferred loading where appropriate, efficient images/assets and no unnecessary re-rendering.
- Help/onboarding: contextual help, examples, useful empty states and explanations suitable for a first-time user without creating clutter.
- Maintainability: extract repeated components/patterns, remove dead/duplicate code, avoid heavy business logic in Blade and keep mobile/API readiness.
- Tests: regression coverage for meaningful behavior changes plus existing CI gates.
- QAS: authenticated functional and visual acceptance on the exact revision before CLOSED status.
- Cross-page effect: linked journeys and shared components still behave correctly; browser Back/Forward, refresh, expired session, slow network and repeated submissions are checked where relevant.
- Content and claims: no placeholder/demo promises in the customer product; displayed features, payment/tax claims, policies and links match implemented and approved behavior.

## Customer / Storefront direction
The current functionality is a foundation, not a visual constraint. Customer pages may be reorganized, simplified, replaced or rebuilt when it materially improves clarity, trust, conversion, responsiveness or perceived product quality. The target is a deliberate modern commerce product, not a styled demo application.

## Admin direction
Admin is also a first-class product experience. Long pages should be split into understandable workspaces/sections/tabs where appropriate, and important actions such as Save/Apply should remain reachable (for example with sticky action areas) rather than forcing unnecessary long scrolling.

## Motion
Use subtle, purposeful and performant motion for feedback, drawers/modals, live updates, skeleton/loading states and transitions. Avoid decorative animation that slows the user or distracts from work.

## Livewire 4 rules
- Prefer partial server-confirmed updates over full page reloads when the workflow stays on the same page.
- Immediate quantity/price/total changes should update immediately when safe instead of requiring a second Update click.
- Preserve no-JS/server fallbacks where they materially improve resilience.
- Money, inventory, permissions and destructive business rules remain server-authoritative.
- Use capabilities such as live models/actions, navigation, lazy/deferred loading and isolated components only where they improve the real UX/performance.

## First global-shell finding
The collapsed Admin sidebar hid normal menu labels but did not hide the labels for the utility actions (Open Storefront and Sign Out), causing their text to escape the mini sidebar visually. The global shell fix makes those utility actions icon-only and centered in collapsed mode while retaining accessible labels/tooltips.

## Working discipline
Do not optimize only the issue that originally brought us to a page. Review the whole page so this pass closes accumulated product, UI, UX and technical debt instead of moving it around.

Work in coherent batches and checkpoint the exact source/CI/QAS state after each meaningful slice. The page pass never substitutes for security, payment, restore or cross-page journey acceptance. See the [execution and handoff plan](BUYER_GRADE_EXECUTION_PLAN_2026-09-26_AR.md).
