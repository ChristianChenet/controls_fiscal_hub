param(
    [Parameter(Mandatory = $true)]
    [string]$DumpFile,

    [string]$PgBin = "C:\Program Files\PostgreSQL\16\bin",
    [string]$Database = "controls_portal",
    [string]$User = "controls",
    [string]$HostName = "127.0.0.1",
    [int]$Port = 5432
)

$ErrorActionPreference = "Stop"
if (!(Test-Path $DumpFile)) {
    throw "Arquivo de backup nao encontrado: $DumpFile"
}

$PgRestore = Join-Path $PgBin "pg_restore.exe"
& $PgRestore -h $HostName -p $Port -U $User -d $Database --clean --if-exists $DumpFile
Write-Host "Backup restaurado em $Database"
