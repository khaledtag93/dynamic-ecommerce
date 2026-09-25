# Dynamic Completion Pass — Consolidated QAS Acceptance Checklist
## Date: 2026-09-25

This is the authoritative manual acceptance gate for the current completion pass. It is intentionally broader than an individual feature checklist.

## Gate rules

- Deploy one exact `v42-clean-baseline` commit to QAS and record the SHA before testing.
- Do not infer Production readiness from source/CI success, public HTTP 200, or page-load recovery.
- Run the same critical flows in English and Arabic, including RTL layout.
- Cover desktop and mobile-web breakpoints.
- Use realistic non-production records.
- Record every defect with page/route, role, language, viewport, steps, expected result, actual result, and evidence.
- Re-run the affected module after every fix.
- Production remains unchanged until all release-blocking findings are closed.

## 1. Deployment and environment preflight

- [ ] Record source branch HEAD.
- [ ] Record deployed QAS SHA and confirm it exactly matches the intended commit.
- [ ] Confirm `APP_ENV` is QAS/staging and debug output is not exposed.
- [ ] Confirm QAS `PUBLIC_ROOT_PATH` points to the isolated QAS public root.
- [ ] Confirm existing QAS uploads/images render and no Production upload path is used.
- [ ] Run migrations successfully on the QAS database.
- [ ] Confirm routes, Blade compilation and frontend assets are healthy.
- [ ] Confirm scheduler/queue-dependent commands required by the tested flows are available.
- [ ] Capture a QAS database backup before destructive workflow testing.

## 2. Authentication, roles and permissions

Test at minimum Super Admin/owner-equivalent explicit role, Operations Manager, Support Agent, Cashier, normal Customer, and a role without the target permission.

- [ ] No roleless-admin fallback grants implicit Super Admin access.
- [ ] Sidebar/topbar items follow permissions.
- [ ] Direct-route access is denied when the UI hides an action.
- [ ] Roles & Permissions Overview / Staff / Roles / Permission matrix work in EN/AR.
- [ ] Permission search/filtering is no-reload and preserves context.
- [ ] Customer-to-staff promotion/demotion follows explicit role rules.
- [ ] Sensitive pages do not leak counts/details to unauthorized roles.

## 3. Admin shell and shared UX

- [ ] Shared KPI/stat cards are visually consistent across major workspaces.
- [ ] Long pages use clear sections/workspaces and sticky actions where appropriate.
- [ ] Switches/toggles align correctly in LTR and RTL.
- [ ] Live search/filter/sort/pagination does not cause avoidable full reloads.
- [ ] Browser Back/Forward restores live-list state.
- [ ] Success/warning/error feedback is visible without unexpected scroll jumps.
- [ ] Empty/loading/error states are bilingual and user-facing rather than developer-like.
- [ ] Admin sidebar/header/mobile navigation remain usable at narrow widths.

## 4. Catalog, categories, branding and analytics

### Products / catalog
- [ ] Product create/edit preserves simple/variant integrity.
- [ ] SKU/barcode uniqueness works across products and variants.
- [ ] Variant stock changes create correct inventory movements.
- [ ] Copying a product clears retail identifiers safely.
- [ ] Product Admin Livewire behavior preserves state and errors.

### Categories
- [ ] Canonical/base fields are not overwritten by localized accessors.
- [ ] Arabic translations can be added, changed and cleared safely.
- [ ] Category search/filter/pagination works live.
- [ ] EN/AR terminology, direction and validation are consistent.

### Branding / appearance
- [ ] Professional Commerce preset and custom themes render correctly.
- [ ] Reapply-preset behavior is intentional and warns about unsaved changes where required.
- [ ] Logo/media uploads use the QAS media root.
- [ ] Homepage visibility toggles behave correctly.
- [ ] Semantic success/warning/danger colors remain readable.

### Analytics
- [ ] Overview / reports / insights navigation is clear and not excessively long.
- [ ] KPIs/charts use real persisted data and correct date/range filters.
- [ ] Arabic labels and chart/container direction are readable.
- [ ] No obvious slow query or excessive-load behavior appears with realistic data.

## 5. Growth Workspace V2.4

- [ ] Overview / Content / Operations / Insights are clearly separated.
- [ ] Safe toggles/mutations update without full-page reload.
- [ ] Run Engine and demo seed/clear keep their intentional broader refresh behavior.
- [ ] Campaigns / Rules / Templates / Segments / Experiments use independent pagination.
- [ ] Deliveries/logs remain bounded and navigable.
- [ ] Overview health counts are correct.
- [ ] Rule-to-campaign linkage is valid and invalid orphan states are surfaced.
- [ ] Raw trigger/channel/type values are presented with correct EN/AR labels.
- [ ] RTL switches and action rows remain aligned.
- [ ] Measure representative response time and query behavior on QAS with non-trivial data.

## 6. Orders, payments, refunds, returns and receipts

### Orders
- [ ] Order queues/search/filter/sort/pagination work without losing context.
- [ ] Customer ownership and staff `orders.view` boundaries are enforced.
- [ ] Cancellation is replay-safe and does not double-restock or double-notify.

### Payments
- [ ] Safe manual transition allow-list is enforced.
- [ ] Paid/refunded terminal states cannot be incorrectly downgraded.
- [ ] Callback replay is idempotent.
- [ ] Late failure cannot overwrite a paid state.
- [ ] HMAC/reconciliation evidence appears only to authorized staff.

### Refunds / Returns RMA
- [ ] Refund amount never exceeds remaining refundable amount.
- [ ] Return quantities never exceed remaining returnable quantities.
- [ ] Refund-resolution amount is bounded by received refundable items.
- [ ] Repeated/replayed actions do not duplicate stock or money effects.
- [ ] Customer and Admin timelines/statuses remain consistent.

### Online order receipt
- [ ] Customer can print only owned orders.
- [ ] Authorized staff can print through the Admin order path.
- [ ] Currency, line totals, captured payments, refunds and delivery snapshot are correct.
- [ ] EN/AR print layout is readable.
- [ ] Non-tax/non-fiscal disclaimer is present until tax/fiscal policy is implemented.

## 7. Shipping, delivery and inventory

### Shipping
- [ ] Standard / Express / Store Pickup stable method codes behave correctly.
- [ ] Zone/city matching and free-shipping threshold basis are correct.
- [ ] Checkout quote updates correctly and order snapshot remains immutable.
- [ ] ETA presentation is bilingual and responsive.

### Deliveries
- [ ] Transition matrix blocks invalid status jumps.
- [ ] `shipped_at` and `delivered_at` timestamps are set only by lifecycle events.
- [ ] Metadata-only updates do not duplicate customer notifications.
- [ ] Store-pickup flow differs correctly from shipping flow.

### Purchases / receiving
- [ ] Ordered purchase can be received exactly once.
- [ ] Replay of receive does not add stock twice.
- [ ] Product/variant mismatch blocks all stock mutation.
- [ ] Barcode receiving requires every ordered unit before verified completion.
- [ ] Duplicate-line barcode disambiguation and undo-one work correctly.
- [ ] Inventory movements reference the correct purchase/item.

### Inventory
- [ ] Counted-stock adjustment detects stale counts.
- [ ] Simple and variant stock remain distinct.
- [ ] Scan-to-find targets the exact item.
- [ ] Barcode label print/rescan works with a representative physical scanner/printer when available.

## 8. Online-payment stock reservation

- [ ] Reservation is created atomically for online-payment checkout.
- [ ] Successful payment commits the reservation without decrementing stock a second time.
- [ ] Failure/cancel/expiry releases stock exactly once.
- [ ] Retry re-reserves only when stock is available.
- [ ] Late-paid/no-stock exception is visible and auditable.
- [ ] Scheduler expiry behavior is verified against realistic timestamps.

## 9. Customer storefront and account

- [ ] Catalog search/category browsing is responsive and live where designed.
- [ ] Quick View still works after live result refresh.
- [ ] Cart quantities/totals behave correctly and preserve context.
- [ ] Product-card/Product-detail Add to Cart updates the header cart count without reload; Buy Now still navigates to Checkout.
- [ ] Checkout uses honest shipping totals and recorded currency.
- [ ] My Orders pagination and order detail are bilingual/responsive.
- [ ] Notifications pagination/read actions behave correctly.
- [ ] Account navigation exposes Orders, Notifications, Support and available profile/address functions consistently.
- [ ] Password reset/confirmation/email verification use storefront UI rather than default Laravel presentation.

## 10. POS / cashier

- [ ] Cashier access boundary is enforced.
- [ ] Barcode add-product and quantity changes are fast and preserve context.
- [ ] Held carts remain cashier-owned and do not mutate stock/order/payment before checkout.
- [ ] Multi-tab resume locking prevents conflicting active carts.
- [ ] Customer attach/detach respects customer lookup privacy.
- [ ] Sale Summary is read-only after completion.
- [ ] Receipt formats 58 mm / 80 mm / A4 render correctly.
- [ ] Cash received/change and recorded payment data are accurate.

## 11. Workforce

### Employees / attendance
- [ ] Employee directory permissions and live filters work.
- [ ] Clock-in/out guards prevent invalid duplicate/open states.
- [ ] Breaks and effective-vs-recorded attendance are correct.
- [ ] Attendance corrections follow Pending / Approved / Rejected rules and are one-time reviewed.

### Scheduling
- [ ] Draft / Published / Cancelled visibility rules are correct.
- [ ] Overlapping shifts are blocked transactionally.
- [ ] Employee My Schedule shows only published shifts.
- [ ] Schedule-vs-actual comparison is accurate.

### Leave
- [ ] Leave type configuration and yearly balances are correct.
- [ ] Entitlement / Adjustments / Used / Pending / Available / Projected math is correct.
- [ ] Overlapping requests and cross-year V1 requests are rejected.
- [ ] Approved leave and work-shift conflicts are protected in both directions.
- [ ] Employee cancellation/reviewer decisions preserve audit history.

### Payroll
- [ ] Compensation profiles and period overlap rules are enforced.
- [ ] Open attendance/breaks and pending corrections/leave block affected employees.
- [ ] Salary/hourly calculations and adjustments match the implemented V1 rules.
- [ ] Approved/Paid runs are immutable according to policy.
- [ ] Payslip ownership and mixed-currency presentation are correct.

## 12. Customer Support / Helpdesk V2.1

- [ ] Customer can create and view only their own cases.
- [ ] Customer cannot link another customer's order.
- [ ] Staff accounts cannot be selected as support customers.
- [ ] Internal notes never appear in the customer timeline.
- [ ] First-response time starts only on first customer-visible staff reply.
- [ ] SLA targets/breach indicators match configured priority rules.
- [ ] Reply templates prefill but do not bypass permissions, visibility or audit.
- [ ] Customer/order/delivery/payment/return context appears only when the viewer has the corresponding permission.
- [ ] Case search/filter/pagination and owner/status/priority queues work in EN/AR.
- [ ] Closed/reopen lifecycle follows the implemented rules.

## 13. Customer Account Statement V1

- [ ] Date/type filters are correct.
- [ ] Entries come only from canonical orders, captured payments, refunds and returns.
- [ ] Multi-currency totals remain separated; no fake FX conversion.
- [ ] No running balance is shown without a real financial ledger.
- [ ] Source drill-through respects permissions.
- [ ] Print and UTF-8 CSV export are correct in EN/AR content.

## 14. Connected Identity foundation

- [ ] Provider identity uniqueness/collision rules remain enforced.
- [ ] Verified provider email is required for linking.
- [ ] Existing email accounts are not silently merged.
- [ ] Existing password login remains fully usable.
- [ ] Real Google/Facebook OAuth is not enabled until Socialite is added with a legitimate Composer lock update and provider configuration/recovery rules are tested.

## 15. Final acceptance gate

- [ ] No Sev-1/Sev-2 correctness, security, money, stock, authorization or data-isolation defect remains open.
- [ ] No critical EN/AR/RTL/mobile blocker remains open.
- [ ] Re-run automated branch-head CI after the final defect-fix batch.
- [ ] Record final QAS SHA and evidence.
- [ ] Re-run critical money/stock/permission smoke flows.
- [ ] Review environment/provider/infrastructure release blockers.
- [ ] Prepare exact-commit Production deploy and rollback only after explicit release approval.
