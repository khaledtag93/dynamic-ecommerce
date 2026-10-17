# Flowra — Testing & Acceptance Execution Roadmap (Master)

**Date:** 2026-10-17 (Africa/Cairo)  
**Repository / working branch:** `khaledtag93/dynamic-ecommerce` / `sec03-framework-upgrade`  
**Mode:** DOCUMENTATION + TEST DESIGN ONLY. **Release decision: Phase 1 NO-GO.**  
**Maintained with:** [Assistant-led QA Charter](ASSISTANT_LED_QA_AND_ACCEPTANCE_CHARTER_2026-10-14.md) · [Detailed 26-journey inventory](PHASE1_DETAILED_INVENTORY_2026-10-17_AR.md) · [Production closure register](PHASE1_PRODUCTION_CLOSURE_REGISTER_2026-10-17_AR.md) · [Capability coverage](PHASE1_COMPLETE_CAPABILITY_COVERAGE_2026-10-17_AR.md) · [C1-A execution pack](C1_CORE_COMMERCE_ACCEPTANCE_PACK_2026-10-17.md).

## 1. Why this document exists

هدفنا نقدر نشغل **سيناريوهات فعلية مترابطة** ونقول بدليل لكل Case: PASS / FAIL / BLOCKED / NOT RUN، ولكل Business Journey: ACCEPTED أو NOT ACCEPTED. ده مش بديل عن خطة المنتج أو الـ QA Charter؛ ده **ترتيب تنفيذي واختبارات محددة، قابلة للمتابعة خلال التطوير**. لا نقفز في الترتيب، ولا نعيد فتح جزء Green بدون Impact أو Bug جديد.

- **Source / CI ≠ QAS functional acceptance ≠ Production acceptance.**
- كل سطح ظاهر أو قابل للاستدعاء في Phase 1 داخل الجرد الحالي إلى أن يصدر قرار مالك المنتج بتضمينه أو استبعاده وعزله بأمان.
- مش هنضرب كل الـ combinations عشوائياً؛ هنستخدم **risk-based representative test matrix** مع إلزام الحالات المالية/الأمنية/المخزنية وكل الـ conditional behavior الفعلي، ونوثّق أي عدم تغطية. كلمة "100%" تعني 100% من *الـ approved scope & gates* بدليل، لا صفر Bugs مضمونة.
- الاختبارات التلقائية/QAS والسجلات مسؤولية المساعد بقدر ما تسمح به الأدوات والوصول المصرح؛ المالك يقرر policies والتسليم النهائي وما يتطلب أجهزة أو مزود خارجي.

## 2. Two-layer coverage — tests + actual business cycles

1. **Atomic / Page / Service Cases:** matrix in [Test Case Ledger](FLOWRA_TEST_CASE_LEDGER_2026-10-17.md). IDs `T-J00-01` إلى `T-J25-03` كبداية 78 حالات حقيقية محددة وليست "جميع احتمالات" كل واجهة؛ كل Action أو conditional state جديد يوثّق في subcases قبل اختباره. أضف `T-X01`–`T-X10` لحدود الجودة والأمن والتشغيل.
2. **Full End-to-End Business Cycles:** `T-E01`–`T-E10`، تربط Storefront/Admin/POS/Inventory/Finance/Support/Workforce. نجاح صفحة لا يغلق Cycle، ونجاح Cycle لا يعفي من negative/permission cases.

**Detailing rule:** Expand one bounded slice at a time from 26-journey route/view/Livewire inventories (including dynamic routes, tabs, modals and roles), cover success, negative, empty, boundaries, retry/concurrency, external effects, calculated values, then execute; never mark an unenumerated action ACCEPTED by inference.

## 3. Gate sequence, exact scope and exit criteria

| Gate | Modules / journey IDs | Execution order | Exit proof |
|---|---|---|---|
| **P0 — Safe QA preflight** | version, fixture strategy, QAS isolation, test roles, outbound sink, baseline counts, permissions | first before *any* stateful QAS action; read-only when possible | exact QAS app + DB identity + version + delivery isolation, access, expected values, approved action/fixture budget, recovery plan. Otherwise BLOCKED |
| **C1 — Commerce / identity** | J00–J08, J13, J25 | C1-A COD isolated → C1-B auth/guest cart/session → C1-C sandbox payments → C1-D RMA/delivery → remaining catalog/offers edges | relevant ledger cases and E01–E03; exact money, payment, order, reservation, refund, stock, account ownership; policy decisions resolved |
| **C2 — Merchant operations** | J09–J12 | supplier/purchases → stock/FEFO/COGS → POS shift/cash/return/scanner/printer → customer statement | E04/E05, stock and finance reconciliation, real device/hardware acceptance if advertised |
| **C3 — Admin/commercial/support** | J14–J18, J23 | config & branding → growth/analytics → support/notification → roles → import draft honesty | E06/E08/E09, EN/AR + access/metrics, no unauthorized external messaging, DEC-IMP resolved |
| **C4 — Workforce V1** | J19–J22 | org + 7 synthetic personas → scheduling/attendance → leave/routing/concurrency → payroll | E07 and permissions/financial acceptance; **or** an explicit owner-approved fail-safe exclusion with isolation proof (not only hidden menu) |
| **C5 — Release & nonfunctional** | J24, X01–X10, all G01–G10 | performance, privacy/security, restore/rollback, deploy exact-SHA, full regression, final UAT | E09/E10, all critical release gates PASS, owner Go; Production requires independent deployment approval |

**Important:** Existing C1-A [execution pack](C1_CORE_COMMERCE_ACCEPTANCE_PACK_2026-10-17.md) is already concrete and remains the first QAS stateful boundary. Don't replace it or expand its 12-order cap. Hardware/Paymob and Workforce require *separate* scoped authorization and isolation. The current no-write restriction stays in force.

## 4. Mandatory scenario dimensions for every enabled action

| Dimension | Examples: assert actual behavior, not just HTTP 200 |
|---|---|
| Positive / business result | expected UI, stored record, audit trail and next-module output |
| Validation / boundary | required/optional/invalid, empty/duplicate/zero/decimal/max, disabled variant, expired items, timezone, localization |
| Negative access | Guest, Customer A/B, cashier, manager, support, admin, HR/payroll; direct request, Livewire, API, export, cross-branch/tenant where in scope |
| Money / stock | precise cents, rounding, discounts and tax only under approved policy, lots/expiry, COGS, revenue, refund, payroll before/after reconciliation |
| State machine / integration | transitions, cancellations, queued jobs, webhook, error/retry/timeout, idempotency, slow/offline and concurrent requests |
| Interface | EN & AR, LTR/RTL, desktop 1280px, mobile 390px, focus/keyboard, no-data/error/loading/success, screen-reader semantics, no pointless reload |
| Production operations | safe redacted logs, scheduler/worker, settings toggles, restore, reproducible exact SHA, perf under measured representative load |
| Hardware/providers | approved sandbox money, HID barcode incl. leading zeros, real receipt printer, third-party delivery proof, without real personal data |

Combinatorial policy: **critical money/security/business state tests** in all relevant variants, plus risk-based locale/viewpoint coverage; don't mechanically multiply destructive operations across all screen sizes if a smaller UI-only verification proves layout. Keep the specific covered cells and reason for exclusions in the ledger.

## 5. Status and acceptance contract

**Case status:** `NOT RUN` → `READY` → `PASS` or `FAIL` or `BLOCKED`; repaired cases `RETEST` → `PASS`; `N/A` only with approved scope/justification. A case can also be `PARTIAL` with precisely named coverage.

**Journey acceptance:** `NOT ACCEPTED` until applicable cases PASS, CI GREEN on exact source SHA, authorized QAS version and browser/DB proof, correct role and UI acceptance, no P0/P1 release blockers, and owner decision/signoff if required. Then `ACCEPTED`, with evidence URLs and date. No blanket ACCEPTED from test count, source coverage, deployment, prior chat or a pending GitHub Actions run.

**Defect severity:** P0 = security/privacy breach or irreversible money/stock/payroll damage, P1 = core cycle blocked/wrong totals/authorization, P2 = meaningful but bypassable UX or limited behavior, P3 = cosmetic/nonblocking; triage and waiver need explicit owner approval. No silent waivers for a security, money or stock fault in an enabled feature.

**Each executed row records:** ID; exact source SHA; CI run/conclusion; deployed QAS SHA/DB/environment verified; timestamp; role and isolated fixture alias; locale/viewport; steps and actual vs expected with exact values; redacted screenshot/trace or audit/DB evidence; status; defect link; regression/retest SHA; owner approval; test fixture reconciliation. Keep secrets, real accounts, tokens and private identifiers out of Git.

## 6. Test data and safety (non-negotiable)

- Use unique synthetic `QA-<gate>-<run>` prefix and **private** ID manifest. Profiles: Small/Medium/Heavy when testing performance; purpose-built two customers, admin/read-only, cashier, store ops, support, employee/manager/payroll for business scenarios.
- QAS only after allowed runtime identity/source equality, sandbox/notification isolation, permission/role access, data budget, expected snapshot & cleanup approval. **Do not touch Production**, do not bypass remote safety controls, do not change secrets/config/policies, do not send real email/SMS/WhatsApp or charge real payments as a byproduct of testing.
- Failure STOP: any unintended external send, unexpected DB identity/SHA, real user record, double money/stock impact, count mismatch, missing evidence channel, unexpected role access → stop bounded run, mark BLOCKED/FAIL appropriately, preserve audit and reconcile. No raw SQL cleanup of finance/inventory to manufacture a pass.
- Tests that mutate QAS need **explicit bounded approval** for exact actions and side effects. Planning these cases grants **no** such approval. GET may itself mutate session/cart or initiate Paymob, as already found in inventory.
- Preserve historical accepted guest Slice04 (24/24 render + 2 locale switch) and Slice05 (32/32 interactions) as **limited prior evidence**, not universal acceptance; retest only on code impact or expiry of release-evidence requirements.

## 7. Execution rhythm while development continues

**Per coherent development slice:** source inventory/affected journey → design/update ledger (success, failure, privacy, boundaries) → regression RED if defect → smallest fix → tests and full CI completed/success on exact SHA → risk-based authorized QAS browser + DB/audit checks → actual case status and screenshot → defect/follow-up → GitHub checkpoint. Reopen accepted journeys only on real regression/change.

**Weekly/release overview** (without arbitrary time-based false closure): C1–C5 ledger totals grouped by PASS/FAIL/BLOCKED/NOT RUN/PARTIAL; critical blocker register G01–G10; source and QAS SHA gap; defect age/owner; E2E cycle outcomes; unanswered policy decisions; next runnable batch. Report coverage only as **accepted eligible case count / approved applicable case count**, separately report pending decisions, blocked cases and excluded scope. Never average gate percentages into GO.

**Owner review:** short business/demo signoff on important cycle outcomes (catalog/customer purchase, merchant POS day, Workforce payroll, multi-role privacy, restore), not manual repetition of all technical checks.

## 8. Current checkpoint / explicit truth (no execution in this plan)

- **Previously verified source:** C1 registration race minimal fix on `ea672d5888fb3eb5a7d9b4232f555100f3119e78`; [CI #39043137013](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/39043137013) completed/success (1,011 tests / 19,626 assertions), as recorded in CURRENT_PHASE. This does **not** mean login/registration QAS accepted; a new documentation commit needs its own exact-SHA CI verification.
- **QAS last operator-reported app deployment:** `a682074f`; current live running SHA/DB identity not freshly verified here. C1-A #2 remains **BLOCKED** at preflight; source-only auth review can continue without QAS writes.
- **Guest prior evidence:** limited slices as above; C1–C5 as a whole OPEN, Workforce W-ORG-01b BLOCKED, Paymob provider E2E OPEN, OTP/social login, session revocation, import V1 behavior and relevant policies remain DECISION OPEN.
- **At creation:** all *new* scenario ledger rows start `NOT RUN` (not claiming fresh execution). Existing A01–A15 execution pack remains separate `BLOCKED/NOT RUN` according to its record; don't duplicate execution or silently overwrite it.
- **No application source/QAS/Production changes or test execution in this documentation task.** GitHub docs and tracking issue only.

## 9. Next execution slice (do not make another generic plan)

**C1 preflight + case linkage:** link ledger `T-J02`/`T-J03`/`T-J04`/`T-J05` and `T-E01` to existing **A01–A15** without multiplying fixture orders; verify exact newest CI and source; collect read-only A0–A8 QAS preflight evidence using *authorized* paths only. If any preflight is BLOCKED, report the precise reason and advance with one source-only regression/contract decision, not stateful QAS writes. The smallest separately scoped owner authorization is required before creating QAS synthetic records or submitting login/cart/checkout.

## 10. Expansion discipline

Post-Phase1 Merchant Data Import & Migration Center lives in [Issue #10](https://github.com/khaledtag93/dynamic-ecommerce/issues/10) and needs independent MIG-01–MIG-04 acceptance, **after** Phase1 and required tenant-isolation dependencies. Existing exposed import-drafts V1 issue stays inside C3 and must not be silently deferred. Future SaaS/mobile/external integrations gain their own scenario rows when scoped; don't claim their implementation now.

**Handoff:** read CURRENT_PHASE, Phase1 closure register, this file, case ledger, then the existing C1-A pack. Work one bounded slice with one next-step action and update case evidence on GitHub.
