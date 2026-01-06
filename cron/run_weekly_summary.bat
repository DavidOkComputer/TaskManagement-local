@echo off
REM ============================================================
REM run_weekly_summary.bat
REM Script para ejecutar el resumen semanal
REM Programar para ejecutarse los LUNES a las 8:00 AM
REM ============================================================

REM Configuración
SET PHP_PATH=C:\xampp\php\php.exe
SET PROJECT_PATH=C:\xampp\htdocs\taskManagement
SET CRON_PATH=%PROJECT_PATH%\cron

REM Crear directorio de logs si no existe
IF NOT EXIST "%CRON_PATH%\logs" mkdir "%CRON_PATH%\logs"

REM Obtener fecha actual
FOR /F "tokens=2 delims==" %%I IN ('wmic os get localdatetime /format:list') DO SET datetime=%%I
SET LOG_DATE=%datetime:~0,4%-%datetime:~4,2%-%datetime:~6,2%
SET LOG_TIME=%datetime:~8,2%:%datetime:~10,2%:%datetime:~12,2%

SET LOG_FILE=%CRON_PATH%\logs\weekly_summary_%LOG_DATE%.log

echo [%LOG_TIME%] ========================================== >> "%LOG_FILE%"
echo [%LOG_TIME%] Iniciando envio de resumen semanal >> "%LOG_FILE%"
echo [%LOG_TIME%] ========================================== >> "%LOG_FILE%"

REM Ejecutar script de resumen semanal
"%PHP_PATH%" "%CRON_PATH%\send_weekly_summary.php" >> "%LOG_FILE%" 2>&1

echo [%LOG_TIME%] Proceso completado >> "%LOG_FILE%"
echo. >> "%LOG_FILE%"

exit /b 0