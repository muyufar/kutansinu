@echo off
echo Installing dependencies...
composer install

echo Generating PDF documentation...
php generate_pdf.php

echo Done!
pause 