@echo off
echo ========================================
echo  Generator PDF - Penginputan Keuangan
echo ========================================
echo.

cd /d "%~dp0.."

echo [1/3] Installing dependencies...
call composer install --no-interaction

echo.
echo [2/3] Capturing screenshots from LIVE server...
cd docs
if not exist capture.env (
    echo Peringatan: docs\capture.env belum ada.
    echo Salin capture.env.example ke capture.env dan isi username/password live.
    echo Login page tetap di-capture dari live; halaman lain memakai fallback lokal.
)
node capture_keuangan_screenshots.mjs

echo.
echo [3/3] Generating PDF...
php generate_keuangan_pdf.php

echo.
echo ========================================
echo  SELESAI!
echo  File: docs\panduan_penginputan_keuangan.pdf
echo ========================================
pause
