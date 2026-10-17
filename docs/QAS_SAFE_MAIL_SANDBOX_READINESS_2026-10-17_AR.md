# Flowra — QAS Safe Mail Sandbox Readiness (PLAN ONLY, 2026-10-17)

## Scope, evidence, hard stop
- Work branch `sec03-framework-upgrade`, **source/deployed QAS SHA** `de180de6cae5f28dc867b752ab6f1d97baa71416`. [CI #39044788618](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39044788618) verified **completed/success** on that exact SHA.
- Prior authorized **read-only** QAS diagnostic established `php artisan about --json --only=drivers` -> `mail=array`; `config:show mail.mailers.array` -> `transport=array`; `mail.from` -> placeholder address ending in `@example.com`; broker `expire=60` minutes, `throttle=60` seconds. The user's earlier public reset success is **NOT** proof of registration, dispatch or inbox delivery. Exact requesting email was not disclosed or checked.
- **This slice is documentation/source inspection only.** No QAS/Production writes, provider calls, outgoing SMTP handshakes/messages, actual test emails, queue scans, retry, config/secrets/permissions/policies modification, or live acceptance. The analysis below is based on source and official provider documentation; QAS live effective automation flags remain UNKNOWN.
- Production **NO-GO**. C1-A [#2](https://github.com/khaledtag93/dynamic-ecommerce/issues/2) **BLOCKED**; QAS acceptance Issues [#3](https://github.com/khaledtag93/dynamic-ecommerce/issues/3), [#4](https://github.com/khaledtag93/dynamic-ecommerce/issues/4), [#5](https://github.com/khaledtag93/dynamic-ecommerce/issues/5), [#6](https://github.com/khaledtag93/dynamic-ecommerce/issues/6) **OPEN**.

## Provider choice for current Hostinger deployment

| Criterion | Mailtrap **Email Testing / Sandbox** | Mailpit |
| --- | --- | --- |
| Installation | Hosted SMTP sandbox + inbox, no long-lived Hostinger daemon required | Self-hosted standalone executable or Docker; SMTP listener and private web UI must run continuously |
| Mail capture | Traps outbound test email; requires sandbox credentials and disabled forwarding | Local capture; no external release/SMTP relay configuration permitted |
| Hostinger fit | **Preferred simplest on current web-hosting-style setup**, subject to QAS outbound TLS/port access and provider account consent | Alternative only if hosting tier supports a safely managed, persistent process, ports/loopback, access-controlled UI, and backups |
| Laravel adapter | Current `config/mail.php` already supports `smtp`; no Composer dependencies needed | Also SMTP if service can be hosted safely |
| Main risk | Sandbox forwarding, accidental **Mailtrap Email Sending** service credentials, exposure of real data in third-party inbox | New service, UI exposure, SMTP relay, persistence and permissions on Hostinger |

**Decision: RECOMMEND Mailtrap Email Testing (Sandbox) — PLAN ONLY.** **NOT** Mailtrap Email Sending/Transactional. This recommendation is not owner approval for config changes, live sends, SMTP connectivity tests or mailbox creation. Do not substitute a Google/Hostinger live SMTP account for the sandbox.

Official references:
- Mailtrap [Sandbox SMTP integration](https://docs.mailtrap.io/email-sandbox/setup/sandbox-smtp-integration), [Sandbox overview](https://docs.mailtrap.io/email-sandbox/overview), [sandbox help/ports/forwarding troubleshooting](https://docs.mailtrap.io/email-sandbox/help).
- Mailpit [installation and default listener ports](https://mailpit.axllent.org/docs/install/) and [Docker requirements](https://mailpit.axllent.org/docs/install/docker/).
- Hostinger [SMTP port guidance](https://www.hostinger.com/tutorials/smtp-port/): Hostinger's own SMTP submission uses 465/587; this does **not** by itself prove outbound access from this specific hosting account to *Mailtrap*. Prefer port **587 STARTTLS** when supported by the specific Mailtrap sandbox integration and verified by authorized connectivity checks; follow sandbox's **actual** credentials and approved connection configuration, never use live SMTP or invent ports.

## Source-derived blast radius of changing the GLOBAL mailer

**Do not do** `MAIL_MAILER=array → smtp` yet. This changes transport for all callers of Laravel Mail, not just password recovery. Current array transport also provides intentional no-real-mail isolation.

| Channel/source | Source proof | Side effects if switching global mailer |
| --- | --- | --- |
| Forgot Password | `app/Http/Controllers/Auth/ForgotPasswordController.php` / Laravel password broker and `User` Notifiable | Real user's or unknown requesting email can reach sandbox if account exists; contains one-time password-reset secret link. Keep sandbox restricted and never commit tokens/screenshots unredacted. |
| Order notifications | `app/Services/Commerce/OrderNotificationService.php` `sendEmail()` calls `Mail::to()->send(new OrderUpdateMail(...))`; `config/commerce_notifications.php` lists email for status/cancel/delivery/refund | Enabling SMTP can start capturing notification mail from any triggered order to the **same** sandbox, including real account/order PII; DB flags `notification_center_enabled`, `notification_channel_email_enabled`, per-event toggles are runtime-database dependent, not proven OFF. The code treats `send()` success as sent; with Sandbox that means **captured, not delivered**. |
| Automation + recovery | `app/Services/Commerce/NotificationAutomationService.php`: active `NotificationAutomationRule` `ACTION_RETRY_SAME_CHANNEL`, `ACTION_FALLBACK_CHANNEL`, `ACTION_NOTIFY_ADMIN_EMAIL`; sends `Mail::to` and `Mail::raw`. Some seed rules are marked active in source. | Fail/skip/stale sends may be retried via email; admin escalation may use `rule.admin_email` **or default `mail.from.address`**. **Never assume changing order-email flags disables all automation.** Read effective DB rules and queue/backlog, then explicitly isolate. |
| Scanner / cron | `app/Console/Kernel.php` schedules `notifications:scan-escalations` every 10 min and `growth:run` every 15 min; `app/Console/Commands/RunNotificationEscalationScannerCommand.php` delegates to automation service | Scheduled processes may run independently of an operator. Backlog/retries may have stale recipient information and start capturing mail unexpectedly after transport switch. Do not trigger, flush, replay, or delete queued jobs in this planning slice. |
| Growth emails | `app/Services/Growth/Channels/SmtpEmailGrowthChannel.php` calls `Mail::to()->send(new GrowthCampaignMail(...))`; `app/Services/Growth/GrowthCampaignService.php` reads DB-backed `growth_real_email_enabled`, source default `0`; delivery job path in `GrowthDeliveryService.php` | Default `0` is **not evidence** of effective QAS DB value. Active campaigns, pending deliveries and retries must be audited before mailer switch. |
| Checkout + WhatsApp | `app/Services/Frontend/CheckoutService.php` queues WhatsApp order confirmation; `app/Services/Channels/WhatsApp/WhatsAppManager.php` can queue or send via Meta provider | **Independent provider path:** Mailtrap does not isolate WhatsApp, SMS, payment provider, or HTTP webhook. C1-A A4 remains BLOCKED until separately verified/isolated. |
| Database notifications | `app/Notifications/OrderPlacedNotification.php`, `OrderStatusChangedNotification.php` `via()=['database']` | These particular classes remain DB-only; do not use that fact to assert the separate OrderNotificationService email channel is disabled. |
| Queue and worker | `config/queue.php`, `docs/SCHEDULER_QUEUE_OPERATIONS_RUNBOOK_2026-09-26.md` and deployed QAS `queue=database` / worker; deploy script issues `queue:restart` | Worker may retain config until restart; new config must not be exposed while old pending jobs can trigger unreviewed messages. Queue health `OK` is not notification isolation. |

### Critical nuance — safe sandbox ≠ zero data risk
- Provider sandbox normally catches messages instead of delivering to intended recipients; however *messages and their addresses/content are transferred to a third party*. Restrict to synthetic users and inspect access, privacy/retention, team membership, forwarding/relay and notification settings.
- Mailtrap sandbox may support **automatic forwarding** configured separately: require evidence forwarding **OFF**; never enable auto-forwarding or use live-sending credentials. A test inbox intentionally holds sensitive reset links; mask/delete per the approved evidence-retention rule.
- An SMTP transport change should not be interpreted as product authorization for email campaigns, sending real customer mail, or closing QAS acceptance.

## Gate-by-gate APPROVAL BEFORE any changes (all unapproved unless later explicitly accepted)

| Gate | Evidence required | Currently |
| --- | --- | --- |
| M0 — exact target | QAS authorized staging / expected rehearsal DB / source SHA / operator and test account; source CI exact SHA GREEN | **Historical deploy/CI evidenced**, re-read at execution time |
| M1 — dedicated sandbox | Owner-approved Mailtrap **Email Testing sandbox**, private least-privilege members, no forwarding/relay and no Email Sending credentials | **OPEN** |
| M2 — sender/receiver | Synthetic QAS test customer only, permitted sender identity/domain (not `example.com`), explicit content/retention consent and reset-link confidentiality | **OPEN** |
| M3 — outbound inventory | Permissioned READ-ONLY QAS actual values for order-email notifications, event toggles, admin emails, active automation rules incl. fallback/retry, Growth `growth_real_email_enabled`, WhatsApp flags and third-party dispatch paths; do not disclose addresses or secrets | **OPEN** |
| M4 — pending work | Review queue backlog types/counts, unprocessed email dispatch logs, scheduled scanner/growth tasks and manual retries, safe stop/start strategy; avoid external side effects and preserve pending jobs | **OPEN** |
| M5 — transport connectivity | Confirm Mailtrap sandbox-specific SMTP port/TLS connectivity from exact Hostinger QAS with an expressly authorized **non-delivery connectivity probe**; no test send before isolated gate is signed | **OPEN** |
| M6 — change authorization | **Separate explicit owner consent** for QAS-only mail transport, authorized target sandbox, From identity, bounded one-reset send and any settings/queue changes, backup, rollback and observed side effects. No Production. | **NOT GIVEN** |
| M7 — verified rollback | Capture previous `mail=array` baseline (without credentials), protected QAS-only runtime backup, verified config/worker reload and recovery if no safe capture; preserve DB/queue, no destructive cleanup | **OPEN** |
| M8 — bounded acceptance | After M0–M7 PASS, single synthetic account/reset request; inbox capture, from/headers, valid token link, expiry/replay and successful new-password login; no other customer/admin/growth send and no WhatsApp. Record synthetic test proof only | **NOT RUN** |

**STOP** if any gate is unknown. Specifically, do not run `php artisan notifications:scan-escalations`, `queue:retry`, admin test-send buttons, real customer reset, or switch `MAIL_MAILER` globally during preflight.

## Future QAS implementation procedure (NOT EXECUTED)
1. Obtain M0–M7 read-only evidence and owner decisions; identify an approved QAS-only sandbox inbox. Configure Mailtrap separately with **Email Testing**, not Email Sending; verify team access/forwarding disabled and synthetic fixture.
2. Approve how all non-reset sends will be isolated. **Preferred simplest design only when validated:** all Laravel email flows may point to a catch-only sandbox **if** source/queue/runtime isolation ensures only synthetic permitted data and zero external forwarding; otherwise keep global `array` and design a bounded **reset-only scoped mailer** as a separate source slice with CI and explicit approval.
3. At a separately approved change window, store sandbox SMTP username/password in QAS environment secret handling only, never in Git or chat. Use provider-approved host, supported TLS/port (prefer 587 if supported); set authorized `MAIL_FROM_ADDRESS` and name. Backup current effective settings and rollback path; config cache/queue worker sync is required under Laravel. Do not edit Production.
4. Independently re-check effective `mail.default`, `mail.mailers.<name>.transport`, From domain only (do not print keys), sandbox routing, inactive/unroutable external paths, queue backlog, then one synthetic reset send **under the approved bounded write scope**. Do not infer deliverability from broker's generic success.
5. Capture redacted evidence of sandbox receipt, URL host points to QAS not Production, reset token use + replay rejection, new password login, no unexpected extra outbound messages; reset back to `array` on any failure. Do not expose/reset-token links in GitHub evidence.
6. Leave C1-A and wider QAS acceptances OPEN until *all* P0 gates, including WhatsApp/payment/order isolation and business journey reconciliation, have independent evidence.

## Owner decision required for next slice
Approval is **not** implied by this document. Proposed bounded follow-up is *read-only* evaluation of actual QAS notification/automation/queue/Growth flags and sandbox provider setup readiness, followed by explicit owner choice:
- `PLAN-A`: QAS-wide Mailtrap Email Testing, capture-only, synthetic data and outbound inventory proved isolated.
- `PLAN-B`: global mail remains array; source-only isolated reset mailer design + CI until acceptable runtime contract.
Neither is authorized for execution now.

## Source references
- `app/Http/Controllers/Auth/ForgotPasswordController.php`, `config/mail.php`, `config/auth.php`.
- `app/Services/Commerce/OrderNotificationService.php`, `app/Services/Commerce/NotificationAutomationService.php`, `config/commerce_notifications.php`, `app/Services/Commerce/StoreSettingsService.php`.
- `app/Services/Growth/GrowthCampaignService.php`, `app/Services/Growth/GrowthDeliveryService.php`, `app/Services/Growth/Channels/SmtpEmailGrowthChannel.php`.
- `app/Console/Kernel.php`, `app/Console/Commands/RunNotificationEscalationScannerCommand.php`, `app/Services/Frontend/CheckoutService.php`, `app/Services/Channels/WhatsApp/WhatsAppManager.php`.
- Related GitHub audit issues [#6](https://github.com/khaledtag93/dynamic-ecommerce/issues/6), [#2](https://github.com/khaledtag93/dynamic-ecommerce/issues/2).

## Documentation checkpoint CI
This plan-only commit has no source/runtime code edits. **Do not call its new CI Green unless `completed/success` verified at exact documentation SHA.** QAS remains deployed at earlier verified source SHA `de180de6`.
