# Flowra — C1 Core Commerce Acceptance Pack

Date: 2026-10-17 Cairo | Source baseline `c5fb8c626c5a12290f9f2822a97fd789b5d0ab1f` | branch `sec03-framework-upgrade`

## Decision and verified baseline

- CI [38985883662](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38985883662) was directly inspected: **completed / success**, exact head `c5fb8c62`.
- This is the concrete execution pack for C1 in the existing Phase 1 register, not another general roadmap. Source assertions and side effects were read; **no new application tests, browser journeys, QAS writes, login submits or provider calls were executed**.
- QAS application `a682074f` remains prior operator-deployment evidence. Its running revision, effective settings, fixtures and current transport permissions must be verified at preflight. The current review does not grant access or override the earlier SSH restriction.
- Status: **C1-A test design and arithmetic READY; QAS execution BLOCKED BY CURRENT NO-WRITE SCOPE and unverified runtime preconditions. C1 overall OPEN.**

## C1-A — first executable boundary

Authenticated customer isolation, cart quantity/variant validation, COD + pickup checkout, order/receipt consistency, duplicate submission, cancellation and exact stock restoration. No real money, Paymob, paid/refunded status, delivery completion, POS, Workforce, schema, application-code or global settings changes.

Coverage target: EN desktop 1280×800; EN mobile 390×844; AR desktop 1280×800; AR mobile 390×844. Use fresh isolated browser contexts for customers A/B; locale switches confined to those contexts. Each result records its locale/viewport separately. A passing subset does not stand in for the other cells.

Maximum successful orders: **12** (three arithmetic scenarios × four locale/viewport cells). Duplicate/negative submissions must not increase this cap. Never continue creating orders after an unexpected count, amount or stock mismatch. Two synthetic customer accounts only; use an existing explicitly approved admin/operator identity for fixture administration, with no role changes.

## Preflight — must pass before creating any fixture/order

| Gate | Required evidence | If absent |
|---|---|---|
| A0 Environment | Exact QAS host, running app/environment and data identity through an available authorized channel; no Production | BLOCKED; no guessed identity or SSH workaround |
| A1 Accounts/access | Permitted admin session and isolated synthetic customer creation/login; evidence access for exact order/payment/stock values | BLOCKED; never reuse a real customer |
| A2 Revision | Document app revision and match reviewed code; resolve code differences before execution | BLOCKED or reassess impacted source |
| A3 Store contract | COD and pickup already enabled; pickup amount 0; customer cancellation enabled; currency EGP; no applicable discounts for fixtures | BLOCKED; do not toggle shared settings to force a pass |
| A4 External delivery | Prove checkout/cancel notification paths cannot reach email/WhatsApp/SMS recipients or external automations under current configuration, using read-only config/channel evidence; otherwise an already-approved isolated sink | BLOCKED before order; browser request interception does not stop server-side jobs |
| A5 Side effects | Owner authorizes linked in-app notifications to customer and admin accounts, activity/behavior/stock/payment rows and test-order accounting footprint | BLOCKED; do not claim “only three product rows change” |
| A6 Fixtures | UUID-tagged records, exact-ID manifest, active relevant stock baseline and no existing object reuse; approved setup route | BLOCKED; no broad seed/reset |
| A7 Reconciliation | Authorized read-only evidence sufficient to confirm exact linked orders/payment rows/stock movements and after-cancel stock | If only screenshots are available, do not mark backend reconciliation PASS |
| A8 Runtime guards | New CI completed/success, explicit scope authorization, bounded request allowlist and failure stop | No mutation until all applicable gates are met |

No global mail/WhatsApp/promotion/shipping/payment settings will be changed under C1-A. If isolation is missing, propose a separate precise configuration change for review; do not disable queues or silently redirect notifications. No intentional external message is authorized by this pack.

## Proposed fixtures — not created

Use one run prefix `QA-C1-<UTC timestamp>-<random suffix>`; derive names/SKUs/slugs from it. Exact IDs and baseline snapshots stay in a private execution manifest; no passwords/tokens in Git or screenshots. Proposed contacts use `.invalid` placeholder domains; they are not proof that delivery is isolated. Do not invent a routable telephone number; use an approved non-delivery fixture value only after A4 is proven.

| Alias | Proposed record | Baseline / purpose |
|---|---|---|
| A / B | Two ordinary synthetic customer accounts, no staff permissions | Own vs other-customer isolation; separate sessions |
| C | One synthetic category | Groups test products for later safe hiding |
| S | Simple product, EGP 50.00, active, quantity 5, no sale price | Standard order, quantity validation, cancellation restore |
| V | Variant product parent; two variants | Active variant V1: EGP 75.25, quantity 4; inactive V2: EGP 80.00, quantity 0; validates selection and rejection |
| P | Simple decimal product, EGP 0.10, active, quantity 5 | Exact-cent calculation |
| Addresses | At most one synthetic saved address per account | Owned address access; changing address must not rewrite persisted order snapshot |

Cap: 2 customers, 1 category, 3 products, 2 variants, 2 addresses, 12 successful unpaid COD orders and their natural linked records. No coupons, promotions, new roles, shipping methods, gateways or new custom implementation in this batch. If source/browser validation forbids a proposed fixture, record setup BLOCKED; do not bypass validation with direct SQL.

Record product/variant stock as exposed by the supported model; do not assume the parent aggregate equals variant quantity. Natural inventory lots/movements created by normal setup must be included in the manifest and reconciliation; do not insert, reset or delete lots ad hoc.

## Independent money/stock expectations

All figures are decimal EGP, not binary floating-point approximations. Preconditions: no applicable coupon/promotion, pickup shipping 0.00, current CartService tax 0.00. If preflight finds different behavior/configuration, stop and reconcile the expectation instead of changing settings or labelling a legitimate discount a bug.

| Scenario | Cart contents | Subtotal | Discount | Shipping | Tax | Grand total / payment amount | Stock after COD | Stock after cancellation |
|---|---|---:|---:|---:|---:|---:|---|---|
| M1 | S × 2 | 100.00 | 0.00 | 0.00 | 0.00 | 100.00 | S: 5 → 3 | S: 3 → 5 |
| M2 | V1 × 2 | 150.50 | 0.00 | 0.00 | 0.00 | 150.50 | V1: 4 → 2 | V1: 2 → 4 |
| M3 | P × 3 | 0.30 | 0.00 | 0.00 | 0.00 | 0.30 | P: 5 → 2 | P: 2 → 5 |

Restore each case through the supported cancellation flow before reusing that fixture for the next cell. After every successful checkout: exactly one new order and one linked payment, cleared owned cart, line_total = scenario subtotal, currency EGP. Order status pending; order.payment_status **unpaid**; payment.status **pending**; delivery status pending. Do not confuse order and payment status fields.

After cancellation: order and delivery cancelled; order.payment_status stays **unpaid** for COD; pending payment row becomes **failed** under the current cancellation implementation. The payment record is retained, with no captured money/refund. Product/variant stock returns to its exact baseline. If a repeat cancel is accepted idempotently or rejected by the UI/controller, the invariant is the same: no second restock or extra money movement. Do not require a specific response code without checking that endpoint's contract.

Expected maximum requested merchandise value across all 12 cancelled orders is 4 × (100.00 + 150.50 + 0.30) = **1,003.20 EGP**, with **0.00 captured money and 0.00 refunds**. Orders remain marked test/cancelled in the evidence record; pending/failed ledger amounts do not represent collected revenue.

## C1-A case matrix

All cases start **NOT RUN**. Backend proof may use authorized application detail/audit views or a permitted read-only backend channel; no bypass when one is unavailable.

| ID | Action and persona | Expected outcome | Existing source evidence / remaining gap |
|---|---|---|---|
| A01 | Guest → login form; invalid credentials then A login/logout | Localized feedback; only A session authenticated; invalid attempt creates no order/cart fixture unexpectedly | Auth controllers/traits; browser acceptance unproven; no password guessing or brute force |
| A02 | A/B separate sessions, addresses and account | B cannot see A address; foreign edit/read/write denied without changing it | CustomerAccountTest::test_addresses_are_private_and_defaults_fall_back_after_deletion |
| A03 | A adds S, changes quantity, removes it | Exact cart totals, clear feedback; stock unchanged before checkout; removal affects only A | StorefrontCartLiveUpdateTest + CartService; actual browser pending |
| A04 | A attempts quantity greater than available | Validation; no extra cart quantity/order/stock decrement | CartCheckoutClosureTest::test_cart_quantity_update_rejects_item_that_became_out_of_stock (related assertion, not identical overquantity scenario) |
| A05 | A selects V1 then attempts inactive V2 by direct fixture route | V1 price/quantity correct; V2 rejected with no stock/order side effect | CartCheckoutClosureTest::test_disabled_variant_cannot_be_added_to_cart_through_direct_route |
| A06 | Empty cart GET checkout | Returns to cart with EN/AR feedback; zero orders/payments | StorefrontCheckoutBilingualFeedbackTest::test_empty_cart_redirect_uses_localized_arabic_feedback |
| A07 | Nonempty cart, missing required checkout field | Validation, retained useful inputs, no order/payment/stock mutation | CheckoutController::store validation; no claim that string-contract tests prove browser behavior |
| A08 | M1 checkout COD/pickup | Exact 100.00 and 5→3; one order/payment; clear cart | CheckoutIdempotencyTest::test_repeated_checkout_from_same_cart_creates_only_one_order + CheckoutService |
| A09 | B reads A fixture order/receipt and attempts cancel | 404 ownership guards; A order and stock unchanged | CheckoutController::showOrder/receipt/cancelOrder use owner check; test references in detailed inventory |
| A10 | A order success/detail/receipt | Same number, items, EGP totals, unpaid semantics; EN/AR desktop/mobile readable | OnlineOrderReceiptTest; actual render/interaction pending |
| A11 | Replay same checkout once after successful M1 | No second order/payment/stock decrement; empty-cart feedback | CheckoutIdempotencyTest::test_repeated_checkout_from_same_cart_creates_only_one_order. Sequential replay is NOT concurrency proof |
| A12 | Cancel M1 then repeat cancellation once | Stock exactly 5, no duplicate restock; cancelled order, failed pending payment, unpaid order status | OrderActionService::cancel; source duplicate-cancel guard; live endpoint acceptance pending |
| A13 | M2 checkout + cancel | Exact 150.50, V1 stock 4→2→4; V2 unchanged | CheckoutService integer-cents path plus catalog/variant tests; browser/database evidence pending |
| A14 | M3 checkout + cancel | Exact 0.30, stock 5→2→5; no rounding drift | CheckoutIdempotencyTest::test_checkout_persists_exact_cent_cart_and_payment_totals |
| A15 | End-of-run reconciliation / close contexts / hide fixtures | At most 12 cancelled unpaid orders, exact stock baselines, empty test carts, no external dispatch; private manifest reconciled | Execution evidence required; no claim of automatic cleanup implementation |

Screenshots required per locale/viewport: cart state, invalid feedback, checkout quote, success/order detail, receipt, cancellation outcome. Each case records expected/actual, source/QAS SHA, persona alias, IDs privately, URL/method, timestamp, evidence link and any defect. Never store credentials, browser storage or raw customer/admin information in public screenshots.

## Source-side effects found before execution

1. CheckoutService::place writes order/items, stock/lot movement or reservations, payment, profit snapshots, coupon usage if present, cart clearing and in-app notifications. Current OrderPlacedNotification::via returns **database only**, but CheckoutService also iterates **all role_as=1 admin accounts** for order notifications. These linked rows need explicit scope acknowledgment.
2. WhatsAppManager::queueMessage returns early only when WhatsApp or the feature is disabled; otherwise it dispatches after commit, queued or synchronous. Blocking outbound browser requests is insufficient. Current QAS configuration was not inspected.
3. OrderActionService::cancel invokes cancellation and delivery notification paths. OrderNotificationService chooses configured database/email/WhatsApp channels and can invoke automation on dispatch outcomes. Read-only isolation evidence must include cancellation, not just order placement.
4. CheckoutController GET index calls BehaviorTrackingService::track; successful submit tracks order completion. Behavior/analytics records are expected incidental writes if enabled. A fake email address is not a general isolation mechanism.
5. COD creation does not collect payment. Do not mark paid/completed/delivered, refund, or use Paymob redirect/callback during C1-A.

These are observed execution boundaries, not newly reproduced bugs. No source change was made.

## Fixture retention and reconciliation

- Maintain a private exact-ID manifest of created users/category/products/variants/addresses/carts/orders/items/payments/stock movements/lots/notifications/logs/behavior records that can be attributed to the run. Include initial and final stock and cancelled order IDs.
- Cancel each synthetic order through the permitted normal flow; validate restoration before the next case. If cancellation is unavailable or fails, STOP and report; no direct stock patch or database reset to make results look clean.
- After proof, remove owned test cart/address data through supported actions where permitted and hide/deactivate only the run's synthetic products/category. Retain cancelled orders and linked audit/payment/stock history as evidence. Accounts can remain labelled test if no supported safe archive exists.
- Do not hard-delete order/payment/history, edit real admin notification preferences, delete existing queues/logs, or run broad cleanup by name prefix alone. Existing unrelated data remains untouched. In-app notifications created for admins are retained and identified by synthetic order ID, not silently deleted.
- Cleanup therefore means controlled isolation and reconciliation, **not zero residual rows**. No cleanup code or migration is requested or implemented in this slice.

## C1 remaining acceptance after A — not silently deferred to Phase 2

| Boundary | Required remaining proof | Scope decision |
|---|---|---|
| C1-B Accounts/offers/shipping | Register/recovery/email verification wiring/session expiry; eligible/ineligible coupon, stacking/caps; paid shipping/threshold/city failures; bundle and stale-price behavior | Still Phase1. Needs test notification sink/approved fixtures and no invented business policy |
| C1-C Payment/provider | Paymob paid/failed/duplicate/late/retry/provider reconciliation; online reservations and release | PAY-01 remains OPEN; separate explicit sandbox/provider/amount authorization; never capture real funds under C1-A |
| C1-D Fulfillment/returns/integrity | Delivered COD settlement; partial/full refund/RMA; POS/RMA mutual reservation; FEFO provenance/expiry/last-unit simultaneous checkout | Still Phase1. Needs larger fixture scope and permitted runtime concurrency/data evidence |
| C1-E Page/security/performance | Remaining content/reviews/catalog editor, keyboard/zoom, loading/error, data sizes, role mutation denials | Existing page DoD remains binding; do not infer full acceptance from A |

Existing FEFO test provides a concrete later fixture: lots 2 at cost 10 and 3 at cost 30; sale quantity 3 at price 50 consumes 2+1, expected COGS 50, line profit 100, total stock 5→2; cancellation restores exact lots 2/3. Source method: CheckoutIdempotencyTest::test_checkout_allocates_fefo_lots_and_cancellation_restores_the_same_lots. It is NOT part of the first three-product C1-A setup.

## Concrete authorization request for the next turn

The earlier explicit no-write instruction is the reason permission is required. Approval of this bounded C1-A pack would cover: verifying permitted QAS identity/settings read-only; creating only the stated synthetic fixtures via supported existing actions; synthetic customer and approved admin login; isolated EN/AR sessions; cart/address actions; at most 12 unpaid COD/pickup orders; foreign synthetic-ID negative tests; cancellation and controlled fixture hiding/reconciliation; and the documented linked in-app admin notifications/audit/behavior rows.

It would **not** authorize application code, schema, shared configuration, permission changes, blocked SSH or alternate bypasses, Production, real payments/refunds, external messages, Paymob, POS, Workforce, or deleting real data. If external-delivery isolation, COD/pickup/cancellation configuration, approved operator access or evidence channel is not already suitable, execution stops before writes. No credentials requested in chat.

Next after approval: verify this pack commit's CI; run the preflight once; execute eligible C1-A cases, record PASS/FAIL/BLOCKED individually, fix nothing without a new source-change scope, and save actual evidence. **Do not produce another generic plan.**
