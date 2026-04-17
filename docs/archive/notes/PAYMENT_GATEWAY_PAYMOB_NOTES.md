# Paymob integration notes

- Recommended provider for Egypt-first rollout.
- Fill these admin settings before testing:
  - payment_gateway_provider = paymob
  - payment_online_enabled = 1
  - paymob_api_key
  - paymob_integration_id
  - paymob_iframe_id (optional but recommended)
  - paymob_hmac_secret
- Checkout flow in this version:
  1. Customer places order normally.
  2. If the payment method is online, the order stays pending.
  3. Success / order details page shows a secure Paymob payment button.
  4. Clicking it generates a real Paymob payment key and redirects to the hosted iframe.
  5. Callback updates the payment record and the order payment status.

This approach keeps COD and bank transfer working without breaking the current checkout flow.
