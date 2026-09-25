# Shipping Engine V1
Date: 2026-09-25
Working branch: `v42-clean-baseline`
Application source checkpoint: `97dc443d`

## Goal

Replace the disconnected zero-shipping cart/checkout behavior with a server-authoritative shipping engine that prices orders from configured delivery method + zone/city + rate policy, preserves the shipping decision on the order, and updates checkout without a full-page reload.

## Stable delivery methods

V1 preserves the existing Delivery V2 method codes:
- `standard_shipping`
- `express_shipping`
- `store_pickup`

The codes are seeded as stable system methods so Delivery V2 transition logic remains compatible.

Admins can configure:
- English / Arabic storefront name;
- active state;
- sort order;
- ETA min/max calendar days;
- notes.

V1 does not allow arbitrary new delivery-method codes.

## Zones and cities

`shipping_zones` stores:
- code;
- EN/AR name;
- ISO 2-letter country code;
- storefront country name;
- active state;
- priority;
- notes.

`shipping_zone_cities` stores exact supported checkout city names plus normalized matching values.

Rules:
- a city may belong to only one zone per country;
- duplicate normalized city assignment is rejected before the DB write;
- moving a zone to another country is blocked when its cities would conflict there;
- inactive zones are not quoted.

## Rates

`shipping_rates` stores one rate per Shipping Method + Shipping Zone.

Each rate supports:
- amount;
- active state;
- optional free-shipping threshold;
- explicit threshold basis:
  - subtotal before discounts;
  - subtotal after discounts.

No shipping amount or free-shipping threshold is auto-assumed.

Store Pickup requires no rate and always quotes zero shipping.

Standard/Express require an active rate for the resolved zone.

## Quote engine

`ShippingService`:
- resolves active method;
- resolves exact normalized city + country to an active zone;
- resolves active method/zone rate;
- evaluates optional free-shipping policy;
- returns shipping amount;
- returns free-shipping remaining/progress;
- returns ETA metadata;
- returns a complete historical shipping snapshot.

Unsupported city/country or unavailable method/zone combination fails explicitly.

## Checkout integration

Checkout now:
- loads active configured shipping methods;
- requests a server quote when Method / City / Country changes;
- debounces address typing;
- aborts stale quote requests;
- updates Shipping and Total without a full-page reload;
- displays zone, ETA and free-shipping progress/qualification;
- disables Place Order until the server confirms a valid quote;
- surfaces shipping errors in checkout instead of redirecting the customer to Cart.

The final order price is **re-quoted inside the checkout transaction**. The browser quote is display-only and cannot override server pricing.

## Order snapshot

Orders now store:
- `shipping_method_id`
- `shipping_zone_id`
- `shipping_rate_id`
- `shipping_snapshot`
- existing `shipping_total`
- existing `estimated_delivery_date`

The snapshot includes:
- method names EN/AR;
- zone names EN/AR;
- configured rate;
- applied rate;
- threshold and basis;
- threshold basis amount;
- free-shipping result;
- ETA min/max;
- ETA basis = calendar days;
- country/city;
- pickup flag.

Historical delivery-method labels prefer the order snapshot, so later admin renaming does not rewrite past orders.

`estimated_delivery_date` uses the configured maximum ETA calendar days as the current Delivery V2 single-date estimate while the full min/max window remains in the snapshot.

## Cart correctness

The previous hard-coded EGP 600 shipping-goal messaging was disconnected from actual shipping price.

V1 removes that claim from Cart/Checkout.

Cart now shows:
- Shipping = Calculated at checkout;
- Total before shipping.

The real shipping price is introduced only after the Shipping Engine can quote it.

## Admin setup

Shipping Setup is split into focused workspaces:
1. Shipping Methods
2. Zones & Cities
3. Shipping Rates

This avoids turning shipping policy into one oversized settings page.

Access uses the existing `settings.manage` permission. Operations Manager has this permission by default; Cashier does not.

All shipping setup mutations are backend-authoritative and audited:
- method update;
- zone create/update;
- city add/remove;
- rate save.

## MySQL / migration safety

All new Shipping Engine FK/index/unique names are explicit and short to avoid the MySQL 64-character identifier problem encountered during Workforce QAS deployment.

## Regression coverage

`ShippingEngineTest` covers:
- zone/method rate quote;
- before-discount free-shipping threshold;
- after-discount threshold;
- Store Pickup zero quote;
- unsupported city rejection;
- inactive method rejection;
- checkout quote JSON;
- order shipping snapshot;
- ETA persistence;
- historical label stability after later shipping-config edits;
- Shipping Setup permission isolation;
- duplicate city assignment protection.

`CheckoutIdempotencyTest` was updated for the new ShippingService constructor and now uses Store Pickup so its original idempotency scope remains isolated from shipping-rate setup.

## Release status

- Application source checkpoint: `97dc443d`.
- CI: Pending; no GitHub workflow run/status was visible for this head when checked.
- QAS: not yet deployed for this Shipping Engine slice.
- Production: unchanged.

## Next critical-commerce slice

**Online-payment stock reservation / expiry / release V1**

Reason for ordering:
- current checkout immediately decreases stock before online payment completes;
- unpaid/failed online payment can hold stock indefinitely unless restored by another workflow;
- this is a commerce-correctness issue independent of tax jurisdiction.

Tax/VAT stays after an explicit merchant/legal configuration decision instead of hard-coding a rate.
