# Dynamic — Product Position & Gap Analysis
Date: 2026-09-25

## Current position

Dynamic has moved beyond a conventional Laravel storefront into an integrated commerce-operations platform. The source code is materially ahead of QAS and Production, so the main risk is no longer weak foundations; it is consolidation, verification, and commercial release readiness.

Current checkpoints:
- Working branch: `v42-clean-baseline`
- Latest application checkpoint before Shift Scheduling work: `b0fc6d20`
- QAS verified application revision: `0a08253`
- Production: older than most current source work
- Latest workforce CI at this checkpoint: pending independent verification

## Current assessment

| Area | Current assessment |
| --- | ---: |
| Architecture / backend foundation | 8.5 / 10 |
| Business safety / financial integrity | 8.5 / 10 |
| Admin operational capability | 8.5 / 10 |
| Inventory / retail readiness | 8.5 / 10 |
| POS foundation | 8 / 10 |
| Workforce | 5 / 10 at Foundation V1 |
| Storefront customer experience | 7 / 10 |
| UI/UX consistency | 7 / 10 |
| Arabic / English / RTL | 7.5 / 10 |
| Automated test maturity | 7.5 / 10, with recent branch-head CI still pending |
| Deployment / operational readiness | 6.5 / 10 |
| Commercial launch readiness | 6–6.5 / 10 |
| Long-term product potential | 9 / 10 |

Overall product/source maturity: approximately 8 / 10.
Commercial launch readiness is intentionally lower because source development currently leads QAS/Production verification.

## Strongest areas

- Canonical Orders / Payments / Inventory ledgers and safer financial/stock mutation rules.
- Transaction locking, idempotency, refund/cancellation protection, and audit history.
- Procurement, barcode, receiving, inventory adjustment, SKU/barcode integrity, labels and scan-to-find.
- POS cashier foundation with scan/manual lookup, customer attach, discounts, hold/resume, returns, receipts, cash shifts and manager review.
- Admin live/no-reload read-side interaction standard across core operational lists.
- Customer account, addresses, orders and notifications.
- Workforce foundation with separate Employee Profile, attendance sessions, permissions and time clock.
- Roles/permissions, deployment/rollback foundations, bilingual support and structured project documentation.

## Main strategic gap

The source code is ahead of release verification.

A growing portion of the product is branch-only:
- live/no-reload closure;
- procurement/payment/promotion/admin improvements;
- workforce foundation and later slices;
- multiple UI/UX improvements.

This is acceptable during active development, but it means a consolidated CI/QAS integration gate must happen before the gap becomes too large.

## Remaining priority gaps

### P0 — commercial/release trust
- complete historical credential-rotation evidence;
- explicit Owner / Super Admin migration and retirement of roleless-admin fallback;
- production demo/placeholder/support-content cleanup;
- Paymob account-side readiness + full E2E flow;
- current branch-head CI verification;
- updated QAS deployment and consolidated authenticated EN/AR desktop/mobile regression;
- final controlled Production promotion / rollback verification.

### Workforce
Execution order:
1. Shift Scheduling V1.
2. Attendance rules, breaks, late/early/absence, corrections/approval.
3. Leave types, requests, balances, accrual/carry-over.
4. Payroll foundation, pay periods, salary/hourly inputs, overtime, allowances, bonuses, deductions, payroll audit and payslips.

### Commerce correctness
- real shipping engine: zones/cities/rates/free-shipping/pickup/ETA + order snapshots;
- configurable tax/VAT after merchant/legal decision;
- online unpaid-order stock reservation / expiry / release;
- full Returns/RMA workflow;
- verified reviews/moderation;
- functional CSV import pipeline;
- online order invoice / PDF-friendly document.

### POS next depth
- paid-in / paid-out cash drawer operations;
- optional staff PIN/session/location policy;
- schedule/attendance awareness after workforce rules are mature;
- deeper exchange/void/approval flows where needed;
- fiscal/tax invoice only after jurisdiction requirements are defined.

### Delivery
- zones/service levels;
- carrier/driver assignment;
- pick/pack/ready/dispatch milestones;
- delivery attempts/proof;
- failed delivery/reschedule/RTO;
- COD reconciliation;
- provider adapters.

### Customer conversion
- guest checkout;
- wishlist;
- compare where useful;
- stronger autocomplete/facets;
- tracking timeline;
- verified review media/Q&A;
- loyalty and abandoned-cart recovery only after reliable economics/consent.

### UI/UX consistency
- stronger reusable KPI/header cards;
- global switch alignment including RTL;
- Analytics & Insights redesign;
- section long pages consistently;
- final Branding/theme polish;
- remove remaining demo/internal copy;
- consolidated mobile + RTL pass.
- **Roles & Permissions redesign backlog:** the current page is excessively long, mixes English and Arabic in the same experience, and has weak information hierarchy/UI consistency. When its turn comes, split it into focused role management, staff assignment, custom-permission and permission-matrix workspaces/tabs; complete EN/AR parity and RTL; reduce repetitive permission cards; improve search/filtering and responsive behavior.
- **Global preserve-context interaction backlog:** suitable Admin and Customer actions must not cause avoidable full-page reloads or jump the user back to the top. POS add-product / quantity increment is a confirmed example. Extend the existing live/no-reload standard from read-side lists to safe server-confirmed inline mutations where appropriate, preserving scroll/focus/cart context and showing explicit loading/success/error feedback. Financial, destructive, permission and inventory-sensitive actions remain backend-authoritative and must never fake success.

### Engineering modernization
- Laravel upgrade on a dedicated branch;
- more Form Requests / Policies / domain events;
- static analysis and dependency/security auditing;
- browser/E2E smoke tests;
- split oversized controllers/views incrementally;
- image/performance/Core Web Vitals;
- defer multi-warehouse, SaaS, mobile app and advanced AI until core release is stable.

## Product direction decision

Dynamic does not currently need a rewrite. The core architecture is viable and extensible.

The biggest risk from this point is uncontrolled breadth: adding multi-warehouse, SaaS, mobile, advanced AI, loyalty and offline POS before closing core commerce, workforce and release verification would reduce focus.

Preferred sequence:
**Workforce completion → critical commerce gaps → consolidated CI/QAS gate → UI/UX final pass → Production readiness → controlled expansion.**

## Roadmap hygiene

Some older roadmap items now describe work already completed (for example global search, saved addresses, barcode/POS/receipt foundations and initial HR foundation).

Roadmap maintenance should progressively classify work as:
- Done / source complete;
- CI verified;
- QAS pending/verified;
- Production pending/verified;
- Next;
- Later.

This note is the current strategic reference for deciding what to build next and what not to expand prematurely.
