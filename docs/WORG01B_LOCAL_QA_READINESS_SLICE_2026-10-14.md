# W-ORG-01b — Local QA Readiness Slice (2026-10-14)

**Scope:** repair and harden the W-ORG-01b QAS helper tools without any QAS/Production connection. Source base `b7a1eb31`, branch `sec03-framework-upgrade`.

## Changes delivered

- `ops/qa/worg01b_personas.php`: correct Laravel root to `dirname(__DIR__, 2)` and require existing `vendor/autoload.php` and `bootstrap/app.php` before bootstrap. Preserve original seven synthetic QA accounts, org charts, attendance and leave fixtures and `--verify-auth`.
- Add `--cleanup-dry-run`: inspect tracked manifest IDs, verify all five fixture table row counts, enumerate **actual foreign-key references in information_schema**, refuse untracked child references, check role assignment count, and block when active database sessions for the QA user IDs remain. Results contain aggregate counts/blocks only, never passwords.
- `--cleanup` calls the same dry-run impact assessment, aborts on unmanaged dependencies, deletes **exact fixture IDs** inside a transaction, and asserts pre/post table counts including `role_user` within that transaction. Manifest is removed only after SQL commit, and file deletion failure is reported.
- Protect fixture escrow lifecycle: write `worg01b-personas-manifest.json.pending` with restricted permissions within the database transaction, rename to final manifest only after commit, refuse reseeding on stale pending escrow. **A crash or rename failure requires manual operator reconciliation** (do not delete `.pending` blindly).
- `tests/browser/workforce-authenticated-readonly.cjs`: preserve 7 personas / EN+AR / desktop+mobile GET-only tests and diagnostic selectors; add deterministic `search=QA-` for scoped employee, attendance and compensation lists to prevent first-page false negatives in populated QAS.
- Add explicit `--mutation-denials` mode for one English desktop pass per selected role. Requires `FLOWRA_QA_ALLOW_MUTATION_DENIAL_TESTS=YES-QAS-ONLY` **and** an external private manifest showing disposable QAS approval and verified live QAS DB. Checks carefully selected 403 actions on **QA-owned IDs only**, with missing fields for employee/compensation PUT to avoid valid updates; any 419/422/302/unexpected response fails.
- `ops/qa/worg01b_browser_login_diagnostic.cjs`: previously untracked local work preserved and included for future authorized login diagnostics (prints aggregate HTTP/session booleans only, no secrets).
- `tests/browser/workforce-qa-contract.test.cjs`: **9 offline Node regression tests** for matrix scope, filtered routes, constrained mutation targets, missing fixtures, root and manifest checks and fail-closed execution. Integrated into `.github/workflows/hardening-ci.yml`.

## Local evidence

- `node --test tests/browser/workforce-qa-contract.test.cjs`: **9/9 PASS**.
- `php -l ops/qa/worg01b_personas.php`: PASS.
- `node --check` for authenticated browser runner and login diagnostic: PASS.
- `git diff --check`: PASS.
- Source reviewed; no browser login, SSH, external DB command, fixture seed, import, delete, Production/QAS deploy or QAS write was attempted.
- Prior run [CI 38633838075](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38633838075): GREEN on source baseline `b7a1eb31`. Final CI run for this slice must be recorded after the code commit.

## Important security/functional limits

- **Remote admin safety restriction remains in force.** Never attempt SSH or switch transports to bypass it. New Hostinger SSH public key registration does not waive the restriction.
- **The mutation-denial runner intentionally sends HTTP requests to the QA server ONLY after explicit authorization and QAS DB verification.** A 403 result alone is not enough for closure; compare persistence/side effects on QAS fixture-only rows before/after the test.
- The cleanup impact assessment relies on declared FK references and a session check. Non-FK audit, queue, polymorphic and external integration records may need extra reconciliation. If dependencies are found, cleanup **stops** rather than dropping arbitrary records. Review the dry-run and repair only known fixture-linked artifacts with approval.
- Pending-manifest escrow cannot make SQL and filesystem commits fully atomic. Manual reconciliation is required for a rare post-commit/pre-rename crash. Do not mark cleanup successful until the private file is removed and QA rows are absent.
- The script cannot yet run on a QAS host until it is **approved and deployed there**, QAS source SHA matches the tested commit, runtime `APP_ENV=staging`, `APP_DEBUG=false` and actual `SELECT DATABASE() = u637857322_v42_rehearsal` have been verified read-only.
- Private manifest must remain outside GitHub, CI artifacts, chat, screenshots and public paths. The `.gitignore` rule `/storage/app/private/` is already present.

## Single acceptance run — after approved access only

1. Confirm authorized access (tool safety cleared) and QAS-only deployed SHA, runtime DB, role policy and backup metadata. No Production.
2. On verified QAS only: `php ops/qa/worg01b_personas.php --preflight`; proceed only on PASS and no colliding QA identities or manifest.
3. Seed **once** with `--seed`; verify `--verify-auth` (7/7). Keep private fixture manifest secure; move to browser test device only through an approved secure transfer and an absolute path **outside Git**.
4. In the authenticated browser environment, with `FLOWRA_QA_PERSONAS_FILE` pointing to that private file, run `node tests/browser/workforce-authenticated-readonly.cjs` across 7 roles x 2 locales x 2 viewports (28 sessions), record failed and passed routes, screenshots/trace only after redaction.
5. After explicit approval, run `FLOWRA_QA_ALLOW_MUTATION_DENIAL_TESTS=YES-QAS-ONLY node tests/browser/workforce-authenticated-readonly.cjs --mutation-denials` and compare targeted QA row statuses/contents before/after; do not accept 419/422/redirect as 403.
6. Review `php ops/qa/worg01b_personas.php --cleanup-dry-run` and any dependent QA records; only if `READY` run `--cleanup`, then verify all QA profiles, users, attendance, leave and manifests were removed with unrelated baseline unchanged.
7. Record the outcome and live QAS SHA in `CURRENT_PHASE.md`. **W-ORG-01b CLOSES only after QAS acceptance evidence and cleanup reconciliation PASS.**

**Closure status now:** OPEN — authorized QAS access blocked. Local QA-readiness source and offline regression are prepared, subject to CI.
