# Flowra — Reset-only QAS Mail Sandbox Alternative (SOURCE DESIGN ONLY, 2026-10-17)

## Why not globally enable SMTP
QAS currently uses Laravel mail=array and From identity @example.com (last verified), which cannot deliver a real Password Reset inbox message. Existing source directly uses Laravel Mail for order status updates, admin automation retry/fallback, and Growth SMTP mail. Scheduler, queue and independent Meta WhatsApp integration run separately. Effective QAS notification/automation/Growth flags and pending job class remain UNKNOWN, so enabling global smtp would expand the outbound-data surface.

## Proposed narrowly scoped architecture — NOT IMPLEMENTED
1. Keep global mail.default=array in QAS and Production unchanged.
2. **Future approved source/config slice only:** declare a separate named mailer called qas_reset_capture backed by **Mailtrap Email Testing / Sandbox**, NOT Email Sending, with protected QAS-only runtime credentials and a legitimate approved QAS sender identity. No secrets in Git, documentation or chat.
3. For synthetic, approved QAS accounts only, use Laravel password-reset notification seam: User::sendPasswordResetNotification($token) dispatches an appropriately scoped subclass of Illuminate/Auth/Notifications/ResetPassword; its toMail() uses MailMessage::mailer('qas_reset_capture'). Laravel 13 explicitly supports per-notification mailers; [official docs](https://laravel.com/framework/docs/13.x/notifications#customizing-the-mailer). This is a **candidate design**, not tested or coded.
4. **Fail closed:** if environment is not exact staging QAS, synthetic-recipient allowlist check fails, or named mailer is absent, do not use external transport or fall back to production SMTP. Decide a safe no-send/default-array path without leaking whether account exists. Do not embed API credentials or recipient addresses in code.
5. Preserve Password Broker behavior (60-minute token expiry, 60-second throttle), generic Forgot Password response (no enumeration) and reset/token replay protection.
6. Other flows (orders, admin retry/fallback, Growth mail) stay on global array. Named mailer should be unreachable by manual admin retries, queued order mail, arbitrary notification routing, or customer inputs. WhatsApp/SMS/providers remain separately BLOCKED until proven isolated.
7. Capture inbox retention, token privacy, disable forwarding/relay, use synthetic accounts only; verify capture URL is QAS HTTPS and never Production. Explicit owner permission required before any QAS write or SMTP connection/send.

## Source-only CI acceptance plan BEFORE later change authorization
- Tests use Notification::fake(), Mail::fake() or other zero-network fake transport; no real Mailtrap or QAS/Production operation.
- Confirm authorized staging synthetic test user selects only the dedicated sandbox route, with no leakage of token/URL to logs.
- Confirm ordinary QAS customer, administrator, and Production users cannot select the sandbox mailer even via direct notification calls; default stays array.
- Confirm no order/notification/automation/Growth path can use qas_reset_capture, even with pending/replayed simulated jobs.
- Confirm unknown accounts get same generic response, repeated requests throttle, token expires at 60 minutes, tokens are single use, and session/CSRF policies remain unchanged.
- Existing tests/Feature/PasswordRecoveryPrivacyTest.php checks Laravel ResetPassword class; design must preserve its notification-class compatibility (including subclass instanceof and mail-fake assertions) or explicitly update regression contracts without weakening coverage.
- Error handling cannot turn silent mail capture failure into false acceptance evidence. All send audit results must distinguish captured-by-sandbox from delivered-to-user.

## Future QAS release prerequisites
Require owner-approved synthetic account, Mailtrap Email Testing inbox restricted with forwarding OFF and retention, SSH/hosting outbound port feasibility, sender ownership, authorized M0–M7/route isolation, rollback to array, full exact-SHA CI GREEN, and explicit bounded authorization for one Reset Password send. Do not enable SMTP or claim acceptance based on this design.

## Current conclusion
- Operator phpMyAdmin UI is not accessible to the assistant's available tools; [prepared read-only SQL packet](QAS_M3_M4_PHPMYADMIN_OPERATOR_READONLY_PACKET_2026-10-17_AR.md) not executed.
- **This document has NO code, config, permission, policy, secret or QAS/Production changes, NO messages sent and NO accepted QAS reset test.**
- M3 BLOCKED; M4 PARTIAL/BLOCKED; C1-A Issue #2 BLOCKED; QAS #3–#6 OPEN; Production NO-GO.
