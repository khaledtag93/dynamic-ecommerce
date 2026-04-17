#!/usr/bin/env bash
set -e

echo "== Dynamic E-commerce Health Check =="
php -v >/dev/null 2>&1 && echo "PHP: OK" || echo "PHP: Missing"
php artisan --version && echo "Artisan: OK"
php artisan optimize:clear
php artisan route:list > /dev/null && echo "Routes: OK"
php artisan config:show app.name > /dev/null 2>&1 || true
php artisan migrate:status || true
if [ -f "public/build/manifest.json" ]; then
  echo "Vite build: OK"
else
  echo "Vite build: manifest missing"
fi
if [ -f ".env" ]; then
  echo ".env: present"
else
  echo ".env: missing"
fi
echo "Health check finished."
