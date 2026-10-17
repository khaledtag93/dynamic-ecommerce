# Flowra — Reset Mailer Source-only Fake Contracts / QAS Operator Gate (2026-10-17)

## Starting evidence
- Base GitHub checkpoint: b6c3a7ef03c83f55566ce6cc1ca48e71e18e477e.
- [Full baseline CI #39050928061](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39050928061) independently observed completed/success on the **exact** base SHA: 1,011 tests / 19,626 assertions.
- Last verified QAS deployed source: de180de6cae5f28dc867b752ab6f1d97baa71416; QAS Laravel mail=array per prior read-only evidence. This is NOT the newer documentation/test branch SHA.

## Step 1 — phpMyAdmin operator-assisted SQL result
- The authorized QAS phpMyAdmin browser SQL tab was **not accessible** through the currently available tools. The connected desktop exposes terminal/file access, not an authenticated browser SQL tab. **No QAS phpMyAdmin SQL was run**, including the first identity check.
- The only permitted first SQL when an authorized operator has the tab remains \`SELECT DATABASE() AS connected_database;\` **alone**. Hard STOP unless exact result equals \`u637857322_v42_rehearsal\`. Only afterward may the carefully allowlisted aggregate-only queries in [operator packet](QAS_M3_M4_PHPMYADMIN_OPERATOR_READONLY_PACKET_2026-10-17_AR.md) run. No raw settings dump, tokens, PII, job payloads, secrets, or rule recipients.
- No replacement SSH/DB PHP, tool-safety bypass or QAS/Production mutation used. M3 BLOCKED; M4 PARTIAL/BLOCKED. Effective flags/automation recipients, queue job classes and delivery backlog remain UNKNOWN.

## Step 2 — isolated source-only contract regression
- Introduced \`tests/Feature/PasswordResetMailerIsolationContractTest.php\` with **2 tests**, using \`Notification::fake()\` plus \`Mail::fake()\`; no external transport, SMTP, queue, scanner, retry, sandbox inbox or mailer activation.
- **Contract A:** synthetic source-only customer and legacy staff role-marker accounts requesting password reset receive only a fake-recorded stock Laravel \`ResetPassword\` mail-channel notification, with **no per-message named mailer override**; default test mailer is \`array\`, unchanged through requests. Explicitly checks \`toMail()->mailer === null\`, not actual delivery. Legacy role marker is NOT proof of super-admin authorization.
- **Contract B:** unknown account receives the same generic public success shape without a notification or sent mail; source password broker config retains 60-minute expiry and 60-second throttle, without modifications.
- Existing \`PasswordRecoveryPrivacyTest\` covers throttle behavior, expiry, and single-use reset lifecycle; these are not rebranded as live-QAS acceptance.
- **Strict distinction:** Current source has **no** \`qas_reset_capture\` named mailer or synthetic allowlist, so these regression tests CANNOT prove future sandbox allowlist enforcement, admin/automations/Growth routing isolation, capture retention, forwarding OFF, or delivery. The architecture remains [SOURCE DESIGN ONLY](QAS_RESET_ONLY_MAIL_SANDBOX_SOURCE_DESIGN_2026-10-17_AR.md). Do not implement it without a separately approved config/provider/security slice.
- Regression execution is through GitHub Hardening CI on the test/documentation commit; do not mark GREEN until exact-SHA CI status is \`completed/success\`. Any confirmed regression failure must be triaged (test assumption vs real application defect) before a minimal fix; no unproven bug is claimed.

## Closure / next dependency
- No application behavior change, mailer config/permission/secret/policy change, real SMTP/email/SMS/WhatsApp send, QAS/Production write, worker, scanner, retry, or operator SQL execution.
- **M3 BLOCKED / M4 PARTIAL-BLOCKED; C1-A #2 BLOCKED; QAS #3/#4/#5/#6 OPEN; Phase 1 Production NO-GO**.
- Next: obtain only an explicitly authorized QAS operator result for **the standalone identity SELECT**. If unavailable, preserve gate; separately consider a more detailed source-only mailer seam acceptance design without activating any provider.
