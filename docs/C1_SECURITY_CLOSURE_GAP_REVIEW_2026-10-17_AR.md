# Flowra — C1 Security Closure Gap Review (2026-10-17)

## Scope / baseline
- Base [0c25f8057c47991f079de735f9b0a01aa70b2e62](https://github.com/khaledtag93/dynamic-ecommerce/commit/0c25f8057c47991f079de735f9b0a01aa70b2e62).
- Baseline [CI #39039425271](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39039425271): **completed/success**, exact head SHA matched.
- This is **source-only documentation review**, not new HTTP regression and not QAS acceptance. No new code bug confirmed in this review, so no application edit. No OTP/OAuth implementation, configs, permissions, policies, secrets, messages or QAS/Production writes.
- Authoritative product inventory: [PHASE1_COMPLETE_CAPABILITY_COVERAGE_2026-10-17_AR.md](PHASE1_COMPLETE_CAPABILITY_COVERAGE_2026-10-17_AR.md) J02/J25/GAP-AUTH-01..04, [C1B_AUTH_ACCOUNT_SOURCE_AUDIT_2026-10-17_AR.md](C1B_AUTH_ACCOUNT_SOURCE_AUDIT_2026-10-17_AR.md), [C1B_ACCOUNT_SESSION_SECURITY_SOURCE_AUDIT_2026-10-17_AR.md](C1B_ACCOUNT_SESSION_SECURITY_SOURCE_AUDIT_2026-10-17_AR.md), [C1B_TWO_ACTIVE_SESSIONS_POLICY_AND_EVIDENCE_2026-10-17_AR.md](C1B_TWO_ACTIVE_SESSIONS_POLICY_AND_EVIDENCE_2026-10-17_AR.md).

## Capability / exact source evidence / missing gates

| Boundary | Status | Confirmed evidence | Not yet accepted |
| --- | --- | --- | --- |
| Credential login/logout | **IMPLEMENTED source / PARTIAL release** | `Auth::routes()`; `Auth/LoginController.php` Laravel AuthenticatesUsers; `LogoutAndResetSessionLifecycleTest.php` logout, CSRF; `AuthGuestCartSessionRotationTest.php` login cart handoff | Real QAS customer and role journeys, mobile/EN/AR, session flags, brute-force operational evidence |
| Registration | **IMPLEMENTED source / PARTIAL release** | `Auth/RegisterController.php` name, unique email, hashed min-8 password; pre-auth guest session handoff; existing `AuthGuestCartSessionRotationTest.php` | Live confirmation/abuse policy, browser/form validation, accessibility, QAS |
| Password recovery | **IMPLEMENTED source / PARTIAL release** | `Auth/ForgotPasswordController.php` normalizes unknown/throttled account responses; `PasswordRecoveryPrivacyTest.php` uses Notification::fake, proves broker throttle, 60-minute expiry and token single-use; `LogoutAndResetSessionLifecycleTest.php` tests HTTP reset and CSRF | Real-provider reset mail arrival, valid link opening on authorized QAS, rate/timing side channels and EN/AR |
| Profile email change | **IMPLEMENTED source / PARTIAL policy** | `Frontend/AccountController.php` current password required for email change; clears `email_verified_at`; `CustomerAccountTest.php` | Contract for mandatory re-verification and changes to existing sessions |
| Profile password change | **IMPLEMENTED source / PARTIAL release** | `Frontend/AccountController.php` requires current password, hashes new password; `ActiveTwoBrowserSessionLifecycleTest.php`, `TwoActiveAccountSessionsHttpTest.php` | Cross-device sign-out policy decision and QAS real-browser acceptance |
| Email verification | **PARTIAL / mandatory flow not active** | `Auth/VerificationController.php` contains Laravel VerifiesEmails signed/throttled trait; `User.php` does **not** implement MustVerifyEmail; `routes/web.php` calls `Auth::routes()` without verify option; `PasswordRecoveryPrivacyTest.php` asserts verification notice/resend routes absent | **DEC-AUTH-EM:** verify link vs numeric OTP, required for login/checkout/profile change or neither, delivery/replay/resend/expiry, bilingual QAS |
| Numeric email OTP | **BLOCKED / no completed implementation proven** | Source audit and J02/J25 inventory distinguish link-based verification from numerical OTP | Product decision; no implementation or provider use authorized in this slice |
| Verified phone / SMS OTP | **BLOCKED / no completed implementation proven** | `RegisterController.php` only name/email/password; collected checkout/address phone is contact data, not verified identity | **DEC-AUTH-PH:** account phone requirement, change/recovery, provider, consent, rate/replay controls, QAS |
| Google/Facebook | **PARTIAL foundation, operational OAuth not demonstrated** | `ConnectedIdentityService.php`, `ConnectedIdentityFoundationTest.php` verified-email matching, no silent merge and uniqueness; `composer.json` has no Socialite; checked `routes/web.php` has no callback endpoints | **DEC-AUTH-SOC:** Google/Facebook Phase1 inclusion, verified claims, state, OAuth callback, account link/unlink, collision/errors, credentials/provider sandbox |
| Existing device sessions | **IMPLEMENTED tested behavior / POLICY OPEN** | `TwoActiveAccountSessionsHttpTest.php` + `ActiveTwoBrowserSessionLifecycleTest.php`: independent persisted A/B login sessions remain HTTP 200 after reset/change; stale Remember Me recaller rejected. Source CI #39039070210 completed/success 1002 tests/19491 assertions, later documentation CI #39039425271 success | **DEC-SESSION-REVOCATION:** keep or revoke other sessions after reset/change; user messaging/UX and real cookie/session driver acceptance. Do not label current survival a bug absent contract |
| Cookie / CSRF / transport | **PARTIAL** | CSRF forced middleware test in `LogoutAndResetSessionLifecycleTest.php`, config/session.php source cookie defaults. PHPUnit normally skips CSRF elsewhere | QAS HTTPS/secure/HttpOnly/SameSite actual response, session backend/expiry, origin/CSRF browser acceptance |
| Identity abuse/privacy | **PARTIAL** | PasswordRecoveryPrivacyTest neutral known/unknown/throttle feedback; ConnectedIdentity foundation rejects silent account merger | Rate limits across register/login/reset, provider failure/recovery, privacy/misuse and QAS observational evidence |

## Non-regression review
- Source inspection yields **no separately demonstrated non-policy bug**. No speculative fix.
- Existing historical regressions (guest-cart session rotation, recovery account enumeration) have recorded source fixes and test coverage; this documentation review does **not** rerun them individually.
- The existing C1-B two-server-session success is **not** equivalent to full J02/J25 security acceptance.

## Product / Security decisions, explicit gates
1. **DEC-AUTH-EM**: email link or numeric OTP, mandatory boundaries, email changes, expiry/resend/replay/anti-enumeration and delivery behavior.
2. **DEC-AUTH-PH**: phone identity required in V1? If yes, format, verification/challenge expiry, throttling, number change/loss and opt-in/provider constraints.
3. **DEC-AUTH-SOC**: Google/Facebook actually V1? If yes, each provider's signup/login, explicit secure link/unlink and collision/recovery UX; existing connected identities are foundation only.
4. **DEC-SESSION-REVOCATION**: retain active other sessions vs revoke on reset vs revoke on both password operations vs opt-in; define initiating device semantics.
5. Abuse controls, QAS acceptance user journeys and delivery isolated sinks must be separately approved and observed; no claim of operational safety from tests alone.

## Ordered source-only continuation
- Next independent **C1 Auth Abuse & Boundary Regression**: inventory and test login/register/reset rate limits, malformed/unknown credential responses, account enumeration, privilege/role separation and session fixation without changing policy. Only if a genuine independent bug is first demonstrated may a minimal fix be committed and followed by full CI.
- Subsequently policy requirements approval and authorized QAS identity/provider acceptance can be requested separately; do not assume permission to send OTP/mail or OAuth calls.
- **C1-A Issue #2 BLOCKED; QAS acceptances #3/#4/#5/#6 OPEN; Phase1 Production NO-GO.**

## Documentation checkpoint integrity
This file is a documentation-only change; baseline exact-SHA CI is green. Any newly triggered CI on this documentation commit must be recorded as pending until an exact-head **completed/success** result is independently confirmed.
