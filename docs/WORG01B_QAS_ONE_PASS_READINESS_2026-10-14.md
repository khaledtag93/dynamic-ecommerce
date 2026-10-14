# Flowra W-ORG-01b — One-Pass QAS Acceptance Readiness (2026-10-14)

**Status:** readiness review complete; **QAS authenticated acceptance NOT RUN**.
**Source checkpoint at review start:** `28c4bf44`, branch `sec03-framework-upgrade`.
**CI:** [Hardening #38632785294](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38632785294) completed **success** (isolated CI; not QAS browser acceptance). Historical full suite from earlier confirmed run: 907 tests / 18,230 assertions.
**Security:** remote SSH has an explicit tool safety block. **No SSH attempt or substitute transport in this review; no QAS data changes, no Production changes**.

## Files reviewed read-only and preserved locally

| File | State | Local SHA-256 at review |
| --- | --- | --- |
| `ops/qa/worg01b_personas.php` | Modified; NOT staged | `8506ffc6f93e5951f79601c467a6c5795dc33793c0a4aa2782a059814b239196` |
| `tests/browser/workforce-authenticated-readonly.cjs` | Modified; NOT staged | `3af755d4f703ae34d5fdd4705cf5d2bf74aa026fc13be748c955f9bfde81aa88` |
| `ops/qa/worg01b_browser_login_diagnostic.cjs` | Untracked; NOT staged | `34e247536d16bb94322dc71d9366c86e2e67e05190d01ed7105b4ac4d9c65b57` |

Local `php -l` and both `node --check` commands succeeded. No application code in these three files was edited or run against QAS.

## Findings and gates (priority order)

### B1 — Seeder root path is wrong (confirmed, must fix BEFORE seeding)

Line 14 of `ops/qa/worg01b_personas.php` uses `$root = dirname(__DIR__, 3);`. Because the file resides in `<laravel>/ops/qa/`, resolving three parent levels yields `<laravel-parent>`, **not Laravel's root**. Local path verification confirms dirname levels 1=`ops`, 2=`laravel repo`, 3=`workspace`. Bootstrap via `vendor/autoload.php` will fail or target the wrong tree. **Fix to two parent levels**, then add a regression or static guard validating expected bootstrap/app files in the intended QAS app path. Do not execute this script against QAS in its current form.

### B2 — Sensitive manifest should be excluded from Git (repository fix applied)

The fixture manager writes seven synthetic plaintext account passwords and IDs to `storage/app/private/worg01b-personas-manifest.json` with private directory/file permissions (0700/0600). The repository lacked an explicit ignore for `storage/app/private/`. A **separate** `.gitignore` change now excludes that entire private directory; `git check-ignore -v --no-index` returned PASS. No private manifest currently exists locally or is tracked. Never commit or print its contents.

### B3 — Source/deploy provenance before QAS commands (hard gate)

A Green CI on the branch does **not** prove that QAS is running that SHA or that local modified/untracked QA helper scripts are present on the remote host. After approved remote access, read only: `APP_ENV=staging`, `APP_DEBUG=false`, `DB_DATABASE=u637857322_v42_rehearsal`, live `SELECT DATABASE()`, full deployed source SHA, expected app dir and `v42.tag-marketplace.com` route. Verify scripts are deployed to the **QAS app only**, after a green CI for any code fixes. No Production or other database operation.

### B4 — Browser coverage has gaps (before formal role acceptance)

- Existing `workforce-authenticated-readonly.cjs` targets seven personas, two locales (en/ar), two viewports (desktop/mobile): **28 logged-in journeys** with GET-only status/visibility checks.
- It does **not** test actual forbidden state-changing HTTP actions: outsider profile PUT, cross-team shift updates, leave self-approval and outsider approval PATCH, compensation/payroll PUT/PATCH, self-service unauthorized reads, record integrity after denied writes. Add controlled synthetic-only mutation-denial cases and verify no change in DB aggregates/record hashes for targeted QA rows.
- Pagination matters: Employee directory defaults to **15 rows/page**, attendance to **20**, Finance compensation to **25**. In the disposable-but-populated QAS environment, visible test codes may be off page, causing false FAILs. Use an explicit `search=QA-` filter (where supported) and verify absence/presence against scoped results and aggregate counts; do not mistake a first-page absence for permission enforcement.
- The login diagnostic checks **Alpha Manager only**; it can establish login/post/session behavior but does not prove all seven roles work. The main browser runner does not save screenshots/traces or visual accessibility signoff, nor perform controlled QA fixture cleanup.

### B5 — Fixture semantics and transport between host/browser

- Seeder provides seven users (Alpha Manager/Lead/Worker, Beta Manager/Worker, Operations, Finance), manager links, **2 attendance sessions**, a leave type, **2 leave requests**, namespace `QA-WORG01B`, and separate QA emails. Laravel `User` model has **password => hashed** cast: hashing is handled on model creation (source verified).
- `--preflight` checks staging, live DB identity, roles/permissions and fixture duplicates. `--verify-auth` checks hashes and role membership for all seven, but cannot substitute for actual HTTP logins or role-scope assertions.
- Seeder generates a private manifest *on the QAS host*. The browser runner expects `FLOWRA_QA_PERSONAS_FILE` to be a **private absolute path outside the local Git repository**. A secure, explicitly authorized QAS-to-tester manifest transfer/storage procedure is missing. Do not print credentials to logs, paste to chat, or put them in GitHub Actions artifacts. Restrict access and delete after use; use only approved data transfer.
- Ensure outbound mail, integrations, queues and payroll/payment side effects are disabled/sandboxed for synthetic accounts before performing any state-changing acceptance.

### B6 — Cleanup is scoped but needs hardening and evidence

- Current `--cleanup` checks manifest namespace, all seven email/profile identities and uses exact IDs in one DB transaction. **Good safety basis**, but no separate `--cleanup-dry-run`/impact report exists; it does not enumerate other QA-created relational or audit/notification/session records, and final proof is only a seven-email count.
- Before applying cleanup, inventory FK children, model events, clock corrections, payroll/compensation, shifts, employee leave adjustments, activities, sessions and dependent events for the QA IDs. Print **only aggregate counts**; block on unexplained dependencies or non-QA references. Consider targeted FK-safe ordering, dry-run impact preview, and checks of all QA codes/IDs plus unmodified pre-existing row counts after cleanup.
- Manifest is written to filesystem within an SQL transaction; file and DB transaction are not one atomic resource. Include recovery steps for a crash between file write and commit, and ensure no orphaned or partially removed artifacts.
- The owner's disposable-QAS risk acceptance permits losing current QAS test data; do NOT convert that into indiscriminate table drops, Production wipes or silent claims of successful cleanup.

## One coordinated acceptance pass — only after explicit authorized admin access

| Stage | Action | Required proof / fail-closed decision |
| --- | --- | --- |
| 0. Stop gate | Existing SSH safety block must be resolved through supported authorization; no attempts through alternate transports | **No remote action until expressly allowed** |
| 1. Source readiness | Preserve current local edits; fix B1 and B2/B4–B6 safely in a dedicated reviewed slice; targeted tests; CI Green; approved QAS-only deploy if needed | Source SHA, CI run, QAS app SHA match |
| 2. QAS read-only preflight | Check staging app, runtime DB, permissions, role policy, stored backup metadata | Non-secret snapshot/evidence, exact target; abort on mismatch |
| 3. Fixture seed | Execute QAS-only `--preflight`, confirm zero collision, seed **once**, verify 7 identities/roles and bounded sample workforce records | Exact-ID private manifest, DB counts, verify-auth PASS; abort on partial fixture |
| 4. Authenticated browser | Securely stage the manifest on test device outside Git; 7 roles x EN/AR x desktop/mobile GET checks, locale, direct URL denials, scope, payroll separation | Per-role/page 200/403, expected vs actual, screenshot/traces scrubbed |
| 5. Mutation-denial checks | Only QA-namespaced data; protected actions with expected 403 and negative side-effect assertions. Approved positive actions if part of acceptance | No cross-team/own-approval changes; lawful positive actions persisted |
| 6. Cleanup & reconciliation | Review dry-run manifest/relationship counts; exact-ID cleanup (or explicitly signed-off QAS-only reset at future boundary), verify no QA artifacts remain | Pre/post QA-only counts and unchanged unrelated rows |
| 7. Formal closure | Evidence links, defect list, CI state and QAS runtime SHA | **CLOSED only if 1–6 pass; otherwise OPEN with concrete blocker** |

**No need for another export of existing expendable QAS data**. Current archive static integrity PASS; actual isolated restore remains UNVERIFIED (deferred under owner policy).

## Current decision

**W-ORG-01b OPEN / BLOCKED (SSH tool safety + remaining code/readiness fixes).** Production unchanged. The three local uncommitted QA scripts were read only and not staged. This document is a preparation artifact, **not** live test results.
