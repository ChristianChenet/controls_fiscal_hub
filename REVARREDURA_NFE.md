# Revarredura NF-e

## Objetivo

A revarredura NF-e reinicia o cursor local de NSU de uma empresa quando há suspeita de histórico incompleto, resumos perdidos ou ajustes recentes no parser/coletor.

Ela não apaga XMLs completos já baixados.

## Quando usar

Use somente quando:

- o robô NF-e chegou em `ultNSU = maxNSU`;
- abril/maio ou outro período esperado não aparece;
- houve correção de parser/coleta;
- existe suspeita de que resumos foram ignorados anteriormente.

## Cuidados

- Aguarde pelo menos 1 hora após qualquer consulta NF-e.
- Não use se houver cooldown local ativo.
- Faça uma empresa por vez.
- Depois de reiniciar, execute somente **Robô NF-e / NFC-e até último NSU**.
- A SEFAZ pode rejeitar com consumo indevido se entender que o NSU não segue a sequência esperada.

## Como usar

1. Acesse **Revarrer NF-e**.
2. Escolha a empresa.
3. Revise `ultNSU`, `maxNSU`, última consulta e bloqueio local.
4. Digite `REINICIAR NFE`.
5. Clique em **Reiniciar cursor NF-e**.
6. Acesse **Radar de XML**.
7. Rode **Robô NF-e / NFC-e até último NSU**.
8. Se aparecerem documentos `apenas_resumo`, use **Robô NF-e + ciência da operação** ou manifeste pela tela Documentos.

## O que é preservado

- XMLs completos existentes.
- Documentos existentes.
- Logs e histórico.
- Configuração de pasta.

Somente estes settings são reiniciados:

- `nfe_{empresa}_ult_nsu`
- `nfe_{empresa}_max_nsu`
- `nfe_{empresa}_cooldown_until`
- `nfe_{empresa}_last_check_at`
