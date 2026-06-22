@echo off
setlocal
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\windows\gerar-diagnostico.ps1" -AppRoot "%~dp0."
pause
