@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul
cd /d "%~dp0\..\.."

set "DBCLI="
where mariadb >nul 2>nul && set "DBCLI=mariadb"
if not defined DBCLI where mysql >nul 2>nul && set "DBCLI=mysql"
if not defined DBCLI (
    echo [ERROR] Клиент MariaDB/MySQL не найден в окружении проекта.
    exit /b 1
)

if not exist "database\patches" (
    echo [OK] Каталог SQL-патчей отсутствует, применять нечего.
    exit /b 0
)

for %%F in (database\patches\*.sql) do (
    echo [PATCH] %%~nxF
    %DBCLI% --protocol=TCP -h MariaDB-11.8 -P 3306 -u root outline_yii3 < "%%F"
    if errorlevel 1 (
        echo [ERROR] Не удалось применить %%~nxF
        exit /b 1
    )
)

echo [OK] Все SQL-патчи MariaDB применены.
exit /b 0
