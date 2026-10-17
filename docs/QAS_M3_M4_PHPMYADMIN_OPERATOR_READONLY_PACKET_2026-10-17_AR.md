# Flowra — M3/M4 Operator-assisted phpMyAdmin SQL Packet (2026-10-17)

## Evidence status and authorization
- Starting checkpoint: 0cf089365203a9ac7d1244f6120afa4a0bb49023; [CI #39049409471](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39049409471) completed/success, 1,011 tests / 19,626 assertions at exact SHA.
- QAS last verified deployed code: de180de6cae5f28dc867b752ab6f1d97baa71416; Laravel mail=array, queue=database. Production untouched.
- **The assistant does NOT have access to a browser-backed, authenticated QAS phpMyAdmin SQL tab** through its available tools. This task forbids a substitute SSH/DB PHP query or a tool-safety bypass. **No phpMyAdmin connection or SQL statement was run**, including the identity check.
- This document is an **operator-assisted, read-only prepared packet**. Its SQL must only be run in the owner's existing, explicitly authorized QAS phpMyAdmin session, with SELECT rights. No QAS/Production writes, jobs, scanners, retries, email/SMS/WhatsApp sends, secrets, policies or configuration changes.
- M3 BLOCKED/UNKNOWN; M4 PARTIAL/BLOCKED. C1-A #2 BLOCKED; QAS #3/#4/#5/#6 OPEN; Phase1 Production NO-GO.

## Step 1 — hard database identity gate (run ALONE, separately)

Open the owner-authorized QAS phpMyAdmin tab in hPanel. Select the rehearsal database, then execute ONLY:

~~~sql
SELECT DATABASE() AS connected_database;
~~~

**STOP unless the only returned value exactly equals u637857322_v42_rehearsal.** Do not batch this SELECT with the next queries. A known URL or remembered credentials do not prove a correct or authorized current session. Never paste passwords/session cookies into chat.

## Step 2 — database-backed channel and Growth flags (run only after Step 1 PASS)

~~~sql
SELECT
  `key` AS setting_key,
  CASE
    WHEN `value` = '1' THEN 'ON'
    WHEN `value` = '0' THEN 'OFF'
    ELSE 'OTHER_REDACTED'
  END AS db_override_state
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
  'notification_event_refund_recorded_whatsapp',
  'whatsapp_enabled',
  'whatsapp_queue_enabled',
  'whatsapp_feature_order_confirmation',
  'whatsapp_feature_order_status_update',
  'whatsapp_feature_delivery_update',
  'growth_real_email_enabled',
  'growth_messaging_enabled'
)
ORDER BY setting_key;
~~~

The SELECT returns only allowlisted keys and ON/OFF/OTHER. **Missing key means no database override, NOT OFF.** Source defaults in app/Services/Commerce/StoreSettingsService.php default Notification Center/email to ON; other defaults depend on effective config/whatsapp.php. Growth real email source fallback is OFF, unless overridden. Do not query all website_settings: that table can hold sensitive values.

## Step 3 — active automation rules by *categories* only

~~~sql
SELECT
  action_type,
  COALESCE(source_channel,'ANY') AS source_channel,
  COALESCE(target_channel,'NONE') AS target_channel,
  COUNT(*) AS active_rule_count,
  COALESCE(SUM(
    CASE WHEN action_type='notify_admin_email'
      AND admin_email IS NOT NULL AND admin_email <> ''
    THEN 1 ELSE 0 END
  ),0) AS explicit_admin_recipient_count
FROM notification_automation_rules
WHERE is_active=1
GROUP BY action_type, source_channel, target_channel
ORDER BY action_type, source_channel, target_channel;
~~~

Never retrieve rule names, admin_email, recipients, message bodies or errors. Explicit admin recipient COUNT is not approval to send.

## Step 4 — Growth / notification delivery backlog (counts only)

~~~sql
SELECT
  (SELECT COUNT(*) FROM growth_campaigns
   WHERE channel='email' AND is_active=1) AS active_email_campaign_count,
  (SELECT COUNT(*) FROM growth_deliveries
   WHERE channel='email' AND status IN ('pending','queued','scheduled')) AS open_email_growth_deliveries,
  (SELECT COUNT(*) FROM growth_deliveries
   WHERE channel='email' AND status='failed') AS failed_email_growth_deliveries;

SELECT channel,status,COUNT(*) AS dispatch_count
FROM notification_dispatch_logs
WHERE channel IN ('email','whatsapp','database')
GROUP BY channel,status
ORDER BY channel,status;
~~~

Zero pending now does not prove scheduled workflows won't create email later.

## Step 5 — queued-job *type hints* and failure counts

~~~sql
SELECT COUNT(*) AS pending_total,
       COALESCE(SUM(CASE WHEN payload LIKE '%RecordQueueHeartbeat%' THEN 1 ELSE 0 END),0) AS heartbeat_hint,
       COALESCE(SUM(CASE WHEN payload LIKE '%SendGrowthDeliveryJob%' THEN 1 ELSE 0 END),0) AS growth_hint,
       COALESCE(SUM(CASE WHEN payload LIKE '%SendWhatsAppMessageJob%' THEN 1 ELSE 0 END),0) AS whatsapp_hint,
       COALESCE(SUM(CASE WHEN payload LIKE '%SendQueuedMailable%' THEN 1 ELSE 0 END),0) AS queued_mail_hint
FROM jobs;

SELECT COUNT(*) AS failed_jobs_total FROM failed_jobs;
~~~

The LIKE matches inspect serialized payload *inside the database only* and return **counts**; class hints can overlap or miss unknown classes. Do NOT SELECT payload, raw job/notification rows, exception, email, phone, session or token data. Do not infer the one observed pending job is definitely a heartbeat.

## Stop conditions and evidence to share

If connected DB name mismatches, permission is unavailable, a SELECT errors or times out, or any result could expose PII: **STOP, mark BLOCKED**. Do not retry through SSH, alternate connector, or bypass a safety restriction.

Only record/share: DB identity verified PASS (not credentials), allowlisted keys + ON/OFF/OTHER, grouped rule/action/channel counts, delivery/dispatch grouped counts, queue class *hint* counts, failures, and observation time. Redact screenshots to avoid browser cookies, user identifiers and reset links.

Proof of M3/M4 requires reconciling absent DB keys with exact source + safe effective config. Even PASS on aggregates does not authorize Mailtrap SMTP until full M0–M7, forwarding OFF, single synthetic account, queue isolation, and separate owner approval.

## Linked plan
- [Prior M3/M4 block](QAS_M3_M4_SAFE_READ_ONLY_EVIDENCE_UNBLOCK_2026-10-17_AR.md)
- [Reset-only sandbox alternative design](QAS_RESET_ONLY_MAIL_SANDBOX_SOURCE_DESIGN_2026-10-17_AR.md)
- [C1-A issue #2](https://github.com/khaledtag93/dynamic-ecommerce/issues/2), [Reset issue #6](https://github.com/khaledtag93/dynamic-ecommerce/issues/6)
- New documentation checkpoint CI must separately be completed/success at exact SHA before GREEN.
