# Flowra — QAS-only rollback safety guard (SOURCE-ONLY checkpoint, 2026-10-18)

## Decision and scope

**Delivered:** a minimal, pure-PHP, offline **rollback readiness validator**, not a remote or local rollback executor. **Not delivered:** a production/QAS deployment script, recovery execution capability, code/asset backup, QAS remote connection, environment proof or migration undo.

This intentionally limited implementation makes the QAS-only rollback **fail closed** under the user's current prohibition on SSH/SQL/PHP-at-QAS, blocked-tool workarounds, Deploy, QAS/Production writes, provider sends, queue/scanner/retry and policy/config changes. It cannot grant itself execution authority. This is **not a method to bypass any previous tool safety block**.

- Source baseline before this slice: `56bb5e893973324322f67e6236ec7b49343b5c75` / GitHub Hardening CI #39066102230 `completed/success` (1,030 tests / 19,844 assertions).
- Planned code recovery baseline, pinned as **full immutable SHA**: `de180de6cae5f28dc867b752ab6f1d97baa71416`.
- QAS destination: `v42.tag-marketplace.com`; app `laravel_app_v42_rehearsal`; public `public_html/v42`; expected isolated MySQL database `u637857322_v42_rehearsal`.
- Production `tag-marketplace.com`, `laravel_app`, `public_html`, generic `deploy.sh` and `rollback.sh` are **strictly out of scope**.
- Existing owner decision [Issue #2 comment #6240991790](https://github.com/khaledtag93/dynamic-ecommerce/issues/2#issuecomment-6240991790): one-time fresh **QAS database backup waiver** for disposable test data; potential irrecoverable DB loss accepted. No deployment or rollback execution authorized.

## Files

- `app/Support/QasRollbackReadiness.php` (new): pure `assess(array $facts): array` returning only `BLOCKED` or `REVIEW_READY_ONLY`, a narrow allowlist of non-sensitive blocker codes and a fixed QAS protected-path inventory. **`execution_authorized=false` under ALL inputs**. No app boot, Laravel services, network, file read/write, shell or database interaction; not registered as an HTTP route or operational command.
- `tests/Unit/QasRollbackReadinessTest.php` (new): isolated synthetic regression tests requiring neither app bootstrap nor runtime access; they cover missing facts, synthetic fully positive *non-executing* evidence, exact host/path/database/env, unsafe traversals and Production paths, provenance that excludes phpMyAdmin/hPanel-Git/`.env` or historical reports, full SHA and matching CI evidence, absent pending migrations/schema compatibility, code/public-asset recovery, protection of runtime paths, explicit approval/channel conditions and static ban on execution APIs.

## Strict evidence and protection contract

The guard requires separately obtained operator assertions for:
- Only the **exact** QAS hostname/app path/public path and **actual connected Laravel runtime** environment `staging` and DB `u637857322_v42_rehearsal`. Git metadata and phpMyAdmin-selected DB are **not** substituted for Laravel's running DB identity.
- A full 40-digit *observed running release SHA* == full 40-digit *reviewed source release SHA* == full 40-digit *completed-success CI SHA*, **distinct** from and explicitly paired with the exact old recovery SHA above. The current `v42-clean-baseline` branch label, short hashes or moving `origin` branch tips are **not acceptable release pins**.
- A demonstrably clean app working tree, **0 verified pending migrations**, independently checked DB schema compatibility with the old code, a verified **QAS-only code+public-assets recovery point**, checked preservation of all QAS runtime-only paths, a permitted official operator channel and a separate specific rollback approval. Unknown, omitted, loosely typed, unapproved or inconsistent evidence is **BLOCKED**.
- Explicitly protect `.env`, `storage/` and `storage/app/`, untracked `scripts/qas-queue-cron.sh` and `scripts/qas-scheduler-cron.sh`, `public_html/v42/uploads/`, `public_html/v42/storage/`, QAS-specific public `index.php` and uploads `.htaccess`. Preserve permissions/symlinks and avoid overwriting runtime-generated data. This list is **an inventory**, not confirmation that copies exist. A code-only rollback must never automatically revert DB tables or migrations.

**Important distinction:** `REVIEW_READY_ONLY` is an offline consistency result computed from caller-supplied metadata. It is **not proof that the metadata was actually measured on QAS**, and even artificially supplied positive facts do not unlock a runnable rollback. There is no exposed command to run and no live execution authorization mechanism.

## What remains needed before any practical rollback (separate permission and engineering)

1. Resolve current **BLOCKED** first-party QAS runtime `APP_ENV` and Laravel database identity through a pre-existing officially permitted read-only surface; do not inspect `.env`, run terminal/DB PHP/SQL, or route around blocked tools under the current restrictions.
2. Obtain fresh, independently verified QAS-only code-and-asset recovery snapshot while protecting untracked runtime files. Database backup waiver applies to *database only*. This may require separate authorized operator work and is **not undertaken here**.
3. Establish and test an officially permitted, QAS-specific restore mechanism/rollback SOP with explicit, pinned SHA and file allowlists, compatible app and database schema, approved maintenance/worker-handling plan, independent post-restore SHA and functional checks. Current `deploy-qas.sh` has branch-tip reset, `migrate --force`, `queue:restart`, `rsync --delete` and writes before its DB preflight. Existing `rollback.sh` is a **Production-only** script, never usable for QAS.
4. No database restore or down-migrations, mail/WhatsApp/other provider sends, scanning, retries, queue restart or cleanup can happen automatically. Unsafe or unknown state means STOP/incident review, not destructive repair.
5. Only a **new explicit scope and approved operator execution channel** may authorize a future QAS rollback or deploy; this source-only guard does not supply either.

## Phase 1 gates

**Source-only guard tests are CI evidence only.** They do not alter QAS/Production and do not close live M3/M4 gates. Preflight runtime identity and restoration feasibility remain **BLOCKED**.

- M3 **BLOCKED**; M4 **PARTIAL/BLOCKED**.
- C1-A Issue #2 **BLOCKED**; Issues #3–#6 and Growth Issue #12 **OPEN**.
- QAS promotion **NO-GO**; Phase 1 Production **NO-GO**.

**Final CI:** verify the exact final branch SHA and associated workflow `completed/success` before closing *this source-only slice*. Do not infer Green from a previous commit.
