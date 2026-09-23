# Admin daily-work UX — QAS review

Source revision: `9fe1a96` on `v42-clean-baseline`. [Code CI run 35919171926](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/35919171926) passed 48 tests (208 assertions), clean MySQL migration, Blade compilation and frontend build.

Status: **QAS deployment and authenticated visual/task review pending**. Record the exact QAS application commit and reviewer evidence here before marking a scenario verified. Do not infer a Production rollout from a green source CI run.

| Screen / task | QAS acceptance check | Evidence / status |
| --- | --- | --- |
| Admin home | Four 30-day metrics display meaningful values. Gross order value includes all order statuses; paid order share counts paid orders divided by all orders in the window. Pending orders, low-stock products and failed payments lead to their relevant screens. Recent rows link to records. | Pending |
| Restricted staff home | Staff with only dashboard access sees no catalog, customer, promotion or order search results/links. Staff with operational access sees only permitted actions; direct route middleware still denies forbidden URLs. | Pending |
| Product editor | Switch among Details, Pricing, Variants, Related, SEO and Images. Edit fields, variants and image order; switch tabs repeatedly; save through both controls. Values and uploads stay intact. A failed save opens its error section and focuses the invalid field. | Pending |
| Brand & Identity | Change a theme, identity field, banner and image in separate sections; inspect Live preview; submit the single form. Validation returns to the relevant section without losing other values or selected file previews. | Pending |
| Store content | Edit contact/checkout/cancellation and each policy tab; submit the single form; failed validation selects the field's section. | Pending |
| Order detail | Four status cards align on wide screens and stack clearly on mobile. Section links land on Items, Customer, Summary, Payment, Delivery and Refund. Read-only staff cannot see mutation forms; permitted staff can complete existing state changes. | Pending |
| Navigation | Sidebar opens the active workspace, Admin home is direct, storefront remains reachable, topbar quick actions and mobile profile language switch work. No links lead to a 403 for the tested roles. | Pending |

Review every task at desktop and narrow phone width in **Arabic RTL and English LTR**. Include keyboard Tab/arrow navigation for section tabs, visible focus, horizontal tab scrolling, and no overlap with the mobile menu. Capture the browser/device, test role, QAS commit and result for each row; note any regression as a linked issue or a follow-up commit.

Open follow-ups: product publish-readiness/completion hints, deeper Analytics hierarchy and data freshness, consistency of remaining admin forms/lists, and full authenticated accessibility review. The dated [UI/UX review](UI_UX_AND_COMMERCIAL_READINESS_REVIEW_2026-09-23.md) defines broader acceptance gates; [master status](../PROJECT_MASTER_STATUS.md) governs deployment state.
