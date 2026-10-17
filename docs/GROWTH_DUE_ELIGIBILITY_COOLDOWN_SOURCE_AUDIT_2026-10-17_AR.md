# Flowra — M3/M4 Growth Due Eligibility & Trigger Cooldown Source-only Review (2026-10-17)

## Start checkpoint and runtime gate
- Baseline \`d87cbaa795462d507f18a043c52e8553c521ac67\` / [exact-SHA full CI #39058542900](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39058542900): **completed/success**, 1,022 tests / 19,737 assertions. Branch \`sec03-framework-upgrade\`.
- Read [Growth simulation transition evidence](GROWTH_SIMULATED_DELIVERY_SOURCE_CONTRACT_2026-10-17_AR.md), \`CURRENT_PHASE.md\`, \`PROJECT_MASTER_STATUS.md\`, [OPEN Product/Security decision #12](https://github.com/khaledtag93/dynamic-ecommerce/issues/12), \`GrowthDeliveryService\`, \`GrowthCampaignService\`, \`GrowthDelivery\`, \`GrowthTriggerLog\` and schema.
- No assistant-accessible, authenticated, authorized QAS phpMyAdmin SQL tab; \`SELECT DATABASE()\` **NOT RUN** and **zero QAS SQL**. Hard gate remains: \`SELECT DATABASE() AS connected_database;\` alone, only by an authorized operator in the correct UI, STOP unless exact \`u637857322_v42_rehearsal\`. Then and only then consult the [non-PII SELECT packet](QAS_M3_M4_PHPMYADMIN_OPERATOR_READONLY_PACKET_2026-10-17_AR.md). No SSH/DB PHP, safety bypass, QAS/Production writes or provider sends.

## Bounded source findings (not live QAS behavior)

### Due eligibility: status, time and page order
- \`GrowthDeliveryService::processDuePending(int $limit=100)\` has an Eloquent SELECT over \`status IN ('pending','scheduled','simulated')\`, \`scheduled_for IS NULL OR scheduled_for <= now()\`, ordered **ascending by scheduled_for**, \`LIMIT $limit\`. It then invokes \`SendGrowthDeliveryJob::dispatchSync\`, which was **NOT invoked** here.
- **Reproducible conditional selection risk:** Synthetic rows with a 10-day-old due \`simulated\` and 9-day-old due \`pending\` give only the \`simulated\` row for \`LIMIT 1\`. A sufficiently large number of old due simulated rows could occupy the default 100-row selection before newer pending rows. This is **selector starvation potential**, not proof of actual QAS backlog, processing time, or harm.
- A future \`scheduled\` row is excluded; a \`sent\` row is excluded. Null scheduling is eligible by source. No status priority, processed timestamp watermark, row claim/lock, or unique secondary sort exists in this SELECT; does not prove two concurrent jobs ran or duplicated sends.
- A synthetic **read-only mirror** of this source SELECT is exercised in CI; test statically anchors the exact source clauses. Never run \`processDuePending\`, a job, a scanner or a queue command to establish this. Mirror is not an end-to-end regression for actual dispatcher throughput.

### Trigger cooldown is NOT delivery idempotency
- \`GrowthCampaignService::recentlyTriggered\` is a separate read-only existence check scoped to one \`campaign_id\`, recipient \`user_id\` or else \`session_id\`, \`triggered_at >= now() - max(1,cooldownHours)\`, and **trigger status IN ('triggered','sent','delivered')**.
- CI synthetic in-memory test DB confirms a recent \`sent\` trigger blocks within the campaign and cooldown window; older \`sent\` does not; synthetic \`simulated\` trigger status does not; one recent session trigger blocks only that session, and an empty user/session candidate does not match. This is not a trigger mutation or provider run.
- In the existing simulated-email \`GrowthDeliveryService::send\` path, the linked trigger log is updated to \`sent\`; therefore existing simulated *deliveries* may still benefit from trigger cooldown **for newly generated candidates**. The guard does not check \`GrowthDelivery\` status on direct re-entry and does not provide a durable unique delivery idempotency token or worker claim. No concurrency bug asserted without concurrent evidence.
- Eligibility selector and trigger cooldown are independent; cooldown does **not** remove an existing simulated delivery from the due selector.

## Safe CI-only tests (NO side effects outside isolated fixture DB)
- New \`tests/Feature/GrowthDueEligibilityAndCooldownReadOnlyTest.php\` contains **2** tests using \`RefreshDatabase\`, \`Mail::fake()\`, \`Notification::fake()\`. Only synthetic \`@example.test\` data, no PII.
- Test 1 checks source SELECT literals and **duplicates its Eloquent SELECT in the isolated test DB only**; checks ordering and limit, excluding sent/future scheduled. Does not invoke method with dispatch and does not prove an end-to-end queue claim.
- Test 2 calls **only** protected read method \`recentlyTriggered\` through Reflection; proves per-campaign/per-user or session status+age semantics. Does not call \`processCandidate\`, send, queue or retry.
- **CI evidence:** [exact commit `e54c3431a512232f75c7493602fdaebb85a6eb9c`](https://github.com/khaledtag93/dynamic-ecommerce/commit/e54c3431a512232f75c7493602fdaebb85a6eb9c) / [full Hardening CI #39059384954](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39059384954) verified `completed/success` with **1,024 tests / 19,761 assertions** and no failed workflow steps. The two new contracts PASS **as source/isolated-DB evidence only**. Documentation-only follow-up commit requires its own CI.
- No QAS/Production writes, email/SMTP/WhatsApp/SMS, \`SendGrowthDeliveryJob::handle\`, \`dispatchSync\`, \`processDuePending\`, worker, scheduled command, scanner, provider \`send\`, settings/permissions/secrets/policy change. New exact-SHA full CI must be verified before PASS.

## Decision, risk and acceptance
- **No approved policy fix applied.** Excluding simulated rows or changing terminal/replay behavior intersects [Issue #12](https://github.com/khaledtag93/dynamic-ecommerce/issues/12) and requires an explicit Product/Security choice. It would be unsafe to silently treat \`simulated\` as terminal or auto-send old emails.
- Outstanding Product decision includes: should historical simulated deliveries be eligible when OFF/ON; fair ordering/limit; terminal states; concurrency idempotency. **OPEN**, not an independently proven accepted app defect.
- QAS real-email flag, active campaigns, count/age of due simulated and pending rows, automation rules, queue classes, and delivery backlog remain **UNKNOWN** until the authorized database identity gate and later allowed aggregates.
- **M3 BLOCKED; M4 PARTIAL/BLOCKED; C1-A #2 BLOCKED; QAS #3–#6 OPEN; Phase 1 Production NO-GO.**
- Next action: authorized standalone phpMyAdmin identity \`SELECT DATABASE()\` if UI becomes available; otherwise stop QAS runtime checks and keep Issue #12 awaiting owner decision. Do not run a job/queue merely to prove selector fairness.
