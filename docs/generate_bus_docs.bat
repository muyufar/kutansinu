@echo off
echo ========================================
echo  Generator Dokumentasi PDF - User Bus
echo ========================================
echo.

cd /d "%~dp0.."

echo [1/3] Installing PHP dependencies...
call composer install --no-interaction
if errorlevel 1 (
    echo Gagal install composer dependencies.
    pause
    exit /b 1
)

echo.
echo [2/3] Capturing screenshots...
cd docs
call npx --yes playwright install chromium
if errorlevel 1 (
    echo Peringatan: Playwright gagal, lanjut tanpa screenshot live.
)
node capture_bus_screenshots.mjs
if errorlevel 1 (
    echo Peringatan: Capture screenshot gagal sebagian.
)

echo.
echo [3/3] Generating PDF...
php generate_bus_pdf.php
if errorlevel 1 (
    echo Gagal generate PDF.
    pause
    exit /b 1
)

echo.
echo ========================================
echo  SELESAI!
echo  File: docs\panduan_user_bus.pdf
echo ========================================
pause
