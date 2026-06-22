# V5.1 Notes

- Cadastro de empresas agora é feito diretamente no portal.
- Removida a dependência operacional de planilha para iniciar o multi-CNPJ.
- Mantida a configuração flexível da estrutura de pastas.

# V5 Notes

## Novidades
- Importação em lote de empresas via cadastro manual no portal ou texto colado
- Modo de armazenamento configurável
- Preview da pasta por empresa
- Validação rápida de certificado e pasta
- Job `certificate_check`
- Job `collect_missing` mantido como alias operacional de reprocessamento

## Regras de armazenamento
A pasta final é resolvida nesta ordem:
1. pasta da empresa, se preenchida
2. pasta global
3. modo escolhido em Configurações
4. template, se o modo for `template`

## Exemplos de template
- `{base}/{doc_type}`
- `{base}/{year}/{month}`
- `{base}/{cnpj}/{year}/{doc_type}`
