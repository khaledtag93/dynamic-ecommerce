# Dynamic — Product UX Master Backlog

Updated: 2026-09-25

This file keeps cross-cutting UX work ordered so individual workstreams can be finished without opening unrelated areas in parallel.

## Working rule

Finish the active workstream to a strong, tested state before moving to the next major area. Cross-cutting discoveries are recorded here and scheduled deliberately instead of interrupting the current batch.

## Current workstream

### Systematic Bilingual Integrity Sweep

Started after the Footer / Storefront Shell V2 source hardening. The first shared-shell slice now guards Admin navbar/sidebar, language switcher, page-help and section-tab translation keys in both EN and AR. Missing shared-shell copy discovered in the sweep was completed, including sidebar accessibility/navigation labels and shared group labels.

Continue surface-by-surface across Admin, Customer, POS and Workforce, prioritizing shared components and high-traffic workspaces before isolated pages. Mixed-language UI remains a product-quality defect unless the text is intentionally language-neutral.

### Footer / Storefront Shell V2

Turn the storefront footer into a configurable production component suitable for multiple business types. The active source work now includes independent visibility controls for Shop, Policies, Categories, Support, Trust highlights and the experience note; actionable email/phone/WhatsApp/business-website support channels; automatic suppression of empty support content; responsive/RTL presentation polish; and three bilingual configurable trust highlights instead of commerce-specific hard-coded promises.

Footer settings reuse the existing WebsiteSetting / StoreSettingsService / localized-settings architecture rather than introducing a parallel configuration system. Regression coverage protects toggle persistence, bilingual trust-copy persistence/rendering and empty-support behavior. Continue hardening this workstream before the systematic bilingual integrity sweep.

Shared Navigation Shell + Global Feedback/Toast source hardening is complete and has been explicitly deployed to QAS. Its authenticated desktop/mobile EN/AR visual review is deliberately deferred so collected QAS issues can be reviewed together later; it does not block the current footer workstream.

### Recently closed

- Footer / Storefront Shell V2 — source hardening completed 2026-09-25 with configurable sections/support/trust/social channels, bilingual copy, safe social URL validation, responsive/RTL treatment and regression coverage. Authenticated QAS visual acceptance remains a separate deployment gate.
- Analytics & Insights V2 — closed 2026-09-25 after source-level UX, responsive/RTL, bilingual and regression hardening.
- Branding & Appearance V2 — closed 2026-09-25 after final Hardening CI passed at `abb0477`.

## Cross-cutting product standards

### 1. Bilingual integrity — high priority

Arabic inside an English interface, or English inside an Arabic interface, must be treated as a product-quality defect unless the text is intentionally language-neutral (for example a SKU, email, URL, brand name, or technical identifier).

Audit Admin, Customer, POS, Workforce and shared components for:
- hard-coded UI strings;
- missing EN/AR translation keys;
- mixed-language headings, buttons, helper text, validation, empty states and status labels;
- RTL alignment and spacing regressions;
- backend/server messages leaking untranslated text into the UI.

Goal: each locale should feel intentionally authored, not translated in patches.

### 2. Action feedback and scroll stability — high priority

Routine actions should not make the page jump to the top just to expose a success/error alert.

Preferred pattern:
- toast notifications for non-blocking success and error feedback;
- preserve scroll position and current workspace/tab/filter where practical;
- inline field validation beside the relevant input;
- page-level blocking alerts only when the whole page genuinely requires attention;
- live/AJAX interactions where they materially improve the workflow, with safe server-side validation and fallback behavior.

Apply consistently to search, create, update, delete, quantity changes and similar frequent actions.

### 3. Immediate reactive controls — high priority

Controls that visually imply direct manipulation must update immediately. Do not require a separate Apply/Update button for simple quantity or state changes unless the workflow genuinely needs an explicit confirmation step.

Examples:
- cart/POS quantity changes recalculate totals immediately;
- toggles reflect their state without unnecessary reloads;
- filters/search update without disruptive full-page reloads where practical;
- dependent totals, badges and summaries stay synchronized.

### 4. Navigation shell quality — source hardening substantially complete

Admin sidebar/top navigation and customer navigation are persistent product surfaces and need dedicated polish:
- cleaner hierarchy and grouping;
- strong active/hover/focus states;
- compact but readable spacing;
- responsive/mobile behavior;
- RTL-safe icons, chevrons and alignment;
- sensible collapse/expand behavior;
- notification/account/cart states;
- accessibility and keyboard navigation;
- consistent visual language with the selected brand/theme.

Do this as a shared-shell workstream rather than page-by-page patches.

### 5. Footer — active major workstream

Complete the storefront footer as a configurable production component:
- brand identity and useful store information;
- configurable navigation groups;
- contact/support information;
- legal links;
- social/channel links when configured;
- payment/shipping/trust information where appropriate;
- bilingual/RTL and mobile layout;
- avoid empty or demo sections;
- configuration should hide unused content cleanly.

## Planned order after Branding

1. Analytics & Insights workspace refinement, unless a blocking shared-shell defect requires earlier treatment.
2. Shared navigation shell (Admin + Customer) and global feedback/toast behavior.
3. Footer/storefront shell completion.
4. Systematic bilingual integrity sweep across all surfaces.
5. Reactive/no-reload interaction sweep, including quantity/totals and remaining search/action reloads.
6. Continue remaining functional modules according to the master roadmap.

The order can be adjusted when dependencies make another sequence safer, but workstreams should not be mixed casually.


## Ongoing collaboration and continuity rules

These are permanent project-working rules and should be carried into future sessions:

- Record meaningful product decisions, newly agreed UX standards, workflow rules, discovered gaps, technical risks, and roadmap changes in repository notes instead of relying only on chat history.
- Keep notes concise and useful: do not record casual conversation or duplicate information that is already documented accurately.
- Work in medium-to-large coherent batches. Avoid both tiny one-detail commits and giant tool operations that risk hanging; internally split inspection/execution when needed, then deliver a coherent tested batch.
- Use a fast-but-safe batching model: do not stop development for every intermediate CI run. Continue independent low-risk work within the same coherent batch, then use the latest branch-head CI as the primary verification gate. Stop and fix immediately only when a failure or dependency makes continuing unsafe.
- Finish the active workstream before opening unrelated major workstreams. New ideas should normally enter the backlog in their proper order unless they are a dependency or blocking defect.
- Monitor CI after pushes during active work. When waiting for CI, estimate completion time from recent real run durations and report an approximate local ETA, clearly as an estimate rather than a guarantee.
- Do not deploy QAS or Production automatically. Deployment remains an explicit user decision.
- When a full workstream is genuinely complete and the project moves to a materially different area, explicitly mark the completed workstream and name the next one. Do not repeat milestone announcements for every small batch.
