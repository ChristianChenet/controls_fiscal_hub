$ErrorActionPreference = "Continue"

$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$Storage = Join-Path $ProjectRoot "app\storage"
$LogDir = Join-Path $Storage "logs"
New-Item -ItemType Directory -Force -Path $LogDir | Out-Null

function Log($Text) {
    $line = "[" + (Get-Date -Format "yyyy-MM-dd HH:mm:ss") + "] " + $Text
    Write-Host $line
    Add-Content -Path (Join-Path $LogDir "windows_stop.log") -Value $line
}

function Stop-PidFile($Path, $Label) {
    if (!(Test-Path $Path)) { return }
    $content = (Get-Content $Path -ErrorAction SilentlyContinue | Select-Object -First 1)
    if (!$content) { return }
    $pidText = ($content -split "\s+")[0]
    $processId = 0
    if (![int]::TryParse($pidText, [ref]$processId)) { return }
    $proc = Get-Process -Id $processId -ErrorAction SilentlyContinue
    if ($proc) {
        Log "Parando $Label PID $processId"
        Stop-Process -Id $processId -Force -ErrorAction SilentlyContinue
    }
}

function Stop-Port($Port) {
    $rows = netstat -ano | Select-String ":$Port\s"
    foreach ($row in $rows) {
        if ($row.Line -notmatch "LISTENING") { continue }
        $parts = $row.Line -split "\s+"
        $processId = [int]$parts[-1]
        if ($processId -gt 0) {
            $proc = Get-Process -Id $processId -ErrorAction SilentlyContinue
            if ($proc) {
                Log "Parando portal na porta $Port PID $processId"
                Stop-Process -Id $processId -Force -ErrorAction SilentlyContinue
            }
        }
    }
}

Stop-PidFile (Join-Path $Storage "auto_cte_worker.process.lock") "worker CT-e"
Stop-PidFile (Join-Path $Storage "auto_nfe_worker.process.lock") "worker NF-e"
Stop-PidFile (Join-Path $Storage "auto_nfse_worker.process.lock") "worker NFS-e"
Stop-Port 8088

Log "Parada solicitada concluida."
