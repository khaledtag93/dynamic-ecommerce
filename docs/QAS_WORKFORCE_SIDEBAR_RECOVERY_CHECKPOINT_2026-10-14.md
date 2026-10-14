# QAS Workforce Schema Recovery & Admin Sidebar Clarity — 2026-10-14

## Scope and verified state

- Branch: `sec03-framework-upgrade`.
- Verified application / CI / QAS SHA: `c4801411`.
- Hardening CI run: https://github.com/khaledtag93/dynamic-ecommerce/actions/runs/38578100532 — **Green**; 889 PHPUnit tests, 18,015 assertions; clean MySQL migrations, config/Blade, browser interactions and frontend build passed.
- QAS deployed using `bash deploy-qas.sh sec03-framework-upgrade`. App and static HTTP 200, maintenance OFF, server HEAD `c4801411`.
- Production and `main` were not changed.

## Confirmed cause of the Workforce 500s

QAS Laravel logs for October 14 reproduced MySQL error 1146: `employee_attendance_breaks` was missing, although migration `2026_09_25_020000_create_employee_attendance_breaks_table` was recorded as **Ran**. This was real QAS schema drift, not a request to rewrite the Workforce business logic.

Source includes `2026_10_14_000100_repair_missing_employee_attendance_breaks_table.php`: idempotently create the original table, FK and index only when absent. It never drops existing table data on rollback. QAS applied this migration successfully during deployment.

Before any mutation, a private QAS DB backup was created at `deploy_backups/qas_before_workforce_schema_repair_20261014_135752.sql` (7,015,683 bytes; permissions 0600). Do not commit or publish this file.

Post-deploy QAS read-only smoke:
- `WORKFORCE_SCHEMA=PASS`: table and required columns exist.
- `ATTENDANCE_RELATIONS=PASS`: attendance sessions + employee/break/correction eager loading.
- `SCHEDULE_RELATIONS=PASS`: work shifts + employee relations.
- `php artisan ops:health --strict` succeeded: fresh Scheduler/Queue heartbeats, database queue, zero failed jobs; one transient pending heartbeat job during verification.
- CI includes regression checks for sidebar contrast/hover/current-page selectors.

**Data-integrity caveat:** The recovery restores the table structure; any historical attendance-break rows deleted before this recovery are not reconstructed. The cause and timing of the table loss remain unproven. QAS data history may require a Hostinger historical database backup if needed.

## Admin sidebar experience

`resources/views/layouts/admin.blade.php` now computes contrast-safe navigation ink/surface tokens from the white-label `admin_sidebar_color` setting rather than introducing separate hard-coded theme selection. Hover (including parent group), keyboard focus and current-page state are visually distinct. Active links use a pronounced contrast surface, primary-color rail and RTL-safe logical position. Existing `aria-current` route markers are preserved.

## Remaining acceptance and continuation

- Manually open **My time clock**, **Work schedule** and **Attendance** while authenticated on QAS in EN/AR. Record 200/page rendering, contents and actions, not only DB relation success. Check no new Laravel 500 is generated.
- Visually review sidebar hover/active/keyboard focus with the current white-label palette, light/dark options, collapsed/mobile navigation and RTL. Adjust only if a defect is reproduced.
- Investigate why the original attendance-break table disappeared if QAS history or repeatability requires it.
- Then return to the remaining unclosed monetary/accounting integrity audit from the new verified revision.
- Production readiness gates (OPS-01, PAY-01, OPS-02, production scheduler/queue) remain open.
