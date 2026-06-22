param(
    [string]$AppRoot = ""
)

$ErrorActionPreference = "Stop"

if ([string]::IsNullOrWhiteSpace($AppRoot)) {
    $AppRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
} else {
    $AppRoot = $AppRoot.Trim().Trim('"')
    $AppRoot = Resolve-Path $AppRoot
}

$AppRoot = [string]$AppRoot
$Stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$OutRoot = Join-Path $AppRoot "diagnosticos"
$OutDir = Join-Path $OutRoot "diagnostico-$Stamp"
$ZipPath = Join-Path $OutRoot "diagnostico-$Stamp.zip"

New-Item -ItemType Directory -Force -Path $OutDir | Out-Null

function Write-Section {
    param([string]$Text)
    Write-Host ""
    Write-Host "==== $Text ===="
}

function Write-TextFile {
    param([string]$Name, [string]$Content)
    $path = Join-Path $OutDir $Name
    $dir = Split-Path $path -Parent
    New-Item -ItemType Directory -Force -Path $dir | Out-Null
    Set-Content -Path $path -Value $Content -Encoding UTF8
}

function Copy-LogTail {
    param([string]$SourcePath, [string]$TargetName, [int]$Lines = 700)
    if (Test-Path $SourcePath) {
        $target = Join-Path $OutDir $TargetName
        $dir = Split-Path $target -Parent
        New-Item -ItemType Directory -Force -Path $dir | Out-Null
        Get-Content -Path $SourcePath -Tail $Lines -ErrorAction SilentlyContinue |
            Set-Content -Path $target -Encoding UTF8
    }
}

function Mask-EnvLine {
    param([string]$Line)
    if ($Line -match '^\s*#' -or $Line -notmatch '=') {
        return $Line
    }
    $key = ($Line -split '=', 2)[0].Trim()
    if ($key -match '(PASS|PASSWORD|TOKEN|SECRET|APP_KEY|KEY|CERT|AUTH_PASS)') {
        return "$key=***mascarado***"
    }
    return $Line
}

function Find-Php {
    $candidates = @(
        (Join-Path $AppRoot "php\php.exe"),
        "C:\php\php.exe",
        "C:\Program Files\PHP\php.exe"
    )
    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) { return $candidate }
    }
    $cmd = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    return ""
}

function Find-Psql {
    $candidates = @(
        "C:\Program Files\PostgreSQL\18\bin\psql.exe",
        "C:\Program Files\PostgreSQL\17\bin\psql.exe",
        "C:\Program Files\PostgreSQL\16\bin\psql.exe",
        "C:\Program Files\PostgreSQL\15\bin\psql.exe",
        "C:\Program Files\PostgreSQL\14\bin\psql.exe",
        "C:\Program Files\PostgreSQL\18\pgAdmin 4\runtime\psql.exe",
        "C:\Program Files\PostgreSQL\17\pgAdmin 4\runtime\psql.exe",
        "C:\Program Files\PostgreSQL\16\pgAdmin 4\runtime\psql.exe"
    )
    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) { return $candidate }
    }
    $cmd = Get-Command psql.exe -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    return ""
}

function Read-AppEnv {
    $envPath = Join-Path $AppRoot "app\.env"
    $data = @{}
    if (!(Test-Path $envPath)) { return $data }
    foreach ($line in Get-Content $envPath) {
        if ($line -notmatch '^\s*([^#=]+)\s*=\s*(.*)\s*$') { continue }
        $key = $matches[1].Trim()
        $value = $matches[2].Trim().Trim('"')
        $data[$key] = $value
    }
    return $data
}

function Parse-PgDsn {
    param([string]$Dsn)
    $info = @{
        host = "127.0.0.1"
        port = "5432"
        dbname = "control_s_fiscal_hub"
    }
    if ($Dsn -match '^pgsql:(.*)$') {
        foreach ($part in $matches[1].Split(';')) {
            if ($part -match '^\s*([^=]+)=(.*)$') {
                $info[$matches[1].Trim()] = $matches[2].Trim()
            }
        }
    }
    return $info
}

function Run-PsqlQuery {
    param(
        [string]$Psql,
        [hashtable]$Db,
        [string]$User,
        [string]$Pass,
        [string]$Sql,
        [string]$OutName
    )
    $target = Join-Path $OutDir $OutName
    $sqlFile = [System.IO.Path]::Combine([System.IO.Path]::GetTempPath(), ("controls-fiscal-hub-" + [System.Guid]::NewGuid().ToString("N") + ".sql"))
    $stdout = $target + ".out.tmp"
    $stderr = $target + ".err.tmp"
    $env:PGPASSWORD = $Pass
    $env:PGCONNECT_TIMEOUT = "5"
    try {
        Set-Content -Path $sqlFile -Value $Sql -Encoding UTF8
        $args = @(
            "-h", $Db.host,
            "-p", $Db.port,
            "-U", $User,
            "-d", $Db.dbname,
            "-v", "ON_ERROR_STOP=1",
            "-P", "pager=off",
            "-f", $sqlFile
        )
        $process = Start-Process -FilePath $Psql -ArgumentList $args -NoNewWindow -PassThru -RedirectStandardOutput $stdout -RedirectStandardError $stderr
        $finished = $process.WaitForExit(5000)
        if (-not $finished) {
            try { $process.Kill() } catch {}
            Set-Content -Path $target -Value "Timeout ao executar consulta no banco depois de 5 segundos." -Encoding UTF8
        } else {
            $content = @()
            if (Test-Path $stdout) { $content += Get-Content $stdout -ErrorAction SilentlyContinue }
            if (Test-Path $stderr) { $content += Get-Content $stderr -ErrorAction SilentlyContinue }
            if (!$content) { $content = @("(sem retorno)") }
            Set-Content -Path $target -Value $content -Encoding UTF8
        }
    } catch {
        Set-Content -Path $target -Value ("Falha ao executar consulta: " + $_.Exception.Message) -Encoding UTF8
    } finally {
        Remove-Item $sqlFile -ErrorAction SilentlyContinue
        Remove-Item $stdout -ErrorAction SilentlyContinue
        Remove-Item $stderr -ErrorAction SilentlyContinue
        Remove-Item Env:\PGPASSWORD -ErrorAction SilentlyContinue
        Remove-Item Env:\PGCONNECT_TIMEOUT -ErrorAction SilentlyContinue
    }
}

Write-Section "Control S Fiscal Hub - diagnostico operacional"
Write-Host "Aplicacao: $AppRoot"
Write-Host "Saida: $ZipPath"

$php = Find-Php
$psql = Find-Psql
$envData = Read-AppEnv
$dbDsn = ""
if ($envData.ContainsKey("DB_DSN")) { $dbDsn = [string]$envData["DB_DSN"] }
$dbUser = "controls"
if ($envData.ContainsKey("DB_USER")) { $dbUser = [string]$envData["DB_USER"] }
$dbPass = ""
if ($envData.ContainsKey("DB_PASS")) { $dbPass = [string]$envData["DB_PASS"] }
$dbInfo = Parse-PgDsn $dbDsn

Write-Section "Coletando ambiente"
$summary = @()
$summary += "Gerado em: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
$summary += "AppRoot: $AppRoot"
$summary += "PHP: $php"
$summary += "psql: $psql"
$summary += "Banco: $($dbInfo.dbname) em $($dbInfo.host):$($dbInfo.port)"
$summary += "Usuario banco: $dbUser"
$repoFile = Join-Path $AppRoot "app\src\Repository.php"
$jobRunnerFile = Join-Path $AppRoot "app\src\JobRunner.php"
if (Test-Path $repoFile) {
    $summary += "Repository.php SHA256: $((Get-FileHash -Path $repoFile -Algorithm SHA256).Hash)"
    $repoContent = Get-Content $repoFile -Raw
    $summary += "Correcao boolean posted_to_erp instalada: $($repoContent.Contains('PDO::PARAM_BOOL'))"
}
if (Test-Path $jobRunnerFile) {
    $jobRunnerContent = Get-Content $jobRunnerFile -Raw
    $summary += "Recuo por CNPJ instalado: $($jobRunnerContent.Contains('rewind_nsu_once_company_'))"
}
Write-TextFile "00-resumo.txt" ($summary -join [Environment]::NewLine)

if ($php) {
    try {
        & $php -v 2>&1 | Set-Content -Path (Join-Path $OutDir "01-php-version.txt") -Encoding UTF8
        & $php -l (Join-Path $AppRoot "app\public\index.php") 2>&1 | Set-Content -Path (Join-Path $OutDir "02-php-lint-index.txt") -Encoding UTF8
    } catch {
        Write-TextFile "01-php-version.txt" ("Falha ao validar PHP: " + $_.Exception.Message)
    }
}

$envPath = Join-Path $AppRoot "app\.env"
if (Test-Path $envPath) {
    $masked = Get-Content $envPath | ForEach-Object { Mask-EnvLine $_ }
    Write-TextFile "03-app-env-mascarado.txt" ($masked -join [Environment]::NewLine)
}

try {
    Get-ScheduledTask | Where-Object { $_.TaskName -like "Control S Fiscal Hub*" } |
        Select-Object TaskName, State, TaskPath |
        Format-Table -AutoSize |
        Out-String -Width 240 |
        Set-Content -Path (Join-Path $OutDir "04-tarefas-windows.txt") -Encoding UTF8
} catch {
    Write-TextFile "04-tarefas-windows.txt" ("Falha ao listar tarefas: " + $_.Exception.Message)
}

Write-Section "Coletando banco"
if ($psql) {
    Run-PsqlQuery $psql $dbInfo $dbUser $dbPass "select id, company_name, cnpj, is_active, default_download_dir from companies order by id;" "10-empresas.txt"
    Run-PsqlQuery $psql $dbInfo $dbUser $dbPass "select key, case when key ~* '(pass|password|token|secret|app_key|cert)' then '***mascarado***' else coalesce(value,'') end as value from settings where key like 'auto_%' or key like 'cte_%' or key like 'nfe_%' or key like 'nfse_%' or key like '%cooldown%' order by key;" "11-settings-nsu-automacao.txt"
    Run-PsqlQuery $psql $dbInfo $dbUser $dbPass "select id, company_id, company_name, job_type, status, started_at, finished_at, created_count, updated_count, error_count, left(coalesce(log_text,''), 3000) as log_text from jobs where job_type ilike '%cte%' or job_type ilike '%nfe%' or job_type ilike '%nfse%' order by id desc limit 80;" "12-jobs-recentes.txt"
    Run-PsqlQuery $psql $dbInfo $dbUser $dbPass "select id, company_id, action_type, created_at, left(coalesce(details,''), 3000) as details from actions_log where action_type ilike '%cte%' or action_type ilike '%nfe%' or action_type ilike '%nsu%' or action_type ilike '%auto%' order by id desc limit 100;" "13-auditoria-operacional.txt"
    Run-PsqlQuery $psql $dbInfo $dbUser $dbPass "select company_id, company_name, doc_type, count(*) as total, min(issue_date) as primeira_emissao, max(issue_date) as ultima_emissao, max(imported_at) as ultima_importacao from documents where doc_type in ('CTE','NFE','NFCE','NFSE','MDFE') group by company_id, company_name, doc_type order by company_name, doc_type;" "14-resumo-documentos.txt"
    Run-PsqlQuery $psql $dbInfo $dbUser $dbPass "select id, company_id, company_name, doc_type, access_key, schema_name, status, manifestation_status, issue_date, imported_at, updated_at, left(coalesce(notes,''), 500) as notes from documents where doc_type in ('CTE','NFE') order by imported_at desc nulls last, id desc limit 120;" "15-ultimos-documentos.txt"
    Run-PsqlQuery $psql $dbInfo $dbUser $dbPass "select * from distribution_controls order by company_id, doc_type, environment;" "16-controle-distribuicao.txt"
} else {
    Write-TextFile "10-banco.txt" "psql.exe nao encontrado. As consultas de banco nao foram executadas."
}

Write-Section "Coletando logs"
$logDir = Join-Path $AppRoot "app\storage\logs"
if (Test-Path $logDir) {
    Get-ChildItem $logDir -File |
        Sort-Object LastWriteTime -Descending |
        Select-Object Name, Length, LastWriteTime |
        Format-Table -AutoSize |
        Out-String -Width 240 |
        Set-Content -Path (Join-Path $OutDir "20-lista-logs.txt") -Encoding UTF8

    foreach ($name in @(
        "collector_cte.log",
        "collector_nfe.log",
        "auto_cte_worker.log",
        "auto_nfe_worker.log",
        "auto_nfse_worker.log",
        "nsu_rewind.log",
        "manifestation.log"
    )) {
        Copy-LogTail (Join-Path $logDir $name) ("logs\" + $name + ".tail.txt") 700
    }
    Copy-LogTail (Join-Path $logDir "soap_nfe_debug.log") "logs\soap_nfe_debug.log.tail.txt" 80
} else {
    Write-TextFile "20-lista-logs.txt" "Pasta de logs nao encontrada: $logDir"
}

Write-Section "Compactando"
if (Test-Path $ZipPath) {
    Remove-Item -LiteralPath $ZipPath -Force
}
Compress-Archive -Path (Join-Path $OutDir "*") -DestinationPath $ZipPath -Force

Write-Host ""
Write-Host "Diagnostico gerado com sucesso:"
Write-Host $ZipPath
Write-Host ""
Write-Host "Envie este arquivo ZIP para analise."
