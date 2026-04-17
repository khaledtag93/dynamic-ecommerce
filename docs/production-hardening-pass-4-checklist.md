# Production Hardening Pass 4

## Focus
- Queue worker resilience
- Dead-letter style recovery workflow
- Provider timeout and backoff tuning
- Final release QA checklist

## Recommended production checks
1. Confirm `QUEUE_CONNECTION` is not `sync`.
2. Keep the queue worker alive under Supervisor or systemd.
3. Make sure worker restart is part of deploy after code changes.
4. Keep queue `retry_after` above the WhatsApp job timeout.
5. Retry one representative failed notification first before mass retries.
6. Fix provider credentials, missing templates, or invalid order data before requeueing old failures.
7. Review Notification Center health cards and observability snapshot before release.
