# Growth Workspace V2 — 2026-09-25

## Purpose

This batch starts the focused Growth Engine redesign after the owner flagged the previous page as visually weak, crowded, inconsistent, and still mixed between Arabic and English.

## Source checkpoint

- Working branch: `v42-clean-baseline`
- Growth UI implementation commit: `aa26a48338379acd0b0b1dc10b96426bc37b5d43`
- Arabic completion commit: `f32fba8a675913e06ab8ec910e239e3aa0116212`
- Production unchanged.
- QAS deployment/acceptance remains a separate gate.

## What changed

- Reworked the Growth shell into a calmer control-center layout with focused Overview, Content & Journeys, Operations and Insights navigation.
- Added a reusable Admin `<x-admin.page-help>` component. Growth is the first consumer; the component is intended for later rollout to other complex Admin workspaces.
- Help content explains each Growth page in plain language with examples where useful.
- Replaced Growth KPI cards with the shared Admin V2 stat-card component.
- Reworked engine toggles onto the shared RTL-safe Admin switch contract.
- Changed Content & Journeys from cramped multi-column tables to five focused collapsible modules: Campaigns, Automation Rules, Templates, Audience Segments and Experiments.
- Removed developer-facing CLI instructions from the Admin Operations UI and reframed demo tooling as explicit non-Production test-data tools.
- Added delivery-health summary cards and clearer Operations information hierarchy.
- Restructured Insights into Campaign Performance and Customer Health sections.
- Mobile Growth navigation now stays horizontally scrollable instead of becoming a tall stack of navigation buttons.
- Completed Arabic coverage for all translation keys currently used by all 10 Growth Blade views: 375 keys checked, 0 missing.
- Added `GrowthWorkspaceV2Test` for shared-card usage, help integration, collapsible modules, removal of CLI copy and core Arabic copy.

## V2.1 integrity and no-reload hardening

- Preserved advanced Growth settings when the Overview form submits only the four visible engine controls.
- Kept explicit hidden false values for unchecked switches so turning a visible control off remains intentional and reliable.
- Added JSON responses for safe Growth mutations while preserving the existing redirect + flash response as a progressive-enhancement fallback.
- Overview settings now save without a full-page reload and refresh the four status badges from server-confirmed values.
- Campaign, automation-rule, and experiment Enable/Disable actions now update their row state and action label in place.
- Failed-delivery Retry now updates the delivery state in place and removes the Retry action when the server reports that retry is no longer applicable.
- Added lightweight, non-disruptive success/error feedback for these actions in both English and Arabic.
- Run Engine, seed/clear test data, and create/edit/delete flows intentionally keep their normal navigation because they change broader page state or move the user into a dedicated workflow.
- Backend authorization, CSRF protection, validation, and server-side state remain authoritative; JavaScript is only progressive enhancement.
- Regression coverage now checks the async contracts, feedback surface, row bindings, and Arabic fallback message.

## Product rules reinforced by this batch

- Readability and simplicity beat showing every control at once.
- Large Admin workspaces should be sectioned or progressively disclosed rather than becoming long walls of controls.
- Help belongs inside the product and should explain business meaning, rules and examples in non-technical language.
- Arabic/English parity is part of completion, not a later translation pass.
- Shared Admin components should be reused instead of creating page-specific visual systems.
- Developer/testing details must not leak into normal Production-facing Admin copy.
- Source completion, CI, QAS acceptance and Production promotion remain separate states.

## QAS acceptance checks

1. Open all four Growth workspace pages in English and Arabic.
2. Verify RTL layout, Help modal, horizontal mobile navigation and collapsible Content sections.
3. Verify all Growth create/edit forms stay fully Arabic when locale is Arabic.
4. Confirm the four engine switches save without a full-page reload, update the header status badges from the server response, and still work through the normal redirect fallback when JavaScript is unavailable.
5. Confirm test-data tools remain unavailable in Production and are clearly labeled in QAS/staging.
6. Verify tables remain usable on small screens and no page introduces horizontal layout breakage beyond intentional table scrolling.
7. Verify campaign/rule/experiment toggles and failed-delivery Retry update in place, while existing Growth business logic and server authorization remain unchanged.

## Next Growth slice

Next, remove expensive analytics/predictive refresh work from normal Growth page GET requests. Add a measured cache/scheduled-refresh boundary so navigation stays fast as orders, customers, attribution touches, cohorts, and predictive data grow.
