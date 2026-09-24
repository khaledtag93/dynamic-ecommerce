# Storefront account access UX — 2026-09-24

Working branch: `v42-clean-baseline`

## Goal

Bring customer authentication and account-entry screens into the same storefront experience without changing authentication, order, notification, payment, or checkout business logic.

## Implemented

- Replaced the remaining default Laravel-style password reset, password confirmation, and email verification layouts with the shared storefront card/form language.
- Added clear customer-facing page titles, guidance, return paths, and consistent form styling.
- Kept password reset and confirmation endpoints, CSRF behavior, validation, and authentication logic unchanged.
- Added small-screen navigation access to My Orders and Notifications for authenticated customers.
- Added a small-screen Login link for guests; previously the compact header could leave only Create account visible.
- Clarified notification actions:
  - unread notification with a destination: View update
  - unread notification without a destination: Mark as read
  - read notification with a destination: View update
- Added English and Arabic translations for the new account-access wording.
- Added regression coverage for the storefront auth shell and small-screen account/login navigation.

## Product gap recorded

The current customer area exposes Orders and Notifications but does not yet provide a dedicated Profile / Address Book workspace. That remains a separate product feature and was not mixed into this UI-only hardening pass.

## Validation state

- Source implementation: complete for this account-access iteration.
- Automated regression coverage: updated.
- Code-head CI: **passed** on `2c182308` — Hardening CI run `36016732522`.
- Authenticated English/Arabic desktop/mobile QAS review: pending.
- Production: unchanged.

## QAS focus

1. Login and Register in English and Arabic on desktop and small mobile widths.
2. Forgot-password request screen and reset-password form.
3. Password confirmation screen when a protected action requests re-authentication.
4. Email verification screen if verification is enabled in the environment.
5. Small mobile header/navigation as guest: Login and Create account must both be reachable.
6. Small mobile header/navigation as customer: My Orders, Notifications, Cart, and Logout must remain reachable.
7. Notifications with and without action URLs: labels must match what clicking the action actually does.
8. Confirm RTL alignment, validation errors, password-manager/autofill behavior, and keyboard focus order.
