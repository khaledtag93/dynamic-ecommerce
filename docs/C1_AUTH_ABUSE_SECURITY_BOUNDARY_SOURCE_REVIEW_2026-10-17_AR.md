# C1 — Auth Abuse & Security Boundary Source-only Slice (2026-10-17)

## Baseline and CI truth
- Baseline checkpoint `c084cdd666528cdcc154d5a771544d049b3ce1d7` CI [#39039912460](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39039912460) is **completed/cancelled**, NOT green.
- Test commit [`35ee840c04b226c7513881a8448823e0fb135749`](https://github.com/khaledtag93/dynamic-ecommerce/commit/35ee840c04b226c7513881a8448823e0fb135749), full CI [#39040124781](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39040124781) **completed/success at exact SHA**.
- Tests: `tests/Feature/C1AuthAbuseBoundaryTest.php` (five source-only tests). No application-code edits or new auth/security/identity policies.

## Reviewed source boundaries
- **Login**: `app/Http/Controllers/Auth/LoginController.php` uses Laravel `AuthenticatesUsers`, Laravel's `ThrottlesLogins`; existing `AuthGuestCartSessionRotationTest.php`. New test checks five wrong attempts lock the next valid attempt, then login after the test clock advances, and rotates pre-auth session ID. **IMPLEMENTED / SOURCE PASS**, actual multi-IP/distributed-limiter behavior and QAS still OPEN.
- **Register**: `RegisterController.php` validates required name, unique email, confirmed password >= 8, creates a `User` with selected fields and hashed password. New test submits forbidden `role_as=1` / `email_verified_at` and verifies neither is adopted; customer gets no staff role or admin-dashboard access. **IMPLEMENTED / SOURCE PASS**. Duplicate email surfaces a validation error; this can be expected on a registration form, and is **not** classified as an enumeration bug without an approved privacy/UX policy. Abuse/rate-limiting for registration is **not independently demonstrated**, a SECURITY/PRODUCT rate policy gate, not a bug assumed from absence.
- **Password Reset**: `ForgotPasswordController.php` normalizes unknown/throttled account feedback; prior `PasswordRecoveryPrivacyTest.php` tests one fake delivery to known account, no delivery for unknown, broker throttling, expiry and single-use; `LogoutAndResetSessionLifecycleTest.php` tests CSRF, reset and logout. **IMPLEMENTED / SOURCE PASS based on prior test baseline**. Actual delivery and side-channel timing/rate risks OPEN.
- **CSRF**: `app/Http/Kernel.php` web middleware includes `VerifyCsrfToken`; PHPUnit normally skips CSRF. New test-specific middleware binding requires actual CSRF checks: missing tokens -> HTTP 419; matching per-session token allows Login/Register. Prior test covers Logout/Reset. **IMPLEMENTED / SOURCE PASS**, real-browser QAS not accepted.
- **Customer vs Staff boundaries**: `routes/web.php` customer account guarded by `auth`; admin under `auth`, `isAdmin`, and granular `permission`; `app/Models/User.php` role checks. New tests prove guest denied, customer accepted on account, customer cannot access admin and injected role rejected. **IMPLEMENTED / SOURCE PASS** for tested paths; complete permission matrix remains separate release gate.
- **Session fixation**: pre- to post-login and pre- to post-registration session IDs rotate; prior two-active-session regression proves existing device sessions survive password changes while old recaller replay fails. **IMPLEMENTED / SOURCE PASS**. All-device sign-out **POLICY OPEN** (not a bug).
- **Identity verification**: email verification and numeric email/phone OTP remain PARTIAL/BLOCKED; Google/Facebook operational OAuth remains PARTIAL foundation. Decisions DEC-AUTH-EM/PH/SOC and DEC-SESSION-REVOCATION remain OPEN, untouched.

## Bug determination
The inspected source plus successful bounded regression tests **did not prove a new non-policy defect**. Therefore this slice makes no app-code fix. Avoid turning unspecified registration throttling and all-device revocation requirements into unapproved security policy changes.

## Release gate
- Source-only; isolated CI database, no QAS/Production writes; no actual email, SMS, WhatsApp, OTP, OAuth, config, permissions, secrets or policy changes.
- C1-A [#2](https://github.com/khaledtag93/dynamic-ecommerce/issues/2) BLOCKED. QAS acceptances #3/#4/#5/#6 OPEN. Phase1 / Production **NO-GO**.
- Next: choose one independent test-only slice for registration abuse policy evidence (rate contract first) **or** authorized QAS account acceptance after C1-A preflight; do not implement unapproved controls.
- This documentation commit's CI must be verified separately at its own exact SHA before called GREEN.
