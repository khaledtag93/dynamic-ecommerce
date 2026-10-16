# Flowra — QAS Promotion and Repeatable Deployment Checkpoint

Date: 2026-10-16 | Branch: `sec03-framework-upgrade`
Purpose: capture the full chat-to-QAS handoff, verified facts, pending gates and future repeatable-deploy improvement. This is a checkpoint, **not** deployment evidence.

## Current source and CI

- Intended QAS application release SHA: `a682074fddbc0e8e09fd1785a055eaf24d7c5643` (`a682074f`).
- Local and `origin/sec03-framework-upgrade` matched at this SHA; working tree was clean before this documentation update.
- Hardening CI [#38839902319](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38839902319): **SUCCESS**; 981 PHPUnit tests / 18,794 assertions, JavaScript tests 10/10, offline Workforce QA contracts 9/9, and frontend build PASS.
- Recent source/CI closures include Admin Revenue Intelligence period navigation, Storefront Add-to-Cart response integrity, POS cash shift attribution, POS return replay idempotency and Paymob diagnostics redaction. Workforce leave policy/preview/snapshot/approval readiness source work is also CI-tested; live QAS acceptance is separate.

## Last directly observed QAS state (2026-10-16)

- QAS URL: `https://v42.tag-marketplace.com`; latest actual server Git SHA returned **`a62ad480b661920d0d89d627c0d49fbf892e214f`**, so target `a682074f` **has not deployed**. Comparison from this QAS SHA to target: 88 commits, 96 changed tracked files.
- Original authorized Hostinger SSH route with QAS-specific key successfully ran read-only `true`, `git rev-parse HEAD`, `git status`, PHP/version and backup listing. This proves connectivity and read access, **not** permission to run every remote operation.
- QAS `.env` checked: `APP_ENV=staging`, `APP_DEBUG=false`, `DB_DATABASE=u637857322_v42_rehearsal`. Owner also confirms this database is the dedicated QAS database. **Live Laravel `SELECT DATABASE()` result remains unverified**: the attempted tool call was blocked before execution.
- QAS PHP CLI `8.3.35`; public homepage, `/admin/js/off-canvas.js`, and `/js/storefront-cart-actions.js` returned HTTP 200. These are basic read-only smoke checks, not latest-code or authenticated acceptance.
- QAS Git worktree reported `v42-clean-baseline...origin/v42-clean-baseline [ahead 719]`, despite containing `a62ad480`. Untracked local runtime paths: `scripts/qas-queue-cron.sh`, `scripts/qas-scheduler-cron.sh` and `storage/app/`; preserve them.
- Existing private SQL backups were listed; latest observed `qas_before_workforce_scoped_access_20261014_164209.sql` dated October 14, size 7,065,795 bytes, mode 0600. File presence is verified, restoration is not.
- Direct deployment attempt to run existing `bash deploy-qas.sh sec03-framework-upgrade` through Desktop Commander **was rejected before execution**: tool could not determine request safety. Follow-up read-only SHA remained `a62ad480`. This does not prove Hostinger denied a deploy or that a partial deploy occurred.

## Operator decisions and hard boundaries

- Owner expressly prioritizes **consolidated QAS promotion and acceptance before new development slices**.
- Owner says all current QAS records are disposable test data; **fresh QAS backup may be deferred for this QAS-only rehearsal**, knowingly accepting loss of that data. This is NOT a production backup waiver and does not waive destination/environment checks.
- QAS promotion is authorized by the owner; `main`, Production filesystem, production database and live payments are explicitly out of scope. Never treat `SOURCE+CI CLOSED` as QAS acceptance.
- A tool-blocked action must not be retried using a disguised command, alternate transport, GitHub runner, browser, or phpMyAdmin as a bypass. Owner-operated actions and their results need independent, explicit verification.

## QAS promotion checklist — NOT YET COMPLETED

1. Operator and assistant verify actual QAS app path, current SHA, branch/worktree state and `.env` targets; protect untracked QAS cron and storage files.
2. Before any migrations, verify **Laravel's actual connected database** equals `u637857322_v42_rehearsal` using a permitted, approved check. Stop on mismatch, error or tool safety refusal.
3. Confirm owner-approved disposable-QAS-only backup waiver, record that restoration cannot be guaranteed, and perform a fresh backup if practical or if scope changes.
4. Use the existing QAS-only script with explicit `sec03-framework-upgrade` argument **only where execution is permitted**. Its source guards check staging env and expected DB, use a remote Git fetch/reset, Composer, migrations, public sync, and HTTP/static smoke. The script does not make a fresh SQL backup by itself.
5. After a successful deployment independently verify server SHA **`a682074f`**, pending migrations (including `2026_10_15_000100_add_opt_in_leave_request_policies.php` and `2026_10_15_000200_add_leave_policy_snapshot_to_requests.php`), application/static HTTP, queue/scheduler and strict `ops:health`, no failed jobs, maintenance OFF, uploads/storage and existing cron scripts intact. Record PASS/FAIL, not assumptions.
6. Run consolidated authenticated QAS acceptance over Storefront, Admin, POS, Financial/Inventory, and Workforce; track findings. W-ORG-01b seven synthetic personas and 28 language/viewport role journeys remain DEFERRED/OPEN; Workforce concurrency remains BLOCKED.

## Future operational improvement — OPS-QAS-DEPLOY-01 (OPEN; high priority)

**Goal:** repeatable, audited, explicitly approved QAS-only promotion from ChatGPT without repeatedly hand-typing SSH commands or treating tool refusal as a Hostinger failure.
- Investigate the exact Desktop Commander/tool safety denial for the deploy and database-check operation through the supported administrator/provider approval process. Do **not** disable safeguards or bypass a refusal.
- Design an officially permitted, least-privilege deployment channel with target-environment/DB identity checks, explicit SHA pinning, CI green gate, operator approval, migration/backup-waiver visibility, runtime-script preservation, observability, and fail-closed errors.
- Verify QAS branch-label mismatch and destructive-reset/asset-sync edge cases before automating. Include post-deploy SHA, health, migrations, queue/scheduler, rollback limitations and a concise checkpoint response.
- Do not activate a new CI/operations workflow, secrets, production access or scheduled deployment without a separate reviewed scope and user approval. This is a **future work item**, not implemented now.

## Resumption point

**Target source:** `a682074f` / CI `#38839902319 GREEN`. **QAS:** `a62ad480`, promotion NOT RUN. **Next:** guided operator read-only preflight and verified QAS database identity, then QAS-only promotion where authorized; report each step. No new feature scope, no Production change.
