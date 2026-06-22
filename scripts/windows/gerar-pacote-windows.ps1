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
    "TECHNICAL_OVERVIEW.md",
    "VALIDATION.md",
    "INSTALAR_OU_ATUALIZAR.cmd",
    "INSTALAR_OU_ATUALIZAR.ps1",
    "GERAR_DIAGNOSTICO.cmd"
)

foreach ($Item in $Itens) {
    $Origem = Join-Path $Raiz $Item
    if (Test-Path $Origem) {
        Copy-Item $Origem (Join-Path $Pacote $Item) -Recurse -Force
    }
}

$Storage = Join-Path $Pacote "app\storage"
if (Test-Path $Storage) {
    Get-ChildItem $Storage -Force | Remove-Item -Recurse -Force
}
New-Item -ItemType Directory -Force -Path $Storage | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $Pacote "backup") | Out-Null

tar.exe -a -c -f $Zip -C $Pacote .
Write-Host "Pacote gerado em: $Zip"
