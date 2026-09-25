# Roles & Permissions V2 — 2026-09-25

## Goal
Replace the long mixed-language permissions page with a focused access-control workspace while preserving the explicit-role authorization contract introduced by the P0 admin hardening.

## Information architecture
The page is now split into four focused workspaces using the shared accessible Admin section tabs:

1. **Overview**
   - staff/system/custom-role/permission summary cards;
   - compact built-in role summaries;
   - clear explanation that the admin flag and assigned role have different responsibilities;
   - warning when an admin account has no explicit role.

2. **Staff assignments**
   - one staff account per compact assignment card;
   - explicit current role;
   - one role selector and save action;
   - live staff search by name, email, or assigned role with a visible-result counter;
   - unassigned admins are clearly flagged instead of being treated as Super Admin.

3. **Roles**
   - focused custom-role creation;
   - permissions grouped by access area instead of one flat checkbox wall;
   - Select all / Clear all helpers speed up large role definitions without bypassing server validation;
   - existing custom roles collapsed by default and expanded only for editing;
   - delete actions use the shared in-app confirmation flow;
   - custom permission creation is separated from normal role editing.

4. **Permission matrix**
   - searchable without full-page reload;
   - search matches permission name, description, group, slug, and role;
   - live visible-result count;
   - unmatched groups collapse from the result set;
   - matching groups expand automatically;
   - permission slugs remain visible for technical traceability.

## Localization / RTL
- System role names and descriptions are translated in EN/AR.
- Built-in permission names and descriptions are translated in EN/AR.
- Access-area group labels and all new workspace copy are translated.
- Existing shared section-tab keyboard behavior respects RTL direction.
- User-created custom role/permission labels remain user-owned content and are not automatically translated.

## Authorization behavior
No relaxation of authorization was introduced:
- page/actions still require the existing route permission boundary;
- PermissionController still requires explicit Super Admin for sensitive access-control mutations;
- system roles remain protected;
- assigned custom roles cannot be deleted;
- admin role assignment remains server-authoritative;
- roleless admin accounts receive no implicit permissions.

## Regression coverage
tests/Feature/PermissionsWorkspaceV2Test.php verifies:
- sectioned workspace, searchable matrix, live staff-search markup, and bulk permission controls;
- Arabic role/permission copy;
- absence of legacy fallback wording;
- explicit warning/presentation for unassigned admin accounts.

## Related security prerequisite
See EXPLICIT_ADMIN_ROLE_HARDENING_2026-09-25.md.

## Release state
- Source: v42-clean-baseline.
- CI: Green through UX hardening commit `9a50aa3` (Hardening CI run `36178503073`).
- QAS: unchanged until the next consolidated deployment.
- Production: unchanged.
