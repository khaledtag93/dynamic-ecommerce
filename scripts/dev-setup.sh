#!/usr/bin/env bash
set -e

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

echo "[1/7] Checking environment file..."
if [ ! -f .env ]; then
  echo "ERROR: .env was not found."
  echo "Copy .env.example to .env, fill credentials, then run this script again."
  exit 1
fi

echo "[2/7] Installing Composer dependencies if needed..."
if [ ! -d vendor ]; then
  composer install
else
  echo "vendor already exists, skipping composer install."
fi

echo "[3/7] Installing Node dependencies if needed..."
if [ ! -d node_modules ]; then
  npm install
else
  echo "node_modules already exists, skipping npm install."
fi

echo "[4/7] Clearing caches..."
php artisan optimize:clear

echo "[5/7] Ensuring APP_KEY exists..."
if grep -q '^APP_KEY=$' .env || ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
else
  echo "APP_KEY already set, skipping key generation."
fi

echo "[6/7] Running migrations..."
php artisan migrate

echo "[7/7] Building frontend assets..."
npm run build

echo "Done. Project setup completed successfully."
