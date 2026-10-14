# W-ORG-01b — QAS SQL export verification (2026-10-14)

**Evidence type:** non-sensitive metadata and static inspection only  
**Environment:** intended disposable QAS; no Production change  
**Source checkpoint:** `56c0ebc7` (`sec03-framework-upgrade`)  
**SQL contents:** intentionally excluded from GitHub and ChatGPT

## Private owner-device export

- Authorized phpMyAdmin source selection: `u637857322_v42_rehearsal`.
- Local filename: `C:\Users\Khaled\Downloads\u637857322_v42_rehearsal.sql`.
- File modified: **2026-10-14 21:28:16 +03:00**; size **7,306,278 bytes**; **20,069 lines**.
- SHA-256: `65a626a55fb7188715194fc7b0084e68a03469a6f2749db174e39b3976e42d3f`.
- SHA-256 was calculated **twice** with matching results. A 96-byte sidecar `u637857322_v42_rehearsal.sql.sha256` was saved in the same private Downloads directory. The SQL contents were never copied into the repository or chat.

## Read-only static SQL scan

| Check | Observation |
| --- | --- |
| phpMyAdmin SQL dump header | present |
| Strict UTF-8 decode | PASS |
| SQL `CREATE TABLE` statements | 101 |
| Unique created tables | 101 |
| SQL `INSERT INTO` statements | 192 |
| Expected Workforce/auth/migrations tables | **11 of 11** |
| Transaction statements | one BEGIN/START TRANSACTION and one COMMIT |
| `CREATE DATABASE` / `USE` | absent |
| `DROP` statements | absent |
| HTML or error markers | none detected |
| SQL dump end marker | not detected by generic search; final nonempty line was SQL comment or COMMIT |
| CREATE VIEW / TRIGGER / PROCEDURE / EVENT | none found in the static scan; source object inventory not independently verified |

**Verdict:** Fresh export file **FOUND**, static SQL checks **PASS**; **NOT RESTORE-VERIFIED**. A file's size, header, table statements and hash do not establish a successful restore, point-in-time consistency or source-to-target completeness.

## QAS application-to-database evidence

Repository `deploy-qas.sh` specifies `APP_DIR=laravel_app_v42_rehearsal`, `PUBLIC_DIR=public_html/v42`, QAS healthcheck `https://v42.tag-marketplace.com`, and `EXPECTED_DB=u637857322_v42_rehearsal` (lines 18–23). Before deployment it demands `APP_ENV=staging`, `APP_DEBUG=false`, and exact `DB_DATABASE` matching (lines 86–88); it also checks `SELECT DATABASE()` equals the expected name (lines 133–134).

This is strong **deployment-script mapping**, **not a fresh read of the live running application's database setting**. The other Hostinger database must not be touched.

## CI and acceptance boundary

- Prior Hardening CI [run 38614394503](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38614394503) completed **success** on `56c0ebc7`. The isolated PHPUnit suite had **907 passing tests / 18,230 assertions** from preceding verified evidence.
- The owner accepts loss of existing *disposable QAS* test records and prefers one coordinated persona/acceptance pass. A full isolated restore rehearsal is **deferred** for this expendable-QAS workflow, not silently marked complete.
- **QAS admin access BLOCKED:** previous direct SSH call was rejected by the platform safety check. The connected Desktop Commander device was reported Online but file/process requests timed out on this continuation. Do **not** circumvent the blocked route using phpMyAdmin writes, alternate SSH command transport, GitHub workflow remote execution or other means.
- **No QAS persona or DB mutation** executed; seven synthetic authenticated browser personas and ROLE acceptance remain **BLOCKED**. QAS deployment currently running was not reverified; previously observed QAS SHA was `a62ad480`. Production unchanged.
- A local update to other docs during this continuation is **unverified** because the desktop tool stopped responding; this new GitHub-owned file is the authoritative evidence record, not a claim that those local changes were pushed.

## Next admissible step

Restore the existing authorized Desktop Commander connection for local work and arrange an explicitly approved administrative QAS access method **that the tool safety checks permit**. Independently confirm the *running application's* QAS database mapping read-only. Only then provision seven QA-namespaced synthetic personas using a saved exact-ID manifest, run authenticated EN/AR desktop/mobile role/mutation tests, retain PASS/FAIL evidence and make a formal W-ORG-01b closure decision.

For future important-data environments, perform an isolated restore drill before relying on this export for recovery.
