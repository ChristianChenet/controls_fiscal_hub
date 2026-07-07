@echo off
setlocal
title Control S Fiscal Hub - Restaurar banco local
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0RESTAURAR_BANCO_LOCAL.ps1"
echo.
echo Processo finalizado. Se ocorreu algum erro, veja a mensagem acima.
pause
