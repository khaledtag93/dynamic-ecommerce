# Final Stabilization Checklist

## Admin
- Orders list renders and filters correctly
- Order details page loads and status updates still work
- Payments list renders and filters correctly
- Payment details page loads and manual status update still works
- Deliveries page renders and links to order delivery card
- Notifications, inventory, and customer pages still render without broken layout

## Customer side
- Homepage loads
- Category and product pages load
- Cart, checkout, and order success pages load
- Customer notifications page still renders

## Payments
- COD flow still creates order and payment record
- Paymob redirect still opens
- Callback processing remains unchanged
- Payment result page still reflects final state

## Translations
- Updated admin screens no longer fall back to mixed hardcoded labels
- Arabic interface still renders after JSON updates

## Safety
- No migration required
- No config change required
- Existing `.env` remains intact for active development
