# Instalador e atualizador Windows

Use `INSTALAR_OU_ATUALIZAR.cmd` para instalar ou atualizar o Control S Fiscal Hub sem Docker.

## Como usar

Execute como Administrador na pasta do pacote:

```powershell
INSTALAR_OU_ATUALIZAR.cmd
```

O instalador:

- copia a aplicacao para `C:\Control S Fiscal Hub`;
- preserva `app\.env`, `app\storage` e backups;
- instala PHP e PostgreSQL quando possivel;
- habilita extensoes PHP necessarias;
- cria usuario e banco PostgreSQL;
- configura `app\.env`;
- executa a inicializacao/migracoes;
- libera a porta `8088`;
- cria atalho na area de trabalho;
- registra tarefas automaticas para portal, CT-e, NF-e e NFS-e.

## Atualizacao

Para atualizar, execute o mesmo arquivo novamente. O instalador substitui codigo e telas, mas nao apaga dados operacionais.

## Parametros uteis

```powershell
powershell.exe -ExecutionPolicy Bypass -File .\INSTALAR_OU_ATUALIZAR.ps1 -InstallDir "C:\Control S Fiscal Hub"
powershell.exe -ExecutionPolicy Bypass -File .\INSTALAR_OU_ATUALIZAR.ps1 -NaoCriarTarefas
powershell.exe -ExecutionPolicy Bypass -File .\INSTALAR_OU_ATUALIZAR.ps1 -NaoInstalarDependencias
powershell.exe -ExecutionPolicy Bypass -File .\INSTALAR_OU_ATUALIZAR.ps1 -DumpFile .\backup\controls_portal_YYYYMMDD_HHMMSS.dump
```

## Backup antes de atualizar

Se a instalacao ja estiver em uso, gere backup antes:

```powershell
powershell.exe -ExecutionPolicy Bypass -File .\scripts\windows\backup-database.ps1
```

Tambem preserve:

- `app\.env`;
- `app\storage`;
- pasta configurada em `DEFAULT_DOWNLOAD_DIR`, se estiver fora da pasta da aplicacao.
