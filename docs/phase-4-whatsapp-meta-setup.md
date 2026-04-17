# Phase 4 — WhatsApp Meta API Setup

## Included in this version
- WhatsApp foundation using Meta WhatsApp Cloud API
- Safe order confirmation dispatch after successful order placement
- Message logs table (`whatsapp_logs`)
- Queue-ready job (`SendWhatsAppMessageJob`)
- Config-based setup with future DB-admin override support

## Environment variables
Add the following to your active `.env`:

```env
WHATSAPP_ENABLED=false
WHATSAPP_DEFAULT_PROVIDER=meta
WHATSAPP_QUEUE_ENABLED=false
WHATSAPP_QUEUE_CONNECTION=
WHATSAPP_QUEUE_NAME=default
WHATSAPP_META_BASE_URL=https://graph.facebook.com
WHATSAPP_META_GRAPH_VERSION=v23.0
WHATSAPP_META_ACCESS_TOKEN=
WHATSAPP_META_PHONE_NUMBER_ID=
WHATSAPP_META_BUSINESS_ACCOUNT_ID=
WHATSAPP_META_APP_SECRET=
WHATSAPP_META_VERIFY_TOKEN=
WHATSAPP_META_TIMEOUT=20
WHATSAPP_FEATURE_ORDER_CONFIRMATION=true
WHATSAPP_FEATURE_ORDER_STATUS_UPDATE=false
WHATSAPP_FEATURE_DELIVERY_UPDATE=false
WHATSAPP_TEMPLATE_ORDER_CONFIRMATION_AR=
WHATSAPP_TEMPLATE_ORDER_CONFIRMATION_EN=
WHATSAPP_TEMPLATE_ORDER_CONFIRMATION_AR_LANG=ar
WHATSAPP_TEMPLATE_ORDER_CONFIRMATION_EN_LANG=en_US
```

## Required Meta values
- `WHATSAPP_META_ACCESS_TOKEN`
- `WHATSAPP_META_PHONE_NUMBER_ID`
- Approved template name for Arabic and/or English order confirmation

## Important
- Keep `WHATSAPP_ENABLED=false` until Meta credentials and approved templates are ready.
- Order flow will not break if WhatsApp fails.
- Failures are stored in `whatsapp_logs`.

## Migration
Run:

```bash
php artisan migrate
```

## First safe activation
1. Add Meta credentials
2. Add approved template names
3. Run migration
4. Set `WHATSAPP_ENABLED=true`
5. Place a test order
6. Review `whatsapp_logs`

## Notes about templates
The app currently sends order confirmation as a template message with these body parameters in order:
1. customer name
2. order number
3. order total
4. payment method
5. delivery method

Create your Meta approved templates in the same parameter order.
