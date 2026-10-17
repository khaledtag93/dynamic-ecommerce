# Flowra — M3/M4 Growth Entrypoint Concurrency & Atomic-Claim Source Review (2026-10-17)

## Baseline & hard QAS identity gate
- Starting [checkpoint \`52d942f7504a42328b43939554aae2dbe3849ae2\`](https://github.com/khaledtag93/dynamic-ecommerce/commit/52d942f7504a42328b43939554aae2dbe3849ae2) / [full CI #39059767896](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39059767896), independently verified **completed/success on exact SHA, 1,024 tests / 19,761 assertions**.
- Reviewed [due-selector and cooldown evidence](GROWTH_DUE_ELIGIBILITY_COOLDOWN_SOURCE_AUDIT_2026-10-17_AR.md), \`CURRENT_PHASE.md\`, \`PROJECT_MASTER_STATUS.md\`, and [Product/Security decision Issue #12](https://github.com/khaledtag93/dynamic-ecommerce/issues/12) (**OPEN**).
- No accessible, authenticated, explicitly authorized QAS phpMyAdmin browser SQL tab in current tool set. **No SQL was executed, including SELECT DATABASE().** Only if an authorized browser tab is available: execute \`SELECT DATABASE() AS connected_database;\` **alone first** and STOP until the returned value equals exactly \`u637857322_v42_rehearsal\`. Only then consider the previously approved non-PII aggregated [operator packet](QAS_M3_M4_PHPMYADMIN_OPERATOR_READONLY_PACKET_2026-10-17_AR.md). No SSH, DB PHP, remote terminal SQL, safety bypass or guessed environment identity.

## New bounded question: does scheduler overlap prevention also protect Growth delivery execution?

| Source entrypoint | Observable call path | Boundary / what was **not** established |
| --- | --- | --- |
| \`app/Console/Kernel.php\` scheduled \`growth:run\` | Has \`withoutOverlapping()\`; command calls \`GrowthCampaignService::runNow()\` | Overlap control applies to the scheduled command, not a universal per-delivery mutex |
| \`app/Http/Controllers/Admin/GrowthController.php::runNow\` | Calls \`GrowthCampaignService::runNow($campaign)\` directly | Source does not use the scheduler's \`withoutOverlapping\` lock; admin action was **not invoked** |
| \`GrowthCampaignService::runNow\` | If engine enabled, processes candidates, then calls \`GrowthDeliveryService::processDuePending()\` | Scheduled and direct admin entrypoints share a service. No source-level per-delivery lock at this call boundary |
| \`GrowthController::retryDelivery\` / \`GrowthCampaignService::retryDelivery\` | Calls \`GrowthDeliveryService::retry\`, which sets status and invokes \`SendGrowthDeliveryJob::dispatchSync\` | Independent explicit retry route; it was **not invoked** and no permission or retry policy is changed |
| \`GrowthDeliveryService::processDuePending\` | Reads due \`pending/scheduled/simulated\`, loops selected records and \`dispatchSync\`s each | No visible atomic claim/compare-and-set (\`where status...\` guarded update), \`lockForUpdate\` or transaction wrapping select-and-dispatch in this method |
| \`GrowthDeliveryService::send\` / \`SendGrowthDeliveryJob\` | Loads delivery, checks only future schedule, increments attempts and later reaches driver; job implements \`ShouldQueue\`, and dispatch can be synchronous | No terminal-state early return or per-delivery claim; job has no \`ShouldBeUnique\` contract. These are *source facts*, not demonstration of concurrent processing, real delivery or a duplicate incident |

### Risk classification and strict evidence boundary
- **Structural re-entry / claim gap: SOURCE OBSERVED.** A scheduler mutex cannot establish idempotency across admin-triggered run, explicit retry and direct service calls. No delivery-atomic claim visible along the specific inspected path.
- **Duplicate real email or two requests racing: NOT PROVEN / UNKNOWN.** No concurrent calls, job execution, provider driver, mail, scheduler, queue, scanner, retry or QAS DB queries were attempted. Effective QAS real-email configuration, record backlog and session/role permissions were not observed.
- \`dispatchSync\` means normal queued-job uniqueness must not be casually assumed to dedupe this synchronous path; do not infer actual repeat sends from its existence.
- Schema files reviewed: \`create_growth_deliveries_table\`, \`alter_growth_deliveries_for_batch_d\`, \`create_growth_message_logs_table\`, \`alter_growth_message_logs_for_batch_c\`. The specific growth delivery/message-log relationships have FKs and indexes, but **the inspected migration definitions do not declare a unique delivery claim/idempotency key**. This is not a comprehensive assertion about every migration or deployed QAS schema.
- \`recentlyTriggered\` is campaign+recipient cooldown for *new candidate selection*, **not** a guard over an existing delivery's status or idempotency; see prior validated CI tests. No duplicate selector/cooldown tests were created here.

## Why no fix or fresh regression in this slice?
- The contract for whether \`simulated\` is terminal or eligible for historical replay, whether \`sent\` can ever be explicitly retried, who may request replay, and what uniqueness/fairness key should be enforced remains **OPEN Product/Security decision #12**. Silently adding locks, filters, backfills, queue semantics or retry restrictions could change Business Policy and require schema/migration changes.
- This review found **no separately demonstrated functional bug independent of that decision**. A concurrent send claim would require new explicitly approved, controlled, zero-provider reproductions with a defined expected contract. Do not invent a defect from a missing design primitive.
- No new tests were added: prior closed tests already establish static eligibility and single-call simulated behavior. Repeating them would add noise, not acceptance evidence. **No app code, settings, policy, permission, secrets or schema changes** were made.

## Practical unblock / next action (do not mistake source CI for QAS acceptance)
1. Authorized QAS phpMyAdmin identity SELECT, alone, exact name match. If missing UI, **BLOCKED**, do not substitute another route.
2. After PASS only, run *previously permitted* aggregated SELECT packet (allowlisted flags / active-rule categories / pending and failure counts / job type hints). No recipients, payloads, PII or secrets. The packet does **not** establish concurrent-send safety.
3. Owner Product/Security chooses a terminal/replay/idempotency contract under [Issue #12](https://github.com/khaledtag93/dynamic-ecommerce/issues/12) before any code change. A separate approval would be needed for concurrent-processing tests.
4. Record expected QAS evidence, CI at exact commit SHA and acceptance outcomes; do not claim M3/M4 green from source inspection.

**M3 BLOCKED; M4 PARTIAL/BLOCKED; C1-A Issue #2 BLOCKED; QAS Issues #3–#6 OPEN; Phase 1 Production NO-GO.**
