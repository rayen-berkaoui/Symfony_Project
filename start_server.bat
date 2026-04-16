@echo off
cd /d "%~dp0"
echo Clearing Symfony cache...
php bin/console cache:clear
echo.
echo Starting Symfony development server...
php -S 127.0.0.1:8000 -t public
