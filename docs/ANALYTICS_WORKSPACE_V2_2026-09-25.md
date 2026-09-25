# Analytics Workspace V2 — 2026-09-25

## Goal
Reduce the long-scroll, mixed-hierarchy analytics experience without changing calculations, reporting ranges, or business logic.

## Revenue Intelligence
- Replaced anchor-link navigation with the shared Admin section-tab workspace.
- Sections are now Performance, Decision read, Trends, Funnel & comparison, and Drilldowns.
- Empty analytics state exposes only the meaningful Performance tab.
- More diagnostics belongs to Trends instead of appearing as an unrelated block at the bottom.
- Session watchlist belongs to Drilldowns.
- Existing charts, comparisons, funnel logic, product/category/coupon concentration, and date filters are unchanged.

## Offers drilldown
- Replaced anchor navigation with Summary, Coupon charts, Management view, and Detailed table tabs.
- Converted the four offer KPI cards to the shared Admin Stat Card component.
- Kept coupon charts, leaderboard, promotion posture, and detailed table data unchanged.

## Growth Automation
- Split the long page into Growth overview, Campaigns & automation, Product signals, and Offers & coupons.
- Existing recommendations, rule readiness, product opportunity table, coupon candidates, and promotions remain unchanged.
- Specialized signal cards retain their meter visualization because that carries extra information beyond a standard KPI card.

## Localization / RTL
- Added EN/AR labels for all new workspace sections.
- Reuses the shared section-tab keyboard behavior, including RTL-aware arrow navigation.

## Regression coverage
tests/Feature/AnalyticsWorkspaceV2Test.php verifies:
- section-tab contracts for Revenue Intelligence, Offers, and Growth;
- removal of the old anchor-navigation markup from the main analytics and offers workspaces;
- shared Stat Card use for offer KPIs;
- Arabic labels for the new analytics sections.

## Release state
- Source: v42-clean-baseline.
- CI: pending branch-head verification.
- QAS: unchanged for this slice.
- Production: unchanged.
