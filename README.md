# Control S Fiscal Hub

Portal fiscal multiempresa com identidade visual da Control S, focado em operação para múltiplos CNPJs.

## Principais recursos da v5.1

- Multiempresa com vários CNPJs no mesmo portal
- Certificado A1/PFX por empresa
- PostgreSQL
- Importação manual de XML de NF-e, NFC-e, CT-e e NFS-e nacional
- Coleta por empresa ou em lote para todos os CNPJs ativos
- Manifestação em massa para NF-e
- Exportação ZIP
- Importação de empresas por cadastro manual no portal
- Verificação rápida de certificado + pasta gravável
- Estrutura de pastas **configurável**
  - tudo na mesma pasta
  - padrão `/CNPJ/TIPO/ANO/MES`
  - template personalizado

## Estrutura de pastas configurável

Na tela **Configurações**, escolha:

1. **Tudo na mesma pasta**  
   Todos os XMLs ficam diretamente na pasta global ou na pasta da empresa.

2. **Estrutura padrão**  
   `/CNPJ/TIPO/ANO/MES`

3. **Template personalizado**  
   Exemplo: `{base}/{year}/{month}/{doc_type}`

Placeholders disponíveis:
- `{base}`
- `{cnpj}`
- `{doc_type}`
- `{year}`
- `{month}`
- `{day}`

## Importação de empresas por cadastro manual no portal

Na tela **Empresas**, envie um cadastro manual no portal com colunas:

`company_name;cnpj;default_download_dir;is_active`

Exemplo:
```csv
company_name;cnpj;default_download_dir;is_active
Empresa 1;12345678000199;/dados/xmls;1
Empresa 2;99887766000155;/dados/xmls;1
```

## Jobs

- `collect_all`
- `collect_missing`
- `nfe`
- `cte`
- `nfse`
- `certificate_check`

## Documentação

- `INSTALL.md`
- `TECHNICAL_OVERVIEW.md`
- `VALIDATION.md`
- `MULTI_CNPJ.md`
- `V5_NOTES.md`
# Control S Fiscal Hub

O portal pode ser executado em Docker ou diretamente no Windows.

- Instalação Docker: [INSTALL.md](INSTALL.md)
- Instalação Windows nativa: [INSTALACAO_WINDOWS.md](INSTALACAO_WINDOWS.md)
