# Setup Guide

## Active development workflow
This project keeps the real `.env` file inside active development ZIP deliveries so the project can open immediately without repeated environment recreation.

## Windows
Run:
```bat
scripts\dev-setup.bat
```

## Linux / macOS / Git Bash
Run:
```bash
bash scripts/dev-setup.sh
```

## What the scripts do
- check that `.env` exists
- install Composer dependencies if missing
- install Node dependencies if missing
- clear cached config/view/routes
- generate app key only if missing
- run migrations
- build frontend assets

## If `.env` is missing
1. Copy `.env.example` to `.env`
2. Fill database credentials and API keys
3. Run the setup script again

## Release packaging rule
Keep `.env` during active development.
Only switch to `.env.example` when preparing a public/release-safe package.
