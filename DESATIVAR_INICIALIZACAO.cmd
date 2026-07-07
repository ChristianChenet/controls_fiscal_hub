@echo off
setlocal
title Control S Fiscal Hub - Desativar inicializacao
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\windows\disable-startup-shortcuts.ps1"
echo.
pause
