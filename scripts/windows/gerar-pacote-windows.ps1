$ErrorActionPreference = "Stop"

$Raiz = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$ReleaseDir = Join-Path $Raiz "release"
$Versao = Get-Date -Format "yyyyMMdd-HHmmss"
$Pacote = Join-Path $ReleaseDir "ControlSFiscalHub-Windows-$Versao"
$Zip = Join-Path $ReleaseDir "ControlSFiscalHub-Windows-$Versao.zip"

New-Item -ItemType Directory -Force -Path $Pacote | Out-Null

$Itens = @(
    "app",
    "database",
    "scripts",
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
    "GERAR_DIAGNOSTICO.cmd",
    "RESTAURAR_BANCO_LOCAL.cmd",
    "RESTAURAR_BANCO_LOCAL.ps1",
    "PARAR_SISTEMA.cmd",
    "DESATIVAR_INICIALIZACAO.cmd"
)

$ArquivosObrigatorios = @(
    "app\bootstrap.php",
    "app\public\index.php",
    "app\templates\dashboard.php",
    "app\templates\documents.php",
    "app\templates\revenue.php",
    "app\templates\users.php",
    "INSTALAR_OU_ATUALIZAR.ps1",
    ".env.windows.example"
)

foreach ($Obrigatorio in $ArquivosObrigatorios) {
    if (!(Test-Path (Join-Path $Raiz $Obrigatorio))) {
        throw "Arquivo obrigatorio ausente no pacote: $Obrigatorio"
    }
}

foreach ($Item in $Itens) {
    $Origem = Join-Path $Raiz $Item
    if (Test-Path $Origem) {
        if ($Item -eq "app") {
            New-Item -ItemType Directory -Force -Path (Join-Path $Pacote "app") | Out-Null
            foreach ($AppItem in @("bootstrap.php", "public", "scripts", "src", "templates")) {
                Copy-Item (Join-Path $Origem $AppItem) (Join-Path $Pacote "app\$AppItem") -Recurse -Force
            }
        } else {
            Copy-Item $Origem (Join-Path $Pacote $Item) -Recurse -Force
        }
    }
}

$Storage = Join-Path $Pacote "app\storage"
if (Test-Path $Storage) {
    Get-ChildItem $Storage -Force | Remove-Item -Recurse -Force
}
New-Item -ItemType Directory -Force -Path $Storage | Out-Null

$EnvFiles = @(
    (Join-Path $Pacote ".env"),
    (Join-Path $Pacote ".env.local"),
    (Join-Path $Pacote "app\.env"),
    (Join-Path $Pacote "app\.env.local")
)
foreach ($EnvFile in $EnvFiles) {
    if (Test-Path $EnvFile) {
        Remove-Item -LiteralPath $EnvFile -Force
    }
}

$GeneratedAppPaths = @(
    (Join-Path $Pacote "app\C*"),
    (Join-Path $Pacote "app\public\C*")
)
foreach ($PathPattern in $GeneratedAppPaths) {
    Get-ChildItem -Path $PathPattern -Force -ErrorAction SilentlyContinue | Remove-Item -Recurse -Force
}

New-Item -ItemType Directory -Force -Path (Join-Path $Pacote "backup") | Out-Null

tar.exe -a -c -f $Zip -C $Pacote .
Write-Host "Pacote gerado em: $Zip"
