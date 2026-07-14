@echo off
setlocal
where php >nul 2>nul
if %errorlevel%==0 (
    set PHP=php
) else (
    set PHP=%~dp0..\..\..\..\..\php\php.exe
)
"%PHP%" -d display_startup_errors=0 "%~dp0phpunit.phar" %*
