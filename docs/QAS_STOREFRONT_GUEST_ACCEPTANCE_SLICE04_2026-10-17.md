# Flowra — QAS Storefront Guest Bilingual Acceptance Slice 04

Date: 2026-10-17 | QAS: https://v42.tag-marketplace.com
Source documentation baseline: `0dfcefc2bd98bc7304226be61b1f889b36967ffe` on `sec03-framework-upgrade`.
QAS running application: `a682074f` per owner's prior successful deploy evidence; **not independently re-read during this slice**.

## Gate 01 — pre-existing CI verified

- GitHub [Hardening CI #38981983235](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38981983235) was directly checked and returned `status=completed`, `conclusion=success`, `head_sha=0dfcefc2bd98bc7304226be61b1f889b36967ffe`.
- This confirms the **previous** checkpoint. It must not be conflated with the new documentation commit's future CI.

## Explicitly approved browser scope / isolation

Owner explicitly authorized visiting English and then Arabic in one **new, isolated unauthenticated Guest browser session on QAS only**. This authorization includes the temporary server-side guest `session(['locale' => 'en'/'ar'])` changes produced by GET `/locale/en` and `/locale/ar`; it does not authorize any other QAS data mutations.

Actual headless Google Chrome controlled by Puppeteer on owner-connected `DESKTOP-KOG45HP`; a newly launched browser process/profile was used rather than the owner's existing authenticated browser. The context was closed after completion. Intercepted requests rejected all non-GET/HEAD methods and unrelated locale and logout routes. **Only 2 locale requests** (`/locale/en`, `/locale/ar`) were intentionally state-changing, inside the isolated guest session. No login submit, add-to-cart, checkout, account update, product change, configuration change, DB operation, deploy, or Production access.

### Test-harness correction (not an app defect)

Initial browser attempt used an overly broad guard for all `/admin/*` GET requests, which unintentionally blocked legitimate public static assets such as `/admin/js/live-list.js` and `/admin/images/favicon.ico`. **That first pass was not accepted as complete evidence.** The local-only test harness was corrected to allow read-only static assets while still forbidding mutation methods and unauthorized locale/logout routes. **The full 24-page acceptance matrix was rerun** using the corrected guard. Final-run observed: `blockedRequests=[]`, `networkFailures=[]`, `pageErrors=[]`. These observations concern the tested execution and do not guarantee zero potential bugs.

## Locale switch and cross-page persistence

- Initial fresh guest page GET returned `lang=ar` and `dir=rtl`.
- **EN switch PASS:** clicked actual visible English locale link, server redirect returned HTTP 200; resulting home document `lang=en`, `dir=ltr`, heading *Everything you need from one trusted store.* All 12 subsequent EN page/viewport cases preserved `en/ltr`.
- **AR switch PASS:** returned to Home and clicked actual visible Arabic locale link; HTTP 200; resulting document `lang=ar`, `dir=rtl`, heading `كل ما تحتاجه من متجر واحد موثوق.` All 12 subsequent AR cases preserved `ar/rtl`.

## Guest bilingual render acceptance matrix

Each of the six public pages was opened independently by actual Chrome navigation in English and then Arabic, at both screen dimensions: desktop `1280×800` and mobile `390×844`.

| Public page | Route | EN desktop | EN mobile | AR desktop | AR mobile |
|---|---|---|---|---|---|
| Home | `/` | PASS | PASS | PASS | PASS |
| Search results | `/search?q=product` | PASS | PASS | PASS | PASS |
| Category | `/category/1` | PASS | PASS | PASS | PASS |
| Product detail | `/products/product-1` | PASS | PASS | PASS | PASS |
| Empty guest cart | `/cart` | PASS | PASS | PASS | PASS |
| Login form (no submit) | `/login` | PASS | PASS | PASS | PASS |

**Total: 24/24 successful public page rendering checks** (HTTP 200, expected `lang` and `dir`, no measured document horizontal overflow, requested page heading/content present), plus **2/2 actual locale-switch checks**. Examples: English login `Login to your account`; Arabic login `سجّل الدخول إلى حسابك`; English cart `Review your cart`; Arabic cart `راجع السلة`. English was not faked with Accept-Language headers or a screenshot mockup.

The final run captured no uncaught page JS errors, no `requestfailed` events, and no blocked requests. Visible images and labels were inspected in representative real browser screenshots; browser lazy-loaded images need not all be loaded at capture time, so **do not claim every image in every page was validated**. Viewport result `scrollWidth=innerWidth` for all 24 observations. This does not prove all small-text accessibility/color-contrast, every viewport, or all interactive flows.

## Real QAS screenshots committed as evidence

English desktop:
- [EN desktop Home](qa/evidence/2026-10-17/slice04/en-desktop-home.jpg)
- [EN desktop Search](qa/evidence/2026-10-17/slice04/en-desktop-search.jpg)
- [EN desktop Login](qa/evidence/2026-10-17/slice04/en-desktop-login.jpg)

English mobile (390px):
- [EN mobile Home](qa/evidence/2026-10-17/slice04/en-mobile-home.jpg)
- [EN mobile Category](qa/evidence/2026-10-17/slice04/en-mobile-category.jpg)
- [EN mobile Product](qa/evidence/2026-10-17/slice04/en-mobile-product.jpg)
- [EN mobile Empty Cart](qa/evidence/2026-10-17/slice04/en-mobile-empty-cart.jpg)
- [EN mobile Login](qa/evidence/2026-10-17/slice04/en-mobile-login.jpg)

Arabic:
- [AR desktop Home](qa/evidence/2026-10-17/slice04/ar-desktop-home.jpg)
- [AR desktop Login](qa/evidence/2026-10-17/slice04/ar-desktop-login.jpg)
- [AR mobile Home](qa/evidence/2026-10-17/slice04/ar-mobile-home.jpg)
- [AR mobile Product](qa/evidence/2026-10-17/slice04/ar-mobile-product.jpg)
- [AR mobile Login](qa/evidence/2026-10-17/slice04/ar-mobile-login.jpg)

Original **24 actual page screenshots** and JSON test observations (`results.json`) exist in the owner's local temporary directory `%LOCALAPPDATA%\Temp\flowra-qas-guest-20261017\slice04\`; the **13 curated** screenshots above are included in GitHub for durable evidence.

## Boundary and checkpoint

**Slice 04: EN/AR Guest Locale + Desktop/Mobile Read-Only Render Acceptance — PASS for tested cases.** This unblocks **bilingual Guest public-page render acceptance only**, not full Storefront E2E release acceptance.

- **Still open:** cart-add/quantity/checkout and authentication (would write data or require a test account); mobile navigation/filter end-to-end across both locales (only page renders sampled here); price-sort semantic comparison with unequal prices (public QAS catalog still had 5 products of the same EGP 1,000 price in Slice 03); multi-browser/a11y and further responsive widths.
- No application code change. Documentation and genuine public screenshot evidence only. CI for this new doc commit must be checked independently; don't mark GREEN until completed/success.
- **Suggested next Slice 05:** read-only EN/AR guest UI interaction regression (mobile nav open/close, search form and filtering, GET-only navigation and empty states) with screenshots; then escalate separately approved QAS fixture/persona acceptance for stateful workflows. Do not alter QAS data or application unless expressly authorized.
