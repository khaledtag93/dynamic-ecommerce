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

### GF-09 — Audit coverage and release evidence
The initial shared-foundation list did not itself enumerate every route/page variant or prove that shared changes worked across roles and journeys. Build the route/navigation/role inventory and accept each common pattern on representative Admin and Customer pages. Record CI, QAS and Production revision separately. Keep OPS-01, PAY-01 and OPS-02 release blockers visible while independent UI work continues.

**Progress:** CI run `36251293003` exported the 337-entry Laravel route manifest and reconciled all 142 literal GET seed declarations with 160 registered GET-capable routes. The [registered route inventory](PAGE_INVENTORY_2026-09-26.md) remains a discovery aid. Actual navigation, conditional UI, permission/role variants and QAS evidence remain open.

### GF-10 — Reproducibility and buyer review
The buyer may independently inspect source, dependencies, clean installation, upgrade migrations, authorization, browser behavior, performance and operational recovery. Add repeatable evidence and product-scope claims to the [buyer-grade execution plan](BUYER_GRADE_EXECUTION_PLAN_2026-09-26_AR.md), then use it as a gate. Passing tests alone does not prove visual or operational acceptance.

### GF-11 — Shared Admin confirmation and submit feedback
**Finding:** the confirmation dialog was used by destructive, stock, payroll and deployment forms. Without a typed phrase it did not take keyboard focus, did not trap Tab or restore focus, and `data-submit-loading` could disable the submit button before the confirmation was accepted. A native validation failure after `requestSubmit()` could leave the one-time confirmation flag set for the next attempt.

**Source action:** moved the common dialog contract to `public/admin/js/admin-confirm-dialog.js`, added focus/Tab/Escape restoration and background inertness, accessible typed-phrase errors, conditional confirmation, original submitter preservation and a flag reset after native validation. Loading starts only on the accepted submission. Inactive coupon saves no longer show a misleading publish confirmation. Four Node interaction tests are wired into Hardening CI without adding frontend dependencies.

**Gate:** latest-head CI plus authenticated QAS checks for cancel/confirm/invalid-input/keyboard flow on representative destructive, deploy-phrase and publish forms in EN/AR and mobile. This finding remains IN REVIEW until those checks pass on the deployed application SHA.

### GF-12 — Collapsed navigation labels and viewport stability
**Finding:** the seven Admin sidebar group summaries lose their visible text in icon-only mode, leaving icons without a reliable accessible name or tooltip. The on-load `scrollIntoView()` for the current menu item could also move the document viewport while trying to reveal the sidebar item.

**Source action:** localized `aria-label`/`title` on every group summary, with `aria-expanded` still synchronized on toggle. The current item is now revealed by scrolling the sidebar itself only when it has internal overflow; page scroll is not touched. Existing source regression coverage was aligned with the new scroll contract.

**Gate:** [Hardening CI 36250969555](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36250969555) passed for source commit `23d87640ab3b9047c70668071e400da01dfb6876`. Authenticated QAS checks on expanded/collapsed desktop and mobile in EN/AR, keyboard/screen-reader naming, sidebar scrolling and no main-page jump remain outstanding. GF-12 remains IN REVIEW until matching-revision QAS acceptance.

### GF-13 — Public health probes and local diagnostics
**Finding:** `/ping` and `/api/ping` returned the local safe-boot state, application environment, database connection name and application URL to unauthenticated callers. The front controller wrote `local_boot_trace.log` for every request even with the local diagnostics flag disabled.

**Source action:** both JSON health probes now expose only their minimal `ok`/`message` contract. The unused environment-report method was removed. Front-controller trace and shutdown logging run only when `LOCAL_SAFE_BOOT` is explicitly enabled; the local pre-boot ping shortcut remains available. A feature test asserts exact public probe bodies.

**Gate:** [Hardening CI 36251884894](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36251884894) passed on source `39c6a87b7e475f70d9d81b4d569e3208c443a999`. QAS checks of both probe variants with diagnostics disabled and local safe boot enabled where appropriate remain outstanding. QAS/Production have not been changed by this finding.

### GF-14 — Storefront navigation destinations
**Finding:** the shared storefront header used local fragment URLs for Offers, Best sellers, New arrivals and the category dropdown's View all action on every page. Outside Home these fragments had no target. Home sections are configurable, so a disabled section could also leave a visible dead navigation link. A source-to-route check found all 45 sidebar, 11 Admin topbar, 18 Storefront shell and 6 account-navigation literal route names registered; the failure was the destination behavior, not a missing named route.

**Source action:** shared Storefront navigation now keeps in-page jumps for enabled Home sections; off Home, Offers and New arrivals go to their real filtered/sorted product list. Category/Best sellers links reach their Home sections from other pages only when those sections are enabled, and disappear when disabled. Three focused tests cover Home, another page and disabled sections. Storefront page content and user-configured hero/banner links will receive their own page-level review.

**Gate:** [Hardening CI 36252296274](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36252296274) passed on source `7ceff28f05cd7e79c5007a74ee2e2195a2f959a3`. EN/AR desktop/mobile QAS checks from Home, product/category, cart and account routes, including enabled/disabled Home-section settings, remain outstanding. GF-14 remains IN REVIEW until matching-revision acceptance.

### GF-15 — Storefront action destinations and shared-view query efficiency
**Finding:** after the shared header navigation fix, configurable Home/page actions still had two cross-page risks. Known Home fragments used by hero/banner/suggestion actions could point to sections the merchant had hidden, and the shared view composer recalculated Storefront settings/categories/cart state for multiple partials inside one HTTP request. Category tiles also counted visible products per category, creating avoidable repeated queries.

**Source action:** known Home action fragments now resolve against the sections actually rendered: valid local sections keep their anchor, hidden known sections fall back to a real product/search destination, on-sale falls back to the filtered offers result, and merchant-controlled external/custom destinations are preserved. Product/cart/checkout suggestion actions no longer use dead local Home fragments. Shared Storefront view data is memoized only in the current request via request attributes (no process-wide state), and category product totals are loaded with `withCount` rather than one count query per tile.

**Gate:** [Hardening CI 36256650267](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36256650267) passed on source `3fe0f28d2d1f5f1980155c9250e9d23e19a91d5b`; [Hardening CI 36256978023](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36256978023) passed on `a536b22bf815ec616afa1c9b36a7f3ec075cb5b2`. Matching-revision QAS checks still need to verify enabled/disabled Home sections plus representative Home/product/cart/checkout navigation and real request/query behavior. GF-15 remains IN REVIEW until that acceptance.

### GF-16 — Shared keyboard landmarks and reduced-motion behavior
**Finding:** neither shared shell exposed a skip-to-content link; the Admin content wrapper was not a `<main>` landmark; and both shells used motion without a common `prefers-reduced-motion` baseline. Storefront account-nav auto-scroll was also nested inside the `/` search shortcut, so pressing the search shortcut could trigger unrelated navigation motion, and the JavaScript scroll was always smooth even when the user requested reduced motion.

**Source action:** Admin and Storefront now expose localized EN/AR skip links targeting focusable main landmarks. Both shells apply a reduced-motion CSS baseline while keeping normal motion for users who have not requested reduction. The mobile account-nav reveal runs independently during shell initialization and switches JavaScript scrolling to `auto` under reduced-motion preference; the `/` keyboard shortcut now only focuses the visible Storefront search. Regression coverage protects the landmarks, bilingual copy, motion preference and shortcut separation.

**Gate:** the initial landmark/motion source `72cf678010b35ff6967fe4c1077feece790f8ba6` passed [Hardening CI 36257428543](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36257428543). An intermediate follow-up correctly exposed one stale existing test that required unconditional smooth scrolling; that assertion was aligned with the accessible contract. Final source `ba00f303b2e32fb8cb76ce84f25af89d449fadf7` passed [Hardening CI 36257847029](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36257847029): 470 PHP tests / 13,982 assertions, shared Node interaction tests, clean MySQL migration, Composer audit, Blade/config compilation and frontend build. QAS keyboard/mobile/EN/AR/reduced-motion acceptance remains open, so GF-16 is IN REVIEW.

### GF-17 — Storefront account/auth current-state and label semantics
**Finding:** the shared mobile Storefront navigation marked Account Overview with the broad `account.*` matcher while Address Book had its own `account.addresses.*` matcher. On address routes this could expose two different links as the current page. The desktop account dropdown did not expose current-page semantics, and the primary Login/Register forms were visually close to the newer auth pages but were missing the common page-shell geometry and explicit `label[for]` associations on credential fields.

**Source action:** Account Overview now matches only `account.index` in mobile/footer navigation; the desktop account dropdown exposes current states for Account, Orders, Address Book and Notifications and styles the active item. Login/Register now use `lc-page-shell`, the shared auth width, and explicit field-label associations. A rendered Address Book regression test protects against a false Account Overview current state, and auth tests protect the label/shell contract.

**Gate:** source `e82b3f9d82afdc3be71506827b29f71fb377f967` is implemented on `sec03-framework-upgrade`. GF-17 remains **IN REVIEW** pending readable final Hardening CI evidence and exact-revision EN/AR desktop/mobile keyboard QAS acceptance. No Production claim is made.

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
10. Auth/account shared shell and security/interaction contract. Implement real social login when the Auth page/workflow enters closure; connected identity alone is insufficient.
11. Build the page/role/journey inventory and begin strict page-by-page closure using `docs/PAGE_CLOSURE_SYSTEM_2026-09-26.md`.

The order expresses dependencies, not a requirement for a giant all-at-once refactor. A shared component is ready when its contract and representative real-page checks pass; expand it during the page pass when a specific finding requires it. Keep security/payment/restore P0 gates active in parallel and never confuse their QAS state with Production acceptance.

## Closure discipline
A global item or page is not CLOSED because source code was changed. It must pass the relevant automated tests and the required QAS/visual/functional acceptance.
