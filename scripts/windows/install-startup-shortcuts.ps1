$ErrorActionPreference = "Stop"

$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$Startup = [Environment]::GetFolderPath("Startup")
$Shell = New-Object -ComObject WScript.Shell

function New-StartupShortcut {
    param(
        [string]$Name,
        [string]$Target,
        [string]$Arguments = "",
        [string]$WorkingDirectory = $ProjectRoot,
        [int]$WindowStyle = 7
    )

    $Path = Join-Path $Startup ($Name + ".lnk")
    $Shortcut = $Shell.CreateShortcut($Path)
    $Shortcut.TargetPath = $Target
    $Shortcut.Arguments = $Arguments
    $Shortcut.WorkingDirectory = $WorkingDirectory
    $Shortcut.WindowStyle = $WindowStyle
    $Shortcut.IconLocation = "shell32.dll,220"
    $Shortcut.Save()
    Write-Host "Atalho de inicializacao criado: $Path"
}

New-StartupShortcut `
    -Name "Control S Fiscal Hub - Portal" `
    -Target (Join-Path $ProjectRoot "scripts\windows\start-portal.bat")

New-StartupShortcut `
    -Name "Control S Fiscal Hub - Robos" `
    -Target (Join-Path $ProjectRoot "scripts\windows\start-workers.bat")

New-StartupShortcut `
    -Name "Control S Fiscal Hub - Abrir navegador" `
    -Target (Join-Path $ProjectRoot "scripts\windows\open-portal-browser.bat") `
    -WindowStyle 7

Write-Host "Inicializacao automatica configurada para o usuario atual."
