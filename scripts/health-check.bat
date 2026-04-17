@echo off
echo == Dynamic E-commerce Health Check ==
php -v >nul 2>nul && echo PHP: OK || echo PHP: Missing
php artisan --version && echo Artisan: OK
php artisan optimize:clear
php artisan route:list >nul && echo Routes: OK
php artisan migrate:status
if exist public\build\manifest.json (
  echo Vite build: OK
) else (
  echo Vite build: manifest missing
)
if exist .env (
  echo .env: present
) else (
  echo .env: missing
)
echo Health check finished.
