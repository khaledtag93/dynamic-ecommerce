# Dynamic E-commerce System

Production-oriented Laravel e-commerce platform built to be reusable across different store types, with Arabic/English support, dynamic catalog architecture, admin controls, commerce/operations foundations, and a roadmap toward growth automation, multi-channel messaging, SaaS, and mobile readiness.

## Current Status

- **Official baseline:** V42
- **Baseline focus:** Cost Calculator refactor + Arabic/English translation updates
- **Current phase:** clean Git baseline & production hardening
- **Canonical source:** GitHub `main`

See:
- `PROJECT_MASTER_STATUS.md`
- `CURRENT_PHASE.md`
- `PROJECT_RULES.md`
- `DEPLOY_FLOW.md`
- `TEST_PLAN.md`

## Development Workflow

Starting with V42, Git is the source of truth. Do not create a new full project folder for every normal version.

Use branches/commits for changes and tags/releases for stable milestones. ZIP packages may still be created for backup or handoff when useful.

## Security Rule

Real environment values are never committed.

- `.env` stays local/server-side only.
- Commit only safe examples such as `.env.example`.
- Production/CI credentials belong in approved secret stores such as GitHub Actions Secrets.
- Any credential previously exposed in public Git history must be rotated.

## Main Modules

- Catalog: categories, brands, products, variants, attributes, images, translations
- Commerce: cart, checkout, orders, coupons, refunds, payment flow
- Operations: suppliers, purchases, inventory, cost/profit foundations, Cost Calculator
- Growth: analytics events, campaigns, automation rules, audience segments, templates
- Platform: permissions, notifications, settings, branding, translations, deploy tooling

## Production Deploy

Production deploy/rollback scripts:

- `deploy.sh`
- `rollback.sh`
- `DEPLOY_FLOW.md`

Production deploys are based on a known commit on `main`, preserve server secrets/uploads, create a recoverable application backup, and require post-deploy verification.

## Setup

### Windows

```bat
scripts\dev-setup.bat
```

### Linux / macOS / WSL

```bash
bash scripts/dev-setup.sh
```

### Quick health check

Windows:

```bat
scripts\health-check.bat
```

Linux / macOS / WSL:

```bash
bash scripts/health-check.sh
```

## Documentation Rule

Project documentation is part of the definition of done. Meaningful changes must update the project status/current phase so the repository—not chat memory—remains the durable project record.
