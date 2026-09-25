# Branding Workspace V2 — 2026-09-25

## Goal
Reduce the White-label / Branding page height and visual overload without changing settings keys, upload behavior, theme application, or the existing update endpoint.

## Summary cards
- Replaced the three hand-written summary cards with the shared Admin Stat Card component.
- Default theme, Promo banners, and Saved themes now use the same KPI language as the rest of the Admin workspace.

## Homepage CMS
The Homepage CMS tab is now split into five focused collapsible editors:
1. Visibility & order
2. Hero content
3. Merchandising sections
4. Manual featured products
5. Trust & legacy content

- All existing field names and request payload keys are unchanged.
- Visibility switches continue using the shared global RTL-safe switch contract.
- The default open section is Visibility & order; deeper content stays collapsed until needed.
- Legacy promo fields remain available but are visually isolated from current homepage merchandising settings.

## Promo banners
- Each of the three promo banner editors is now collapsible.
- Only the first editor opens by default.
- The summary shows banner number, current title (or Untitled banner), and active/inactive state.
- Upload preview, manual media path, title/subtitle, CTA, active state, and sort order remain unchanged.

## Trust blocks
- Each trust block editor is now collapsible using the same pattern.
- Only the first editor opens by default.
- The summary shows the trust-block title and active/inactive state.
- Icon, title, subtitle, active state, and sort order fields remain unchanged.

## Localization
- Added EN/AR copy for the new Homepage CMS section labels and collapsible editor fallback labels.

## Regression coverage
tests/Feature/BrandingWorkspaceV2Test.php verifies:
- shared Stat Card adoption;
- the five Homepage CMS internal sections;
- collapsible promo/trust editors;
- Arabic labels for the new workspace copy.

## Release state
- Source: v42-clean-baseline.
- CI: pending branch-head verification.
- QAS: unchanged for this slice.
- Production: unchanged.
