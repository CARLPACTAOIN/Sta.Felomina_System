@echo off
rem ==============================================================================
rem Barangay Sta. Felomina Document Archiving System - Windows Restore Script
rem Compatible with Windows and Laragon environments.
rem ==============================================================================

setlocal enabledelayedexpansion

set "SCRIPT_DIR=%~dp0"
set "ROOT_DIR=%SCRIPT_DIR%.."

cd /d "%ROOT_DIR%"

echo ========================================================================
echo Barangay Sta. Felomina Document Archiving System - Disaster Recovery
echo ========================================================================

rem Detect PHP executable
where php >nul 2>nul
if %ERRORLEVEL% neq 0 (
    if exist "C:\laragon\bin\php\php-8.4*\php.exe" (
        for /d %%D in (C:\laragon\bin\php\php-8.4*) do (
            set "PATH=%%D;!PATH!"
        )
    )
)

php artisan backup:restore %*
set "EXIT_CODE=%ERRORLEVEL%"

if %EXIT_CODE% equ 0 (
    echo [%DATE% %TIME%] Restore completed successfully.
) else (
    echo [%DATE% %TIME%] Restore failed with exit code %EXIT_CODE%.
)

exit /b %EXIT_CODE%
