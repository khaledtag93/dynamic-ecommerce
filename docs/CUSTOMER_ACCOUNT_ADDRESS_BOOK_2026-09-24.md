# Customer account and address book — 2026-09-24

Working branch: `v42-clean-baseline`.

## Implemented in source

- A customer account overview links to orders, saved addresses and notifications, and shows recent real orders and unread counts.
- Customers can update their name, change their email after confirming the current password, and change their password with current-password and confirmation checks. An email change clears the previous verification timestamp.
- Address book supports create, view, edit and delete, with separate default shipping and billing addresses. The first address becomes both defaults; when a default is removed, the oldest remaining address takes its place. Mutations lock the customer row and run in a transaction to serialize default changes.
- Address routes resolve records through the signed-in customer, returning 404 for another customer's address.
- Checkout pre-fills contact/shipping fields from the selected saved address and billing fields from the billing default. Customers can choose a different address or enter a new one, then edit the filled fields for that order. The address selection is made before completing the rest of the form because it reloads checkout.
- The checkout billing switch now submits an explicit false value when turned off. Orders continue storing their own address snapshots; later address-book edits and deletions do not change placed orders.
- Customer pages and compact navigation use the existing storefront shell with English/Arabic copy and RTL-aware layout. Address deletion uses the shared in-app confirmation.

## Verification

- Focused feature tests cover profile/password security, ownership, shipping/billing defaults, checkout prefill and immutable order snapshots.
- Hardening CI run [36019574401](https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/36019574401) passed at application commit `05c2c51`: PHP syntax, clean MySQL migration, route boot, Blade compile, 115 tests (579 assertions), and frontend build.
- Authenticated QAS desktop/mobile review in English and Arabic: pending.
- Production and `main`: unchanged.

## QAS focus

1. Account overview, navigation, forms and password-manager/autofill on desktop and narrow mobile widths in both languages.
2. Profile name edit; email edit with wrong/correct current password; verification state after email change; password change and validation messages.
3. First address, multiple shipping/billing defaults, editing, deleting a default and empty state.
4. Checkout with default shipping and distinct billing; switching saved addresses before completing the form; entering a new address; editing pre-filled fields for one order.
5. Check that placed order details remain unchanged after modifying or deleting an address-book entry.
6. RTL alignment, keyboard focus, address text direction and shared delete confirmation.
