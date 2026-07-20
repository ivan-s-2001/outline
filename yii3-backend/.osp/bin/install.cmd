@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul

cd /d "%~dp0\..\.."

echo.
echo ============================================================
echo   Outline Yii 3 - установка для Open Server Panel
echo ============================================================
echo.

where php >nul 2>nul || (
    echo [ERROR] PHP не найден в окружении проекта.
    exit /b 1
)

php -r "if (PHP_VERSION_ID < 80200 || PHP_VERSION_ID >= 80600) {fwrite(STDERR, 'Required PHP 8.2-8.5, current: '.PHP_VERSION.PHP_EOL); exit(1);} foreach (['ctype','filter','json','mbstring','pdo','pdo_mysql'] as $ext) {if (!extension_loaded($ext)) {fwrite(STDERR, 'Missing PHP extension: '.$ext.PHP_EOL); exit(1);}} echo 'PHP '.PHP_VERSION.' OK'.PHP_EOL;" || exit /b 1

where composer >nul 2>nul || (
    echo [ERROR] Composer не найден. Включите модуль System в окружении OSP.
    exit /b 1
)

if not exist ".env" copy /y ".env.example" ".env" >nul
if not exist "runtime" mkdir "runtime"
if not exist "runtime\uploads" mkdir "runtime\uploads"

call composer install --no-interaction --prefer-dist --optimize-autoloader || exit /b 1
call composer yii-config-rebuild || exit /b 1

set "DBCLI="
where mariadb >nul 2>nul && set "DBCLI=mariadb"
if not defined DBCLI where mysql >nul 2>nul && set "DBCLI=mysql"
if not defined DBCLI (
    echo [ERROR] Клиент MariaDB/MySQL не найден в окружении проекта.
    exit /b 1
)

%DBCLI% --protocol=TCP -h MariaDB-11.8 -P 3306 -u root -e "CREATE DATABASE IF NOT EXISTS outline_yii3 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || exit /b 1
%DBCLI% --protocol=TCP -h MariaDB-11.8 -P 3306 -u root outline_yii3 < "database\schema.sql" || exit /b 1

echo [OK] Схема MariaDB импортирована.

where redis-cli >nul 2>nul && (
    for /f "delims=" %%R in ('redis-cli -h Redis -p 6379 ping 2^>nul') do set "REDIS_RESULT=%%R"
    if /i "!REDIS_RESULT!"=="PONG" (
        echo [OK] Redis отвечает PONG.
    ) else (
        echo [WARN] Redis не ответил. Проверьте модуль Redis в окружении OSP.
    )
) || echo [WARN] redis-cli не найден, проверка Redis пропущена.

call "%~dp0check.cmd" || exit /b 1

echo.
echo [OK] Установка завершена.
echo Откройте: https://outline.local/health
echo.
exit /b 0
