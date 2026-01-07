@echo off
REM ============================================================
REM run_weekly_summary.bat
REM Script para ejecutar el resumen semanal
REM ============================================================
 
SET PHP_PATH=C:\xampp\php\php.exe
SET PROJECT_PATH=C:\xampp\htdocs\taskManagement
SET CRON_PATH=%PROJECT_PATH%\cron
 
IF NOT EXIST "%CRON_PATH%\logs" mkdir "%CRON_PATH%\logs"
 
FOR /F "tokens=2 delims==" %%I IN ('wmic os get localdatetime /format:list') DO SET datetime=%%I
SET LOG_DATE=%datetime:~0,4%-%datetime:~4,2%-%datetime:~6,2%
SET LOG_TIME=%datetime:~8,2%:%datetime:~10,2%:%datetime:~12,2%
 
REM Use a DIFFERENT log file for the batch wrapper
SET BAT_LOG=%CRON_PATH%\logs\scheduler_%LOG_DATE%.log
 
echo [%LOG_TIME%] Iniciando resumen semanal >> "%BAT_LOG%"
 
REM Let PHP handle its own logging - don't redirect output
"%PHP_PATH%" "%CRON_PATH%\send_weekly_summary.php"
 
echo [%LOG_TIME%] Proceso completado con codigo: %ERRORLEVEL% >> "%BAT_LOG%"
 
exit /b 0