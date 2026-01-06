@echo off
REM ============================================================================
REM check_inactive_items.bat
REM Script batch para ejecutar verificación de items inactivos en Windows
REM ============================================================================

REM Configurar la ruta de PHP
REM XAMPP: C:\xampp\php\php.exe
REM WAMP: C:\wamp64\bin\php\php7.x.x\php.exe
REM PHP Standalone: C:\php\php.exe
SET PHP_PATH=C:\xampp\php\php.exe

REM Ruta del script PHP 
SET SCRIPT_PATH=%~dp0check_inactive_items.php

REM Configurar zona horaria 
SET TZ=America/Mexico_City

REM Cambiar al directorio del script
cd /d "%~dp0"

REM Ejecutar el script PHP
"%PHP_PATH%" -f "%SCRIPT_PATH%"

REM Crear log con timestamp
echo [%date% %time%] Script ejecutado >> "%~dp0logs\cron_inactive.log"

REM Salir
exit /b
