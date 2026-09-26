# Scheduler & Queue Operations Runbook — 2026-09-26

## Purpose

This runbook turns scheduler and queue execution into a measurable production gate instead of an assumption.

Dynamic already contains queued work for analytics, Growth, and WhatsApp. The framework default remains `QUEUE_CONNECTION=sync` until QAS proves the asynchronous runtime.

## Source controls

- `ops:heartbeat` records a scheduler heartbeat and dispatches a queue heartbeat job.
- `ops:health` checks scheduler age, queue-worker age, active queue connection, pending database jobs, and failed jobs.
- `ops:health --strict` additionally requires an asynchronous queue driver and zero failed jobs.
- Queue heartbeats are bound to the active queue connection so a recent old `sync` heartbeat cannot make a new `database` queue look healthy.
- `jobs` and `job_batches` migrations are included so the database queue driver can be enabled intentionally.
- QAS and Production deploy scripts issue `artisan queue:restart` after application/cache rebuilds.

## QAS activation order

Do not change Production first.

1. Deploy the exact green branch commit to QAS and run migrations.
2. Confirm the current configuration:
   `php artisan about`
   `php artisan ops:heartbeat`
   `php artisan ops:health --max-age=180`
3. With `QUEUE_CONNECTION=sync`, non-strict health may pass but strict health must fail. This is expected.
4. Set QAS `QUEUE_CONNECTION=database`, rebuild config cache, and verify `php artisan queue:failed`.
5. Run `php artisan ops:heartbeat`. The queue heartbeat must not become healthy until a real worker processes the queued job.
6. Start the worker using the hosting-supported process monitor. Preferred worker command:
   `php artisan queue:work database --queue=default --sleep=1 --tries=3 --timeout=120`
7. Configure the scheduler entry to run once per minute:
   `cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`
8. Wait at least two scheduler cycles, then run:
   `php artisan ops:health --strict --max-age=180`
9. The release gate is green only when scheduler and queue heartbeats are fresh, the queue driver is asynchronous, and failed jobs are reviewed/resolved.

## Shared-host fallback

If the host cannot supervise a permanent worker, use the hosting control panel's cron/process feature to run bounded workers frequently. Avoid overlapping workers. A bounded worker can use Laravel's `--stop-when-empty` / `--max-time` controls, but the exact cron setup must be verified on the host rather than assumed.

Example worker command for a controlled cron environment:

`php artisan queue:work database --queue=default --stop-when-empty --max-time=50 --sleep=1 --tries=3 --timeout=120`

If the host offers a real process monitor, prefer a persistent monitored worker.

## Deploy behavior

Every deployment now signals `php artisan queue:restart`. Long-lived Laravel workers keep booted application state, so deployments must tell workers to reload the new code.

## Failure handling

- `ops:health` failure: inspect scheduler/worker configuration before retrying.
- Pending jobs increasing while queue heartbeat is stale: worker is not consuming work.
- Failed jobs > 0 in strict mode: inspect `php artisan queue:failed`, fix the cause, retry only when the operation is safe/idempotent, then clear resolved records intentionally.
- Never use `queue:flush` as a routine fix.
- Payment, stock, permissions, and external-message jobs must retain server-side validation/idempotency when moved from sync to async execution.

## Production promotion gate

Production remains blocked until:

- QAS scheduler heartbeat stays fresh across multiple cycles.
- QAS queue heartbeat is produced by the configured asynchronous connection.
- Failed-job handling is demonstrated.
- Stock reservation expiry is observed through the scheduler.
- Deploy + `queue:restart` behavior is verified.
- The same operational setup is documented for Production before enabling `QUEUE_CONNECTION=database`.
