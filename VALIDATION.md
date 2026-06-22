# Validation

## Validação local executada
- Sintaxe PHP (`php -l`) em todos os arquivos de `app/`
- Conferência estrutural dos templates
- Conferência do schema PostgreSQL/auto-migrate
- Geração do pacote ZIP final

## O que foi validado funcionalmente no código
- Salvamento de empresas
- Importação de empresas por cadastro manual no portal
- Upload de certificado com leitura de validade
- Resolução de pasta de download
- Três modos de armazenamento:
  - flat
  - segmented
  - template
- Construção de preview de pasta por empresa
- Geração de ZIP de exportação

## O que depende do ambiente real
- comunicação com SEFAZ/ADN
- distribuição real DF-e
- manifestação real em produção
- autenticação real da NFS-e nacional
