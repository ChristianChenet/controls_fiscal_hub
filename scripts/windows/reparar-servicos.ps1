param(
    [string]$PhpPath = "",
    [int]$PortalPort = 8088
)

$ErrorActionPreference = "Stop"
$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$AppRoot = Join-Path $ProjectRoot "app"
$LogDir = Join-Path $AppRoot "storage\logs"
$RepairLog = Join-Path $LogDir "service_repair.log"

function Write-RepairLog {
    param([string]$Text)
    $line = "[" + (Get-Date -Format "yyyy-MM-dd HH:mm:ss") + "] " + $Text
    Write-Host $line
    Add-Content -Path $RepairLog -Value $line
}

function Assert-Admin {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    if (!$principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw "Abra o PowerShell como Administrador para reparar os servicos."
    }
}

function Find-PHP {
    if ($PhpPath -and (Test-Path $PhpPath)) {
        return (Resolve-Path $PhpPath).Path
    }

    $localPhp = Join-Path $ProjectRoot "tools\php\php.exe"
    if (Test-Path $localPhp) {
        return $localPhp
    }

    $cmd = Get-Command "php.exe" -ErrorAction SilentlyContinue
    if ($cmd) {
        return $cmd.Source
    }

    $candidates = @()
    $candidates += Get-ChildItem "C:\tools" -Filter "php.exe" -Recurse -ErrorAction SilentlyContinue
    $candidates += Get-ChildItem "C:\Program Files" -Filter "php.exe" -Recurse -ErrorAction SilentlyContinue |
        Where-Object { $_.FullName -match "\\php" }

    if ($candidates.Count -gt 0) {
        return ($candidates | Sort-Object FullName -Descending | Select-Object -First 1).FullName
    }

    throw "PHP nao encontrado. Execute o instalador completo ou informe -PhpPath."
}

function Stop-Port {
    param([int]$Port)
    $rows = netstat -ano | Select-String ":$Port\s"
    foreach ($row in $rows) {
        if ($row.Line -notmatch "LISTENING") { continue }
        $parts = $row.Line -split "\s+"
        $processId = [int]$parts[-1]
        $proc = Get-Process -Id $processId -ErrorAction SilentlyContinue
        if ($proc) {
            Write-RepairLog "Liberando porta ${Port}: parando PID $processId ($($proc.ProcessName))."
            Stop-Process -Id $processId -Force -ErrorAction SilentlyContinue
        }
    }
}

function Stop-ServiceIfExists {
    param([string]$Name)
    $service = Get-Service -Name $Name -ErrorAction SilentlyContinue
    if ($service -and $service.Status -ne "Stopped") {
        Write-RepairLog "Parando servico $Name."
        Stop-Service -Name $Name -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
    }
}

Assert-Admin
New-Item -ItemType Directory -Force -Path $LogDir | Out-Null
Write-RepairLog "Inicio do reparo dos servicos do Control S Fiscal Hub."

foreach ($serviceName in @(
    "ControlSFiscalHubPortal",
    "ControlSFiscalHubRoboCTe",
    "ControlSFiscalHubRoboNFe",
    "ControlSFiscalHubRoboNFSe",
    "ControlSFiscalHubWorkercte",
    "ControlSFiscalHubWorkernfe",
    "ControlSFiscalHubWorkernfse"
)) {
    Stop-ServiceIfExists $serviceName
}

foreach ($taskName in @(
    "Control S Fiscal Hub - Portal",
    "Control S Fiscal Hub - Worker cte",
    "Control S Fiscal Hub - Worker nfe",
    "Control S Fiscal Hub - Worker nfse"
)) {
    try {
        Write-RepairLog "Desativando tarefa antiga/conflitante: $taskName."
        Stop-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
        Disable-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue | Out-Null
    } catch {
    }
}

Stop-Port $PortalPort

$resolvedPhp = Find-PHP
Write-RepairLog "PHP usado nos servicos: $resolvedPhp"

$installer = Join-Path $PSScriptRoot "install-services.ps1"
& powershell.exe -NoProfile -ExecutionPolicy Bypass -File $installer -PhpPath $resolvedPhp -PortalPort $PortalPort

Start-Sleep -Seconds 5

$expected = @(
    "ControlSFiscalHubPortal",
    "ControlSFiscalHubRoboCTe",
    "ControlSFiscalHubRoboNFe",
    "ControlSFiscalHubRoboNFSe"
)

foreach ($serviceName in $expected) {
    $service = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
    if (!$service) {
        Write-RepairLog "ERRO: servico nao encontrado apos reparo: $serviceName"
        continue
    }
    Write-RepairLog "${serviceName}: $($service.Status)"
}

foreach ($logName in @("service_portal.log", "service_RoboCTe.log", "service_RoboNFe.log", "service_RoboNFSe.log")) {
    $logPath = Join-Path $LogDir $logName
    if (Test-Path $logPath) {
        Write-RepairLog "Ultimas linhas de ${logName}:"
        Get-Content -LiteralPath $logPath -Tail 20 | ForEach-Object { Write-RepairLog "  $_" }
    } else {
        Write-RepairLog "Log ainda nao encontrado: $logName"
    }
}

$listening = netstat -ano | Select-String ":$PortalPort\s" | Select-String "LISTENING"
if ($listening) {
    Write-RepairLog "Portal ouvindo na porta $PortalPort."
} else {
    Write-RepairLog "ATENCAO: portal ainda nao apareceu ouvindo na porta $PortalPort."
}

Write-RepairLog "Reparo concluido."
