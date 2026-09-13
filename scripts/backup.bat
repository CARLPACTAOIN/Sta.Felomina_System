@echo off
rem ==============================================================================
rem Barangay Sta. Felomina Document Archiving System - Windows Backup Script
rem Compatible with Windows Task Scheduler and Laragon environments.
rem ==============================================================================

setlocal enabledelayedexpansion

set "SCRIPT_DIR=%~dp0"
set "ROOT_DIR=%SCRIPT_DIR%.."

cd /d "%ROOT_DIR%"

echo [%DATE% %TIME%] Starting Barangay System backup...

rem Detect PHP executable
where php >nul 2>nul
if %ERRORLEVEL% neq 0 (
    if exist "C:\laragon\bin\php\php-8.4*\php.exe" (
        for /d %%D in (C:\laragon\bin\php\php-8.4*) do (
            set "PATH=%%D;!PATH!"
        )
    )
)

php artisan backup:create --no-interaction %*
set "EXIT_CODE=%ERRORLEVEL%"

if %EXIT_CODE% equ 0 (
    echo [%DATE% %TIME%] Backup completed successfully.
) else (
    echo [%DATE% %TIME%] Backup failed with exit code %EXIT_CODE%.
)

exit /b %EXIT_CODE%
