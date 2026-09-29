# New Chat Handoff — Flowra / Dynamic — 2026-09-29

## Current checkpoint — Sales COGS / Profit Integrity on QAS

Continue on `sec03-framework-upgrade`. The exact verified **application / CI / QAS** revision is `7c66bc19f074d69c007be8fcc1027fb011f7af0d` (`7c66bc19`), with **Hardening CI #2252 Green** on that exact SHA.

Order-level COGS/profit is now driven by actual FEFO lot provenance rather than current/average catalog valuation. The closure covers mixed-cost allocations, POS post-consumption recalculation, original-lot COGS recovery on POS/RMA restocks, and online-payment retry/re-reservation when the second reservation comes from different lots. Legacy order lines without lot provenance retain historical `unit_cost` fallback.

QAS was promoted from `123fcbc9` to `7c66bc19` using `deploy-qas.sh sec03-framework-upgrade`. No new migrations were pending. Remote HEAD matches the verified SHA, maintenance is OFF, application/static health are HTTP 200, Home/Login are HTTP 200, and protected Payroll still redirects unauthenticated requests to Login. Immutable tag: `qas-sales-cogs-integrity-2026-09-29`.

**Important boundary:** order-level `cost_total` / `profit_total` is authoritative for this slice. `order_items.profit_amount` is not promoted to accounting evidence until storefront order-level coupon/promotion discounts have a defined line-allocation policy. Shipping cost, tax liability and GL posting remain separate accounting-policy boundaries.

Production remains unchanged. OPS-01, PAY-01, OPS-02 and Production scheduler/queue verification remain independent release gates.

**Next:** audit whether any reporting/history still treats aggregate `inventory_movements.unit_cost` as actual COGS. Where a report is accounting-facing, prefer lot provenance and preserve legacy fallback. Do not invent line-level discount allocation or statutory accounting policy.

Paste-ready continuation:

> نكمل Flowra على `sec03-framework-upgrade` من application/QAS SHA `7c66bc19`. Hardening CI #2252 Green ونفس الـSHA منشور على QAS؛ maintenance OFF وapp/static HTTP 200. Sales COGS/Profit Integrity مقفول order-level: FEFO actual lot costs، mixed-cost allocations، POS recalculation، original-lot restock recovery، وonline retry re-reservation. Legacy orders لها unit_cost fallback. line-level profit_amount لسه non-authoritative لحد ما نحدد order-discount allocation policy. الخطوة التالية audit لأي reporting بيستخدم inventory_movements.unit_cost كـactual COGS بدل lot provenance. Production unchanged؛ OPS-01/PAY-01/OPS-02 وProduction scheduler/queue لسه release gates.

### Earlier checkpoint — Workforce Payroll Integrity


## Current checkpoint — Workforce Payroll Integrity on QAS

Continue on `sec03-framework-upgrade`. The exact verified **application / CI / QAS** revision is `123fcbc9f10eb5fb6b6ed24bf628af877aa31ee6` (`123fcbc9`), with **Hardening CI #2250 Green** on that exact SHA.

Payroll Foundation V1 is now business-integrity hardened across effective-dated compensation history, retroactive historical-rate resolution, compensation overlap/proration guards, unstable attendance/break/correction/leave generation blockers, strict payroll money precision, exact-cent currency-separated summaries, direct model-level immutability after approval, and explicit separation between internal `Paid` workflow status and actual bank settlement.

QAS was promoted from `523af8f8` to `123fcbc9` using `deploy-qas.sh sec03-framework-upgrade`. All 25 pending ERP-integrity migrations applied successfully through `2026_09_29_001000_enable_compensation_history`. Application health HTTP 200, static asset HTTP 200, maintenance OFF, Home/Login HTTP 200, and protected Payroll correctly redirects unauthenticated requests to Login. Immutable tag: `qas-payroll-integrity-2026-09-29`.

**Important boundary:** source/full CI/deployed-QAS integrity evidence is CLOSED, but the signed-in Payroll/Workforce browser checklist remains a separate acceptance gate. Do not claim statutory tax/social-insurance, payroll bank settlement, payroll-file acceptance, automatic leave monetization or undefined salary/rate proration.

Production remains unchanged. OPS-01, PAY-01, OPS-02 and Production scheduler/queue verification remain independent release gates.

**Next:** run controlled authenticated Workforce / Payroll QAS acceptance on `123fcbc9` using controlled test data only. Fix only reproduced defects with regression coverage. Then continue the first genuinely unclosed Business Process & ERP Integrity domain.

Paste-ready continuation:

> نكمل Flowra على `sec03-framework-upgrade` من application/QAS SHA `123fcbc9`. Hardening CI #2250 Green ونفس الـSHA منشور على QAS؛ الـ25 migration الجديدة اتطبقت، app/static HTTP 200 وmaintenance OFF. Payroll Integrity مقفول source/CI/deployed-QAS integrity: compensation history، retroactive rates، proration blockers، stable attendance/leave inputs، exact money/currency summaries، finalized immutability، وPaid منفصل عن bank settlement. الخطوة التالية controlled authenticated Workforce/Payroll QAS acceptance ببيانات test فقط، وأي defect حقيقي يتقفل regression-first. Production unchanged؛ OPS-01/PAY-01/OPS-02 وProduction scheduler/queue لسه release gates.

### Earlier checkpoints below

## Current checkpoint — Purchasing + Inventory / Lot-Batch Integrity V1 on QAS

Continue on `sec03-framework-upgrade`. The exact verified source/CI/QAS application is `1c90222e4cedabe49ac9e704401e9890875095f1` (`1c90222e`), and Hardening CI **#2151 is Green** on that SHA.

Commerce Financial Integrity remains CLOSED from the prior checkpoint. The new audited Purchasing/Inventory slice also closes **Lot/Batch Integrity V1 + Sellable Inventory alignment** on source/CI/QAS: FEFO lot consumption, exact reservation-release allocations, expired-lot exclusion, legacy negative-stock repair without fake lots, POS/RMA/cancellation return origin integrity, inventory valuation/cost separation, partial receiving, safe purchase receipt reversal auditing, and sellable-stock propagation through commerce/catalog/Admin product/dashboard surfaces.

QAS is deployed on the same `1c90222e` revision. Deployment applied migrations through `2026_09_28_005000_create_inventory_lot_ledger`, completed with application HTTP 200 + static asset HTTP 200, and maintenance mode is OFF. Immutable checkpoint tag: `qas-lot-batch-integrity-2026-09-28`. The dedicated SSH key on the connected personal Windows device is verified; future QAS deployments can be executed directly with `deploy-qas.sh sec03-framework-upgrade` after confirming the exact green target SHA.

Production remains unchanged. Separate release gates remain **OPS-01 credential rotation evidence**, **PAY-01 real Paymob E2E**, **OPS-02 isolated database restore rehearsal**, plus Production scheduler/queue setup and verification.

**Next:** run a short controlled authenticated QAS smoke/acceptance pass for the new Purchasing/Inventory/lot-batch journeys. Fix only reproduced defects. Then continue the Business Process & ERP Integrity Audit with the next unresolved domain; do not reopen Commerce Financial Integrity or Lot/Batch Integrity V1 without a reproduced defect.

Paste-ready continuation:

> نكمل Flowra على `sec03-framework-upgrade` من SHA `1c90222e4cedabe49ac9e704401e9890875095f1`. Hardening CI #2151 Green، ونفس الـSHA منشور على QAS مع HTTP 200 للـapp والـstatic assets وmaintenance OFF. Commerce Financial Integrity مقفول، وكمان Lot/Batch Integrity V1 + Sellable Inventory alignment مقفولين source/CI/QAS. migrations وصلت لحد `2026_09_28_005000_create_inventory_lot_ledger`. ابدأ بـQAS smoke قصير على Purchasing/Inventory/lot-batch ببيانات test controlled، اقفل أي defect حقيقي فقط، وبعدها كمل Business Process & ERP Integrity Audit في أول domain غير مقفول. Production unchanged؛ OPS-01 وPAY-01 وOPS-02 وProduction scheduler/queue ما زالوا release gates.

### Earlier checkpoints below

## Current checkpoint — customer commerce hardening through Paymob retry serialization

Continue on `sec03-framework-upgrade`. Latest application source checkpoint is `b7646b7ec158c6e71067381b3fb3786dd88fa249`; Hardening CI **#2061** passed on that exact SHA.

The latest source/CI chain closes live-state and repeated-action gaps across Product Reviews, Order Cancel, Address Save/Delete, Profile/Password, Return Cancel, Support Reply and Notifications. The latest Paymob change adds a DB-backed initiation claim so overlapping retry requests cannot create duplicate gateway sessions, with stale-claim expiry, ownership-safe release and terminal-payment recheck before gateway initiation.

Latest confirmed QAS deployment on the intended `sec03-framework-upgrade` line is `71d9b6e1abf0b99a2d3953ae448b22598a5c3001`. Do **not** treat later source as QAS accepted until the exact application revision is deployed and checked. Production remains unchanged.

Remaining release blockers: **OPS-01 credential rotation evidence**, **PAY-01 real Paymob E2E**, **OPS-02 database restore rehearsal**. OPS-03 is accepted on QAS only and still needs Production setup/verification during release.

**Next:** continue strict Page Closure only for substantive remaining findings; finish remaining Customer journey gaps, then Admin/POS/Workforce in dependency order. Keep new product features deferred until existing implemented surfaces are closed. Matching-revision authenticated EN/AR/RTL/mobile/keyboard QAS acceptance remains mandatory before CLOSED.

Paste-ready continuation:

> نكمل Dynamic على `sec03-framework-upgrade` من application SHA `b7646b7e`، وHardening CI #2061 Green. آخر QAS صحيح على نفس الفرع هو `71d9b6e1`، لذلك كل source بعده QAS pending وProduction unchanged. سلسلة Customer live hardening وPaymob retry serialization مقفولة source/CI؛ كمل أول finding حقيقي في Page Closure بدون إعادة شغل مقفول، ثم Admin/POS/Workforce. لا Production قبل OPS-01 وPAY-01 وOPS-02 والقبول النهائي.

### Earlier checkpoints below
## Current checkpoint — GF-17 account/auth semantics

Continue on `sec03-framework-upgrade`. Application source for GF-17 is `e82b3f9d82afdc3be71506827b29f71fb377f967`: Account Overview current-state matching is now exact, Address Book no longer competes with it for `aria-current="page"`, the desktop account dropdown exposes active destinations, and Login/Register share the auth page shell with explicit label associations. Regression coverage was added in `StorefrontExperienceTest`.

GF-17 is **IN REVIEW**. Do not call it green/closed until final push-triggered Hardening CI is verified; the current GitHub connector endpoint available in chat does not return push-run metadata for the commit. QAS still needs the exact revision with EN/AR desktop/mobile keyboard checks. Earlier GF-14/15/16 QAS separation and Production blockers OPS-01/PAY-01/OPS-02 remain unchanged.

**Next:** verify CI evidence, continue remaining shared Storefront/customer shell only where there is a real cross-page risk, then begin strict Page Closure.

### Earlier checkpoints below

## Current checkpoint — Storefront shared foundation through `ba00f303`

Continue on `sec03-framework-upgrade`. Final application HEAD in this checkpoint is `ba00f303b2e32fb8cb76ce84f25af89d449fadf7`, green in [Hardening CI 36257847029](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36257847029) with 470 PHP tests / 13,982 assertions plus shared JS checks/build. Since the prior route/navigation checkpoint, `3fe0f28` hardened configurable Home/hero/banner action destinations, `a536b22` removed repeated shared-view work inside the same HTTP request and category-count N+1 behavior, and `72cf678`/final `ba00f303` added Admin/Storefront skip-to-content landmarks, reduced-motion behavior, and separated mobile account-nav auto-scroll from the `/` search shortcut. Source CI is green; this is **not** QAS acceptance. Last documented QAS application remains `f1f20297`; Production unchanged; OPS-01/PAY-01/OPS-02 remain release blockers.

**Next source slice:** continue the remaining shared Storefront/customer shell foundation — footer/account/auth conditional navigation, EN/AR + RTL/LTR, mobile/accessibility and targeted CSS/JS cleanup only where it removes repeated risk — then begin strict page-by-page closure. When the newer source is deployed to QAS, perform exact-revision desktop/mobile keyboard/reduced-motion checks before closing GF-14/15/16.

Paste-ready continuation message:

> نكمل Dynamic على `sec03-framework-upgrade` من `ba00f303` حسب `CURRENT_PHASE.md` و`docs/NEW_CHAT_HANDOFF_2026-09-26.md`. آخر CI أخضر هو `36257847029`. آخر QAS موثق ما زال `f1f20297` وProduction لم يتغير. كمل Global Foundation من shared Storefront/customer shell (footer/account/auth + conditional navigation + EN/AR/RTL + mobile/accessibility + targeted cleanup)، وبعدها Page Closure صفحة صفحة. لا تعتبر GF-14/15/16 CLOSED قبل QAS على نفس النسخة، واحتفظ ببوابات OPS-01/PAY-01/OPS-02.

### Earlier checkpoints below

## Current checkpoint — registered route inventory

Continue on `sec03-framework-upgrade`, reading `CURRENT_PHASE.md` first. Source GF-01/11/12 passed CI runs `36249023102`, `36250710952` and `36250969555` respectively, but matching-revision authenticated QAS acceptance remains open. The route-export application commit `11cd7770` passed CI `36251293003`; its 337-entry Laravel route artifact was reconciled with all 142 literal GET seed URIs, yielding a [160-entry GET discovery register](PAGE_INVENTORY_2026-09-26.md) and reproducible CSV scripts. These are route candidates, not 160 accepted pages. GF-13 public health-probe/trace fix passed CI `36251884894` at `39c6a87`. GF-14 Storefront shared-navigation fix passed CI `36252296274` at `7ceff28` (463 PHP tests / 13,936 assertions plus JS checks/build). Next inspect remaining actual navigation, conditional screens, roles and journeys, including user-configured Home/hero/banner anchors, while continuing shared Admin/Storefront foundation. QAS is still documented at `f1f20297`; Production unchanged. OPS-01/PAY-01/OPS-02 remain blockers.

Paste-ready continuation message:

> نكمل Dynamic من `CURRENT_PHASE.md` و`docs/NEW_CHAT_HANDOFF_2026-09-26.md` على `sec03-framework-upgrade`. تحقق من آخر HEAD وCI، وفرق source/QAS/Production. جرد المسارات اتصالح من CI لكن الصفحات والأدوار والواجهات الشرطية والقبول على QAS لسه مفتوحين. أكمل Global Foundation ثم إغلاق صفحة صفحة بالدليل وسجل النوتس أولًا بأول؛ لا Production قبل OPS-01/PAY-01/OPS-02 والاعتماد النهائي.

### Earlier checkpoints below

## Latest Admin shell continuation

GF-12 now addresses names/tooltips for all seven collapsed Admin navigation groups and keeps auto-scroll inside the sidebar. The previous GF-11 source commit `6e6d75e` passed CI run `36250710952`; verify the newest HEAD/CI before proceeding. QAS still has documented application `f1f20297`, so GF-01/GF-11/GF-12 remain pending matching-revision authenticated acceptance. Production unchanged. Continue Admin shell work and reconcile the initial route/page inventory after the latest-head CI gate; retain OPS-01/PAY-01/OPS-02 release blockers.

## Latest shared-foundation continuation

The Page Closure/mini-sidebar commit `3ba6d5a` passed Hardening CI `36249023102`. The next source slice fixes GF-11 shared Admin confirmation, focus and submit-loading behavior with four Node interaction tests; check `CURRENT_PHASE.md` and the branch HEAD for its exact commit and CI result before relying on it. The last documented QAS application code is still `f1f20297`, so GF-01/GF-11 need matching-revision authenticated QAS checks; Production is unchanged. The next work is latest-head CI, targeted QAS acceptance when deployed, and continued Admin shell/page inventory work. OPS-01/PAY-01/OPS-02 remain release blockers.

The buyer-grade plan and initial inventory linked below remain authoritative; work in coherent batches and keep the next checkpoint current.

## Latest continuation — buyer-grade completion pass

Start with [the buyer-grade execution plan](BUYER_GRADE_EXECUTION_PLAN_2026-09-26_AR.md), [Page Closure System](PAGE_CLOSURE_SYSTEM_2026-09-26.md), `CURRENT_PHASE.md` and `PROJECT_MASTER_STATUS.md`. The plan-review source starting SHA is `3ba6d5ab0a6196d32805ce9e7f64585c2d6682a3` on `sec03-framework-upgrade`; verify the live branch HEAD when resuming. The last application code documented as deployed to QAS is `f1f20297a27e2236603788ac7cc343dbbf86d0b6`. Production remains unchanged. CI/QAS acceptance for the later mini-sidebar and Page Closure source changes is still unconfirmed in this note.

The owner expects every Admin and Customer page, including POS/Workforce variants, to receive a complete product/UI/UX/logic/EN-AR/mobile/security/performance/help review, with the customer storefront treated as a major visual product improvement. The buyer will inspect the delivered project with AI tools, testers and developers. Build an actual page/role/journey inventory and attach repeatable exact-revision evidence to each CLOSED item. Global Foundation shared patterns come first; continue OPS-01, PAY-01 and OPS-02 as separate Production blockers. OPS-03 is verified on QAS, not yet on Production. Do not claim social sign-in, CSV execution, tenant isolation, commerce API or native apps are finished.

The [initial source page inventory](PAGE_INVENTORY_2026-09-26.md) contains a CSV seed of 142 literal GET declarations. It is explicitly incomplete until reconciled with `route:list`, generated Auth routes, mutations/Livewire and conditional UI.

The owner also wants fast, substantive progress without chats hanging: coherent bounded batches, small tool outputs, one meaningful latest-head CI gate per batch, frequent repo checkpoints and concise progress updates. Before moving chats, save current work and provide this paste-ready message (replace SHAs/results with the actual latest checkpoint):

> نكمل Dynamic من `docs/NEW_CHAT_HANDOFF_2026-09-26.md` و`docs/BUYER_GRADE_EXECUTION_PLAN_2026-09-26_AR.md`. ابدأ بالتحقق من HEAD الحالي وفرق source/CI/QAS/Production. آخر حالة موثقة في `CURRENT_PHASE.md`. أكمل أول بند مفتوح في Global Foundation أو Page Closure، وسجل الدليل والنوتس مع كل دفعة. Production لا يتغير قبل بوابات OPS-01 وPAY-01 وOPS-02 والقبول النهائي.

**Immediate next source/acceptance action:** verify CI for `3ba6d5a`, then check the mini-sidebar on the matching QAS application revision before marking GF-01 CLOSED; generate the page/navigation/role/journey inventory and continue Admin shell foundation. OPS-01 rotation, PAY-01 E2E and OPS-02 restore remain separate release work. The older `Exact next action` section below records the pre-modernization release-readiness priority and does not supersede this latest decision.

## Start here

Continue the Dynamic / Tag Marketplace Laravel project from this checkpoint.

### Current branches and deployment

- Repository: `khaledtag93/dynamic-ecommerce`.
- Stable working line: `v42-clean-baseline` — not yet updated with the Laravel 13 rehearsal work.
- Active hardening branch: `sec03-framework-upgrade`.
- The hardening branch has documentation-only commits newer than the deployed application commit; do not confuse the branch documentation HEAD with the QAS application SHA.
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

## Exact next action

Start the next chat with **OPS-01 credential rotation evidence**.

Goal:
- inventory every credential/service that may have been exposed historically through Git;
- rotate or revoke old values without writing secret values into chat, Git, logs, or documentation;
- record only the service name, whether rotation/revocation is complete, and the completion date;
- verify the application still works with the new credentials;
- then move to PAY-01 Paymob E2E and OPS-02 database restore rehearsal.

Do not deploy Production while any of those P0 gates remain open.

## Primary references

- `PROJECT_MASTER_STATUS.md`
- `docs/PRODUCTION_FOUNDATION_AUDIT_2026-09-26_AR.md`
- `docs/SCHEDULER_QUEUE_OPERATIONS_RUNBOOK_2026-09-26.md`
- `docs/COMPLETION_PASS_QAS_CHECKLIST_2026-09-25.md`
- `docs/PRODUCT_ROADMAP_2026-09-24.md`
- `docs/PRODUCT_UX_MASTER_BACKLOG_2026-09-25.md`
