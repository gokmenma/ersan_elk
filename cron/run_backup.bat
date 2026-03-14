@echo off
REM Database Backup Cron Job - Windows Batch File
REM This file should be scheduled in Windows Task Scheduler (e.g., daily)

cd /d "%~dp0"
"C:\xampp\php\php.exe" db_backup.php >> logs\backup_batch.log 2>&1
