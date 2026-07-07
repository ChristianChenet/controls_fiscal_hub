# Conferencia de Faturamento Realizado

## Objetivo

A rotina **Dashboard Faturamento** permite auditar documentos fiscais de venda e devolucao integrados do ERP. Ela nao substitui a captura de XML: o ERP envia os dados fiscais estruturados, e o Fiscal Hub armazena apenas o necessario para conferencia, filtros, dashboards, detalhamento fiscal, itens e visualizacao do XML vinculado quando existir.

## Escopo fiscal

Documentos contemplados:

- NF-e de venda
- NFC-e de venda
- NFS-e de venda
- NF-e de devolucao

Nao devem ser integrados pedidos, orcamentos, romaneios ou registros que nao sejam documentos fiscais.

## Regra essencial das lojas

A rotina separa obrigatoriamente:

- **Loja de emissao**: loja/CNPJ emitente do documento fiscal. Deve fechar com a loja do certificado digital.
- **Loja do pedido**: loja/CNPJ de origem comercial da venda ou pedido.

Esses campos existem em filtros, grid, dashboards, detalhe e exportacao.

## Abas da tela

### Visao Geral

Mostra KPIs e analises gerenciais:

- Total faturado no dia, semana, mes e mes anterior, sempre como venda menos devolucao e respeitando os filtros globais
- Faturamento do periodo com bruto, devolucao negativa e liquido
- Total de impostos
- Total de creditos
- Saldo tributario liquido
- Quantidade de documentos
- Ticket medio
- Evolucao diaria
- Graficos por loja do pedido e loja de emissao
- Rankings por tipo, loja de emissao, loja do pedido, vendedor, cliente, produto e grupo

### Documentos Fiscais

Lista documentos com paginacao de 200 registros, ordenacao, filtros locais por coluna, seletor de colunas, redimensionamento manual e exportacao propria do grid. Principais colunas:

- Data de emissao
- Data/hora de autorizacao
- Tipo de documento
- Finalidade
- Situacao
- Serie e numero
- Chave de acesso
- Loja de emissao e CNPJ
- Loja do pedido e CNPJ
- Cliente e CPF/CNPJ
- Vendedor
- Valores e impostos
- Indicacao de XML disponivel

### Detalhamento Fiscal

Exibe cabecalho, lojas, cliente, vendedor, totais, impostos, creditos, dados de integracao e itens do documento selecionado.

### Analise Tributaria

Usa o mesmo detalhe do documento selecionado e destaca total de impostos, creditos e saldo tributario. A estrutura esta preparada para receber impostos detalhados por item via `taxes_json`.

### Itens / Produtos

Exibe itens integrados com produto, codigos, quantidade, valor, CFOP, NCM, CST/CSOSN, lojas, cliente, vendedor e documento de origem. A aba possui filtros locais por coluna, seletor de colunas e exportacao propria.

### XML

Exibe o XML vinculado quando o ERP enviar `xml_content`. O XML e complementar para auditoria e nao e a origem principal da rotina.

## Filtros

Filtros combinaveis:

- Data inicial e final
- Venda / devolucao
- Tipo de documento
- Situacao
- Loja de emissao e CNPJ
- Loja do pedido e CNPJ
- Cliente e CPF/CNPJ
- Vendedor
- Numero, serie e chave de acesso
- Produto e grupo de produto
- CFOP, NCM, CST/CSOSN
- XML disponivel
- Faixa de valor
- Considerar devolucoes
- Considerar creditos tributarios

## Regras de calculo

- **Faturamento bruto**: soma de `gross_amount` apenas de vendas.
- **Total devolucoes**: soma positiva de devolucoes, exibida como estorno/negativo nos indicadores. O sistema reconhece devolucao por `purpose = devolucao`, `document_type = DEVOLUCAO_NFE` ou `return_amount <> 0`.
- **Faturamento liquido**: vendas menos devolucoes. Para devolucoes, o sistema estorna `return_amount`; se ele vier zerado, usa `gross_amount` ou `net_amount` como base de estorno.
- **Total de impostos**: soma de `taxes_amount`, com devolucoes estornando o imposto.
- **Total de creditos tributarios**: soma de `tax_credits_amount`, respeitando o filtro de creditos.
- **Saldo tributario liquido**: impostos menos creditos, com devolucoes estornadas.
- **Ticket medio**: faturamento bruto de vendas / quantidade de documentos de venda.
- **Quantidade por dimensao**: agrupamentos por tipo, loja, vendedor, cliente, produto e grupo.

Documentos cancelados, denegados, inutilizados e rejeitados devem vir com `document_status` adequado. Eles aparecem claramente na grade e podem ser filtrados.

## Estrutura tecnica da integracao

### Tabela `revenue_documents`

| Campo | Descricao | Tipo esperado | Obrigatorio |
| --- | --- | --- | --- |
| integration_id | ID unico do documento no ERP | texto | recomendado |
| issue_date | Data de emissao | data | sim |
| authorization_datetime | Data/hora de autorizacao | data/hora | nao |
| document_type | NFE, NFCE, NFSE, DEVOLUCAO_NFE | texto | sim |
| purpose | venda ou devolucao | texto | sim |
| document_status | autorizado, cancelado, denegado, inutilizado, rejeitado | texto | sim |
| series | Serie fiscal | texto | nao |
| number | Numero fiscal | texto | nao |
| order_number | Numero/codigo do pedido no ERP | texto | recomendado |
| access_key | Chave de acesso | texto | recomendado |
| issuing_store_name | Loja de emissao | texto | sim |
| issuing_store_cnpj | CNPJ de emissao | texto | sim |
| order_store_name | Loja do pedido | texto | recomendado |
| order_store_cnpj | CNPJ da loja do pedido | texto | recomendado |
| customer_name | Cliente | texto | recomendado |
| customer_document | CPF/CNPJ do cliente | texto | recomendado |
| seller_name | Vendedor | texto | recomendado |
| seller_code | Codigo do vendedor no ERP | texto | nao |
| gross_amount | Valor total bruto | decimal | sim |
| products_amount | Valor de produtos | decimal | nao |
| services_amount | Valor de servicos | decimal | nao |
| freight_amount | Valor de frete | decimal | nao |
| discount_amount | Desconto | decimal | nao |
| return_amount | Valor de devolucao | decimal | nao |
| taxes_amount | Total de impostos | decimal | nao |
| tax_credits_amount | Total de creditos | decimal | nao |
| icms_amount | Valor de ICMS | decimal | nao |
| pis_amount | Valor de PIS | decimal | nao |
| cofins_amount | Valor de COFINS | decimal | nao |
| ipi_amount | Valor de IPI | decimal | nao |
| iss_amount | Valor de ISS | decimal | nao |
| st_amount | Valor de ICMS-ST/ST | decimal | nao |
| ibs_amount | Valor de IBS | decimal | nao |
| cbs_amount | Valor de CBS | decimal | nao |
| difal_amount | Valor de DIFAL | decimal | nao |
| other_taxes_amount | Outros impostos nao classificados | decimal | nao |
| net_amount | Valor liquido gerencial | decimal | sim |
| integration_source | Origem/camada da integracao | texto | nao |
| integration_payload | Payload tecnico recebido | JSON | nao |
| xml_content | XML vinculado | texto | nao |
| xml_integrated_at | Data/hora de integracao do XML | data/hora | nao |

### Tabela `revenue_items`

| Campo | Descricao | Tipo esperado | Obrigatorio |
| --- | --- | --- | --- |
| revenue_document_id | Documento pai | inteiro | sim |
| product_name | Produto | texto | sim |
| internal_code | Codigo interno | texto | nao |
| erp_code | Codigo ERP | texto | nao |
| gtin | GTIN/EAN | texto | nao |
| product_group | Grupo/linha | texto | nao |
| quantity | Quantidade | decimal | nao |
| unit | Unidade | texto | nao |
| unit_amount | Valor unitario | decimal | nao |
| total_amount | Valor total do item | decimal | nao |
| discount_amount | Desconto do item | decimal | nao |
| cfop | CFOP | texto | recomendado |
| ncm | NCM | texto | recomendado |
| cst_csosn | CST/CSOSN | texto | recomendado |
| taxes_amount | Tributos do item | decimal | nao |
| tax_credits_amount | Creditos do item | decimal | nao |
| icms_amount | ICMS do item | decimal | nao |
| pis_amount | PIS do item | decimal | nao |
| cofins_amount | COFINS do item | decimal | nao |
| ipi_amount | IPI do item | decimal | nao |
| iss_amount | ISS do item | decimal | nao |
| st_amount | ICMS-ST/ST do item | decimal | nao |
| ibs_amount | IBS do item | decimal | nao |
| cbs_amount | CBS do item | decimal | nao |
| difal_amount | DIFAL do item | decimal | nao |
| other_taxes_amount | Outros impostos do item | decimal | nao |
| taxes_json | Detalhamento de ICMS, ST, IPI, PIS, COFINS, ISS, FCP, DIFAL e creditos | JSON | recomendado |

## Performance

Foram criados indices para:

- periodo de emissao
- chave de acesso
- tipo de documento
- CNPJ de emissao
- CNPJ da loja do pedido
- CPF/CNPJ do cliente
- documento do item
- nome do produto

A listagem usa paginacao de 200 registros e os dashboards usam agregacoes no banco.

## Permissao

A rotina e liberada para todos os usuarios. Usuarios comuns veem Dashboard XML, Conferencia de Faturamento e Documentos.

## Criterios de aceite

- Menu abaixo do Dashboard XML.
- Filtros aplicaveis e persistidos por URL.
- KPIs calculados com base no periodo filtrado.
- Loja de emissao e loja do pedido separadas em filtros, grade, detalhe e exportacao.
- Listagem paginada e exportavel.
- Itens e XML visiveis quando integrados.
- Estado vazio claro quando nao houver dados.
- Estrutura pronta para integracao ERP sem depender do XML como origem principal.
