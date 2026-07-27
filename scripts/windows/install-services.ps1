param(
    [string]$PhpPath = "php",
    [int]$PortalPort = 8088
)

$ErrorActionPreference = "Stop"
$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$ServicePrefix = "ControlSFiscalHub"
$AppRoot = Join-Path $ProjectRoot "app"
$LogDir = Join-Path $ProjectRoot "app\storage\logs"
$CmdExe = "$env:SystemRoot\System32\cmd.exe"

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
        sc.exe stop $Name | Out-Null
        Start-Sleep -Seconds 2
    }
    & $Nssm remove $Name confirm | Out-Null
    sc.exe delete $Name | Out-Null
    Start-Sleep -Seconds 1
}

function Write-ServiceWrapper {
    param(
        [string]$Path,
        [string]$Command
    )

    $content = @"
@echo off
setlocal
cd /d "$AppRoot"
echo [%date% %time%] Iniciando %~nx0
$Command
echo [%date% %time%] Encerrado %~nx0 com codigo %ERRORLEVEL%
exit /b %ERRORLEVEL%
"@
    Set-Content -Path $Path -Value $content -Encoding ASCII
}

function Get-PostgreSqlServiceName {
    $preferredNames = @("postgresql-x64-17", "postgresql-x64-18", "postgresql-x64-16", "postgresql-x64-15")
    foreach ($serviceName in $preferredNames) {
        if (Get-Service -Name $serviceName -ErrorAction SilentlyContinue) {
            return $serviceName
        }
    }

    $detected = Get-Service -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -like "postgresql*" } |
        Sort-Object Name |
        Select-Object -First 1

    if ($detected) { return $detected.Name }
    return $null
}

function Configure-ServiceStartup {
    param(
        [string]$Name,
        [string]$DependencyName
    )

    sc.exe config $Name start= delayed-auto | Out-Null
    if ($DependencyName) {
        sc.exe config $Name depend= $DependencyName | Out-Null
    }
}

function Install-ControlSService {
    param(
        [string]$Name,
        [string]$Description,
        [string]$Application,
        [string[]]$Arguments,
        [string]$AppDirectory,
        [string]$LogName,
        [string]$Nssm,
        [string]$DependencyName
    )

    Stop-And-Remove-Service -Name $Name -Nssm $Nssm

    & $Nssm install $Name $Application | Out-Null
    & $Nssm set $Name AppParameters ($Arguments -join " ") | Out-Null
    & $Nssm set $Name DisplayName $Name | Out-Null
    & $Nssm set $Name Description $Description | Out-Null
    & $Nssm set $Name AppDirectory $AppDirectory | Out-Null
    & $Nssm set $Name Start SERVICE_AUTO_START | Out-Null
    & $Nssm set $Name AppStdout (Join-Path $LogDir $LogName) | Out-Null
    & $Nssm set $Name AppStderr (Join-Path $LogDir $LogName) | Out-Null
    & $Nssm set $Name AppRotateFiles 1 | Out-Null
    & $Nssm set $Name AppRotateOnline 1 | Out-Null
    & $Nssm set $Name AppRotateBytes 10485760 | Out-Null
    & $Nssm set $Name AppExit Default Restart | Out-Null
    Configure-ServiceStartup -Name $Name -DependencyName $DependencyName
}

Assert-Admin
New-Item -ItemType Directory -Force -Path $LogDir | Out-Null
$Nssm = Find-Nssm
$postgreSqlServiceName = Get-PostgreSqlServiceName
if ($postgreSqlServiceName) {
    Write-Host "Dependencia dos servicos: $postgreSqlServiceName"
} else {
    Write-Host "Aviso: servico do PostgreSQL nao encontrado para configurar dependencia." -ForegroundColor Yellow
}
$serviceScriptDir = Join-Path $ProjectRoot "scripts\windows\services"
New-Item -ItemType Directory -Force -Path $serviceScriptDir | Out-Null

foreach ($legacyName in @(
    "${ServicePrefix}Workercte",
    "${ServicePrefix}Workernfe",
    "${ServicePrefix}Workernfse"
)) {
    Stop-And-Remove-Service -Name $legacyName -Nssm $Nssm
}

$portalWrapper = Join-Path $serviceScriptDir "ControlSFiscalHubPortal.cmd"
Write-ServiceWrapper `
    -Path $portalWrapper `
    -Command "`"$PhpPath`" -S 0.0.0.0:$PortalPort -t `"$AppRoot\public`""

Install-ControlSService `
    -Name "${ServicePrefix}Portal" `
    -Description "Portal web do Control S Fiscal Hub." `
    -Application $CmdExe `
    -Arguments @("/d", "/c", "`"`"$portalWrapper`"`"") `
    -AppDirectory $AppRoot `
    -LogName "service_portal.log" `
    -Nssm $Nssm `
    -DependencyName $postgreSqlServiceName

$workers = @{
    "RoboCTe" = "auto_cte_worker.php"
    "RoboNFe" = "auto_nfe_worker.php"
    "RoboNFSe" = "auto_nfse_worker.php"
}

foreach ($worker in $workers.GetEnumerator()) {
    $script = Join-Path $AppRoot ("scripts\" + $worker.Value)
    $workerWrapper = Join-Path $serviceScriptDir ("ControlSFiscalHub" + $worker.Key + ".cmd")
    Write-ServiceWrapper `
        -Path $workerWrapper `
        -Command "`"$PhpPath`" `"$script`""
    Install-ControlSService `
        -Name "${ServicePrefix}$($worker.Key)" `
        -Description "Robo automatico $($worker.Key) do Control S Fiscal Hub." `
        -Application $CmdExe `
        -Arguments @("/d", "/c", "`"`"$workerWrapper`"`"") `
        -AppDirectory $AppRoot `
        -LogName ("service_" + $worker.Key + ".log") `
        -Nssm $Nssm `
        -DependencyName $postgreSqlServiceName
}

foreach ($serviceName in @("${ServicePrefix}Portal", "${ServicePrefix}RoboCTe", "${ServicePrefix}RoboNFe", "${ServicePrefix}RoboNFSe")) {
    $previousErrorAction = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    $startOutput = (& $Nssm start $serviceName 2>&1 | Out-String).Trim()
    $ErrorActionPreference = $previousErrorAction
    Start-Sleep -Seconds 1
    $service = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
    if (!$service -or $service.Status -ne "Running") {
        Write-Host "Aviso: $serviceName nao ficou em execucao. Retorno: $startOutput" -ForegroundColor Yellow
        Write-Host "Verifique o log: $(Join-Path $LogDir ('service_' + $serviceName.Replace($ServicePrefix, '') + '.log'))" -ForegroundColor Yellow
    }
}

Write-Host "Servicos instalados e iniciados no Windows:"
Write-Host "${ServicePrefix}Portal"
Write-Host "${ServicePrefix}RoboCTe"
Write-Host "${ServicePrefix}RoboNFe"
Write-Host "${ServicePrefix}RoboNFSe"
