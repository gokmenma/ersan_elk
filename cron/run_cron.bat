@echo off
REM Online Sorgulama Cron Job - Windows Batch File
REM Bu dosyayı Windows Görev Zamanlayıcı ile her dakika çalıştırın

cd /d "%~dp0"
"C:\xampp\php\php.exe" online_sorgulama_cron.php >> logs\cron.log 2>&1
