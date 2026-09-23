# Branding & Appearance V2 — implementation note

Date: 2026-09-24  
Working branch: `v42-clean-baseline`

## Scope

This batch starts the Phase 1 UI/UX foundation from the product roadmap. It improves the existing Branding & Appearance workspace without replacing the current settings model or breaking existing custom themes, media uploads, homepage CMS, promo banners, trust blocks, or admin branding fields.

## Implemented

### Professional default direction
- Added built-in preset `professional_commerce`.
- Palette uses a neutral commerce base: blue primary, navy secondary, cyan accent, clean white surfaces, cool gray borders/backgrounds.
- New-install/default fallbacks in the settings service, storefront layout, admin layout, and Branding workspace now use the professional palette.
- Existing saved merchant settings are not overwritten by these fallback changes.

### Theme selection UX
- Theme presets are now shown as visual cards with palette previews.
- The current preset remains available in a normal select for accessibility and fallback behavior.
- Added a reapply action so a merchant can restore the selected preset after manually changing individual colors.
- Custom themes remain supported and are identified separately in the visual picker.

### Color editing
- Core controls remain immediately visible: primary, secondary, accent, background, card radius, and badge style.
- Detailed surface/table/hover/button colors moved into an Advanced palette disclosure.
- Badge style changed from arbitrary free text to the supported values: `soft`, `pill`, and `outline`.

### Preview and save state
- Replaced the abstract preview with more representative admin and storefront mockups.
- Preview responds to the selected/editing colors and radius before save.
- Added a visible clean/unsaved state indicator.
- Added browser navigation protection when Branding has unsaved changes.

### Design-system correctness
- Admin success, warning, and danger states are now semantic colors rather than derived from merchant brand colors.
- This prevents white-label themes from turning warnings/errors into unrelated brand colors.
- Storefront cart badge fallback now follows the active brand primary instead of a hardcoded orange.

### Homepage setting bug fixes
During the Branding review, three existing toggle-handling gaps were found and fixed:
- `show_home_featured_categories`
- `show_home_manual_featured_products`
- `show_home_trust_blocks`

The first two were not normalized with the other checkbox fields, and the trust-block visibility field was also missing from validation. They now save consistently with the other homepage toggles.

### Bilingual additions
New Branding V2 interface strings were registered in English and translated to Arabic.

## Automated coverage

Added `tests/Feature/BrandingSettingsExperienceTest.php` covering:
- access to the Branding workspace;
- presence of the Professional Commerce controls;
- saving the professional preset;
- expected professional palette values;
- normalization of the repaired homepage toggles.

The code/UI portion at commit `3726686d` passed Hardening CI run `35924238577`. Later commits add tests, translation, and documentation; the final branch-head CI result must be recorded before QAS promotion.

## QAS acceptance checklist

Test the exact final application commit in both Arabic RTL and English LTR, desktop and narrow mobile width.

1. Open Branding & Appearance and verify preset cards are readable, keyboard reachable, and do not overflow.
2. Select Professional Commerce and verify the preview updates immediately.
3. Select every existing built-in theme and at least one custom theme; verify no field becomes invalid.
4. Change core colors, open Advanced palette, and verify both picker and hex input stay synchronized.
5. Reapply the selected preset and verify manually changed palette values are restored.
6. Change radius and badge style and verify preview behavior.
7. Verify the unsaved-state indicator changes after edits and returns to clean after successful save.
8. Attempt to leave with unsaved changes and verify the browser warning appears.
9. Upload/store logo, admin logo, favicon, hero banner, and one promo banner; confirm previews and save behavior remain unchanged.
10. Toggle Featured categories, Manual featured, and Trust blocks off and on; reload after each save and verify the state persists.
11. Confirm existing homepage CMS, promo banners, trust blocks, and admin branding sections still save without regression.
12. Confirm admin success/warning/danger UI remains semantically green/amber/red under multiple merchant themes.

## Deployment state

- Source: implemented on `v42-clean-baseline`.
- CI: final head verification pending at the time this note was created.
- QAS: not deployed/visually verified yet.
- Production: not deployed.
- Database migration: none.
- Production data/settings: not changed by this source batch.

## Next UI/UX slice

After QAS verification and any Branding regressions:
1. product publish-readiness and product-form polish;
2. order-detail action hierarchy and timeline;
3. remaining admin list/form consistency;
4. storefront content/demo cleanup and customer funnel polish.
