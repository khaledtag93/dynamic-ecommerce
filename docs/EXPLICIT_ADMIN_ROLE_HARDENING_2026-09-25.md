# Explicit Admin Role Hardening — 2026-09-25

## Why this is P0
Legacy authorization treated any `role_as = 1` user with no assigned staff role as Super Admin. That made absence of role data equivalent to full back-office access.

## Change
- Existing legacy admin accounts without a role are migrated to an explicit `super_admin` assignment before the fallback is removed.
- The transition migration creates the system Super Admin role if an older database does not already contain it.
- Existing permission rows are attached to Super Admin when the permission pivot is present.
- `User::isSuperAdmin()` now requires the explicit `super_admin` role.
- `User::hasRole('super_admin')` no longer grants access because the user has no roles.
- `User::hasPermission()` returns false for non-admin users and resolves admin permissions only from assigned roles, except explicit Super Admin which retains all permissions.
- `AdminMiddleware` requires both the legacy admin flag and at least one explicit staff role.
- A roleless admin is surfaced as `Unassigned admin` instead of being presented as Super Admin.

## Deployment safety
The transition migration is intentionally irreversible in `down()`. Removing explicit role assignments during rollback could lock legitimate admins out.

Expected deployment order remains:
1. enter maintenance mode;
2. deploy code;
3. run migrations;
4. rebuild caches;
5. health/smoke check;
6. exit maintenance mode.

## Regression coverage
`tests/Feature/ExplicitAdminRoleHardeningTest.php` verifies:
- a roleless legacy admin is not Super Admin and cannot enter the admin dashboard;
- explicit Super Admin retains full access;
- the transition migration upgrades an existing roleless legacy admin;
- a normal staff role receives only its assigned permissions.

## Release state
- Source: `v42-clean-baseline`.
- QAS: not yet claimed for this slice.
- Production: unchanged.
- Next security/UI step: redesign Roles & Permissions without changing the new explicit-role authorization contract.
