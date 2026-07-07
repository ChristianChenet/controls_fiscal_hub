param(
    [Parameter(Mandatory = $true)]
    [ValidateSet("cte", "nfe", "nfse")]
    [string]$Worker,

    [string]$PhpPath = "php"
)

$ErrorActionPreference = "Stop"
$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$AppRoot = Join-Path $ProjectRoot "app"
$EnvFile = Join-Path $AppRoot ".env"
$LogDir = Join-Path $AppRoot "storage\logs"

if (!(Test-Path $EnvFile)) {
    throw "Arquivo app\.env nao encontrado. Copie .env.windows.example para app\.env e ajuste as configuracoes."
}

$ScriptMap = @{
    cte = "auto_cte_worker.php"
    nfe = "auto_nfe_worker.php"
    nfse = "auto_nfse_worker.php"
}

New-Item -ItemType Directory -Force -Path $LogDir | Out-Null
Set-Location $AppRoot

$WorkerScript = Join-Path $AppRoot ("scripts\" + $ScriptMap[$Worker])
$ErrorActionPreference = "Continue"
& $PhpPath $WorkerScript 2>&1 |
    ForEach-Object { $_.ToString() } |
    Tee-Object -FilePath (Join-Path $LogDir ("windows_worker_" + $Worker + ".log")) -Append
