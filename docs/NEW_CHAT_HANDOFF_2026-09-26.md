# New Chat Handoff — Dynamic — 2026-09-26

## Start here

Continue the Dynamic / Tag Marketplace Laravel project from this checkpoint.

### Current branches and deployment

- Repository: `khaledtag93/dynamic-ecommerce`.
- Stable working line: `v42-clean-baseline` — not yet updated with the Laravel 13 rehearsal work.
- Active hardening branch: `sec03-framework-upgrade`.
- Current documentation HEAD on the hardening branch: `a18af1e23b76f98162a7be2c881590a672f3984a`.
- Latest application/operations code deployed to QAS: `f1f20297a27e2236603788ac7cc343dbbf86d0b6`.
- QAS URL: `https://v42.tag-marketplace.com`.
- Production remains unchanged.

### Framework / security status

The framework modernization is working on QAS:

- PHP 8.3.33.
- Laravel 13.33.0.
- Livewire 4.4.6.
- Sanctum 4.3.3.
- Carbon 3.14.0.
- Hardening CI run `36215421736` passed on exact application HEAD `f1f20297`.
- Composer validation and security audit passed.
- Clean MySQL migration, routes, config/Blade compilation, PHPUnit, and frontend build passed.
- Security headers verified on QAS: HSTS, nosniff, SAMEORIGIN, strict-origin referrer policy.
- Session cookie verified Secure + HttpOnly + SameSite=Lax.
- Modern Laravel request-forgery middleware compatibility completed.
- Session serialization intentionally remains explicit `php` during the upgrade; JSON migration is a later controlled re-login change.

### Security findings

- SEC-01: closed on rehearsal line by moving from affected Livewire 2 to Livewire 4; dependency audit green.
- SEC-03: Laravel 13 migration source/CI/QAS proof complete on rehearsal branch. Merge to `v42-clean-baseline` and final authenticated QAS acceptance remain.
- SEC-04: safe locale redirect hardening implemented with regression coverage.
- SEC-02: upload extension/content hardening + upload-root execution guard implemented in source; authenticated QAS upload evidence remains part of acceptance.

### Scheduler / Queue / OPS-03

QAS now uses:

`QUEUE_CONNECTION=database`

The database queue runtime tables and operations heartbeat table are installed.

New operational commands:
- `php artisan ops:heartbeat`
- `php artisan ops:health`
- `php artisan ops:health --strict --max-age=180`

Manual queue proof succeeded:
- heartbeat job entered the database queue;
- before a worker ran, strict health correctly showed a stale/mismatched old sync heartbeat and one pending job;
- a real database worker consumed `RecordQueueHeartbeat`;
- pending returned to zero;
- failed jobs stayed zero;
- strict health passed.

Automatic QAS execution is also verified:
- hPanel Cron runs every minute;
- scheduler heartbeat stays fresh automatically;
- queue-worker heartbeat stays fresh automatically;
- queue driver is database;
- failed jobs remain zero;
- Laravel scheduler successfully ran `growth:run`, `notifications:scan-escalations`, and `payments:expire-stock-reservations`;
- bounded queue worker consumes heartbeat jobs and exits cleanly when empty.

A transient `Pending jobs = 1` can appear between cron launches because scheduler and queue cron start in the same minute. The next worker cycle consumes it. This is not a failed job.

OPS-03 is accepted on QAS. Production will still need the same scheduler/queue setup and verification before promotion.

Important operational detail:
- QAS currently uses wrapper scripts created on the server to make Cron execution reliable and log stdout/stderr.
- Treat those wrappers as QAS operational configuration; before Production, either version/genericize them in source or reproduce/document them explicitly so there is no hidden server-only drift.
- SSH key-based access from the user's personal device is configured and verified, so future server checks/deploy commands can be executed directly without asking for a password each time.

### Why Scheduler / Queue matter

This work makes Dynamic behave like a production system rather than only a website that returns pages.

Scheduler:
- runs recurring tasks automatically;
- expires old online stock reservations;
- runs Growth automation;
- scans notification escalations;
- aggregates analytics;
- supports future recurring maintenance.

Queue worker:
- executes background work without blocking the user's web request;
- supports Growth messages, analytics jobs, WhatsApp, notifications, and future email/integration work;
- provides retry/failed-job visibility instead of silently losing background tasks.

Without these, the storefront could appear healthy while important stock, messaging, automation, or analytics work silently stops.

### Remaining P0 release gates before Production

1. OPS-01 — historical credential rotation evidence.
   - Secrets existed in Git history previously.
   - Removing `.env` from the repository is not sufficient evidence.
   - Rotate/revoke the old credentials and record service names + rotation dates only; never commit secret values.

2. PAY-01 — Paymob E2E.
   - Prove paid / failed / duplicate / late callback / retry behavior.
   - Confirm order/payment/stock reservation state is correct and no double charging/refund/stock side effects occur.

3. OPS-02 — database restore rehearsal.
   - Restore a real backup into an isolated database.
   - Prove schema/code compatibility and document restore procedure and recovery expectations.

### Remaining QAS acceptance before Production

Authenticated/manual acceptance is still required across:
- Admin shell/navigation.
- Product list/create/edit, variants, uploads, Livewire behavior.
- Brands/Attributes modals and filters.
- POS.
- Workforce.
- Customer account, addresses, My Orders, Notifications.
- Cart/Checkout.
- EN/AR/RTL.
- Responsive/mobile web.
- SEC-02 upload behavior.
- SEC-04 locale redirect behavior.

Do not treat HTTP 200 alone as feature acceptance.

### Merge / release sequence

1. Finish remaining P0 release gates and critical QAS evidence.
2. Merge `sec03-framework-upgrade` into `v42-clean-baseline`.
3. Run exact-head Hardening CI on the merged stable branch.
4. Deploy the exact stable merge commit to QAS.
5. Repeat final authenticated QAS smoke.
6. Only then prepare a controlled Production deployment.
7. Configure/verify the same scheduler + database queue runtime in Production before considering OPS-03 complete there.

### Product work after release-readiness

Return to the recorded completion plan:
- Growth Engine UI/UX/consistency review.
- Branding/themes and visual system improvements.
- Analytics clarity/consistency.
- remaining EN/AR/RTL cleanup.
- long-page decomposition where useful.
- live/no-reload interactions where safe.
- storefront UI/UX/mobile polish.
- real CSV import execution.
- social login later.
- delivery/workforce expansion according to policy.
- eventual Android/iPhone apps after the web platform is mature.

Keep the existing rule: finish and harden implemented areas before starting unrelated expansion unless the new item is a blocker, security issue, direct dependency, or prevents immediate rework.

## Primary references

- `PROJECT_MASTER_STATUS.md`
- `docs/PRODUCTION_FOUNDATION_AUDIT_2026-09-26_AR.md`
- `docs/SCHEDULER_QUEUE_OPERATIONS_RUNBOOK_2026-09-26.md`
- `docs/COMPLETION_PASS_QAS_CHECKLIST_2026-09-25.md`
- `docs/PRODUCT_ROADMAP_2026-09-24.md`
- `docs/PRODUCT_UX_MASTER_BACKLOG_2026-09-25.md`
