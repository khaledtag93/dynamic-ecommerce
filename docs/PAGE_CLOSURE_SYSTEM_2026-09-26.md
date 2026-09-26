# PAGE CLOSURE SYSTEM — 2026-09-26

## Goal
Turn Dynamic into a polished, production-grade platform by using the upcoming full application pass as a one-time closure opportunity rather than a superficial visual sweep.

## Execution order
1. **Global Foundation** — close shared Admin/Storefront shells, design system, shared components, forms, feedback states, bilingual/RTL rules, responsive behavior, accessibility, motion and Livewire interaction standards first.
2. **Page-by-Page Closure** — review every Admin and Customer page against the full Definition of Done below.
3. **Consolidated QAS** — verify authenticated EN/AR, RTL/LTR, desktop/mobile and real workflows on the exact source revision.
4. **Defect Closure** — fix every acceptance finding before release review.
5. **Release Review** — Production remains unchanged until the separate release/security/operations gates are also satisfied.

## Page statuses
- **OPEN** — not yet fully reviewed.
- **IN REVIEW** — active full-spectrum review/fix/testing is underway.
- **CLOSED** — all relevant checks passed; reopen only for a new requirement or newly discovered defect/regression.

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
