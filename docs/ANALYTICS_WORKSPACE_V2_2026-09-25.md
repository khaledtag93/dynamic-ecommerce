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

## Product drilldown
- Added Product Drilldown to the same focused workspace model with Operator summary, Performance, Trends, and Variant mix sections.
- Replaced legacy hard-coded white/orange/slate surfaces with shared Admin theme tokens.
- Mobile navigation and chart/card spacing now follow the same responsive behavior as the rest of Analytics.
- Product Drilldown is included in the bilingual catalog completeness guard.

## Shared workspace hardening
- Analytics section selection is persisted in the URL with `?section=...` for refresh/share/back-navigation continuity while server validation errors retain priority.
- Overview, Growth, Offers, and Product Drilldown all opt into section-history persistence.
- Analytics top navigation becomes horizontally scrollable/snap-aligned on narrow screens instead of wrapping into a tall navigation block.
- Report actions stack cleanly on mobile and Copy report link now exposes accessible success/failure feedback instead of failing silently.
- Data-trust semantic surfaces use Admin success/warning/danger/theme tokens rather than fixed colors.
- Range/filter controls and wide chart frames have explicit narrow-screen behavior.

## Localization / RTL
- Added EN/AR labels for all new workspace sections.
- Literal translation-key coverage now spans Overview, Growth, Offers, and Product Drilldown with an automated EN/AR completeness test.
- Reuses the shared section-tab keyboard behavior, including RTL-aware arrow navigation.

## Regression coverage
tests/Feature/AnalyticsWorkspaceV2Test.php verifies:
- section-tab contracts for Revenue Intelligence, Offers, Growth, and Product Drilldown;
- URL section persistence across all four analytics workspaces;
- mobile filter/navigation and shared-shell contracts;
- theme-token consistency for shared trust surfaces and Product Drilldown;
- complete EN/AR literal-key catalogs for all four analytics views;
- removal of the old anchor-navigation markup from the main analytics and offers workspaces;
- shared Stat Card use for offer KPIs;
- Arabic labels for the new analytics sections.

## Release state
- Source: v42-clean-baseline.
- CI: passed on branch head `d43c359` (Hardening CI run 36187091347).
- QAS: unchanged for this slice.
- Production: unchanged.
