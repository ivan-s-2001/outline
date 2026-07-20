@echo off
setlocal EnableExtensions
chcp 65001 >nul

call "%~dp0install.cmd" || exit /b 1
call "%~dp0apply-patches.cmd" || exit /b 1
call "%~dp0check.cmd" || exit /b 1

echo.
echo [OK] Outline Yii 3 полностью подготовлен для Open Server Panel.
echo.
exit /b 0
