# Server Access Runbook

This runbook exists because the project owner prefers explicit reminders for server operations and should not be expected to remember SSH/deploy commands.

## Rule for every server task
Always guide the owner from the very beginning:
1. Open Windows PowerShell.
2. Paste the SSH command.
3. Explain any first-connection prompt.
4. Explain that the SSH password is typed invisibly.
5. Wait until the shell prompt appears.
6. Only then provide the next command(s), one small step at a time.
7. Never assume the owner remembers previous server commands.

## Current known Hostinger SSH access
Historical/current known command:

```bash
ssh -p 65002 u637857322@145.79.20.185
```

If this command stops working, verify the current SSH host, username, and port from Hostinger before changing anything.

## First connection
If PowerShell shows:

```text
Are you sure you want to continue connecting (yes/no/[fingerprint])?
```

enter:

```text
yes
```

Then enter the current SSH password. The password will not be displayed while typing. Never store the password in this repository or documentation.

A successful login should end at a prompt similar to:

```text
u637857322@...:~$
```

At that point, stop and continue with the task-specific instructions.

## Production paths
Laravel application:

```text
/home/u637857322/domains/tag-marketplace.com/laravel_app
```

Public webroot:

```text
/home/u637857322/domains/tag-marketplace.com/public_html
```

## Safety
- Never ask the owner to paste secrets into Git or repository files.
- Never use `git push -f origin main` as part of the normal workflow.
- Never delete/reinitialize `.git` on the production server as a normal deploy step.
- Do not run migrations or deploy scripts until the current task explicitly reaches that stage.
- Keep `main` / production untouched until the release gate is approved.


### Production server discovery — 2026-09-20
- SSH login to Hostinger succeeded.
- The historical application path `/home/u637857322/domains/tag-marketplace.com/laravel_app` exists.
- Running `git status --short --branch` inside that directory returned: `fatal: not a git repository`.
- Therefore the current production Laravel directory is not a Git worktree. Do not run fetch/pull/reset/checkout there until the actual deployment layout is inspected and a safe migration/rehearsal plan is chosen.


### Production filesystem inspection — 2026-09-20
At `/home/u637857322/domains/tag-marketplace.com/laravel_app`:
- Laravel application files are present, including `artisan`, `composer.json`, `vendor/`, `storage/`, `.env`, `deploy.sh`, and `rollback.sh`.
- No `.git/` directory is present; this is a deployed file snapshot, not a Git checkout.
- Most application files are dated around 2026-04-17, while `.env` is server-local.
- Do not convert this live production directory into a Git worktree in place before a controlled rehearsal/backup plan.
- Preferred next step: inspect server runtime/tooling and free space, then prepare a separate V42 rehearsal directory so production remains untouched.


### Production runtime check — 2026-09-20
- Hostinger CLI PHP version: `PHP 8.3.33` (NTS) with Zend OPcache.
- This satisfies the application's Composer requirement (`php ^8.1`) at the runtime version level.


### Production Composer check — 2026-09-20
- Hostinger Composer version: `2.9.8`.
- Composer is running under PHP `8.3.33` from `/opt/alt/php83/usr/bin/php`.


### Production Laravel runtime check — 2026-09-20
Hostinger production runtime currently reports:
- Application: Tag Marketplace
- Laravel: 10.48.29
- PHP: 8.3.33
- Composer: 2.9.8
- Environment: production
- Maintenance mode: OFF
- APP_DEBUG/runtime debug mode: ENABLED

Security/release note:
- Debug mode being enabled in production is a release blocker and should be changed to disabled before the next production deployment.
- Do not change it blindly mid-audit; verify the current server .env values first, then update in a controlled step and clear/rebuild config cache.


### Production debug configuration fix — 2026-09-20
- Confirmed production `.env` had `APP_DEBUG=true`.
- Created a server-side backup: `.env.backup_before_debug_fix`.
- Updated production `.env` to `APP_DEBUG=false`.
- Next step is to rebuild Laravel config cache and verify runtime reports Debug Mode disabled.


### Production debug verification — 2026-09-20
- Rebuilt Laravel config cache after changing production `APP_DEBUG=false`.
- Verified with `php artisan about --only=environment` that runtime Debug Mode is now OFF.
- Production remains out of maintenance mode.
