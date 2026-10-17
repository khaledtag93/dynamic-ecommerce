# Flowra — Growth Simulated Delivery Idempotency & OFF→ON Source-only Evidence (2026-10-17)

## Starting gate and scope
- Baseline GitHub checkpoint \`0a943abb82fd220090070ceca83d6b3d32689b86\`; [Hardening CI #39054925464](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39054925464) independently verified \`completed/success\` at that exact SHA: 1,019 tests / 19,701 assertions.
- Reviewed [M3/M4 Growth and fallback source audit](QAS_M3_M4_FALLBACK_GROWTH_SCHEDULE_SOURCE_AUDIT_2026-10-17_AR.md), \`CURRENT_PHASE.md\`, \`PROJECT_MASTER_STATUS.md\`, \`GrowthDeliveryService\`, \`GrowthDeliveryRouter\`, \`GrowthCampaignService\`, \`GrowthDelivery\` and \`GrowthMessageLog\`.
- Authorized authenticated QAS phpMyAdmin browser SQL UI unavailable among accessible tools. **NO SQL was run**, not even \`SELECT DATABASE()\`. If later available, FIRST AND ONLY run \`SELECT DATABASE() AS connected_database;\` alone. Hard stop unless exact result \`u637857322_v42_rehearsal\`. Subsequent non-PII aggregate queries need already documented operator packet and authorization. No SSH/DB PHP/Safety Block bypass.
- All DB fixtures in new tests are **synthetic CI MySQL** with \`RefreshDatabase\`; QAS/Production unchanged. No scheduler, job handle, queue, retry method, scanner, driver send, SMTP/WhatsApp/SMS, real email or provider invoked. No app/config/permission/secret/policy changes.

## Code-derived transition / idempotency matrix
| Existing row | Real email flag at direct \`send()\` invocation | What source presently does | Confidence |
| --- | --- | --- | --- |
| \`scheduled\` with future date | Either | Returns early before attempts, log creation or router; neither job nor provider was invoked | Covered by isolated test |
| \`simulated\` and due | OFF | Increments attempts; sets/retains \`simulated\`, reuses message log; repeated direct call increments attempts again | Covered by direct CI calls, router forbidden |
| \`simulated\` and due | ON | No previous-status early guard; reaches \`router->driverFor\` after incrementing attempts and preparing/reusing the log | Covered ONLY up to intercepted router call; provider send never invoked |
| \`sent\` and due | ON | No previous-status early guard; repeated direct invocation also reaches router | Covered ONLY to intercepted router call; normal \`processDuePending\` query does NOT select \`sent\` |
| \`pending/scheduled/simulated\` and due | runtime flag unknown | \`processDuePending\` would SELECT these states and call \`SendGrowthDeliveryJob::dispatchSync\` | Source inspection **only**, function/job never invoked |
| OFF→ON change between creation and execution | UNKNOWN QAS behavior | Simulated records remain eligible in due selector; send-time flag gates simulation vs route. No live flags, backlog or operator proof | Product decision OPEN; NOT QAS acceptance |

### What the CI tests mean — and do not mean
- New \`tests/Feature/GrowthSimulatedDeliveryBoundaryTest.php\` contains **3** source-only service-contract tests. \`Mail::fake()\` and \`Notification::fake()\` guard outbound messages. Real-email mode is returned by a **mock** \`GrowthCampaignService\`, not persisted as a WebsiteSetting or QAS change.
- Test 1 calls \`GrowthDeliveryService::send\` directly twice in isolated CI DB in OFF mode; the \`GrowthDeliveryRouter\` mock forbids \`driverFor\` entirely. No job/retry method was called. It captures actual repeat-invocation attempts count (1 → 2), same message log, simulated state.
- Test 2 uses OFF→ON equivalent flag=ON with preexisting \`simulated\` and \`sent\` records; the router mock **throws immediately in \`driverFor\`**. This proves the source reaches the routing boundary, **NOT** actual SMTP send, delivery, provider capability or incident. The artificially unfinished queued log under the deliberate exception is not a production outcome.
- Test 3 proves a future \`scheduled\` record short-circuits direct \`send\` with no attempts, no message log and no routing. No schedule/queue/job handle was executed.
- Call-site inspection shows \`processDuePending\` includes simulated records and \`send\` does not check terminal state before flag evaluation. These are source properties, **not evidence of live job backlog or actual unintended email**.
- CI of the new source/test commit must be independently verified \`completed/success\` on exact SHA; no test result is presumed before verification.

## OPEN product/security decision — no implementation
- **Decision needed:** When real email turns ON, should previously simulated deliveries ever become real deliveries? Related contract: should an already-\`sent\` direct re-entry be rejected, and which states are terminal for idempotency?
- Option A (safer default for discussion): \`simulated\` and \`sent\` remain terminal; enabling real emails affects only newly eligible deliveries, without retroactively replaying old simulation data. Would require a separately approved policy and regression before implementation.
- Option B (opt-in historical replay): explicit admin-approved synthetic/test-only reissue using **new** uniquely idempotent deliveries, recorded provenance, recipient guards and auditing; requires separate security design and permission change approval.
- Option C (automatic historical backfill): can reach recipients who never consented to retroactive campaign delivery; do **not** implement without a clear product/legal/security policy and exact duplicate protection.
- No product/security policy choice was made in this slice; no application fix is justified solely by the ambiguous desired transitions. Confirmed observations are **source behaviors**, not a confirmed independent bug. No retroactive or actual outbound traffic is authorized.

## Closure status / continuation
- Scope: source/CI-only tests + documentation. New source contracts may be marked PASS only after exact-SHA full CI success. Runtime/QAS evidence remains UNKNOWN.
- **M3 BLOCKED; M4 PARTIAL/BLOCKED; C1-A Issue #2 BLOCKED; QAS Issues #3/#4/#5/#6 OPEN; Phase 1 Production NO-GO.**
- Next: first authorized QAS phpMyAdmin database identity check as a standalone SELECT. If unavailable, retain hard stop and request a specific Product/Security decision on terminal simulated/sent states before any behavior change.
