@echo off
REM Setup + run PHP backend & frontend on Windows (DB on VPS - no local DB needed)
cd /d "%~dp0"

echo === [1/5] Check PHP pgsql extension ===
php -m | findstr /i pdo_pgsql >nul || (
    echo [ERROR] PHP is missing pdo_pgsql extension.
    echo Open php.ini and enable: extension=pdo_pgsql and extension=pgsql
    pause
    exit /b 1
)

echo === [2/5] Setup backend-php ===
cd backend-php
if not exist .env copy .env.example .env
call composer install
REM Generate APP_KEY only if empty
findstr /x "APP_KEY=" .env >nul && call php artisan key:generate
cd ..

echo === [3/5] Setup frontend ===
cd frontend
call npm install
cd ..

echo === [4/5] Start backend (port 8080) ===
start "backend-php" cmd /k "cd /d %~dp0backend-php && php artisan serve --port=8080"

echo === [5/5] Start frontend (Vite) ===
start "frontend" cmd /k "cd /d %~dp0frontend && npm run dev"

echo.
echo Backend:  http://localhost:8080
echo Frontend: http://localhost:5173
