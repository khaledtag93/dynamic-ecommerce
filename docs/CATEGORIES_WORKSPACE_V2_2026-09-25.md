# Categories Workspace V2 — 2026-09-25

## Goal
Modernize the Categories admin experience for bilingual EN/AR usage, RTL correctness, and Admin V2 consistency without changing category CRUD, translation persistence, media upload behavior, or catalog permissions.

## Categories index
- Replaced the custom page header with the shared Admin page-header component.
- Replaced repeated KPI markup with the shared Admin Stat Card component.
- Preserved the existing live/no-reload search, filters, sorting, queues, and pagination.
- Kept cleanup queues for Empty, Needs content, and Hidden categories.
- Added logical RTL alignment helpers for the actions column.

## Category editor
- Replaced anchor-scroll navigation with focused shared section tabs:
  - Basics
  - Translations
  - SEO
  - Media & visibility
- Basic Information is explicitly described as fallback content.
- English and Arabic translation content remains independently editable.
- Arabic translation panels use dir=rtl and lang=ar automatically.
- Slug inputs stay LTR even inside Arabic panels.
- Save actions and the existing server-authoritative category update/store flow are unchanged.

## Media preview
- Removed the external via.placeholder.com dependency.
- Added a local empty preview state.
- Selecting a new image replaces the empty state immediately using the existing client-side FileReader preview.

## Rendering bug fixed
- Corrected invalid IlluminateSupportStr::limit usage in the category list to the valid fully-qualified Illuminate Support Str namespace.

## Arabic / RTL
- Added missing Arabic copy for category workspace help text, translations, media/visibility, image state, save guidance, lifecycle success messages, and delete dependency errors.
- Added shared logical RTL helpers to the Admin layout so action alignment works outside the category editor too.

## Regression coverage
tests/Feature/CategoryWorkspaceV2Test.php verifies:
- shared Admin V2 page header and KPI components;
- live-list markup preservation;
- four editor tabs;
- Arabic RTL translation pane with LTR slug;
- no external image placeholder dependency;
- valid Str namespace in category results;
- Arabic copy for new workspace labels.

## Release state
- Source: v42-clean-baseline.
- CI: pending branch-head verification.
- QAS: unchanged for this slice.
- Production: unchanged.
