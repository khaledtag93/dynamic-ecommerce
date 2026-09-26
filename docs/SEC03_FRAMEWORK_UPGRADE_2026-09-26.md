# SEC-03 Framework Upgrade — 2026-09-26

## Goal

Close the production P0 created by the unsupported Laravel 10 runtime without weakening the new Composer security gate.

This work is isolated from QAS and Production on `sec03-framework-upgrade`. No deployment is allowed until dependency resolution, automated tests, clean migration, application boot, route compilation, Blade/config compilation, frontend build, security audit, and QAS smoke tests are green.

## Current verified baseline

- PHP requirement in the project: `^8.1`; Production/QAS host previously verified on PHP 8.3.x.
- Laravel: `10.48.29` — security support ended 2025-02-04.
- Livewire: `2.12.7`.
- Sanctum: `3.3.3`.
- Laravel UI: `4.6.1`.
- Collision: `7.12.0`.
- PHPUnit: `10.5.46`.
- Tinker: `2.10.1`.
- Carbon: `2.73.0`.
- Hardening CI now blocks on `composer audit --locked`; this gate must stay enabled.

## Target decision

Laravel 12 remains security-supported only until 2027-02-24. Laravel 13 is the current supported major, supports PHP 8.3, and receives security fixes until 2028-03-17.

Preferred final target: **Laravel 13**, provided the compatibility rehearsal proves the application and hosting stack can move safely. This avoids performing another major framework upgrade only a few months after Laravel 12.

Because Livewire 3.x does not support Laravel 13, a Laravel 13 target also requires **Livewire 4.x**. If the rehearsal exposes unacceptable migration risk, Laravel 12 + Livewire 3 is the controlled fallback, not a silent downgrade.

## Known compatibility work

### Laravel 10 -> 11 requirements

- Framework dependency must move through the Laravel 11 compatibility requirements.
- Sanctum 3 -> 4.
- Livewire 2 -> at least 3 as part of the migration path.
- Collision 7 -> 8.x.
- Review Carbon 3 behavior, especially `diffIn*` return values/sign.
- Keep the existing Laravel 10 application structure; do not rewrite the application bootstrap merely to look like a fresh Laravel 11+ skeleton.
- Publish/verify Sanctum migrations where applicable.
- Update Sanctum middleware class references.

### Laravel 11 -> 12 requirements

- Framework 12 requires PHP >= 8.2.
- PHPUnit must move to 11.x for the Laravel 12 step.
- Carbon 3 is required.
- Review local filesystem default-root behavior.
- Re-verify image validation behavior because SVG is no longer accepted by the generic `image` rule unless explicitly enabled.

### Laravel 12 -> 13 requirements

- Framework 13 requires PHP >= 8.3.
- Tinker moves to 3.x.
- PHPUnit moves to 12.x.
- Review request forgery protection changes.
- Review session serialization; JSON is the stronger new default but switching it invalidates existing sessions and must be an explicit deployment decision.
- Review MySQL/MariaDB `upsert` behavior and any custom cache/session assumptions.
- Check for legacy `array_first()` / `array_last()` helpers before PHP polyfills are loaded.

## Livewire migration findings

The application contains full-page Livewire components under `App\\Http\\Livewire`, including Brands and Attributes, plus the large Product admin workspace.

The migration must preserve the current namespace instead of moving files during the security upgrade. Publish/configure Livewire so `class_namespace` remains `App\\Http\\Livewire`.

Current source contains Livewire 2 browser APIs that must be migrated:
- `dispatchBrowserEvent(...)` in Attribute, Brand and Product components.
- `document.addEventListener('livewire:load', ...)`.
- `Livewire.hook('message.processed', ...)`.

Current templates also rely on Livewire 2 binding semantics:
- `wire:model` and `wire:model.debounce.*` are live in v2 but deferred in v3+.
- Fields that drive search/filter/state immediately must become explicit live bindings.
- Existing `wire:model.defer` fields should retain deferred/save-time behavior.
- Product form already contains some `wire:model.live` usage; it must be normalized against the final Livewire major instead of applying blind find/replace.

Livewire 4 adds another compatibility pass after the v2 -> v3 semantics are made correct. We will follow the documented v3 -> v4 changes instead of jumping syntactically without tests.

## Sanctum finding

`config/sanctum.php` still contains Laravel 10-era middleware references for cookie encryption / CSRF verification. These must be updated to the framework classes required by Sanctum 4 while preserving the application's existing auth behavior.

## Dependency set to resolve in rehearsal

Final Laravel 13 candidate:
- PHP `^8.3`
- `laravel/framework:^13.0`
- `laravel/sanctum:^4.0`
- `livewire/livewire:^4.0`
- `laravel/tinker:^3.0`
- `nunomaduro/collision:^8.1` or Composer-resolved compatible current major
- `phpunit/phpunit:^12.0`
- current compatible `spatie/laravel-ignition`
- keep `laravel/ui` only if Composer confirms its current 4.x line is compatible

Do not hand-edit `composer.lock`. Composer must generate it.

## Rehearsal sequence

1. Work only on `sec03-framework-upgrade`.
2. Record current dependency tree and run `composer why-not` for target packages.
3. Upgrade dependencies with Composer using `--with-all-dependencies`.
4. Run `composer audit` immediately.
5. Apply Laravel 10 -> 11 compatibility changes.
6. Apply Laravel 11 -> 12 changes.
7. Apply Laravel 12 -> 13 changes.
8. Run the Livewire upgrade tooling where supported, then manually review every generated change.
9. Migrate Livewire browser events, hooks, binding timing and namespace/config.
10. Update Sanctum config/migrations.
11. Run PHP syntax scan.
12. Run clean MySQL `migrate:fresh --force`.
13. Run Laravel boot/about and route list.
14. Compile config and Blade views.
15. Run the complete PHPUnit suite.
16. Run `npm ci && npm run build`.
17. Run `composer audit --locked` again.
18. Add focused regression tests for Product/Brand/Attribute Livewire interactions and auth.
19. Only then merge the tested dependency lock/source into the normal branch.
20. Deploy the exact green commit to QAS and run authenticated EN/AR desktop/mobile smoke tests.

## QAS acceptance focus

- Login / registration / reset / verification.
- Admin login and permissions.
- Product list filters, search, bulk actions and inline editing.
- Product create/edit, variants, uploads, save states and validation.
- Brand and Attribute live search/edit/delete confirmations.
- Customer cart/checkout/order account flows.
- POS critical paths.
- Upload security controls added in SEC-02.
- Locale redirect security from SEC-04.
- Scheduler/queue checks required by the wider Production audit.

## Stop conditions

Do not deploy if any of the following is true:
- Composer audit is red.
- A dependency is abandoned/unmaintained without an explicit accepted replacement plan.
- Livewire UI behavior changes silently because binding timing changed.
- Auth/Sanctum behavior is uncertain.
- Database migration or rollback compatibility is unproven.
- Full tests or production build fail.
