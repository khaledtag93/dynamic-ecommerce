# Category bilingual hardening — 2026-09-24

Working branch: `v42-clean-baseline`

## Why this slice exists

Category attributes such as `name`, `slug`, and SEO fields are translated by model accessors according to the active application locale. The admin editor was also reading those translated accessors for its canonical/base fields. In an Arabic admin session, that could display Arabic translated values inside the base fields and make an ordinary save overwrite the canonical category record.

A second issue left stale optional translations behind: clearing all Arabic translation fields did not remove the existing Arabic translation row.

## Implemented

- Canonical category fields in the admin editor now read raw stored category values instead of locale-translated accessors.
- English/Arabic translation fields remain separate from the canonical record.
- Translation panes apply locale-appropriate direction (`rtl` for Arabic, `ltr` for English) while slugs remain LTR.
- Per-field translation validation feedback is rendered inside the translation workspace.
- Clearing an optional translation removes its stale translation row instead of silently preserving old localized content.
- Existing storefront/admin translated accessors remain unchanged for normal localized display.

## Regression coverage

`tests/Feature/AdminCategoryExperienceTest.php` now covers:
- Arabic admin editing showing canonical base values separately from Arabic translation values.
- Clearing the Arabic translation removing the stored Arabic translation row.

## Validation state

- Source change: implemented.
- Branch-head CI: pending.
- Authenticated Arabic/English QAS review: pending.
- Production: unchanged.
