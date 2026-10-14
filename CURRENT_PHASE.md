# CURRENT PHASE

## ACTIVE W-ORG-01b CHECKPOINT — 2026-10-14 (Seven-Persona CI PASS, QAS Gate Blocked)

- **Latest verified test source SHA:** `f0392366` on `sec03-framework-upgrade`. Hardening CI [run 38607285162](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38607285162) **Green: 907 tests / 18,230 assertions**; the two new seven-persona Workforce authorization regression tests PASS. This is **isolated CI MySQL** evidence, not QAS functional acceptance.
- Seven synthetic roles in the tests: Alpha Manager, Alpha Lead, Alpha Worker; Beta Manager, Beta Worker; Operations Manager, Finance Manager. Verified direct/indirect reporting isolation, cross-team denial, employee self-service, global Operations access without payroll, Finance payroll access without Workforce directory rights, and self leave-approval denial. Code: `tests/Feature/WorkforceDemoPersonaMatrixTest.php`.
- **Owner authorizes disposable synthetic data on QAS** during testing and expects a later clean reset. This does not override safeguards for existing records or authorize Production changes. **QAS direct remote command was explicitly blocked by tool safety checks**, and no workaround was attempted.
- **QAS last directly verified application is still `a62ad480`**. No new QAS backup/restoration proof, accounts, personas, fixture mutation, application deployment or Production modification in the continuation. Older baseline dump presence does **not** prove restorability of a current pre-fixture snapshot. **Backup Safety Gate NOT CLEARED; authenticated browser ROLE-01…ROLE-06 BLOCKED; W-ORG-01b OPEN.**
- Persona/evidence plan: `docs/WORG01B_SEVEN_PERSONA_QA_MATRIX_2026-10-14.md`; prior browser and backup evidence: `docs/WORG01B_BROWSER_ACCEPTANCE_2026-10-14.md`.
- **Next:** get approved QAS access restored without bypassing tool checks → verified fresh backup/isolated restore drill → fixture insert by manifest → authenticated Playwright/EN/AR/RTL/mobile and prohibited write checks → exact cleanup → formal acceptance. Preserve Production unchanged.

## ACTIVE CONTINUATION / UI PREFERENCE — 2026-10-14

- The owner explicitly prefers a **clickable next-action control** at the end of meaningful development checkpoints (similar to the earlier ICA collaboration), with the exact next instruction embedded. Use it when the current chat interface supports a working action; otherwise give a copy-ready instruction. This is an interaction preference, not a substitute for executing assistant-led tests.
- Project-wide rule: `PROJECT_RULES.md` section 25, charter: `docs/ASSISTANT_LED_QA_AND_ACCEPTANCE_CHARTER_2026-10-14.md`.
- W-ORG-01b historical browser checkpoint: QAS application last proven `a62ad480`, earlier CI 905 tests/18,188 assertions Green (superseded for isolated test-source CI by 907/18,230 above). **Actual Playwright guest-only QAS smoke 4/4 PASS** on desktop/mobile with RTL login; portable source `tests/browser/workforce-guest-smoke.cjs`, evidence `docs/WORG01B_BROWSER_ACCEPTANCE_2026-10-14.md`. Authenticated multi-persona authorization remains OPEN/BLOCKED: QAS demo users were **not created**, since the pre-fixture backup attempt was blocked by a safety check. Temporary remote fixture scripts were cleaned. Do not close W-ORG-01b or insert fixtures without a verified approved backup/rollback path.

## STANDING QUALITY AGREEMENT — 2026-10-14 (All Flowra Modules)

- **Assistant-led testing is the default:** inspect → define scenarios → implement/regression tests → CI Green → QAS backend/API + authenticated browser automation where available → locale/responsive/accessibility evidence → fix/retest → recorded acceptance before CLOSED.
- Owner primarily validates business policy and product experience; do not assign routine page-by-page technical testing to the owner. If browser tools/test personas are unavailable, mark specific checks BLOCKED rather than claiming acceptance.
- Canonical charter: `docs/ASSISTANT_LED_QA_AND_ACCEPTANCE_CHARTER_2026-10-14.md`; integrated with `PROJECT_RULES.md` and `TEST_PLAN.md`.
- This is a **documentation/process agreement only**; the latest QAS app remains `a62ad480`, W-ORG-01b authenticated multi-persona browser acceptance still OPEN, and Production unchanged.

## LATEST VERIFIED QAS CHECKPOINT — 2026-10-14 (W-ORG-01b Security Scope)

- **Verified application/CI/QAS SHA `a62ad480`** on branch `sec03-framework-upgrade`. Hardening CI https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38599716356 **Green, 905 tests / 18,188 assertions**.
- QAS application/static HTTP 200, maintenance OFF, scope/role migration `2026_10_14_180000_add_workforce_scope_permissions` **Ran [21]**, Scheduler/Queue strict health Green, failed jobs 0.
- **Team Supervisor** role manages direct/indirect reporting team only; no organization-wide Workforce access, salary or payroll data permission. Explicit `workforce.scope.all` maintains the current Operations Manager and Super Admin organization-wide access. Employee lists/stats, attendance, shifts, correction and leave review are scoped server-side; approvals of own leave/correction denied; leave policy and adjustments reserved for organization admins. Workflow details in `docs/QAS_WORKFORCE_ORG_01B_SECURITY_SCOPE_CHECKPOINT_2026-10-14.md`.
- Live QAS read-only smoke: scope permission present; team role present but without global or payroll access, operations manager global scope present; one existing employee unchanged. Pre-deploy DB backup `deploy_backups/qas_before_workforce_scoped_access_20261014_164209.sql` held privately.
- **Remaining manual QAS role acceptance:** add isolated Demo personas and direct-manager hierarchy to test EN/AR/RTL pages; single existing employee cannot demonstrate true cross-team UI behavior. Finance organization's payroll read access, private document classifications and tenant/branch scoping remain open security gates.
- **Payroll/incentives roadmap** `docs/WORKFORCE_COMPENSATION_PAYROLL_ENTERPRISE_ROADMAP_2026-10-14.md` covers raises, compliant deductions, bonus/allowances, automatic overtime, commissions and profit share; do not conflate roadmap with delivered Payroll V1 manual adjustments.
- Production unchanged. **Next:** demo-persona QAS authorization acceptance → leave-duration policy W-LEAVE-02 → assigned manager approval W-APP-03; separate W-PAY-07a payroll confidential-field scope before sensitive rollout.

### Prior review below

## ACTIVE SECURITY WORKSTREAM — W-ORG-01b (Completed, historical implementation notes)

- After QAS `bedb9fc0` W-ORG-01a, work on **team-scoped read and mutation authorization** began. New `WorkforceAccessService` uses employee reporting ancestry for Team Supervisors. `workforce.scope.all` is a separate explicit organization-wide permission; legacy Operations Manager and Super Admin retain authorized global access to avoid silent outages. Team Supervisor lacks that permission, and never receives payroll salary visibility by default.
- Target Workforce endpoints for scoping: Employee Directory and employment mutations, attendance list/void, schedules and assignment, leave request review and corrections, leave-type and balance admin-only settings. Payroll compensation continues under independent `workforce.payroll.*` permissions; a broader payroll field-level/security review is a separate mandatory W-PAY-07a gate.
- **Security acceptance:** direct links to outsiders must 403; filtered lists, counts and live fragments must not leak cross-team data; self approval refused; global organization-only actions denied to team managers. Need PHPUnit/CI Green before any deployment.
- **New enterprise roadmap** `docs/WORKFORCE_COMPENSATION_PAYROLL_ENTERPRISE_ROADMAP_2026-10-14.md`: salary increases, earnings/deductions, rules-driven overtime/leave, bonuses, commission, profit share, compliance and configured payroll policies. Present manual payroll adjustments already exist but automatic overtime/unpaid-leave/proration explicitly do not.
- Historical note at drafting time: QAS previously `bedb9fc0`. **This workstream passed and QAS is now `a62ad480`** per the latest checkpoint above.

## LATEST VERIFIED QAS CHECKPOINT — 2026-10-14 (W-ORG-01a Reporting Hierarchy Foundation)

- Branch `sec03-framework-upgrade`, **QAS application SHA `bedb9fc0`**, Hardening CI https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38595362470 **Green** (901 tests / 18,150 assertions).
- Non-destructive migration `2026_10_14_170000_add_employee_reporting_manager` Ran [20]. Employee profiles have optional direct reporting manager; manager candidate authorization, no self/cycles, audited assignment and EN/AR employee form. No existing employee data assigned automatically.
- QAS app/static HTTP 200, maintenance OFF, strict Scheduler + Queue healthy, failed jobs 0; read-only DB smoke `ORG_SCHEMA=PASS`, employee count 1, assigned manager count 0; pre-migration DB backup retained privately.
- Official evidence: `docs/QAS_WORKFORCE_ORG_01A_CHECKPOINT_2026-10-14.md`.
- **NOT DONE:** W-ORG-01b *employee-scoped* authorization, assigned leave approval and notifications, secured documents, shift template/roster/live status/break policy systems. No Production changes.
- **Next implementation boundary:** W-ORG-01b scoped employee data access, then W-LEAVE-02/W-APP-03/W-DOC-04, followed by versioned configurable Workforce Settings and W-SCHED-05a…05f. Authenticated QAS manager selector visual acceptance remains open.

## CURRENT ACTIVE WORKSTREAM — 2026-10-14 (Workforce Enterprise E2E Review)

- **Additional scheduling/break operations design:** `docs/WORKFORCE_SCHEDULING_BREAK_OPERATIONS_ARCHITECTURE_2026-10-14.md`. Source review confirms no reusable shift templates/rosters, no separate Ready/Not Ready operational presence, no planned break quotas/windows/reminders, and current shift grace 5 minutes is hard-coded. Planned Workforce Settings must expose editable versioned tenant/branch/team policies rather than hardcoding business rules.
- **W-ORG-01a delivered on QAS:** optional direct reporting manager, relationship traversal, cycle guards, audited changes and tests. Separate W-ORG-01b scoped authorization, leave approvals, and document access **remain pending**; hierarchy alone does not confer approval privileges.

- User observed two Draft shifts in admin Scheduled Shifts but no shifts in My Schedule. **Confirmed expected existing behavior:** My Schedule displays Published shifts only. Improved publishing clarity is included in W-SCHED-05.
- Comprehensive code-grounded audit: `docs/WORKFORCE_ENTERPRISE_E2E_AUDIT_2026-10-14.md`.
- Priority gaps: hierarchy is now modeled but globally privileged managers can still act across all leave requests until W-ORG-01b/W-APP-03; leave annual entitlement confused with per-request duration; leave requests Pending even with insufficient annual entitlement until approval; no private employee/leave multi-attachments or assigned approver notifications; leave approval currently blocks even overlapping Draft shifts; UTC/local-day business policy needs deliberate alignment.
- **Execution order:** W-ORG-01 hierarchy and employee-scoped authorization → W-LEAVE-02 leave policy/calendar engine → W-APP-03 assigned approvals/delegation and notifications → W-DOC-04 secure multi-file attachments → W-SCHED-05 schedule/attendance/leave consistency → W-PAY-06 payroll policy/integration and full UAT.
- **Status:** Enterprise E2E architecture audited; W-ORG-01a foundation shipped to QAS at `bedb9fc0`. Planning-only components: scheduling templates/rosters, break quota/notifications, leave routing and sensitive documents. Production unchanged.

## LATEST CONTINUATION CHECKPOINT — 2026-10-14 (Workforce Timezone & Attendance UX)

> This is the current application/QAS state; subsequent October 14 schema-recovery and October 2 financial checkpoints below are historical.

- Branch: `sec03-framework-upgrade`. **Verified application/CI/QAS commit: `a83bc8f0`**.
- **Hardening CI:** https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38587254674 — Green, **896 tests / 18,108 assertions**; schema migrations, Blade/config, PHP, reviewed npm audit, browser interactions and frontend build passed.
- **QAS READY:** deployed `a83bc8f0`, app/static HTTP 200, maintenance OFF, migration `2026_10_14_150000_add_attendance_session_void_audit` Ran, strict Scheduler/Queue health Green, failed jobs 0.
- **Workforce timezone UX:** UTC stored canonically; `WORKFORCE_TIMEZONE` default `Africa/Cairo`; local display and local form input conversion for attendance/scheduling/corrections; readable RTL dates, explicit original vs approved time, live browser-only net/break display, local-day filters.
- **Audited Attendance Void:** manager permission, required explanation, immutable original attendance, no deletion; blocks active/pending-correction/previously voided rows and any overlapping employee payroll snapshot; voided rows excluded from future payroll and active attendance counts.
- **Verified live QAS read-only smoke:** timezone UTC vs Africa/Cairo, previous `14:35 UTC → 17:35 Cairo`; three existing session rows intact, no rows voided, void columns present; pre-deploy private DB backup preserved at `deploy_backups/qas_before_attendance_timezone_void_20261014_151347.sql`.
- **Production unchanged**. Detailed evidence: `docs/QAS_WORKFORCE_TIMEZONE_ATTENDANCE_UX_CHECKPOINT_2026-10-14.md`.
- **Remaining acceptance:** manual authenticated EN/AR/RTL/mobile UI review, open counter, correction forms and demo-only Void; no user records have been modified during validation. Existing Payroll periods still use UTC date clipping and are not silently reallocated to Cairo local days; any change requires explicit payroll policy/reconciliation.
- OPS-01/PAY-01/OPS-02/Production scheduler-queue remain open.

**Next action:** finish authenticated QAS visual acceptance and only then resume the first genuinely unclosed monetary/accounting boundary from SHA `a83bc8f0`.

### Historical checkpoints below

## LATEST CONTINUATION CHECKPOINT — 2026-10-14

> This section is authoritative. The 2026-10-02 financial-integrity checkpoint below remains historical.

- Active branch: `sec03-framework-upgrade`.
- **Latest verified application, CI and QAS SHA:** `c4801411`.
- **Hardening CI** https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38578100532 **Green**: 889 PHPUnit tests, 18,015 assertions, fresh MySQL migration, Blade/config, browser interactions, frontend audit baseline and build.
- **QAS deployed `c4801411`**: application/static HTTP 200, maintenance OFF, new Workforce recovery migration Ran, Scheduler/Queue strict health Green, zero failed jobs.
- **Workforce 500 root cause confirmed in QAS log:** table `employee_attendance_breaks` was absent while its original migration was recorded as Ran. The new idempotent recovery migration restored its original table structure. Read-only QAS smoke passed table/columns, attendance relations and schedule relations.
- **White-label-aware sidebar clarity source/QAS deployed:** higher-contrast hover, active page, group indicator, RTL logical active rail, keyboard focus; uses existing Brand & Identity sidebar/primary colors.
- **QAS backup before schema recovery:** `deploy_backups/qas_before_workforce_schema_repair_20261014_135752.sql` (server only, not committed).
- **Open acceptance:** authenticated browser review of My time clock / Work schedule / Attendance in EN/AR, plus current white-label theme/mobile/RTL hover/active appearance. Schema repair alone does not recover any missing historical break rows. Cause of table disappearance is not yet confirmed.
- **Production unchanged.** OPS-01, PAY-01, OPS-02 and Production scheduler/queue gates remain open.
- Detailed evidence: `docs/QAS_WORKFORCE_SIDEBAR_RECOVERY_CHECKPOINT_2026-10-14.md`.

**Immediate next action:** complete targeted authenticated QAS Workforce page and sidebar visual acceptance, fix only reproduced defects, then resume the remaining genuine monetary/accounting integrity audit from verified application SHA `c4801411`. Do not reopen previously closed financial boundaries without evidence.

### Historical checkpoints below

## LATEST CONTINUATION CHECKPOINT — 2026-10-02

> This section is authoritative for continuation. Older checkpoints below are historical and must not override this state.

- Active branch: `sec03-framework-upgrade`.
- **Latest verified application / CI SHA:** `94a1facad7e5eca2b45c624ac83c438cec2d2870` (`94a1faca`).
- **Hardening CI #2436: Green** on that exact application revision. The Admin Orders Row Payment & Currency Integrity checkpoint is `94a1faca`.
- **Latest verified QAS application SHA:** `94a1facad7e5eca2b45c624ac83c438cec2d2870` (`94a1faca`). Promotion from `e42f2fba` completed successfully with application/static health HTTP 200, maintenance OFF, no pending migrations, scheduler + queue worker healthy, failed jobs 0, one non-failed database-queue job pending during verification, and strict ops health Green.
- **Order Discount Line-Profit Integrity is CLOSED for source + full CI.** Accounting line profit now allocates discounted merchandise revenue deterministically in exact cents across order lines before subtracting authoritative lot/fallback COGS. This closes the storefront coupon/promotion gap without double-applying POS discounts. The policy is accounting allocation by gross line value, not marketing attribution. Historical discounted orders have a bounded dry-run/apply repair command: `commerce:reconcile-order-line-profit`.
- **Offer Analytics Discount Economics is CLOSED for source + full CI.** Paid and partially-refunded discounted orders drive offer/coupon performance, effective discount rate and margin. Fully-refunded discounted losses remain visible in a separate loss bucket instead of distorting coupon rankings or realized promotion KPIs; cancelled orders remain excluded.
- **Paymob Checkout Currency Integrity is CLOSED for source + full CI.** Provider checkout is blocked before any Paymob request when gateway currency, order currency, payment currency, payment ownership or payment amount diverge. The provider payload uses the authoritative validated order currency/amount.
- **Customer business-time chronology and Growth experiment attribution are CLOSED for source + full CI.** Customer recent-order logic and experiment attribution use business timestamps rather than mutation timestamps, preventing later updates from rewriting commercial chronology.
- **Exchange / Replacement Financial Integrity remains CLOSED for source + full CI + QAS.** The earlier Returns / Refunds / POS, partial-return quantity, non-monetary RMA restatement, order completion reporting and customer-value currency integrity slices remain closed unless a reproduced defect reopens them. Order Cash Summary was legitimately reopened by a reproduced QAS defect and is reclosed at the explicit checkpoint below.
- **Refunded-loss Analytics / Growth Integrity remains CLOSED**, with one clarified offer boundary: fully-refunded completed orders do not inflate purchase/conversion/order/quantity counts; their non-restocked COGS and negative profit remain visible in core analytics, product/category/variant profitability, Growth attribution and the dedicated offer refunded-loss evidence, while coupon performance/ranking contains only commercially realized orders.
- **Refund Component Allocation Integrity is CLOSED for source + full CI + deployed-QAS runtime evidence.** Refunds now persist explicit merchandise/shipping/tax/payment-excess allocation; `refund_total` remains total cash returned while `commercial_refund_total` tracks only commercial order-value refund. Payment-excess returns therefore do not reduce realized commercial revenue. Legacy rows retain merchandise-first compatibility. QAS refund-ledger dry-run scanned 1 candidate with `changed=0` / `applied=0`; a transaction-wrapped QAS refund smoke passed automatic allocation, component bounds, payment-excess isolation, realized revenue and rollback cleanup.
- **Shipping Money Boundaries are CLOSED for source + full CI + deployed-QAS runtime evidence.** Shipping rate and free-shipping threshold inputs reject monetary over-precision, threshold calculations use exact cents, exact-threshold and one-cent-below behavior are deterministic, and before/after-discount policies remain explicit. Transaction-wrapped QAS smoke passed and rolled all test rows back.
- **Product Cost Money Integrity is CLOSED for source + full CI + deployed-QAS runtime evidence.** Cost Calculator money/quantity inputs now enforce persisted precision/ranges, calculations use exact `BigDecimal` arithmetic, material line costs round Half-Up to cents, derived totals/profit/margin are range-guarded, overflow rolls back atomically, and `cost_price` remains separate from `inventory_cost_price`. The deployed controller-path smoke passed exact arithmetic, over-precision rejection, overflow rollback, margin overflow protection, and cleanup.
- **Coupon Money Integrity is CLOSED for source + full CI + deployed-QAS runtime evidence.** Coupon value/minimum/cap inputs now match `DECIMAL(12,2)`, percentage values use exact decimal comparison, minimum-subtotal eligibility and discount calculation share the same cent-normalized rule, percentage discounts round Half-Up to cents, caps/subtotal clamps are exact, and the deployed smoke passed threshold/binary-noise/half-cent/cap/clamp/validation cases with rollback cleanup.
- **Promotion Money Integrity is CLOSED for source + full CI + deployed-QAS runtime evidence.** Promotion discount/minimum inputs match `DECIMAL(12,2)`, percentage comparisons and order/category percentage calculations use exact decimals with Half-Up cent rounding, Buy-X-Get-Y accumulates cheapest eligible units in exact cents, best-discount selection is exact, and legacy invalid discounts are clamped to the subtotal. The deployed smoke passed threshold, half-cent, category, Buy-X-Get-Y, legacy clamp and validation cases with rollback cleanup.
- **Catalog Price Money Integrity is CLOSED for source + full CI + deployed-QAS runtime evidence.** Product and Variant selling prices now enforce the real `DECIMAL(10,2)` limit across full editor, bulk variant and inline-price flows; sale-vs-regular comparisons are exact decimals; variant normalization preserves decimal strings; and QAS runtime evidence passed max/range/precision/comparison/persistence/rollback cases.
- **Refund Entry Money Integrity is CLOSED for source + full CI + deployed-QAS runtime evidence.** Admin refund and RMA inputs enforce exact persisted precision, controllers preserve decimal strings, order refund/idempotency/refundable-balance/full-refund logic is cent-authoritative, refund allocation removes float-to-cent conversion, RMA capacity compares cents, EN/AR failures are covered, and the deployed smoke passed generic/RMA over-precision, exact full-refund, canonical idempotency and rollback cleanup.
- **Fulfillment Payment Money Integrity is CLOSED for source + full CI + deployed-QAS runtime evidence.** COD completion and non-COD paid-ledger readiness now compare payment evidence and order totals in exact cents instead of binary float/round arithmetic while preserving existing currency and lifecycle guards. The deployed transaction-wrapped smoke passed split `0.10 + 0.20 = 0.30`, rejected `0.29` mismatches, completed exact-cent COD, preserved mismatch state, and rolled all test rows back.
- **Payment Capture & Reconciliation Money Integrity is CLOSED for source + full CI + deployed-QAS runtime evidence.** Manual capture guards, gateway over-capture detection, payment/refund ledger synchronization and provider-reversal reconciliation now make monetary decisions in exact cents while preserving the existing manual-vs-gateway lifecycle policy. The deployed smoke passed exact split captures/refunds, rejected a one-cent manual over-capture, recorded a one-cent gateway over-capture, and rolled all test rows back.
- **Cart & Checkout Money Composition is CLOSED for source + full CI + deployed-QAS runtime evidence.** Cart subtotal/discount/total composition and checkout subtotal/discount/shipping/tax/grand-total persistence now use exact cents. Coupon-first stacking remains intact, promotion discount is capped to the remaining subtotal, combined effective discount cannot exceed merchandise subtotal, and QAS verified `0.10 × 3 = 0.30`, stacked discount capping, exact Order/Payment/OrderItem persistence and rollback cleanup.
- **POS Cash Shift Money Integrity is CLOSED for source + full CI + deployed-QAS runtime evidence.** Opening/closing cash validates exact persisted precision, reconciliation converts cash sales/refunds to integer cents, expected drawer cash and variance are composed in cents, and the existing `999999999.99` user-facing amount limit remains unchanged. QAS verified split `0.10 + 0.20 = 0.30`, exact refund `0.10`, expected `0.30`, counted `0.20`, variance `-0.10`, over-precision rejection and rollback cleanup.
- **POS Sale Money Integrity is CLOSED for source + full CI + deployed-QAS runtime evidence.** POS price resolution, discount calculation/allocation, tender validation, change and persisted Order/Payment/line totals are cent-authoritative. Monetary inputs reject over-precision, 5% of `0.30` rounds Half-Up from `0.015` to `0.02`, exact `0.28` sale totals and `0.02` change persist correctly, and empty/no-discount semantics remain compatible.
- **Customer Account Statement Money Integrity is CLOSED for source + full CI + deployed-QAS runtime evidence.** Order/payment/refund totals and movement amounts remain canonical two-decimal strings through aggregation and CSV export instead of binary floats. QAS reproduced and then closed a strict-MySQL `ONLY_FULL_GROUP_BY` defect by grouping all currency aggregates on the selected `statement_currency` alias. Final QAS smoke verified exact `0.10 + 0.20 = 0.30`, exact refund `0.10`, canonical movement values, exact CSV output and rollback cleanup.
- **Customer Commercial KPI Money Integrity is CLOSED for source + full CI + deployed-QAS runtime evidence.** Customer list realized spend, aggregate customer revenue, profile gross/refund/net spend and average order value now use exact decimal/cents arithmetic instead of binary floats. AOV rounds Half-Up at cent precision, existing commercially-realized/ranking policy remains unchanged, strict-MySQL grouping uses selected aliases, and QAS verified exact list spend/revenue `0.10`, profile gross `0.20`, refund/net `0.10`, AOV `0.03`, and rollback cleanup.
- **Payment Captured Summary Money Integrity is CLOSED for source + full CI + deployed-QAS runtime evidence.** Admin Payments now groups captured history by a canonical currency using Payment-to-Order-to-EGP fallback, normalizes case before aggregation, and preserves SQL DECIMAL totals as exact two-decimal strings. QAS first reproduced a strict-MySQL grouping defect on `94cb9b30`; final `13c20685` groups by the selected `statement_currency` alias and the same smoke then passed blank-currency USD fallback, lowercase `usd` normalization, exact `0.10 + 0.20 = 0.30`, and rollback cleanup.
- **Order Cash Summary Money Integrity is CLOSED again for source + full CI + deployed-QAS runtime evidence.** A read-only QAS probe reproduced MySQL error 1055 in the actual Admin Orders finance summary, so the previously closed boundary was legitimately reopened. Final `3ed0bfda` normalizes Payment/Order currency before aggregation, groups captured/refund totals by the selected `statement_currency` alias, and computes net/refund money with exact `BigDecimal` decimals instead of float/round arithmetic. QAS then passed exact USD `0.10 + 0.20 - 0.10 = 0.20`, EGP `1.00 - 0.25 = 0.75`, currency separation and rollback cleanup.
- **Analytics Dashboard Summary Money Integrity is CLOSED for source + full CI + deployed-QAS runtime evidence.** QAS first reproduced binary-float drift in both daily-aggregate and raw-event summary paths: `0.10 + 0.20` surfaced internally as `0.30000000000000004` and AOV as `0.15000000000000002`. Final `d0059ee8` keeps revenue, discount, shipping, realized revenue, AOV and money comparison deltas as exact two-decimal `BigDecimal`-derived strings; AOV rounds Half-Up. Its daily/raw QAS smoke remains Green after the later Product / Category change.
- **Analytics Product / Category Money Integrity is CLOSED for source + full CI + deployed-QAS runtime evidence.** QAS on `d0059ee8` reproduced float drift in Product Drilldown totals (`0.30000000000000004` revenue, `0.21000000000000002` profit, `0.15000000000000002` ARPP), float money in raw Top Product/Category and Top Variants, with `AGG_EXACT=NO`, `VARIANT_EXACT=NO`, and `RAW_EXACT=NO`. Final `538ce8fc` keeps Product/Category revenue, realized COGS, profit and ARPP as exact two-decimal decimals and Top Variant economics in integer cents/canonical money strings. The identical QAS probe then passed `AGG_EXACT=YES`, `VARIANT_EXACT=YES`, `RAW_EXACT=YES` and rollback cleanup.
- **Admin Dashboard Currency Integrity is CLOSED for source + full CI + deployed-QAS runtime evidence.** QAS on `538ce8fc` reproduced a real multi-currency defect: a temporary EGP `1.00` order plus USD `2.00` order were combined into the single KPI `EGP 10,003.00`, and the mixed-currency AOV became `EGP 2,500.75`. Final `8a7e5414` groups Gross Order Value and AOV by normalized currency, uses exact decimal AOV rounding, and shows each Recent Order with its own currency. The same transaction-wrapped QAS probe then matched exact per-currency expected values with `DASHBOARD_CURRENCY_EXACT=YES` and rollback cleanup.
- **Analytics Aggregation write path was inspected on QAS and intentionally left unchanged.** The real `aggregateDay()` persistence path preserved exact DECIMAL values for `0.10 + 0.20`, product COGS/profit and the Half-Up AOV case `0.29 / 2 = 0.145 -> 0.15`; the probe returned `PERSISTED_EXACT=YES` with rollback cleanup. No persistence defect was reproduced.
- **Admin Orders Row Payment & Currency Integrity is CLOSED for source + full CI + deployed-QAS runtime evidence.** QAS on `8a7e5414` first proved that a USD order row was hard-coded as EGP for Total, Refunded and Net paid. Intermediate `e42f2fba` fixed row currency labels, then QAS proved a second defect: an unpaid USD `4.00` order with zero Payment rows still displayed `Net paid: USD 4.00`. Final `94a1faca` derives row Net paid from captured Payment ledger rows minus processed OrderRefund rows with exact decimal arithmetic, while normalized order currency labels remain correct. Final QAS returned `ADMIN_ORDERS_CURRENCY_DEFECT=NO`, `ADMIN_ORDERS_NET_PAID_DEFECT=NO`, and rollback cleanup.
- A separate transaction-wrapped QAS runtime smoke also passed discounted Storefront/POS profit, Offers refunded-loss rendering and Paymob currency preflight, leaving no test rows behind. Authenticated browser acceptance remains a separate gate.
- Detailed evidence: `docs/REFUND_COMPONENT_ALLOCATION_INTEGRITY_CHECKPOINT_2026-10-01.md`, `docs/SHIPPING_MONEY_BOUNDARIES_CHECKPOINT_2026-10-01.md`, `docs/PRODUCT_COST_MONEY_INTEGRITY_CHECKPOINT_2026-10-01.md`, `docs/COUPON_MONEY_INTEGRITY_CHECKPOINT_2026-10-01.md`, `docs/PROMOTION_MONEY_INTEGRITY_CHECKPOINT_2026-10-01.md`, `docs/CATALOG_PRICE_MONEY_INTEGRITY_CHECKPOINT_2026-10-01.md`, `docs/REFUND_ENTRY_MONEY_INTEGRITY_CHECKPOINT_2026-10-01.md`, `docs/FULFILLMENT_PAYMENT_MONEY_INTEGRITY_CHECKPOINT_2026-10-01.md`, `docs/PAYMENT_CAPTURE_RECONCILIATION_MONEY_INTEGRITY_CHECKPOINT_2026-10-01.md`, `docs/CART_CHECKOUT_MONEY_COMPOSITION_CHECKPOINT_2026-10-01.md`, `docs/POS_CASH_SHIFT_MONEY_INTEGRITY_CHECKPOINT_2026-10-01.md`, `docs/POS_SALE_MONEY_INTEGRITY_CHECKPOINT_2026-10-01.md`, `docs/CUSTOMER_ACCOUNT_STATEMENT_MONEY_INTEGRITY_CHECKPOINT_2026-10-01.md`, `docs/CUSTOMER_COMMERCIAL_KPI_MONEY_INTEGRITY_CHECKPOINT_2026-10-01.md`, `docs/PAYMENT_CAPTURED_SUMMARY_MONEY_INTEGRITY_CHECKPOINT_2026-10-01.md`, `docs/ORDER_CASH_SUMMARY_MONEY_INTEGRITY_CHECKPOINT_2026-10-01.md`, `docs/ANALYTICS_DASHBOARD_SUMMARY_MONEY_INTEGRITY_CHECKPOINT_2026-10-02.md`, `docs/ANALYTICS_PRODUCT_CATEGORY_MONEY_INTEGRITY_CHECKPOINT_2026-10-02.md`, `docs/ADMIN_DASHBOARD_CURRENCY_INTEGRITY_CHECKPOINT_2026-10-02.md`, and `docs/ADMIN_ORDERS_ROW_PAYMENT_CURRENCY_INTEGRITY_CHECKPOINT_2026-10-02.md`.
- Production remains unchanged.

**Immediate next action:** re-scan the remaining business-critical monetary, payment and currency calculations from exact verified QAS revision `94a1faca`, then choose one genuinely unclosed boundary with reproduced evidence. Do not reopen the closed Admin Orders row payment/currency boundary, Admin Dashboard currency boundary, Analytics Summary/Product/Category/Variant slices, or Analytics Aggregation write path without new evidence. Keep controlled authenticated browser acceptance queued for the dedicated testing phase. Production release gates remain OPS-01 credential rotation evidence, PAY-01 real Paymob E2E, OPS-02 isolated DB restore rehearsal, and Production scheduler/queue verification.

### Historical checkpoints below

## Sales COGS / Profit Integrity — QAS checkpoint — 2026-09-29

- Active branch: `sec03-framework-upgrade`.
- **Verified application / CI / QAS SHA:** `7c66bc19f074d69c007be8fcc1027fb011f7af0d` (`7c66bc19`) — `fix: derive sales cogs from lot provenance`.
- **Hardening CI #2252: Green** on that exact revision.
- QAS is deployed on the exact same SHA; maintenance OFF, application/static health HTTP 200.
- Immutable tag: `qas-sales-cogs-integrity-2026-09-29`.
- Order-level COGS/profit now derives from actual FEFO lot provenance, including mixed-cost lots, POS consumption, POS/RMA restock recovery and online-payment re-reservation.
- Legacy orders without lot provenance retain their historical `unit_cost` fallback.
- `order_items.profit_amount` is not accounting-authoritative until an explicit storefront order-discount allocation policy is defined.
- Detailed evidence: `docs/SALES_COGS_INTEGRITY_CHECKPOINT_2026-09-29.md`.

**Immediate next action:** continue the Business Process & ERP Integrity Audit at the next unclosed profitability/accounting boundary. Audit any reporting or inventory history that still treats aggregate `inventory_movements.unit_cost` as actual COGS instead of lot provenance; fix only if it is consumed as accounting evidence.


## Workforce Payroll Integrity — QAS checkpoint — 2026-09-29

- Active branch: `sec03-framework-upgrade`.
- **Verified application / CI / QAS SHA:** `123fcbc9f10eb5fb6b6ed24bf628af877aa31ee6` (`123fcbc9`) — `fix: clarify payroll paid settlement boundary`.
- **Hardening CI #2250: Green** on that exact application revision.
- QAS was promoted from `523af8f8` to `123fcbc9`; all 25 pending ERP-integrity migrations ran successfully through `2026_09_29_001000_enable_compensation_history`.
- QAS post-deploy evidence: remote HEAD `123fcbc9`, maintenance OFF, Home HTTP 200, Login HTTP 200, static asset HTTP 200, normal Admin redirect, unauthenticated Payroll route redirects to Login.
- Immutable repository tag: `qas-payroll-integrity-2026-09-29`.
- **Payroll Integrity is CLOSED for source / full CI / deployed-QAS integrity evidence.** Authenticated manual/destructive Payroll browser acceptance remains a separate acceptance gate.
- Closed rules include effective-dated compensation history, retroactive rate authority, explicit proration blockers, stable attendance/leave inputs, strict money precision, exact-cent summaries, currency separation, model-level finalized-run immutability and explicit Paid-vs-bank-settlement semantics.
- V1 intentionally does not claim statutory tax/social insurance, leave monetization, salary/rate proration, payroll bank settlement, payroll-file acceptance, or full effective-dated employment-status history.
- Production remains unchanged. OPS-01, PAY-01, OPS-02 and Production scheduler/queue verification remain independent release gates.
- Detailed evidence: `docs/PAYROLL_INTEGRITY_CHECKPOINT_2026-09-29.md`.

**Immediate next action:** run the controlled authenticated Workforce / Payroll QAS acceptance checklist against `123fcbc9` using controlled test data only. Fix only reproduced defects with regression coverage; then continue the next genuinely unclosed Business Process & ERP Integrity boundary.


## Payment disputes / chargebacks readiness — active source checkpoint — 2026-09-29

- Active branch: `sec03-framework-upgrade`.
- Latest application source HEAD: `b42e525b` — `fix: preserve provider reversal evidence`.
- Provider refund/void evidence is preserved without downgrading paid payments or fabricating canonical `order_refunds` rows.
- Replayed identical reversal evidence is idempotent; the related order retains the reconciliation evidence.
- Current Paymob callback data does not provide a trusted dispute/chargeback lifecycle, so Flowra does not infer chargebacks from failures, declines, refunds, voids, or free-form provider statuses.
- A future dispute module must be driven by a real provider/API/webhook source and model its own immutable lifecycle, amount/currency, evidence deadlines, outcomes, fees and reconciliation/accounting effects.
- Full local PHPUnit is unavailable in the current checkout because `vendor/` is absent; syntax/JSON/diff checks passed before the application commit. Integrated CI remains the authoritative full-suite gate.
- QAS and Production remain unchanged for this newer source.
- Detailed checkpoint: `docs/PAYMENT_DISPUTE_READINESS_CHECKPOINT_2026-09-29.md`.

**Immediate next action:** verify the latest-head CI evidence, then continue the Business Process & ERP Integrity Audit at the next unclosed financial/ERP boundary. Do not create a synthetic chargeback workflow without a trusted dispute source.


## Supplier obligations / Operational AP integrity — active source checkpoint — 2026-09-28

- Active branch: `sec03-framework-upgrade`.
- **Latest application source HEAD:** `523af8f8` — `fix: restore admin products index`.
- **Latest confirmed Green CI:** Hardening CI **#2177** on `523af8f8`.
- **Latest verified QAS application is `523af8f8`** — `fix: restore admin products index`. Deploy completed successfully on 2026-09-28 with application HTTP 200, static assets HTTP 200, maintenance OFF, and an authenticated manual smoke confirming Admin → Products opens successfully on QAS.
- **Production remains unchanged.** OPS-01, PAY-01, OPS-02 and Production scheduler/queue verification remain independent release gates.

Closed in the current Supplier Obligations / Operational AP source slice:
- received-item-value supplier payable authority; shipping/tax remain outside Operational AP until a deliberate invoice/GL layer exists;
- partial supplier settlement, overpayment prevention, idempotent recording and void ledger;
- receipt reversal blocked when active supplier payments would exceed remaining received value;
- finance vs inventory responsibility split, including dedicated `purchasing.settlements.manage` permission and role-aware UI actions;
- supplier payment effective dates cannot predate the first active receipt or be future-dated;
- backdated payments are bounded by received value available **as of the payment date**, not current receipts;
- voiding a settlement does not erase it from historical as-of calculations before its `voided_at` timestamp;
- supplier-payment service boundary enforces exact `DECIMAL(12,2)` money format and rejects over-precision/scientific notation instead of truncating silently;
- Purchase currency display/aggregation is currency-safe and mixed currencies are never summed under one currency label;
- new Purchase Orders require an active Supplier both at validation time and again under transaction lock to avoid inactive-supplier race conditions;
- supplier deletion remains serialized and purchase history prevents deletion of a supplier already referenced by procurement history.

**Immediate next action:** finish CI confirmation for the newest Supplier/AP commits, then continue the same Business Process & ERP Integrity Audit at the next accounting boundary. Do not start visual-polish or unrelated feature work while this integrity slice is active. After a stable source/CI checkpoint, deploy deliberately to QAS and run a controlled authenticated supplier-settlement smoke before calling the slice QAS-closed.

### Earlier checkpoints below

## Purchasing + Inventory / Lot-Batch QAS checkpoint — 2026-09-28

- Active branch: `sec03-framework-upgrade`.
- **Verified source / CI / QAS application:** `1c90222e4cedabe49ac9e704401e9890875095f1` (`1c90222e`) — `test: preserve bounded sellable stock export`.
- **Hardening CI #2151: Green** on that exact SHA. The gate passed Composer validation/audit, PHP/Bash syntax, clean MySQL migration, Laravel boot/routes, config/Blade compilation, full PHPUnit, frontend dependency audit, shared browser interactions and production asset build.
- **QAS is deployed on the exact same SHA.** Deployment completed successfully with application health HTTP 200, static asset health HTTP 200 and maintenance mode OFF.
- Immutable repository checkpoint tag: `qas-lot-batch-integrity-2026-09-28`.
- The deployment applied the Purchasing/Inventory schema through `2026_09_28_005000_create_inventory_lot_ledger`, including partial receiving, cancellation audit, inventory valuation cost, purchase receipt reversal audit and the inventory lot ledger.
- **Lot/Batch Integrity V1 is CLOSED for the audited source/CI/QAS slice.** Physical lot availability is FEFO-aware; reservation release restores the original lot allocations; expired lots are excluded from sellable stock; cancellation/POS/RMA return flows preserve stock origin; legacy negative aggregate stock repair does not create fake lots; lot tracking represents physical on-hand only.
- **Sellable Inventory alignment is CLOSED for the audited slice.** Storefront/catalog/admin stock availability now uses sellable lot-aware quantity instead of raw aggregate stock where appropriate, including catalog list hydration, recommendation ranking, Admin dashboard/product workspace stock views and bounded export behavior.
- CI #2150 failure on `7e61ff07` was a regression-test expectation mismatch after the stock semantics changed, not a business-logic failure. `1c90222e` updates that bounded-export contract and #2151 is fully Green.
- QAS was upgraded from `0054e7d1` to `1c90222e` using the versioned `deploy-qas.sh sec03-framework-upgrade` path with the dedicated SSH key; future QAS deploys can be executed directly from the connected personal device when it is online and the key remains valid.
- **Production remains unchanged.** Do not infer Production readiness from this QAS closure. OPS-01 credential rotation evidence, PAY-01 real Paymob E2E, OPS-02 isolated database restore rehearsal and Production scheduler/queue setup/verification remain release gates.
- **Immediate next action:** run a short authenticated QAS smoke/acceptance pass for the new Purchasing + Inventory / lot-batch journeys using controlled test records, fix only reproduced defects, then continue the Business Process & ERP Integrity Audit with the next unclosed domain. Do not reopen Commerce Financial Integrity or Lot/Batch Integrity V1 without a reproduced defect.

### Earlier checkpoints below

## Financial Integrity QAS checkpoint — 2026-09-27

- Active branch: `sec03-framework-upgrade`.
- **Verified source / QAS application:** `0054e7d110520d3f9c9c3b3275e02c71e8eec978` (`0054e7d1`).
- **Hardening CI #2135: Green** on that exact SHA.
- QAS deployment is operator-confirmed on the same SHA. Deployment completed with application health HTTP 200 and static asset health HTTP 200; maintenance mode is off.
- Immutable repository tag: `qas-financial-integrity-2026-09-27`.
- The current closed source/QAS slice is **Commerce Financial Integrity**: Orders, Payments, Refunds, POS cash-shift reconciliation, customer commercial value, offer/coupon analytics, and realized-commerce metrics.
- Key lifecycle contracts now enforced include: paid balances must be refunded before cancellation; cancelled orders cannot be manually reopened through payment status changes; late gateway payment after cancellation preserves financial truth without reactivating stock/fulfillment and raises a refund-required exception; full refund resolves that exception; POS cash reconciliation preserves the original cash sale and records refunds as separate cash outflows.
- Realized-commerce reporting uses completed + paid/partially-refunded orders and net revenue after recorded refunds; cancelled/unpaid orders are excluded from customer value, growth, cohort, attribution and offer analytics.
- Matching regression coverage and the full Hardening suite are green. Public QAS sanity checks for storefront, login, admin redirect, static assets and baseline security headers pass.
- **Status:** this financial-integrity slice is CLOSED for source/CI/QAS deployment evidence. Authenticated destructive browser scenarios should only be rerun when needed against controlled QAS test records; do not mutate arbitrary QAS business data merely to repeat CI-proven invariants.
- **Next source slice:** Purchasing + Inventory lifecycle integrity — receiving, cancellations/reversals, supplier obligations, stock valuation/cost lineage, partial receiving, duplicate/replayed receipts, and cross-module accounting boundaries.
- Production remains unchanged. Production release gates remain separate and include OPS-01 credential rotation evidence, PAY-01 real Paymob E2E, OPS-02 isolated database restore rehearsal, and Production scheduler/queue verification.

### Earlier checkpoints below

## QAS candidate gate — 2026-09-27

- Active branch: `sec03-framework-upgrade`.
- Application candidate: `583717c655b21bb7803ce86cc6cac1ddaa2bb6aa` (`583717c6`), with Hardening CI **#2073 Green**.
- Order Cancel live state and Address Book Save/Delete live state are already closed in source with regression coverage; do not reopen them without a reproduced defect.
- The later hardening chain also closes cart busy-state alignment, Admin order/payment contracts, Inventory adjustment/variant risks/translation parity, POS form contracts, purchase monetary bounds, supplier-delete serialization, and Customer Account Statement scalability.
- There is no known source blocker that should delay refreshing QAS to this candidate. The next meaningful gate is exact-revision authenticated QAS acceptance, not another speculative source sweep.
- Deploy this rehearsal branch explicitly because `deploy-qas.sh` defaults to `v42-clean-baseline`: `./deploy-qas.sh sec03-framework-upgrade`.
- After deployment, execute `docs/COMPLETION_PASS_QAS_CHECKLIST_2026-09-25.md` and fix only reproduced findings before release-readiness review.
- Production remains unchanged. OPS-01, PAY-01 and OPS-02 remain separate Production release blockers; OPS-03 still requires Production runtime setup/verification at release.

### Earlier checkpoints below
## Storefront account/auth semantics checkpoint — 2026-09-26

- Active source branch: `sec03-framework-upgrade`. GF-17 application source is `e82b3f9d82afdc3be71506827b29f71fb377f967`.
- Shared account navigation now uses an exact Account Overview match instead of broad `account.*`, preventing Address Book from exposing two different destinations as `aria-current="page"`. The desktop account dropdown now exposes and styles the actual current account/order/address/notification destination.
- Login and Register now use the same `lc-page-shell` geometry as the rest of customer account access and explicitly associate labels with every credential field. Regression coverage renders the Address Book route and protects the auth label/shell contract.
- **Gate:** GF-17 is source-implemented but remains **IN REVIEW**. The branch workflow is configured to run Hardening CI on every push, but the available GitHub connector does not expose push-triggered workflow runs for this commit, so no green CI claim is recorded here yet. Matching-revision EN/AR desktop/mobile keyboard QAS acceptance is also outstanding. Production unchanged.
- **Next:** verify the final Hardening CI evidence when available, then continue the remaining shared Storefront/customer shell review before strict page-by-page closure.

### Earlier checkpoints below

## Shared Storefront performance/accessibility checkpoint — 2026-09-26

- Active source branch: `sec03-framework-upgrade`. Final application HEAD for this checkpoint is `ba00f303b2e32fb8cb76ce84f25af89d449fadf7`.
- GF-14 follow-up/page actions: `3fe0f28` keeps merchant-configured Home/hero/banner actions navigable when known target sections are hidden; [CI 36256650267](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36256650267) passed.
- GF-15 performance: `a536b22` reuses shared Storefront view data once per HTTP request and replaces per-category product-count queries with bounded `withCount`; [CI 36256978023](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36256978023) passed.
- GF-16 accessibility/motion: Admin + Storefront now have bilingual skip-to-content links, explicit focusable main landmarks and reduced-motion behavior. Storefront account-nav auto-scroll is decoupled from the `/` search shortcut and respects reduced-motion preference. Final `ba00f303` passed [Hardening CI 36257847029](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36257847029): 470 PHP tests / 13,982 assertions plus shared JS interaction tests, clean MySQL, Composer audit, Blade/config compile and frontend build.
- Last documented QAS application remains `f1f20297a27e2236603788ac7cc343dbbf86d0b6`; none of GF-14/15/16 is QAS-accepted merely because source CI is green. Production remains unchanged. OPS-01, PAY-01 and OPS-02 remain P0 release gates.
- **Next:** continue the shared Storefront/customer shell review (footer/account/auth, conditional navigation, mobile/RTL/accessibility and only worthwhile CSS/JS cleanup), then move into strict page-by-page closure. When this newer source is deployed to QAS, perform exact-revision EN/AR desktop/mobile keyboard/reduced-motion acceptance before marking these findings CLOSED.

### Earlier checkpoints below

## Registered route inventory checkpoint — 2026-09-26

- Active branch: `sec03-framework-upgrade`. GF-01 Page Closure/mini-sidebar passed [CI 36249023102](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36249023102); GF-11 shared confirmation passed [CI 36250710952](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36250710952); GF-12 collapsed-group labels/sidebar-only scroll passed [CI 36250969555](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36250969555). Their source CI is green, but authenticated QAS acceptance has not occurred.
- The route-export source commit `11cd7770ef20a91a693936e1ae249c9354dfccda` passed [CI 36251293003](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36251293003). Its Laravel JSON artifact was normalized to [337 registered routes and 160 GET-capable discovery entries](docs/PAGE_INVENTORY_2026-09-26.md); all 142 literal GET seed URIs matched. The generated CSV and reproduction scripts are included with this checkpoint; routes are not equivalent to accepted pages.
- GF-13 public health-probe/trace fix passed [CI 36251884894](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36251884894) on `39c6a87b7e475f70d9d81b4d569e3208c443a999`; QAS acceptance remains separate.
- GF-14 Storefront shell source work fixes dead shared-navigation fragments on non-Home pages or when configurable Home sections are disabled. Three focused PHP tests cover Home, other pages and disabled sections. Source `7ceff28f05cd7e79c5007a74ee2e2195a2f959a3` passed [Hardening CI 36252296274](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36252296274): 463 PHP tests / 13,936 assertions, 4 JS interaction tests, clean MySQL, Composer audit, Blade compile and frontend build. Matching-revision QAS checks remain open; see [Global Foundation Audit](docs/GLOBAL_FOUNDATION_AUDIT_2026-09-26.md).
- Last documented QAS application remains `f1f20297a27e2236603788ac7cc343dbbf86d0b6`; GF-01/GF-11/GF-12 remain IN REVIEW. Production unchanged. OPS-01 historical secret rotation, PAY-01 Paymob E2E and OPS-02 restore rehearsal are open release gates.
- **Next:** continue actual Admin/Storefront navigation, conditional UI and role-variant review against the registered routes and shared-shell foundation. Review user-configured Home/hero/banner anchors against disabled sections. When the matching source is on QAS, carry out authenticated EN/AR/mobile/keyboard acceptance before any CLOSED claim. Do not infer this from the route manifest.

### Earlier checkpoints below

Read the newest section first; older CI-pending statements describe their earlier checkpoints.

## Admin navigation continuation — 2026-09-26

- GF-12 source work names all seven collapsed sidebar groups for assistive technology/tooltips and confines current-item auto-scroll to the sidebar instead of the document. See [Global Foundation Audit](docs/GLOBAL_FOUNDATION_AUDIT_2026-09-26.md). CI and QAS acceptance for this newest slice are pending.
- The previous GF-11 source commit `6e6d75eb54dda8efa86420d3a26f34aa13847f41` passed [Hardening CI 36250710952](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36250710952). Use the latest branch-head CI as the GF-12 source gate.
- QAS application evidence remains `f1f20297`; GF-01/GF-11/GF-12 are not yet CLOSED there. Production unchanged. Next: finish the shared Admin shell source slice, confirm latest-head CI, then matching-revision authenticated QAS acceptance and route/page inventory reconciliation.

### Earlier checkpoints below

Read the latest section first; prior "next" statements are dated.

## Latest shared-foundation source slice — 2026-09-26

- The pre-slice application/Page Closure commit `3ba6d5a` passed [Hardening CI 36249023102](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36249023102). This proves source CI, not authenticated QAS acceptance of GF-01.
- GF-11 Admin confirmation/submit feedback is implemented in the current source workstream and covered by four Node interaction tests. See [Global Foundation Audit](docs/GLOBAL_FOUNDATION_AUDIT_2026-09-26.md). Record the final commit/CI outcome before describing this new slice as source-verified.
- Latest QAS application evidence remains `f1f20297` in the existing ledger; neither GF-01 nor GF-11 is CLOSED on QAS. Production remains unchanged.
- Next: obtain latest-head CI for GF-11, then seek matching-revision authenticated QAS acceptance for GF-01/GF-11 and continue the Admin shell foundation/page inventory. OPS-01, PAY-01 and OPS-02 remain separate release blockers.

### Earlier decisions below

Their "next" lines describe their prior checkpoints.

## Latest execution decision — 2026-09-26 (buyer-grade closure)

- The current source branch for the modernization pass is `sec03-framework-upgrade`; plan-review starting HEAD `3ba6d5ab0a6196d32805ce9e7f64585c2d6682a3`. CI/QAS acceptance for its Page Closure + mini-sidebar changes has not been established in this plan update.
- The latest QAS application revision already verified in the project ledger remains `f1f20297a27e2236603788ac7cc343dbbf86d0b6`. Production is unchanged. Do not infer QAS acceptance of newer source commits.
- The authoritative execution method is [buyer-grade plan](docs/BUYER_GRADE_EXECUTION_PLAN_2026-09-26_AR.md) + [Page Closure System](docs/PAGE_CLOSURE_SYSTEM_2026-09-26.md). Inventory actual page/role variants, close shared foundation on representative surfaces, then close every page and linked journey with exact-revision evidence.
- Initial static source discovery is saved in [Page Inventory](docs/PAGE_INVENTORY_2026-09-26.md) and its 142-GET-declaration CSV seed. This is not the complete live route/page inventory; reconcile generated Auth routes, mutations, Livewire and conditional UI on a PHP/QAS environment.
- Buyer/developer/AI inspection is an explicit quality gate: repeatable installation, permissions/security, critical money/stock flows, EN/AR browser evidence, performance, operations and honest feature boundaries. There is no evidence for a guaranteed zero-defect score.
- Work in coherent bounded batches with frequent repository checkpoints and short updates. For a new chat, use [handoff](docs/NEW_CHAT_HANDOFF_2026-09-26.md), updated with source/QAS/Production separation and the first next action.
- OPS-01 credential rotation evidence, PAY-01 Paymob E2E and OPS-02 database restore rehearsal remain Production blockers. OPS-03 is accepted on QAS only; Production setup/verification remains.
- **Next source/acceptance slice:** check CI for the mini-sidebar/page-closure HEAD, accept GF-01 on the matching QAS application revision, and create the actual route/navigation/role/page inventory; then continue the Admin shell foundation. Release blockers can be progressed independently in parallel.

### Earlier checkpoints below

Their "next action" lines are historical unless repeated in the latest section above.

## Product modernization execution mode — 2026-09-26

- Permanent workflow: **Global Foundation -> Page-by-Page Closure -> consolidated QAS -> defect closure -> release review**.
- Authoritative method: [Page Closure System](docs/PAGE_CLOSURE_SYSTEM_2026-09-26.md).
- Global shared-layer audit: [Global Foundation Audit](docs/GLOBAL_FOUNDATION_AUDIT_2026-09-26.md).
- Current source baseline for this pass: `sec03-framework-upgrade` (Laravel 13 / Livewire 4 rehearsal line); Production remains unchanged.
- First global-shell source fix in this pass: collapsed Admin sidebar utility actions are icon-only in mini mode, with accessible labels/tooltips retained. CI/QAS acceptance is still required before this item is CLOSED.
- Do not call a page CLOSED because styling alone is finished; all relevant product, interaction, bilingual, mobile, accessibility, security, performance, help, code-quality and test gates must be reviewed.

## Authoritative continuation checkpoint — 2026-09-26

- New-chat continuation reference: [docs/NEW_CHAT_HANDOFF_2026-09-26.md](docs/NEW_CHAT_HANDOFF_2026-09-26.md).
- Active hardening branch: `sec03-framework-upgrade`.
- Latest QAS application/operations code: `f1f20297`; latest branch HEAD after documentation updates is newer and documentation-only.
- Laravel 13 / Livewire 4 / Sanctum 4 migration is source/CI/QAS verified.
- QAS uses database queue mode and automatic hPanel Scheduler + bounded Queue worker execution is verified across multiple minute cycles.
- OPS-03 is accepted on QAS; Production still needs the same runtime configuration before promotion.
- Remaining P0 release gates: OPS-01 credential rotation evidence, PAY-01 Paymob E2E, OPS-02 database restore rehearsal.
- Authenticated consolidated QAS acceptance remains required before Production.
- Production remains unchanged.
- Continue completion before expansion.
- OPS-01 historical exposure scope is now documented in `docs/OPS01_CREDENTIAL_ROTATION_EVIDENCE_2026-09-26.md`: confirmed historical secrets are the Laravel APP_KEY and Paymob API/HMAC credentials; DB/Mail/AWS/Redis/Pusher secrets were not populated in the tracked historical `.env` snapshot.
- **Exact next action:** complete OPS-01 runtime verification/rotation (verify QAS/Production are not using the historical APP_KEY; rotate/revoke historical Paymob API/HMAC credentials; record dates only), then PAY-01 Paymob E2E, then OPS-02 database restore rehearsal.

### Historical checkpoints below

Older source/QAS/CI/“next” statements below describe their own dates. They do not override the checkpoint above. Use the new audit to distinguish implemented features from pending acceptance and genuinely unimplemented scope.


## 2026-09-25 — Consolidated QAS Acceptance

### Current source state
- Working branch: `v42-clean-baseline`.
- The major commerce, POS, workforce, customer-account and Admin V2 foundations are implemented in source.
- Admin V2 consistency is considered source-complete for the known high-impact workspaces.
- Broad live/no-reload migration is closed for safe read-side flows; mutations remain server-authoritative.
- Production remains unchanged.

### What is completed
- Commerce hardening and safe deploy/rollback foundations.
- Shipping Engine V1.
- Online-payment stock reservation V1.
- Returns / RMA V1.
- POS / Cashier Foundation, receipt printing, customer attach, hold/resume and manager shift review.
- Barcode/SKU and purchase/inventory receiving foundations.
- Workforce Foundation, scheduling, attendance/corrections, leave and Payroll Foundation V1.
- Customer profile/password/address-book foundation.
- Roles & Permissions V2 + explicit Admin role hardening.
- Analytics, Branding, Categories, Catalog, Commerce Settings and Admin V2 consistency sweeps.
- Shared Admin Page Header / Stat Card / Section Tabs / switch / RTL / confirmation contracts.


### Development continuation decision — 2026-09-25
- Consolidated QAS acceptance remains required before Production, but manual acceptance is intentionally deferred while ordered source development continues.
- Current source slice: Growth Workspace V2, following the recorded UI/UX concern that the Growth area was crowded, inconsistent and still mixed EN/AR.
- Growth source checkpoint: `f32fba8a`; CI and authenticated QAS visual acceptance remain separate gates.
- New ideas continue to be recorded and placed into the roadmap by dependency/priority rather than interrupting the active slice unless they are blockers, regressions or security issues.

### Current objective
Run the consolidated authenticated QAS acceptance pass on the operator-confirmed QAS deployment of `v42-clean-baseline`.

### QAS deployment checkpoint
- QAS deployment is operator-confirmed complete on 2026-09-25.
- Deployed source commit: `899e6410331543fa3c2a807c788ccf4d066a25f5` (`899e6410`).
- QAS URL: `https://v42.tag-marketplace.com`.
- First deployment attempt stopped safely during route verification because duplicate invalid frontend return routes referenced a non-imported `FrontendReturnController`; source was fixed before retry.
- Second attempt stopped safely during the Blade namespace preflight because Inventory, Payment details, and Purchases still contained corrupted namespace references; all were corrected before the successful deployment.
- Added `BladeNamespacePreflightTest` to guard against recurrence of the corrupted Blade namespace pattern.
- Production remains unchanged.

### QAS acceptance priorities
1. Admin shell/navigation, EN/AR/RTL, responsive layout.
2. Categories / Brands / Attributes / Products CRUD and list interactions.
3. Coupons / Promotions / Suppliers create-edit flows.
4. Payments + Payment Settings + Shipping Methods/Zones/Rates.
5. Returns / RMA workflows.
6. POS sale / receipt / customer / hold-resume / shift review.
7. Workforce Employees / Schedule / Attendance Corrections / Leave / Payroll pages.
8. Customer account / addresses / My Orders / Notifications.
9. Analytics / Branding / Notification Center / WhatsApp settings.

### Remaining product work after QAS
- Storefront UI/UX polish and mobile/RTL pass.
- Deeper theme/preset redesign.
- Real CSV import execution pipeline.
- Online order invoice / printable invoice.
- Verified reviews/moderation.
- Delivery operations V3.
- Workforce V2 policy-dependent work.
- Tax/VAT only after explicit jurisdiction/business policy.
- Later Laravel/platform modernization and broader E2E/static-analysis coverage.

### Release rule
- QAS is now refreshed to `899e6410` for consolidated acceptance.
- Production stays unchanged until consolidated QAS acceptance and remaining operational release gates are explicitly cleared.

### Reference
See `docs/PROJECT_CHECKPOINT_2026-09-25.md` for the detailed checkpoint and remaining roadmap.


### Latest QAS verification note
- Media-root regression is verified resolved in QAS.
- Category list/edit images now render correctly after the isolated QAS public-root fix and one-time uploads synchronization.
- Continue with the remaining consolidated QAS acceptance backlog; do not reopen the media issue unless it regresses.


## 2026-09-26 — Green Growth closure checkpoint

### Verified source / CI state
- Working branch remains `v42-clean-baseline`.
- Latest verified green source HEAD: `9526d27537f7e5268422a9422a450af87242edbb` (`9526d275`).
- GitHub Actions run `36208265474` completed successfully on that exact HEAD.
- This is the current source baseline for continuation unless a newer branch commit is verified.
- Production remains unchanged.

### QAS separation
- Operator confirmed a QAS deployment earlier in this session from the prior green baseline `5e2a393abfa9e7773846a463ccd674afe717d0e3`.
- Growth commits after `5e2a393a`, including the bounded-insight and CRUD-return-flow changes through `9526d275`, are source/CI verified but are **not yet recorded as deployed to QAS**.
- Do not assume QAS contains `9526d275` until the operator explicitly deploys and confirms it.

### Growth Workspace closure progress
- Content lists are paginated independently: campaigns, automation rules, templates, audience segments and experiments.
- Operations lists are paginated independently: deliveries, trigger logs and message logs.
- Insights experiment performance is paginated.
- Attribution, cohort, predictive and adaptive insight reads are database-bounded; predictive/adaptive top-row reads now request exactly the 5 rows displayed by the UI.
- `GrowthWorkspaceV2Test` guards the pagination/bounded-read contract.
- Growth CRUD create/update flows now return to `Content & Journeys` and the relevant module anchor instead of dumping the operator back on Overview.
- Regression coverage guards the Growth journey return flow.
- Latest Growth closure commits:
  - `19fbb152` — align Growth insight query bounds.
  - `3f46148f` — guard Growth insight result bounds.
  - `84011ac4` — keep Growth CRUD in journey workspace.
  - `9526d275` — guard Growth journey return flow.
- Remaining acceptance is targeted authenticated QAS EN/AR/RTL/responsive/functional review, not another broad source redesign unless that review finds a real regression.

### Newly recorded roadmap item — Social sign-in
- Customer social login is **not implemented yet**.
- Current authentication remains standard Laravel `Auth::routes()`; the repository currently has no Laravel Socialite dependency and no Google/Facebook/Apple OAuth routes/controllers.
- Future Customer Account / Authentication slice: configurable social sign-in, with Google as the natural first provider, Apple important for the planned iOS/mobile product, and Facebook optional based on product need.
- Provider enable/disable and credentials must be Admin-configurable and handled securely.
- This is recorded for later and must not interrupt the current close-existing-work-first sequence.

### Working rule reaffirmed
- Close existing in-progress modules to production-grade quality before starting unrelated new features, unless a new finding is a blocker, regression, security issue or prevents avoidable rework.
- New useful ideas should be recorded in the roadmap and picked up in dependency/priority order.
