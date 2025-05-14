@echo off
echo Installing dependencies...
C:\xampp\php\php.exe C:\xampp\composer.phar install

echo Generating PDF documentation...
C:\xampp\php\php.exe generate_pdf.php

echo Done!
pause 