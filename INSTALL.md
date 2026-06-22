## Atualização multiempresa

Esta versão suporta múltiplos CNPJs no mesmo portal, com certificado A1 por empresa, NSU separado por empresa, coleta consolidada para todos os CNPJs ativos, filtros por empresa, pastas de download por empresa e manifestação em massa agrupada por empresa.

# Instalação

## Modos de instalacao

O projeto pode rodar de duas formas:

- **Docker**: recomendado para ambiente padronizado.
- **Windows nativo**: PHP + PostgreSQL instalados diretamente no Windows Desktop/Server.

Para Windows nativo, use o guia [INSTALACAO_WINDOWS.md](INSTALACAO_WINDOWS.md).

## Requisitos Docker

- Docker e Docker Compose
- certificado A1/PFX do CNPJ
- acesso de rede aos endpoints fiscais

## Passo a passo

1. Extraia o pacote.
2. Copie `.env.example` para `app/.env`.
3. Ajuste:
   - credenciais do PostgreSQL
   - ambiente `SEFAZ_ENVIRONMENT`
   - URLs dos serviços
   - base/path da NFS-e nacional
4. Suba com:

```bash
docker compose up --build -d
```

5. Acesse o portal em `http://localhost:8088`.
6. Vá em **Configurações** e informe:
   - empresa
   - CNPJ
   - pasta padrão
   - certificado A1/PFX
   - endpoints fiscais
7. Vá em **Coletas e Jobs** e rode:
   - NF-e / NFC-e
   - CT-e
   - NFS-e Nacional

## Cron

Exemplo a cada 30 minutos:

```bash
*/30 * * * * docker exec controls-portal php /var/www/html/scripts/cron.php collect_all >> /var/log/controls-portal-cron.log 2>&1
```

## n8n

O workflow em `n8n/collect-all-workflow.json` pode chamar o script por HTTP ou Execute Command.

## Observações

- para produção, valide os endpoints por autorizador/ambiente
- mantenha o certificado A1 sempre válido
- use volume persistente para a pasta de XMLs


## Configuração da estrutura de pastas

Depois de subir o portal:

1. Abra **Configurações**
2. Defina `Pasta padrão global de download`
3. Escolha `Estrutura de pastas`
4. Se usar template, informe algo como:
   - `{base}/{cnpj}/{doc_type}/{year}/{month}`
   - `{base}/{year}/{month}/{doc_type}`
   - `{base}` para gravar tudo junto

## Cadastro em lote dos CNPJs

1. Acesse **Empresas**
2. Importe um cadastro manual no portal ou cole o conteúdo
3. Faça upload do certificado de cada empresa
4. Rode **Validar certificado e pasta**
