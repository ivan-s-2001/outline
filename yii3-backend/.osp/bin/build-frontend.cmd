@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul

set "BACKEND_DIR=%~dp0\..\.."
for %%I in ("%BACKEND_DIR%\..") do set "REPO_DIR=%%~fI"
cd /d "%REPO_DIR%"

echo [BUILD] Проверка Node.js...
where node >nul 2>nul || (
    echo [ERROR] Node.js не найден. Включите Node-24.16.0 в окружении проекта OSP.
    exit /b 1
)
for /f "delims=" %%V in ('node -p "process.versions.node"') do set "NODE_VERSION=%%V"
echo [OK] Node !NODE_VERSION!

where corepack >nul 2>nul || (
    echo [ERROR] Corepack не найден в модуле Node.
    exit /b 1
)
call corepack enable || exit /b 1

where yarn >nul 2>nul || (
    call corepack prepare yarn@4.9.2 --activate || exit /b 1
)

if not exist "node_modules" (
    echo [BUILD] Установка зависимостей Outline...
    call yarn install --immutable || exit /b 1
)

echo [BUILD] Сборка оригинального React-интерфейса Outline...
set "NODE_ENV=production"
call yarn vite build --config vite.yii3.config.ts || exit /b 1

if not exist "yii3-backend\public\static\.vite\manifest.json" (
    echo [ERROR] Vite manifest не создан.
    exit /b 1
)

for %%D in (images fonts logos email) do (
    if exist "server\static\%%D" (
        robocopy "server\static\%%D" "yii3-backend\public\%%D" /MIR /NFL /NDL /NJH /NJS /NP >nul
        if errorlevel 8 exit /b 1
    )
)

if exist "shared\i18n\locales" (
    robocopy "shared\i18n\locales" "yii3-backend\public\locales" /MIR /NFL /NDL /NJH /NJS /NP >nul
    if errorlevel 8 exit /b 1
)

if exist "public" (
    for %%D in (images logos) do (
        if exist "public\%%D" (
            robocopy "public\%%D" "yii3-backend\public\%%D" /E /NFL /NDL /NJH /NJS /NP >nul
            if errorlevel 8 exit /b 1
        )
    )
)

echo [OK] React-интерфейс собран в yii3-backend\public\static.
exit /b 0
