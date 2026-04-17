# Phase 3 Foundation Notes

Included in this version:

- Payment foundation
  - Dynamic payment methods from settings
  - Payment settings page in admin
  - Payment records list + detail + manual status updates
- Notifications foundation
  - Database notifications migration
  - Customer + admin notifications for order/payment/delivery updates
  - Frontend + admin notifications pages
- Permissions foundation
  - Lightweight roles + permissions tables
  - Staff role assignment page
  - Permission middleware for new admin modules
- Delivery foundation
  - Delivery fields on orders
  - Delivery list page in admin
  - Delivery update form inside order details

## After pulling this version

Run:

```bash
php artisan migrate
php artisan optimize:clear
```

If needed:

```bash
php artisan storage:link
```

## Notes

- Existing working order flow was kept intact.
- Legacy `role_as` admin support still works, so old admins do not lose access.
- Arabic/English support was considered in the new UI and settings.
