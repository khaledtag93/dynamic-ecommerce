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
