# CURRENT PHASE

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

### Current objective
Deploy the latest `v42-clean-baseline` to QAS and run one consolidated authenticated acceptance pass instead of continuing to defer manual verification.

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
- QAS may be updated from `v42-clean-baseline`.
- Production stays unchanged until consolidated QAS acceptance and remaining operational release gates are explicitly cleared.

### Reference
See `docs/PROJECT_CHECKPOINT_2026-09-25.md` for the detailed checkpoint and remaining roadmap.
