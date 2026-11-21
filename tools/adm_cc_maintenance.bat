@echo off
REM Agendador Windows — manutenção Control Center (purge, storage sync, cron seed)
REM Exemplo Task Scheduler: a cada 15 minutos
"C:\xampp\php\php.exe" "%~dp0..\cron\adm_cc_maintenance.php"
exit /b %ERRORLEVEL%
