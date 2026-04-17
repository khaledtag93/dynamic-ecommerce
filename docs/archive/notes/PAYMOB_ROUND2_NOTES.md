# Paymob Round 2

## Added in this round
- Stronger HMAC validation flow for Paymob callbacks.
- Safer callback handling for browser redirects and server-to-server POST callbacks.
- Reuse of still-valid hosted payment sessions instead of generating a fresh one every retry.
- Safer prevention of new payment sessions when the order is already marked as paid.
- Customer-facing payment result page for paid / pending / failed states.
- Better payment gateway logs inside the admin payment details page.
- Extra event timeline stored in `payments.meta.events`.
- Deduped payment status notifications by `payment_id + status`.

## Important config
Fill these in admin payment settings:
- payment_gateway_provider = paymob
- paymob_api_key
- paymob_integration_id
- paymob_iframe_id
- paymob_hmac_secret

## After update
Run:

```bash
php artisan optimize:clear
```

## Existing database
No migration is required for this round.

## Main routes
- Customer redirect: `payments.paymob.redirect`
- Paymob callback: `payments.paymob.callback`
- Customer result page: `payments.paymob.result`
