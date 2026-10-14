# Flowra Workforce Timezone & Attendance UX — QAS Checkpoint (2026-10-14)

## Status and evidence

- Active branch: `sec03-framework-upgrade`.
- **Verified application and QAS SHA:** `a83bc8f0`.
- **Hardening CI** https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38587254674 — Green: **896 passed / 18,108 assertions**. PHP syntax, clean MySQL migrations, Blade/config boot, browser interaction checks, reviewed npm audit, frontend build pass.
- QAS deployed using `bash deploy-qas.sh sec03-framework-upgrade`: app and static HTTP 200, maintenance OFF, Laravel migration `2026_10_14_150000_add_attendance_session_void_audit` Ran [19], Scheduler/queue fresh and healthy, zero failed jobs.
- Production and main were not changed.

## Implemented

1. **UTC canonical storage + Africa/Cairo local view.** `config/workforce.php` defines `WORKFORCE_TIMEZONE` (defaults to `Africa/Cairo`); PHP application remains UTC. `App\Support\WorkforceClock` converts incoming local datetime input to UTC, converts stored timestamps to local display, enforces non-existent DST time validation, and computes date filter bounds from local calendar day. This is environment-configurable, **not yet an editable White Label / Branding setting**.
2. **Attendance and schedules.** Record/original and effective/approved time column meanings clarified, timestamps shown `dd/mm/yyyy HH:mm` with RTL-safe bidi isolation; `My time clock`, attendance list, corrections, work shift forms and lists, employee summaries use local display. Date filters and today counters use Cairo local day. Schedule/correction user-entered datetimes are converted to UTC before persistence, with original recorded history preserved.
3. **Open session duration.** Client-side `public/admin/js/workforce-clock.js` refreshes displayed open-session net time/break time around every 15 seconds; this is *display-only*, not a payroll or timekeeping write.
4. **Void rather than delete.** New `voided_at`, `voided_by_user_id`, `void_reason` attributes on attendance sessions. POST action restricted to `workforce.manage`, requires reason (10 to 2,000 chars) and confirmation. Service locks row, rejects open/already-voided sessions and pending corrections and blocks void when an overlapping existing payroll run snapshot involves the employee (including Draft). Original check-in/out stays intact; audit is appended. Voided sessions excluded from active attendance counters, attendance shift matching, and **future** payroll generation.
5. **Regression coverage** including Cairo conversion (original QAS 14:35 UTC → 17:35 Cairo), US spring-forward nonexistent time, schedule inputs, correction inputs, role authorization, existing payroll guard, and repeated/open void attempts. EN/AR translated labels verified by shared-shell integrity test.

## QAS read-only smoke after deployment

- Server `HEAD = a83bc8f0`.
- `STORAGE_TZ=UTC`, `WORKFORCE_TZ=Africa/Cairo`.
- `LOCAL_17_35_UTC=2026-10-14 14:35:00`.
- `VOID_SCHEMA=PASS`.
- Sessions id 3, 2, 1: original timestamps read unchanged; session 3 showed `UTC 14:35 → Cairo 14/10/2026 17:35`; break minutes 0; none was voided.
- `php artisan ops:health --strict`: Scheduler OK, Queue worker OK, failed jobs 0.
- Before deployment, private backup `deploy_backups/qas_before_attendance_timezone_void_20261014_151347.sql` created (7,040,357 bytes); verified intact. Temporary QAS smoke/backup scripts removed.
- **No QAS attendance rows were edited or voided during validation.**

## Pending scope and acceptance

- Browser-authenticated manual visual acceptance is still needed (EN/AR and RTL, desktop/mobile). Check Attendance, My time clock, Schedule and correction input. Verify counter ticking and changes after a real clock-out, and a carefully controlled demo-only void after confirming there is no payroll run. Never void actual user records for testing.
- Current Payroll period date-boundary/clipping policy uses existing UTC calendar day boundaries. This slice intentionally did **not** silently redefine published payroll accounting periods or retroactively reallocate financial snapshots to Cairo date boundaries. Such a change requires separate policy/acceptance and controlled reconciliation if the business needs local-day payroll accounting.
- `WORKFORCE_TIMEZONE` is currently deployment configuration. Future multi-branch/multi-tenant timezone management remains product roadmap.
- Original QAS attendance break table had previously been restored; its missing historical rows (if any) were not reconstructed.
- Production release gates OPS-01, PAY-01, OPS-02 and scheduler/queue readiness remain open.

## Next

Complete manual authenticated QAS UI acceptance. Fix only reproduced residual defects. Then return to the first genuine unclosed Business Integrity boundary from verified application SHA `a83bc8f0`, not the older 2026-10-02 source state.
