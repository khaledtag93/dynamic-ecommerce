# Analytics & Insights hierarchy V2 — 2026-09-24

Working branch: `v42-clean-baseline`

## Goal

Reduce the length and repetition of the Analytics overview without changing the underlying analytics calculations or reporting window semantics.

## Problems addressed

The previous overview repeated the same commercial story across multiple stacked blocks before operators reached the main trends:
- operator summary
- depth signals
- pattern cards
- executive summary
- story cards
- comparison storytelling
- KPI cards
- a second chart-summary block

The page also contained a duplicated `chart-suite` anchor and analytics-specific orange styling that did not follow the configurable admin theme.

## Implemented

- Reorganized the overview into a decision-first flow:
  1. Performance KPIs
  2. Decision read
  3. Performance trends
  4. Funnel + period comparison
  5. Commercial drilldowns
- Reused the shared admin metric-card visual language for the four primary KPIs.
- Preserved the existing revenue/order/AOV calculations, comparison data, funnel data, product/category/coupon drilldowns, date ranges, data trust panel, export behavior, and session watchlist.
- Moved daily bar diagnostics into a native expandable `More diagnostics` section so the default page remains shorter.
- Removed duplicated summary/story layers and the duplicated chart anchor.
- Simplified the shared Analytics navigation to Overview / Growth / Offers.
- Replaced hard-coded orange navigation and toolbar surfaces with configurable admin theme variables.
- Kept the export summary in the DOM for CSV/print behavior while hiding the duplicate table from the normal screen view.
- Added Arabic/English copy for the new hierarchy.
- Added `AdminAnalyticsExperienceTest` to guard the simplified section structure and prevent the removed duplicate layers from returning.

## Validation state

- Source implementation: complete for this iteration.
- Automated regression coverage: added.
- Branch-head CI: pending.
- Authenticated desktop/mobile English/Arabic QAS review: pending.
- Production: unchanged.

## QAS focus

1. Compare 7d / 30d / 90d and custom ranges; all sections must stay on the same reporting window.
2. Verify the four primary KPIs against the previous version for identical values.
3. Check Revenue + Orders and AOV SVG charts at narrow and desktop widths.
4. Open More diagnostics and confirm daily values remain available.
5. Verify Product drilldown links preserve range parameters.
6. Verify Print view and CSV export still work even though the export table is hidden on screen.
7. Review English and Arabic/RTL layouts, especially range controls, comparison rows and mobile section navigation.
8. Confirm configurable Branding & Appearance colors propagate to Analytics navigation/cards without hard-coded orange surfaces.
