# Dynamic — Product roadmap
Date: 2026-09-24
Working branch: `v42-clean-baseline`

This roadmap records the owner's expanded product direction after the 2026-09-23 architecture and UI/UX audits. It is a planning document, not a claim that the listed future modules are implemented or deployed.

## Product goal

Build Dynamic into a commercially credible, bilingual commerce and retail operations platform with a polished storefront, efficient admin workflows, trustworthy business rules, safe releases, and an extensible in-store/POS path.

## Delivery principles

- Preserve working behavior and change one reviewable slice at a time.
- Run CI for each code batch. The owner deferred manual QAS scenarios to a consolidated review phase after more development; before any Production promotion, verify the exact QAS application commit and complete authenticated Arabic/English desktop/mobile review of the affected flows.
- Remove demo, placeholder, fabricated, or temporary release-marker content from customer-facing Production.
- Prefer reusable design-system components and business services over page-specific patches.
- Do not hard-code payroll, tax, shipping, or compliance rules that depend on jurisdiction or merchant policy.
- Treat permissions, audit logs, inventory, payment, refund, payroll, and cash-drawer actions as sensitive workflows.

## Phase 0 — finish the current release gates

1. QAS-review the CI-verified admin daily-work UX batch (`9fe1a96`) and trust/access batch (`f7ff4e8`).
2. Verify and remove temporary V42 homepage deployment markers in QAS, then Production only after approval.
3. Complete the explicit owner/Super Admin migration and remove the legacy roleless-admin fallback.
4. Audit/clean Production demo records, placeholder product/support content, and mixed-language customer copy.
5. Close historical credential rotation evidence.
6. Keep Paymob E2E/commercial readiness as a controlled payment gate.

## Phase 1 — UI/UX foundation and design system

**Implementation update — 2026-09-24:** Branding & Appearance V2 is implemented in source on `v42-clean-baseline` and documented in `BRANDING_APPEARANCE_V2_2026-09-24.md`. It introduces the Professional Commerce direction, visual theme selection, advanced palette disclosure, improved previews, unsaved-change handling, semantic admin states and homepage-toggle fixes. QAS visual/task acceptance remains required before this slice is considered verified or deployable.

### Admin shell
- Finalize sidebar/topbar/navigation hierarchy and role-aware actions.
- **Long-form action rule:** forms/workspaces with primary actions below the fold should use a reusable responsive sticky action dock (Save / Checkout / Approve / Update as appropriate), preserving safe content clearance, RTL, mobile behavior, validation visibility, and keyboard accessibility. Do not add sticky chrome when the primary action is already naturally visible.
- **Operational search rule:** high-frequency workflows should prefer fast contains/autocomplete-style discovery over exact-only lookup when ambiguity can be presented safely; show enough identity context to choose correctly without exposing unnecessary customer data.
- **No-reload interaction rule (Admin + Customer):** progressively replace ordinary full-page reload interactions for search, autocomplete, filters, sorting, pagination, tab/workspace switching, and safe inline actions with live/AJAX-style updates where this improves speed and preserves context. Search fields should normally use debounced contains-style live results/autocomplete when the result can be selected safely. Preserve keyboard accessibility, loading/error/empty states, URL/query state where navigation or sharing matters, RTL/EN/AR behavior, authorization, validation, CSRF protection, and server-side business rules. Do not convert destructive, financial, inventory, permission, or other sensitive actions into optimistic UI that can hide failure; these may still submit asynchronously, but the server remains authoritative and explicit confirmation/result feedback is required.
- **Touched-screen migration rule:** whenever an Admin or Customer page is modified, audit its remaining search/filter/action controls for unnecessary page reloads and migrate suitable interactions to the shared live pattern in the same batch when safe, rather than leaving isolated legacy reload UX behind.
- Standardize page headers, filters, tables, empty states, form actions, confirmations, toasts, validation and mobile behavior.
- Remove duplicated guidance cards and internal/demo wording that does not belong in a real merchant product.
- Reduce oversized pages by using focused sections/workspaces without breaking form state.

### Branding & Appearance
- Treat Branding as a first-class workspace rather than one long settings page.
- Keep focused sections for Theme, Identity, Customer colors, Homepage, Banners/Trust, Media, and Admin branding.
- Replace the current orange/pink-heavy default direction with a stronger neutral base and accessible configurable brand tokens.
- Add curated professional presets, live preview, reset-to-preset, contrast/readability checks and clearer unsaved-change behavior.
- Keep advanced granular colors available for power users, but do not make them the default editing experience.

### Storefront
- Mobile-first responsive pass for homepage, category/search, product, cart, checkout, payment result and account/order pages.
- Deduplicate repeated product merchandising when the catalog is small.
- Remove fabricated urgency/social proof and unsupported delivery/free-shipping claims.
- Finish customer-facing Arabic/English translation and RTL checks.

## Phase 2 — commerce correctness before platform expansion

1. Real global storefront search with results, pagination, filters, sorting and no-result state.
2. Shipping engine: methods, zones/cities, rates, free-shipping threshold, pickup, ETA and order snapshot.
3. Tax/VAT configuration and order tax snapshots after merchant/legal decision.
4. Online-payment stock reservation/expiry/release policy.
5. Returns/RMA workflow with item quantities, reasons, approval, restock and refund/exchange linkage.
6. Verified real reviews/moderation.
7. Saved customer addresses.
8. Real CSV import pipeline.
9. Invoice/receipt generation for online orders with printable/PDF-friendly detail.

## Phase 3 — inventory, barcode and retail readiness

### Barcode foundation
- Preserve SKU and existing barcode fields; define unique barcode rules at product/variant level.
- Support EAN/UPC/GTIN where merchants use GS1 identifiers, plus internal barcodes when appropriate.
- Barcode lookup endpoints/services shared by inventory and POS.
- Fast scanner input compatible with HID keyboard-mode scanners.
- Camera scanning later where browser/device support is reliable.
- Barcode label generation/printing and duplicate validation.

### Inventory workflows
- Scan to find product/variant.
- Scan during purchase receiving and stock adjustments.
- Inventory movement audit trail for every change.
- Later: locations/warehouses/transfers/cycle counts only after single-location rules are stable.

## Phase 4 — POS / cashier

**POS implementation update — 2026-09-24:** Source now includes scan-first cashier sales, Cash/Card Terminal checkout, canonical Order/Payment/Inventory ledgers, printable 58/80 mm + A4 sales receipts, Hold / Resume / Discard, customer attachment, discounts, controlled returns, cash shift reconciliation, manager shift review, and live product/customer lookup. Cash Shift Review is the first reusable live-list migration for filters and pagination; integrated branch CI passed at `19a4b8c` in run `36059356857`. Consolidated authenticated QAS review remains open. Fiscal/tax invoices, drawer paid-in/paid-out and workforce scheduling remain later controlled slices. Production is unchanged.

**Admin interaction update — 2026-09-24:** Admin Orders is the next reusable live-list consumer: search, status/payment/method filters, operational queues, column sorting and pagination keep URL/history while replacing server-rendered results. Read-only order staff see no status-edit form; financial and inventory actions remain server-authoritative. Local regression checks and integrated MySQL CI passed at published branch head `19a4b8c` (197 tests / 1248 assertions). Consolidated QAS review remains deferred.

**Admin Customers interaction update — 2026-09-25:** Customer name/email search, role/activity/value filters, per-page changes, operational customer queues and pagination now reuse the same progressive live-list pattern. Live requests preserve `customers.manage` authorization, escaped output, GET fallback and URL/history while avoiding full-page KPI/revenue recomputation. Focused regression coverage is committed at application revision `51a3418`; branch-head CI verification remains pending. QAS and Production are unchanged.

**Procurement / Inventory interaction update — 2026-09-25:** Suppliers, Purchases and Inventory Movement Explorer now reuse the same server-rendered progressive live-list infrastructure for their safe read-only search/filter/sort/queue/pagination interactions. Destructive, stock-receipt and inventory-changing actions remain explicit backend mutations. Inventory fragment requests no longer recompute low-stock/expiry workspaces. Application revision `7df29ec`; CI verification remains pending and QAS/Production are unchanged.

**Finance / Promotions interaction update — 2026-09-25:** Payments, Coupons and Promotion Rules now reuse the same live-list infrastructure for safe read-only search/filter/queue/sort/pagination flows. Payment status, coupon mutation and promotion mutation remain server-authoritative. Coupon Type sorting was corrected and promotion sorting is now surfaced in the UI. Application revision `b79177a`; CI verification remains pending and QAS/Production are unchanged.

**Live / no-reload phase closure — 2026-09-25:** Application revision `eedd109` closes the broad migration. Deliveries, Categories, Import pagination, storefront Search/Category browsing, My Orders pagination and Customer Notifications pagination join the earlier live surfaces. Product Admin remains Livewire. Business mutations remain server-authoritative by design. Detailed matrix: `LIVE_NO_RELOAD_CLOSURE_2026-09-25.md`. CI verification is pending; QAS/Production are unchanged. Future work should move to the next product priority and apply this interaction standard incrementally when screens are touched.

Build POS as a focused operational application, not as another dense admin page.

Core:
- PIN-based staff session/identity and location assignment.
- Product search + barcode scan + category shortcuts. POS product entry must support both fast exact HID scanning and a manual contains-style Name / SKU / Barcode fallback with stock/price context.
- Cart, quantity, line discount, order discount, customer attach/create. Existing-customer lookup should support contains-style name/email/phone discovery, with phone sourced from saved customer addresses until a canonical customer phone field is introduced.
- Cash/COD/custom payment foundation; online/card terminal integrations remain provider-specific.
- Hold/resume cart, notes and controlled manual price/discount permissions.
- Sale, cancel/void, return/exchange and restock workflows with manager approval where configured.
- Cash drawer/opening float, paid-in/paid-out, cash counts and shift reconciliation.
- Receipt print/reprint and order/receipt barcode.
- Offline-tolerant strategy only after conflict/reconciliation rules are designed.
- Every sensitive action attributed to the staff member in an activity log.

## Phase 5 — employees and workforce

**Foundation V1 implemented in source — 2026-09-25:** `b0fc6d20` adds employee profiles linked to staff users, personal attendance clock-in/out, live manager Employee Directory and Attendance Review, workforce permissions, attendance audit logs, and workforce identity in POS Cash Shift Review. Attendance remains separate from POS cash-drawer shifts and is not yet enforced as a checkout prerequisite. Detailed checkpoint: `WORKFORCE_FOUNDATION_2026-09-25.md`. CI verification is pending; QAS/Production are unchanged.

**Shift Scheduling V1 implemented in source — 2026-09-25:** `ff423286` adds employee work shifts with Draft/Published/Cancelled lifecycle, overlap protection, live manager schedule, My Schedule and first schedule-vs-attendance comparison. Detailed checkpoint: `WORKFORCE_SHIFT_SCHEDULING_V1_2026-09-25.md`. CI verification is pending; QAS/Production are unchanged.

**Attendance Rules & Corrections V1 implemented in source — 2026-09-25:** `b7fe238d` adds break tracking, immutable recorded-vs-effective attendance, audited correction requests/approval, centralized late/early/absence rules and net worked time. Work Schedule now consumes the same rule output. See `WORKFORCE_ATTENDANCE_RULES_CORRECTIONS_V1_2026-09-25.md`. CI verification is pending; this slice is not yet claimed on QAS.

**Leave Management V1 implemented in source — 2026-09-25:** `c6df3fc2` adds configurable leave types, employee requests, append-only balance adjustments, Available/Projected balances and bidirectional Schedule↔Approved-Leave conflict protection. See `WORKFORCE_LEAVE_MANAGEMENT_V1_2026-09-25.md`. CI verification is pending; this slice is not yet claimed on QAS.

**Payroll Foundation V1 implemented in source — 2026-09-25:** `b51a93e6` adds scoped payroll permissions, compensation profiles, payroll periods/runs, immutable employee payroll snapshots, explicit overtime/allowance/bonus/deduction components, currency-separated totals and self-owned printable payslips. Salary partial-period processing is blocked until an explicit proration policy exists; tax/social insurance/leave monetization remain configurable future policy. See `WORKFORCE_PAYROLL_FOUNDATION_V1_2026-09-25.md`. CI verification is pending and this slice is not claimed on QAS.

**Next implementation order:** Workforce QAS recovery + consolidated validation → critical commerce gaps (shipping/tax decision, unpaid-order stock reservation, Returns/RMA, online invoice) → Payroll V2 only after actual operating policy is defined.


Keep workforce identity related to, but not overloaded into, the customer account model.

Foundation:
- Employee profile, employment status, job/role, branch/location, hire/end dates.
- Link employee to an application user only when login access is needed.
- Separate business permissions from HR data access.

Attendance:
- Check-in/check-out timecards with location/device metadata and correction workflow.
- Breaks, late/early/absence rules, manual-edit request and manager approval.
- Audit trail; no silent edits to historical time.

Scheduling:
- Shift templates, draft/published schedules, availability and shift assignment.
- Time-off/leave requests and approvals.
- Conflict detection and schedule vs actual comparison.

Leave:
- Leave types, annual entitlement, accrual/carryover policy, used/pending/available balances and adjustments.

Payroll foundation:
- Pay periods, salary/hourly wage, approved attendance inputs, overtime, allowances, bonuses and deductions.
- Payslip and payroll run audit trail.
- Payroll/tax/social-insurance formulas must be configurable and validated for the actual operating jurisdiction before activation.
- Do not mix payroll ledger changes with ordinary profile edits.

## Phase 6 — delivery operations

Extend the existing delivery status/provider fields into an operational workflow:
- Shipping zones and service levels.
- Carrier/provider records and tracking numbers/links.
- Assignment to internal driver or external courier.
- Pick/pack/ready-to-ship milestones.
- Dispatch and delivery attempts.
- ETA windows and proof-of-delivery where needed.
- Failed delivery, reschedule, return-to-origin and COD reconciliation.
- Customer notifications through configured channels.
- Carrier API integrations added provider-by-provider behind an adapter interface.

## Phase 7 — conversion and customer experience

- Guest checkout.
- Wishlist.
- Product compare where useful for the catalog.
- Search autocomplete and stronger facets.
- Order tracking timeline.
- Review photos/Q&A after real-review foundation.
- Loyalty/rewards only after reliable customer/order economics exist.
- Abandoned-cart recovery only with consent and trustworthy analytics.

## Phase 8 — engineering modernization and scale

- Upgrade Laravel from the unsupported Laravel 10 baseline on a dedicated tested branch.
- Expand Form Requests, Policies, domain events and queued non-critical side effects.
- Static analysis, dependency audits, browser/E2E smoke tests and critical-domain coverage.
- Split the largest Blade/controller/service files incrementally.
- Asset cleanup, image optimization, caching and Core Web Vitals work.
- Multi-location/multi-warehouse, advanced carrier integrations, mobile app/API, multi-currency and SaaS only after the core is stable.

## Immediate execution order

**Execution decision — 2026-09-24:** Continue source work while recording manual QAS checks for a later consolidated pass. The QAS application commit `0a08253` was verified by server-side HEAD output; this confirms the code revision, not feature behavior. New code batches remain branch-only until separately deployed. Production gates are unchanged.

The next working sequence is:

1. QAS-review current admin UX changes and correct regressions.
2. Branding & Appearance redesign/polish, including a new professional color direction.
3. Continue admin screen-by-screen simplification (product, order, analytics, remaining forms/lists).
4. Storefront content/demo cleanup and customer funnel UI/UX.
5. Search + shipping + payment-stock correctness.
6. Invoice/receipt.
7. Barcode/inventory scanning foundation.
8. POS.
9. Employee/workforce.
10. Delivery expansion.

This ordering keeps the currently deployed commerce system stable while moving toward the larger retail/operations vision.
