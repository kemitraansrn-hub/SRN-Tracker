@echo off
REM Dipanggil dari Windows Task Scheduler buat backup harian.
REM Lihat: app/Console/Commands/BackupData.php
cd /d "%~dp0"
"C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan app:backup >> "%~dp0storage\logs\backup.log" 2>&1
