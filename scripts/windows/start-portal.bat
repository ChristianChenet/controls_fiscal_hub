@echo off
setlocal
cd /d "%~dp0\..\.."
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%cd%\scripts\windows\run-portal.ps1"
