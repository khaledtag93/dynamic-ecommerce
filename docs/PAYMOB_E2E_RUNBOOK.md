# Paymob E2E Release Runbook

Last reviewed: 2026-09-20

## Purpose
Validate the real Paymob test-mode flow before V42 is promoted to `main` or Production.

## Current application state
- The application currently implements the legacy Paymob hosted IFrame flow:
  `auth/tokens -> ecommerce/orders -> acceptance/payment_keys -> IFrame`.
- Callback verification is mandatory and state-changing callbacks are protected by HMAC, amount, currency, and integration-ID checks.
- The callback endpoint is public and CSRF-exempt:
  `/payments/paymob/callback`.
- Provider secrets are server-environment-only. Do not save API/HMAC/Secret/Meta credentials in `website_settings`.

## Paymob 2026 compatibility decision
Paymob's current documentation recommends Payment Intention + Unified Checkout for new integrations.
Existing merchants with an active IFrame integration can continue validating the legacy path, but new IFrames may require Paymob support.

Before E2E, inspect the Paymob dashboard in **Test** mode:

1. Developers / API Keys
   - confirm Test Secret Key exists
   - confirm Test Public Key exists
   - confirm API Key exists
   - do not copy these values into tickets, chat, Git, or database settings
2. Payment Integrations
   - record the non-secret Test Integration ID
   - determine whether an existing active IFrame ID is already attached/available
3. Callback configuration
   - Transaction Processed/Webhook URL must point to the public test application's Paymob callback
   - Redirect URL must return to the public test application
4. HMAC
   - obtain the HMAC secret privately and configure it only in the server environment

### Path A — existing active IFrame
Use the application's existing legacy flow for the E2E gate.

Required server environment:
- `PAYMOB_API_KEY`
- `PAYMOB_HMAC_SECRET`
- `PAYMOB_INTEGRATION_ID`
- `PAYMOB_IFRAME_ID`
- `PAYMOB_BASE_URL=https://accept.paymob.com/api`
- `PAYMOB_CURRENCY=EGP`
- `PAYMOB_VERIFY_SSL=true`

### Path B — no existing IFrame / new integration
Do not create a new dependency on the legacy IFrame flow.
Implement and validate Paymob Payment Intention + Unified Checkout using:
- `PAYMOB_SECRET_KEY`
- `PAYMOB_PUBLIC_KEY`
- Test Integration ID
- HMAC secret
- public notification and redirection URLs

Keep the existing legacy path as a compatibility fallback only if still required for an existing account.

## Public test environment requirement
The Paymob Transaction Processed callback is server-to-server, so the application callback must be reachable publicly over HTTPS.
Do not point Paymob Test callbacks at Production V40 while V42 is still isolated.

Use either:
- a staging/test subdomain/server running V42, or
- a controlled HTTPS tunnel to a local V42 environment for test-only validation.

## Mandatory E2E scenarios

### 1. Successful payment
- create one low-value online order
- Paymob hosted checkout opens
- payment completes using Paymob Test mode
- server callback is received
- HMAC is valid
- Payment becomes `paid`
- Order payment status becomes `paid`
- transaction reference/provider status are recorded
- refreshing the customer result page does not create another payment/order

### 2. Declined/failed payment
- execute a Paymob Test decline/failure
- Payment becomes `failed`
- Order does not become paid
- stock/order remain internally consistent
- retry path remains available

### 3. Duplicate callback
- deliver/process the same successful callback more than once
- Payment remains terminal `paid`
- no duplicate financial state or duplicate order is created

### 4. Late failure after success
- process success, then a later failure/pending callback for the same payment
- Payment must remain `paid`
- Order must remain paid

### 5. Full refund state
- after a fully recorded refund, Payment must be `refunded`
- late Paymob paid/failed callbacks must not downgrade the refunded ledger state

### 6. Browser refresh / duplicate tabs
- open/refresh Paymob redirect/result pages repeatedly
- checkout URL reuse may occur while valid
- no duplicate Order or Payment records are created

### 7. Integrity rejection
Confirm callbacks are rejected before state mutation when any of these are wrong:
- HMAC
- amount
- currency
- Integration ID

## Evidence to retain
For each E2E case record only non-secret evidence:
- local Order ID/order number
- Payment ID
- Paymob transaction ID
- status before/after
- HTTP response/result
- sanitized application log event
- screenshot of Paymob transaction status if useful

Never retain:
- API key
- Secret Key
- HMAC secret
- payment token/client secret
- full callback payload containing customer/payment data

## Completion
The Paymob release gate is green only after:
- one real Test-mode success passes end to end
- negative/duplicate/delayed callback scenarios are verified
- the Paymob dashboard and local Order/Payment ledgers agree
- rotated credentials are active in the target environment
- no historical plaintext provider secrets remain in application database settings


### Paymob account discovery — 2026-09-20
- New dashboard, Test mode: Payment Integrations table is empty.
- Old dashboard, Test mode: Developers -> Payment Integrations is also empty.
- Attempting to create a new Non-Shopify / MIGS / EGP Test integration returns: "Cannot create more than 1 test integration with the same gateway type and currency."
- This means the account backend recognizes an existing MIGS/EGP Test integration even though neither dashboard currently renders it.
- Next diagnostic: inspect Developers -> Iframes in the old dashboard for the previously used iframe/integration linkage. If no legacy artifact is visible there either, treat this as a Paymob account-side integration visibility/state issue and open a support case rather than creating another integration.


### Paymob legacy artifact confirmed — 2026-09-20
Old dashboard -> Developers -> Iframes shows two Test-mode IFrames:
- IFrame `1024106` — `Installment_Discount`
- IFrame `1024107` — `My new card Iframe`

This confirms the account still retains legacy checkout artifacts even though both old and new Payment Integrations pages render no integration rows. The next diagnostic is to open/edit IFrame `1024107` and inspect which payment integration(s) it is linked to. Do not create another MIGS/EGP Test integration while Paymob reports that one already exists.


### Paymob iframe edit inspection — 2026-09-20
- Old dashboard IFrame `1024107` ("My new card Iframe") was opened in edit mode.
- The edit screen exposes only presentation/customization fields (Name, Description, HTML, JavaScript, CSS).
- No Payment Integration ID or MIGS linkage is exposed from the IFrame editor.
- Conclusion: the hidden MIGS/EGP Test integration cannot be recovered from the IFrame UI. Since both Payment Integrations pages are empty while creation is blocked as a duplicate, this is now treated as a Paymob account-side hidden/orphan integration state.
- Next action: Paymob support/account manager should reveal, restore, or reset the Test MIGS/EGP integration. Do not create/duplicate/delete IFrames as a workaround.


### Recovered legacy deployment/payment notes — 2026-09-20
Recovered from the user's old local notes (non-secret facts only):
- Paymob Merchant ID: `1147230`
- Paymob Test Integration ID previously used: `5596653`
- Paymob legacy IFrame previously used: `1024107`
- Production Laravel app path historically used: `/home/u637857322/domains/tag-marketplace.com/laravel_app`
- Production public webroot historically used: `/home/u637857322/domains/tag-marketplace.com/public_html`
- Historical SSH port used: `65002`
- Historical deploy routine used `deploy.sh` / `rollback.sh` from the Laravel app directory.

Security note:
- The recovered notes also contained plaintext provider, database, SSH, and account credentials.
- No credential values are copied into this repository documentation.
- Treat all historical credentials from those notes as exposed and rotate them before Production promotion.
- The recovered Paymob Integration ID / IFrame ID are identifiers, not secrets, and may be used for compatibility diagnostics.
