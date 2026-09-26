# GLOBAL FOUNDATION AUDIT — 2026-09-26

## Purpose
Establish the shared baseline that must be hardened before the strict page-by-page closure pass. This is a product-quality and architecture pass, not a request to rewrite stable code without benefit.

## Verified baseline
- Active modernization/security branch: `sec03-framework-upgrade`.
- Runtime direction on this branch: PHP 8.3+, Laravel 13, Livewire 4.
- Production remains unchanged.
- Current repository inventory at the start of this pass: about 1,935 tracked files, 227 Blade views, 95 test files and 22 Livewire-related PHP/Blade files.

## Initial shared-foundation findings

### GF-01 — Admin mini-sidebar utility actions
**Finding:** normal menu labels were hidden in collapsed mode, but the utility-action labels for Open Storefront and Sign Out were not. Their text could visually escape the mini sidebar.

**Source action:** fixed globally in the shared Admin shell; utility actions become centered icon-only controls in collapsed mode while retaining accessible labels/tooltips.

**Gate:** automated CI + authenticated QAS visual check before CLOSED.

### GF-02 — Shared shell code concentration
- `resources/css/app.css` is currently empty.
- `resources/views/layouts/admin.blade.php` carries a very large shared CSS/JS surface (roughly 3.7k lines at this checkpoint).
- `resources/views/layouts/app.blade.php` also carries a large Storefront shared CSS/JS surface (roughly 1.1k lines).
- Dynamic theme values are intentionally rendered from Blade, so extraction must separate dynamic tokens from static rules rather than blindly moving everything into a static stylesheet.

**Direction:** progressively extract/reuse static shared rules and scripts when it reduces duplication or page risk. Do not perform a huge cosmetic refactor with no user/product benefit.

### GF-03 — Livewire 4 is available but not yet the universal interaction model
The project has a limited set of dedicated Livewire components relative to the total view count. Existing progressive no-reload patterns already cover many workflows, but the page pass must still inspect each interaction for unnecessary reload, scroll jump, stale state or extra confirmation click.

**Direction:** use the best safe Livewire 4 / progressive pattern per workflow, not Livewire for its own sake. Money, inventory, permissions and destructive rules remain server-authoritative.

### GF-04 — Customer visual foundation
The Storefront has a real functional foundation and theme variables, but customer-facing visual quality must be treated as a first-class redesign target during this pass. Existing markup is not sacred when a cleaner, more premium, more conversion-friendly structure is justified.

### GF-05 — Bilingual / RTL closure
Translation integrity tests exist and already cover many shared/Admin/Customer surfaces. The new closure rule is stronger: every touched page must be visually and functionally checked in EN + AR and LTR + RTL, not merely have translation keys present.

### GF-06 — Help / onboarding
The shared help system must be evaluated for contextual usefulness, not only presence. Empty states, validation errors and unfamiliar settings should explain the next action clearly to a first-time operator/customer.

### GF-07 — Motion and feedback
Create one restrained motion/feedback language across Admin and Storefront: button/loading feedback, drawers/modals, live updates, skeletons and state transitions. Avoid decorative or slow animation.

### GF-08 — Authentication / social sign-in
Connected identity foundations exist in project status, but real customer social sign-in is not considered closed until provider integration, secure configuration, UI, failure/collision flows, redirects, mobile behavior and QAS are all production-ready.

## Global Foundation execution order
1. Admin shell/navigation closure.
2. Storefront shell/navigation/footer visual foundation.
3. Shared design tokens/components and action hierarchy.
4. Forms, validation, toast/error/success/loading/empty states.
5. EN/AR + RTL/LTR global consistency.
6. Live/no-reload/state-preservation interaction standards.
7. Responsive/mobile + accessibility baseline.
8. Motion/animation baseline.
9. Shared CSS/JS/component cleanup where it prevents repeated work.
10. Auth/account shell and social-login readiness.
11. Begin strict page-by-page closure using `docs/PAGE_CLOSURE_SYSTEM_2026-09-26.md`.

## Closure discipline
A global item or page is not CLOSED because source code was changed. It must pass the relevant automated tests and the required QAS/visual/functional acceptance.
