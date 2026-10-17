# C1-B — Account Session Security Source Audit (2026-10-17)

**Authoritative correction:** The earlier hypothesis that unchanged `remember_token` after an account password change meant that persistent Remember Me cookies remain valid was **incorrect for Laravel 13**. [Framework `SessionGuard`](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Auth/SessionGuard.php) adds a password-derived HMAC to the recaller cookie and `userFromRecaller` checks that against the current password hash, independently of the stored remember token. The earlier [test-only RED #38995215153](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38995215153) **only proved that `remember_token` was unchanged**, NOT that reauthentication succeeded with a stale cookie. Treat it as a *disproved security hypothesis*, **not a fixed vulnerability**. An additional token-rotation change was briefly proposed in commit `06002ada` but **is reverted** in the corrective checkpoint to preserve original behavior/policy. [Issue #7](https://github.com/khaledtag93/dynamic-ecommerce/issues/7) tracks the correction transparently.

**Scope:** Account Session Security (Login, Logout, Remember Me, Password Reset, Profile Password Change, session rotation, cross-device revocation). GitHub source and isolated CI feature tests ONLY; zero QAS/Production writes, external messages, secrets, config/permission/policy changes.

| Capability | Result from inspected source | Remaining acceptance |
| --- | --- | --- |
| Login, register, session rotation | **IMPLEMENTED and prior CI tested**: Laravel `AuthenticatesUsers` / `RegistersUsers`, login/register guest-session cookie regression | QAS login/registration EN/AR + browser |
| Logout | **IMPLEMENTED inherited Laravel**: `Auth::routes()`, `AuthenticatesUsers::logout`, guard clears login and persistent token and session invalidation | Live second-browser logout acceptance NOT RUN |
| Remember Me authentication | **IMPLEMENTED via Laravel 13**: recaller cookie stores user ID + remember token + password-derived hash | Corrected two-browser cookie-replay feature tests, followed by QAS |
| Account password change | **IMPLEMENTED**: current-password validation, hashing, and existing session continues; Laravel 13 recaller becomes invalid on password-hash mismatch **even if** `remember_token` does not rotate | Actual cookie replay must be confirmed by corrected feature tests; QAS source deployment/acceptance OPEN |
| Password reset | **FRAMEWORK IMPLEMENTED / PARTIAL acceptance**: `ResetPasswordController` uses `ResetsPasswords`; token expiry/reuse via previous CI 993 tests | HTTP reset current/other session lifecycle & delivery NOT RUN |
| Already-authenticated other devices | **POLICY / ACCEPTANCE GAP**: `auth.session` middleware alias exists but is not applied to account routes. A password change does **not automatically invalidate all existing server sessions** | If universal revocation is a V1 requirement, owner decision/approved implementation needed; do not quietly introduce |
| Cookie security | **SOURCE default, runtime UNVERIFIED**: HttpOnly=true, SameSite=lax, secure flag env-derived, lifetime/serialization env-derived | QAS/Production flags, HTTPS and session backend evidence OPEN |

### Test evidence

- Baseline source [CI #38994664242](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38994664242): `completed/success`, 993 tests / 19,215 assertions.
- Misframed test-only [RED #38995215153](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38995215153): one test FAIL with unchanged stored remember token. **Not a proven security exploit, and not valid reason to force token rotation.**
- Corrected feature tests in the new source checkpoint reproduce **real login with Remember Me + cookie handling**, simulate another browser with only the original recaller cookie (fresh unrelated session ID), prove successful reauthentication **before** password change, and deny that same recaller **after** password change while original browser's logged-in session remains valid. Do not claim PASS before independent GitHub CI `completed/success` on exact SHA. No external sends or QAS writes.
- If a corrected test fails, first examine cookie/session test harness, browser cookie encryption and Laravel guards; do not invent additional security fixes.

### Explicit release truth

NO-GO for Production. C1-A [#2](https://github.com/khaledtag93/dynamic-ecommerce/issues/2) BLOCKED; QAS acceptance [#3](https://github.com/khaledtag93/dynamic-ecommerce/issues/3), [#4](https://github.com/khaledtag93/dynamic-ecommerce/issues/4), [#5](https://github.com/khaledtag93/dynamic-ecommerce/issues/5), [#6](https://github.com/khaledtag93/dynamic-ecommerce/issues/6) OPEN. No OTP/OAuth changes. If no separate genuine bug is established this slice ends with **no production application-code change**.
