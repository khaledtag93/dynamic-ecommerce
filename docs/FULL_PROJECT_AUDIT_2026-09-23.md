# Dynamic E-commerce System — Full Project Audit
Date: 2026-09-23
Branch audited: `v42-clean-baseline`
Scope: architecture, storefront, catalog, checkout, orders, payments, inventory, admin, authorization, analytics/growth, bilingual support, SEO, performance, testing, operations, and commercial readiness.

**Current-state note (2026-09-23):** This audit records the inspected baseline. The first implementation batch on `v42-clean-baseline` has closed the customer-promotion path that created a roleless admin and removed fabricated product-page social proof in source. CI/QAS/Production verification is recorded separately in the [documentation guide](README.md) and [master status](../PROJECT_MASTER_STATUS.md). The legacy Super Admin fallback and real reviews remain open.

Screen-by-screen admin/storefront evidence, live Production observations, additional trust and demo-data findings, and measurable UX gates are in [UI/UX and commercial-readiness review](UI_UX_AND_COMMERCIAL_READINESS_REVIEW_2026-09-23.md). This companion narrows the broad UX recommendations here into reviewable work batches.

## Executive summary

Dynamic already has a broad commerce and operations foundation: catalog/products/variants, cart, checkout, orders, coupons/promotions, payments, refunds, inventory movements, suppliers/purchases, roles/permissions, analytics/growth, notifications, WhatsApp foundations, bilingual support, branding/content controls, cost calculator, CI, QAS, and safe Production deployment/rollback tooling.

The current priority should not be adding more advanced modules. The highest-value work is to close customer-facing integrity gaps, finish incomplete commerce workflows, simplify the architecture, expand regression coverage, and move the framework/runtime onto a supported security baseline.

## Repository snapshot

- ~1562 tracked files.
- ~40 controllers.
- ~54 models.
- ~53 services.
- ~65 migrations.
- ~132 Blade views.
- ~17 PHP test files including framework/example files.
- Only one dedicated Form Request currently exists.
- No application Policies, domain Events, or Listeners are currently present.
- `public/` contains roughly 44 MB of tracked assets and vendor/static files.

## P0 — release / trust / security blockers

### 1. Implicit legacy Super Admin escalation still exists

`App\Models\User::isSuperAdmin()` currently treats a legacy admin (`role_as=1`) with no attached role as Super Admin.

At the audited baseline, `Admin\CustomerController::updateRole()` could promote a normal customer to `role_as=1` without assigning a staff role. The first source batch requires a limited staff role and changes both fields transactionally; existing roleless admin accounts are unchanged.

This conflicts with the product rule that one explicit owner is Super Admin while other staff must receive limited explicit roles.

Required:
- remove the implicit no-role Super Admin fallback after a safe migration/bootstrap;
- attach the explicit `super_admin` role to the owner;
- require a staff role when promoting an account to admin;
- protect ownership transfer separately;
- add regression tests for promotion, owner uniqueness, legacy fallback removal, and role removal.

### 2. Fabricated storefront social proof

At the audited baseline, the product page generated customer-facing social-proof values from the product ID:
- synthetic average rating;
- synthetic review count;
- synthetic sold count;
- synthetic current viewers;
- synthetic wishlist/save count;
- three mock customer reviews.

The first source batch removes those fabricated blocks; it is not yet deployed. A real `ProductReview` model/table exists, but there is no complete customer review submission/moderation/display workflow.

Required:
- remove fake production-facing social proof;
- use real order/review/analytics data only;
- implement verified-purchase review submission;
- add admin moderation/status;
- calculate rating/count from approved real reviews;
- display sold/viewing/save counts only when backed by real trustworthy data.

### 3. Historical credential rotation is not documented as closed

The repository history previously contained a tracked `.env`. The V42 branch no longer tracks secrets, but final rotation of all historically exposed production credentials remains a mandatory closure item unless separately verified.

Required credential inventory:
- production DB credentials;
- APP_KEY if rotation is safe/planned;
- SMTP;
- Paymob;
- WhatsApp / Meta;
- deploy shared secrets;
- any historical SSH/provider credentials.

### 4. Laravel 10 is out of security support

Current Composer baseline is Laravel 10. Laravel 10 security fixes ended in February 2025. A supported Laravel major version upgrade must be planned and executed on a dedicated branch with full regression testing before Production promotion.

## P1 — core commercial functionality to close

### 5. Global header search appears non-functional

The desktop/mobile header search submits `GET /` with query parameter `q`.

`FrontendController::index()` does not consume the search query. Search currently exists only inside category listing filters.

Required:
- create a real storefront search results route/page;
- search translated product name/description, SKU, brand, category, and optionally attributes;
- paginate results;
- add sorting/filtering;
- handle no-results and typo suggestions later;
- keep query URLs SEO-safe.

### 6. Shipping engine is not implemented

`CartService::summary()` currently sets shipping to `0.00`.

Orders expose Standard Shipping, Express Shipping, and Store Pickup, but changing the method does not currently calculate a shipping fee from method/location/order value.

Required:
- shipping methods table/config;
- enabled/disabled methods;
- zone/city/governorate rates;
- free-shipping threshold;
- pickup rules;
- optional weight/value rules;
- checkout recalculation;
- snapshot selected shipping rule/fee onto the order.

### 7. Tax engine is not implemented

`CartService::summary()` currently sets tax to `0.00`.

Required:
- configurable tax/VAT behavior;
- inclusive/exclusive pricing decision;
- taxable subtotal rules;
- order tax snapshots;
- tax display on cart/checkout/order/invoice.

Tax/legal configuration should be validated for the actual selling jurisdiction before activation.

### 8. Online-payment failure / stale order inventory policy is incomplete

Inventory is decremented when an order is placed, before an online payment is confirmed.

When Paymob reports a failed payment, the payment/order payment status becomes failed, but inventory is restored only when the order itself is cancelled.

Required product rule:
- define reservation lifetime for online payments;
- automatically cancel/release stock after failed/expired unpaid orders, or introduce explicit stock reservations;
- prevent late successful callbacks from creating inconsistent stock/order states;
- add tests for failed, abandoned, delayed, duplicate, and late-success gateway paths.

### 9. Paymob commercial sign-off remains external/operational

Payment code has substantial hardening: mandatory HMAC, amount/currency/integration checks, row locking, terminal paid/refunded states, and duplicate/late callback defenses.

Still required:
- real account onboarding/verification completion;
- real sandbox/test E2E;
- successful payment;
- declined payment;
- delayed callback;
- duplicate callback;
- retry/refresh;
- timeout/abandonment;
- final Live credentials and callback URLs.

### 10. Return / RMA workflow is not complete

Orders have a delivery status called `returned`, and refund records exist, but there is no dedicated customer return/RMA workflow.

Required:
- customer return request;
- per-item quantities;
- reason/evidence;
- request status;
- admin approval/rejection;
- received/inspection condition;
- restock decision;
- refund/exchange linkage;
- audit history and notifications.

### 11. CSV import is currently a placeholder

The Import Jobs page explicitly creates drafts only and does not import data.

Required:
- real file upload;
- CSV validation/encoding handling;
- preview;
- mapping;
- dry-run;
- row-level errors;
- transactional/batched processing;
- resumability/retry;
- downloadable error report;
- product/customer import first;
- order import only if its business semantics are defined safely.

### 12. Real product reviews are unfinished

Foundation exists in `ProductReview`, but customer/admin routes and workflows are missing.

Required:
- review eligibility;
- verified purchase;
- one review per user/order item policy;
- rating/comment validation;
- moderation;
- edit/delete/report rules;
- aggregate rating caching or efficient query strategy.

### 13. Customer addresses are not reusable

Orders snapshot shipping/billing addresses, but no saved customer-address book exists.

Required:
- customer address CRUD;
- default shipping/billing;
- checkout prefill;
- address snapshot remains immutable on each order.

### 14. Currency is hardcoded to EGP in checkout

This is acceptable for the current Egypt store but conflicts with the long-term generic/reusable product goal.

Required eventually:
- store default currency setting;
- currency formatting helper;
- payment-gateway currency compatibility validation;
- postpone true multi-currency until needed.

## P1 — quality / engineering closure

### 15. Automated test coverage is small relative to system size

CI is useful and currently covers:
- Composer install/validation;
- PHP syntax;
- Bash syntax;
- clean MySQL migration;
- Laravel boot/routes;
- config/view compile;
- PHPUnit;
- frontend build.

But the application surface is much larger than the current targeted test suite.

Priority tests:
- authorization/owner/staff boundaries;
- search;
- checkout totals;
- shipping/tax;
- stock reservation/release;
- coupons/promotions and stacking;
- online-payment timeout/failure;
- cancellations/refunds/returns;
- real reviews;
- import;
- bilingual route/render smoke tests;
- customer ownership/object access.

Also add:
- `composer audit`;
- `npm audit` policy;
- static analysis such as PHPStan/Larastan;
- code style check;
- browser/E2E smoke tests;
- coverage reporting for critical domains;
- syntax validation for `deploy-prod.sh` and `rollback-prod.sh` in CI.

### 16. Source-control release state should be reconciled

`v42-clean-baseline` is currently far ahead of `main`, while V42 application code has already been deployed to Production through the exact-commit deployment workflow.

Required:
- after the current development baseline is approved, promote/reconcile the validated V42 history into `main`;
- make `main` match the canonical production release model defined in project rules;
- tag releases;
- do not confuse documentation-only branch heads with exact deployed application commits.

## P2 — bilingual / content consistency

### 17. Arabic translation audit is unfinished

`missing_ar.txt` currently contains 277 non-empty missing strings.

Required:
- translate all customer-facing strings first;
- then admin/operations strings;
- remove hardcoded messages from controllers/services where appropriate;
- add an automated translation-key parity check to CI;
- test RTL layouts at mobile/tablet/desktop sizes.

## P2 — architecture and maintainability

### 18. Several files have grown into very large modules

Examples:
- `resources/views/layouts/admin.blade.php` ~131 KB;
- admin dashboard ~82 KB;
- Livewire product form view ~81 KB;
- `GrowthCampaignService` ~79 KB;
- frontend product page ~60 KB;
- Livewire ProductForm PHP ~49 KB;
- NotificationCenterController ~46 KB;
- DashboardController ~44 KB;
- PaymobGatewayService ~44 KB.

Required incremental refactor:
- split Blade pages into partials/components;
- move inline CSS/JS into versioned frontend assets;
- split service use cases;
- keep controllers thin;
- extract query/report services;
- avoid one large settings service becoming the universal configuration object.

### 19. Validation is mostly inline

There are ~40 controllers but only one dedicated Form Request.

Required:
- add Form Requests for checkout, admin order/payment/delivery changes, promotions, settings, imports, roles, and content;
- centralize normalization and validation messages;
- improve testability.

### 20. Authorization is route/middleware-heavy and lacks Policies

Permission middleware is useful, but there are no model Policies.

Required where useful:
- Order policy;
- Product/admin policy;
- customer resource ownership policies;
- refund/payment sensitive actions;
- later API/mobile authorization.

### 21. Domain side effects are tightly coupled

There are no domain Events/Listeners currently. Notifications/analytics/messaging are frequently invoked directly from services/controllers.

Improvement:
- keep core financial/inventory transactions synchronous and explicit;
- emit after-commit domain events for non-critical side effects;
- queue email/WhatsApp/analytics where safe;
- prevent side effects from firing for rolled-back transactions.

## P2 — performance / operations

### 22. Tracked public assets are heavy

`public/` is roughly 44 MB and contains a large legacy/admin vendor asset surface, source maps, fonts, flags, and images.

Required:
- identify actual runtime-used assets;
- delete unused demos/vendor source maps/assets;
- build/minify first-party assets consistently;
- lazy-load images;
- convert/compress product and branding media;
- add sensible cache headers;
- audit Core Web Vitals on QAS.

### 23. Queue and scheduler runtime must be operationally verified

Scheduled commands exist for analytics, growth, and notification escalation. Queue configuration defaults to sync unless environment overrides it.

Required production verification:
- cron executes `schedule:run` every minute;
- queue worker/supervisor exists if async queues are enabled;
- failed jobs are monitored;
- retry rules are safe;
- growth/notification jobs cannot overlap or flood providers.

## P2 — SEO / discovery

### 24. SEO foundation is partial

The storefront layout has title and meta description, and products/categories have meta fields, but no complete SEO layer was found for:
- canonical URLs;
- OpenGraph/Twitter cards;
- JSON-LD Product/Offer/Breadcrumb;
- XML sitemap;
- robots/noindex strategy;
- hreflang/localized URL strategy.

Required before serious acquisition/marketing:
- add sitemap and robots controls;
- per-product/category canonical/meta;
- structured data;
- social sharing meta;
- noindex thin/filter/search permutations where appropriate.

## P2 — privacy / analytics

### 25. Analytics captures significant visitor metadata

Behavior/analytics storage includes session IDs, user IDs when logged in, URL/path, hashed IP, user agent, referrer, UTM data, and commerce behavior. The same high-level behavior can also be written to both `user_behaviors` and `analytics_events`.

Required:
- define retention policy;
- document analytics/cookie behavior in privacy policy;
- add consent controls where legally required;
- minimize duplicated data;
- aggregate/archive old raw events;
- make cleanup schedulable.

## P2 — product / conversion improvements

After P0/P1 closure:
- guest checkout;
- wishlist;
- product comparison;
- search autocomplete;
- richer filters/facets;
- real review photos/Q&A;
- saved addresses;
- configurable coupon/promotion stacking;
- abandoned-cart recovery based on real consent;
- invoice/receipt generation;
- delivery ETA and carrier links;
- customer account/profile improvements.

Already present and worth keeping:
- recently viewed;
- related/bundle/add-on products;
- behavior-driven recommendations;
- promotions/coupons;
- analytics/growth foundations;
- order/payment/refund hardening;
- product variants/attributes;
- bilingual product/category foundations.

## P3 — later expansion, not current priority

Do not prioritize these until the commercial core is stable:
- POS;
- barcode scanner workflow beyond storing barcode;
- multi-warehouse inventory;
- advanced carrier integrations;
- subscriptions/trials/renewals;
- full SaaS multi-tenancy;
- native mobile app;
- comprehensive public commerce API;
- multi-currency/multi-country;
- deeper ML/personalization.

## Naming / product clarity

`AIRecommendationEngine` currently behaves as an explainable weighted/rules-based recommendation engine using category/brand/session/popularity/relation signals. That is useful, but it is not an external ML model.

Recommendation:
- either rename it to Smart/Adaptive Recommendation Engine;
- or keep the AI label only if the product documentation clearly explains how recommendations are generated.

## Suggested execution phases

### Phase A — Trust & authorization
1. Fix owner/Super Admin model.
2. Remove fabricated social proof.
3. Close credential rotation evidence.
4. Add regression tests for these changes.

### Phase B — Commerce correctness
1. Real search.
2. Shipping rates/zones.
3. Tax configuration.
4. Online-payment stock expiry/release.
5. Paymob E2E.
6. Returns/RMA.
7. Real reviews.
8. Saved addresses.

### Phase C — Operational completeness
1. Real import pipeline.
2. Queue/scheduler production verification.
3. Invoice/receipt flow.
4. Translation closure.
5. SEO baseline.

### Phase D — Engineering modernization
1. Upgrade Laravel to a supported major.
2. Expand CI/tests/static analysis.
3. Refactor the largest controllers/services/views incrementally.
4. Asset/performance cleanup.
5. Add domain events/policies where they reduce coupling and authorization risk.

### Phase E — Conversion features
1. Guest checkout.
2. Wishlist.
3. Compare.
4. Search autocomplete/facets.
5. Review/Q&A enhancements.
6. Conversion experiments backed by real analytics.

### Phase F — platform expansion
POS, warehouses, subscriptions, SaaS, mobile/API, multi-country/currency.

## Definition of commercially ready

Dynamic should not be treated as commercially finished merely because the screens exist. A module is complete only when:
- database model is correct;
- backend business rules are implemented;
- authorization is correct;
- bilingual UI is complete;
- validation/error paths are handled;
- tests cover the critical rules;
- admin can operate it safely;
- customer flow is usable;
- logging/monitoring exists where necessary;
- deployment/migration/rollback impact is understood;
- no placeholder/demo/fabricated customer-facing data remains.

## Immediate next engineering target

Start with Phase A: owner/Super Admin authorization + storefront social-proof cleanup. These are high-value because they affect security and customer trust and can be closed without destabilizing the broader commerce architecture.
