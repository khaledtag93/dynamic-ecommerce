# Flowra — Repeatable QAS Backup, Restore and Workforce Acceptance Runbook

- Created: 2026-10-14
- Environment: QAS only (Hostinger / `v42.tag-marketplace.com`)
- Project workstream: W-ORG-01b
**Status:** reusable procedure documented; first current SQL export and authenticated QAS acceptance NOT yet complete.

## Fast-path for disposable QAS test data — owner policy (2026-10-14)

- Owner confirmed **all existing QAS records are expendable synthetic/garbage test data**, and explicitly prefers one coordinated, low-friction testing pass rather than repeated screenshots or manual approvals for every harmless step.
- **One-pass workflow:** confirm target is QAS, finish Custom SQL export once (optional archival safety copy), verify private download metadata and checksum locally, then use an **approved administrative access channel** to prepare seven isolated test personas, run automated role journeys, record results and clean only known fixture IDs or agree on a verified QAS-only reset after testing.
- For this **disposable QAS workstream**, the owner does **not** require a successful isolated restore rehearsal to preserve the current expendable database contents. The restored-backup claim must remain **UNVERIFIED** until a separate restore drill actually passes; restore testing remains mandatory before a future production/important-data recovery workflow.
- This policy change is **not** permission to modify Production, change database users/privileges, discard unrelated environments, or bypass tool safety checks. Verify app-to-DB mapping read-only before any fixture writes or reset; the presence of a second database on the same host increases the importance of target isolation.
- Export screenshot evidence (2026-10-14): many visible tables have Structure+Data checked, **Save output to a file** selected, UTF-8, Compression=None, **Enclose export in a transaction** enabled; foreign-key-check disabling appears OFF. The image only shows a section of the options and the top of the table list. Do not claim every table or bottom option is verified.
- **Minimal final click path:** ensure Select all tables + Structure AND Data; keep file output, UTF-8, SQL, transaction ON, CREATE TABLE/other applicable objects ON, DROP and CREATE DATABASE/USE OFF. gzip optional. Then export **once** and confirm a nonempty file; no need for repeated screenshots unless an unexpected option/error appears.
- Stage-gate still **BLOCKED** for live QAS persona creation: the normal remote SSH administrative action was rejected by the platform's safety check. Do not route around that restriction via phpMyAdmin SQL writes, alternate remote shells or side-channel credentials. Read-only export through the owner's approved hPanel UI is allowed.


## Why we do this

The goal is **reliable, evidence-based acceptance** of Team Supervisor/Employee/Operations/Finance authorization before closing W-ORG-01b. A database export is merely a recovery prerequisite, not the delivery objective.

The one-pass steps for the current **disposable QAS** lane:

1. Confirm the actual QAS database; optionally capture **one fresh export** and validate its metadata/checksum before fixture writes.
2. With approved administrative access, create seven clearly namespaced synthetic test personas and Workforce records, tracking inserted IDs and side effects.
3. Run authenticated role/ownership checks, direct-route denials, EN/AR/RTL desktop/mobile and applicable mutation scenarios; record actual results without secrets.
4. Verify persistence; clean fixture IDs or document a future QAS-only reset, then make the formal W-ORG-01b closure decision.

**Separate protected-data lane:** verify a full isolated restore (schema, records and routines) before any risky changes to Production or important environments. This drill is recommended for developing a reliable backup SOP but is not required to preserve expendable QAS data.

No CI Green, HTML 200, SQL download, or database filename is sufficient by itself for W-ORG-01b closure.

## What has already been verified in Hostinger hPanel

- Go to **Websites > tag-marketplace.com > Files > Backups**. Under **Restore and download > Database backup**, a weekly snapshot dated **2026-10-08 18:52** was visible; a download control exists. **Manual backups** were shown **Locked**, and automatic backups **Weekly**. Old snapshots are not a current pre-fixture recovery point.
- Go to **Databases > Management**. Two visible databases:
  - `u637857322_v42_rehearsal` — **40 MB** in Hostinger, DB user `u637857322_v42test`, created 2026-09-20; **expected QAS target** based on previous deployment/checkpoints, but verify the app's DB name by an authorized read-only configuration check before writes/restores.
  - `u637857322_tagmarketplace` — **9 MB**, created 2026-04-01; likely the other environment. **Do not access/mutate it for this workflow.** A name alone is not proof it is Production.
- Select **Enter phpMyAdmin** for `u637857322_v42_rehearsal`. Check the top breadcrumb/database header matches *exactly* `u637857322_v42_rehearsal`. The **Export** tab has been reached; screenshot currently shows **Quick** selected and format **SQL**. No export has yet been demonstrated.
- The Hostinger website, database names, sizes, menu state and backup availability were observed from owner screenshots. None of these observations proves current QAS app environment configuration, download integrity, or restore readiness.

## Repeatable phpMyAdmin export procedure (read-only)

1. From the **database root view** (not an individual table), verify the header names `u637857322_v42_rehearsal` and select **Export**.
2. Choose **Custom — display all possible options** instead of Quick, keeping format **SQL**. **Stop and review all newly displayed settings before clicking Export/Go**; option labels can differ by phpMyAdmin version.
3. Choose **all tables** and **Structure and data**. Include applicable **views, triggers, routines and events** where available. Prefer transactionally consistent InnoDB export options (e.g. single transaction) where supported; do not enable destructive commands or change the database itself. Review options before making the actual selection.
4. Choose a download-to-file output, not display-as-text. If compression (e.g. gzip) is offered and supported, it may help. Use a descriptive private filename such as `flowra_qas_before_worg01b_YYYYMMDD_HHMM.sql` (or `.sql.gz`).
5. Only after reviewing settings, **Export**. Confirm the downloaded file exists and has a nonzero size. Do **not** share the SQL in chat, email or Git: it may contain employee/customer/payment-related information, hashed credentials and other sensitive data. Keep in a restricted/encrypted location.
6. Privately record: timestamp/timezone, selected database, file extension/size, checksum (SHA-256) and approximate counts of critical tables (read-only). Do not print records/passwords.
7. A nonempty export is **not yet restore-verified**. Run an isolated restore rehearsal before claiming the backup is fully recoverable or before using this procedure for important/Production data. On the owner's disposable QAS testing lane, this optional drill can be scheduled separately without mislabeling it PASS.

**Prohibited actions in this UI:** `Import`, `Restore database`, `Drop`, `Empty`, `Delete`, privilege/password changes, or importing a dump back into the original QAS database. **No paid upgrade required at this stage.**

### Verified menu labels: QAS phpMyAdmin Custom SQL Export (2026-10-14)

The owner shared the full list of options on the **Export** page for `u637857322_v42_rehearsal`. **Important: the pasted textual page does not reveal which individual checkboxes are presently selected.** Use this as a target configuration and review the visible checked/unchecked controls before exporting.

| Setting shown in phpMyAdmin | Target for pre-fixture archive |
| --- | --- |
| Export method / Format | **Custom** / **SQL** |
| Tables | **Select all**; include **Structure AND Data** for every table (do not exclude large tables) |
| Output | **Save output to a file**, not **View output as text**; UTF-8 |
| Compression | **gzip** if offered, otherwise **None**; verify the downloaded extension |
| Export tables as separate files / Skip tables larger than | Off / leave size threshold empty |
| Use LOCK TABLES statement | Off when making a transaction-consistent InnoDB dump |
| Enclose export in a transaction | On; improves source consistency for InnoDB tables but is not a substitute for verifying restore correctness |
| Disable foreign key checks | On for imported SQL's FK handling in an **isolated restore**; this does not issue a QAS mutation at export time |
| Export views as tables | Off; use CREATE VIEW where actual views are present |
| Add CREATE DATABASE / USE statement | Off so restored SQL does not select or create the original database automatically |
| Add DROP TABLE / VIEW / PROCEDURE / FUNCTION / EVENT / TRIGGER statement | Off for a non-destructive, empty-target restore |
| Add CREATE TABLE / CREATE VIEW / CREATE PROCEDURE, FUNCTION, EVENT / CREATE TRIGGER statements | On for applicable objects the export account may read |
| Truncate table before insert / INSERT DELAYED / INSERT IGNORE | All Off |
| INSERT function and syntax | INSERT; prefer **both of the above** (column names plus multiple rows) |
| Enclose table and column names with backquotes | On |
| Dump binary columns in hexadecimal notation | On if available, to safely represent binary data |
| Dump TIMESTAMP columns in UTC | On for consistent timestamp export/import across time zones |
| Database system/older MySQL compatibility | NONE (unless the isolated target requires a documented compatibility mode) |

**Verification caveats:** This list is a **planned configuration**, not yet confirmed as applied; do not mark the export as complete from the textual capture alone. Confirm that any views/routines/events/triggers actually exist and were included; some shared-host privileges may omit objects or prevent complete exports. If phpMyAdmin times out or the file is unexpectedly small, stop and investigate rather than marking backup PASS. Record private file size and checksum, then perform isolated restore and counts verification before seeding QA personas.


## Isolated restore verification (future protected-data gate; deferred for current expendable QAS testing)

- Provision a **new disposable database with a distinct name and credentials** using an approved separate environment; do not confuse this with a second schema of an existing live environment.
- Restrict access and outbound emails/jobs/payments. Import the saved QAS SQL **only** into that disposable target via an approved tool.
- Check expected table list/schema, migrations, table counts and a few *non-sensitive aggregate counts* against the source snapshot. Check triggers/routines/events if applicable. Document SQL-import errors and compatibility.
- Prove the rollback path is usable; a backup is NOT ready if the full import fails or differs materially.
- Destroy or securely retain the disposable restored copy under an agreed data-retention policy. **No production or original-QAS restore** for this test.
- Do not claim **RESTORE-VERIFIED** or **recoverable backup PASS** without dated evidence of an isolated successful restore. Current disposable-QAS testing may proceed through approved access without that claim.

## Seven-role QAS acceptance and cleanup (future gate)

- **CI-only proof:** `f0392366` tested seven synthetic profiles and role boundaries. CI on `a0b3375d` was GREEN: **907 tests and 18,230 assertions**. It did **not** create seven accounts on QAS.
- Personas: Alpha Manager, Alpha Lead, Alpha Worker; Beta Manager, Beta Worker; Operations Manager; Finance Manager.
- Use `@flowra-qa.invalid` test addresses, `QA-` employee codes, password material stored privately outside Git, a known tenant/team context and a manifest of exact inserted IDs.
- Follow `docs/WORG01B_SEVEN_PERSONA_QA_MATRIX_2026-10-14.md`. Guarded browser source: `tests/browser/workforce-authenticated-readonly.cjs`.
- Authenticated GET/visual checks are distinct from **403 mutation-denial** tests; the existing browser runner does not prove mutation resistance, approvals, screenshots or cleanup.
- Verify list/detail/aggregate scope, direct URLs, own vs subordinate vs outsider, salary segregation, self approval denial, EN/AR RTL desktop/mobile and persistence of permitted/denied actions.
- Clean *only fixture IDs* from the manifest, respecting references, then confirm baseline unrelated records unchanged. Record PASS/FAIL/BLOCKED and decision.

## Access / security boundary

- The connected owner desktop is available and GitHub operations work. **Direct QAS SSH was blocked by a tool safety check** in recent sessions. Never try to circumvent that restriction. phpMyAdmin UI export via the owner's authorized logged-in hPanel is a separate documented manual route; an export does not itself authorize automated QAS DB writes.
- If an approved QAS admin connection or isolated restore environment cannot be obtained, stop at the exact blocked gate and continue only non-destructive CI/local testing.
- Source/application SHA and deployed QAS SHA must be documented separately. Last directly confirmed QAS deployment in preceding session: `a62ad480` (not reconfirmed now). Production unchanged.

## Milestone ledger to update every repetition

| Gate | Evidence needed | Current status |
| --- | --- | --- |
| QAS database identity | Authorized read-only app DB config match | NOT FULLY VERIFIED |
| Fresh complete SQL export | Actual dated private file, table coverage, size/checksum | PENDING |
| Isolated restore rehearsal | Separate database, import success, schema/row-count comparison | DEFERRED for disposable QAS; NOT VERIFIED |
| Authorized fixture provisioning | Seven QA-only accounts + IDs/relationships, side-effect isolation | BLOCKED |
| Authenticated QAS acceptance | EN/AR/desktop/mobile, GET + mutation checks, PASS/FAIL evidence | BLOCKED |
| Cleanup and closure | Exact-ID cleanup/retention decision and formal gate signoff | NOT APPLICABLE YET |

**Next owner action:** in phpMyAdmin Export for the QAS candidate database, select **Custom** and send a screenshot showing the newly revealed options **without clicking Export yet**. Then confirm the full structure+data export configuration and proceed carefully.
