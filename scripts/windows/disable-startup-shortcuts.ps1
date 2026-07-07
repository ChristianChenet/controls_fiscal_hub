$ErrorActionPreference = "Stop"

$Startup = [Environment]::GetFolderPath("Startup")
$Names = @(
    "Control S Fiscal Hub - Portal.lnk",
    "Control S Fiscal Hub - Robos.lnk",
    "Control S Fiscal Hub - Abrir navegador.lnk"
)

foreach ($Name in $Names) {
    $Path = Join-Path $Startup $Name
    if (Test-Path $Path) {
        Remove-Item $Path -Force
        Write-Host "Removido: $Path"
    }
}

Write-Host "Inicializacao automatica desativada para o usuario atual."
