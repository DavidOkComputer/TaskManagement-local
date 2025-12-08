@echo off
REM ============================================================================
REM check_overdue_projects.bat
REM Script batch para ejecutar verificación de proyectos vencidos en Windows
REM ============================================================================

REM Configurar la ruta de PHP (ajustar según tu instalación)
REM XAMPP: C:\xampp\php\php.exe
REM WAMP: C:\wamp64\bin\php\php7.x.x\php.exe
REM PHP Standalone: C:\php\php.exe
SET PHP_PATH=C:\xampp\php\php.exe

REM Ruta del script PHP (ajustar a tu ubicación)
SET SCRIPT_PATH=%~dp0check_overdue_projects.php

REM Configurar zona horaria (opcional)
SET TZ=America/Mexico_City

REM Cambiar al directorio del script
cd /d "%~dp0"

REM Ejecutar el script PHP
"%PHP_PATH%" -f "%SCRIPT_PATH%"

REM Opcional: Crear log con timestamp
echo [%date% %time%] Script ejecutado >> "%~dp0logs\cron_overdue.log"

REM Salir
exit /b
