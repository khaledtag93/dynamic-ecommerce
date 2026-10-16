# Flowra — QAS Storefront Guest Acceptance: Slice 03 (locale readiness and price sort)
Date: 2026-10-17 | Branch: `sec03-framework-upgrade` | Base checkpoint `a7eb2b26efba6044ddb0233dd88f5e0481d1e959`

## Verified baseline

- [Hardening CI #38981478061](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38981478061): **completed / success**, head_sha `a7eb2b26efba6044ddb0233dd88f5e0481d1e959`. This certifies the previous Slice 02 documentation checkpoint, **not** the new Slice 03 commit.
- QAS deployed application version **`a682074f`**, as previously recorded from the owner's deployment output. **No independent runtime SHA reread in this Slice.**
- Target: https://v42.tag-marketplace.com, fresh unauthenticated Chrome/Puppeteer browser session on owner's connected Windows device, public GET only, with request interception forbidding non-GET/HEAD and `/locale/*`/logout. No request to change guest locale was made. Browser tests captured **0 blocked mutation attempts and 0 uncaught page errors** for the executed pages. Do not infer this covers untested interactions.

## Price sorting — source of test data, results, limitations

- Requested *real varied-price QAS sample* could not be found in the public visible catalog without changing data.
- Direct browser GET `/search` showed **Products found: 5**; all five visible product cards had a displayed current price of **EGP 1,000.00**. Names: `Product 1` and four `Product 1 (Copy...)` variations.
- Browser GET `/search?sort=price_low_high`: HTTP 200; five visible cards; prices `[1000,1000,1000,1000,1000]`; low→high ordering is nondecreasing **vacuously**, not a meaningful heterogeneous-price test.
- Browser GET `/search?sort=price_high_low`: HTTP 200; five visible cards; prices `[1000,1000,1000,1000,1000]`; high→low ordering is nonincreasing **vacuously**.
- Both controls/routes are **PASS for loading and selection**; **price-order semantics remain INCONCLUSIVE / BLOCKED BY INSUFFICIENT DATA**. Not a proven defect. No products were created, edited or added to a cart.
- **Future test design when source data exists:** find ≥3 public active products with distinct effective display prices; record title/URL, current price, category, availability and any variant pricing. Submit both sorting modes via guest GET form; compare the rendered product-card sequence numerically ascending/descending, including tie behavior separately. Record screenshots and page count; do not invent a dataset.

## Arabic guest locale: observed evidence

| Page and viewport | HTTP | HTML language | Direction | Horizontal overflow | Status |
|---|---|---|---|---|---|
| Home desktop 1280×800 | 200 | `ar` | `rtl` | None (`scrollWidth=1280`) | **PASS (read-only render)** |
| Login desktop 1280×800 | 200 | `ar` | `rtl` | None | **PASS (read-only render)** |
| Home mobile 390×844 | 200 | `ar` | `rtl` | None (`scrollWidth=390`) | **PASS (read-only render)** |
| Product /products/product-1 mobile 390×844 | 200 | `ar` | `rtl` | None | **PASS (read-only render)** |
| Login mobile 390×844 | 200 | `ar` | `rtl` | None | **PASS (read-only render)** |

English and Arabic language switch links are present in the DOM, e.g. `/locale/en?redirect=...` and `/locale/ar?redirect=...`; presence does not establish successful behavior. These observations extend earlier Slice 02 mobile 320px / 390px smoke checks.

## English locale: explicit permission gate

**BLOCKED / NOT TESTED.** GitHub deployed-release source `routes/web.php` implements `GET /locale/{locale}` with `session(['locale' => $locale])`; `SetLocale` reads Session. Switching a Guest Session is state-changing despite using GET. No such request was sent because the owner required explicit prior consent. Accept-Language header alone was previously observed to leave the default `ar/rtl`; do not count that as English acceptance.

**Request owner approval before proceeding.** Proposed **narrow authorization**: only in a newly isolated unauthenticated QAS browser context, visit `/locale/en` (and optionally `/locale/ar`) to change *that disposable guest browser's session locale only*; then read public Home, Search, Category, Product, empty Cart and Login at desktop 1280px and mobile 390px; capture screenshots, `lang`, `dir`, text and overflow. Do not modify tenant setting, database schema/content, product catalog, account/login, orders or production. Never submit a POST/PATCH/DELETE. Without approval, keep English BLOCKED.

## Actual screenshots stored in GitHub

- [Price sort low-to-high](qa/evidence/2026-10-17/slice03/sort-price_low_high.jpg)
- [Price sort high-to-low](qa/evidence/2026-10-17/slice03/sort-price_high_low.jpg)
- [Arabic desktop login](qa/evidence/2026-10-17/slice03/ar-desktop-login.jpg)
- [Arabic mobile home 390px](qa/evidence/2026-10-17/slice03/ar-mobile-home.jpg)
- [Arabic mobile product 390px](qa/evidence/2026-10-17/slice03/ar-mobile-product.jpg)
- [Arabic mobile login 390px](qa/evidence/2026-10-17/slice03/ar-mobile-login.jpg)

All are genuine live guest-QAS Chrome screenshots. The temporary local capture and machine-readable observation file live under `%LOCALAPPDATA%\Temp\flowra-qas-guest-20261017\slice03\`. Screenshots above are copied as repository blobs for lasting evidence. Do not confuse screenshot presence with authenticated or write-flow acceptance.

## Decision / checkpoint

**SLICE 03 — PARTIAL PASS / REMAINING BLOCKERS IDENTIFIED.**
- CI for previous checkpoint **GREEN**.
- Arabic desktop + mobile public page render **PASS on sampled pages**.
- Price sort URL/forms **PASS**, actual relative ordering across diverse prices **INCONCLUSIVE due to test-data gap**.
- English real locale **BLOCKED pending specific user authorization for isolated Guest Session-only switch**.
- No product, checkout, auth or code changes, no QAS or production deployment/writes; only documentation + captured public screenshots added to GitHub.

**Next action:** request explicit consent for isolated QAS guest-session locale switch; once approved, execute controlled English+Arabic browser acceptance and checkpoint it. Keep price-sort acceptance open until a legitimate varied-price test sample is available without unapproved writes.
