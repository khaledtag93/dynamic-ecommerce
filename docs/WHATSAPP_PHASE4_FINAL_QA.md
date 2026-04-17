# WhatsApp Phase 4.1 — Final QA Matrix

## Pre-flight
- Meta access token is valid and not expired.
- Phone Number ID is correct.
- Real approved templates are configured for AR and EN.
- Queue is enabled only if a worker is running.

## Functional checks
1. Create order -> `order_confirmation` log is created once.
2. Change order to processing -> `order_status_update` log is created.
3. Cancel order -> `order_status_update` and `delivery_update` are created.
4. Change delivery to shipped -> `delivery_update` is created.
5. Retry failed log -> new attempt is stored without deleting the old log.
6. Manual send from settings page works for each message type.

## Safety checks
- Refreshing the same page does not create duplicate logs within the configured duplicate window.
- If WhatsApp is disabled, checkout still succeeds and logs are marked skipped.
- If template name is missing, log fails cleanly without breaking order flow.
- Invalid phone number fails safely and stores the reason in logs.

## Production checks
- `php artisan optimize:clear` after deploy.
- If queue is on, a worker is running with the configured queue name.
- Monitor `storage/logs/laravel.log` and WhatsApp Logs page after first production send.
