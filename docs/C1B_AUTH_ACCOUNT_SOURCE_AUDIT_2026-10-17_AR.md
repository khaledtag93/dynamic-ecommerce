# C1-B — Source Audit: Authentication, Verification & Connected Identity (2026-10-17)

## Decision summary

**Phase 1 Production: NO-GO.** This is a SOURCE-only audit + one bounded correction. No QAS/Production stateful actions, no real email, SMS, WhatsApp, social provider calls, source/config/permission/identity-policy changes outside the explicit fix. Previous source HEAD: `cabc2238b97de6b9db1dfdc2df79ef2d624a5561` (CI [#38992730518](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38992730518) `completed/success`: 987 tests / 19,172 assertions).

**Inventory contract:** [comprehensive Phase1 coverage](PHASE1_COMPLETE_CAPABILITY_COVERAGE_2026-10-17_AR.md) J02/J25, DEC-AUTH-SOC/EM/PH. Observed PHP files, views, migrations, tests, and `composer.json`; these are NOT live-QAS acceptance.

| Capability | Source status | Exact evidence | Remains open |
| --- | --- | --- | --- |
| Email/password customer login and logout | **IMPLEMENTED (source)** | `Auth::routes()` in `routes/web.php`; `Auth/LoginController` uses Laravel `AuthenticatesUsers`; `auth/login.blade.php`; web session/CSRF; existing account tests | Authenticated QAS E2E, throttling, role + session/remember-me, locale/mobile; bounded guest-cart defect below |
| Email/password registration | **IMPLEMENTED (source)** | `Auth/RegisterController` validates unique email + minimum 8 password and hashes; `auth/register.blade.php` | QAS journey, rate abuse controls, email verification product contract; guest-cart defect below |
| Forgot/reset password | **SOURCE FOUNDATION / PARTIAL acceptance** | `ForgotPasswordController`, `ResetPasswordController`, reset views, `config/auth.php` reset expiry/throttle | No isolated-provider email reset delivery/link/token/expiry/replay observed; do not assert end-to-end PASS |
| Profile / change email / change password | **IMPLEMENTED (source)** | `Frontend/AccountController` requires `current_password` for email change, clears `email_verified_at`; password update also checks current password; `CustomerAccountTest` | QAS ownership and multi-session behavior; delivery and re-verification policy |
| Email address verification | **PARTIAL, not numeric OTP** | `Auth/VerificationController` includes `VerifiesEmails` trait, signed/throttled handlers; `auth/verify.blade.php` explicitly explains **verification link**; `User` has `email_verified_at` field/cast | `Auth::routes()` has no explicit `['verify' => true]`; `User` does **not** implement `MustVerifyEmail`. Neither enabled runtime verify route nor enforcement is proven. Decision **DEC-AUTH-EM OPEN** (link vs OTP + mandatory gate scope). **Do NOT silently enable email sends or `verified` middleware.** |
| Phone collection | **CONTACT DATA ONLY; no verified identity** | Checkout/shipping/customer addresses accept phone strings, but `RegisterController`/registration UI have only name/email/password | **MISSING phone-OTP account verification**: no verified-phone lifecycle, challenge expiry/attempt rate limits, resend/recovery/provider observed. Decision **DEC-AUTH-PH OPEN** |
| Google/Facebook identities | **PARTIAL FOUNDATION ONLY** | `ConnectedIdentity`, `ConnectedIdentityService`, `connected_identities` migration with provider/user uniqueness, `ConnectedIdentityFoundationTest` verifies verified-mail matching, no silent merge, existing mapping resolution | **MISSING exposed OAuth flow:** no provider redirect/callback or account Link/Unlink UI found in inspected source; no `laravel/socialite` in `composer.json`. Provider credentials/state/callback QAS acceptance unverified. Product scope **DEC-AUTH-SOC OPEN** |
| Authenticated-account cart merge | **BUG CONFIRMED before fix, SOURCE FIX candidate** | Pre-fix controller hooks call `CartService::mergeGuestCartIntoUserCart($user->id)` after guard/session rotation, which looks up **new** session ID instead of old guest `CartItem::session_id` | QAS acceptance pending; tests/CI proofs below |

### Exactly one corrected business-critical bug — cart ownership after session rotation

**Expected:** Guest adds products, logs in/registers, Laravel rotates session ID (session fixation defense), previous guest CartItem is transferred to authenticated `user_id`; no other guest cart is merged.

**Observed RED:** test-only SHA `3e236827a425769661742c9f5de118db518beaaf`, [CI #38993158677](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38993158677) **completed/failure**, exactly two **new** `AuthGuestCartSessionRotationTest` feature tests failed at `assertDatabaseHas('cart_items', ['user_id'=>..., 'session_id'=>null, 'quantity'=>...])` after successful login and registration. The other **987 tests passed** (19,186 assertions). The tests also assert Laravel DID rotate the session ID. This is a real, repeatable lost-cart defect, not a speculative source smell.

**Minimal fix:** `LoginController::login` and `RegisterController::register` wrap their Laravel trait handlers to capture **server session ID** in `Request::attributes` *before* authentication. Post-login `authenticated` and post-register `registered` pass the captured ID to existing `CartService::mergeGuestCartIntoUserCart(int $userId, ?string $guestSessionId = null)`; no session rotation changes, no input/public session ID accepted and the previous method remains backward-compatible. No auth policy, password rules, OAuth behavior, checkout settings or QAS state altered.

**GREEN gate:** source fix's SHA and full completed/success CI, test count and real link MUST be recorded with a later issue update; **do not claim fixed or GREEN from code alone**. Separate QAS guest-to-customer acceptance remains OPEN.

### Remaining gates / priority

- **C1-A** remains BLOCKED: [#2](https://github.com/khaledtag93/dynamic-ecommerce/issues/2) requires authorized read-only runtime app/env/DB identity, COD/pickup and email/WhatsApp/automation external sink isolation and exact order/stock reconciliation before any QAS writes.
- **Notification Center Issue #4** source CI GREEN, real QAS visual EN/AR responsive/accessibility acceptance OPEN.
- **OrderUpdateMail Issue #3** source regression FIXED/CI GREEN, QAS mail-sink E2E OPEN; do not retry historical emails.
- **J02/J25** login, register, reset, verified identity, phone and OAuth need separate test plans; **DEC-AUTH-SOC/EM/PH** product requirements must be explicitly decided before adding providers or mandatory verification.
- **NO-GO** until C1–C5 acceptance/production gates, not merely the number of passing source tests.

Keep this document and `CURRENT_PHASE.md` / `PROJECT_MASTER_STATUS.md` as cross-chat continuation pointers; code/CI evidence is tied to exact SHA.
