@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul
cd /d "%~dp0\..\.."

echo [CHECK] Composer configuration...
call composer validate --no-check-publish || exit /b 1

echo [CHECK] PHP syntax...
set "LINT_FAILED=0"
for %%D in (src config public) do (
    if exist "%%D" (
        for /r "%%D" %%F in (*.php) do (
            php -l "%%F" >nul 2>&1 || (
                echo [ERROR] Ошибка синтаксиса: %%F
                php -l "%%F"
                set "LINT_FAILED=1"
            )
        )
    )
)
if "!LINT_FAILED!"=="1" exit /b 1

echo [CHECK] Yii configuration cache...
call composer yii-config-rebuild || exit /b 1

set "DBCLI="
where mariadb >nul 2>nul && set "DBCLI=mariadb"
if not defined DBCLI where mysql >nul 2>nul && set "DBCLI=mysql"
if not defined DBCLI (
    echo [ERROR] Клиент MariaDB/MySQL не найден.
    exit /b 1
)

for /f "usebackq delims=" %%C in (`%DBCLI% --protocol=TCP -N -B -h MariaDB-11.8 -P 3306 -u root -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='outline_yii3';"`) do set "TABLE_COUNT=%%C"
if not defined TABLE_COUNT (
    echo [ERROR] Не удалось проверить базу outline_yii3.
    exit /b 1
)
if !TABLE_COUNT! LSS 40 (
    echo [ERROR] В базе найдено только !TABLE_COUNT! таблиц. Схема импортирована не полностью.
    exit /b 1
)
echo [OK] MariaDB: !TABLE_COUNT! таблиц.

where redis-cli >nul 2>nul && (
    for /f "delims=" %%R in ('redis-cli -h Redis -p 6379 ping 2^>nul') do set "REDIS_RESULT=%%R"
    if /i not "!REDIS_RESULT!"=="PONG" (
        echo [ERROR] Redis не отвечает PONG.
        exit /b 1
    )
    echo [OK] Redis доступен.
)

where curl >nul 2>nul && (
    curl --silent --show-error --fail --insecure https://outline.local/health >nul 2>&1
    if errorlevel 1 (
        echo [WARN] HTTP health-check пока недоступен. Перезапустите проект OSP после установки.
    ) else (
        echo [OK] HTTP health-check доступен.
    )
)

echo [OK] Проверки завершены.
exit /b 0
