param(
    [Parameter(Mandatory = $true)]
    [string]$DumpFile,

    [string]$PgBin = "",
    [string]$Database = "control_s_fiscal_hub",
    [string]$User = "controls",
    [string]$HostName = "127.0.0.1",
    [int]$Port = 5432
)

$ErrorActionPreference = "Stop"
if (!(Test-Path $DumpFile)) {
    throw "Arquivo de backup nao encontrado: $DumpFile"
}

if ($PgBin -eq "") {
    $cmd = Get-Command pg_restore.exe -ErrorAction SilentlyContinue
    if ($cmd) {
        $PgBin = Split-Path -Parent $cmd.Source
    } else {
        $PgBin = (Get-ChildItem "C:\Program Files\PostgreSQL" -Filter "pg_restore.exe" -Recurse -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1 | ForEach-Object { Split-Path -Parent $_.FullName })
    }
}
if (!$PgBin) { throw "pg_restore.exe nao encontrado. Informe -PgBin." }

$Psql = Join-Path $PgBin "psql.exe"
$PgRestore = Join-Path $PgBin "pg_restore.exe"
& $Psql -h $HostName -p $Port -U $User -d $Database -v ON_ERROR_STOP=1 -c "DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public; GRANT ALL ON SCHEMA public TO $User; GRANT ALL ON SCHEMA public TO public;"
if ($LASTEXITCODE -ne 0) { throw "Falha ao limpar schema public em $Database." }
& $PgRestore -h $HostName -p $Port -U $User -d $Database $DumpFile
if ($LASTEXITCODE -ne 0) { throw "Falha ao restaurar backup em $Database." }
Write-Host "Backup restaurado em $Database"
