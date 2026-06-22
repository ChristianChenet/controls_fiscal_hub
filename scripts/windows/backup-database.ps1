param(
    [string]$PgBin = "C:\Program Files\PostgreSQL\16\bin",
    [string]$Database = "controls_portal",
    [string]$User = "controls",
    [string]$HostName = "127.0.0.1",
    [int]$Port = 5432
)

$ErrorActionPreference = "Stop"
$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$BackupDir = Join-Path $ProjectRoot "backup"
New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null

$Stamp = Get-Date -Format "yyyyMMdd_HHmmss"
$OutFile = Join-Path $BackupDir "controls_portal_$Stamp.dump"
$PgDump = Join-Path $PgBin "pg_dump.exe"

& $PgDump -h $HostName -p $Port -U $User -d $Database -Fc -f $OutFile
Write-Host "Backup gerado em $OutFile"
