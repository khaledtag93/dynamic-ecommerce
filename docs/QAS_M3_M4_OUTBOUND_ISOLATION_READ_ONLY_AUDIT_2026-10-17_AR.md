# Flowra — M3/M4 QAS Outbound Isolation Read-Only Audit (2026-10-17)

## Scope and integrity
- Starting documentation checkpoint [`fdd59961b0d2e9d044190ae63b5c4231bf143d0e`](https://github.com/khaledtag93/dynamic-ecommerce/commit/fdd59961b0d2e9d044190ae63b5c4231bf143d0e) and [CI #39047061009](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39047061009) **completed/success**, **1,011 tests / 19,626 assertions**, exact baseline SHA.
- QAS existing deployed code remains [`de180de6cae5f28dc867b752ab6f1d97baa71416`](https://github.com/khaledtag93/dynamic-ecommerce/commit/de180de6cae5f28dc867b752ab6f1d97baa71416), not the documentation-only branch head.
- Explicit authorization: **READ ONLY** QAS runtime observation and source audit. **No** QAS/Production data/config/secret/policy/permission mutation, email/SMS/WhatsApp, real SMTP/provider calls, order or test-fixture writes, queue retry, scanner execution or message dispatch. Do not circumvent any safety block.
- No runtime credentials, PII, notification recipients, queue payloads, rule names, tokens or secret values copied into this document, tool summaries or GitHub.

## Direct verified QAS runtime results

An authorized read-only SSH command on the existing QAS Windows Desktop Commander connection (strict host-key checking, previously vetted QAS key) returned:
- Git current exact SHA: `de180de6cae5f28dc867b752ab6f1d97baa71416` — **PASS** against currently deployed QAS.
- `php artisan about --json --only=drivers`: **mail=array**, **queue=database**, **session=file**, **database=mysql**, log driver `single`. **PASS** for *current no-external-Laravel-email transport only*; not proof that WhatsApp or other providers are isolated.
- `php artisan ops:health --strict --no-ansi`: **Scheduler OK (last seen 32s)**, **Queue Worker OK (33s)**; **Pending Jobs=1**, **Failed Jobs=0**, queue driver database. **PASS** for runtime liveness and aggregate counts only. **Do not assume the one pending job is a heartbeat**.
- The previous focused mail diagnostic showed From identity placeholder `@example.com`, password-broker expiry 60 minutes and request throttle 60 seconds. Not reconfigured here.

Two follow-up **read-only, allowlisted, aggregate-only QAS PHP/DB inspection attempts** were made. They were designed to return only ON/OFF/presence or counts, with a hard-coded `staging`/`u637857322_v42_rehearsal`/`mail=array` guard, and no database writes. **Neither attempt returned a completed, attributable result within the execution window**, and a local terminal session cancellation was requested. The reason for noncompletion is **not proven**; do **not** claim a DB error, credential failure, successful DB query, safety denial, or permission change. Do not treat the lack of output as OFF/empty. No alternate bypass was attempted. This is **BLOCKED/UNKNOWN for effective live DB flags and pending job identity**, not evidence that the features are safe.

## M3 — Effective channel and rule inventory

| Question | Direct runtime evidence | Source evidence (not proof of QAS actual settings) | Disposition |
| --- | --- | --- | --- |
| Current Laravel mail transport | `mail=array` actual QAS | `config/mail.php` resolves default transport at runtime | **PASS**: no real inbox delivery through this Laravel mail transport *currently* |
| Notification Center master and per-channel email/DB/WhatsApp flags | DB flags not returned | `app/Services/Commerce/StoreSettingsService.php` default values allow notification center and email/WhatsApp; `OrderNotificationService::isChannelEnabledForEvent()` reads effective settings | **UNKNOWN — M3 BLOCKED** |
| Email and WhatsApp event toggles (status, cancellation, delivery, refund) | DB overrides not returned | `config/commerce_notifications.php` lists event candidate channels; order dispatch can call `Mail::to()->send()` or WhatsApp queue | **UNKNOWN — M3 BLOCKED** |
| WhatsApp enabled and order-confirmation/status/delivery flags | DB/config effective flags unobserved | `CheckoutService` calls WhatsApp order confirmation, `WhatsAppManager` may queue/send via Meta, independent of Laravel Mail | **UNKNOWN — M3/A4 BLOCKED** |
| Active NotificationAutomationRule count, target-channel fallback and same-channel retry | No DB aggregate returned | `NotificationAutomationService::executeForDispatch()` reads active DB rules and can retry/fallback; source seed contains active candidates | **UNKNOWN — M3 BLOCKED** |
| Admin email escalation recipients | No DB rule presence aggregates returned, no recipient addresses requested | `NotificationAutomationService::notifyAdminEmail()` uses rule address or `mail.from.address` fallback | **UNKNOWN — M3 BLOCKED** |
| Growth real-email state | No DB setting returned | `GrowthCampaignService::realEmailEnabled()` reads `growth_real_email_enabled`, default `0`, but *database can override it*; `SmtpEmailGrowthChannel` uses global Mail | **UNKNOWN — M3 BLOCKED** |

**Important:** The current `array` transport protects Laravel mail *today*, but switching global mail to a sandbox could cause queued/scheduled customer, admin and Growth messages to flow into that third-party inbox, including PII. Although a capture sandbox would not normally deliver to recipient inboxes, sending a real customer's content to a third party without approved isolation is still unsafe.

## M4 — Queue, scheduler and delivery backlog

| Question | Runtime evidence / limitations | Disposition |
| --- | --- | --- |
| Queue driver | Actual `database` | **PASS** |
| Scheduler and worker heartbeats | Both **OK**, about 32s/33s during one observed check | **PASS** for liveness, not sender isolation |
| `jobs` pending count | **1** per `ops:health --strict` | **PASS** for aggregate count; type **UNKNOWN** |
| Failed jobs count | **0** per `ops:health --strict` | **PASS** for aggregate count only |
| Exact pending job class, recipient and side effects | No data read completed; **never inspect/export payload or PII directly**. Only count whitelisted classes in approved aggregate read | **UNKNOWN — M4 BLOCKED** |
| Pending/failed NotificationDispatchLog email/WhatsApp | DB aggregate read not completed | **UNKNOWN — M4 BLOCKED** |
| Pending/failed GrowthDelivery email backlog | DB aggregate read not completed | **UNKNOWN — M4 BLOCKED** |
| Scheduled scanner/growth definitions | `app/Console/Kernel.php`: `notifications:scan-escalations` every **10 minutes**, `growth:run` every **15 minutes**. Only definitions, not observed execution history or effective active rules | **SOURCE VERIFIED; live effects UNKNOWN** |
| Existing retries/admin fallback | Source supports `notifications:scan-escalations` and manual retry; none executed | **SOURCE VERIFIED; active runtime rule/action UNKNOWN** |

## M3/M4 safety gate verdict
- **M3 = BLOCKED**, despite **PASS** on current `mail=array`. Effective notification/email/WhatsApp switches, active automation rule types, admin escalation recipients and Growth real-email state are **UNKNOWN**.
- **M4 = PARTIAL/BLOCKED**: `queue=database`, liveness, counts (one pending/zero failed) **PASS**. Pending job identity and delivery/retry backlog **UNKNOWN**; cannot sign off send-isolation.
- **Mailtrap Email Testing** remains a recommended **PLAN ONLY**. **Do not change `MAIL_MAILER=array` to SMTP** or use a real sender address, or contact SMTP provider, without explicit separate authorization.
- **C1-A Issue #2 BLOCKED**; QAS acceptance Issues **#3/#4/#5/#6 OPEN**; **Phase 1 Production NO-GO**.

## Smallest safe unblock (NO EXECUTION IN THIS SLICE)
1. Use **an already authorized, permission-scoped READ-ONLY QAS operator surface** for the effective known Boolean channel/event switches, active rule **counts by action type only** (no admin_email or message text), Growth real-email flag and limited pending job *class counts only*. Require known target environment and no automatic test-send/scanner execution.
2. Separately inspect *only approved metadata* of scheduled scanner and worker; never run `notifications:scan-escalations`, `growth:run`, `queue:retry`, `queue:work`, mail send or admin test-send as diagnostics.
3. Record per-field PASS/UNKNOWN, a high-level whitelist of permitted destinations and sandbox forwarding/retention controls. If no safe read-only evidence path exists, keep M3/M4 blocked and prefer **reset-only mailer/source design** while global transport remains `array`.
4. Only after M0–M7 verified and **explicit owner permission** for QAS configuration + bounded synthetic test, move to isolated sandbox sender setup. No Production modifications.
5. Source docs remain at their current checkout and the previously verified CI; check any *new documentation-only* checkpoint's CI for `completed/success` on its exact SHA before declaring that checkpoint GREEN.

## Source and operational references
- [Sandbox readiness plan](QAS_SAFE_MAIL_SANDBOX_READINESS_2026-10-17_AR.md).
- `app/Services/Commerce/OrderNotificationService.php`, `app/Services/Commerce/NotificationAutomationService.php`, `app/Services/Commerce/StoreSettingsService.php`, `config/commerce_notifications.php`.
- `app/Services/Growth/GrowthCampaignService.php`, `app/Services/Growth/Channels/SmtpEmailGrowthChannel.php`.
- `app/Services/Frontend/CheckoutService.php`, `app/Services/Channels/WhatsApp/WhatsAppManager.php`, `app/Console/Kernel.php`.
- [C1-A Issue #2](https://github.com/khaledtag93/dynamic-ecommerce/issues/2), [Password reset Issue #6](https://github.com/khaledtag93/dynamic-ecommerce/issues/6).
