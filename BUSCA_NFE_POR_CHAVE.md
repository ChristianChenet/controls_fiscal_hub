# Busca NF-e por chave

## Quando usar

Use este módulo quando a distribuição por NSU chega ao `maxNSU`, mas documentos antigos de abril/maio não aparecem no portal.

A distribuição DF-e não permite pedir “abril” ou “maio” diretamente. A busca por chave permite consultar uma NF-e específica usando a chave de 44 dígitos.

## Como usar

1. Acesse **Busca por Chave**.
2. Selecione a empresa correta.
3. Cole as chaves NF-e.
4. Opcionalmente marque **Enviar ciência da operação para resumo pendente**.
5. Execute.

O limite é de 200 chaves por execução para evitar consumo indevido.

## Resultado possível

- XML completo: o portal salva na pasta configurada.
- Apenas resumo: o portal salva na base para permitir manifestação.
- Após ciência: o documento fica aguardando novo download.
- Não encontrado/sem permissão/fora da janela: o retorno fica registrado no histórico.

## Sobre voltar NSU

Não é seguro simplesmente voltar o `ultNSU` e tentar tudo novamente. A SEFAZ pode rejeitar com consumo indevido quando a consulta não usa o NSU esperado. Além disso, se outro sistema usando o mesmo CNPJ/certificado já avançou a distribuição, a recuperação por NSU pode não voltar documentos antigos.

Para documentos antigos não retornados por NSU, o caminho operacional mais seguro é obter as chaves e consultar por chave.
