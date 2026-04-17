@echo off
setlocal
cd /d %~dp0\..

echo [1/7] Checking environment file...
if not exist .env (
  echo ERROR: .env was not found.
  echo Copy .env.example to .env, fill credentials, then run this script again.
  exit /b 1
)

echo [2/7] Installing Composer dependencies if needed...
if not exist vendor (
  composer install
) else (
  echo vendor already exists, skipping composer install.
)

echo [3/7] Installing Node dependencies if needed...
if not exist node_modules (
  npm install
) else (
  echo node_modules already exists, skipping npm install.
)

echo [4/7] Clearing caches...
php artisan optimize:clear

echo [5/7] Ensuring APP_KEY exists...
findstr /b "APP_KEY=base64:" .env >nul
if errorlevel 1 (
  php artisan key:generate --force
) else (
  echo APP_KEY already set, skipping key generation.
)

echo [6/7] Running migrations...
php artisan migrate

echo [7/7] Building frontend assets...
npm run build

echo Done. Project setup completed successfully.
endlocal
