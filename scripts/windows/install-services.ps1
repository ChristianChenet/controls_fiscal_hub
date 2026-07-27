param(
    [string]$PhpPath = "php",
    [int]$PortalPort = 8088
)

$ErrorActionPreference = "Stop"
$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$PowerShell = "$env:SystemRoot\System32\WindowsPowerShell\v1.0\powershell.exe"
$ServicePrefix = "ControlSFiscalHub"
$LogDir = Join-Path $ProjectRoot "app\storage\logs"

function Assert-Admin {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    if (!$principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw "Execute como Administrador para instalar os servicos do Windows."
    }
}

function Find-Nssm {
    $cmd = Get-Command "nssm.exe" -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }

    $local = Join-Path $ProjectRoot "tools\nssm\nssm.exe"
    if (Test-Path $local) { return $local }

    $tools = Join-Path $ProjectRoot "tools"
    $zip = Join-Path $tools "nssm-2.24.zip"
    $extract = Join-Path $tools "nssm-2.24"
    New-Item -ItemType Directory -Force -Path $tools | Out-Null

    Write-Host "NSSM nao encontrado. Baixando wrapper de servico..."
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    Invoke-WebRequest -Uri "https://nssm.cc/release/nssm-2.24.zip" -OutFile $zip -UseBasicParsing
    if (Test-Path $extract) {
        Remove-Item -LiteralPath $extract -Recurse -Force
    }
    Expand-Archive -LiteralPath $zip -DestinationPath $tools -Force
    $arch = if ([Environment]::Is64BitOperatingSystem) { "win64" } else { "win32" }
    $downloaded = Join-Path $extract "$arch\nssm.exe"
    if (!(Test-Path $downloaded)) {
        throw "Nao foi possivel preparar o NSSM."
    }
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $local) | Out-Null
    Copy-Item -LiteralPath $downloaded -Destination $local -Force
    return $local
}

function Stop-And-Remove-Service {
    param([string]$Name, [string]$Nssm)
    $service = Get-Service -Name $Name -ErrorAction SilentlyContinue
    if (!$service) { return }
    if ($service.Status -ne "Stopped") {
        & $Nssm stop $Name | Out-Null
        Start-Sleep -Seconds 2
    }
    & $Nssm remove $Name confirm | Out-Null
    Start-Sleep -Seconds 1
}

function Install-ControlSService {
    param(
        [string]$Name,
        [string]$DisplayName,
        [string]$Description,
        [string]$Script,
        [string[]]$ExtraArgs,
        [string]$LogName,
        [string]$Nssm
    )

    Stop-And-Remove-Service -Name $Name -Nssm $Nssm

    $args = @("-NoProfile", "-ExecutionPolicy", "Bypass", "-File", "`"$Script`"", "-PhpPath", "`"$PhpPath`"") + $ExtraArgs
    & $Nssm install $Name $PowerShell ($args -join " ") | Out-Null
    & $Nssm set $Name DisplayName $DisplayName | Out-Null
    & $Nssm set $Name Description $Description | Out-Null
    & $Nssm set $Name AppDirectory $ProjectRoot | Out-Null
    & $Nssm set $Name Start SERVICE_AUTO_START | Out-Null
    & $Nssm set $Name AppStdout (Join-Path $LogDir $LogName) | Out-Null
    & $Nssm set $Name AppStderr (Join-Path $LogDir $LogName) | Out-Null
    & $Nssm set $Name AppRotateFiles 1 | Out-Null
    & $Nssm set $Name AppRotateOnline 1 | Out-Null
    & $Nssm set $Name AppRotateBytes 10485760 | Out-Null
    & $Nssm set $Name AppExit Default Restart | Out-Null
}

Assert-Admin
New-Item -ItemType Directory -Force -Path $LogDir | Out-Null
$Nssm = Find-Nssm

$portalScript = Join-Path $ProjectRoot "scripts\windows\run-portal.ps1"
$workerScript = Join-Path $ProjectRoot "scripts\windows\run-worker.ps1"

Install-ControlSService `
    -Name "${ServicePrefix}Portal" `
    -DisplayName "Control S Fiscal Hub - Portal" `
    -Description "Portal web do Control S Fiscal Hub." `
    -Script $portalScript `
    -ExtraArgs @("-Port", "$PortalPort") `
    -LogName "service_portal.log" `
    -Nssm $Nssm

foreach ($worker in @("cte", "nfe", "nfse")) {
    Install-ControlSService `
        -Name "${ServicePrefix}Worker$worker" `
        -DisplayName "Control S Fiscal Hub - Robo $worker" `
        -Description "Robo automatico $worker do Control S Fiscal Hub." `
        -Script $workerScript `
        -ExtraArgs @("-Worker", $worker) `
        -LogName "service_worker_$worker.log" `
        -Nssm $Nssm
}

foreach ($serviceName in @("${ServicePrefix}Portal", "${ServicePrefix}Workercte", "${ServicePrefix}Workernfe", "${ServicePrefix}Workernfse")) {
    & $Nssm start $serviceName | Out-Null
}

Write-Host "Servicos instalados e iniciados no Windows:"
Write-Host "Control S Fiscal Hub - Portal"
Write-Host "Control S Fiscal Hub - Robo cte"
Write-Host "Control S Fiscal Hub - Robo nfe"
Write-Host "Control S Fiscal Hub - Robo nfse"
