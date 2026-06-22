param(
    [string]$PhpPath = "php",
    [int]$PortalPort = 8088
)

$ErrorActionPreference = "Stop"
$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$TaskPrefix = "Control S Fiscal Hub"
$PowerShell = "$env:SystemRoot\System32\WindowsPowerShell\v1.0\powershell.exe"

function Register-ControlSTask {
    param(
        [string]$Name,
        [string]$Arguments
    )

    $Action = New-ScheduledTaskAction -Execute $PowerShell -Argument $Arguments -WorkingDirectory $ProjectRoot
    $Trigger = New-ScheduledTaskTrigger -AtStartup
    $Settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit (New-TimeSpan -Days 365)
    Register-ScheduledTask -TaskName $Name -Action $Action -Trigger $Trigger -Settings $Settings -Description $Name -Force | Out-Null
}

$PortalScript = Join-Path $ProjectRoot "scripts\windows\run-portal.ps1"
$WorkerScript = Join-Path $ProjectRoot "scripts\windows\run-worker.ps1"

Register-ControlSTask `
    -Name "$TaskPrefix - Portal" `
    -Arguments "-NoProfile -ExecutionPolicy Bypass -File `"$PortalScript`" -PhpPath `"$PhpPath`" -Port $PortalPort"

foreach ($Worker in @("cte", "nfe", "nfse")) {
    Register-ControlSTask `
        -Name "$TaskPrefix - Worker $Worker" `
        -Arguments "-NoProfile -ExecutionPolicy Bypass -File `"$WorkerScript`" -Worker $Worker -PhpPath `"$PhpPath`""
}

Write-Host "Tarefas criadas. Elas iniciam junto com o Windows."
Write-Host "Para iniciar agora, use:"
Write-Host "Start-ScheduledTask -TaskName '$TaskPrefix - Portal'"
Write-Host "Start-ScheduledTask -TaskName '$TaskPrefix - Worker cte'"
Write-Host "Start-ScheduledTask -TaskName '$TaskPrefix - Worker nfe'"
Write-Host "Start-ScheduledTask -TaskName '$TaskPrefix - Worker nfse'"
