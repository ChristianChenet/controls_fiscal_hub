param(
    [string]$DumpFile = "C:\Control S Fiscal Hub\backup\controls_portal_20260520_141049.dump",
    [string]$Database = "control_s_fiscal_hub",
    [string]$User = "controls",
    [string]$HostName = "127.0.0.1",
    [int]$Port = 5432,
    [string]$PgBin = ""
)

$ErrorActionPreference = "Stop"

function Encontrar-PgBin {
    if ($PgBin -ne "") { return $PgBin }
    $cmd = Get-Command pg_restore.exe -ErrorAction SilentlyContinue
    if ($cmd) { return Split-Path -Parent $cmd.Source }
    $found = Get-ChildItem "C:\Program Files\PostgreSQL" -Filter "pg_restore.exe" -Recurse -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending |
        Select-Object -First 1
    if ($found) { return Split-Path -Parent $found.FullName }
    throw "pg_restore.exe nao encontrado. Informe -PgBin."
}

if (!(Test-Path $DumpFile)) {
    throw "Dump nao encontrado: $DumpFile"
}

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$envPath = Join-Path $root "app\.env"
$envMap = @{}
if (Test-Path $envPath) {
    Get-Content $envPath | Where-Object { $_ -match "=" -and $_.Trim()[0] -ne "#" } | ForEach-Object {
        $k,$v = $_.Split("=",2)
        $envMap[$k.Trim()] = $v.Trim().Trim('"')
    }
}
if ($envMap["DB_PASS"]) {
    $env:PGPASSWORD = $envMap["DB_PASS"]
} elseif (!$env:PGPASSWORD) {
    $env:PGPASSWORD = Read-Host "Senha do banco para o usuario $User"
}

$bin = Encontrar-PgBin
$psql = Join-Path $bin "psql.exe"
$restore = Join-Path $bin "pg_restore.exe"

Write-Host "Restaurando dump:"
Write-Host $DumpFile
Write-Host "Banco destino: $Database"

$exists = & $psql -h $HostName -p $Port -U $User -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname = '$Database';"
if ($exists -ne "1") {
    & $psql -h $HostName -p $Port -U $User -d postgres -c "CREATE DATABASE $Database OWNER $User;"
    if ($LASTEXITCODE -ne 0) { throw "Falha ao criar banco $Database." }
}

& $psql -h $HostName -p $Port -U $User -d $Database -v ON_ERROR_STOP=1 -c "DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public; GRANT ALL ON SCHEMA public TO $User; GRANT ALL ON SCHEMA public TO public;"
if ($LASTEXITCODE -ne 0) { throw "Falha ao limpar schema public." }

& $restore -h $HostName -p $Port -U $User -d $Database $DumpFile
if ($LASTEXITCODE -ne 0) { throw "Falha ao restaurar dump." }

Write-Host "Banco restaurado com sucesso em $Database."
