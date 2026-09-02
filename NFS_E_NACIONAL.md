# Busca de NFS-e Nacional em Entradas

## Escopo implementado

A rotina de NFS-e consulta o ADN Nacional por NSU, empresa por empresa, usando o CNPJ cadastrado como tomador/destinatario da nota. O objetivo e trazer para a aba Entradas somente NFS-e emitidas contra o CNPJ da empresa.

## Regras principais

- Cada CNPJ possui seu proprio cursor `nfse_{empresa_id}_ult_nsu`.
- O worker automatico processa uma empresa por vez e registra log individual em `app/storage/logs/auto_nfse_worker.log` e `collector_nfse.log`.
- O HTTP 404 do ADN e tratado como NSU sem documento para aquele CNPJ, avancando o cursor sem derrubar a rotina.
- O HTTP 429 aplica bloqueio local temporario para evitar consumo indevido.
- NFS-e com tomador diferente do CNPJ consultado e ignorada e registrada no log.
- XML ja existente e deduplicado por chave, quando existir, ou pelo digest SHA-256 do XML.

## Busca retroativa

Use em Configuracoes:

- `Recuar NSU NFS-e na proxima execucao` para recuo global unico.
- `Recuo NFS-e por CNPJ` para recuar apenas uma empresa especifica.

O recuo e aplicado uma unica vez, volta para zero automaticamente e preserva a deduplicacao.

## Limite importante

"Todos os municipios" aqui significa todos os municipios que disponibilizam a NFS-e no padrao nacional ADN. Municipios que ainda nao integram o padrao nacional exigem conector municipal proprio, com endpoint/autenticacao/regra especifica.
