# Dynamic E-commerce System

Production-oriented Laravel e-commerce platform built to be reusable across different store types, with Arabic/English support, dynamic catalog architecture, admin controls, and a phased roadmap toward growth automation, operations, SaaS, and mobile readiness.

## Current Status

- **Baseline:** V35 Cleanup Step 3
- **Current working phase:** Phase 3.3 complete structurally, now in cleanup and production preparation
- **Current focus:** cleanup step 3, UI consistency, translation hardening, payment stabilization planning, and readiness for Phase 4 (WhatsApp-first multi-channel)

## Core Rules

The project follows the official rules in:

- `PROJECT_RULES.md`
- `PROJECT_MASTER_STATUS.md`
- `CURRENT_PHASE.md`

Important development principles:

- Do not break working features
- Deliver full ZIP builds during active development
- Keep Arabic/English support in all features
- Build generic reusable modules, not niche-only logic
- Prefer services and modular architecture over blade-heavy logic
- Keep performance, conversion, and admin control in mind
- Do not rely on memory; rely on project docs

## Main Modules

- Catalog: categories, brands, products, variants, attributes, images, translations
- Commerce: cart, checkout, orders, coupons, refunds, payment flow
- Operations: suppliers, purchases, inventory, profit/cost foundations
- Growth: analytics events, campaigns, automation rules, audience segments, templates
- Platform: permissions, notifications, settings, branding, translations

Full module map:

- `docs/MODULES_OVERVIEW.md`

## Documentation Map

### Project control files
- `PROJECT_RULES.md`
- `PROJECT_MASTER_STATUS.md`
- `CURRENT_PHASE.md`

### Docs folder
- `docs/MODULES_OVERVIEW.md`
- `docs/KNOWN_ISSUES.md`
- `docs/SETUP_GUIDE.md`
- `docs/QA_CHECKLIST.md`
- `docs/TRANSLATION_AUDIT.md`
- `docs/CLEANUP_STEP2_REPORT.md`
- `docs/RELEASE_CHECKLIST.md`
- `docs/archive/NOTES_INDEX.md`


## Production Deploy

This project now includes **Deploy V2** automation for Hostinger:

- `deploy.sh` for one-click production deploy
- `rollback.sh` for quick rollback
- `docs/PRODUCTION_DEPLOY.md` for the full deploy guide

Standard production flow:

```bash
npm run build
git add .
git commit -m "your update message"
git push origin main
```

Then on the server:

```bash
cd /home/u637857322/domains/tag-marketplace.com/laravel_app
chmod +x deploy.sh rollback.sh
./deploy.sh
```

## Setup

### Windows
Run:

```bat
scripts\dev-setup.bat
```

### Linux / macOS / WSL
Run:

```bash
bash scripts/dev-setup.sh
```

### Quick health check

#### Windows
```bat
scripts\health-check.bat
```

#### Linux / macOS / WSL
```bash
bash scripts/health-check.sh
```

## Active Development Rule for `.env`

During active development, project ZIP deliveries **keep the working `.env`** to avoid breaking local setup.

When the project reaches public release / sharing stage, prepare a release-safe package with:

- `.env` removed
- `.env.example` verified
- secrets checked
- production checklist completed

## Notes Archive

Older planning and historical notes were moved out of the project root into:

- `docs/archive/notes/`

This keeps the root cleaner while preserving project history.
