# C1 — Registration Abuse Readiness (SOURCE ONLY, 2026-10-17)

## Baseline and evidence integrity
- Starting documentation checkpoint [`7dca5ea56b0eeb3e2dca979e7d8719a46392d837`](https://github.com/khaledtag93/dynamic-ecommerce/commit/7dca5ea56b0eeb3e2dca979e7d8719a46392d837).
- Baseline CI [#39041352912](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39041352912): **in_progress on exact SHA when first checked**; not Green without completed/success. This CI can be superseded/cancelled by the follow-up run.
- This slice adds test-only [`tests/Feature/C1RegistrationAbuseReadinessTest.php`](../tests/Feature/C1RegistrationAbuseReadinessTest.php) at commit [`96be82d722d2150c58c9883147b4e8663eaa4d3f`](https://github.com/khaledtag93/dynamic-ecommerce/commit/96be82d722d2150c58c9883147b4e8663eaa4d3f). Full CI [#39041553741](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39041553741) pending as of first read. **Do not mark these tests PASS before completed/success on exact tested SHA.**

## Reviewed route/source behavior
| Boundary | Actual source contract | Disposition |
| --- | --- | --- |
| POST registration route | `routes/web.php` calls Laravel UI `Auth::routes()`; `RegisterController::__construct()` applies `guest`, not an explicit registration `throttle`; `app/Http/Kernel.php` web stack has CSRF but no blanket rate limiter | **PARTIAL — no explicit app route/IP/account registration throttle established.** Does *not* exclude upstream infrastructure/WAF limiting. Owner/security must first define budget and policy. |
| Repeated valid account creation | Registration controller calls `frameworkRegister`, then `User::create`; previously no independent multiple-signup same-IP runtime test | **New source regression** uses two distinct synthetic guest browsers from the same TEST-NET IP with distinct email addresses; proves bounded current behavior, not unlimited abuse capacity. |
| Email uniqueness | `RegisterController::validator()` uses `unique:users`; `users.email` has DB unique key in migration; duplicate validation visibly discloses already-used email to registration request | **IMPLEMENTED/source tested previously**; disclosure behavior may be standard UX but explicit privacy/abuse acceptance still needed. Do not silently change error response. |
| Validation | Name required/max255, email required/valid/max255/unique, password min8/confirmed; controller creates only name/email/hashed password | **IMPLEMENTED/source**; new negative tests cover blank name, invalid email, short or mismatched password and verify zero new accounts. |
| Session fixation | Laravel RegistersUsers rotates authenticated session; `AuthGuestCartSessionRotationTest.php` and `C1AuthAbuseBoundaryTest.php` already verify guest ID rotates | **IMPLEMENTED/prior CI**; new two-guest test separately checks pre/post IDs. |
| CSRF | `VerifyCsrfToken` is part of web middleware. `C1AuthAbuseBoundaryTest.php` forces real forgery middleware in PHPUnit and tests 419 without CSRF, success with token | **IMPLEMENTED/prior CI**; ordinary PHPUnit default CSRF bypass acknowledged, not confused with browser acceptance. |
| Privilege injection | `RegisterController::create()` whitelists name/email/password; `C1AuthAbuseBoundaryTest.php` verifies injected admin role and verified-at are ignored | **IMPLEMENTED/prior CI**; full role matrix/QAS remains OPEN. |
| Provider traffic / abuse | No outbound delivery or provider calls in this slice; mail/notification fakes in added tests | **NOT RUN**: reverse proxy protections, distributed IP reputation, real load/scale, bot detection, device fingerprinting and provider sinks. |

## Test design and interpretation
1. Inspect the generated POST register route for an explicit `throttle:` middleware label; absence is only an app-level code contract, **not a claim that no upstream rate limiting exists**.
2. Exercise two separate guest sessions, each GET registration then POST registration, both from synthetic `192.0.2.44`, using separate original session cookies and distinct email addresses; assert account creation and authenticated session rotation, no actual notifications/mail. Two accounts do **not** prove unlimited creation, merely that no threshold was hit for these two.
3. Verify duplicate email, blank name, malformed email, short password and confirmation mismatch reject creation; account count unchanged. A duplicate error can reveal pre-existing address; classify it as **accepted existing behavior pending policy** rather than inventing an incident.
4. Reuse existing test evidence for real-forgery middleware CSRF, role injection and guest-cart/session rotation. Tests are HTTP simulated in GitHub CI, not a real-browser QAS environment.

## Policy choices requiring explicit Product/Security approval before any implementation
- **DEC-REG-RATE:** maximum attempts/new accounts per IP, per subnet, per identity, time window; thresholds and release UX; errors vs silent slowdown; reverse proxy trusted client-IP behavior; exceptions for NAT/corporate/shared networks; validation failures vs successes counting; attacker evasion and monitoring; concurrency/distributed-backend requirement.
- **DEC-REG-ENUM:** whether duplicate email response should reveal account existence; consistency with recovery privacy, support and customer feedback.
- **DEC-REG-VERIFY:** whether email/phone confirmation is required before use and spam/abuse prevention; depends on DEC-AUTH-EM/PH, still open.
- These are **policy gaps, not independently proven non-policy application bugs**. No policy change authorized in this slice.

## Release truth and checkpoint
- No new non-policy bug established during source review; therefore **no app fix**.
- No QAS/Production writes, external Email/SMS/WhatsApp, OTP/OAuth, Config, Permissions, Secrets, Policies changes.
- C1-A [#2](https://github.com/khaledtag93/dynamic-ecommerce/issues/2) **BLOCKED**; QAS issues [#3](https://github.com/khaledtag93/dynamic-ecommerce/issues/3), [#4](https://github.com/khaledtag93/dynamic-ecommerce/issues/4), [#5](https://github.com/khaledtag93/dynamic-ecommerce/issues/5), [#6](https://github.com/khaledtag93/dynamic-ecommerce/issues/6) **OPEN**. Phase1/Production **NO-GO**.
- **Next independent slice**: scoped signup email enumeration + concurrent registration collision/integrity or other outstanding source-only account security boundary, with actual regression. Do not implement rate policy without contract; obtain exact-head full CI on tests and documentation commit.

## Verified full-suite result (supersedes earlier pending run status)
- Full [CI #39041666884](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39041666884) is **completed/success on exact tested SHA `18cd82a8fe221e7d65738135ce50eecc2aedef85`**, 1,010 tests / 19,609 assertions. `C1RegistrationAbuseReadinessTest` contains **3 PASS** cases (route-throttle snapshot; two independent guest signups from same synthetic IP; duplicate/invalid registration rejection).
- Intermediate test-only [#39041553741](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39041553741) was **cancelled** due to branch concurrency after documentation was pushed. This is not a failed test.
- Prior baseline [#39041352912](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39041352912) ultimately **completed/cancelled**, not GREEN. The successful exact-SHA suite above is the authoritative execution evidence.
- This documentation/status pointer commit has its own fresh CI gate: **do not label its SHA GREEN until independently completed/success**.
