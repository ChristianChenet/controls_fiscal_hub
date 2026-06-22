@echo off
setlocal
cd /d "%~dp0\..\.."
start "Control S Worker CT-e" powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%cd%\scripts\windows\run-worker.ps1" -Worker cte
start "Control S Worker NF-e" powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%cd%\scripts\windows\run-worker.ps1" -Worker nfe
start "Control S Worker NFS-e" powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%cd%\scripts\windows\run-worker.ps1" -Worker nfse
