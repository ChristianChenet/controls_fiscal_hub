@echo off
setlocal
title Control S Fiscal Hub - Parar sistema
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\windows\stop-all.ps1"
echo.
pause
