# Conferência de XMLs para exportação ao ERP

## O que foi ajustado

- A tela **Documentos** passou a ter filtros por empresa, tipo, status, texto livre, colunas do grid e período de emissão.
- A listagem exibe 200 registros por página, total de registros filtrados, soma total filtrada, quantidade selecionada e soma dos valores selecionados.
- Foi adicionada exportação para Excel a partir dos filtros aplicados.
- A tabela ganhou rolagem, cabeçalho fixo e colunas redimensionáveis pelo navegador.
- A importação manual agora ignora XML duplicado por digest ou por chave completa já existente, registra falhas por arquivo e mantém os demais XMLs em processamento.
- O parser de XML foi reforçado para identificar CT-e por conteúdo real do XML, incluindo `CTe`, `infCte`, `chCTe`, `CTeOS` e valores em `vTPrest`.
- Foi adicionada a rotina `php scripts/repair_documents.php` para reclassificar documentos já importados com tipo ou valor incorreto.

## Como validar o período de 01/05/2026 a 10/05/2026

1. Acesse **Documentos**.
2. Informe **Data inicial** `01/05/2026` e **Data final** `10/05/2026`.
3. Filtre por tipo quando necessário: `NFE`, `CTE`, `NFSE` ou todos.
4. Confira os cartões **Total filtrado** e **Soma filtrada**.
5. Use os filtros por coluna para conferir empresa, chave, emissor e origem.
6. Selecione os documentos do período e confira **Selecionados** e **Soma selecionada**.
7. Exporte para Excel e compare com o ERP ou relatório operacional.
8. Para XMLs, use **Exportar ZIP** somente após confirmar que os registros filtrados estão com status **XML completo**.

## Como validar o Radar por Período

1. Acesse **Radar por Período**.
2. Execute o período `01/05/2026` a `05/05/2026`.
3. O radar deve considerar apenas documentos cuja data de emissão/competência esteja dentro do intervalo.
4. A distribuição DF-e continua sendo por NSU. O filtro de período é interno ao portal.
5. Se houver bloqueio por cooldown, aguarde o horário exibido antes de tentar nova consulta.

## Como garantir segurança antes da exportação

- Rode a importação manual dos XMLs recebidos para o período.
- Execute `php scripts/repair_documents.php` para corrigir registros antigos eventualmente classificados de forma errada.
- Em **Documentos**, filtre `01/05/2026` a `10/05/2026` e confira:
  - nenhum CT-e aparecendo como NFS-e;
  - valores preenchidos quando existirem no XML;
  - ausência de duplicidade pela mesma chave;
  - status **XML completo** para documentos que serão enviados ao ERP.
- Use o Excel como relatório de conferência e o ZIP como pacote operacional de XML.
- Quando a origem for DF-e, lembre que o portal não promete recuperação histórica ilimitada. A cobertura depende do que o serviço disponibiliza por NSU e das manifestações aplicáveis.
