@echo off
REM ============================================================
REM run_cron_tasks.bat
REM Script maestro para ejecutar tareas programadas del sistema
REM ============================================================

REM Configuración
SET PHP_PATH=C:\xampp\php\php.exe
SET PROJECT_PATH=C:\xampp\htdocs\taskManagement
SET CRON_PATH=%PROJECT_PATH%\cron

REM Obtener fecha y hora actual para el log
FOR /F "tokens=2 delims==" %%I IN ('wmic os get localdatetime /format:list') DO SET datetime=%%I
SET LOG_DATE=%datetime:~0,4%-%datetime:~4,2%-%datetime:~6,2%
SET LOG_TIME=%datetime:~8,2%:%datetime:~10,2%:%datetime:~12,2%

REM Archivo de log maestro
SET MASTER_LOG=%CRON_PATH%\logs\cron_master_%LOG_DATE%.log

echo [%LOG_TIME%] ========================================== >> "%MASTER_LOG%"
echo [%LOG_TIME%] Iniciando ejecucion de check deadlines >> "%MASTER_LOG%"
echo [%LOG_TIME%] ========================================== >> "%MASTER_LOG%"

REM Ejecutar verificacion de vencimientos
echo [%LOG_TIME%] Ejecutando check_deadlines.php... >> "%MASTER_LOG%"
"%PHP_PATH%" "%CRON_PATH%\check_deadlines.php" >> "%MASTER_LOG%" 2>&1

echo [%LOG_TIME%] Tareas completadas >> "%MASTER_LOG%"
echo. >> "%MASTER_LOG%"

exit /b 0