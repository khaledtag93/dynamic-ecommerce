# Flowra — M3/M4 Safe Read-only Evidence Unblock (2026-10-17)

## Authority and baseline
- Starting checkpoint [`56c22726e72af05776cb1fb78dfe3c245e546626`](https://github.com/khaledtag93/dynamic-ecommerce/commit/56c22726e72af05776cb1fb78dfe3c245e546626).
- [CI #39048347893](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39048347893) independently verified **completed/success at exact starting SHA**, 1,011 tests and 19,626 assertions. Branch pointed to the same SHA at slice start.
- QAS application deployed version (previous directly observed): `de180de6cae5f28dc867b752ab6f1d97baa71416`; effective mail was `array`, queue `database`, Scheduler OK, Queue Worker OK, one job pending, zero failed from the immediately preceding read-only audit. **Do not represent previous snapshot as a fresh complete DB audit.**
- Authorized mode in this slice: READ ONLY for QAS; GitHub documentation writes are permitted; no SMTP, send, queue work, scanner, retry, QAS/Production writes, config, policy, permission, token or secret changes.

## Previous-session status
- Desktop Commander `list_sessions` did not list the two prior long-running inspection sessions `9616` or `15468`. That is evidence of their absence from **Desktop Commander session tracking**, not proof of termination for every remote operating-system process or DB query.
- Only one new *limited* aggregate-read attempt was executed for this slice. It piped a process-local PHP read-only inspection over the previously authorized SSH route and gated on `APP_ENV=staging`, `mail=array`, the expected QAS configured MySQL DB `u637857322_v42_rehearsal`, and a further `SELECT DATABASE()` identity check **before business-table inspection**.
- The script's intended queries used only `SELECT` with allowlisted Boolean settings, active rule aggregates, Growth delivery counts, Notification dispatch channel/status counts, and `jobs`/failed-job aggregate and known-class pattern counts; no rows, addresses, message bodies, tokens or payloads were requested for output.
- **Outcome: NO VERIFIED RESULTS FROM THAT ATTEMPT.** The process returned no visible output before exceeding the expected bounded execution interval. **Do not infer** that the QAS DB guard passed, that any SQL SELECT completed, or that a specific database/SSH error caused the stall.
- A local Desktop Commander `force_terminate` was called **once**, reported successfully initiated, and a following session list no longer displayed the new session PID `16320`. This verifies the local session disappeared; every SSH/remote child process was **not** independently inspected. No repeat or alternative-route query was attempted, per owner instruction. There was no evidence of a write/send from the inspection design.
- If the reason for long-running direct DB reads is not identified safely, **keep the gate blocked rather than bypassing the control**.

## Actual M3/M4 evidence matrix

| Boundary | What is proven | What remains unproven | Gate |
| --- | --- | --- | --- |
| GitHub baseline integrity | Prior CI #39048347893 completed/success, 1,011 tests / 19,626 assertions at exact 56c22726 | New docs-only CI remains separately verifiable | **PASS** baseline |
| QAS mail isolation *as last observed* | Laravel mail driver `array`; cannot deliver directly to external inbox through this transport | Actual provider settings/third-party outbound channels not comprehensively inventoried | **PASS limited**, not full isolation |
| Notification Center / per-event Email, WhatsApp, database, SMS | Source defaults and runtime read patterns known | QAS database-backed overrides and effective values remain unknown (read-only attempt yielded no usable result) | **M3 BLOCKED/UNKNOWN** |
| Active `NotificationAutomationRule` retry, fallback and admin-email rules | Code supports these paths; some source seed candidates are enabled | Actual active-rule groups, target channels, admin configured-recipient presence | **M3 BLOCKED/UNKNOWN** |
| Growth real-email flag and live campaigns | `GrowthCampaignService` uses DB-backed `growth_real_email_enabled` with source default OFF | Effective QAS value and any active campaign/delivery backlog | **M3 BLOCKED/UNKNOWN** |
| WhatsApp external delivery | Code exposes independent Meta/queue service and channel switches | Effective QAS activation and any dispatched/stale WhatsApp jobs | **M3/A4 BLOCKED/UNKNOWN** |
| Queue overall health | Prior `ops:health --strict`: Worker/Scheduler OK, `database`, one pending, zero failed | Exact class of pending job, actual send side effects and subsequent changes | **M4 PARTIAL/BLOCKED** |
| Notification dispatch/Growth delivery backlog | Tables, source code, status contracts identified | Channel/status counts and business impact of retrying or switching transport | **M4 BLOCKED/UNKNOWN** |
| Scheduled scanner/growth | `notifications:scan-escalations` every 10 min, `growth:run` every 15 min by source | Which live DB rules and campaign sends those schedules can trigger | **M4 SOURCE VERIFIED; LIVE UNKNOWN** |

**Release verdict:** **M3 BLOCKED, M4 PARTIAL/BLOCKED, C1-A #2 BLOCKED, QAS #3–#6 OPEN, Phase1 Production NO-GO**. Mailtrap Email Testing remains **PLAN ONLY**. Do **not** switch global `MAIL_MAILER=array` or provide SMTP credentials.

## Smallest authorized alternative evidence path — future operator step, NOT executed

If direct remote DB read stalls, use an **owner-authorized QAS database console with explicit read-only rights** (e.g., the existing QAS phpMyAdmin session) after independently verifying its connection is to the expected rehearsal DB. **No privilege changes, no safety tool bypass, no CREATE/UPDATE/DELETE or active mail/scanner/retry.** Query results must be shared only as category labels/ON-OFF and aggregate counts; no raw mail addresses or other user rows. The following are **proposals for a single future read-only operator session**, not actions performed:

```sql
SELECT DATABASE() AS connected_database;

-- Run subsequent statements ONLY if connected_database exactly matches
-- u637857322_v42_rehearsal.
SELECT `key`,
       CASE WHEN `value`='1' THEN 'ON'
            WHEN `value`='0' THEN 'OFF'
            ELSE 'OTHER' END AS state
FROM website_settings
WHERE `key` IN (
  'notification_center_enabled',
  'notification_channel_database_enabled',
  'notification_channel_email_enabled',
  'notification_channel_whatsapp_enabled',
  'notification_channel_sms_enabled',
  'notification_event_order_status_updated_email',
  'notification_event_order_cancelled_email',
  'notification_event_delivery_status_updated_email',
  'notification_event_refund_recorded_email',
  'notification_event_order_status_updated_whatsapp',
  'notification_event_order_cancelled_whatsapp',
  'notification_event_delivery_status_updated_whatsapp',
  'whatsapp_enabled',
  'whatsapp_queue_enabled',
  'growth_real_email_enabled',
  'growth_messaging_enabled'
) ORDER BY `key`;

SELECT action_type,
       COALESCE(source_channel,'ANY') AS source_channel,
       COALESCE(target_channel,'NONE') AS target_channel,
       COUNT(*) AS active_count
FROM notification_automation_rules
WHERE is_active=1
GROUP BY action_type,source_channel,target_channel;

SELECT channel,status,COUNT(*) AS dispatch_count
FROM notification_dispatch_logs
GROUP BY channel,status;

SELECT channel,status,COUNT(*) AS delivery_count
FROM growth_deliveries
WHERE channel='email'
GROUP BY channel,status;

SELECT queue,COUNT(*) AS pending_count,
       SUM(payload LIKE '%RecordQueueHeartbeat%') AS heartbeat_like,
       SUM(payload LIKE '%SendGrowthDeliveryJob%') AS growth_like,
       SUM(payload LIKE '%SendWhatsAppMessageJob%') AS whatsapp_like,
       SUM(payload LIKE '%SendQueuedMailable%') AS mail_like
FROM jobs GROUP BY queue;

SELECT COUNT(*) AS failed_job_count FROM failed_jobs;
```

**Interpretation rules:** DB row absence is not equal to OFF. Reconstruct the effective value using the **matching source defaults/config** and `StoreSettingsService::all()`; any source defaults involving `config('whatsapp.*')` require an independent safe effective-config check. `payload LIKE` class counts are **nonexclusive heuristics** (not proof of execution or exact job class); do not return raw payload. If connected DB identity is wrong or query rights/tooling prevent safe inspection, **STOP and record BLOCKED**. Do not assume global sandbox is safe just because some status counts are zero.

Alternative product-safe design if M3/M4 remains unresolvable: leave global mail transport `array`; consider **source-only reset-specific capture transport** with isolated CI tests in a separately approved slice, but real sending and config remain forbidden without explicit authorization.

## Outcome and continuation
- This slice executed **no successful M3/M4 DB data extraction**; documentation and safety boundary are its delivered artifacts.
- Documentation changes only: new report and updated `CURRENT_PHASE.md`, `PROJECT_MASTER_STATUS.md`; no deploy, messages, settings or app-code modifications.
- GitHub Issues [#2](https://github.com/khaledtag93/dynamic-ecommerce/issues/2) and [#6](https://github.com/khaledtag93/dynamic-ecommerce/issues/6) to retain BLOCKED acceptance with evidence.
- Next bounded action: review an owner-approved QAS **read-only database console** as a new scoped action, **or** prepare an isolated reset-only mailer design source-only with CI without enabling SMTP. Explicit approval remains mandatory for any QAS config/write/synthetic send.
- The new doc checkpoint CI must not be called green until `completed/success` at the exact new SHA.
