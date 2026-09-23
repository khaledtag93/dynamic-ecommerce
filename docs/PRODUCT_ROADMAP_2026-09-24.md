# Dynamic — Product roadmap
Date: 2026-09-24
Working branch: `v42-clean-baseline`

This roadmap records the owner's expanded product direction after the 2026-09-23 architecture and UI/UX audits. It is a planning document, not a claim that the listed future modules are implemented or deployed.

## Product goal

Build Dynamic into a commercially credible, bilingual commerce and retail operations platform with a polished storefront, efficient admin workflows, trustworthy business rules, safe releases, and an extensible in-store/POS path.

## Delivery principles

- Preserve working behavior and change one reviewable slice at a time.
- CI -> QAS -> authenticated Arabic/English desktop/mobile review -> exact-commit Production promotion.
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

### Admin shell
- Finalize sidebar/topbar/navigation hierarchy and role-aware actions.
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

Build POS as a focused operational application, not as another dense admin page.

Core:
- PIN-based staff session/identity and location assignment.
- Product search + barcode scan + category shortcuts.
- Cart, quantity, line discount, order discount, customer attach/create.
- Cash/COD/custom payment foundation; online/card terminal integrations remain provider-specific.
- Hold/resume cart, notes and controlled manual price/discount permissions.
- Sale, cancel/void, return/exchange and restock workflows with manager approval where configured.
- Cash drawer/opening float, paid-in/paid-out, cash counts and shift reconciliation.
- Receipt print/reprint and order/receipt barcode.
- Offline-tolerant strategy only after conflict/reconciliation rules are designed.
- Every sensitive action attributed to the staff member in an activity log.

## Phase 5 — employees and workforce

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
