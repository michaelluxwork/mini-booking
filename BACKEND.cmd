@echo off
cd /d "%~dp0backend"
"%~dp0.tools\php\php.exe" artisan serve --host=127.0.0.1 --port=8017 --no-reload
