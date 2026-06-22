# Multi CNPJ

## O que esta versão entrega
- Cadastro de múltiplas empresas/CNPJs
- Certificado A1 por empresa
- NSU individual por empresa para NF-e, CT-e e NFS-e Nacional
- Coleta de todos os CNPJs ativos em uma única execução
- Manifestação em massa agrupada por empresa
- Importação por cadastro manual no portal
- Diagnóstico de certificado e pasta
- Estrutura de download configurável

## Estruturas possíveis de pasta
- Tudo na mesma pasta
- `/CNPJ/TIPO/ANO/MES`
- Template personalizado

## Fluxo operacional sugerido
1. Importe os CNPJs por cadastro manual no portal.
2. Envie o certificado A1 de cada empresa.
3. Rode `certificate_check`.
4. Ajuste os conectores fiscais.
5. Rode `collect_all`.
6. Faça manifestação em massa dos resumos pendentes.
7. Rode nova coleta para buscar os XMLs completos.


## Cadastro de empresas
A partir desta revisão, o cadastro das empresas é feito diretamente na tela **Empresas / CNPJs** do portal. Não é mais necessário importar planilha para iniciar a operação.
