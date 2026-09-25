# MASTER PROJECT STATUS
## Dynamic E-commerce System (Tag Marketplace)

> Living status: start with [the documentation guide](docs/README.md) for the current implementation/CI/QAS/Production ledger and the dated audit links. Historical checkpoints below are retained as evidence; their “next step” statements describe the date they were written.

## Current state

- **Expanded product direction (2026-09-24):** UI/UX/design-system modernization, POS/cashier, barcode scanning, invoice/receipt, employee attendance/shifts/leave/payroll, and expanded delivery operations are now tracked in `docs/PRODUCT_ROADMAP_2026-09-24.md`. These are phased roadmap items; they are not yet deployed features.
- **Official application baseline:** V42
- **Baseline description:** Cost Calculator refactor + Arabic/English translation updates
- **Current working branch:** `v42-clean-baseline`
- **Current phase:** Incremental commercial-readiness fixes on the V42 working line, with CI → QAS → review → Production promotion per exact commit
- **GitHub `main`:** still on the older baseline and has not yet been promoted to the validated V42 branch
- **Production:** V42 routine deployment flow is validated; exact application commit `95e9f50` was deployed successfully with HTTP 200
- **Production domain:** `tag-marketplace.com`
- **QAS deployment checkpoint (2026-09-24):** The operator reported uploading the clean working-line head targeted at `a1e8c57` after confirming a clean server checkout, staging environment and rehearsal database, creating a pre-deploy database snapshot, and checking the remote branch SHA. The public QAS login returned HTTP 200 with expected Arabic content afterward. Server deployed HEAD and authenticated customer/account behavior have not been independently verified; the account/address-book English/Arabic desktop/mobile review remains open. The backup is held outside the repository at `deploy_backups/qas_before_account_a1e8c57_20260924.sql` (157,110 bytes; SHA-256 `e1a0f552f1c84eaae56aa0a714bb798d8c559a1dd96802339086e43598b5de86`).
- **QAS Purchases V2 verified code revision (2026-09-24):** After the upload, the operator provided server-side `git rev-parse HEAD` output: `0a08253d61c26f7be841de93c9e5b7ad2d7efdfe`. Public `/login` responded HTTP 200. Authenticated purchase receipt, inventory and bilingual UI checks are still outstanding; this is not a QAS feature acceptance or Production promotion. Later repository commits that update documentation alone do not change the application revision on QAS.
- **Review timing decision (2026-09-24):** The owner asked to defer manual QAS tests and run them together in a later review phase while development continues. Automated CI remains part of each code batch. No unreviewed QAS feature is considered Production-ready.
- **Admin Customers live-list checkpoint (2026-09-25):** source revision `51a3418` migrates customer name/email search, role/activity/value filters, per-page changes, quick customer queues and pagination to the shared progressive live-list pattern. The same `customers.manage` authorization boundary remains server-side; live fragment requests skip full-page KPI/revenue work, and a focused feature test covers permissions, filtering and escaping. CI verification for this new head is still pending; QAS and Production are unchanged.
- **Procurement & Inventory live-list checkpoint (2026-09-25):** application revision `7df29ec` extends the reusable no-reload pattern to Suppliers, Purchases and Inventory Movement Explorer. Filters/search/pagination and safe queue/sort navigation are live; supplier deletion, purchase receiving and inventory mutations remain server-authoritative. Live fragment requests skip unrelated overview/KPI queries. Three focused feature-test files cover permissions, filtering, pagination and escaping. CI verification for this head is still pending; QAS remains at `0a08253` and Production is unchanged.
- **Finance & Promotions live-list checkpoint (2026-09-25):** application revision `b79177a` extends the shared live-list pattern to Payments, Coupons and Promotion Rules. Payment reads remain under `payments.view`; payment mutations are unchanged under `payments.manage`. Coupon Type sorting is corrected, promotion sorting is now exposed in the UI, and related destructive/pricing mutations remain backend-confirmed. Three focused feature-test files were added. CI verification remains pending; QAS remains `0a08253` and Production is unchanged.
- **Live / no-reload phase closed in source (2026-09-25):** application revision `eedd109` completes the broad read-side migration across Deliveries, Categories, Imports, storefront Catalog Search/Category browsing, My Orders pagination and Customer Notifications pagination. Product Admin remains Livewire; mutations that change money, stock, delivery, access, cancellation or destructive business state remain explicit backend requests. See `docs/LIVE_NO_RELOAD_CLOSURE_2026-09-25.md`. CI verification remains pending; QAS stays at `0a08253`, and Production is unchanged.
- **Workforce Foundation V1 (2026-09-25):** application revision `b0fc6d20` adds employee profiles, attendance sessions, scoped workforce permissions, manager Employee Directory / Attendance Review, personal Time Clock, transaction-safe attendance guards, activity auditing and POS cash-shift identity integration. Attendance and POS cash shifts remain separate concepts; checkout is not blocked by attendance. CI verification is pending; QAS remains `0a08253` and Production is unchanged. See `docs/WORKFORCE_FOUNDATION_2026-09-25.md`.
- **Workforce Shift Scheduling V1 (2026-09-25):** application revision `ff423286` adds Draft/Published/Cancelled employee work shifts, transaction-safe overlap protection, live manager schedule, employee My Schedule and Schedule-vs-Actual attendance comparison. Cancelled shifts are retained historically; published-only visibility is enforced for employees. CI pending; QAS stays `0a08253`; Production unchanged. See `docs/WORKFORCE_SHIFT_SCHEDULING_V1_2026-09-25.md`.

## Latest working-line update — 2026-09-23
- An admin daily-work UX batch is CI-verified in source, pending QAS review: the default dashboard now shows four clearly defined 30-day metrics, permission-scoped priorities, workspaces and recent records. Its controller no longer computes the unused deep-dive panels. The sidebar no longer queries order/coupon/supplier counts on every render. Topbar/search and order actions respect route permissions.
- **Branding & Appearance V2 (2026-09-24):** source implementation now adds the `professional_commerce` neutral default direction, visual preset cards, reapply-preset behavior, a focused core palette with Advanced controls, a more representative live preview, unsaved-change state/protection, semantic admin success/warning/danger colors, supported badge-style choices, and Arabic/English strings. During the pass, homepage visibility handling was repaired for Featured categories, Manual featured products and Trust blocks. Automated coverage was added in `tests/Feature/BrandingSettingsExperienceTest.php`. Detailed scope/QAS checks: `docs/BRANDING_APPEARANCE_V2_2026-09-24.md`. Final branch-head CI, authenticated QAS review and Production deployment remain separate gates.
- A shared accessible section navigator splits product editing, branding and content settings into focused sections while retaining one form and both product save controls. Order details now have section links and an even four-card status grid. The section helper selects the first error section and preserves the active tab across Livewire updates; this behavior requires authenticated desktop/mobile Arabic/English QAS review, including variants and uploads.
- Admin code revision `9fe1a96` passed [CI run 35919171926](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/35919171926): PHP syntax, clean MySQL migration, Blade compilation, 48 tests (208 assertions), frontend build. These changes have **not** been visually verified in QAS or deployed to Production. The [documentation ledger](docs/README.md) and [Admin UX QAS checklist](docs/ADMIN_UX_QAS_CHECKLIST.md) track the next gate.
- Batch 0 code now requires an explicit non-owner staff role when a customer receives admin access, assigns/removes that role transactionally, and prevents changing an owner through the customer profile. Customer-list access editing moved to the profile to simplify the table.
- The product page no longer creates synthetic ratings, reviews, sales, viewer and save counts; repeated unverified reassurance blocks were removed. The purchase/variant controls remain, and the initial stock note uses the selected variant stock.
- Growth validation demo seed/clear is now restricted to local, testing and QAS/staging at the HTTP and service layers; its operations controls are hidden in Production. Existing demo records have not been audited or removed.
- Focused promotion/demotion, permission-boundary and Production demo-guard regression tests were added. Code revision `f7ff4e8` passed [Hardening CI run 35916058338](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/35916058338): PHP syntax, clean MySQL migration, Blade compilation, 45 PHPUnit tests and frontend build. **QAS visual verification and Production deployment are still pending**; neither environment is recorded as running this batch.
- The pre-existing roleless-admin Super Admin fallback is still active. This is a **partial authorization fix**; inventory current admins, designate and explicitly assign the owner, then remove the fallback with migration/rollback tests. Other P0 release gates in the audits remain open.
- The source audit and UI review are dated baselines, and their later implementation states are tracked in the [documentation guide](docs/README.md). Update this section and the ledger for every subsequent batch.

## QAS Workforce migration recovery — 2026-09-25
- QAS deploy reached new Workforce migrations and failed on the attendance table's autogenerated composite index name exceeding MySQL's identifier-length limit.
- Source fix `f4dcf32e` assigns explicit short index names; follow-up `8272f14c` restores executable mode for `deploy-qas.sh`.
- Before rerun, inspect the failed `employee_attendance_sessions` migration state and only remove the partial table if it exists and has zero rows.
- The operator subsequently confirmed the QAS upload completed after this recovery procedure. Exact authenticated feature acceptance is still deferred; Production unchanged.

## QAS Workforce 500 diagnosis / Blade namespace repair — 2026-09-25
- QAS showed generic 500 pages on Employees, Schedule, Corrections and Leave after the interrupted Workforce deploy.
- Root causes are now separated: incomplete Attendance/Leave schema from the failed migration plus malformed PHP class references in 12 Workforce Blade files (`AppModels...` / `IlluminateSupport...`).
- The confirmed Blade namespace faults were repaired across Workforce views and a regression guard `WorkforceBladeIntegrityTest` was added.
- Fixes are not yet claimed on QAS; a clean schema recovery + successful deploy is required before feature acceptance.

## Deferred cross-product UX backlog — 2026-09-25
- Roles & Permissions is a confirmed redesign target: too long, mixed EN/AR, weak UI hierarchy and poor UX. Future pass should split the workspace, complete EN/AR + RTL parity, improve permission discoverability/search/filtering and reduce repetitive visual density.
- Global action UX must preserve context: avoid unnecessary full-page reloads and scroll-to-top jumps across Admin and Customer surfaces. POS add-product / quantity increment is a confirmed example. Migrate suitable safe inline mutations to reusable server-confirmed async interactions while preserving focus/scroll and explicit error/success feedback; sensitive mutations remain backend-authoritative.

## QAS Workforce FK-name recovery — 2026-09-25
- QAS deploy to `d8dce33c` failed at the new attendance-break migration because an autogenerated MySQL foreign-key constraint name exceeded the 64-character identifier limit.
- All new Attendance/Leave foreign keys in the affected pending migrations now use explicit short constraint names; source fix head `5d03a205`.
- The failed deploy's error handler restores QAS from maintenance mode. Before rerun, remove only an empty partial `employee_attendance_breaks` table if it exists. Production unchanged.

## QAS Workforce integration checkpoint — 2026-09-25
- Operator confirmed the latest Workforce build deployed successfully to QAS and the previously failing Workforce pages now open.
- Confirmed page-load recovery includes Employees, Work Schedule, Attendance Corrections and Leave.
- This closes the prior QAS blocker category caused by interrupted migrations, oversized MySQL FK names and corrupted Workforce Blade class references.
- Treat this as integration/page-load success only; full functional acceptance still requires consolidated EN/AR, responsive, permission and workflow validation.
- Production unchanged.

## Online-payment Stock Reservation V1 — 2026-09-25
- Added `order_stock_reservations` ledger with Reserved / Committed / Released / Expired lifecycle and one reservation per Order Item.
- New inventory movement types: `order_reservation` and `reservation_release`.
- Online checkout reserves inventory atomically; successful payment commits without another decrement; failed/expired/cancelled reservations release inventory idempotently.
- Configurable reservation TTL under Payment Settings: 5–1440 minutes, default 30.
- Paymob redirect/retry verifies inventory before opening the gateway and re-reserves released/expired stock only when available.
- Scheduled expiry command runs every five minutes and moves pending online payment to Failed after releasing expired stock.
- Late Paid callback after expiry preserves Paid financial truth; if stock cannot be re-reserved, no negative inventory is created and a `paid_without_fulfillable_reservation` exception is surfaced to Admin and customer UI for review.
- Online cancellation is reservation-aware and avoids double-restock.
- Inventory movement labels now distinguish reservations/releases from committed sales.
- Blade namespace preflight/test coverage expanded project-wide after an existing Inventory view corruption was found.
- Regression coverage: `OnlineStockReservationTest`; updated `CheckoutIdempotencyTest` and `BusinessIntegrityHardeningTest`.
- Application source: `e58e82e9`; CI pending; not yet on QAS; Production unchanged.
- Next critical-commerce slice: Returns / RMA V1.

## Shipping Engine V1 — 2026-09-25
- Added `shipping_methods`, `shipping_zones`, `shipping_zone_cities`, `shipping_rates` and explicit shipping snapshot references on Orders.
- Existing Standard / Express / Store Pickup method codes stay stable for Delivery V2 compatibility. Names, availability, ETA and sort order are configurable.
- Cities resolve to one active zone per country using normalized exact matching; duplicate assignment is rejected.
- One active rate per method/zone can define amount plus optional free-shipping threshold and explicit before/after-discount basis. Pickup is zero without a rate.
- Checkout live quote is server-authoritative, no-reload and stale-request safe. Place Order stays disabled until a valid quote is confirmed.
- CheckoutService re-quotes shipping inside the order transaction and writes immutable method/zone/rate/threshold/ETA snapshot data.
- Cart no longer advertises disconnected EGP 600 shipping progress or shipping = 0; it states that shipping is calculated at checkout.
- Shipping setup is split into three focused settings screens and uses existing `settings.manage`; configuration mutations are audited.
- MySQL FK/index names are explicit/short.
- Regression coverage: `ShippingEngineTest`; checkout idempotency constructor/scope updated.
- Application source: `97dc443d`. CI pending, not yet claimed on QAS, Production unchanged.
- Next: Online-payment stock reservation / expiry / release V1. Tax/VAT remains policy-gated.

## Workforce Payroll Foundation V1 — 2026-09-25
- Added `employee_compensations`, `payroll_periods`, `payroll_runs`, `payroll_entries` and `payroll_adjustments` with explicit short MySQL constraint/index names.
- Added scoped payroll permissions: `workforce.payroll.self`, `workforce.payroll.view`, `workforce.payroll.manage`. Finance Manager gets view/manage/self; Operations Manager/Cashier/Support get self only; Super Admin gets all.
- Compensation supports Salary-per-payroll-period or Hourly basis, base rate, currency, effective dates, overtime eligibility/reference multiplier and notes. Historical runs snapshot values and do not change after compensation edits.
- Payroll periods cannot overlap and must end before generation. Salary partial-period coverage is blocked until an explicit proration policy exists.
- Payroll generation snapshots effective attendance, breaks, approved corrections, paid/unpaid approved leave, compensation and employee identity. Pending attendance corrections/leave plus open attendance/breaks block included employees only.
- V1 deliberately does not auto-monetize paid/unpaid leave or calculate overtime, tax, social insurance or jurisdiction-specific deductions.
- Explicit pay components: Overtime, Allowance, Bonus, Deduction. Deduction rollback protects against negative Net pay.
- Lifecycle: Draft → Approved → Paid. Approval freezes adjustment changes and closes the period; Paid cannot be reached from Draft.
- Run summaries are grouped by currency; My Payslips shows only finalized own payroll entries; printable payslip view included.
- Audit coverage includes compensation, period creation, generation, component add/remove, approval and Paid transition.
- Regression coverage: `WorkforcePayrollTest` plus recursive `WorkforceBladeIntegrityTest`.
- Application source: `b51a93e6`. CI pending with no visible workflow/status. QAS Workforce remains unaccepted pending full recovery/redeploy and consolidated validation; Payroll is not on QAS. Production unchanged.
- Next: **Workforce QAS integration / consolidated validation** → critical commerce gaps → Payroll V2 only after real merchant/jurisdiction policy.

## Workforce Leave Management V1 — 2026-09-25
- Added `employee_leave_types` for configurable English/Arabic paid/unpaid leave policy and default annual entitlement.
- Added `employee_leave_requests` with Pending / Approved / Rejected / Cancelled lifecycle and reviewer audit fields.
- Added append-only `employee_leave_adjustments` for opening balance, carryover and documented signed credits/debits.
- `LeaveBalanceService` calculates Entitlement, Adjustments, Used, Pending, Available and Projected balances by employee/type/year.
- Employee requests use inclusive calendar days, cannot cross calendar years in V1 and reject overlapping Pending/Approved requests.
- Approval requires enough available balance and no unresolved work-shift conflict. Work Shift create/update rejects Approved leave overlap in the opposite direction.
- Leave approval lock order was hardened to Employee → Leave Request → related records to reduce lock inversion with scheduling.
- My Leave, live manager Leave Review, Leave Types and focused Balance Adjustment/history workspaces were added with EN/AR copy.
- Leave request/cancel/review, leave policy changes and balance adjustments are audited.
- Regression coverage: `WorkforceLeaveManagementTest`.
- Application source: `c6df3fc2`. CI pending; not yet claimed on QAS. Production unchanged.
- Next: **Payroll Foundation V1**.

## Workforce Attendance Rules & Corrections V1 — 2026-09-25
- Added `employee_attendance_breaks` and transaction-safe Start/End Break actions. Open breaks block clock-out and all break changes are audited.
- Attendance sessions now preserve Recorded time while exposing Effective time through the latest approved correction; raw clock-in/out history is never silently overwritten.
- Added `employee_attendance_corrections` with Pending / Approved / Rejected lifecycle, previous-effective snapshot, requested times, reason, manager notes, reviewer identity and timestamps.
- Correction requests enforce ownership, closed session, one-pending-request and corrected-time ordering; review is one-time and audited.
- Added `AttendanceRulesService` with centralized 5-minute start/end grace logic and late/early/early-departure/after-shift/absence/not-clocked-in/upcoming states.
- My Time Clock now supports breaks and correction requests. Attendance Review shows Recorded vs Effective, breaks and Net worked. Manager Correction Review is live/no-reload.
- Work Schedule uses the centralized rules output and displays effective attendance exceptions plus Net worked time.
- Regression coverage: `WorkforceAttendanceRulesTest`.
- Application source: `b7fe238d`. CI pending; this slice is not yet claimed on QAS. Production unchanged.
- Next: **Leave Management V1** → Payroll Foundation.

## Workforce Shift Scheduling V1 — 2026-09-25
- Added `employee_work_shifts` and `EmployeeWorkShift` with Draft / Published / Cancelled lifecycle.
- Added transaction-safe `WorkShiftService` with employee-first locking and overlap prevention. Adjacent shifts remain valid.
- Manager Work Schedule supports live search/filter/date range/pagination using the shared fragment helper.
- Staff My Schedule shows only published current/upcoming work shifts.
- Cancelled work shifts remain historical records and are no longer editable.
- Manager schedule compares planned shifts against overlapping attendance sessions and reports actual clock-in/out plus early/late/on-time start state.
- All create/update/cancel actions write to the existing admin activity audit log.
- Existing `workforce.view`, `workforce.manage`, `workforce.clock` permission boundaries are reused.
- Regression coverage: `WorkforceSchedulingTest`.
- Application source: `ff423286`. CI pending; QAS `0a08253`; Production unchanged.
- Next: **Attendance Rules & Corrections V1** → Leave → Payroll.

## Workforce Foundation V1 — 2026-09-25
- Added `employee_profiles` with one-to-one staff-user identity and employment metadata.
- Added `employee_attendance_sessions` plus transaction-safe `AttendanceService` for personal clock-in/out.
- New permissions: `workforce.view`, `workforce.manage`, `workforce.clock`; default roles receive least-privilege access.
- Employee Directory and Attendance Review use live server-rendered fragments with URL/history/no-JS fallback.
- My Time Clock supports personal attendance with optional notes and recent session history.
- Safety guards reject duplicate open sessions, clock-out without an open session, clock-in for non-active employees, and deactivation/leave/termination while a session is open.
- Attendance actions are written to `admin_activity_logs`.
- Cash Shift Review now exposes/searches employee code and department when the cashier has a workforce profile; attendance remains independent from POS drawer shifts.
- Regression coverage is concentrated in `WorkforceFoundationTest`.
- Application source: `b0fc6d20`. CI pending; QAS `0a08253`; Production unchanged.
- Next: **Shift Scheduling V1**, then leave balances/requests, then payroll/deductions.

## Live / no-reload phase closure — 2026-09-25
- Source checkpoint `eedd109` closes the broad migration of safe read-side search/filter/sort/queue/pagination flows.
- Added at closure: Admin Deliveries, Categories and Import pagination; storefront Catalog Search and Category browsing; customer My Orders and Notifications pagination.
- Category live replacement preserves Quick View through delegated events. Import KPI semantics were corrected to global counts.
- Existing live surfaces include POS lookup/shift review, Orders, Customers, Suppliers, Purchases, Inventory, Payments, Coupons and Promotion Rules; Product Admin remains Livewire.
- Explicit server mutations remain intentionally non-optimistic: money/refunds, stock/receiving, delivery updates, order cancellation, read-state writes, destructive actions, permissions/settings/deployment.
- Regression coverage and release boundaries are recorded in `docs/LIVE_NO_RELOAD_CLOSURE_2026-09-25.md`.
- CI remains pending independent verification; QAS is still `0a08253`; Production and `main` are unchanged.
- **Decision:** stop the broad reload sweep here and move development to the next roadmap priority. Apply the standard only when future screens are touched.

## Finance & Promotions live-list checkpoint — 2026-09-25
- Payments: live reference/order/provider search, status/method filters, attention/failed queues and pagination; fragment reads avoid the full finance KPI block. Payment update logic is unchanged and remains server-authoritative.
- Payment list links to Orders and Payment Settings now respect the current admin's actual permissions instead of offering navigation that can lead directly to a 403.
- Coupons: live search/type/status/usage/per-page filters, expired/limit-reached queues, sortable columns and pagination. The visible Type sort is now backed by the controller's sort allow-list.
- Promotion Rules: live search/type/status/schedule/per-page filters, schedule queues, pagination and explicit Name/Type/Discount/Priority sorting.
- Coupon and promotion create/edit/delete behavior is unchanged; destructive actions continue through confirmed backend requests.
- Regression coverage: `AdminPaymentLiveListTest`, `AdminCouponLiveListTest`, `AdminPromotionLiveListTest`.
- Application source revision: `b79177a`. CI is pending verification; no QAS, `main`, or Production promotion is claimed.

## Procurement & Inventory live-list checkpoint — 2026-09-25
- Suppliers: debounced search, status/usage/per-page filters, inactive/unused queues, sorting and pagination now update the server-rendered result fragment while preserving URL/history and GET fallback.
- Purchases: reference/supplier search, status, supplier, per-page, awaiting-receipt queue and pagination are live. The receive-stock action remains a confirmed POST using the existing receiving service and replay protections.
- Inventory: movement search/type/source/per-page/pagination are live; Low Stock and Near Expiry workspaces stay stable and are not recomputed on fragment requests.
- Inventory cards now avoid stale filter-dependent values: the global movement count is explicit, Movement types is stable, and matching/current-page counts live inside the dynamic results area.
- EN/AR copy was added for the new inventory result states. `AdminSupplierLiveListTest`, `AdminPurchaseLiveListTest`, and `AdminInventoryLiveListTest` add focused coverage.
- Application source revision: `7df29ec`. CI is pending verification; no QAS, `main`, or Production promotion is claimed.

## Admin Customers live-list checkpoint — 2026-09-25
- Customer search is debounced and updates the server-rendered result set without a full-page reload.
- Role, order-activity, customer-value and per-page filters update immediately; Buyers / No orders / Repeat buyers shortcuts and pagination use the same live navigation path.
- URL/history remain shareable and Back/Forward-compatible, with the original GET flow retained as the no-JavaScript/error fallback.
- Fragment requests do not recalculate the global customer KPI cards. Full-page linked-customer revenue is computed directly from linked orders instead of hydrating every user aggregate.
- Authorization remains unchanged under `customers.manage`; customer names/emails stay Blade-escaped. Focused regression coverage is included in `AdminCustomerLiveListTest`.
- Current application source revision: `51a3418`. Branch-head CI is pending verification; no QAS or Production promotion is claimed.

## Deliveries V2 hardening checkpoint — 2026-09-24
- Delivery mutations now use a row-locked transactional service with an explicit transition matrix for shipping and store-pickup flows.
- `shipped_at` and `delivered_at` now follow lifecycle rules instead of being rewritten on ordinary metadata saves; out-for-delivery requires prior shipment evidence.
- Customer database notifications and WhatsApp delivery updates are emitted only when the delivery status actually changes, so metadata-only saves and concurrent replays do not duplicate status messaging.
- The order delivery editor disables invalid next states, shows shipment/delivery timestamps, and requires in-app confirmation before a real status change. English/Arabic copy and focused regression coverage were added.
- Detailed scope and QAS checks: [`docs/DELIVERIES_V2_2026-09-24.md`](docs/DELIVERIES_V2_2026-09-24.md).
- Source implementation is complete for this slice; branch-head CI, authenticated Arabic/English QAS review, and Production promotion remain separate gates. Production is unchanged.

## Catalog Admin V2 checkpoint — 2026-09-24
- Catalog administration has expanded beyond Products into Categories, Brands, Attributes, and Attribute Values with operational queues, stronger filters, usage/health metrics, safer destructive actions, and editor workflow improvements.
- Category editing now enforces unique slugs, image type/size limits, translation-field validation, and protects categories linked to products from deletion.
- **Category bilingual hardening (2026-09-24):** canonical/base category fields now read raw stored values instead of locale-translated accessors, preventing an Arabic admin edit from overwriting the canonical record. Translation panes now use correct RTL/LTR direction with field-level errors, and clearing an optional Arabic translation removes the stale translation row. Regression coverage was added; branch-head CI/QAS remain pending. See `docs/CATEGORY_BILINGUAL_HARDENING_2026-09-24.md`.
- Brand management now surfaces empty/linked records and uses guarded deletion with a backend recheck for product dependencies.
- Attribute and Attribute Value management is variant-aware: attributes or values referenced by product variants are protected from deletion, while usage counts and cleanup queues are visible to admins.
- Arabic/English catalog-admin localization is now part of the ongoing definition of done. New strings introduced during the V2 work are being registered in English and translated into Arabic as the related screens are upgraded.
- Automated coverage has started for the upgraded catalog workflows. Final release readiness still requires green branch-head CI plus authenticated QAS review in English and Arabic before Production promotion.

## Analytics & Insights V2 checkpoint — 2026-09-24
- The Analytics overview now uses a shorter decision-first hierarchy: four primary KPIs → decision read → trends → funnel/period comparison → commercial drilldowns.
- Repeated operator/depth/pattern/storytelling layers were removed while preserving the underlying calculations, reporting windows, funnels, comparisons and drilldowns.
- The same consistency pass now covers Growth and Offers: duplicated growth signal/executive layers and offers hero/operator-summary layers were removed, while campaign/rule/product signals and coupon/promotion detail remain available.
- Growth Insights now reuses the shared admin metric-card language, and undefined legacy Growth semantic color variables were replaced with the defined admin theme tokens.
- Daily detail remains available under an expandable More diagnostics section instead of filling the default page.
- Shared Analytics navigation and report surfaces now use configurable admin theme tokens instead of hard-coded orange styling; the duplicate on-screen export summary table is hidden while CSV/print behavior remains available.
- English/Arabic hierarchy copy and a focused `AdminAnalyticsExperienceTest` were added. Detailed scope/QAS checks: `docs/ANALYTICS_INSIGHTS_V2_2026-09-24.md`.
- Source implementation and automated regression verification are complete for this iteration. Hardening CI run `36004327589` passed at `8b477d8`; authenticated English/Arabic desktop/mobile QAS review remains required. Production is unchanged.

## WhatsApp workspace UX checkpoint — 2026-09-24
- The long WhatsApp administration screen now uses focused Overview / Channel & queue / Provider & templates / Test tools / Logs navigation instead of reading as one oversized white panel.
- Five channel/queue switches now use aligned toggle cards and shared admin switch sizing; delivery/message summary metrics use the shared admin stat-card language.
- Provider/template/test/resend/log workflows and server-managed credential handling remain functionally unchanged.
- English/Arabic workspace copy and focused regression coverage were added. See `docs/WHATSAPP_WORKSPACE_UX_2026-09-24.md`.
- Source implementation and automated regression verification are complete for this UI iteration. Hardening CI run `36005416310` passed at `2a237e1`; authenticated English/Arabic desktop/mobile QAS review remains required. Production is unchanged.

## Cost Calculator UX checkpoint — 2026-09-24
- The existing V42 Cost Calculator received a focused UI polish without changing recipe/cost/profit/margin logic.
- Legacy orange workflow styling now follows configurable admin theme tokens; live cost summary cards reuse the shared admin stat-card language.
- Raw Materials / Product Recipe / Profit Calculation shortcuts were added, and raw-material deletion now uses the shared in-app confirmation flow instead of browser `confirm()`.
- English/Arabic copy and focused UI regression coverage were added. See `docs/COST_CALCULATOR_UX_2026-09-24.md`.
- Source implementation and automated regression verification are complete for this polish iteration. Hardening CI run `36005416310` passed at `2a237e1`; authenticated English/Arabic desktop/mobile QAS review remains required. Production is unchanged.

## Storefront Foundation UX checkpoint — 2026-09-24
- Customer-facing defaults are now marketplace-neutral instead of electronics-specific, while exact customized content remains untouched.
- Duplicate homepage shortcut navigation was removed; the hero keeps one concise shortcut row.
- Header search now uses a real `/search` product-results flow with availability/offer/sort filters and pagination instead of submitting an ignored home-page query.
- Known fake contact defaults are removed from code/seeding and safely scrubbed only on exact legacy-value matches.
- Product/category/cart/checkout missing-image fallbacks now use a local storefront SVG instead of `via.placeholder.com`.
- Checkout now advertises only enabled payment methods and uses an aligned billing-address toggle; Product Detail preserves uncropped merchandise imagery and reports the true gallery count.
- English/Arabic copy and focused `StorefrontExperienceTest` coverage were added. See `docs/STOREFRONT_FOUNDATION_UX_2026-09-24.md`.
- Source implementation is complete for this foundation iteration; branch-head CI and authenticated English/Arabic desktop/mobile QAS review remain required. Production is unchanged.

## Storefront safety & content checkpoint — 2026-09-24
- The storefront foundation now also hardens customer-facing support/payment/legal flows: raw Paymob errors are hidden, localized contact-hours fallback is respected, Category Quick View preserves product image ratio, Notifications hides empty bulk actions, and Cart removal uses the shared in-app confirmation.
- Default Privacy / Terms / Refund / Shipping bodies no longer publish assumed legal commitments; blank policies show an explicit unpublished state until reviewed content is configured.
- Store Content & Policies now carries a legal publishing caution and all seven contact/cancellation toggles use the shared aligned switch treatment.
- Regression coverage was expanded across these flows. Shared product cards are now theme-aware, Category result count duplication is fixed, and Contact hides empty business detail panels while linking configured WhatsApp numbers directly. Detailed scope/QAS checks: `docs/STOREFRONT_FOUNDATION_UX_2026-09-24.md`.
- Hardening CI run `36011846373` passed at `473a119e`. Production remains unchanged; authenticated English/Arabic desktop/mobile QAS is still required before promotion.

## Storefront customer UX checkpoint — 2026-09-24
- Customer-facing Product Detail copy was cleaned up: internal stock/settings wording and the technical gallery count were removed, and empty descriptions no longer show placeholder `coming soon` copy.
- Checkout keeps its already-correct aligned billing switch, while customer reassurance/offer labels were simplified to clearer production wording without changing payment/order logic.
- The Home hero now follows configurable storefront brand tokens instead of hard-coded dark-blue/orange campaign colors, and previous/next/CTA arrow direction is correct for both LTR and RTL.
- Customer Order Details and Order Success now hide raw gateway `checkout_error`/provider-status details and use safe retry/support guidance instead.
- Cart recommendation labels were cleaned up so internal-style wording (`Personalized offers`, `Smart offers`, `Return path`) is no longer exposed to customers; recommendation logic is unchanged.
- English/Arabic copy and focused storefront regression coverage were updated. See `docs/STOREFRONT_CUSTOMER_UX_2026-09-24.md`.
- Source implementation and automated regression verification are complete for this iteration. Hardening CI run `36014798313` passed at `d13e6a3`; authenticated English/Arabic desktop/mobile QAS review remains required. Production is unchanged.

## Customer account and address book checkpoint — 2026-09-24
- A customer account overview, editable name/email with current-password protection for email changes, and a separate password-change form are implemented in source.
- Customer-owned saved addresses now have shipping/billing defaults with serialized mutations and fallback on deletion. Checkout can prefill a selected address and the billing default; each placed order retains an independent address snapshot. The billing-same-as-shipping toggle now submits its off state explicitly.
- English/Arabic account and address-book copy, compact navigation, in-app deletion confirmation, and focused ownership/profile/default/checkout regression tests were added. See `docs/CUSTOMER_ACCOUNT_ADDRESS_BOOK_2026-09-24.md`.
- Hardening CI run `36019574401` passed at application commit `05c2c51`: PHP syntax, clean MySQL migration, Blade compile, 115 tests (579 assertions), and frontend build. Authenticated English/Arabic desktop/mobile QAS remains pending. `main` and Production are unchanged.

## POS / Cashier Foundation V1 checkpoint — 2026-09-24
- Back office now has a dedicated `pos.manage` permission and system Cashier role, plus a persistent database-backed POS cart per cashier.
- POS is scan-first: exact product/variant barcodes feed the cashier cart one unit at a time, with active-item checks, stock caps, stale quantity protection, remove/clear actions and no stock mutation before checkout.
- Cash and Card Terminal are dedicated POS payment methods and are not exposed through storefront payment options.
- Final checkout locks and rechecks the cashier cart, cart items, products and variants, recalculates current price/cost, creates a canonical completed POS Order and paid Payment, deducts inventory, captures profit, closes the cart and writes an admin audit entry in one transaction.
- POS orders use `sales_channel=pos`; fake customer email/address data is not generated. Completed-cart replay cannot duplicate the sale/payment/inventory deduction.
- Sale Summary access is cashier-owned unless broader order-view permission exists. Formal receipt/invoice printing is intentionally deferred to the next slice.
- [Hardening CI 36041425089](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36041425089) passed at application head `ef9797d` with **173 tests (969 assertions)** plus frontend production build.
- QAS remains on `0a08253`; Production and `main` are unchanged. See `docs/POS_CASHIER_FOUNDATION_V1_2026-09-24.md`.

## POS Receipt Printing V1 checkpoint — 2026-09-24
- Completed POS sales now expose a dedicated read-only Sales Receipt from the cashier Sale Summary.
- Receipt access reuses the existing cashier ownership / broader order-review boundary; printing cannot bypass sale authorization.
- Browser-print layouts support 58 mm, 80 mm and A4, with unsupported paper input falling back safely to 80 mm.
- V1 reuses the recorded POS order number as the receipt reference. Store name/address/support contact data come from existing Store Settings, and receipt values come from persisted Order / Order Items / Payment records.
- Receipt rendering is presentation-only: repeated viewing/printing does not create or update orders, payments, inventory movements or stock.
- English/Arabic receipt copy and focused safety regression coverage were added.
- [Hardening CI 36042936219](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36042936219) passed at application head `bca25c1` with **174 tests (983 assertions)** plus frontend production build.
- This V1 is intentionally a sales receipt, not a fiscal/tax invoice; no jurisdiction-specific tax identity, legal invoice numbering or fiscal claims were invented.
- QAS remains on `0a08253`; physical-printer and bilingual visual review stay queued for the consolidated phase. Production and `main` are unchanged. See `docs/POS_RECEIPT_PRINTING_V1_2026-09-24.md`.

## POS Customer Attach V1 checkpoint — 2026-09-24
- POS now supports a limited customer-account lookup by name/email, capped at 8 customer-only results and scoped under `pos.manage`; staff/admin accounts are not exposed by the lookup.
- Cashiers can attach/detach a customer account on their own active cart. Attachment persists through Hold / Resume, and full Clear removes it.
- Final checkout locks and rechecks the attached account, then links the canonical POS Order through `user_id` while snapshotting customer name/email. Walk-in sales remain unlinked.
- Customer eligibility changes are fail-safe: if an attached account becomes staff before checkout, Order/Payment/Inventory writes are rejected.
- Linked POS purchases now render correctly in customer My Orders as in-store/store-pickup purchases instead of showing blank shipping/courier content; POS Cash/Card Terminal account-area payment instructions were corrected.
- Sale Summary and printable receipt surface attached customer identity. Attach/detach are audit logged.
- [Hardening CI 36046473889](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36046473889) passed at application head `9129998` with **180 tests (1094 assertions)** plus frontend production build.
- QAS remains on `0a08253`; consolidated authenticated review is deferred. Production and `main` are unchanged. See `docs/POS_CUSTOMER_ATTACH_V1_2026-09-24.md`.

## POS Hold / Resume V1 checkpoint — 2026-09-24
- Cashiers can now pause a non-empty POS cart into a persistent held queue and immediately continue with a fresh active cart.
- Hold preserves cart items, quantities, optional customer name, notes, a hold label and held timestamp without creating an Order/Payment or mutating inventory.
- Resume is owner-only and transaction-protected. A cashier-row lock serializes competing resumes; a non-empty current sale cannot be overwritten, while an empty active cart is safely abandoned before the held cart becomes active.
- Held sales can be discarded into the existing abandoned state without inventory impact; hold/resume/discard actions are audit logged.
- The held queue is integrated into the POS workspace with EN/AR copy. Stored held totals are informational only; checkout still rechecks current product/variant validity, prices and stock.
- [Hardening CI 36044608065](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36044608065) passed at application head `8bf77cc` with **177 tests (1047 assertions)** plus frontend production build.
- QAS remains on `0a08253`; consolidated authenticated review is still deferred. Production and `main` are unchanged. See `docs/POS_HOLD_RESUME_V1_2026-09-24.md`.

## Purchase Barcode Receiving V1 checkpoint — 2026-09-24
- Ordered purchases now support persistent per-line barcode verification. Each accepted scan counts one physical unit without changing inventory.
- Exact simple/variant identity uses the shared barcode resolver; parent variant-product barcodes, unknown/out-of-purchase items, and ambiguous identifiers are rejected.
- Duplicate purchase lines require explicit line choice instead of automatic allocation across potentially different cost/expiry rows.
- Undo-one correction and over-scan caps are enforced before receipt.
- Barcode-verified final receipt requires every locked line's verified quantity to equal its ordered quantity before the existing all-or-nothing, replay-safe purchase receipt writes stock.
- Existing manual receive remains available as a protected operational fallback.
- [Hardening CI 36038635115](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36038635115) passed at application head `0a2ceb9` with 163 tests (889 assertions) and frontend production build.
- QAS remains on `0a08253`; Production and `main` are unchanged. See `docs/PURCHASE_BARCODE_RECEIVING_V1_2026-09-24.md`.

## Purchases V2 receipt hardening checkpoint — 2026-09-24
- The next working-line batch serializes receipt under a purchase row lock and transaction, permits only ordered nonempty purchases, validates every product/variant line before stock changes, and treats repeated receipt as an informational replay.
- Admin purchase creation validates product–variant ownership under lock; awaiting count and receive buttons only reflect ordered purchases. The form now requires variants where appropriate and escapes dynamic content. EN/AR confirmations, errors and regression coverage are included. See [`docs/PURCHASES_V2_HARDENING_2026-09-24.md`](docs/PURCHASES_V2_HARDENING_2026-09-24.md).
- [Hardening CI run 36023870549](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36023870549) passed at code commit `e723b0c` (122 tests, 628 assertions), including clean MySQL migration, Blade compilation and frontend build. Final application head `0a08253` passed CI run `36024229399` and matches the user-provided QAS server HEAD. Authenticated QAS review remains open. Production and `main` are unchanged.

## Inventory counted-stock adjustment checkpoint — 2026-09-24
- A dedicated admin workflow now takes an exact product/variant counted quantity and reason. The service locks the target, rejects mismatched variants and stale form counts, and records one signed inventory movement plus admin activity for a real change. A no-op creates no movement. See [`docs/INVENTORY_ADJUSTMENT_2026-09-24.md`](docs/INVENTORY_ADJUSTMENT_2026-09-24.md).
- English/Arabic copy and focused regression tests are included. [Hardening CI run 36028959639](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36028959639) passed at code commit `50cb707` (128 tests, 675 assertions). This new source batch is not on QAS `0a08253`. Manual QAS review is intentionally queued for the later consolidated pass. Production and `main` are unchanged.

## Catalog stock audit checkpoint — 2026-09-24
- The product editor now saves catalog data, variant rows and stock audit entries in one transaction after checking loaded product/variant quantities under lock. Opening stock and real editor changes write signed movements and admin activity; no-op saves do not. Stale editors are stopped, stocked variants cannot be silently deleted, and stocked simple products cannot be converted to variants until counted to zero.
- Catalog quick quantity edits use the counted-stock service with stale-count protection and a distinct source. Product duplication starts at zero independent stock. Inventory history labels the three admin sources. English/Arabic UI and focused regression coverage are included; see [`docs/CATALOG_STOCK_AUDIT_2026-09-24.md`](docs/CATALOG_STOCK_AUDIT_2026-09-24.md).
- The catalog stock batch is CI-verified: [Hardening CI 36031782934](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36031782934) passed at `6889248` with 135 tests (736 assertions) and the frontend production build. The operator-confirmed QAS application HEAD remains `0a08253`; manual review is deferred to the consolidated phase. Production and `main` are unchanged.

## Barcode Label Printing V1 checkpoint — 2026-09-24
- Inventory scanner results can now open a real-size barcode-label preview for exact simple products and variants. Variant products require an explicit variant before a stock-level label is printable.
- A dependency-free Code 128B service generates checksum-correct inline SVG barcodes from existing printable-ASCII identifiers; missing/unsupported identifiers remain non-printable instead of being rewritten.
- Label controls support 1–100 copies, 50 × 30 / 60 × 40 / 70 × 40 mm sizes, optional price display and browser print CSS that hides admin chrome.
- Unit/feature coverage validates encoding, checksum, SVG geometry, variant ownership, copy count and safe error states. See `docs/BARCODE_LABEL_PRINTING_V1_2026-09-24.md`.
- No catalog or stock mutation is introduced by label printing. [Hardening CI 36037456794](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36037456794) passed at application head `83d5ee6` with 156 tests (826 assertions) and the frontend production build. QAS remains on `0a08253`; Production and `main` are unchanged.

## Barcode Scan-to-Find V1 checkpoint — 2026-09-24
- Inventory now has a dedicated scanner workspace that accepts HID keyboard-mode barcode input and uses the shared exact barcode resolver rather than fuzzy search.
- Exact simple-product and variant matches link into the existing counted-stock adjustment workflow; scanner lookup itself does not mutate inventory.
- Parent product barcodes on variant products require explicit variant selection, while unknown and legacy ambiguous barcodes cannot reach stock actions.
- Inventory/Adjust Stock navigation, English/Arabic copy and focused regression coverage are included. See `docs/BARCODE_SCAN_TO_FIND_V1_2026-09-24.md`.
- Camera scanning, barcode label printing and POS cart behavior remain future slices. [Hardening CI 36036425394](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36036425394) passed at application head `fea30ea` with 147 tests (794 assertions) and the frontend production build. QAS remains on `0a08253`; Production and `main` are unchanged.

## Barcode / SKU Foundation V1 checkpoint — 2026-09-24
- The active product editor now applies SKU uniqueness across products and variants, matching the existing cross-catalog barcode safety rule. Duplicate identifiers inside the same pending product/variant payload are rejected before save.
- The shared identifier service now resolves exact SKU values as well as barcodes and refuses ambiguous legacy matches instead of guessing. Catalog search can find a parent product by variant SKU or barcode.
- Product duplication clears SKU and barcode while preserving the existing zero-stock copy rule so a copied record cannot inherit a sellable retail identity.
- English/Arabic guidance and focused regression coverage are included. See `docs/BARCODE_SKU_FOUNDATION_V1_2026-09-24.md`.
- No risky database-wide product identifier uniqueness migration was added; legacy collisions remain detectable and must be audited before stronger schema constraints. [Hardening CI 36035395851](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36035395851) passed at `05eb13c` with 141 tests (762 assertions) and the frontend production build. QAS remains on `0a08253`; Production and `main` are unchanged.

## Product Admin commercial UX checkpoint — 2026-09-24
- Product catalog management now has combined search/status/category/brand/content-readiness/inventory/featured filters, catalog-health counters, actionable low-stock and out-of-stock views, inline simple-product pricing/quantity controls, and clearer stock/content/featured state badges.
- Bulk operations now cover storefront visibility and featured merchandising. Activation intentionally keeps the current non-blocking content-readiness policy; incomplete content is advisory until barcode/variant/retail publication rules are finalized.
- Single-product and bulk destructive deletion now use explicit guarded confirmation state and in-app confirmation modals instead of immediate deletion/browser-only prompts. Managed product images remain part of the deletion flow.
- Regression coverage now exercises product editor retail identifiers, advisory readiness, bulk visibility, featured actions, and guarded bulk deletion. Additional bulk-visibility tests are currently running in CI at working-line head; do not treat this Product Admin checkpoint as QAS-verified until the final branch-head CI and authenticated visual checks pass.
- Product Admin remains source-complete for this iteration but not Production-promoted. Next gate: green branch-head CI, authenticated QAS desktop/mobile + Arabic/English review, then resolve any findings before moving the module to release-ready status.

## V42 baseline status
- Clean V42 Git baseline completed and verified.
- Expected V42 source files matched the source snapshot by Git blob hash after intentional exclusions.
- Dependency/runtime/local-data artifacts are no longer tracked in the V42 branch.
- `.env` is no longer tracked in the V42 branch.
- Local DB, logs, sessions, uploads, generated Laravel cache files, and backup files are excluded from the baseline.

## Completed / strongly implemented
### Commerce core
- storefront, categories, brands, products, variants and attributes
- cart, checkout, orders and coupons
- cancellation/refund foundations
- Arabic/English translation foundation

### Operations
- suppliers and purchases
- inventory movement foundations
- cost/profit foundations
- Cost Calculator module introduced in V42

### Payments
- payment records/settings
- Paymob gateway foundation
- callback/result flows
- payment admin visibility

### Growth / intelligence foundations
- analytics events and daily stats
- growth automation rules
- message templates/logs
- audience segments
- experiments/validation foundations
- attribution/cohort/customer-scoring foundations

### Platform/admin foundations
- roles/permissions foundations
- notifications and settings
- branding/content controls
- deployment and rollback scripts

## Production-hardening priorities
1. **P0 — Credential rotation:** credentials previously committed to Git history must be treated as exposed and rotated before Production.
2. Verify payment callback/HMAC behavior and idempotency.
3. Verify authorization and privilege-escalation paths.
4. Verify concurrency behavior for stock, coupons, refunds, and orders.
5. Verify Cost Calculator formulas and reporting semantics.
6. Add/repair automated CI coverage for critical flows.
7. Execute deployment, health-check, and rollback validation.
8. Promote V42 to `main` only after verification.

## Planned / not yet closed
- WhatsApp/SMS multi-channel expansion
- deeper analytics/dashboard polish
- broader Arabic/English consistency pass
- HR/POS/barcode/receipt capabilities
- wishlist/comparison/smart search/customer feature closure
- subscription/trial/renewal controls
- deeper AI personalization
- SaaS multi-tenancy and mobile-app path

## Release rule
No commercial handoff or Production deployment is considered complete until the production-hardening priorities are closed or explicitly accepted with documented risk.


## V42 hardening checkpoint — 2026-09-20
### Newly closed
- duplicate checkout protection using transactional cart locking
- atomic inventory decrement / oversell protection
- atomic coupon usage-limit enforcement
- order cancellation idempotency and row-level serialization
- refund race / over-refund protection
- payment/refund-ledger consistency
- permissions self-escalation path
- deploy-center safe default and mandatory verified health check
- clean MySQL migration compatibility for growth learning index
- GitHub Actions hardening CI established

### CI status
A production-like GitHub Actions build on PHP 8.2 + MySQL 8 is green for the V42 hardening branch. It validates Composer, PHP syntax, a clean migration, Laravel boot/routes, config/views, the existing PHPUnit suite, and the frontend production build.

### Still required before Production
- rotate credentials that were historically committed
- execute a Paymob sandbox/test end-to-end transaction and negative-path callback checks
- rehearse deploy/rollback against the target server or staging-like environment
- validate the explicit database recovery procedure using the pre-migration SQL snapshot
- run final smoke checks, then promote V42 to `main`

### Additional V42 hardening — 2026-09-20
- custom role deletion now refuses assigned roles, preventing foreign-key cascade from leaving admins in implicit legacy Super Admin state
- permission-role deletion now has targeted automated regression coverage
- remaining tracked Livewire temporary files and product runtime uploads were removed from Git and their runtime storage paths are ignored

### Financial integrity follow-up — 2026-09-20
- Paymob/gateway status transitions are now row-locked and transactional, closing the simultaneous paid-vs-failed callback race
- order payment-state synchronization is row-locked
- permanent order hard-delete is disabled to retain financial and inventory audit history
- targeted tests now cover terminal paid state, retained cancelled orders, and assigned-role deletion protection


### Final V42 code-hardening checkpoint — 2026-09-20
Additional closures verified by green CI:
- full-refund Payment ledger synchronization and terminal refunded state
- consistent Order → Payment lock ordering for payment/refund concurrency
- checkout transaction rollback regression coverage for late failures
- cancellation inventory-restock audit movements without changing current product cost valuation
- correct Profit Margin semantics in Cost Calculator (profit / selling price)
- deploy backup secret minimization (`.env` excluded from app snapshot; private file creation)
- manual rollback maintenance mode + HTTPS health verification
- Paymob callback raw-payload/full-URL log minimization

The latest production-like GitHub Actions run for code head `b9a46a6` passed the complete pipeline on PHP 8.2 + MySQL 8, including the expanded PHPUnit regression suite and frontend production build.

Historical Git inspection confirms that `.env` existed in the older repository history before the hardening removal commit. Credential rotation therefore remains a mandatory P0 release gate even though V42 no longer tracks the file.


### Recovered legacy deployment/payment notes — 2026-09-20
Recovered from the user's old local notes (non-secret facts only):
- Paymob Merchant ID: `1147230`
- Paymob Test Integration ID previously used: `5596653`
- Paymob legacy IFrame previously used: `1024107`
- Production Laravel app path historically used: `/home/u637857322/domains/tag-marketplace.com/laravel_app`
- Production public webroot historically used: `/home/u637857322/domains/tag-marketplace.com/public_html`
- Historical SSH port used: `65002`
- Historical deploy routine used `deploy.sh` / `rollback.sh` from the Laravel app directory.

Security note:
- The recovered notes also contained plaintext provider, database, SSH, and account credentials.
- No credential values are copied into this repository documentation.
- Treat all historical credentials from those notes as exposed and rotate them before Production promotion.
- The recovered Paymob Integration ID / IFrame ID are identifiers, not secrets, and may be used for compatibility diagnostics.


### Gmail evidence for Paymob account state — 2026-09-20
Connected Gmail history confirms:
- Paymob welcomed the merchant account on 2026-03-31 after dashboard onboarding.
- Multiple Paymob TEST card transactions were executed on 2026-03-31 for EGP 98.00 through the hosted IFRAME flow; all surfaced transaction-status emails were declined.
- Starting 2026-04-01, Paymob repeatedly sent "Document Resubmission Required" onboarding emails for Merchant ID `1147230`.
- The same resubmission notice was still being sent as recently as 2026-09-18.
- Gmail search found no Paymob email confirming successful verification, activation, or Live-mode approval.
- Gmail search also found no sent support thread to `support@paymob.com` or `support@weaccept.co`.

Operational conclusion:
- The merchant account was integrated enough for Test IFRAME transactions, but onboarding/verification appears to have remained incomplete.
- This account state likely contributes to current dashboard limitations and is a separate concern from Laravel integration correctness.
- Continue V42 release hardening independently; do not block non-payment readiness work on Paymob verification.


### Production server discovery — 2026-09-20
- SSH login to Hostinger succeeded.
- The historical application path `/home/u637857322/domains/tag-marketplace.com/laravel_app` exists.
- Running `git status --short --branch` inside that directory returned: `fatal: not a git repository`.
- Therefore the current production Laravel directory is not a Git worktree. Do not run fetch/pull/reset/checkout there until the actual deployment layout is inspected and a safe migration/rehearsal plan is chosen.


### Production filesystem inspection — 2026-09-20
At `/home/u637857322/domains/tag-marketplace.com/laravel_app`:
- Laravel application files are present, including `artisan`, `composer.json`, `vendor/`, `storage/`, `.env`, `deploy.sh`, and `rollback.sh`.
- No `.git/` directory is present; this is a deployed file snapshot, not a Git checkout.
- Most application files are dated around 2026-04-17, while `.env` is server-local.
- Do not convert this live production directory into a Git worktree in place before a controlled rehearsal/backup plan.
- Preferred next step: inspect server runtime/tooling and free space, then prepare a separate V42 rehearsal directory so production remains untouched.


### Production runtime check — 2026-09-20
- Hostinger CLI PHP version: `PHP 8.3.33` (NTS) with Zend OPcache.
- This satisfies the application's Composer requirement (`php ^8.1`) at the runtime version level.


### Production Composer check — 2026-09-20
- Hostinger Composer version: `2.9.8`.
- Composer is running under PHP `8.3.33` from `/opt/alt/php83/usr/bin/php`.


### Production PHP extension check — 2026-09-20
Required runtime extensions were confirmed present on Hostinger CLI PHP 8.3.33:
- bcmath
- curl
- dom
- intl
- mbstring
- pdo_mysql
- xml / SimpleXML / xmlreader / xmlwriter
- zip

This clears the PHP-extension portion of the server preflight.


### Production Laravel runtime check — 2026-09-20
Hostinger production runtime currently reports:
- Application: Tag Marketplace
- Laravel: 10.48.29
- PHP: 8.3.33
- Composer: 2.9.8
- Environment: production
- Maintenance mode: OFF
- APP_DEBUG/runtime debug mode: ENABLED

Security/release note:
- Debug mode being enabled in production is a release blocker and should be changed to disabled before the next production deployment.
- Do not change it blindly mid-audit; verify the current server .env values first, then update in a controlled step and clear/rebuild config cache.


### Production debug configuration fix — 2026-09-20
- Confirmed production `.env` had `APP_DEBUG=true`.
- Created a server-side backup: `.env.backup_before_debug_fix`.
- Updated production `.env` to `APP_DEBUG=false`.
- Next step is to rebuild Laravel config cache and verify runtime reports Debug Mode disabled.


### Production debug verification — 2026-09-20
- Rebuilt Laravel config cache after changing production `APP_DEBUG=false`.
- Verified with `php artisan about --only=environment` that runtime Debug Mode is now OFF.
- Production remains out of maintenance mode.


### Production database connectivity check — 2026-09-20
- `php artisan migrate:status` completed successfully on production, confirming Laravel can connect to the configured database.
- Every migration file currently present in the deployed production snapshot is marked Ran.
- The V42 hardening branch contains three newer migration files not present in this production snapshot yet:
  - `2026_06_24_000000_create_cost_calculator_tables.php`
  - `2026_09_20_000000_normalize_growth_offer_learning_index.php`
  - `2026_09_20_235900_scrub_plaintext_provider_secrets.php`
- These must only be applied during the controlled V42 deployment/rehearsal after a database snapshot; do not run them on the current production snapshot now.


### Production env permission hardening — 2026-09-20
- Confirmed production `.env` and the temporary debug-fix backup were mode `644`.
- Changed both to mode `600`.
- Verified owner remains `u637857322`.
- This removes group/other read access from files containing production secrets.


### Production public webroot inspection — 2026-09-20
- `/home/u637857322/domains/tag-marketplace.com/public_html` exists and contains the deployed public assets.
- Visible entries include `index.php`, `.htaccess`, `build/`, `assets/`, `admin/`, `storage/`, and `uploads/`.
- The current deployment uses a split layout: Laravel application code under `laravel_app`, with web-facing public assets under `public_html`.
- Next check: inspect `public_html/index.php` to confirm exactly which Laravel application path it boots.


### Production bootstrap linkage confirmed — 2026-09-20
- `public_html/index.php` requires `../laravel_app/vendor/autoload.php`.
- It boots `../laravel_app/bootstrap/app.php`.
- Therefore the live webroot is explicitly wired to the sibling `laravel_app` directory.
- Any rehearsal must use a separate directory and must not repoint `public_html/index.php` until the release gate is approved.


### Production health check after hardening — 2026-09-20
- After setting `APP_DEBUG=false`, rebuilding config cache, and tightening `.env` permissions, the live site returned `HTTP 200` from `https://tag-marketplace.com`.
- Current production remains healthy after the server-side safety fixes.
- Next phase: prepare a separate V42 rehearsal directory; do not repoint `public_html` or run V42 migrations yet.


### Production Node.js check — 2026-09-20
- `node` is not installed/available in the Hostinger SSH shell.
- This is not a production deploy blocker for the current V42 flow because frontend assets are built in CI and `public/build` is committed/deployed with the application.
- The hardened `deploy.sh` does not require Node/npm on production.
- Do not install Node on the live server solely for deployment unless the deployment strategy changes.


## Post-deployment cleanup — 2026-09-23
- Routine QAS -> Production promotion was already validated end-to-end with application commit `95e9f50`.
- Two homepage labels used only as visible deployment markers were still present after validation: `V42 Special Offers` and `V42 Popular Products`.
- Cleanup commit `41a2f99` restores the intended customer-facing labels: `Today offers` and `Popular now`.
- Cleanup commit is pushed to `v42-clean-baseline` and is **not yet recorded as QAS/Production deployed**.
- Next operational step: deploy `41a2f99` to QAS, browser-verify, then Production dry-run + exact-commit execution if approved.
- After the cleanup is verified in Production, promote the validated V42 history to `main` so source control matches the production release line.


## Full project audit — 2026-09-23
- A repository-wide architecture, commercial-readiness, security, UX, bilingual, performance, testing, and operations audit is recorded in `docs/FULL_PROJECT_AUDIT_2026-09-23.md`.
- Immediate priority sequence: explicit owner/Super Admin authorization; remove fabricated storefront social proof; close credential-rotation evidence; real storefront search; shipping/tax; unpaid-online-order stock release; Paymob E2E; returns/reviews/addresses; then architecture/framework modernization.
- Latest audited `v42-clean-baseline` CI is green.

## UI/UX and commercial-readiness addendum — 2026-09-23
- Detailed screen and journey review: `docs/UI_UX_AND_COMMERCIAL_READINESS_REVIEW_2026-09-23.md`; baseline inspected at `c1f2146` on `v42-clean-baseline`.
- Live Production storefront inspection found `Moble`/`Toolsssss`, an unrelated product description, default support email/phone, mixed Arabic/English text, fabricated review and activity counts, repeated single-product merchandising, and the still-deployed V42 marker labels. A no-match header search returned the ordinary homepage.
- Additional code finding: the EGP 600 free-shipping progress message is disconnected from the current zero-valued shipping calculation; “best sellers” may use non-paid order items and falls back to newest products; authenticated Growth Operations exposes demo-data seed/clear without an environment guard.
- Admin UX implementation order: section the Livewire product form while preserving its state/save behavior; focus order-detail actions; separate branding workspaces; shorten the dashboard's default operator view. Growth already provides an example of focused subpages. Admin visual/mobile behavior still needs authenticated QAS review.
- Before real commercial traffic, close public-content/trust and authorization gates; then implement search, honest shipping/tax totals and unpaid-order stock lifecycle. Measure search, checkout, payment, fulfillment, support and Web Vitals against non-demo baselines rather than assuming a design change guarantees sales.
- This addendum is documentation only. No application code, QAS, Production, database, payment configuration, or public content was changed by the review. The cleanup commit `41a2f99` remains not recorded as deployed; `main` remains on the older baseline until separately reconciled.


## Admin Commerce Operations V2 — 2026-09-24
The current `v42-clean-baseline` branch now includes a broad operational-admin pass across the commercial back office. This work is branch-level only unless a later deployment checkpoint explicitly says otherwise.

- **Catalog / Products:** operational filters, readiness and inventory queues, catalog health KPIs, guarded delete flows, inline simple-product editing, featured controls, saved views, and advisory content readiness. Product activation intentionally remains non-blocking while retail/barcode/variant policy is finalized.
- **Categories / Brands / Attributes:** usage and content-coverage filters, guarded destructive actions, improved editor workspaces, and attribute-value operational visibility. Attribute-value scoped editing and in-use rename integrity remain follow-up items.
- **Orders / Inventory / Purchases / Suppliers:** action queues, financial and inventory context, improved procurement/supplier workspaces, bilingual UI coverage, and safer operational presentation. Purchase receive idempotency/transaction semantics and deeper order transition/refund tests remain release-hardening work.
- **Customers:** retention workspace with buyer/no-order/repeat/high-value views, customer revenue context, net spend and average-order detail metrics, and improved account/access presentation.
- **Coupons / Promotions:** promotion operations queues and builder UX, percentage validation, schedule visibility, real PromotionEngine regression coverage, required category targeting for category-percentage rules, and normalization that clears stale fields when promotion type changes.
- **Deliveries / Payments:** action/exception queues and reconciliation-oriented detail UI. Delivery transition rules and payment transition/idempotency hardening remain explicit follow-up items.
- **Localization:** English and Arabic strings were expanded across the touched admin workspaces; touched legacy screens should continue to be checked for untranslated strings during each subsequent batch.
- **Localization / consistency:** English and Arabic coverage was expanded across touched workspaces. Attribute Values now follows the same operational Admin V2 language for headers, metrics, protected actions, search/empty states, loading feedback, and destructive confirmations.
- **Verified CI:** Product/Promotion regression suite was clean at run `35932953241`. Attribute Value integrity fixes and their final UI/i18n batch are verified green at run `35934209260` on commit `1672bc9a`.

## Attribute Values integrity + UX closure — 2026-09-24
- Scoped edit/save prevents a value ID from another attribute from being edited or reassigned through the current attribute workspace.
- Variant usage remains keyed by attribute + textual value; therefore an in-use value cannot be renamed because that would silently desynchronize existing variant records.
- In-use values cannot be deleted. Unused values remain editable/deletable and same-attribute duplicates remain rejected.
- Total / in-use / unused KPIs are calculated from the complete attribute value set rather than the current search result.
- Added `AdminAttributeValueIntegrityTest` coverage for cross-attribute access, protected rename/delete, normal unused rename/delete, and duplicate rejection.
- UI now communicates the lock directly, disables invalid edit/delete actions, distinguishes search-no-results from a genuinely empty attribute, and shows save progress.
- Missing English/Arabic copy for the workspace and protection states was completed.
- Final Attribute Values batch CI: **green**, run `35934209260`, head `1672bc9a`.

### Next hardening sequence
1. Harden delivery transition/status/timestamp/notification rules.
2. Verify purchase receiving transaction/idempotency and inventory movement behavior.
3. Continue Settings/branding/colors UX overhaul.
4. Build employee operations, POS/cashier, barcode workflows, and detailed invoice printing as separate coherent product batches.

> Standing Definition of Done for each batch: business safety + UI/UX + Admin V2 consistency + EN/AR localization + regression tests + documentation + verified CI.


## Payments V2 hardening closure — 2026-09-24
- Explicit safe manual transition allow-list; refunded is terminal and paid cannot be manually downgraded.
- Gateway callbacks are row-locked and replay-safe; terminal paid/refunded states reject stale downgrades.
- Regression coverage includes invalid manual regression, callback replay idempotency, and late-failure protection after paid confirmation.
- Payment Details locks terminal financial states, exposes only safe next states, points returned-money handling to the order refund workflow, and surfaces HMAC plus gateway reconciliation evidence.
- English/Arabic safety and reconciliation copy completed for the touched payment workflow.
- Final Payments V2 CI: **green**, run `35935259115`, head `48fc7c0d`.


## Admin UI/UX consistency backlog — 2026-09-24
These are cross-admin requirements, not isolated screen fixes, and should be applied progressively to every touched admin workspace.

- **Theme system:** redesign the current admin themes/colors; the existing palette is not the desired quality bar. Build a more polished, cohesive theme/token system rather than page-specific color patches.
- **Page structure:** long or visually flat screens must be divided into clear sections/cards/workspaces. The Brand create/edit screen is a concrete example of a form that currently feels insufficiently structured.
- **KPI/header cards:** redesign the recurring header metric cards (for example Open/Closed/count cards) into a stronger reusable component and migrate the improved pattern across admin pages instead of fixing individual screens.
- **Switch alignment:** audit toggle/switch controls globally. Current switches can sit outside or misalign with their label/form row, especially in RTL; fix the shared layout/component so the correction propagates consistently.
- **Analytics & Insights:** treat this as a major redesign target. The current long page lacks hierarchy and does not consistently follow the Admin V2 visual language; improve information architecture, sectioning, metric presentation, spacing, responsive behavior, and shared components.
- **Categories:** explicitly audit Arabic/English parity, mixed-language copy, RTL/LTR layout, labels, validation, empty/loading states, and terminology.
- **Consistency rule:** every subsequent admin batch must reuse the same page shell, section/card language, KPI components, filters, actions, spacing, typography, states, confirmations, and bilingual/RTL behavior. Avoid one-off UI patterns unless the workflow genuinely requires them.
- **Quality rule:** when an old screen is touched, fix obvious UI/UX, localization, RTL, and consistency defects encountered in that screen rather than preserving them as legacy debt.


## Live / no-reload interaction standard — 2026-09-24
- This is a cross-product requirement for both **Admin** and **Customer** interfaces, not a POS-only enhancement.
- Search, autocomplete, filters, sorting, pagination, tab/workspace switching, and safe inline actions should progressively avoid unnecessary full-page reloads and use reusable live/AJAX-style interaction when it preserves context and improves speed.
- Operational search should normally be debounced, contains-style, keyboard-accessible, and expose enough result context for safe selection.
- Every touched Admin/Customer screen must be checked for legacy reload-based controls and suitable interactions should be migrated as part of that screen's UX hardening.
- Live behavior must retain loading, empty, validation and error states, EN/AR + RTL, accessibility, authorization, CSRF, and server-side business rules. Sensitive/destructive/financial/inventory/permission actions remain server-authoritative and require explicit outcome feedback; no optimistic UI may imply success before the backend confirms it.
- POS is the first current implementation example: product/customer lookup is moving from GET + page reload to debounced live autocomplete while barcode scanning remains a dedicated fast path.

### Implementation checkpoint: manager Cash Shift Review
- The POS product/customer lookup now uses live autocomplete and the legacy reload search path has been removed from the current branch.
- Manager Cash Shift Review now uses the reusable server-rendered live-list helper for debounced cashier search, status filtering, and pagination. It retains GET/URL navigation, Back/Forward, a no-JavaScript path, and an explicit error fallback to the full page.
- Both full and fragment reads keep the existing `pos.shifts.review` authorization boundary; a feature test checks access, filtering and escaped notes. The live slice changes no cash, order, payment, stock or shift records.
- The earlier branch CI at `a4fbfe9` had five failing POS tests. The working batch fixes stale role/search/schema expectations, missing-variant validation feedback, and the Sale Summary Blade syntax fault; a separate Address Book Blade syntax fault found during the compiled-view check is also fixed.
- Local verification passed: POS 32 tests / 330 assertions and Customer Account 3 tests / 58 assertions using a separate SQLite test worktree with temporary MySQL-to-SQLite compatibility changes; all 166 compiled Blade views passed PHP syntax lint. `node --check`, EN/AR JSON parsing and `git diff --check` also passed. Integrated [Hardening CI 36059356857](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36059356857) then passed on MySQL 8 at `19a4b8c` with 197 tests / 1248 assertions and frontend build.
- After explicit owner approval, the GitHub connection published the identical source trees as `bb10f96` (shift review) and `19a4b8c` (Orders). The new commit IDs reflect GitHub API metadata; the final tree matches local `f69d5cb` exactly. QAS was last confirmed at `0a08253`; `main` and Production remain unchanged.

### Implementation checkpoint: Admin Orders live list
- The same reusable helper now supports operational queue and column-sort links in addition to debounced search, filters and pagination. Queue selection, results and sorting refresh together while the GET form, shareable URL, Back/Forward and full-page fallback stay in sync.
- The permission-protected `orders.view` action returns the same server-filtered rows as a full page or a results fragment; read-only staff no longer see quick-status controls or unrelated navigation without access. Status changes stay on the existing server-authoritative `orders.manage` route, with a saving state after live replacement.
- The fragment avoids full-page financial summary queries and preserves Blade escaping. Global KPI cards remain unfiltered and refresh on a normal page load. EN/AR loading, success and error copy is reused.
- Local verification: two new feature tests passed (29 assertions) in the isolated SQLite compatibility worktree; compiled Blade templates, PHP/JavaScript syntax and whitespace checks passed. Integrated MySQL [CI 36059356857](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36059356857) passed at `19a4b8c` with 197 tests / 1248 assertions.
- The owner explicitly approved publication after the earlier automatic review block. The published `v42-clean-baseline` source matches the local final tree exactly. QAS remains operator-confirmed at `0a08253`; no `main` or Production change is claimed.


## Returns / RMA V1 — 2026-09-25
- Added customer-owned return requests for eligible paid delivered/completed orders, with explicit item quantities, reason and requested Refund/Exchange resolution.
- Active return quantities reduce each order item's remaining returnable quantity so repeated requests cannot exceed the original purchase.
- Customer workspace: My Returns, request form, return details/status timeline, return history on Order Details, and customer cancellation while still Requested.
- Admin workspace: live/no-reload search/status/pagination under `orders.view`; lifecycle mutations remain under `orders.manage`.
- Lifecycle: Requested → Approved → Received → Completed, with Requested → Rejected and customer Requested → Cancelled side paths.
- Approval does not restore stock. Receiving requires the full approved quantity in V1 and records an explicit restock quantity, allowing damaged/unsellable goods to stay out of saleable inventory.
- Restocked units use `return_restock` inventory movements with RMA metadata.
- Completion reuses the canonical order refund ledger and links refunds through `order_refunds.return_request_id`.
- Financial hardening: RMA refund amount cannot exceed the value of received items whose requested resolution is Refund; the normal remaining order refundable balance is still enforced.
- Exchange hardening: linked exchange order must differ from the original order and belong to the same customer.
- EN/AR copy and RTL-compatible customer/admin views are included.
- Focused regression coverage: `ReturnRequestWorkflowTest` for lifecycle/restock/refund linkage, RMA over-refund prevention, and cross-customer exchange prevention.
- Detailed implementation note: `docs/RETURNS_RMA_V1_2026-09-25.md`.
- Source is on `v42-clean-baseline`; final integrated CI verification is pending at this checkpoint. QAS and Production are unchanged until the normal consolidated review/deployment gate.


## Explicit admin role hardening — 2026-09-25
- Closed the legacy roleless-admin Super Admin fallback.
- Existing `role_as = 1` accounts without a staff role are converted by migration to an explicit `super_admin` assignment before fallback removal.
- The migration self-heals the Super Admin system role on older databases and attaches current permissions when available.
- `User::isSuperAdmin()` now requires explicit `super_admin`; missing role data no longer equals full access.
- `AdminMiddleware` now requires an explicit staff role in addition to the admin flag.
- Roleless admins are labeled `Unassigned admin` and receive no implicit permissions.
- Focused regression coverage: `ExplicitAdminRoleHardeningTest`.
- Detailed note: `docs/EXPLICIT_ADMIN_ROLE_HARDENING_2026-09-25.md`.
- CI/QAS verification remains pending for this new head; Production is unchanged.

## Roles & Permissions V2 — 2026-09-25
- Rebuilt the previously long, mixed-language access-control page into four focused workspaces: Overview, Staff assignments, Roles, and Permission matrix.
- Reused the shared accessible Admin section-tab pattern with keyboard navigation and RTL-aware arrow behavior.
- Added explicit unassigned-admin warning; missing role data is never presented as Super Admin.
- Staff assignment cards now focus on one account, current role, and one server-authoritative role change.
- Custom role creation/editing groups capabilities by access area instead of rendering one giant checkbox wall; existing custom roles stay collapsed until edited.
- Role deletion now uses the shared in-app confirmation flow; system-role and assigned-role backend protections remain unchanged.
- Permission matrix now has client-side no-reload search across permission name, description, group, slug and assigned roles, plus visible-result counting and automatic group filtering.
- Completed EN/AR copy for the new workspace, system roles, built-in permission names/descriptions, and access-area labels.
- Added PermissionsWorkspaceV2Test covering structure/search markup, Arabic copy, unassigned-admin presentation, and absence of legacy fallback wording.
- Fixed duplicate Return controller imports found during the pass before continuing the UI work.
- Detailed note: docs/ROLES_PERMISSIONS_V2_2026-09-25.md.
- CI/QAS verification pending for the current head; Production unchanged.

## Admin UI consistency foundation V2 — 2026-09-25
- Added a real shared admin Stat Card component instead of relying on repeated hand-written KPI markup.
- Standardized the component contract for label/value/icon/help/meta plus semantic success/warning/danger accents.
- Rolled the shared KPI card into Analytics, Permissions, Orders, Deliveries, Customers, Purchases, Payments, Inventory, and Workforce Employees/Schedule/Corrections/Leave.
- Analytics KPI presentation now uses the same shared component rather than its own competing label/value/help structure.
- Added a global admin switch layout contract that neutralizes conflicting spacing utilities and aligns control/label/help consistently.
- Switch layout is RTL-aware and supports reverse mode without physical left/right assumptions.
- Added AdminUiConsistencyV2Test covering the component contract, core rollout, and RTL-safe switch foundation.
- Detailed note: docs/ADMIN_UI_CONSISTENCY_V2_2026-09-25.md.
- CI/QAS verification pending for the current head; Production unchanged.

## Analytics workspace V2 — 2026-09-25
- Reworked Revenue Intelligence from one long anchor-scrolled page into focused shared section tabs: Performance, Decision read, Trends, Funnel & comparison, and Drilldowns.
- Moved More diagnostics under Trends and Session watchlist under Drilldowns without removing any existing data or calculations.
- Reworked Offers drilldown into Summary, Coupon charts, Management view, and Detailed table tabs.
- Converted Offers KPI cards to the shared Admin Stat Card component.
- Reworked Growth Automation into Growth overview, Campaigns & automation, Product signals, and Offers & coupons.
- Preserved all existing analytics calculations, date/range logic, charts, recommendations, funnel metrics, product/coupon data, and server-side authorization.
- Added EN/AR labels for the new workspaces and reused RTL-aware shared section-tab behavior.
- Added AnalyticsWorkspaceV2Test covering the three analytics workspaces, removal of old anchor navigation, shared KPI usage, and Arabic labels.
- Detailed note: docs/ANALYTICS_WORKSPACE_V2_2026-09-25.md.
- CI/QAS verification pending for the current head; Production unchanged.

## Branding workspace V2 — 2026-09-25
- Reduced the White-label / Branding page height without changing settings keys, upload behavior, theme application, or the update endpoint.
- Replaced the three top summary cards with the shared Admin Stat Card component.
- Split Homepage CMS into focused collapsible sections: Visibility & order, Hero content, Merchandising sections, Manual featured products, and Trust & legacy content.
- Isolated legacy promo fields from current homepage merchandising settings instead of mixing them into one flat form.
- Converted all three promo banner editors into collapsible cards with title and active-state summaries.
- Converted all four trust block editors into the same collapsible pattern.
- Kept the shared RTL-safe switch presentation for homepage visibility and active-state controls.
- Added EN/AR labels for the new Branding workspace structure.
- Added BrandingWorkspaceV2Test covering shared KPI adoption, Homepage CMS sections, promo/trust collapse behavior, and Arabic labels.
- Detailed note: docs/BRANDING_WORKSPACE_V2_2026-09-25.md.
- CI/QAS verification pending for the current head; Production unchanged.

## Categories workspace V2 — 2026-09-25
- Aligned Categories index with Admin V2 shared page header and Stat Card components.
- Preserved live/no-reload category search, filtering, sorting, cleanup queues, and pagination.
- Reworked Create/Edit from anchor-scroll sections into focused Basics, Translations, SEO, and Media & visibility tabs.
- Clarified fallback/default content versus localized EN/AR content.
- Arabic translation panes now use explicit RTL direction and language metadata while slug fields remain LTR.
- Removed the external via.placeholder.com category-image dependency and replaced it with a local empty preview state.
- Fixed invalid IlluminateSupportStr::limit usage in category result rendering.
- Added missing Arabic copy for category help text, media/visibility, translation guidance, image state, save guidance, success messages, and linked-product delete protection.
- Added shared logical RTL alignment helpers for table/action layouts.
- Added CategoryWorkspaceV2Test covering shared components, editor tabs, RTL/LTR behavior, local image preview, valid Str namespace, and Arabic labels.
- Detailed note: docs/CATEGORIES_WORKSPACE_V2_2026-09-25.md.
- CI/QAS verification pending for the current head; Production unchanged.

## Catalog workspace V2 — 2026-09-25
- Aligned Brands, Attributes, and Attribute Values with the shared Admin V2 Page Header and Stat Card components.
- Preserved existing Livewire search, filters, pagination, inline editing, protected deletes, and catalog business rules.
- Added logical RTL Actions alignment across Brands, Attributes, Attribute Values, and Products.
- Converted Product Catalog / Active / Hidden health cards to shared Stat Cards.
- Kept Needs content / Low stock / Out of stock as interactive Livewire KPI filters while applying the same Stat Card V2 visual contract.
- Removed browser-native confirm() dialogs from Product bulk Activate/Hide and replaced them with in-app confirmation modals while preserving bulkSetStatus behavior.
- Fixed mixed-language Product SEO label by using the complete Meta Description translation key.
- Added missing Arabic cleanup/status/confirmation copy for catalog management.
- Added CatalogWorkspaceV2Test covering shared primitives, RTL actions, Product health-card consistency, in-app confirmations, and Arabic labels.
- Detailed note: docs/CATALOG_WORKSPACE_V2_2026-09-25.md.
- CI/QAS verification pending for the current head; Production unchanged.

## Admin consistency sweep V2 — 2026-09-25
- Continued Admin V2 normalization across Suppliers, Coupons, Promotions, Import Jobs, and Admin Notifications.
- Replaced remaining manual KPI/summary-card markup in those workspaces with the shared Admin Stat Card component.
- Moved Coupons, Promotions, and Imports from legacy custom page headers to the shared Admin page-header component.
- Preserved existing live-list search, filters, sorting, pagination, notification actions, and server-authoritative mutations.
- Added logical RTL alignment to Supplier, Coupon, and Promotion action columns.
- Fixed three runtime rendering hazards discovered during the sweep: invalid IlluminateSupportStr::limit, AppModelsCoupon::TYPE_PERCENT, and IlluminateSupportStr::headline references.
- Added AdminConsistencySweepV2Test covering shared primitives, valid namespaces, RTL actions, and removal of old KPI markup.
- Detailed note: docs/ADMIN_CONSISTENCY_SWEEP_V2_2026-09-25.md.
- CI/QAS verification pending for the current head; Production unchanged.

## Admin form workspace V2 — 2026-09-25
- Reworked Coupon Create/Edit into shared Offer, Limits & eligibility, and Schedule & notes tabs while preserving its live preview and existing request contract.
- Reworked Promotion Create/Edit into shared Rule, Eligibility, and Schedule & status tabs while preserving buy-X-get-Y UI logic and server-side validation.
- Moved Coupon and Promotion forms to the shared Admin page-header component.
- Kept Supplier Create/Edit structure but removed redundant local switch sizing so it follows the global Admin switch contract.
- Added Arabic labels for all new Coupon/Promotion form sections.
- Added AdminFormWorkspaceV2Test covering shared headers/tabs, coupon preview preservation, promotion buy-X-get-Y behavior marker, Supplier switch cleanup, and Arabic labels.
- Detailed note: docs/ADMIN_FORM_WORKSPACE_V2_2026-09-25.md.
- CI/QAS verification pending for the current head; Production unchanged.

## Commerce settings workspace V2 — 2026-09-25
- Split Payment Settings into focused Payment methods, Gateway & stock, Paymob setup, and Bank transfer tabs without changing provider, callback, stock-reservation, secret-management, or update semantics.
- Added one shared Shipping settings navigation partial across Methods, Zones & cities, and Rates; removed duplicated cross-links from page headers.
- Preserved shipping method updates, zone/city uniqueness rules, rate upserts, threshold-basis validation, and checkout behavior.
- Reviewed Notification Center and intentionally kept its existing modular Overview / Logs / Templates / Automation / Diagnostics structure unchanged.
- Added Arabic labels for the new Payment and Shipping workspace navigation.
- Added CommerceSettingsWorkspaceV2Test covering Payment tabs, Paymob callback presence, shared Shipping navigation, Notification Center modularity, and Arabic labels.
- Detailed note: docs/COMMERCE_SETTINGS_WORKSPACE_V2_2026-09-25.md.
- CI/QAS verification pending for the current head; Production unchanged.
