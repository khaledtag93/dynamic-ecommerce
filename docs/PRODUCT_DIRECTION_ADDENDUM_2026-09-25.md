# Dynamic Product Direction Addendum — 2026-09-25

## Product vision

Dynamic is being built as a configurable, production-grade commerce and retail platform rather than a single-store website. The long-term goal is one strong core platform that can be adapted to many business models by enabling, hiding or configuring modules and workflows instead of forking the product for every customer.

The platform should feel easy, modern, fast and commercially credible to both merchants and customers. Existing screens or flows may be redesigned or replaced whenever a better product solution exists.

## Core experience rules

- Readability, simplicity, consistency and friendly language are default UX requirements.
- Avoid long crowded Admin screens; split or progressively disclose complex workspaces.
- Arabic, English and RTL quality must be closed while each workspace is being improved.
- Prefer immediate/live interactions for safe searches, filters, quantities, totals and other lightweight actions.
- Avoid unnecessary full-page reloads and scroll-to-top jumps across Admin, Customer and POS.
- Standardize modern non-disruptive feedback: field-level validation, toasts for small successes, clear warnings, retryable errors and confirmations only when the action warrants them.
- Keep important actions available on long pages with sticky action areas where appropriate.
- Sidebar, top toolbar/header, mobile navigation and scrolling behavior are first-class product surfaces and must remain consistent in EN/AR desktop/mobile.
- Remove demo-like, developer-facing or contextually irrelevant copy from real user surfaces.

## In-product Help system

Complex Admin pages should expose a small Help control that opens a polished explanation of:
- what the page is for,
- what each section or setting means,
- what the important validation/business rules are,
- what benefit the feature provides,
- simple practical examples where useful.

Growth Workspace V2 introduces the first reusable Admin page-help component. Future Admin redesigns should reuse and extend the same pattern rather than creating unrelated help UI.

## Customer experience direction

Customer UI/UX needs a dedicated modernization pass. Actions such as cart quantity changes should update totals immediately without a separate update button when server safety allows it. Remove content that takes space without helping the current task. Storefront interactions should be fast, obvious, responsive and bilingual.

## Configurability

Dynamic should support platform-owner and merchant/admin control over which modules, navigation items, sections and optional business features are enabled or visible. Vertical-specific needs should preferably be implemented as configurable capabilities or feature flags, not hard-coded assumptions.

## Candidate target verticals

The same platform should be adaptable to:
- Fashion, clothing and footwear
- Grocery, supermarkets and convenience stores
- Electronics, mobile phones and accessories
- Cosmetics and perfume
- Furniture and home decor
- Books and stationery
- Toys and gifts
- Pet stores
- Auto parts
- Sports and fitness retail
- Hardware and building supplies
- Jewelry and accessories
- Specialty food
- Wholesale and distribution
- Multi-branch retail and small department stores
- Cafes, bakeries and restaurants with adapted ordering flows
- Service businesses that also sell physical products

Examples of capability differences:
- Fashion: size/color variants and visual merchandising.
- Grocery: fast barcode POS, inventory speed and potentially weighted products.
- Wholesale: quantity tiers, customer price levels and bulk workflows.
- Food service: later table/order/kitchen capabilities if that vertical is intentionally entered.

## Migration and onboarding

Legacy-business onboarding is a commercial requirement. A future Dynamic Migration Center should support:
- CSV and Excel import,
- field mapping,
- preview before commit,
- validation and clear row-level errors,
- duplicate handling,
- dry-run/test import,
- batch processing,
- resumability,
- import history and audit logs,
- safe rollback strategy where technically possible,
- product/category/customer/inventory migration,
- image mapping,
- larger-customer API or custom adapters when justified.

A business with thousands of existing products or customers should not have to re-enter data manually to adopt Dynamic.

## Scalability rule

Do not guess capacity from disk size or one successful deployment. Before making merchant-count, traffic or data-volume claims, gather and measure CPU, RAM, PHP-FPM workers, MySQL configuration, database size/indexes, slow queries, storage IOPS, bandwidth, cache/Redis availability, queues, media growth and backup constraints. Use staged load testing to establish real thresholds.

Infrastructure should scale with real demand: optimize application/database behavior first, then add cache, CDN/object storage, dedicated database resources, queue workers, additional app servers or load balancing when evidence justifies them.

## Definition of done for meaningful features

A feature is not finished only because it works. Review:
1. Is it easy to understand?
2. Is it visually clear and consistent?
3. Is it fast and low-friction?
4. Does it feel production-grade?
5. Is Arabic/English/RTL correct?
6. Is mobile behavior correct?
7. Are authorization, validation and security correct?
8. Does it hold up with real business data and realistic load?

New product ideas, UX findings, security gaps, validations, infrastructure concerns and commercial opportunities should be added to the roadmap automatically, then placed in the correct dependency order instead of interrupting the current slice unless they are blockers or regressions.
