@echo off
chcp 65001 >nul
title Ersan E-İmza Yerel Ajanı

echo ========================================================
echo         Ersan E-İmza Yerel Ajanı Başlatılıyor...
echo ========================================================
echo.

where python >nul 2>nul
if %ERRORLEVEL% EQU 0 (
    python "%~dp0ersan_eimza_agent.py"
    goto end
)

where py >nul 2>nul
if %ERRORLEVEL% EQU 0 (
    py -3 "%~dp0ersan_eimza_agent.py"
    goto end
)

echo [HATA] Bilgisayarınızda Python 3 bulunamadı.
echo Lütfen https://www.python.org/downloads/ adresinden Python yükleyiniz.
echo Kurulum sırasında "Add Python to PATH" seçeneğini işaretleyiniz.
echo.
pause

:end
