# Flowra — M3/M4 Fallback, Growth Job & Scheduler Source-only Evidence (2026-10-17)

## Starting verified checkpoint
- Repository: \`khaledtag93/dynamic-ecommerce\`, branch \`sec03-framework-upgrade\`.
- Baseline SHA: \`e4cb5ddaafec68bdc90e1c283d27f4d0e9e44352\`. [Full CI #39053796255](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39053796255) independently verified **completed/success at this exact SHA**, 1,017 tests / 19,679 assertions.
- [Previous direct mail leaf fake audit](QAS_M3_M4_OUTBOUND_MAIL_ROUTE_FAKE_CONTRACT_AUDIT_2026-10-17_AR.md). Previously verified QAS source \`de180de6\`, effective global Laravel mail \`array\`; **no new QAS read executed**.

## phpMyAdmin hard stop
- No authorized browser-backed QAS phpMyAdmin SQL interface exists among the accessible tools in this slice; a connected terminal is **not** a substitute for a browser tab. **No QAS SQL was executed**, including the identity check.
- If an explicitly authorized QAS operator obtains the interface, the **only first statement** is \`SELECT DATABASE() AS connected_database;\` run **alone**. All later queries remain forbidden until the returned name matches \`u637857322_v42_rehearsal\` exactly; follow the [read-only SQL packet](QAS_M3_M4_PHPMYADMIN_OPERATOR_READONLY_PACKET_2026-10-17_AR.md) only afterward. Do not infer environment identity from URLs or prior QAS SSH evidence. No SSH/DB PHP workaround or safety bypass.

## Static source call graph (READ / INSPECT ONLY)

### Notification automation
- \`NotificationAutomationService::executeForDispatch\` selects active rules filtered by event, trigger status, source channel and \`max_attempts\`, then delay and safety limits for automatic executions; already-processed triggers skip further automated execution.
- **Manual branch differs:** \`$manual=true\` bypasses processed-trigger shortcut, delay and \`passesSafetyLimits\` (still constrained by query-selected active rules and attempt count). This is a **source-proven conditional bypass**, NOT proof of a live QAS admin rule or an authorized provider send.
- \`applyRule\` dispatches \`ACTION_RETRY_SAME_CHANNEL\` to the original log channel and \`ACTION_FALLBACK_CHANNEL\` to the rule target; \`ACTION_NOTIFY_ADMIN_EMAIL\` calls the global \`Mail::raw\` leaf; \`ACTION_CREATE_ACTIVITY\` writes admin activity data.
- \`sendUsingChannel\` persists a new dispatch log before attempting delivery. Its \`email\` branch uses global Laravel Mail; \`whatsapp\` invokes \`WhatsAppServiceInterface::sendOrderStatusUpdate/sendDeliveryUpdate\`, independent of Laravel mail; \`database\` notifies the account. The source branch itself does not perform a separate effective global event/channel-settings check here. **Do not claim this is a bug without agreed routing/override policy and reproducible failing contract.**
- \`scanStalePending\` reads stale pending logs, calls \`executeForDispatch\`, and can mark recovered entries FAILED. It was **not executed**. Actual active rules, escalation recipients, delay/rate limits and backlog are UNKNOWN.

### Growth routing
- \`GrowthDeliveryService::queue\` persists a delivery; if due now it calls \`SendGrowthDeliveryJob::dispatchSync\`.
- \`retry\` updates a delivery then calls \`dispatchSync\`; \`processDuePending\` selects \`pending\`, \`scheduled\` and \`simulated\` due deliveries then calls \`dispatchSync\`. **None** of these methods, jobs or handlers were executed here.
- \`SendGrowthDeliveryJob\` implements \`ShouldQueue\`; \`handle()\` calls \`GrowthDeliveryService::send\` and \`failed()\` calls \`markFailed\`. \`send\` persists attempt/log metadata; email branch only simulates when \`GrowthCampaignService::realEmailEnabled()\` resolves false **at send time**, otherwise hands off to \`GrowthDeliveryRouter\`.
- \`GrowthDeliveryRouter\` allows \`in_app\` and \`email\` with provider \`smtp\` or null. \`qas_reset_capture\` is **not** a registered Growth provider (nor an implemented mailer). A real send from the SMTP driver uses the global mail route, so toggling global mail can affect Growth.
- **Conditional concern, not confirmed bug:** \`simulated\` is eligible in \`processDuePending\`, while the real-email gate is re-read at \`send()\`. A flag change from OFF to ON may alter subsequent treatment of a previously simulated delivery. Actual runtime exposure, duplicate dispatch potential, business intention and correct handling need a separately approved policy/acceptance decision. No provider operation, setting change, queue execution or hotfix was performed.

### Scheduled entrypoints (definitions only)
- \`app/Console/Kernel.php\`: \`growth:run\` every 15 minutes, \`notifications:scan-escalations\` every 10 minutes, both \`withoutOverlapping()\`; \`ops:heartbeat\` every minute and payment expiry job every 5 minutes are separate concerns.
- \`RunGrowthAutomationCommand::handle\` calls \`GrowthCampaignService::runNow\` (and attribution/cohort refresh); \`runNow\` may invoke \`GrowthDeliveryService::processDuePending\`.
- \`RunNotificationEscalationScannerCommand::handle\` calls \`NotificationAutomationService::scanStalePending\` and records an admin activity audit.
- Existing scheduler/queue liveness evidence does **not** prove which commands fired, which rules matched, which jobs are pending, or whether WhatsApp/SMTP providers are isolated. We did **not** invoke schedules, commands, workers, scanners, retries or jobs.

## Verified source-only regression closure

- [Test commit `b033f8476a4df6dcff610994b496f74cb47f57d4`](https://github.com/khaledtag93/dynamic-ecommerce/commit/b033f8476a4df6dcff610994b496f74cb47f57d4) and [full Hardening CI #39054552582](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39054552582) **completed/success on exact SHA**, 1,019 PHPUnit tests / 19,701 assertions, all workflow steps successful. 2 added zero-network routing tests PASS; no app defect confirmed. Documentation pointer follow-up commit needs its own exact-SHA CI, not assumed green.

**This is source/test evidence only.** No QAS operator database identity or runtime channel state was observed.

## Zero-network source-only contract tests
- \`tests/Feature/OutboundAutomationRoutingBoundaryTest.php\` adds 2 bounded tests:
  1. Override **only** \`sendUsingChannel\` with an in-memory non-sending fake; call the protected \`applyRule\` match to assert fallback uses the target and same-channel retry uses the original, including manual flag propagation. No rules are queried or executed, no logs inserted and no providers reached.
  2. Instantiate \`GrowthDeliveryRouter\` and resolve class types only; assert accepted \`in_app\`, \`smtp\`/null email and rejection of unsupported or hypothetical reset-only providers. **Never invoke driver \`send()\`.**
- Both tests install \`Mail::fake()\` and \`Notification::fake()\`; assert no outgoing fake mail and unchanged global test \`array\` mailer.
- New Hardening CI requires exact-SHA \`completed/success\` verification. The tests cannot prove live QAS configuration, scanner side effects, retry idempotency, future QAS sandbox isolation, or email capture.
- This audit proves **no application bug** on its own. No policy/config/secret/permission or app behavior change; no QAS/Production writes or real outbound sends.

## Gate verdict and continuation
- **M3 BLOCKED; M4 PARTIAL/BLOCKED; C1-A #2 BLOCKED; QAS acceptance #3–#6 OPEN; Phase 1 Production NO-GO.**
- Next priority: owner-authorized phpMyAdmin identity evidence and then whitelisted, non-PII read-only aggregates if PASS. Otherwise keep runtime gate blocked and separately decide the Growth simulated-delivery state transition contract before any source fix or SMTP activation.
