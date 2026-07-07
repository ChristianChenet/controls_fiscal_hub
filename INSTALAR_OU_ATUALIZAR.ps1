param(
    [string]$InstallDir = "C:\Control S Fiscal Hub",
    [string]$DbHost = "127.0.0.1",
    [int]$DbPort = 5432,
    [string]$DbName = "control_s_fiscal_hub",
    [string]$DbUser = "controls",
    [int]$PortalPort = 8088,
    [switch]$NaoInstalarDependencias,
    [switch]$NaoCriarBanco,
    [switch]$NaoCriarTarefas,
    [switch]$NaoCriarAtalho,
    [switch]$RestaurarBackupMaisRecente,
    [string]$DumpFile = ""
)

$ErrorActionPreference = "Stop"
$Origem = Split-Path -Parent $MyInvocation.MyCommand.Path
$LogPath = Join-Path $Origem "install.log"
Start-Transcript -Path $LogPath -Append | Out-Null

trap {
    Write-Host ""
    Write-Host "ERRO NA INSTALACAO/ATUALIZACAO:" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    Write-Host ""
    Write-Host "Log gravado em: $LogPath" -ForegroundColor Yellow
    Stop-Transcript | Out-Null
    exit 1
}

function Titulo($Texto) {
    Write-Host ""
    Write-Host "==== $Texto ====" -ForegroundColor Green
}

function Falhar($Texto) {
    Write-Host ""
    Write-Host $Texto -ForegroundColor Red
    throw $Texto
}

function Tem-Comando($Nome) {
    return [bool](Get-Command $Nome -ErrorAction SilentlyContinue)
}

function Atualizar-Path-Sessao {
    $machine = [System.Environment]::GetEnvironmentVariable("Path", "Machine")
    $user = [System.Environment]::GetEnvironmentVariable("Path", "User")
    $env:Path = "$machine;$user"
}

function Esta-Administrador {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Texto-Seguro($SecureString) {
    $ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecureString)
    try {
        return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)
    } finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)
    }
}

function Encontrar-PHP {
    if (Tem-Comando "php.exe") { return (Get-Command "php.exe").Source }

    $candidatos = @()
    $candidatos += Get-ChildItem "C:\tools" -Filter "php.exe" -Recurse -ErrorAction SilentlyContinue
    $candidatos += Get-ChildItem "C:\Program Files" -Filter "php.exe" -Recurse -ErrorAction SilentlyContinue |
        Where-Object { $_.FullName -match "\\php" }

    if ($candidatos.Count -gt 0) {
        return ($candidatos | Sort-Object FullName -Descending | Select-Object -First 1).FullName
    }
    return $null
}

function Encontrar-PgBin {
    if (Tem-Comando "psql.exe") {
        return Split-Path -Parent (Get-Command "psql.exe").Source
    }

    $candidatos = Get-ChildItem "C:\Program Files\PostgreSQL" -Filter "psql.exe" -Recurse -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending

    if ($candidatos.Count -gt 0) {
        return (Split-Path -Parent $candidatos[0].FullName)
    }
    return $null
}

function Instalar-Dependencias-Se-Necessario {
    if ($NaoInstalarDependencias) {
        Write-Host "Instalacao automatica de dependencias ignorada por parametro."
        return
    }

    Titulo "Verificando dependencias Windows"
    Atualizar-Path-Sessao
    $php = Encontrar-PHP
    $pgBin = Encontrar-PgBin

    if ($php -and $pgBin) {
        Write-Host "PHP encontrado: $php"
        Write-Host "PostgreSQL encontrado: $pgBin"
        return
    }

    if (!(Esta-Administrador)) {
        Falhar "PHP/PostgreSQL nao encontrados. Abra o PowerShell como Administrador e execute INSTALAR_OU_ATUALIZAR.cmd novamente."
    }

    if (Tem-Comando "winget.exe") {
        if (!$php) {
            Write-Host "Instalando PHP via winget..."
            winget install --id PHP.PHP.8.3 -e --accept-package-agreements --accept-source-agreements
        }
        if (!$pgBin) {
            Write-Host "Instalando PostgreSQL via winget..."
            winget install --id PostgreSQL.PostgreSQL.16 -e --accept-package-agreements --accept-source-agreements
        }
    } elseif (Tem-Comando "choco.exe") {
        if (!$php) {
            Write-Host "Instalando PHP via Chocolatey..."
            choco install php -y --no-progress
        }
        if (!$pgBin) {
            Write-Host "Instalando PostgreSQL via Chocolatey..."
            choco install postgresql16 -y --no-progress
        }
    } else {
        Falhar "Nao encontrei winget nem Chocolatey. Instale PHP 8.3+ e PostgreSQL 16+ manualmente e execute novamente."
    }

    Atualizar-Path-Sessao
    $php = Encontrar-PHP
    $pgBin = Encontrar-PgBin
    if (!$php) { Falhar "PHP nao foi encontrado apos a instalacao." }
    if (!$pgBin) { Falhar "PostgreSQL nao foi encontrado apos a instalacao." }
}

function Configurar-PHP($PhpPath) {
    Titulo "Validando PHP"
    & $PhpPath -v

    $phpDir = Split-Path -Parent $PhpPath
    $phpIni = Join-Path $phpDir "php.ini"
    if (!(Test-Path $phpIni)) {
        $prod = Join-Path $phpDir "php.ini-production"
        $dev = Join-Path $phpDir "php.ini-development"
        if (Test-Path $prod) {
            Copy-Item $prod $phpIni -Force
        } elseif (Test-Path $dev) {
            Copy-Item $dev $phpIni -Force
        }
    }

    if (Test-Path $phpIni) {
        $conteudo = Get-Content $phpIni -Raw
        $extDir = Join-Path $phpDir "ext"
        if ($conteudo -match "(?m)^;?\s*extension_dir\s*=") {
            $conteudo = $conteudo -replace "(?m)^;?\s*extension_dir\s*=.*$", "extension_dir=`"$extDir`""
        } else {
            $conteudo += "`r`nextension_dir=`"$extDir`"`r`n"
        }

        foreach ($ext in @("pdo_pgsql", "pgsql", "openssl", "zip", "curl", "mbstring", "fileinfo")) {
            if ($conteudo -match "(?m)^;?\s*extension\s*=\s*$ext\s*$") {
                $conteudo = $conteudo -replace "(?m)^;?\s*extension\s*=\s*$ext\s*$", "extension=$ext"
            } else {
                $conteudo += "extension=$ext`r`n"
            }
        }
        foreach ($builtin in @("dom", "simplexml")) {
            $conteudo = $conteudo -replace "(?m)^\s*extension\s*=\s*$builtin\s*$", "; extension=$builtin"
        }
        Set-Content -Path $phpIni -Value $conteudo -Encoding ASCII
    }

    $modulos = & $PhpPath -m
    foreach ($obrigatorio in @("pdo_pgsql", "pgsql", "openssl", "zip", "curl", "mbstring", "dom", "SimpleXML")) {
        if ($modulos -notcontains $obrigatorio) {
            Write-Host "Aviso: extensao PHP nao apareceu em php -m: $obrigatorio" -ForegroundColor Yellow
        }
    }
}

function Atualizar-Launchers($Destino, $PhpPath) {
    Titulo "Atualizando atalhos tecnicos de inicializacao"
    $scriptsDir = Join-Path $Destino "scripts\windows"
    $phpEscapado = $PhpPath.Replace('"', '""')

    $portalBat = @"
@echo off
setlocal
cd /d "%~dp0\..\.."
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%cd%\scripts\windows\run-portal.ps1" -PhpPath "$phpEscapado" -Port $PortalPort
"@
    Set-Content -Path (Join-Path $scriptsDir "start-portal.bat") -Value $portalBat -Encoding ASCII

    $workersBat = @"
@echo off
setlocal
cd /d "%~dp0\..\.."
start "Control S Worker CT-e" powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%cd%\scripts\windows\run-worker.ps1" -Worker cte -PhpPath "$phpEscapado"
start "Control S Worker NF-e" powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%cd%\scripts\windows\run-worker.ps1" -Worker nfe -PhpPath "$phpEscapado"
start "Control S Worker NFS-e" powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%cd%\scripts\windows\run-worker.ps1" -Worker nfse -PhpPath "$phpEscapado"
"@
    Set-Content -Path (Join-Path $scriptsDir "start-workers.bat") -Value $workersBat -Encoding ASCII
}

function Parar-Instancia-Atual($Destino) {
    Titulo "Parando instancia atual antes da atualizacao"

    foreach ($task in @(
        "Control S Fiscal Hub - Portal",
        "Control S Fiscal Hub - Worker cte",
        "Control S Fiscal Hub - Worker nfe",
        "Control S Fiscal Hub - Worker nfse"
    )) {
        try {
            Stop-ScheduledTask -TaskName $task -ErrorAction SilentlyContinue
        } catch {
        }
    }

    $stopScript = Join-Path $Destino "scripts\windows\stop-all.ps1"
    if (Test-Path $stopScript) {
        try {
            & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $stopScript
        } catch {
            Write-Host "Aviso: parada pelo script anterior falhou. Tentando parada direta." -ForegroundColor Yellow
        }
    }

    $destinoNormalizado = $Destino.Replace('\', '\\')
    $processos = Get-CimInstance Win32_Process -Filter "name = 'php.exe'" -ErrorAction SilentlyContinue |
        Where-Object {
            $cmdLine = [string]$_.CommandLine
            $cmdLine -like "*$Destino*" -or $cmdLine -like "*$destinoNormalizado*"
        }

    foreach ($proc in $processos) {
        try {
            Write-Host "Parando processo PHP antigo PID $($proc.ProcessId)"
            Stop-Process -Id $proc.ProcessId -Force -ErrorAction SilentlyContinue
        } catch {
        }
    }

    $linhasPorta = netstat -ano | Select-String ":$PortalPort\s"
    foreach ($linha in $linhasPorta) {
        if ($linha.Line -notmatch "LISTENING") { continue }
        $partes = $linha.Line -split "\s+"
        $processId = [int]$partes[-1]
        if ($processId -gt 0) {
            try {
                Write-Host "Parando processo na porta $PortalPort PID $processId"
                Stop-Process -Id $processId -Force -ErrorAction SilentlyContinue
            } catch {
            }
        }
    }

    Start-Sleep -Seconds 2
}

function Copiar-Aplicacao($Origem, $Destino) {
    Titulo "Copiando/atualizando arquivos da aplicacao"
    New-Item -ItemType Directory -Force -Path $Destino | Out-Null

    $origemResolvida = (Resolve-Path $Origem).Path.TrimEnd('\')
    $destinoResolvido = (Resolve-Path $Destino).Path.TrimEnd('\')
    if ($origemResolvida -ieq $destinoResolvido) {
        Write-Host "Origem e destino sao a mesma pasta. Copia ignorada."
        return
    }

    foreach ($dir in @("database", "scripts")) {
        $origemDir = Join-Path $Origem $dir
        if (Test-Path $origemDir) {
            Copy-Item $origemDir (Join-Path $Destino $dir) -Recurse -Force
        }
    }

    foreach ($file in @(
        ".env.windows.example",
        ".gitignore",
        "README.md",
        "INSTALL.md",
        "INSTALADOR_WINDOWS.md",
        "INSTALACAO_WINDOWS.md",
        "LEIA-ME-WINDOWS.txt",
        "AUTOMACAO_CTE.md",
        "AUTOMACAO_NFE.md",
        "NFS_E_NACIONAL.md",
        "BUSCA_NFE_POR_CHAVE.md",
        "REVARREDURA_NFE.md",
        "CONFERENCIA_EXPORTACAO_ERP.md",
        "CONFERENCIA_FATURAMENTO.md",
        "TECHNICAL_OVERVIEW.md",
        "VALIDATION.md",
        "INSTALAR_OU_ATUALIZAR.cmd",
        "INSTALAR_OU_ATUALIZAR.ps1",
        "RESTAURAR_BANCO_LOCAL.cmd",
        "RESTAURAR_BANCO_LOCAL.ps1",
        "PARAR_SISTEMA.cmd",
        "DESATIVAR_INICIALIZACAO.cmd"
    )) {
        $origemFile = Join-Path $Origem $file
        if (Test-Path $origemFile) {
            Copy-Item $origemFile (Join-Path $Destino $file) -Force
        }
    }

    $appDestino = Join-Path $Destino "app"
    New-Item -ItemType Directory -Force -Path $appDestino | Out-Null
    foreach ($item in @("bootstrap.php", "public", "src", "templates")) {
        $origemItem = Join-Path (Join-Path $Origem "app") $item
        if (Test-Path $origemItem) {
            Copy-Item $origemItem (Join-Path $appDestino $item) -Recurse -Force
        }
    }

    New-Item -ItemType Directory -Force -Path (Join-Path $appDestino "storage") | Out-Null
}

function Garantir-Pastas($Destino) {
    Titulo "Criando pastas operacionais"
    foreach ($rel in @(
        "backup",
        "app\storage\logs",
        "app\storage\exports",
        "app\storage\certificates",
        "app\storage\runtime",
        "app\storage\xmls"
    )) {
        New-Item -ItemType Directory -Force -Path (Join-Path $Destino $rel) | Out-Null
    }
}

function Criar-Env($Destino, $SenhaBanco) {
    Titulo "Configurando app.env"
    $appDir = Join-Path $Destino "app"
    $envPath = Join-Path $appDir ".env"
    $envExample = Join-Path $Destino ".env.windows.example"
    if (!(Test-Path $envPath)) {
        Copy-Item $envExample $envPath -Force
    }

    $downloadDir = Join-Path $appDir "storage\xmls"
    $conteudo = Get-Content $envPath -Raw
    $pares = [ordered]@{
        "APP_NAME" = '"Control S Fiscal Hub"'
        "APP_ENV" = "production"
        "APP_DEBUG" = "false"
        "APP_URL" = "http://localhost:$PortalPort"
        "DB_DSN" = "pgsql:host=$DbHost;port=$DbPort;dbname=$DbName"
        "DB_USER" = $DbUser
        "DB_PASS" = $SenhaBanco
        "AUTH_ENABLED" = "true"
        "AUTH_USER" = "admin"
        "AUTH_PASS" = "admin"
        "AUTH_DEFAULT_EMAIL" = "admin@controls.local"
        "DEFAULT_DOWNLOAD_DIR" = $downloadDir
        "AUTO_MIGRATE" = "true"
        "TIMEZONE" = "America/Sao_Paulo"
    }

    foreach ($key in $pares.Keys) {
        $valor = [string]$pares[$key]
        if ($conteudo -match "(?m)^$key=") {
            $conteudo = $conteudo -replace "(?m)^$key=.*$", "$key=$valor"
        } else {
            $conteudo += "`r`n$key=$valor"
        }
    }
    Set-Content -Path $envPath -Value $conteudo -Encoding ASCII
}

function Criar-Banco-Se-Necessario($PgBin, $SenhaBanco) {
    if ($NaoCriarBanco) {
        Write-Host "Criacao do banco ignorada por parametro."
        return
    }

    Titulo "Configurando banco PostgreSQL"
    $psql = Join-Path $PgBin "psql.exe"
    $env:PGPASSWORD = $SenhaBanco
    $senhaSql = $SenhaBanco.Replace("'", "''")

    $roleExiste = & $psql -h $DbHost -p $DbPort -U postgres -tAc "SELECT 1 FROM pg_roles WHERE rolname = '$DbUser';"
    if ($roleExiste -ne "1") {
        Write-Host "Criando usuario $DbUser..."
        & $psql -h $DbHost -p $DbPort -U postgres -c "CREATE USER $DbUser WITH PASSWORD '$senhaSql';"
    } else {
        Write-Host "Usuario $DbUser ja existe."
        & $psql -h $DbHost -p $DbPort -U postgres -c "ALTER USER $DbUser WITH PASSWORD '$senhaSql';"
    }

    $dbExiste = & $psql -h $DbHost -p $DbPort -U postgres -tAc "SELECT 1 FROM pg_database WHERE datname = '$DbName';"
    if ($dbExiste -ne "1") {
        Write-Host "Criando banco $DbName..."
        & $psql -h $DbHost -p $DbPort -U postgres -c "CREATE DATABASE $DbName OWNER $DbUser;"
    } else {
        Write-Host "Banco $DbName ja existe."
    }
}

function Restaurar-Backup($Destino, $PgBin, $SenhaBanco) {
    $arquivo = $DumpFile
    if ($RestaurarBackupMaisRecente -and !$arquivo) {
        $arquivo = Get-ChildItem (Join-Path $Destino "backup") -Filter "*.dump" -ErrorAction SilentlyContinue |
            Sort-Object LastWriteTime -Descending |
            Select-Object -First 1 -ExpandProperty FullName
    }
    if (!$arquivo) { return }
    if (!(Test-Path $arquivo)) { Falhar "Backup nao encontrado: $arquivo" }

    Titulo "Restaurando backup do banco"
    $env:PGPASSWORD = $SenhaBanco
    $restore = Join-Path $PgBin "pg_restore.exe"
    & $restore -h $DbHost -p $DbPort -U $DbUser -d $DbName --clean --if-exists $arquivo
}

function Rodar-Migracoes($Destino, $PhpPath) {
    Titulo "Validando aplicacao e migracoes"
    $appDir = Join-Path $Destino "app"
    $index = Join-Path $appDir "public\index.php"
    $bootstrap = Join-Path $appDir "bootstrap.php"
    & $PhpPath -l $index
    & $PhpPath -l $bootstrap

    $snippet = "require '$($bootstrap.Replace('\','/'))'; app_container(); echo 'OK';"
    & $PhpPath -r $snippet | Out-Host
}

function Liberar-Firewall {
    Titulo "Configurando firewall"
    if (!(Esta-Administrador)) {
        Write-Host "Sem permissao de administrador. Firewall nao foi alterado." -ForegroundColor Yellow
        return
    }
    $ruleName = "Control S Fiscal Hub $PortalPort"
    $saida = netsh advfirewall firewall show rule name="$ruleName" 2>$null | Out-String
    if ($LASTEXITCODE -ne 0 -or $saida -match "No rules match") {
        netsh advfirewall firewall add rule name="$ruleName" dir=in action=allow protocol=TCP localport=$PortalPort | Out-Null
    }
    Write-Host "Porta $PortalPort liberada para entrada TCP."
}

function Criar-Atalho($Destino) {
    if ($NaoCriarAtalho) { return }
    Titulo "Criando atalho na Area de Trabalho"
    $cmd = Join-Path $Destino "scripts\windows\start-portal.bat"
    $desktop = [Environment]::GetFolderPath("Desktop")
    $atalhoPath = Join-Path $desktop "Control S Fiscal Hub.lnk"
    $shell = New-Object -ComObject WScript.Shell
    $atalho = $shell.CreateShortcut($atalhoPath)
    $atalho.TargetPath = $cmd
    $atalho.WorkingDirectory = $Destino
    $atalho.IconLocation = "shell32.dll,220"
    $atalho.Save()
    Write-Host "Atalho criado em: $atalhoPath"
}

function Criar-Tarefas($Destino, $PhpPath) {
    if ($NaoCriarTarefas) { return }
    Titulo "Criando tarefas automaticas"
    if (!(Esta-Administrador)) {
        Write-Host "Sem permissao de administrador. Tarefas nao foram criadas." -ForegroundColor Yellow
        return
    }
    $script = Join-Path $Destino "scripts\windows\install-scheduled-tasks.ps1"
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $script -PhpPath $PhpPath -PortalPort $PortalPort

    foreach ($task in @(
        "Control S Fiscal Hub - Portal",
        "Control S Fiscal Hub - Worker cte",
        "Control S Fiscal Hub - Worker nfe",
        "Control S Fiscal Hub - Worker nfse"
    )) {
        try {
            Start-ScheduledTask -TaskName $task
            Write-Host "Tarefa iniciada: $task"
        } catch {
            Write-Host "Nao foi possivel iniciar a tarefa agora: $task" -ForegroundColor Yellow
        }
    }
}

function Criar-Inicializacao-Usuario($Destino) {
    Titulo "Configurando inicializacao automatica do usuario"
    $script = Join-Path $Destino "scripts\windows\install-startup-shortcuts.ps1"
    if (Test-Path $script) {
        & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $script
    }
}

function Iniciar-Portal-Manual($Destino, $PhpPath) {
    Titulo "Iniciando portal agora"
    $jaOuvindo = netstat -ano | Select-String ":$PortalPort"
    if ($jaOuvindo) {
        Write-Host "Porta $PortalPort ja esta em uso. Portal pode ja estar iniciado."
        return
    }

    $script = Join-Path $Destino "scripts\windows\run-portal.ps1"
    Start-Process -FilePath powershell.exe `
        -ArgumentList @("-NoProfile", "-ExecutionPolicy", "Bypass", "-File", $script, "-PhpPath", $PhpPath, "-Port", "$PortalPort") `
        -WorkingDirectory $Destino `
        -WindowStyle Hidden
    Start-Sleep -Seconds 3

    $ouvindo = netstat -ano | Select-String ":$PortalPort"
    if ($ouvindo) {
        Write-Host "Portal iniciado na porta $PortalPort."
    } else {
        Write-Host "Portal nao confirmou escuta na porta $PortalPort. Verifique app\storage\logs\windows_portal.log." -ForegroundColor Yellow
    }
}

Titulo "Control S Fiscal Hub - instalador/atualizador"
Write-Host "Origem: $Origem"
Write-Host "Destino: $InstallDir"

if ($env:CONTROL_S_DB_PASSWORD) {
    $senhaBanco = $env:CONTROL_S_DB_PASSWORD
} else {
    $senhaBanco = Texto-Seguro (Read-Host "Senha do PostgreSQL para o usuario controls e para configurar o portal" -AsSecureString)
}

Instalar-Dependencias-Se-Necessario
Atualizar-Path-Sessao
$php = Encontrar-PHP
$pgBin = Encontrar-PgBin
if (!$php) { Falhar "PHP nao encontrado." }
if (!$pgBin) { Falhar "PostgreSQL nao encontrado." }

Configurar-PHP $php
Parar-Instancia-Atual $InstallDir
Copiar-Aplicacao $Origem $InstallDir
Garantir-Pastas $InstallDir
Criar-Env $InstallDir $senhaBanco
Atualizar-Launchers $InstallDir $php
Criar-Banco-Se-Necessario $pgBin $senhaBanco
Restaurar-Backup $InstallDir $pgBin $senhaBanco
Rodar-Migracoes $InstallDir $php
Liberar-Firewall
Criar-Atalho $InstallDir
Criar-Tarefas $InstallDir $php
Criar-Inicializacao-Usuario $InstallDir
Iniciar-Portal-Manual $InstallDir $php

Titulo "Instalacao/atualizacao concluida"
Write-Host "Para iniciar agora:"
Write-Host "cd /d `"$InstallDir`""
Write-Host "scripts\windows\start-portal.bat"
Write-Host ""
Write-Host "Acesse:"
Write-Host "http://localhost:$PortalPort/"
Write-Host "http://IP_DO_SERVIDOR:$PortalPort/"
Write-Host ""
Write-Host "Log gravado em: $LogPath"
Stop-Transcript | Out-Null
