# Instalacao Windows nativa

Este guia instala o **Control S Fiscal Hub** sem Docker, em Windows Desktop ou Windows Server.

O Docker continua disponivel no projeto, mas nao e obrigatorio para esta instalacao.

## Arquitetura

No modo Windows nativo:

- O portal roda em PHP no Windows.
- O banco roda em PostgreSQL instalado no Windows.
- Os robos CT-e, NF-e e NFS-e rodam por scripts PowerShell ou Tarefas Agendadas.
- XMLs, certificados, logs e exportacoes ficam em `app\storage` ou na pasta configurada no portal.

## Requisitos

Instale no servidor:

- PHP 8.3 ou superior para Windows.
- PostgreSQL 16 ou superior.
- Extensoes PHP habilitadas:
  - `pdo_pgsql`
  - `pgsql`
  - `openssl`
  - `zip`
  - `curl`
  - `mbstring`
  - `dom`
  - `simplexml`
- Acesso HTTPS aos endpoints da SEFAZ e ADN NFS-e.
- Certificados A1/PFX das empresas.

## Instalacao automatica recomendada

Na pasta do pacote, execute como **Administrador**:

```powershell
INSTALAR_OU_ATUALIZAR.cmd
```

O instalador faz a instalacao ou atualizacao no mesmo fluxo:

- copia/atualiza a aplicacao em `C:\Control S Fiscal Hub`;
- preserva `app\.env`, `app\storage` e backups existentes;
- tenta instalar PHP e PostgreSQL automaticamente por `winget` ou Chocolatey;
- configura extensoes PHP necessarias;
- cria usuario e banco PostgreSQL quando necessario;
- configura `app\.env`;
- executa a inicializacao/migracoes do portal;
- libera a porta `8088` no Firewall quando houver permissao;
- cria atalho na area de trabalho;
- registra as tarefas automaticas do portal e dos robos CT-e, NF-e e NFS-e.

Parametros uteis:

```powershell
powershell.exe -ExecutionPolicy Bypass -File .\INSTALAR_OU_ATUALIZAR.ps1 -InstallDir "C:\Control S Fiscal Hub"
powershell.exe -ExecutionPolicy Bypass -File .\INSTALAR_OU_ATUALIZAR.ps1 -NaoCriarTarefas
powershell.exe -ExecutionPolicy Bypass -File .\INSTALAR_OU_ATUALIZAR.ps1 -NaoInstalarDependencias
powershell.exe -ExecutionPolicy Bypass -File .\INSTALAR_OU_ATUALIZAR.ps1 -DumpFile .\backup\controls_portal_YYYYMMDD_HHMMSS.dump
```

Se o servidor bloquear instalacao automatica de dependencias, instale PHP/PostgreSQL manualmente e rode o instalador novamente com `-NaoInstalarDependencias`.

## Preparar PHP

No arquivo `php.ini`, confira se as extensoes abaixo estao habilitadas:

```ini
extension=pdo_pgsql
extension=pgsql
extension=openssl
extension=zip
extension=curl
extension=mbstring
extension=dom
extension=simplexml
```

Depois confirme:

```powershell
php -v
php -m
```

## Preparar PostgreSQL

Crie o usuario e banco:

```powershell
psql -U postgres
```

Dentro do `psql`:

```sql
CREATE USER controls WITH PASSWORD 'controls123';
CREATE DATABASE controls_portal OWNER controls;
\q
```

Use uma senha forte em producao e ajuste o `app\.env`.

## Configurar o portal

Copie o exemplo Windows para o arquivo usado pela aplicacao:

```powershell
copy .env.windows.example app\.env
```

Edite `app\.env`:

```env
DB_DSN=pgsql:host=127.0.0.1;port=5432;dbname=controls_portal
DB_USER=controls
DB_PASS=controls123
DEFAULT_DOWNLOAD_DIR=C:\Control S Fiscal Hub\app\storage\xmls
APP_URL=http://localhost:8088
```

Crie as pastas:

```powershell
mkdir "C:\Control S Fiscal Hub\app\storage\xmls"
mkdir app\storage\logs
mkdir app\storage\exports
mkdir app\storage\certificates
mkdir app\storage\runtime
```

## Rodar manualmente para teste

Abra um terminal na raiz do projeto:

```powershell
scripts\windows\start-portal.bat
```

Acesse:

```text
http://localhost:8088
```

Em outro terminal, para iniciar os robos:

```powershell
scripts\windows\start-workers.bat
```

Os robos respeitam as opcoes da tela **Configuracoes**. Se estiverem desativados, ficam aguardando.

## Instalar como tarefas do Windows

Execute o PowerShell como administrador:

```powershell
powershell.exe -ExecutionPolicy Bypass -File .\scripts\windows\install-scheduled-tasks.ps1
```

Isso cria quatro tarefas:

- `Control S Fiscal Hub - Portal`
- `Control S Fiscal Hub - Worker cte`
- `Control S Fiscal Hub - Worker nfe`
- `Control S Fiscal Hub - Worker nfse`

Para iniciar sem reiniciar o Windows:

```powershell
Start-ScheduledTask -TaskName "Control S Fiscal Hub - Portal"
Start-ScheduledTask -TaskName "Control S Fiscal Hub - Worker cte"
Start-ScheduledTask -TaskName "Control S Fiscal Hub - Worker nfe"
Start-ScheduledTask -TaskName "Control S Fiscal Hub - Worker nfse"
```

## Acesso pela rede

Libere a porta `8088` no Firewall do Windows.

No servidor:

```text
http://localhost:8088
```

Em outras maquinas:

```text
http://IP_DO_SERVIDOR:8088
```

## Backup

Banco:

```powershell
powershell.exe -ExecutionPolicy Bypass -File .\scripts\windows\backup-database.ps1
```

Arquivos que tambem precisam de backup:

- `app\.env`
- `app\storage`
- pasta configurada em `DEFAULT_DOWNLOAD_DIR`, se estiver fora de `app\storage`

## Restauracao

Restaure o banco:

```powershell
powershell.exe -ExecutionPolicy Bypass -File .\scripts\windows\restore-database.ps1 -DumpFile .\backup\controls_portal_YYYYMMDD_HHMMSS.dump
```

Depois restaure:

- `app\.env`
- `app\storage`
- pasta de XMLs configurada.

## Observacoes importantes

- O modo Windows nativo mantem as mesmas telas, banco, robos e regras fiscais.
- O portal usa o mesmo schema e as mesmas rotinas do Docker.
- O PHP embutido (`php -S`) atende bem para operacao interna controlada. Se o cliente exigir IIS/Apache, a pasta publica deve apontar para `app\public`.
- Nao altere `app\storage` manualmente com os robos em execucao.
- Para NFS-e Nacional, mantenha intervalos conservadores para evitar HTTP 429 do ADN.
