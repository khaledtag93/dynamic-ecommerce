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

## C1-B Follow-up — Logout / HTTP Reset and CSRF (2026-10-17)

- [Base checkpoint `094dc228`](https://github.com/khaledtag93/dynamic-ecommerce/commit/094dc2284b502d50016b6b1d7924bca39c6ae734), [CI #38995966181 attempt 2 GREEN](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38995966181) (995 tests/19,245 assertions). **Test-only** [CI #38996910142](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38996910142) `completed/success` at SHA `58f2a751` (997 tests/19,289 assertions).
- **Logout — IMPLEMENTED/PASS on source CI**: `Auth::routes()` POST Logout, `AuthenticatesUsers::logout` invokes guard logout + session invalidate + CSRF regenerate. `SessionGuard::logout` cycles existing Remember Me token. New HTTP test confirms original authenticated session and original remember recaller both rejected after logout, with a fresh separate session.
- **Reset — IMPLEMENTED/PASS on source CI**: real GET reset form + POST via Laravel UI `ResetsPasswords`. Password broker token is generated within isolated test DB (no external email), consumed once; new password hash set, remember token rotated, old remember recaller rejected in fresh browser, resetting browser logged in with rotated session. Replaying reset token produces error, stored password unchanged.
- **CSRF — explicit test added**: Laravel 13 `PreventRequestForgery` is normally skipped in PHPUnit via `runningUnitTests()`. One test-only middleware subclass forces normal verification. No `Sec-Fetch-Site` same-origin header is sent, so it asserts missing `_token` produces HTTP 419 and matching per-session `_token` succeeds for both Logout and Reset. Application web middleware, exemptions and configuration are unchanged. Final exact-SHA CI must independently pass before marking this test PASS.
- **PARTIAL / OPEN**: other **already-authenticated** sessions after password reset or password change are not proven revoked, since customer route group does not enforce `auth.session`; universal logout is a security/UX policy decision requiring owner approval. Browser CSRF/origin and cookie flags, EN/AR UI and provider reset-email delivery on QAS **NOT RUN**.
- No proven app bug in Logout/Reset on tested source paths. **No app-code changes** or QAS/Production writes, credentials, OTP/OAuth, real Email/SMS/WhatsApp, config/permissions/identity-policy changes. C1-A [#2](https://github.com/khaledtag93/dynamic-ecommerce/issues/2) BLOCKED; QAS issues #3/#4/#5/#6 OPEN. Phase1 NO-GO.


## Verified C1-B Slice — Two independently stored authenticated sessions (2026-10-17)

**Source-only, exact checkpoint [`af3bacef`](https://github.com/khaledtag93/dynamic-ecommerce/commit/af3baceffb36a7cc4fa019d135160accf0d86be1) and [CI #38998589662](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38998589662) directly verified `completed/success`: `1,000 PHPUnit tests passed / 19,405 assertions`.** The two new `ActiveTwoBrowserSessionLifecycleTest` cases both PASSED. Zero QAS/Production writes or provider emails; no application, permission, config, session policy or schema changes.

### Test methodology and precise observed behavior

- CI uses `SESSION_DRIVER=array` as configured in `phpunit.xml`: **one in-memory session handler storing distinct records** with separately generated browser A and browser B session IDs. Both browsers independently POST Login using correct session cookies and are verified as logged in on authenticated GET `account.index`; their different session keys each have independently persisted handler data. `viaRemember()` is **false** for both browsers. This intentionally tests *active server sessions*, not persistent Remember Me reauthentication.
- **Profile Password Change:** With A's session cookie, PATCH `account.password.update` changes password after current-password validation. Independent previously active B session still gets HTTP 200 from `account.index` and remains authenticated. A also stays authenticated.
- **Password Reset:** After two independent active sessions A/B have been created, an unrelated browser C GETs reset form and POSTs a broker-issued test reset token; password hash changes, reset token consumed, C is authenticated with its own rotated session, and previously active A/B still get authenticated account pages. The password-reset recaller-token behavior is covered by prior source regression and **must not** be conflated with active session records.
- **Test harness limitation, transparently corrected:** Laravel `Session\Store::loadSession()` uses `array_replace($this->attributes, $this->readFromHandler())`. Within a single PHPUnit app instance a second browser can inherit first browser's **in-memory session attributes** unless test code clears them before loading the different saved cookie. Both preliminary attempts [CI #38997904422](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38997904422) and [CI #38998270324](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38998270324) failed at second `GET login` (302 vs 200), **before password change or reset**; these are NOT application security failures. Final test-only correction `Auth::forgetGuards()` + `session()->flush()` clears cached guard and in-memory attributes without invoking `logout()`, `invalidate()`, or deleting either stored session record. Verified both independent sessions can be reloaded from handler after each switch. This is a test harness adjustment only, NOT a production code alteration.
- **Limits:** `array` is an isolated shared test-process handler, not a real device or production Redis/database/file session backend. No QAS browser/device test, production cookie settings, session-driver metadata, audit events or multi-tenant/staff policies have been accepted. No actual email/WhatsApp/SMS sent.

### IMPLEMENTED / PARTIAL / BLOCKED matrix

| Capability | Evidence-based status |
| --- | --- |
| Two distinct authenticated session IDs surviving a subsequent request | **IMPLEMENTED / SOURCE PASS** (both cookie-based GET + handler record reload) |
| Active B session after A changes profile password | **IMPLEMENTED current behavior / SOURCE PASS**: B remains authenticated; **policy not yet approved** |
| Active A/B sessions after unrelated C password reset | **IMPLEMENTED current behavior / SOURCE PASS**: A/B remain authenticated; C newly authenticated; **policy not yet approved** |
| Remember Me cookie replay after reset/change | **SOURCE PASS in earlier regression**: old remembered recaller denied, distinct from server sessions |
| Automatic all-other-device sign-out | **MISSING as enforced behavior / OPEN POLICY**: `auth.session` alias exists, but not applied to account route group; no approved revocation contract |
| Real QAS EN/AR 2-device, cookie flags, session-driver concurrency, transport | **BLOCKED** by C1-A preflight A0–A8 and authorized acceptance |
| Social OAuth, email/phone OTP | **MISSING/PARTIAL under existing C1-B inventory**, separate product decisions; no new integration here |

### Decision: DEC-SESSION-REVOCATION-V1 — owner approval required

[Dedicated decision issue #9](https://github.com/khaledtag93/dynamic-ecommerce/issues/9) records three options, **none authorized**:

- **A: Retain current behavior**: active devices persist after reset/change; lowest login disruption but potentially leaves stolen active sessions alive.
- **B: Balanced customer policy (candidate, not approved)**: revoke previously active **other** sessions on password reset and profile change, keep initiating browser logged in; requires effective revocation for the chosen session driver, correct token/revocation races, EN/AR messaging, audit events and real two-device acceptance. Separate privileged staff/admin/POS/Workforce policy required to avoid interrupting active operational sessions without a decision.
- **C: Strict**: revoke all sessions including current device and require login; strongest universal reauth, highest UX/operations disruption.

**Do not implement any option by default.** A session's continued authentication under current source code is not automatically a vulnerability without an agreed security/UX product policy. Source slice ends with **no app-code fix**.

### Project release gates and continuation

- C1-A [#2](https://github.com/khaledtag93/dynamic-ecommerce/issues/2) **BLOCKED**, including current QAS app/env/DB and external delivery isolation.
- Notification Center [#4](https://github.com/khaledtag93/dynamic-ecommerce/issues/4), email rendering [#3](https://github.com/khaledtag93/dynamic-ecommerce/issues/3), guest cart [#5](https://github.com/khaledtag93/dynamic-ecommerce/issues/5), password recovery privacy [#6](https://github.com/khaledtag93/dynamic-ecommerce/issues/6), logout/reset [#8](https://github.com/khaledtag93/dynamic-ecommerce/issues/8) **QAS acceptances remain OPEN**.
- **Phase1 Production remains NO-GO**. Preserve the full product scope, including pending DEC-AUTH-EM/PH/SOC and barcode/POS acceptance; no silent deferral to Phase2.
- This is a fully documented source/regression checkpoint. Final documentation commit may have a **separate** CI run; do not claim that run green until its exact SHA is directly verified.

