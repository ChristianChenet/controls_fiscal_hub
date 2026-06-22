## Atualização multiempresa

Esta versão suporta múltiplos CNPJs no mesmo portal, com certificado A1 por empresa, NSU separado por empresa, coleta consolidada para todos os CNPJs ativos, filtros por empresa, pastas de download por empresa e manifestação em massa agrupada por empresa.

# Visão técnica

## Camadas

### Portal
- `app/public/index.php`
- templates em `app/templates`

### Infra
- PostgreSQL
- Docker
- cron / n8n

### Certificado
- upload do PFX
- senha criptografada
- extração temporária para PEM em runtime

### Conectores fiscais

#### NF-e / NFC-e
- `NFeConnector`
- serviço: `NFeDistribuicaoDFe`
- persistência por `ultNSU`
- reprocessamento após manifestação

#### Manifestação
- `ManifestationService`
- assinatura XMLDSig no `infEvento`
- envio para `RecepcaoEvento4`

#### CT-e
- `CTeConnector`
- distribuição DF-e do CT-e

#### NFS-e Nacional
- `NFSeNationalConnector`
- conector REST configurável
- pensado para ADN e cenário nacional

## Fluxo operacional

1. usuário configura empresa e certificado
2. coletor consulta distribuição por NSU
3. documentos completos são salvos em pasta
4. resumos entram como `summary_only`
5. operador manifesta em massa os pendentes
6. próxima coleta traz XML completo quando disponibilizado

## Banco

Tabela principal:
- `documents`

Campos relevantes:
- `doc_type`
- `access_key`
- `status`
- `manifestation_status`
- `schema_name`
- `xml_path`

## Segurança

- senha do certificado criptografada com `APP_KEY`
- PEM temporário com permissão restrita
- CSRF nas ações POST
- login opcional
