param(
    [string]$PgBin = "",
    [string]$Database = "control_s_fiscal_hub",
    [string]$User = "controls",
    [string]$HostName = "127.0.0.1",
    [int]$Port = 5432
)

$ErrorActionPreference = "Stop"
$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$BackupDir = Join-Path $ProjectRoot "backup"
New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null

$Stamp = Get-Date -Format "yyyyMMdd_HHmmss"
$SafeDatabase = $Database -replace '[^A-Za-z0-9_]+', '_'
$OutFile = Join-Path $BackupDir "$SafeDatabase`_$Stamp.dump"
if ($PgBin -eq "") {
    $cmd = Get-Command pg_dump.exe -ErrorAction SilentlyContinue
    if ($cmd) {
        $PgBin = Split-Path -Parent $cmd.Source
    } else {
        $PgBin = (Get-ChildItem "C:\Program Files\PostgreSQL" -Filter "pg_dump.exe" -Recurse -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1 | ForEach-Object { Split-Path -Parent $_.FullName })
    }
}
if (!$PgBin) { throw "pg_dump.exe nao encontrado. Informe -PgBin." }

$PgDump = Join-Path $PgBin "pg_dump.exe"

& $PgDump -h $HostName -p $Port -U $User -d $Database -Fc -f $OutFile
Write-Host "Backup gerado em $OutFile"
