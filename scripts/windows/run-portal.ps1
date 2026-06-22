param(
    [string]$PhpPath = "php",
    [string]$HostAddress = "0.0.0.0",
    [int]$Port = 8088
)

$ErrorActionPreference = "Stop"
$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$AppRoot = Join-Path $ProjectRoot "app"
$EnvFile = Join-Path $AppRoot ".env"
$PublicRoot = Join-Path $AppRoot "public"
$LogDir = Join-Path $AppRoot "storage\logs"

if (!(Test-Path $EnvFile)) {
    throw "Arquivo app\.env nao encontrado. Copie .env.windows.example para app\.env e ajuste as configuracoes."
}

New-Item -ItemType Directory -Force -Path $LogDir | Out-Null
Set-Location $AppRoot

# O servidor embutido do PHP escreve mensagens normais no stderr.
# Mantemos os erros de preparacao como "Stop", mas deixamos a execucao do PHP seguir.
$ErrorActionPreference = "Continue"
& $PhpPath -S "${HostAddress}:${Port}" -t $PublicRoot 2>&1 |
    ForEach-Object { $_.ToString() } |
    Tee-Object -FilePath (Join-Path $LogDir "windows_portal.log") -Append
