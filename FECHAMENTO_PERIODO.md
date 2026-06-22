# Fechamento manual por período

Este módulo implementa um fechamento operacional do portal por período, sem prometer recuperação fiscal ilimitada.

## O que foi implementado

- Tela `Fechamento por Período` no menu lateral.
- Seleção de uma ou várias empresas.
- Seleção de tipos: NF-e, CT-e e NFS-e Nacional.
- Período inicial/final informado pelo usuário.
- Consulta DF-e por NSU para NF-e e CT-e, com filtro interno por data após processar o retorno.
- Controle anti-consumo por empresa, tipo e ambiente.
- Lock para evitar execução concorrente do mesmo CNPJ/tipo.
- Cooldown de 1 hora quando a distribuição chega ao fim sem novos documentos ou quando há consumo indevido.
- Deduplicação: XML completo já existente não é salvo novamente.
- Manifestação em massa de NF-e pendente.
- Reprocesso manual de pendências do período.
- Exportação ZIP e CSV do fechamento.
- Pasta consolidada opcional do período.
- Logs em `storage/logs/period_closure.log`.

## Como usar

1. Acesse `Fechamento por Período`.
2. Selecione empresas ou `Todos os CNPJs ativos`.
3. Selecione os tipos desejados.
4. Informe data inicial e final.
5. Mantenha `Baixar apenas documentos sem XML completo` marcado para evitar regravação de XML completo.
6. Marque manifestação/reprocesso apenas quando quiser tratar NF-e resumida.
7. Marque exportação ZIP/CSV se quiser gerar arquivos do fechamento.
8. Execute a coleta segura do período.

O resultado aparece em cards e tabela. O histórico permite reabrir fechamentos anteriores.

## Anti-consumo e lock

O controle fica na tabela `distribution_controls`, por:

- empresa
- tipo de documento
- ambiente

Campos registrados:

- `last_distribution_check_at`
- `last_distribution_result`
- `last_ult_nsu`
- `last_max_nsu`
- `cooldown_until`
- `locked_by_job_id`
- `source_context`
- `manual_override_reason`

Durante o cooldown, o portal bloqueia nova consulta e mostra mensagem amigável com o horário de nova tentativa. Isso evita consumo indevido no serviço DF-e.

## Limitações reais

- NF-e e CT-e via DF-e são distribuídos por NSU, não por filtro nativo de data.
- O filtro por período é interno ao portal.
- A SEFAZ pode retornar documentos fora do período solicitado; eles podem ser registrados tecnicamente, mas o fechamento mostra o período solicitado.
- NF-e de destinatário pode chegar apenas como resumo.
- Para obter XML completo de NF-e resumida, pode ser necessária manifestação e nova consulta posterior.
- Há limitação temporal prática do serviço DF-e; o portal não afirma “mês completo” nem “todos os XMLs recuperados”.
- NFS-e Nacional não é tratada como idêntica a NF-e/CT-e. O fechamento considera o que já existe na base e deixa claro quando o conector atual não oferece a mesma recuperação por período.

## Status do fechamento

- `xml_completo`: documento com XML completo salvo no portal.
- `apenas_resumo`: documento recebido sem XML completo.
- `pendente_manifestacao`: NF-e resumida que depende de manifestação.
- `aguardando_novo_download`: NF-e manifestada aguardando nova distribuição.
- `ja_existente`: XML completo já estava na base antes do fechamento.
- `fora_do_periodo_solicitado`: retorno técnico por NSU fora do intervalo informado.
- `indisponivel_por_limite_temporal`: não recuperável no momento por limitação prática do serviço.
- `nao_encontrado`: nada do período foi localizado na base após a consulta possível.
- `erro`: falha de processamento ou coleta.

## Arquivos principais

- `app/src/PeriodClosureService.php`
- `app/templates/period_closure.php`
- `app/src/Repository.php`
- `app/src/Database.php`
- `app/src/Collectors/AbstractFiscalCollector.php`
- `app/src/Storage.php`

## Script de teste operacional

Para validar pelo container:

```bash
php /var/www/html/scripts/period_closure.php 2026-05-01 2026-05-13 cte
```

Use esse script com cuidado, pois ele respeita o mesmo controle anti-consumo e pode aplicar cooldown.
