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

## Professional theme and preview polish
- Expanded the built-in professional catalog with Royal Navy, Emerald Studio, and Plum Editorial while preserving every existing preset and saved custom theme.
- Added Arabic labels for the new built-in themes to prevent mixed-language preset names in Arabic mode.
- The customer live preview now reflects primary, secondary, accent, background, soft, border, button text, card radius, and badge style.
- Badge previews visibly distinguish Soft, Pill, and Outline.
- Media controls can be compacted to reduce visual weight without removing upload/manual-path capabilities.
- Removed the desktop nested-scroll pattern from the media/preview rail; the media card flows naturally and only Live Preview remains sticky on wide screens.
- Added regression coverage for the expanded preset catalog, saving a complete preset contract, Arabic labels, media controls, accent preview, badge preview, and sticky-preview behavior.

- Translation completeness is now regression-tested against every literal `__()` key used by the Branding workspace for both English and Arabic catalogs, preventing future mixed-language regressions.

## Release state
- Source: v42-clean-baseline.
- CI: pending branch-head verification.
- QAS: unchanged for this slice.
- Production: unchanged.
