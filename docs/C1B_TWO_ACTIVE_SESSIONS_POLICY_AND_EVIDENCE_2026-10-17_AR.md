# C1-B — Two active server sessions: source-only evidence and decision gate (2026-10-17)

## Scope and baseline
Base: `9c2ebb2277e8eac112f32421425a5b05c17a58a5`, user-reported CI #38997375695 GREEN (998 tests / 19,310 assertions). This record does **not** claim a new CI run or completed two-device test. Source-only inspection; no QAS/Production writes, messages, OTP/OAuth, config, policy, secrets, or permissions changes.

## What is proved versus unproved
- **IMPLEMENTED (source):** customer routes in `routes/web.php` are under `auth`, not `auth.session`. `AccountController::updatePassword` validates current password and confirmation, then updates the password hash; it does not directly enumerate/revoke all server sessions. The reset route uses Laravel UI reset handling; existing source tests confirm reset-token single use, resetting-browser login and old *recaller* rejection.
- **IMPLEMENTED / previously tested:** Laravel 13 Remember Me recaller password-derived HMAC check; the stale recaller is not interchangeable with an existing authenticated server-side session. `AccountRememberCookieAfterPasswordChangeTest` and `LogoutAndResetSessionLifecycleTest` cover *recaller* behavior; their isolated pseudo-browser transitions do **not** establish two simultaneously live, independently persisted server sessions.
- **PARTIAL (inference, not runtime acceptance):** Existing active authenticated device B may continue after device A changes the password, and similarly after a reset, because the account routes do not enforce password-hash-aware session checking. This is not labeled a bug without an approved cross-device revocation contract.
- **BLOCKED/NOT TESTED in this independent slice:** actual two-device HTTP session-store persistence after reset/change; session A and B *both independently active before* mutation; correct encrypted cookie replay with separate cookie jars; fresh unrelated third browser with old recaller cookie; CI of new tests; QAS browser/cookie/security flags; external reset delivery.

## Required isolated HTTP regression design
Use one test-only app DB plus a durable per-test session store with real, distinct server session IDs and browser-specific cookie jars A/B. Run actual GET login forms then POST login requests with each browser's own CSRF token and cookie values, yielding two authenticated server session IDs. Verify both via GET `account.index` **before** mutation; assert distinct session IDs and independent saved session records, and do not call guard `logout()`, `session()->invalidate()` or `actingAs()` to impersonate another browser. The Laravel testing client's guard singleton and request cookie persistence require careful reset between A/B requests; guard `forgetUser()` alone is not proof of persistent independent sessions.

Run two independent tests:
1. A and B logged in. A PATCH `account.password.update` with its session/CSRF; A GET account, B GET account using **original** B session cookie, then a fresh C browser attempts using B's old encrypted Remember Me recaller only. Capture HTTP code, authenticated user, `viaRemember()`, session IDs, store record existence and cookies. No behavioral expectation of B invalidation unless policy approved.
2. A and B logged in. Fresh reset browser R issues token through password broker entirely within the test DB, GET reset, POST valid token with R's own session/CSRF, then A GET and B GET with original authenticated cookies; fresh C attempts old recaller only. Assert token single-use, hash change, correct independent cookies, and document observed survival/revocation without changing policy.

If app-session result differs from the policy chosen later, make a dedicated policy implementation slice. Only a reproducible independent data/authentication defect outside this policy boundary warrants minimal source fix + regression + full CI green.

## Product/Security decision required — all-device sign-out
| Option | Security | UX / operational effect |
| --- | --- | --- |
| A. Keep other active server sessions until expiry/manual logout | Lesser disruption, but a compromised already-authenticated device may retain access | Other devices do not unexpectedly sign out; reset is **not** a universal kill switch |
| B. Revoke other sessions on **password reset only** | Good account-recovery response | Existing devices prompt login; requires durable cross-device mechanism, driver compatibility and notification language |
| C. Revoke other sessions on **both reset and profile password change** | Strongest consistent password-rotation boundary | Maximum re-login friction; remember-cookie handling and current-device preservation must be specified |
| D. Optional checkbox: sign out other devices | Offers user agency, conditional protection | Adds UX surface and potential ambiguity; defaults and recovery override must be specified |

**Decision: OPEN — requires Product + Security owner approval.** Define which flows, whether the initiating browser remains logged in, whether current sessions and recaller tokens are both revoked, session-backend support, administrator impersonation considerations, observability and bilingual copy. Do not silently enable `auth.session` or alter guards.

## Release / checkpoint
Documentation-only checkpoint; **no new two-active-session PASS**. C1-A issue #2 remains BLOCKED; QAS issues #3/#4/#5/#6 remain OPEN, Phase 1 / Production NO-GO. Next independent slice: build reliable separate-client HTTP harness, run CI on exact commit SHA, publish precise observed behavior; keep policy unchanged.
