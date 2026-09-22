<?php include __DIR__ . '/layout_top.php'; ?>
<?php
$filters = $documentFilters ?? [];
$documentsDeferred = !empty($documentsDeferred);
$totals = $documentTotals ?? ['total' => 0, 'total_value' => 0];
$currentPage = (int)($documentPage ?? 1);
$perPage = (int)($documentPerPage ?? 200);
$totalPages = max(1, (int)ceil(($totals['total'] ?? 0) / max(1, $perPage)));
$baseQuery = array_filter($filters, static fn($value) => $value !== '' && $value !== null);
$baseQuery['page'] = 'documents';
if (!$documentsDeferred) {
    $baseQuery['load_documents'] = '1';
}
$exportQuery = $baseQuery;
$exportQuery['page'] = 'documents_export';
$timelineMode = (string)($_GET['timeline_mode'] ?? 'value') === 'count' ? 'count' : 'value';
$timeline = $documentsTimeline ?? ['months' => [], 'rows' => [], 'month_totals' => [], 'grand_total' => ['value' => 0.0, 'count' => 0, 'erp' => 0, 'accounting' => 0, 'erp_value' => 0.0, 'accounting_value' => 0.0]];
$timelineExportQuery = $baseQuery;
$timelineExportQuery['page'] = 'documents_timeline_export';
$timelineExportQuery['timeline_mode'] = $timelineMode;
$timelineModeQuery = $baseQuery;
$timelineModeQuery['page'] = 'documents';
$timelineCellBaseQuery = $baseQuery;
unset($timelineCellBaseQuery['p']);
$companyOptions = array_map(static fn(array $co): array => [
    'value' => (string)$co['id'],
    'label' => (string)$co['company_name'] . ' - ' . (string)$co['cnpj'],
], $companies ?? []);
$supplierGroupOptions = array_map(static fn(array $group): array => [
    'value' => (string)$group['id'],
    'label' => (string)$group['description'] . ' (' . (string)($group['supplier_count'] ?? 0) . ')',
], $supplierGroups ?? []);
$statusOptions = array_map(static fn(array $row): string => (string)($row['status'] ?? ''), $documentStatusOptions ?? []);
$selectedStatus = (string)($filters['status'] ?? '');
if ($selectedStatus !== '' && $selectedStatus !== 'not_cancelled' && !in_array($selectedStatus, $statusOptions, true)) {
    $statusOptions[] = $selectedStatus;
}
$filteredTotal = (int)($totals['total'] ?? 0);
$documentFilterKeys = [
    'company_id','doc_type','status','manifestation_status','posted_to_erp','integrated','accounting_posted','supplier_group_id','without_referenced_nfe','cte_taker_only','ignore_cfops','entry_only','date_start','date_end',
    'company_q','number_q','issuer_q','recipient_q','access_key_q','referenced_nfe_q','referenced_number_q','product_q','cfop_q','source_q','q','sort_by','sort_dir',
];
$canShowDocumentMirror = static fn(array $doc): bool => in_array(strtoupper((string)($doc['doc_type'] ?? '')), ['NFE', 'CTE', 'NFSE'], true)
    && (string)($doc['status'] ?? '') !== 'apenas_resumo';
$timelineDisplayValue = static function (array $cell, string $mode): string {
    return $mode === 'count' ? (string)(int)($cell['count'] ?? 0) : format_money((float)($cell['value'] ?? 0));
};
$timelinePostedValue = static function (array $cell, string $mode, string $prefix): string {
    return $mode === 'count'
        ? (string)(int)($cell[$prefix] ?? 0)
        : format_money((float)($cell[$prefix . '_value'] ?? 0));
};
$timelinePostedDiffClass = static function (array $cell, string $mode, string $prefix): string {
    if ($mode === 'count') {
        return (int)($cell[$prefix] ?? 0) !== (int)($cell['count'] ?? 0) ? 'timeline-posted-diff' : '';
    }
    return abs((float)($cell[$prefix . '_value'] ?? 0) - (float)($cell['value'] ?? 0)) > 0.009 ? 'timeline-posted-diff' : '';
};
?>
<div class="page-header split-header documents-page-header">
    <div>
        <h1>Entradas</h1>
        <p>Conferência operacional de NF-e, CT-e e NFS-e importados, com filtros, DANFE/DACTE e exportação dos XMLs.</p>
    </div>
</div>

<form method="get" class="card card-compact documents-filter">
    <input type="hidden" name="page" value="documents">
    <input type="hidden" name="load_documents" value="1">
    <div class="form-row documents-filter-row" id="documents-filter-fields">
        <label>Pesquisa geral
            <input type="text" name="q" placeholder="Fornecedor, CNPJ, chave ou numero" value="<?= h((string)($filters['q'] ?? '')) ?>">
        </label>
        <label>Numero da nota
            <input type="text" name="number_q" placeholder="Numero" value="<?= h((string)($filters['number_q'] ?? '')) ?>">
        </label>
        <label>Chave de acesso
            <input type="text" name="access_key_q" placeholder="44 digitos ou parte da chave" value="<?= h((string)($filters['access_key_q'] ?? '')) ?>">
        </label>
        <label>Numero referenciado
            <input type="text" name="referenced_number_q" placeholder="Numero da nota ref." value="<?= h((string)($filters['referenced_number_q'] ?? '')) ?>">
        </label>
        <label>Produto
            <input type="text" name="product_q" placeholder="Descricao do produto" value="<?= h((string)($filters['product_q'] ?? '')) ?>">
        </label>
        <label>CFOP
            <input type="text" name="cfop_q" placeholder="CFOP do item" value="<?= h((string)($filters['cfop_q'] ?? '')) ?>">
        </label>
        <label>Origem
            <input type="text" name="source_q" placeholder="Ex.: nfse_pdf_import" value="<?= h((string)($filters['source_q'] ?? '')) ?>">
        </label>
        <label class="cfop-ignore-field">Ignorados
            <input type="hidden" name="ignore_cfops" value="0">
            <span class="cfop-ignore-line" title="Quando marcado, a tela oculta documentos com CFOPs ou notas cadastrados como ignorados, pois nao devem entrar na rotina operacional de escrituracao. Clique no texto para gerenciar a lista.">
                <input type="checkbox" name="ignore_cfops" value="1" <?= ((string)($filters['ignore_cfops'] ?? '1') !== '0') ? 'checked' : '' ?>>
                <button class="cfop-ignore-link" type="button" data-open-ignored-cfops>Ignorar CFOPs / Notas</button>
            </span>
        </label>
        <?= compact_multi_picker('Empresa', 'company_id', $companyOptions, $filters['company_id'] ?? []) ?>
        <label>Tipo
            <select name="doc_type">
                <option value="">NF-e, CT-e e NFS-e</option>
                <?php foreach (['NFE' => 'NF-e', 'CTE' => 'CT-e', 'NFSE' => 'NFS-e'] as $type => $label): ?>
                    <option value="<?= h($type) ?>" <?= (($filters['doc_type'] ?? '') === $type) ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Status
            <select name="status">
                <option value="">Todos</option>
                <option value="not_cancelled" <?= (($filters['status'] ?? '') === 'not_cancelled') ? 'selected' : '' ?>>Exceto cancelados</option>
                <?php foreach ($statusOptions as $status): ?>
                    <option value="<?= h($status) ?>" <?= (($filters['status'] ?? '') === $status) ? 'selected' : '' ?>><?= h(document_status_label($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Nota lançada no ERP
            <select name="posted_to_erp">
                <option value="">Todas</option>
                <option value="1" <?= (($filters['posted_to_erp'] ?? '') === '1') ? 'selected' : '' ?>>Sim</option>
                <option value="0" <?= (($filters['posted_to_erp'] ?? '') === '0') ? 'selected' : '' ?>>Não</option>
            </select>
        </label>
        <label>Integrado
            <select name="integrated">
                <option value="">Todas</option>
                <option value="1" <?= (($filters['integrated'] ?? '') === '1') ? 'selected' : '' ?>>Sim</option>
                <option value="0" <?= (($filters['integrated'] ?? '') === '0') ? 'selected' : '' ?>>Não</option>
            </select>
        </label>
        <label>Lançada contabilidade
            <select name="accounting_posted">
                <option value="">Todas</option>
                <option value="S" <?= (($filters['accounting_posted'] ?? '') === 'S') ? 'selected' : '' ?>>Sim</option>
                <option value="N" <?= (($filters['accounting_posted'] ?? '') === 'N') ? 'selected' : '' ?>>Não</option>
            </select>
        </label>
        <?= compact_multi_picker('Grupo', 'supplier_group_id', $supplierGroupOptions, $filters['supplier_group_id'] ?? []) ?>
        <label>Vinculo NF-e
            <select name="without_referenced_nfe">
                <option value="">Todos</option>
                <option value="1" <?= !empty($filters['without_referenced_nfe']) ? 'selected' : '' ?>>Documentos sem NF-e vinculada</option>
            </select>
        </label>
        <label>Tomador CT-e
            <select name="cte_taker_only">
                <option value="">Todos os CT-e</option>
                <option value="1" <?= !empty($filters['cte_taker_only']) ? 'selected' : '' ?>>Somente CT-e em que somos tomador</option>
            </select>
        </label>
        <label>Data inicial
            <input type="date" name="date_start" value="<?= h((string)($filters['date_start'] ?? '')) ?>">
        </label>
        <label>Data final
            <input type="date" name="date_end" value="<?= h((string)($filters['date_end'] ?? '')) ?>">
        </label>
        <label>Ordenar por
            <select name="sort_by">
                <?php foreach ([
                    'issue_date' => 'Emissão',
                    'company_name' => 'Empresa',
                    'doc_type' => 'Tipo',
                    'number' => 'Número',
                    'issuer_name' => 'Emissor',
                    'total_value' => 'Valor',
                    'status' => 'Status',
                    'imported_at' => 'Importacao',
                    'id' => 'Cadastro',
                ] as $value => $label): ?>
                    <option value="<?= h($value) ?>" <?= (($filters['sort_by'] ?? 'issue_date') === $value) ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Direção
            <select name="sort_dir">
                <option value="desc" <?= (($filters['sort_dir'] ?? 'desc') === 'desc') ? 'selected' : '' ?>>Decrescente</option>
                <option value="asc" <?= (($filters['sort_dir'] ?? 'desc') === 'asc') ? 'selected' : '' ?>>Crescente</option>
            </select>
        </label>
    </div>
    <div class="form-actions">
        <button class="button-link button-compact" type="button" data-collapse-target="#documents-filter-fields" data-collapse-key="controls.documents.filters.collapsed" data-hide-label="Recolher filtros" data-show-label="Mostrar filtros">Recolher filtros</button>
        <button class="button-link button-compact" type="button" data-collapse-target="#columns-panel" data-collapse-key="controls.documents.columns.collapsed" data-hide-label="Esconder colunas" data-show-label="Mostrar colunas">Esconder colunas</button>
        <button class="button-link button-compact" type="button" data-collapse-target="#documents-export-panel" data-collapse-key="controls.documents.export.collapsed" data-hide-label="Recolher exportação" data-show-label="Exportar XML/DANFE">Exportar XML/DANFE</button>
        <a class="button-link button-compact" href="<?= h(base_url('?page=documents&clear_filters=1')) ?>">Limpar</a>
        <button class="primary">Filtrar entradas</button>
    </div>
    <div id="columns-panel" class="columns-panel">
        <?php foreach ([
            'empresa' => 'Empresa',
            'tipo' => 'Tipo',
            'numero' => 'Número',
            'emissor' => 'Emissor',
            'cidade_uf' => 'Cidade / UF',
            'grupo' => 'Grupo',
            'tomador' => 'Tomador',
            'chave' => 'Chave',
            'nfe_vinculada' => 'NF-e vinculada',
            'numero_referenciado' => 'Numero referenciado',
            'erp' => 'Nota lançada no ERP',
            'integrado' => 'Integrado',
            'entrada_erp' => 'Entrada ERP',
            'contabilidade' => 'Lançada contabilidade',
            'eventos_informativos' => 'Eventos informativos',
            'cfop' => 'CFOP',
            'emissao' => 'Emissão',
            'valor' => 'Valor',
            'status' => 'Status',
            'manifestacao' => 'Manifestacao',
            'origem' => 'Origem',
            'link_espelho' => 'Link espelho',
            'acoes' => 'Ações',
        ] as $columnKey => $columnLabel): ?>
            <?php $defaultVisible = $columnKey !== 'origem'; ?>
            <label class="checkbox-inline"><input type="checkbox" <?= $defaultVisible ? 'checked' : '' ?> data-default-visible="<?= $defaultVisible ? '1' : '0' ?>" data-column-toggle="<?= h($columnKey) ?>"> <?= h($columnLabel) ?></label>
        <?php endforeach; ?>
    </div>
</form>

<div class="grid four documents-summary">
    <div class="card stat neutral"><strong><?= h((string)($totals['total'] ?? 0)) ?></strong><span>Total filtrado</span></div>
    <div class="card stat ok"><strong><?= h(format_money((float)($totals['total_value'] ?? 0))) ?></strong><span>Soma filtrada</span></div>
    <div class="card stat neutral"><strong id="selected-count">0</strong><span>Selecionados</span></div>
    <div class="card stat ok"><strong id="selected-value">R$ 0,00</strong><span>Soma selecionada</span></div>
</div>

<div class="documents-tabs" data-documents-tabs>
    <button class="documents-tab is-active" type="button" data-documents-tab="grid">Grid de entradas</button>
    <button class="documents-tab" type="button" data-documents-tab="timeline">Linha do tempo</button>
</div>

<section class="card documents-timeline-card is-hidden" data-documents-tab-panel="timeline">
    <div class="grid-toolbar documents-grid-toolbar">
        <div>
            <h2>Linha do tempo</h2>
            <small>Valores por fornecedor e mês respeitando os filtros aplicados em Entradas.</small>
        </div>
        <div class="documents-action-bar">
            <form method="get" class="inline timeline-mode-form">
                <input type="hidden" name="page" value="documents">
                <input type="hidden" name="load_documents" value="1">
                <?= hidden_filter_inputs($documentFilterKeys, $filters) ?>
                <label>Visualizar
                    <select name="timeline_mode" onchange="this.form.submit()">
                        <option value="value" <?= $timelineMode === 'value' ? 'selected' : '' ?>>Valor</option>
                        <option value="count" <?= $timelineMode === 'count' ? 'selected' : '' ?>>Qtde de documentos</option>
                    </select>
                </label>
            </form>
            <button class="button-compact timeline-mini-action" type="button" data-timeline-fullscreen>🖥️ Tela cheia</button>
            <a class="button-link button-compact timeline-mini-action" href="<?= h(base_url('?' . http_build_query($timelineExportQuery))) ?>">Exportar Excel</a>
        </div>
    </div>
    <?php if ($documentsDeferred): ?>
        <div class="empty-state">Use os filtros acima e clique em Filtrar entradas para carregar a linha do tempo.</div>
    <?php elseif (empty($timeline['months']) || empty($timeline['rows'])): ?>
        <div class="empty-state">Nenhum lançamento encontrado para montar a linha do tempo.</div>
    <?php else: ?>
        <div class="documents-timeline-wrap">
            <table class="documents-timeline-table">
                <thead>
                    <tr>
                        <th class="timeline-supplier-col">Fornecedor - Emissor</th>
                        <?php foreach ($timeline['months'] as $month): ?>
                            <th><?= h((string)$month['label']) ?></th>
                        <?php endforeach; ?>
                        <th class="timeline-total-col">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($timeline['rows'] as $row): ?>
                    <tr>
                        <th class="timeline-supplier-col">
                            <strong><?= h((string)$row['issuer_name']) ?></strong>
                            <small><?= h((string)$row['issuer_cnpj']) ?></small>
                        </th>
                        <?php foreach ($timeline['months'] as $month): ?>
                            <?php
                                $cell = $row['months'][$month['key']] ?? ['value' => 0.0, 'count' => 0, 'erp' => 0, 'accounting' => 0, 'erp_value' => 0.0, 'accounting_value' => 0.0];
                                $cellQuery = $timelineCellBaseQuery;
                                $cellQuery['page'] = 'documents_timeline_cell';
                                $cellQuery['timeline_issuer_group_key'] = (string)($row['issuer_key'] ?? $row['issuer_name']);
                                $cellQuery['timeline_issuer_cnpjs'] = (string)($row['issuer_cnpj'] ?? '');
                                $cellQuery['timeline_month'] = (string)$month['key'];
                                $cellExportQuery = $cellQuery;
                                $cellExportQuery['page'] = 'documents_timeline_cell_export';
                                $hasDocs = (int)($cell['count'] ?? 0) > 0;
                            ?>
                            <td class="<?= $hasDocs ? 'timeline-has-docs' : 'timeline-empty-month' ?>">
                                <?php if ($hasDocs): ?>
                                    <button type="button" class="timeline-cell-button" data-timeline-cell="<?= h(base_url('?' . http_build_query($cellQuery))) ?>" data-timeline-export="<?= h(base_url('?' . http_build_query($cellExportQuery))) ?>" data-timeline-title="<?= h((string)$row['issuer_name'] . ' | ' . (string)$month['label']) ?>">
                                        <strong><?= h($timelineDisplayValue($cell, $timelineMode)) ?></strong>
                                        <small><span class="<?= h($timelinePostedDiffClass($cell, $timelineMode, 'erp')) ?>">Decis <?= h($timelinePostedValue($cell, $timelineMode, 'erp')) ?></span> | <span class="<?= h($timelinePostedDiffClass($cell, $timelineMode, 'accounting')) ?>">Contab. <?= h($timelinePostedValue($cell, $timelineMode, 'accounting')) ?></span></small>
                                    </button>
                                <?php else: ?>
                                    <span class="timeline-empty-label">Sem lançamento</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td class="timeline-total-col">
                            <strong><?= h($timelineDisplayValue($row['total'], $timelineMode)) ?></strong>
                            <small><span class="<?= h($timelinePostedDiffClass($row['total'], $timelineMode, 'erp')) ?>">Decis <?= h($timelinePostedValue($row['total'], $timelineMode, 'erp')) ?></span> | <span class="<?= h($timelinePostedDiffClass($row['total'], $timelineMode, 'accounting')) ?>">Contab. <?= h($timelinePostedValue($row['total'], $timelineMode, 'accounting')) ?></span></small>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th class="timeline-supplier-col">Total por mês</th>
                        <?php foreach ($timeline['months'] as $month): ?>
                            <?php $totalCell = $timeline['month_totals'][$month['key']] ?? ['value' => 0.0, 'count' => 0, 'erp' => 0, 'accounting' => 0, 'erp_value' => 0.0, 'accounting_value' => 0.0]; ?>
                            <th>
                                <strong><?= h($timelineDisplayValue($totalCell, $timelineMode)) ?></strong>
                                <small><span class="<?= h($timelinePostedDiffClass($totalCell, $timelineMode, 'erp')) ?>">Decis <?= h($timelinePostedValue($totalCell, $timelineMode, 'erp')) ?></span> | <span class="<?= h($timelinePostedDiffClass($totalCell, $timelineMode, 'accounting')) ?>">Contab. <?= h($timelinePostedValue($totalCell, $timelineMode, 'accounting')) ?></span></small>
                            </th>
                        <?php endforeach; ?>
                        <th class="timeline-total-col">
                            <strong><?= h($timelineDisplayValue($timeline['grand_total'], $timelineMode)) ?></strong>
                            <small><span class="<?= h($timelinePostedDiffClass($timeline['grand_total'], $timelineMode, 'erp')) ?>">Decis <?= h($timelinePostedValue($timeline['grand_total'], $timelineMode, 'erp')) ?></span> | <span class="<?= h($timelinePostedDiffClass($timeline['grand_total'], $timelineMode, 'accounting')) ?>">Contab. <?= h($timelinePostedValue($timeline['grand_total'], $timelineMode, 'accounting')) ?></span></small>
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</section>

<div class="modal-backdrop action-modal is-hidden" id="timeline-detail-modal" role="dialog" aria-modal="true" aria-labelledby="timeline-detail-title">
    <div class="modal-panel action-modal-panel timeline-detail-panel">
        <div class="modal-header">
            <div>
                <h2 id="timeline-detail-title">Notas da linha do tempo</h2>
                <small id="timeline-detail-subtitle">Detalhes do fornecedor e mês selecionados.</small>
            </div>
            <button type="button" class="modal-close" data-close-timeline-detail>&times;</button>
        </div>
        <div class="timeline-detail-actions">
            <a class="button-link button-compact" id="timeline-detail-export" href="#">Exportar Excel</a>
        </div>
        <div class="table-wrap timeline-detail-wrap">
            <table class="table documents-items-table timeline-detail-table">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Tipo</th>
                        <th>Número</th>
                        <th>Emissor</th>
                        <th>Cidade / UF</th>
                        <th>Tomador</th>
                        <th>Emissão</th>
                        <th>Valor</th>
                        <th>CFOP</th>
                        <th>Decis</th>
                        <th>Entrada ERP</th>
                        <th>Contabilidade</th>
                        <th>Status</th>
                        <th>Origem</th>
                        <th>Observação</th>
                    </tr>
                </thead>
                <tbody id="timeline-detail-body">
                    <tr><td colspan="15">Selecione um mês da linha do tempo.</td></tr>
                </tbody>
            </table>
        </div>
        <div id="timeline-detail-feedback" class="export-feedback" aria-live="polite"></div>
    </div>
</div>

<form method="post" class="card documents-card" data-documents-tab-panel="grid">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <?= hidden_filter_inputs($documentFilterKeys, $filters) ?>
    <input type="hidden" name="p" value="<?= h((string)$currentPage) ?>">
    <div class="grid-toolbar documents-grid-toolbar">
        <div>
            <h2>Grid de entradas</h2>
            <small>Selecione as notas e execute as acoes operacionais sem perder os filtros aplicados.</small>
        </div>
        <div class="documents-action-bar">
            <button class="primary button-compact" type="button" data-open-action-modal="manifest">Manifestar selecionados</button>
            <button class="button-compact" type="button" data-check-cancel-queue data-filtered-total="<?= h((string)$filteredTotal) ?>" title="Consulta a situacao na SEFAZ em fila, um documento por vez, e marca como cancelado quando houver retorno oficial de cancelamento.">Verificar cancelamentos</button>
            <a class="button-link button-compact" href="<?= h(base_url('?page=robot_logs')) ?>">Logs dos Rob&ocirc;s</a>
            <button class="button-compact" type="button" data-open-accounting-import>Importar planilha contabilidade</button>
            <button class="button-compact" type="button" data-open-accounting-missing>Contabilidade sem portal</button>
            <button class="button-compact" type="button" data-open-action-modal="ignore" title="Adiciona as notas selecionadas a lista de ignoradas com justificativa e historico de usuario, data e hora.">Ignorar notas</button>
        </div>
    </div>
    <div class="export-panel is-collapsed compact-export-panel" id="documents-export-panel">
        <div class="export-panel-heading">
            <strong>Exportacao</strong>
            <small>Os arquivos respeitam filtros ou selecao atual.</small>
        </div>
        <div class="export-panel-actions">
            <button class="button-compact" type="button" data-local-zip="selected">Baixar selecionados</button>
            <button class="button-compact" type="button" data-local-zip="filtered">Baixar todos</button>
            <a class="button-link button-compact" href="<?= h(base_url('?' . http_build_query($exportQuery))) ?>">Exportar Excel</a>
        </div>
        <div id="local-export-feedback" class="export-feedback" aria-live="polite"></div>
    </div>
    <div class="modal-backdrop action-modal is-hidden" id="cancel-check-modal" role="dialog" aria-modal="true" aria-labelledby="cancel-check-title">
        <div class="modal-panel action-modal-panel">
            <div class="modal-header">
                <div>
                    <h2 id="cancel-check-title">Verificando cancelamentos</h2>
                    <small>Consulta em fila, um documento por vez, sem perder os filtros aplicados.</small>
                </div>
            </div>
            <div class="queue-progress" id="cancel-check-progress" aria-live="polite">
                <div class="queue-progress-header">
                    <strong>Consulta SEFAZ</strong>
                    <span id="cancel-check-progress-text">0 de 0</span>
                </div>
                <div class="queue-progress-bar"><span id="cancel-check-progress-bar"></span></div>
                <small id="cancel-check-progress-detail">Aguardando seleção.</small>
            </div>
        </div>
    </div>

    <div class="table-wrap documents-table-wrap">
        <?php if ($documentsDeferred): ?>
            <div class="empty-state">Use os filtros acima e clique em Filtrar entradas para carregar o grid.</div>
        <?php endif; ?>
        <table class="table documents-table">
            <thead>
                <tr>
                    <th class="select-col"><input type="checkbox" data-select-all></th>
                    <th class="resizable" data-column="empresa">Empresa</th>
                    <th class="resizable" data-column="tipo">Tipo</th>
                    <th class="resizable" data-column="numero">Número</th>
                    <th class="resizable" data-column="emissor">Emissor</th>
                    <th class="resizable" data-column="cidade_uf">Cidade / UF</th>
                    <th class="resizable" data-column="grupo">Grupo</th>
                    <th class="resizable" data-column="tomador">Tomador</th>
                    <th class="resizable" data-column="chave">Chave</th>
                    <th class="resizable" data-column="nfe_vinculada">NF-e vinculada</th>
                    <th class="resizable" data-column="numero_referenciado">Numero referenciado</th>
                    <th class="resizable" data-column="erp">Nota lançada no ERP</th>
                    <th class="resizable" data-column="integrado">Integrado</th>
                    <th class="resizable" data-column="entrada_erp">Entrada ERP</th>
                    <th class="resizable" data-column="contabilidade">Lançada contabilidade</th>
                    <th class="resizable" data-column="eventos_informativos">Eventos informativos</th>
                    <th class="resizable" data-column="cfop">CFOP</th>
                    <th class="resizable" data-column="emissao">Emissão</th>
                    <th class="resizable" data-column="valor">Valor</th>
                    <th class="resizable" data-column="status">Status</th>
                    <th class="resizable" data-column="manifestacao">Manifestacao</th>
                    <th class="resizable" data-column="origem">Origem</th>
                    <th class="resizable" data-column="link_espelho">Link espelho</th>
                    <th class="resizable actions-col" data-column="acoes">Acoes</th>
                </tr>
                <tr class="grid-filters">
                    <th></th>
                    <th data-column="empresa"><input form="column-filter-form" name="company_q" value="<?= h((string)($filters['company_q'] ?? '')) ?>" placeholder="Filtrar"></th>
                    <th data-column="tipo"></th>
                    <th data-column="numero"><input form="column-filter-form" name="number_q" value="<?= h((string)($filters['number_q'] ?? '')) ?>" placeholder="Filtrar"></th>
                    <th data-column="emissor"><input form="column-filter-form" name="issuer_q" value="<?= h((string)($filters['issuer_q'] ?? '')) ?>" placeholder="Filtrar"></th>
                    <th data-column="cidade_uf"></th>
                    <th data-column="grupo"></th>
                    <th data-column="tomador"><input form="column-filter-form" name="recipient_q" value="<?= h((string)($filters['recipient_q'] ?? '')) ?>" placeholder="Filtrar"></th>
                    <th data-column="chave"><input form="column-filter-form" name="access_key_q" value="<?= h((string)($filters['access_key_q'] ?? '')) ?>" placeholder="Filtrar"></th>
                    <th data-column="nfe_vinculada"><input form="column-filter-form" name="referenced_nfe_q" value="<?= h((string)($filters['referenced_nfe_q'] ?? '')) ?>" placeholder="Filtrar"></th>
                    <th data-column="numero_referenciado"><input form="column-filter-form" name="referenced_number_q" value="<?= h((string)($filters['referenced_number_q'] ?? '')) ?>" placeholder="Filtrar"></th>
                    <th data-column="erp"></th>
                    <th data-column="integrado">
                        <select form="column-filter-form" name="integrated">
                            <option value="">Todas</option>
                            <option value="1" <?= (($filters['integrated'] ?? '') === '1') ? 'selected' : '' ?>>Sim</option>
                            <option value="0" <?= (($filters['integrated'] ?? '') === '0') ? 'selected' : '' ?>>Não</option>
                        </select>
                    </th>
                    <th data-column="entrada_erp"></th>
                    <th data-column="contabilidade">
                        <select form="column-filter-form" name="accounting_posted">
                            <option value="">Todas</option>
                            <option value="S" <?= (($filters['accounting_posted'] ?? '') === 'S') ? 'selected' : '' ?>>Sim</option>
                            <option value="N" <?= (($filters['accounting_posted'] ?? '') === 'N') ? 'selected' : '' ?>>Não</option>
                        </select>
                    </th>
                    <th data-column="eventos_informativos"></th>
                    <th data-column="cfop"></th>
                    <th data-column="emissao"></th>
                    <th data-column="valor"></th>
                    <th data-column="status"></th>
                    <th data-column="manifestacao">
                        <select form="column-filter-form" name="manifestation_status">
                            <option value="">Todas</option>
                            <?php foreach (['pending','not_applicable','manifested_science','manifested_confirm','manifested_unknown','manifested_not_realized','error_science','error_confirm','error_unknown','error_not_realized'] as $status): ?>
                                <option value="<?= h($status) ?>" <?= (($filters['manifestation_status'] ?? '') === $status) ? 'selected' : '' ?>><?= h(manifestation_status_label($status)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </th>
                    <th data-column="origem"><input form="column-filter-form" name="source_q" value="<?= h((string)($filters['source_q'] ?? '')) ?>" placeholder="Filtrar"></th>
                    <th data-column="link_espelho"></th>
                    <th data-column="acoes"><small class="grid-filter-hint">Filtra ao digitar</small></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($documents as $doc): ?>
                <?php
                    $docCity = trim((string)($doc['issuer_city'] ?? '')) !== '' ? (string)$doc['issuer_city'] : (string)($doc['service_city'] ?? '');
                    $docUf = trim((string)($doc['issuer_uf'] ?? '')) !== '' ? (string)$doc['issuer_uf'] : (string)($doc['service_uf'] ?? '');
                    $docCityUf = trim($docCity . ($docUf !== '' ? ' / ' . $docUf : ''));
                ?>
                <tr data-document-row="<?= h((string)$doc['id']) ?>" data-issuer-cnpj="<?= h((string)($doc['issuer_cnpj'] ?? '')) ?>" data-issuer-name="<?= h((string)($doc['issuer_name'] ?? '')) ?>">
                    <td><input type="checkbox" name="ids[]" value="<?= h((string)$doc['id']) ?>" data-doc-checkbox data-doc-type="<?= h(strtoupper((string)($doc['doc_type'] ?? ''))) ?>" data-doc-value="<?= h((string)((float)($doc['total_value'] ?? 0))) ?>"></td>
                    <td data-column="empresa"><strong><?= h((string)$doc['company_name']) ?></strong><br><small><?= h((string)$doc['company_cnpj']) ?></small></td>
                    <td data-column="tipo"><span class="pill"><?= h((string)$doc['doc_type']) ?></span></td>
                    <td data-column="numero"><button type="button" class="link-button doc-products-link" data-document-items="<?= h((string)$doc['id']) ?>"><?= h((string)$doc['number']) ?></button></td>
                    <td data-column="emissor"><strong><?= h((string)$doc['issuer_name']) ?></strong><br><small><?= h((string)$doc['issuer_cnpj']) ?></small></td>
                    <td data-column="cidade_uf"><?= h($docCityUf) ?></td>
                    <td data-column="grupo" data-supplier-group-cell><?= h((string)($doc['supplier_group'] ?? '')) ?></td>
                    <td data-column="tomador"><strong><?= h((string)($doc['recipient_name'] ?? '')) ?></strong><br><small><?= h((string)($doc['recipient_cnpj'] ?? '')) ?></small></td>
                    <td data-column="chave"><small><?= h((string)$doc['access_key']) ?></small></td>
                    <td data-column="nfe_vinculada"><small><?= h((string)($doc['referenced_nfe_keys'] ?? '')) ?></small></td>
                    <td data-column="numero_referenciado"><small><?= h((string)($doc['referenced_document_numbers'] ?? '')) ?></small></td>
                    <td data-column="erp"><?= !empty($doc['posted_to_erp']) ? 'Sim' : 'Não' ?></td>
                    <td data-column="integrado"><?= !empty($doc['integrated']) ? 'Sim' : 'Não' ?></td>
                    <td data-column="entrada_erp"><?= h(format_date_short($doc['entrada_date_erp'] ?? null)) ?></td>
                    <td data-column="contabilidade" class="accounting-status-cell">
                        <?php if (($doc['accounting_posted'] ?? 'N') === 'S'): ?>
                            <button type="button" class="link-button" data-accounting-details="<?= h((string)$doc['id']) ?>">Sim</button>
                        <?php else: ?>
                            <span>Não</span>
                        <?php endif; ?>
                    </td>
                    <td data-column="eventos_informativos">
                        <?php if ((int)($doc['informative_events_count'] ?? 0) > 0): ?>
                            <strong><?= h((string)$doc['informative_events_count']) ?></strong><br>
                            <small><?= h((string)($doc['informative_events_names'] ?? '')) ?></small>
                        <?php else: ?>
                            <small>-</small>
                        <?php endif; ?>
                    </td>
                    <td data-column="cfop"><?= h((string)($doc['primary_cfop'] ?? '')) ?></td>
                    <td data-column="emissao"><?= h(format_date($doc['issue_date'])) ?></td>
                    <td data-column="valor"><?= h(format_money((float)$doc['total_value'])) ?></td>
                    <td data-column="status"><?= h(document_status_label((string)$doc['status'])) ?></td>
                    <td data-column="manifestacao"><?= h(manifestation_status_label((string)$doc['manifestation_status'])) ?></td>
                    <td data-column="origem"><?= h((string)$doc['source']) ?></td>
                    <td data-column="link_espelho">
                        <?php if ($canShowDocumentMirror($doc)): ?>
                            <a class="row-action" target="_blank" href="<?= h(base_url('?page=documents_danfe&id=' . $doc['id'])) ?>">Abrir</a>
                        <?php else: ?>
                            <small>-</small>
                        <?php endif; ?>
                    </td>
                    <td data-column="acoes" class="row-actions">
                        <a class="row-action" target="_blank" href="<?= h(base_url('?page=view_xml&id=' . $doc['id'])) ?>">XML</a>
                        <button type="button" class="row-action row-action-button" data-document-items="<?= h((string)$doc['id']) ?>">Produtos</button>
                        <?php if ($canShowDocumentMirror($doc)): ?><button type="button" class="row-action row-action-button" data-document-danfe="<?= h((string)$doc['id']) ?>">Espelho</button><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$documents): ?>
                <tr><td colspan="23">Nenhuma entrada encontrada.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php
    $prevQuery = $baseQuery;
    $prevQuery['p'] = max(1, $currentPage - 1);
    $nextQuery = $baseQuery;
    $nextQuery['p'] = min($totalPages, $currentPage + 1);
    ?>
    <div class="pagination-bar">
        <span>Página <?= h((string)$currentPage) ?> de <?= h((string)$totalPages) ?> | 200 registros por pagina</span>
        <div class="inline">
            <a class="button-link <?= $currentPage <= 1 ? 'disabled' : '' ?>" href="<?= h(base_url('?' . http_build_query($prevQuery))) ?>">Anterior</a>
            <a class="button-link <?= $currentPage >= $totalPages ? 'disabled' : '' ?>" href="<?= h(base_url('?' . http_build_query($nextQuery))) ?>">Próxima</a>
        </div>
    </div>
    <div class="modal-backdrop action-modal is-hidden" id="documents-manifest-modal" role="dialog" aria-modal="true" aria-labelledby="documents-manifest-title">
        <div class="modal-panel action-modal-panel">
            <div class="modal-header">
                <div>
                    <h2 id="documents-manifest-title">Manifestar selecionados</h2>
                    <small>Informe o tipo de manifesto para as notas selecionadas.</small>
                </div>
                <button type="button" class="modal-close" data-close-action-modal>&times;</button>
            </div>
            <div class="form-row two">
                <label>Tipo do manifesto
                    <select name="manifest_type">
                        <option value="science">Ciencia da Operacao</option>
                        <option value="confirm">Confirmacao da Operacao</option>
                        <option value="unknown">Desconhecimento da Operacao</option>
                        <option value="not_realized">Operacao nao Realizada</option>
                    </select>
                </label>
                <label>Justificativa
                    <input type="text" name="manifest_justification" placeholder="Obrigatoria em alguns manifestos">
                </label>
            </div>
            <div class="modal-actions">
                <button type="button" class="button-link button-compact" data-close-action-modal>Cancelar</button>
                <button class="primary button-compact" name="bulk_manifest" value="1">Confirmar manifesto</button>
            </div>
        </div>
    </div>
    <div class="modal-backdrop action-modal is-hidden" id="documents-ignore-modal" role="dialog" aria-modal="true" aria-labelledby="documents-ignore-title">
        <div class="modal-panel action-modal-panel">
            <div class="modal-header">
                <div>
                    <h2 id="documents-ignore-title">Ignorar notas selecionadas</h2>
                    <small>A justificativa fica gravada no historico com usuario, data e hora.</small>
                </div>
                <button type="button" class="modal-close" data-close-action-modal>&times;</button>
            </div>
            <label>Justificativa
                <textarea name="ignored_document_reason" rows="4" placeholder="Explique o motivo para ignorar estas notas"></textarea>
            </label>
            <div class="modal-actions">
                <button type="button" class="button-link button-compact" data-close-action-modal>Cancelar</button>
                <button class="primary button-compact" name="save_ignored_documents" value="1">Ignorar notas</button>
            </div>
        </div>
    </div>
</form>

<div class="documents-context-menu is-hidden" id="documents-supplier-group-menu" role="menu" aria-live="polite">
    <strong>Vincular fornecedor ao grupo</strong>
    <small id="documents-supplier-group-menu-title"></small>
    <div id="documents-supplier-group-menu-options"></div>
</div>

<div class="modal-backdrop loading-modal is-hidden" id="documents-loading-modal" role="dialog" aria-modal="true" aria-labelledby="documents-loading-title">
    <div class="modal-panel loading-modal-panel">
        <h2 id="documents-loading-title">Buscando entradas</h2>
        <p>Aplicando filtros e preparando o grid.</p>
        <div class="indeterminate-bar"><span></span></div>
    </div>
</div>

<div class="modal-backdrop ignored-cfops-modal is-hidden" id="ignored-cfops-modal" role="dialog" aria-modal="true" aria-labelledby="ignored-cfops-title">
    <div class="modal-panel">
        <div class="modal-header">
            <div>
                <h2 id="ignored-cfops-title">Ignorados em Entradas</h2>
                <small>Nao mostrar documentos com os CFOPs ou notas informados, pois nao realizamos a escrituracao Fiscal desses documentos.</small>
            </div>
            <button type="button" class="modal-close" data-close-ignored-cfops>&times;</button>
        </div>
        <form method="post" class="ignored-cfop-form">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
            <?= hidden_filter_inputs($documentFilterKeys, $filters) ?>
            <div class="form-row">
                <label>CFOP existente
                    <select name="ignored_cfop" required>
                        <option value="">Selecione</option>
                        <?php foreach (($documentCfopOptions ?? []) as $cfop): ?>
                            <option value="<?= h((string)$cfop) ?>"><?= h((string)$cfop) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Motivo
                    <input type="text" name="ignored_reason" placeholder="Motivo opcional">
                </label>
                <label class="form-action-label">
                    <span>&nbsp;</span>
                    <button class="primary button-compact" name="save_ignored_cfop" value="1">Adicionar</button>
                </label>
            </div>
            <?php if (empty($documentCfopOptions)): ?><p class="empty-state">Nenhum CFOP novo encontrado nos itens indexados.</p><?php endif; ?>
        </form>
        <div class="table-wrap">
            <table class="table documents-items-table">
                <thead><tr><th>CFOP</th><th>Motivo</th><th>Usuario</th><th>Adicionado em</th><th>Acao</th></tr></thead>
                <tbody>
                <?php foreach (($documentIgnoredCfops ?? []) as $ignored): ?>
                    <tr>
                        <td><strong><?= h((string)$ignored['cfop']) ?></strong></td>
                        <td><?= h((string)($ignored['reason'] ?? '')) ?></td>
                        <td><?= h((string)($ignored['user_name'] ?? '')) ?></td>
                        <td><?= h(format_date($ignored['created_at'] ?? null)) ?></td>
                        <td>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                <?= hidden_filter_inputs($documentFilterKeys, $filters) ?>
                                <input type="hidden" name="ignored_cfop_id" value="<?= h((string)$ignored['id']) ?>">
                                <button class="row-action row-action-button" name="delete_ignored_cfop" value="1">Remover</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($documentIgnoredCfops)): ?><tr><td colspan="5">Nenhum CFOP ignorado cadastrado.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <h3 class="modal-section-title">Notas ignoradas</h3>
        <div class="table-wrap">
            <table class="table documents-items-table">
                <thead><tr><th>Nota</th><th>Emissor</th><th>Empresa</th><th>Justificativa</th><th>Usuario</th><th>Ignorada em</th><th>Acao</th></tr></thead>
                <tbody>
                <?php foreach (($documentIgnoredDocuments ?? []) as $ignoredDoc): ?>
                    <tr>
                        <td>
                            <strong><?= h((string)($ignoredDoc['document_number'] ?? '')) ?></strong><br>
                            <small><?= h((string)($ignoredDoc['doc_type'] ?? '')) ?> <?= h((string)($ignoredDoc['access_key'] ?? '')) ?></small>
                        </td>
                        <td><?= h((string)($ignoredDoc['issuer_name'] ?? '')) ?><br><small><?= h((string)($ignoredDoc['issuer_cnpj'] ?? '')) ?></small></td>
                        <td><?= h((string)($ignoredDoc['company_name'] ?? '')) ?><br><small><?= h((string)($ignoredDoc['company_cnpj'] ?? '')) ?></small></td>
                        <td><?= h((string)($ignoredDoc['reason'] ?? '')) ?></td>
                        <td><?= h((string)($ignoredDoc['user_name'] ?? '')) ?></td>
                        <td><?= h(format_date($ignoredDoc['created_at'] ?? null)) ?></td>
                        <td>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                <?= hidden_filter_inputs($documentFilterKeys, $filters) ?>
                                <input type="hidden" name="ignored_document_id" value="<?= h((string)$ignoredDoc['id']) ?>">
                                <button class="row-action row-action-button" name="delete_ignored_document" value="1">Remover</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($documentIgnoredDocuments)): ?><tr><td colspan="7">Nenhuma nota ignorada cadastrada.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-backdrop documents-items-modal is-hidden" id="document-items-modal" role="dialog" aria-modal="true" aria-labelledby="document-items-title">
    <div class="modal-panel">
        <div class="modal-header">
            <div>
                <h2 id="document-items-title">Produtos da entrada</h2>
                <small id="document-items-subtitle"></small>
            </div>
            <button type="button" class="modal-close" data-close-document-items>&times;</button>
        </div>
        <div class="table-wrap">
            <table class="table documents-items-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Codigo</th>
                        <th>Produto</th>
                        <th>NCM</th>
                        <th>CFOP</th>
                        <th>Cidade</th>
                        <th>UF</th>
                        <th>Qtd</th>
                        <th>Un</th>
                        <th>Unitario</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody id="document-items-body">
                    <tr><td colspan="11">Carregando produtos...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-backdrop documents-danfe-modal is-hidden" id="document-danfe-modal" role="dialog" aria-modal="true" aria-labelledby="document-danfe-title">
    <div class="modal-panel danfe-modal-panel">
        <div class="modal-header">
            <div>
                <h2 id="document-danfe-title">Espelho do documento</h2>
                <small>Confira o espelho antes de imprimir.</small>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="button-compact" data-copy-document-danfe-link>Copiar link</button>
                <button type="button" class="primary button-compact" data-print-document-danfe>Imprimir</button>
                <button type="button" class="modal-close" data-close-document-danfe>&times;</button>
            </div>
        </div>
        <iframe id="document-danfe-frame" class="document-danfe-frame" title="Espelho do documento"></iframe>
    </div>
</div>

<div class="modal-backdrop action-modal is-hidden" id="accounting-import-modal" role="dialog" aria-modal="true" aria-labelledby="accounting-import-title">
    <div class="modal-panel action-modal-panel">
        <div class="modal-header">
            <div>
                <h2 id="accounting-import-title">Importar planilha contabilidade</h2>
                <small>Importa todas as abas da planilha e marca as entradas localizadas.</small>
            </div>
            <button type="button" class="modal-close" data-close-accounting-modal>&times;</button>
        </div>
        <div class="form-row two">
            <label>Tipo da planilha
                <select id="accounting-doc-type">
                    <option value="NFSE">NFS-e</option>
                    <option value="NFE">NF-e</option>
                    <option value="CTE">CT-e</option>
                </select>
            </label>
            <label>Planilha
                <input type="file" id="accounting-file" accept=".xls,.xlsx,.xlsm,.csv">
            </label>
        </div>
        <div id="accounting-preview" class="empty-state">Selecione a planilha para configurar o de/para.</div>
        <div class="queue-progress is-hidden" id="accounting-import-progress" aria-live="polite">
            <div class="queue-progress-header">
                <strong id="accounting-import-progress-title">Lendo planilha</strong>
                <span id="accounting-import-progress-text">0%</span>
            </div>
            <div class="queue-progress-bar"><span id="accounting-import-progress-bar"></span></div>
            <small id="accounting-import-progress-detail">Aguardando arquivo.</small>
        </div>
        <div class="form-row three" id="accounting-mapping" hidden>
            <label data-map-field="access_key">Coluna chave de acesso
                <select id="accounting-map-access-key"></select>
            </label>
            <label data-map-field="number">Coluna número da nota
                <select id="accounting-map-number"></select>
            </label>
            <label data-map-field="party_document">Coluna CPF/CNPJ do prestador
                <select id="accounting-map-party"></select>
            </label>
            <label data-map-field="issue_date">Coluna data emissão
                <select id="accounting-map-issue-date"></select>
            </label>
        </div>
        <div class="modal-actions">
            <button type="button" class="button-link button-compact" data-close-accounting-modal>Cancelar</button>
            <button type="button" class="primary button-compact" id="accounting-import-confirm" disabled>Importar e vincular</button>
            <button type="button" class="primary button-compact is-hidden" id="accounting-import-done">Concluir</button>
        </div>
        <div id="accounting-import-feedback" class="export-feedback" aria-live="polite"></div>
    </div>
</div>

<div class="modal-backdrop documents-items-modal is-hidden" id="accounting-details-modal" role="dialog" aria-modal="true" aria-labelledby="accounting-details-title">
    <div class="modal-panel">
        <div class="modal-header">
            <div>
                <h2 id="accounting-details-title">Lançamento na contabilidade</h2>
                <small id="accounting-details-subtitle"></small>
            </div>
            <button type="button" class="modal-close" data-close-accounting-details>&times;</button>
        </div>
        <div class="table-wrap">
            <table class="table documents-items-table">
                <thead id="accounting-details-head"><tr><th>Aba</th><th>Linha</th><th>Arquivo</th></tr></thead>
                <tbody id="accounting-details-body"><tr><td colspan="3">Carregando...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-backdrop documents-items-modal is-hidden" id="accounting-missing-modal" role="dialog" aria-modal="true" aria-labelledby="accounting-missing-title">
    <div class="modal-panel">
        <div class="modal-header">
            <div>
                <h2 id="accounting-missing-title">Contabilidade sem portal</h2>
                <small>Registros importados da planilha que ainda nao encontraram entrada no portal.</small>
            </div>
            <button type="button" class="modal-close" data-close-accounting-missing>&times;</button>
        </div>
        <div class="form-row two">
            <label>Tipo
                <select id="accounting-missing-type">
                    <option value="">Todos</option>
                    <option value="NFE">NF-e</option>
                    <option value="CTE">CT-e</option>
                    <option value="NFSE">NFS-e</option>
                </select>
            </label>
            <label>Número da nota
                <input type="text" id="accounting-missing-number" placeholder="Número ou parte">
            </label>
            <label>Fornecedor
                <input type="text" id="accounting-missing-supplier" placeholder="Nome, CPF ou CNPJ">
            </label>
            <label>Planilha
                <input type="text" id="accounting-missing-file" list="accounting-missing-file-options" placeholder="Nome da planilha">
                <datalist id="accounting-missing-file-options"></datalist>
            </label>
            <label>Aba
                <input type="text" id="accounting-missing-sheet" list="accounting-missing-sheet-options" placeholder="Nome da aba">
                <datalist id="accounting-missing-sheet-options"></datalist>
            </label>
            <label>Data inicial
                <input type="date" id="accounting-missing-date-start">
            </label>
            <label>Data final
                <input type="date" id="accounting-missing-date-end">
            </label>
            <label class="form-action-label">
                <span>&nbsp;</span>
                <button type="button" class="button-compact" id="accounting-missing-refresh">Filtrar</button>
            </label>
            <label class="form-action-label">
                <span>&nbsp;</span>
                <button type="button" class="button-compact" id="accounting-missing-export">Exportar Excel</button>
            </label>
            <label class="form-action-label">
                <span>&nbsp;</span>
                <button type="button" class="primary button-compact" id="accounting-missing-launch">Lançar no portal</button>
            </label>
        </div>
        <div class="table-wrap">
            <table class="table documents-items-table">
                <thead id="accounting-missing-head"><tr><th>Tipo</th><th>Planilha</th><th>Aba</th><th>Linha</th><th>Chave/Número</th><th>Documento</th></tr></thead>
                <tbody id="accounting-missing-body"><tr><td colspan="6">Carregando...</td></tr></tbody>
            </table>
        </div>
        <div class="pagination-bar" id="accounting-missing-pagination">
            <span id="accounting-missing-page-info">Página 1</span>
            <div class="pagination-actions">
                <button type="button" class="button-compact" id="accounting-missing-select-all-filter">Marcar todos do filtro</button>
                <button type="button" class="button-compact" id="accounting-missing-clear-selection">Limpar seleção</button>
                <button type="button" class="button-compact" id="accounting-missing-prev">Anterior</button>
                <button type="button" class="button-compact" id="accounting-missing-next">Próxima</button>
            </div>
        </div>
        <div id="accounting-missing-selection-info" class="export-feedback"></div>
    </div>
</div>

<div class="modal-backdrop action-modal is-hidden" id="accounting-launch-modal" role="dialog" aria-modal="true" aria-labelledby="accounting-launch-title">
    <div class="modal-panel action-modal-panel">
        <div class="modal-header">
            <div>
                <h2 id="accounting-launch-title">Lançar no portal pela contabilidade</h2>
                <small id="accounting-launch-subtitle">Confira o de/para antes de gravar as entradas.</small>
            </div>
            <button type="button" class="modal-close" data-close-accounting-launch>&times;</button>
        </div>
        <div class="form-row two" id="accounting-launch-mapping">
            <label data-launch-field="access_key">Coluna chave de acesso
                <select id="accounting-launch-map-access-key"></select>
            </label>
            <label>Coluna número da nota
                <select id="accounting-launch-map-number"></select>
            </label>
            <label>Coluna CPF/CNPJ fornecedor
                <select id="accounting-launch-map-issuer-document"></select>
            </label>
            <label>Coluna nome fornecedor
                <select id="accounting-launch-map-issuer-name"></select>
            </label>
            <label>Coluna data emissão
                <select id="accounting-launch-map-issue-date"></select>
            </label>
            <label>Coluna data entrada
                <select id="accounting-launch-map-entry-date"></select>
            </label>
            <label>Coluna série
                <select id="accounting-launch-map-series"></select>
            </label>
            <label>Coluna espécie
                <select id="accounting-launch-map-species"></select>
            </label>
            <label>Coluna código
                <select id="accounting-launch-map-code"></select>
            </label>
            <label>Coluna valor
                <select id="accounting-launch-map-total-value"></select>
            </label>
            <label>Coluna inscrição estadual
                <select id="accounting-launch-map-state-registration"></select>
            </label>
            <label>Coluna CFOP
                <select id="accounting-launch-map-cfop"></select>
            </label>
            <label>Coluna tipo
                <select id="accounting-launch-map-operation-type"></select>
            </label>
            <label>Coluna UF
                <select id="accounting-launch-map-issuer-uf"></select>
            </label>
            <label>Coluna descrição
                <select id="accounting-launch-map-description"></select>
            </label>
        </div>
        <div>
            <h3 class="section-subtitle">De/para por planilha e aba</h3>
            <div id="accounting-launch-sheets" class="accounting-launch-sheets"></div>
        </div>
        <div class="modal-actions">
            <button type="button" class="button-link button-compact" data-close-accounting-launch>Cancelar</button>
            <button type="button" class="primary button-compact" id="accounting-launch-confirm">Lançar selecionadas</button>
        </div>
        <div class="queue-progress is-hidden" id="accounting-launch-progress" aria-live="polite">
            <div class="queue-progress-header">
                <strong id="accounting-launch-progress-title">Lançando notas</strong>
                <span id="accounting-launch-progress-text">0%</span>
            </div>
            <div class="queue-progress-bar"><span id="accounting-launch-progress-bar"></span></div>
            <small id="accounting-launch-progress-detail">Aguardando confirmação.</small>
        </div>
        <div id="accounting-launch-feedback" class="export-feedback" aria-live="polite"></div>
    </div>
</div>

<form id="column-filter-form" method="get">
    <input type="hidden" name="page" value="documents">
    <!-- Filtros digitados diretamente no cabecalho do grid tambem precisam acionar a consulta.
         Sem este marcador o controller mantinha a tela em modo deferido e parecia que o filtro nao funcionava. -->
    <input type="hidden" name="load_documents" value="1">
    <input type="hidden" name="q" value="<?= h((string)($filters['q'] ?? '')) ?>">
    <?php $companyFilterValues = is_array($filters['company_id'] ?? '') ? ($filters['company_id'] ?? []) : array_filter([(string)($filters['company_id'] ?? '')]); ?>
    <?php foreach ($companyFilterValues as $companyFilterValue): ?>
        <input type="hidden" name="company_id[]" value="<?= h((string)$companyFilterValue) ?>">
    <?php endforeach; ?>
    <?php $supplierGroupFilterValues = is_array($filters['supplier_group_id'] ?? '') ? ($filters['supplier_group_id'] ?? []) : array_filter([(string)($filters['supplier_group_id'] ?? '')]); ?>
    <?php foreach ($supplierGroupFilterValues as $supplierGroupFilterValue): ?>
        <input type="hidden" name="supplier_group_id[]" value="<?= h((string)$supplierGroupFilterValue) ?>">
    <?php endforeach; ?>
    <input type="hidden" name="doc_type" value="<?= h((string)($filters['doc_type'] ?? '')) ?>">
    <input type="hidden" name="status" value="<?= h((string)($filters['status'] ?? '')) ?>">
    <input type="hidden" name="posted_to_erp" value="<?= h((string)($filters['posted_to_erp'] ?? '')) ?>">
    <input type="hidden" name="integrated" value="<?= h((string)($filters['integrated'] ?? '')) ?>">
    <input type="hidden" name="without_referenced_nfe" value="<?= h((string)($filters['without_referenced_nfe'] ?? '')) ?>">
    <input type="hidden" name="cte_taker_only" value="<?= h((string)($filters['cte_taker_only'] ?? '')) ?>">
    <input type="hidden" name="ignore_cfops" value="<?= h((string)($filters['ignore_cfops'] ?? '1')) ?>">
    <input type="hidden" name="date_start" value="<?= h((string)($filters['date_start'] ?? '')) ?>">
    <input type="hidden" name="date_end" value="<?= h((string)($filters['date_end'] ?? '')) ?>">
    <input type="hidden" name="product_q" value="<?= h((string)($filters['product_q'] ?? '')) ?>">
    <input type="hidden" name="cfop_q" value="<?= h((string)($filters['cfop_q'] ?? '')) ?>">
    <!-- Campos filtraveis no cabecalho do grid ficam apenas nos inputs visiveis.
         Duplicar como hidden pode sobrescrever o valor digitado em alguns navegadores. -->
    <input type="hidden" name="sort_by" value="<?= h((string)($filters['sort_by'] ?? 'issue_date')) ?>">
    <input type="hidden" name="sort_dir" value="<?= h((string)($filters['sort_dir'] ?? 'desc')) ?>">
</form>

<script src="<?= h(base_url('assets/vendor/xlsx.full.min.js?v=20260902-accounting-import')) ?>"></script>
<script>
(function () {
    var tabKey = 'controls.documents.activeTab';
    var tabs = document.querySelectorAll('[data-documents-tab]');
    var panels = document.querySelectorAll('[data-documents-tab-panel]');
    if (!tabs.length || !panels.length) return;
    function setTab(name) {
        tabs.forEach(function (tab) {
            tab.classList.toggle('is-active', tab.getAttribute('data-documents-tab') === name);
        });
        panels.forEach(function (panel) {
            panel.classList.toggle('is-hidden', panel.getAttribute('data-documents-tab-panel') !== name);
        });
        try { localStorage.setItem(tabKey, name); } catch (e) {}
    }
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            setTab(tab.getAttribute('data-documents-tab') || 'grid');
        });
    });
    var params = new URLSearchParams(window.location.search);
    var initial = params.has('timeline_mode') ? 'timeline' : 'grid';
    try { initial = localStorage.getItem(tabKey) || initial; } catch (e) {}
    if (initial !== 'timeline') initial = 'grid';
    setTab(initial);
})();
</script>
<script>
(function () {
    var card = document.querySelector('.documents-timeline-card');
    var fullscreenButton = document.querySelector('[data-timeline-fullscreen]');
    var modal = document.getElementById('timeline-detail-modal');
    var title = document.getElementById('timeline-detail-title');
    var subtitle = document.getElementById('timeline-detail-subtitle');
    var tbody = document.getElementById('timeline-detail-body');
    var exportLink = document.getElementById('timeline-detail-export');
    var feedback = document.getElementById('timeline-detail-feedback');
    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char] || char;
        });
    }
    if (card && fullscreenButton) {
        fullscreenButton.addEventListener('click', function () {
            var active = !card.classList.contains('is-fullscreen');
            card.classList.toggle('is-fullscreen', active);
            fullscreenButton.textContent = active ? '↩ Sair' : '🖥️ Tela cheia';
        });
    }
    if (!modal || !tbody) return;
    function closeModal() {
        modal.classList.add('is-hidden');
    }
    document.querySelectorAll('[data-close-timeline-detail]').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });
    modal.addEventListener('click', function (event) {
        if (event.target === modal) closeModal();
    });
    document.querySelectorAll('[data-timeline-cell]').forEach(function (button) {
        button.addEventListener('click', function () {
            var url = button.getAttribute('data-timeline-cell');
            var exportUrl = button.getAttribute('data-timeline-export') || '#';
            var cellTitle = button.getAttribute('data-timeline-title') || 'Notas da linha do tempo';
            if (!url) return;
            if (title) title.textContent = cellTitle;
            if (subtitle) subtitle.textContent = 'Notas encontradas para o fornecedor e mês selecionados.';
            if (exportLink) exportLink.href = exportUrl;
            if (feedback) feedback.textContent = 'Carregando notas...';
            tbody.innerHTML = '<tr><td colspan="15">Carregando...</td></tr>';
            modal.classList.remove('is-hidden');
            fetch(url, {headers: {'Accept': 'application/json'}})
                .then(function (response) {
                    if (!response.ok) throw new Error('Falha ao carregar as notas.');
                    return response.json();
                })
                .then(function (payload) {
                    var docs = payload && payload.documents ? payload.documents : [];
                    if (feedback) feedback.textContent = docs.length + ' nota(s) encontrada(s).';
                    if (!docs.length) {
                        tbody.innerHTML = '<tr><td colspan="15">Nenhuma nota encontrada para este recorte.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = docs.map(function (doc) {
                        return '<tr>' +
                            '<td>' + escapeHtml(doc.company_name) + '</td>' +
                            '<td>' + escapeHtml(doc.doc_type) + '</td>' +
                            '<td>' + escapeHtml(doc.number) + '</td>' +
                            '<td><strong>' + escapeHtml(doc.issuer_name) + '</strong><small>' + escapeHtml(doc.issuer_cnpj) + '</small></td>' +
                            '<td>' + escapeHtml(doc.city_uf) + '</td>' +
                            '<td>' + escapeHtml(doc.recipient_name) + '</td>' +
                            '<td>' + escapeHtml(doc.issue_date) + '</td>' +
                            '<td>' + escapeHtml(doc.total_value) + '</td>' +
                            '<td>' + escapeHtml(doc.cfop) + '</td>' +
                            '<td>' + escapeHtml(doc.posted_to_erp) + '</td>' +
                            '<td>' + escapeHtml(doc.entrada_date_erp) + '</td>' +
                            '<td>' + escapeHtml(doc.accounting_posted) + '</td>' +
                            '<td>' + escapeHtml(doc.status) + '</td>' +
                            '<td>' + escapeHtml(doc.source) + '</td>' +
                            '<td>' + escapeHtml(doc.observation) + '</td>' +
                        '</tr>';
                    }).join('');
                })
                .catch(function (error) {
                    if (feedback) feedback.textContent = error.message || 'Falha ao carregar as notas.';
                    tbody.innerHTML = '<tr><td colspan="15">Nao foi possivel carregar este detalhe.</td></tr>';
                });
        });
    });
})();
</script>
<script>
(function () {
    var menu = document.getElementById('documents-supplier-group-menu');
    var title = document.getElementById('documents-supplier-group-menu-title');
    var optionsBox = document.getElementById('documents-supplier-group-menu-options');
    var csrf = document.querySelector('form.documents-card input[name="_csrf"]');
    var groups = <?= json_encode($supplierGroupOptions, JSON_UNESCAPED_UNICODE) ?>;
    var activeRow = null;
    if (!menu || !optionsBox || !csrf) return;
    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char] || char;
        });
    }
    function hideMenu() {
        menu.classList.add('is-hidden');
        activeRow = null;
    }
    function positionMenu(x, y) {
        menu.classList.remove('is-hidden');
        var rect = menu.getBoundingClientRect();
        var left = Math.min(x, window.innerWidth - rect.width - 12);
        var top = Math.min(y, window.innerHeight - rect.height - 12);
        menu.style.left = Math.max(12, left) + 'px';
        menu.style.top = Math.max(12, top) + 'px';
    }
    function renderOptions() {
        if (!groups.length) {
            optionsBox.innerHTML = '<a class="button-link button-compact" href="?page=supplier_groups">Cadastrar grupo</a>';
            return;
        }
        optionsBox.innerHTML = groups.map(function (group) {
            return '<button type="button" data-quick-supplier-group="' + escapeHtml(group.value) + '">' + escapeHtml(group.label) + '</button>';
        }).join('');
    }
    document.querySelectorAll('tr[data-document-row]').forEach(function (row) {
        row.addEventListener('contextmenu', function (event) {
            var issuerCnpj = row.getAttribute('data-issuer-cnpj') || '';
            if (!issuerCnpj.trim()) return;
            event.preventDefault();
            activeRow = row;
            if (title) {
                title.textContent = (row.getAttribute('data-issuer-name') || 'Fornecedor') + ' | ' + issuerCnpj;
            }
            renderOptions();
            positionMenu(event.clientX, event.clientY);
        });
    });
    optionsBox.addEventListener('click', async function (event) {
        var button = event.target.closest('[data-quick-supplier-group]');
        if (!button || !activeRow) return;
        var previous = button.textContent;
        button.disabled = true;
        button.textContent = 'Salvando...';
        try {
            var response = await fetch('?page=documents_supplier_group_assign', {
                method: 'POST',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json;charset=UTF-8'},
                body: JSON.stringify({
                    _csrf: csrf.value,
                    document_id: activeRow.getAttribute('data-document-row') || '',
                    group_id: button.getAttribute('data-quick-supplier-group') || ''
                })
            });
            var data = await response.json();
            if (!response.ok || !data.ok) throw new Error((data && data.message) || 'Falha ao vincular grupo.');
            var groupCell = activeRow.querySelector('[data-supplier-group-cell]');
            if (groupCell) groupCell.textContent = data.group_description || '';
            hideMenu();
        } catch (error) {
            button.disabled = false;
            button.textContent = previous;
            if (title) title.textContent = error.message || 'Falha ao vincular grupo.';
        }
    });
    document.addEventListener('click', function (event) {
        if (!menu.contains(event.target)) hideMenu();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') hideMenu();
    });
    window.addEventListener('scroll', hideMenu, true);
    window.addEventListener('resize', hideMenu);
})();
</script>
<script>
(function () {
    var modal = document.getElementById('accounting-import-modal');
    var openButton = document.querySelector('[data-open-accounting-import]');
    var fileInput = document.getElementById('accounting-file');
    var docTypeInput = document.getElementById('accounting-doc-type');
    var preview = document.getElementById('accounting-preview');
    var mappingBox = document.getElementById('accounting-mapping');
    var accessSelect = document.getElementById('accounting-map-access-key');
    var numberSelect = document.getElementById('accounting-map-number');
    var partySelect = document.getElementById('accounting-map-party');
    var issueDateSelect = document.getElementById('accounting-map-issue-date');
    var confirmButton = document.getElementById('accounting-import-confirm');
    var doneButton = document.getElementById('accounting-import-done');
    var feedback = document.getElementById('accounting-import-feedback');
    var progress = document.getElementById('accounting-import-progress');
    var progressTitle = document.getElementById('accounting-import-progress-title');
    var progressText = document.getElementById('accounting-import-progress-text');
    var progressBar = document.getElementById('accounting-import-progress-bar');
    var progressDetail = document.getElementById('accounting-import-progress-detail');
    var csrf = document.querySelector('form.documents-card input[name="_csrf"]');
    var parsedWorkbook = null;
    var importFinished = false;
    if (!modal || !openButton || !fileInput || !docTypeInput || !preview || !mappingBox || !confirmButton || !csrf) return;

    function closeModal() {
        if (importFinished) {
            window.location.reload();
            return;
        }
        modal.classList.add('is-hidden');
    }
    function openModal() {
        importFinished = false;
        if (doneButton) doneButton.classList.add('is-hidden');
        confirmButton.classList.remove('is-hidden');
        modal.classList.remove('is-hidden');
    }
    function setAccountingProgress(title, done, total, detail) {
        if (!progress || !progressBar || !progressText || !progressDetail || !progressTitle) return;
        progress.classList.remove('is-hidden');
        var pct = total > 0 ? Math.max(0, Math.min(100, Math.round((done / total) * 100))) : 0;
        progressTitle.textContent = title || 'Processando planilha';
        progressText.textContent = pct + '%';
        progressBar.style.width = pct + '%';
        progressDetail.textContent = detail || '';
    }
    function sleep(ms) {
        return new Promise(function (resolve) { window.setTimeout(resolve, ms); });
    }
    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char] || char;
        });
    }
    function normalizeHeader(value, fallback) {
        var text = String(value == null ? '' : value).trim();
        return text || fallback;
    }
    function norm(value) {
        return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase();
    }
    function fillSelect(select, headers, preferred) {
        if (!select) return;
        select.innerHTML = headers.map(function (header) {
            return '<option value="' + escapeHtml(header) + '">' + escapeHtml(header) + '</option>';
        }).join('');
        var preferredNorms = preferred.map(norm);
        var found = headers.find(function (header) {
            var h = norm(header);
            return preferredNorms.some(function (token) { return h.indexOf(token) >= 0; });
        });
        if (found) select.value = found;
    }
    function refreshMappingVisibility() {
        var type = docTypeInput.value;
        mappingBox.hidden = !parsedWorkbook;
        var accessField = mappingBox.querySelector('[data-map-field="access_key"]');
        var numberField = mappingBox.querySelector('[data-map-field="number"]');
        var partyField = mappingBox.querySelector('[data-map-field="party_document"]');
        var issueDateField = mappingBox.querySelector('[data-map-field="issue_date"]');
        if (accessField) {
            accessField.hidden = type === 'NFSE';
            accessField.style.display = type === 'NFSE' ? 'none' : '';
        }
        if (numberField) {
            numberField.hidden = type !== 'NFSE';
            numberField.style.display = type !== 'NFSE' ? 'none' : '';
        }
        if (partyField) {
            partyField.hidden = type !== 'NFSE';
            partyField.style.display = type !== 'NFSE' ? 'none' : '';
        }
        if (issueDateField) {
            issueDateField.hidden = type !== 'NFSE';
            issueDateField.style.display = type !== 'NFSE' ? 'none' : '';
        }
    }
    function renderPreview() {
        if (!parsedWorkbook) return;
        var headers = parsedWorkbook.headers;
        fillSelect(accessSelect, headers, ['CHAVE DE ACESSO', 'CHAVE']);
        fillSelect(numberSelect, headers, ['NOTA', 'NUMERO', 'NUMERO DA NOTA']);
        fillSelect(partySelect, headers, ['CNPJ/CPF/CEI/CAEPF', 'CPF/CNPJ', 'CNPJ', 'DOCUMENTO']);
        fillSelect(issueDateSelect, headers, ['DATA EMISSAO', 'DATA EMISSÃO', 'EMISSAO', 'EMISSÃO', 'DATA']);
        refreshMappingVisibility();
        var rows = parsedWorkbook.sheets.reduce(function (total, sheet) { return total + sheet.rows.length; }, 0);
        var sheetNames = parsedWorkbook.sheets.map(function (sheet) { return sheet.name + ' (' + sheet.rows.length + ')'; }).join(', ');
        preview.innerHTML = '<strong>' + rows + ' linha(s)</strong> em ' + parsedWorkbook.sheets.length + ' aba(s): ' + escapeHtml(sheetNames);
        confirmButton.disabled = rows === 0;
    }
    async function parseFile(file) {
        if (!window.XLSX) throw new Error('Leitor de planilhas nao carregou.');
        setAccountingProgress('Lendo planilha', 1, 100, 'Abrindo arquivo...');
        var buffer = await file.arrayBuffer();
        setAccountingProgress('Lendo planilha', 15, 100, 'Identificando abas...');
        var workbook = XLSX.read(buffer, {type: 'array', cellDates: false, raw: false});
        var allHeaders = [];
        var headerSet = {};
        var sheets = [];
        for (var sheetIndex = 0; sheetIndex < workbook.SheetNames.length; sheetIndex++) {
            var name = workbook.SheetNames[sheetIndex];
            setAccountingProgress('Lendo planilha', sheetIndex + 1, workbook.SheetNames.length, 'Lendo aba ' + name + '...');
            await sleep(20);
            var sheet = workbook.Sheets[name];
            var matrix = XLSX.utils.sheet_to_json(sheet, {header: 1, defval: '', blankrows: false});
            if (!matrix.length) continue;
            var headers = matrix[0].map(function (cell, index) { return normalizeHeader(cell, 'COLUNA_' + (index + 1)); });
            headers.forEach(function (header) {
                if (!headerSet[header]) { headerSet[header] = true; allHeaders.push(header); }
            });
            var rows = matrix.slice(1).map(function (line, idx) {
                var row = {_rowNumber: idx + 2};
                headers.forEach(function (header, colIdx) { row[header] = line[colIdx] == null ? '' : String(line[colIdx]).trim(); });
                return row;
            }).filter(function (row) {
                return Object.keys(row).some(function (key) { return key !== '_rowNumber' && String(row[key] || '').trim() !== ''; });
            });
            if (rows.length) sheets.push({name: name, rows: rows});
        }
        setAccountingProgress('Planilha lida', 100, 100, 'Configure o de/para e confirme a importacao.');
        return {headers: allHeaders, sheets: sheets};
    }
    fileInput.addEventListener('change', async function () {
        confirmButton.disabled = true;
        feedback.textContent = '';
        parsedWorkbook = null;
        mappingBox.hidden = true;
        preview.textContent = 'Lendo planilha...';
        try {
            var file = fileInput.files && fileInput.files[0];
            if (!file) throw new Error('Selecione uma planilha.');
            parsedWorkbook = await parseFile(file);
            renderPreview();
        } catch (error) {
            preview.textContent = error.message || 'Nao foi possivel ler a planilha.';
        }
    });
    docTypeInput.addEventListener('change', refreshMappingVisibility);
    confirmButton.addEventListener('click', async function () {
        if (!parsedWorkbook) return;
        var type = docTypeInput.value;
        var mapping = type === 'NFSE'
            ? {number: numberSelect.value, party_document: partySelect.value, issue_date: issueDateSelect ? issueDateSelect.value : ''}
            : {access_key: accessSelect.value};
        if (type === 'NFSE' && (!mapping.number || !mapping.party_document)) {
            feedback.textContent = 'Informe as colunas de numero e CPF/CNPJ.';
            return;
        }
        if (type !== 'NFSE' && !mapping.access_key) {
            feedback.textContent = 'Informe a coluna da chave de acesso.';
            return;
        }
        confirmButton.disabled = true;
        feedback.textContent = 'Importando e vinculando...';
        try {
            var file = fileInput.files && fileInput.files[0];
            var replaceExisting = false;
            var checkParams = new URLSearchParams();
            checkParams.set('page', 'documents_accounting_check_file');
            checkParams.set('doc_type', type);
            checkParams.set('file_name', file ? file.name : '');
            var checkResponse = await fetch('?' + checkParams.toString(), {headers: {'Accept': 'application/json'}});
            var checkData = await checkResponse.json();
            if (!checkResponse.ok || !checkData.ok) throw new Error((checkData && checkData.message) || 'Falha ao verificar importacao anterior.');
            if (checkData.exists) {
                replaceExisting = window.confirm('Esta planilha ja foi importada para este tipo. Deseja excluir os dados anteriores e importar novamente?\\n\\nOK: excluir e importar novamente\\nCancelar: nao importar');
                if (!replaceExisting) {
                    feedback.textContent = 'Importacao cancelada. Os dados anteriores foram mantidos.';
                    confirmButton.disabled = false;
                    return;
                }
            }
            var batchSize = 100;
            var batches = [];
            parsedWorkbook.sheets.forEach(function (sheet) {
                for (var start = 0; start < sheet.rows.length; start += batchSize) {
                    batches.push({name: sheet.name, rows: sheet.rows.slice(start, start + batchSize)});
                }
            });
            var totals = {row_count: 0, matched_count: 0, missing_count: 0};
            for (var i = 0; i < batches.length; i++) {
                feedback.textContent = 'Importando bloco ' + (i + 1) + ' de ' + batches.length + '...';
                setAccountingProgress('Importando planilha', i, batches.length, 'Enviando bloco ' + (i + 1) + ' de ' + batches.length + '...');
                var response = await fetch('?page=documents_accounting_import', {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json;charset=UTF-8'},
                    body: JSON.stringify({_csrf: csrf.value, doc_type: type, file_name: file ? file.name : '', mapping: mapping, sheets: [batches[i]], replace_existing: i === 0 && replaceExisting, append_existing: i > 0})
                });
                var rawResponse = await response.text();
                var data = {};
                try {
                    data = rawResponse ? JSON.parse(rawResponse) : {};
                } catch (parseError) {
                    throw new Error('O servidor nao devolveu uma resposta valida no bloco ' + (i + 1) + ': ' + rawResponse.substring(0, 300));
                }
                if (!response.ok || !data.ok) {
                    throw new Error((data && data.message) || ('Falha ao importar contabilidade no bloco ' + (i + 1) + '. HTTP ' + response.status));
                }
                totals.row_count += Number(data.row_count || 0);
                totals.matched_count += Number(data.matched_count || 0);
                totals.missing_count += Number(data.missing_count || 0);
            }
            setAccountingProgress('Importacao concluida', batches.length, batches.length, 'Clique em Concluir para atualizar a tela.');
            feedback.textContent = 'Importacao concluida: ' + totals.row_count + ' linha(s), ' + totals.matched_count + ' entrada(s) localizada(s), ' + totals.missing_count + ' sem vinculo no portal.';
            importFinished = true;
            confirmButton.classList.add('is-hidden');
            if (doneButton) doneButton.classList.remove('is-hidden');
        } catch (error) {
            feedback.textContent = error.message || 'Erro ao importar contabilidade.';
            confirmButton.disabled = false;
        }
    });
    if (doneButton) doneButton.addEventListener('click', function () { window.location.reload(); });
    openButton.addEventListener('click', openModal);
    document.querySelectorAll('[data-close-accounting-modal]').forEach(function (button) { button.addEventListener('click', closeModal); });
    modal.addEventListener('click', function (event) { if (event.target === modal) closeModal(); });
})();
</script>

<script>
(function () {
    var detailsModal = document.getElementById('accounting-details-modal');
    var detailsHead = document.getElementById('accounting-details-head');
    var detailsBody = document.getElementById('accounting-details-body');
    var detailsSubtitle = document.getElementById('accounting-details-subtitle');
    var missingModal = document.getElementById('accounting-missing-modal');
    var missingHead = document.getElementById('accounting-missing-head');
    var missingBody = document.getElementById('accounting-missing-body');
    var missingType = document.getElementById('accounting-missing-type');
    var missingNumber = document.getElementById('accounting-missing-number');
    var missingSupplier = document.getElementById('accounting-missing-supplier');
    var missingFile = document.getElementById('accounting-missing-file');
    var missingSheet = document.getElementById('accounting-missing-sheet');
    var missingFileOptions = document.getElementById('accounting-missing-file-options');
    var missingSheetOptions = document.getElementById('accounting-missing-sheet-options');
    var missingDateStart = document.getElementById('accounting-missing-date-start');
    var missingDateEnd = document.getElementById('accounting-missing-date-end');
    var missingRefresh = document.getElementById('accounting-missing-refresh');
    var missingExport = document.getElementById('accounting-missing-export');
    var missingLaunch = document.getElementById('accounting-missing-launch');
    var missingPageInfo = document.getElementById('accounting-missing-page-info');
    var missingPrev = document.getElementById('accounting-missing-prev');
    var missingNext = document.getElementById('accounting-missing-next');
    var missingSelectAllFilter = document.getElementById('accounting-missing-select-all-filter');
    var missingClearSelection = document.getElementById('accounting-missing-clear-selection');
    var missingSelectionInfo = document.getElementById('accounting-missing-selection-info');
    var launchModal = document.getElementById('accounting-launch-modal');
    var launchSubtitle = document.getElementById('accounting-launch-subtitle');
    var launchAccessKey = document.getElementById('accounting-launch-map-access-key');
    var launchNumber = document.getElementById('accounting-launch-map-number');
    var launchIssuerDocument = document.getElementById('accounting-launch-map-issuer-document');
    var launchIssuerName = document.getElementById('accounting-launch-map-issuer-name');
    var launchIssueDate = document.getElementById('accounting-launch-map-issue-date');
    var launchEntryDate = document.getElementById('accounting-launch-map-entry-date');
    var launchSeries = document.getElementById('accounting-launch-map-series');
    var launchSpecies = document.getElementById('accounting-launch-map-species');
    var launchCode = document.getElementById('accounting-launch-map-code');
    var launchTotalValue = document.getElementById('accounting-launch-map-total-value');
    var launchStateRegistration = document.getElementById('accounting-launch-map-state-registration');
    var launchCfop = document.getElementById('accounting-launch-map-cfop');
    var launchOperationType = document.getElementById('accounting-launch-map-operation-type');
    var launchIssuerUf = document.getElementById('accounting-launch-map-issuer-uf');
    var launchDescription = document.getElementById('accounting-launch-map-description');
    var launchSheets = document.getElementById('accounting-launch-sheets');
    var launchConfirm = document.getElementById('accounting-launch-confirm');
    var launchFeedback = document.getElementById('accounting-launch-feedback');
    var launchProgress = document.getElementById('accounting-launch-progress');
    var launchProgressTitle = document.getElementById('accounting-launch-progress-title');
    var launchProgressText = document.getElementById('accounting-launch-progress-text');
    var launchProgressBar = document.getElementById('accounting-launch-progress-bar');
    var launchProgressDetail = document.getElementById('accounting-launch-progress-detail');
    var csrf = document.querySelector('form.documents-card input[name="_csrf"]');
    var companyOptions = <?= json_encode($companyOptions, JSON_UNESCAPED_UNICODE) ?>;
    var missingEntries = [];
    var launchEntries = [];
    var missingPage = 1;
    var missingPerPage = 100;
    var missingTotal = 0;
    var missingAllSelected = false;
    var launchAllFromFilter = false;
    if (!detailsModal || !detailsBody || !missingModal || !missingBody) return;
    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char] || char;
        });
    }
    function norm(value) {
        return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase();
    }
    function fillLaunchSelect(select, headers, preferred, allowEmpty) {
        if (!select) return;
        var options = allowEmpty ? [''] : [];
        options = options.concat(headers);
        select.innerHTML = options.map(function (header) {
            return '<option value="' + escapeHtml(header) + '">' + escapeHtml(header || 'Selecionar') + '</option>';
        }).join('');
        var found = preferredHeader(headers, preferred);
        if (found) select.value = found;
    }
    function preferredHeader(headers, preferred) {
        var preferredNorms = preferred.map(norm);
        return headers.find(function (header) {
            var h = norm(header);
            return preferredNorms.some(function (token) { return h.indexOf(token) >= 0; });
        }) || '';
    }
    function launchSelectHtml(sheet, field, headers, preferred, allowEmpty, hidden) {
        var selected = preferredHeader(headers, preferred);
        var options = (allowEmpty ? [''] : []).concat(headers).map(function (header) {
            return '<option value="' + escapeHtml(header) + '"' + (header === selected ? ' selected' : '') + '>' + escapeHtml(header || 'Selecionar') + '</option>';
        }).join('');
        return '<label' + (hidden ? ' class="is-hidden"' : '') + '>' + escapeHtml(field.label) + '<select data-accounting-launch-sheet-map="' + escapeHtml(sheet) + '" data-accounting-launch-map-field="' + escapeHtml(field.key) + '">' + options + '</select></label>';
    }
    function selectedMissingEntries() {
        var ids = Array.from(document.querySelectorAll('[data-accounting-missing-check]:checked')).map(function (input) {
            return String(input.value || '');
        });
        return missingEntries.filter(function (entry) { return ids.indexOf(String(entry.id)) >= 0; });
    }
    function rawHeaders(entries) {
        var headers = [];
        entries.forEach(function (entry) {
            if (Array.isArray(entry.headers)) {
                entry.headers.forEach(function (key) {
                    if (key && headers.indexOf(key) < 0) headers.push(key);
                });
            }
            Object.keys(entry.raw || {}).forEach(function (key) {
                if (key !== '_rowNumber' && headers.indexOf(key) < 0) headers.push(key);
            });
        });
        return headers;
    }
    function accountingGroupKey(entry) {
        return String(entry.file_name || '') + '||' + String(entry.sheet_name || '');
    }
    function accountingGroupLabel(entry) {
        var file = String(entry.file_name || 'Sem planilha');
        var sheet = String(entry.sheet_name || 'Sem aba');
        return file + ' > ' + sheet;
    }
    function renderHead(head, fixed, headers) {
        if (!head) return;
        head.innerHTML = '<tr>' + fixed.concat(headers).map(function (header) {
            return '<th>' + escapeHtml(header) + '</th>';
        }).join('') + '</tr>';
    }
    function renderRawCells(raw, headers) {
        return headers.map(function (header) {
            return '<td>' + escapeHtml((raw || {})[header] || '') + '</td>';
        }).join('');
    }
    function missingParams(limit, page) {
        var params = new URLSearchParams();
        params.set('page', 'documents_accounting_missing');
        params.set('limit', String(limit || missingPerPage));
        params.set('missing_page', String(page || missingPage));
        if (missingType && missingType.value) params.set('doc_type', missingType.value);
        if (missingNumber && missingNumber.value) params.set('number_q', missingNumber.value);
        if (missingSupplier && missingSupplier.value) params.set('supplier_q', missingSupplier.value);
        if (missingFile && missingFile.value) params.set('file_q', missingFile.value);
        if (missingSheet && missingSheet.value) params.set('sheet_q', missingSheet.value);
        if (missingDateStart && missingDateStart.value) params.set('date_start', missingDateStart.value);
        if (missingDateEnd && missingDateEnd.value) params.set('date_end', missingDateEnd.value);
        return params;
    }
    async function fetchMissingEntries(limit, page) {
        var response = await fetch('?' + missingParams(limit, page).toString(), {headers: {'Accept': 'application/json'}});
        var data = await response.json();
        if (!response.ok || !data.ok) throw new Error((data && data.message) || 'Falha ao carregar registros.');
        return data;
    }
    async function fetchMissingGroups() {
        var params = missingParams(1, 1);
        params.set('groups_only', '1');
        var response = await fetch('?' + params.toString(), {headers: {'Accept': 'application/json'}});
        var data = await response.json();
        if (!response.ok || !data.ok) throw new Error((data && data.message) || 'Falha ao carregar planilhas e abas.');
        return data;
    }
    async function fetchMissingOptions() {
        var params = missingParams(1, 1);
        params.set('options_only', '1');
        var response = await fetch('?' + params.toString(), {headers: {'Accept': 'application/json'}});
        var data = await response.json();
        if (!response.ok || !data.ok) return;
        if (missingFileOptions) {
            missingFileOptions.innerHTML = (data.files || []).map(function (value) {
                return '<option value="' + escapeHtml(value) + '"></option>';
            }).join('');
        }
        if (missingSheetOptions) {
            missingSheetOptions.innerHTML = (data.sheets || []).map(function (value) {
                return '<option value="' + escapeHtml(value) + '"></option>';
            }).join('');
        }
    }
    async function fetchMissingIds(limit) {
        var params = missingParams(limit || 500, 1);
        params.set('ids_only', '1');
        var response = await fetch('?' + params.toString(), {headers: {'Accept': 'application/json'}});
        var data = await response.json();
        if (!response.ok || !data.ok) throw new Error((data && data.message) || 'Falha ao carregar notas para lancamento.');
        return data.ids || [];
    }
    function updateMissingPagination() {
        var pages = Math.max(1, Math.ceil(missingTotal / missingPerPage));
        if (missingPageInfo) {
            var start = missingTotal ? ((missingPage - 1) * missingPerPage + 1) : 0;
            var end = Math.min(missingTotal, missingPage * missingPerPage);
            missingPageInfo.textContent = 'Página ' + missingPage + ' de ' + pages + ' | ' + start + '-' + end + ' de ' + missingTotal + ' registro(s)';
        }
        if (missingSelectionInfo) {
            missingSelectionInfo.textContent = missingAllSelected
                ? 'Todos os ' + missingTotal + ' registro(s) do filtro estao marcados, incluindo as outras paginas.'
                : '';
        }
        if (missingPrev) missingPrev.disabled = missingPage <= 1;
        if (missingNext) missingNext.disabled = missingPage >= pages;
    }
    function setLaunchProgress(current, total, detail) {
        if (!launchProgress) return;
        launchProgress.classList.remove('is-hidden');
        var percent = total > 0 ? Math.round((current / total) * 100) : 0;
        if (launchProgressTitle) launchProgressTitle.textContent = 'Lançando notas no portal';
        if (launchProgressText) launchProgressText.textContent = percent + '%';
        if (launchProgressBar) launchProgressBar.style.width = Math.max(0, Math.min(100, percent)) + '%';
        if (launchProgressDetail) launchProgressDetail.textContent = detail || '';
    }
    function closeDetails() { detailsModal.classList.add('is-hidden'); }
    function closeMissing() { missingModal.classList.add('is-hidden'); }
    function closeLaunch() {
        if (launchModal) launchModal.classList.add('is-hidden');
        if (launchProgress) launchProgress.classList.add('is-hidden');
        if (launchProgressBar) launchProgressBar.style.width = '0';
    }
    document.querySelectorAll('[data-accounting-details]').forEach(function (button) {
        button.addEventListener('click', async function () {
            detailsModal.classList.remove('is-hidden');
            detailsBody.innerHTML = '<tr><td colspan="4">Carregando...</td></tr>';
            try {
                var response = await fetch('?page=documents_accounting_entries&id=' + encodeURIComponent(button.getAttribute('data-accounting-details') || ''), {headers: {'Accept': 'application/json'}});
                var data = await response.json();
                if (!response.ok || !data.ok) throw new Error((data && data.message) || 'Falha ao carregar detalhe.');
                var doc = data.document || {};
                detailsSubtitle.textContent = [doc.doc_type, doc.number, doc.issuer_name].filter(Boolean).join(' | ');
                var entries = data.entries || [];
                var headers = rawHeaders(entries);
                renderHead(detailsHead, ['Aba', 'Linha', 'Arquivo'], headers);
                detailsBody.innerHTML = entries.length ? entries.map(function (entry) {
                    return '<tr><td>' + escapeHtml(entry.sheet_name) + '</td><td>' + escapeHtml(entry.row_number) + '</td><td>' + escapeHtml(entry.file_name) + '</td>' + renderRawCells(entry.raw, headers) + '</tr>';
                }).join('') : '<tr><td colspan="3">Nenhum detalhe encontrado.</td></tr>';
            } catch (error) {
                detailsBody.innerHTML = '<tr><td colspan="3">' + escapeHtml(error.message || 'Erro ao carregar detalhe.') + '</td></tr>';
            }
        });
    });
    async function loadMissing(selectAll, page) {
        missingModal.classList.remove('is-hidden');
        missingBody.innerHTML = '<tr><td colspan="7">Carregando...</td></tr>';
        try {
            fetchMissingOptions().catch(function () {});
            missingPage = Math.max(1, page || missingPage || 1);
            var data = await fetchMissingEntries(missingPerPage, missingPage);
            var entries = data.entries || [];
            missingTotal = Number(data.total || entries.length || 0);
            missingEntries = entries;
            var headers = rawHeaders(entries);
            if (missingHead) {
                missingHead.innerHTML = '<tr><th><input type="checkbox" data-accounting-missing-check-all></th>' + ['Tipo', 'Planilha', 'Aba', 'Linha', 'Chave/Número', 'Documento'].concat(headers).map(function (header) {
                    return '<th>' + escapeHtml(header) + '</th>';
                }).join('') + '</tr>';
                var checkAll = missingHead.querySelector('[data-accounting-missing-check-all]');
                if (checkAll) {
                    checkAll.checked = !!selectAll || missingAllSelected;
                    checkAll.addEventListener('change', function () {
                        missingAllSelected = checkAll.checked;
                        document.querySelectorAll('[data-accounting-missing-check]').forEach(function (input) { input.checked = checkAll.checked; });
                        updateMissingPagination();
                    });
                }
            }
            missingBody.innerHTML = entries.length ? entries.map(function (entry) {
                var key = entry.access_key || entry.document_number || '';
                return '<tr><td><input type="checkbox" data-accounting-missing-check value="' + escapeHtml(entry.id) + '"' + ((selectAll || missingAllSelected) ? ' checked' : '') + '></td><td>' + escapeHtml(entry.doc_type) + '</td><td>' + escapeHtml(entry.file_name || '') + '</td><td>' + escapeHtml(entry.sheet_name) + '</td><td>' + escapeHtml(entry.row_number) + '</td><td>' + escapeHtml(key) + '</td><td>' + escapeHtml(entry.party_document || '') + '</td>' + renderRawCells(entry.raw, headers) + '</tr>';
            }).join('') : '<tr><td colspan="7">Nenhum registro pendente encontrado.</td></tr>';
            document.querySelectorAll('[data-accounting-missing-check]').forEach(function (input) {
                input.addEventListener('change', function () {
                    if (!input.checked) {
                        missingAllSelected = false;
                        var checkAll = missingHead ? missingHead.querySelector('[data-accounting-missing-check-all]') : null;
                        if (checkAll) checkAll.checked = false;
                        updateMissingPagination();
                    }
                });
            });
            updateMissingPagination();
        } catch (error) {
            missingBody.innerHTML = '<tr><td colspan="7">' + escapeHtml(error.message || 'Erro ao carregar registros.') + '</td></tr>';
        }
    }
    function exportMissing() {
        var params = new URLSearchParams();
        params.set('page', 'documents_accounting_missing_export');
        if (missingType && missingType.value) params.set('doc_type', missingType.value);
        if (missingNumber && missingNumber.value) params.set('number_q', missingNumber.value);
        if (missingSupplier && missingSupplier.value) params.set('supplier_q', missingSupplier.value);
        if (missingFile && missingFile.value) params.set('file_q', missingFile.value);
        if (missingSheet && missingSheet.value) params.set('sheet_q', missingSheet.value);
        if (missingDateStart && missingDateStart.value) params.set('date_start', missingDateStart.value);
        if (missingDateEnd && missingDateEnd.value) params.set('date_end', missingDateEnd.value);
        window.location.href = '?' + params.toString();
    }
    async function openLaunch() {
        if (missingAllSelected) {
            if (launchFeedback) launchFeedback.textContent = '';
            if (missingLaunch) missingLaunch.disabled = true;
            missingBody.innerHTML = '<tr><td colspan="7">Carregando planilhas e abas do filtro para montar o de/para...</td></tr>';
            try {
                var groupData = await fetchMissingGroups();
                launchEntries = groupData.groups || [];
                missingTotal = Number(groupData.total || missingTotal || 0);
                launchAllFromFilter = true;
            } catch (error) {
                missingBody.innerHTML = '<tr><td colspan="7">' + escapeHtml(error.message || 'Erro ao carregar planilhas e abas.') + '</td></tr>';
                if (missingLaunch) missingLaunch.disabled = false;
                return;
            } finally {
                if (missingLaunch) missingLaunch.disabled = false;
            }
        } else {
            launchEntries = selectedMissingEntries();
            launchAllFromFilter = false;
        }
        if (!launchEntries.length) {
            alert('Selecione ao menos uma nota para lancar no portal.');
            return;
        }
        var types = Array.from(new Set(launchEntries.map(function (entry) { return String(entry.doc_type || '').toUpperCase(); })));
        if (types.length > 1) {
            alert('Selecione notas de apenas um tipo por lancamento.');
            return;
        }
        var headers = rawHeaders(launchEntries);
        fillLaunchSelect(launchAccessKey, headers, ['CHAVE DE ACESSO', 'CHAVE'], true);
        fillLaunchSelect(launchNumber, headers, ['NOTA', 'NUMERO', 'NUMERO DA NOTA'], false);
        fillLaunchSelect(launchIssuerDocument, headers, ['CNPJ/CPF/CEI/CAEPF', 'CPF/CNPJ', 'CNPJ', 'DOCUMENTO'], false);
        fillLaunchSelect(launchIssuerName, headers, ['FORNECEDOR', 'PRESTADOR', 'RAZAO SOCIAL', 'NOME'], true);
        fillLaunchSelect(launchIssueDate, headers, ['DATA EMISSAO', 'EMISSAO', 'DATA'], true);
        fillLaunchSelect(launchEntryDate, headers, ['DATA ENTRADA', 'ENTRADA'], true);
        fillLaunchSelect(launchSeries, headers, ['SERIE', 'SÉRIE'], true);
        fillLaunchSelect(launchSpecies, headers, ['ESPECIE', 'ESPÉCIE'], true);
        fillLaunchSelect(launchCode, headers, ['CODIGO', 'CÓDIGO'], true);
        fillLaunchSelect(launchTotalValue, headers, ['VALOR CONTABIL', 'VALOR', 'TOTAL'], true);
        fillLaunchSelect(launchStateRegistration, headers, ['INSC. EST.', 'INSCRICAO ESTADUAL', 'INSCRIÇÃO ESTADUAL', 'IE'], true);
        fillLaunchSelect(launchCfop, headers, ['CFOP'], true);
        fillLaunchSelect(launchOperationType, headers, ['TIPO'], true);
        fillLaunchSelect(launchIssuerUf, headers, ['UF'], true);
        fillLaunchSelect(launchDescription, headers, ['DESCRICAO', 'DISCRIMINACAO', 'SERVICO', 'HISTORICO', 'PRODUTO'], true);
        var accessField = launchModal ? launchModal.querySelector('[data-launch-field="access_key"]') : null;
        if (accessField) accessField.style.display = types[0] === 'NFSE' ? 'none' : '';
        if (launchSubtitle) launchSubtitle.textContent = (launchAllFromFilter ? missingTotal : launchEntries.length) + ' nota(s) selecionada(s) para ' + types[0] + '.';
        var groupKeys = Array.from(new Set(launchEntries.map(accountingGroupKey)));
        if (launchSheets) {
            launchSheets.innerHTML = groupKeys.map(function (groupKey) {
                var sheetEntries = launchEntries.filter(function (entry) { return accountingGroupKey(entry) === groupKey; });
                var firstEntry = sheetEntries[0] || {};
                var sheet = String(firstEntry.sheet_name || '');
                var groupLabel = accountingGroupLabel(firstEntry);
                var sheetHeaders = rawHeaders(sheetEntries);
                var options = '<option value="">Selecionar empresa</option>' + companyOptions.map(function (company) {
                    var companyLabel = company.label || '';
                    var sheetNorm = norm(sheet);
                    var labelNorm = norm(companyLabel);
                    var selected = (sheetNorm.indexOf('MATRIZ') >= 0 && companyLabel.indexOf('05102155000152') >= 0)
                        || (sheetNorm.indexOf('MARINGA') >= 0 && companyLabel.indexOf('05102155000152') >= 0)
                        || (sheetNorm.indexOf('BATAGUASSU') >= 0 && companyLabel.indexOf('05102155000586') >= 0)
                        || (sheetNorm.indexOf('FOZ') >= 0 && companyLabel.indexOf('05102155000403') >= 0)
                        || (sheetNorm.indexOf('CASCAVEL') >= 0 && companyLabel.indexOf('05102155000667') >= 0)
                        || (sheetNorm.indexOf('CURITIBA') >= 0 && companyLabel.indexOf('05102155000233') >= 0)
                        || (labelNorm.indexOf(sheetNorm) >= 0 && sheetNorm !== '');
                    return '<option value="' + escapeHtml(company.value) + '"' + (selected ? ' selected' : '') + '>' + escapeHtml(companyLabel) + '</option>';
                }).join('');
                var fields = [
                    {key: 'access_key', label: 'Chave de acesso', preferred: ['CHAVE DE ACESSO', 'CHAVE'], allowEmpty: true, hidden: types[0] === 'NFSE'},
                    {key: 'number', label: 'Numero da nota', preferred: ['NOTA', 'NUMERO', 'NUMERO DA NOTA'], allowEmpty: false},
                    {key: 'issuer_document', label: 'CPF/CNPJ fornecedor', preferred: ['CNPJ/CPF/CEI/CAEPF', 'CPF/CNPJ', 'CNPJ', 'DOCUMENTO'], allowEmpty: false},
                    {key: 'issuer_name', label: 'Nome fornecedor', preferred: ['FORNECEDOR', 'PRESTADOR', 'RAZAO SOCIAL', 'NOME'], allowEmpty: true},
                    {key: 'issue_date', label: 'Data emissao', preferred: ['DATA EMISSAO', 'DATA EMISSÃO', 'EMISSAO', 'EMISSÃO', 'DATA'], allowEmpty: true},
                    {key: 'entry_date', label: 'Data entrada', preferred: ['DATA ENTRADA', 'ENTRADA'], allowEmpty: true},
                    {key: 'series', label: 'Serie', preferred: ['SERIE', 'SÉRIE'], allowEmpty: true},
                    {key: 'species', label: 'Especie', preferred: ['ESPECIE', 'ESPÉCIE'], allowEmpty: true},
                    {key: 'code', label: 'Codigo', preferred: ['CODIGO', 'CÓDIGO'], allowEmpty: true},
                    {key: 'total_value', label: 'Valor', preferred: ['VALOR CONTABIL', 'VALOR', 'TOTAL'], allowEmpty: true},
                    {key: 'state_registration', label: 'Inscricao estadual', preferred: ['INSC. EST.', 'INSCRICAO ESTADUAL', 'INSCRIÇÃO ESTADUAL', 'IE'], allowEmpty: true},
                    {key: 'cfop', label: 'CFOP', preferred: ['CFOP'], allowEmpty: true},
                    {key: 'operation_type', label: 'Tipo', preferred: ['TIPO'], allowEmpty: true},
                    {key: 'issuer_uf', label: 'UF', preferred: ['UF'], allowEmpty: true},
                    {key: 'description', label: 'Descricao', preferred: ['DESCRICAO', 'DISCRIMINACAO', 'SERVICO', 'HISTORICO', 'PRODUTO'], allowEmpty: true}
                ];
                var mappings = fields.map(function (field) {
                    return launchSelectHtml(groupKey, field, sheetHeaders, field.preferred, field.allowEmpty, field.hidden);
                }).join('');
                var groupTotal = firstEntry.total ? ' <small>(' + escapeHtml(firstEntry.total) + ' registro(s))</small>' : '';
                return '<div class="accounting-launch-sheet-card"><strong>' + escapeHtml(groupLabel) + groupTotal + '</strong><label>Empresa<select data-accounting-launch-sheet="' + escapeHtml(groupKey) + '">' + options + '</select></label><div class="accounting-launch-sheet-map">' + mappings + '</div></div>';
            }).join('');
        }
        if (launchFeedback) launchFeedback.textContent = '';
        if (launchProgress) launchProgress.classList.add('is-hidden');
        if (launchProgressBar) launchProgressBar.style.width = '0';
        if (launchModal) launchModal.classList.remove('is-hidden');
    }
    async function confirmLaunch() {
        if (!launchEntries.length || !csrf || !launchConfirm) return;
        var sheetCompanies = {};
        var missingCompany = false;
        document.querySelectorAll('[data-accounting-launch-sheet]').forEach(function (select) {
            var sheet = select.getAttribute('data-accounting-launch-sheet') || '';
            sheetCompanies[sheet] = select.value;
            if (!select.value) missingCompany = true;
        });
        if (missingCompany) {
            if (launchFeedback) launchFeedback.textContent = 'Informe a empresa de todas as abas selecionadas.';
            return;
        }
        var type = String(launchEntries[0].doc_type || '').toUpperCase();
        var baseMapping = {
            access_key: launchAccessKey ? launchAccessKey.value : '',
            number: launchNumber ? launchNumber.value : '',
            issuer_document: launchIssuerDocument ? launchIssuerDocument.value : '',
            issuer_name: launchIssuerName ? launchIssuerName.value : '',
            issue_date: launchIssueDate ? launchIssueDate.value : '',
            entry_date: launchEntryDate ? launchEntryDate.value : '',
            series: launchSeries ? launchSeries.value : '',
            species: launchSpecies ? launchSpecies.value : '',
            code: launchCode ? launchCode.value : '',
            total_value: launchTotalValue ? launchTotalValue.value : '',
            state_registration: launchStateRegistration ? launchStateRegistration.value : '',
            cfop: launchCfop ? launchCfop.value : '',
            operation_type: launchOperationType ? launchOperationType.value : '',
            issuer_uf: launchIssuerUf ? launchIssuerUf.value : '',
            description: launchDescription ? launchDescription.value : ''
        };
        var mapping = Object.assign({_sheets: {}}, baseMapping);
        mapping._sheets = {};
        document.querySelectorAll('[data-accounting-launch-sheet]').forEach(function (select) {
            var sheet = select.getAttribute('data-accounting-launch-sheet') || '';
            mapping._sheets[sheet] = Object.assign({}, baseMapping);
        });
        document.querySelectorAll('[data-accounting-launch-sheet-map]').forEach(function (select) {
            var sheet = select.getAttribute('data-accounting-launch-sheet-map') || '';
            var field = select.getAttribute('data-accounting-launch-map-field') || '';
            if (!mapping._sheets[sheet]) mapping._sheets[sheet] = Object.assign({}, baseMapping);
            mapping._sheets[sheet][field] = select.value;
        });
        var invalidSheet = Object.keys(mapping._sheets).find(function (groupKey) {
            var sheetMapping = mapping._sheets[groupKey] || {};
            return type === 'NFSE'
                ? (!sheetMapping.number || !sheetMapping.issuer_document)
                : (!sheetMapping.access_key);
        });
        if (invalidSheet) {
            var invalidEntry = launchEntries.find(function (entry) { return accountingGroupKey(entry) === invalidSheet; });
            var invalidLabel = invalidEntry ? accountingGroupLabel(invalidEntry) : invalidSheet;
            if (launchFeedback) launchFeedback.textContent = type === 'NFSE'
                ? 'Informe numero da nota e CPF/CNPJ fornecedor em ' + invalidLabel + '.'
                : 'Informe a chave de acesso em ' + invalidLabel + '.';
            return;
        }
        launchConfirm.disabled = true;
        var totalToProcess = launchAllFromFilter ? missingTotal : launchEntries.length;
        if (launchFeedback) launchFeedback.textContent = 'Iniciando lancamento no portal...';
        setLaunchProgress(0, totalToProcess, 'Preparando ' + totalToProcess + ' nota(s)...');
        try {
            var batchSize = 100;
            var totals = {created_count: 0, linked_count: 0, skipped_count: 0};
            var processed = 0;
            async function sendLaunchBatch(batch) {
                var response = await fetch('?page=documents_accounting_launch', {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json;charset=UTF-8'},
                    body: JSON.stringify({
                        _csrf: csrf.value,
                        entry_ids: batch,
                        mapping: mapping,
                        sheet_companies: sheetCompanies
                    })
                });
                var data = await response.json();
                if (!response.ok || !data.ok) throw new Error((data && data.message) || 'Falha ao lancar no portal.');
                totals.created_count += Number(data.created_count || 0);
                totals.linked_count += Number(data.linked_count || 0);
                totals.skipped_count += Number(data.skipped_count || 0);
                processed += batch.length;
                setLaunchProgress(Math.min(processed, totalToProcess), totalToProcess, 'Processadas ' + Math.min(processed, totalToProcess) + ' de ' + totalToProcess + ' nota(s).');
            }
            if (launchAllFromFilter) {
                setLaunchProgress(0, totalToProcess, 'Carregando lista de notas do filtro...');
                var idsFromFilter = await fetchMissingIds(totalToProcess);
                totalToProcess = idsFromFilter.length || totalToProcess;
                var block = 1;
                for (var filterStart = 0; filterStart < idsFromFilter.length; filterStart += batchSize) {
                    var batchFromFilter = idsFromFilter.slice(filterStart, filterStart + batchSize);
                    setLaunchProgress(processed, totalToProcess, 'Enviando bloco ' + block + '...');
                    await sendLaunchBatch(batchFromFilter);
                    block++;
                }
            } else {
                var ids = launchEntries.map(function (entry) { return entry.id; });
                for (var start = 0; start < ids.length; start += batchSize) {
                    var batch = ids.slice(start, start + batchSize);
                    setLaunchProgress(start, ids.length, 'Enviando bloco ' + (Math.floor(start / batchSize) + 1) + ' de ' + Math.ceil(ids.length / batchSize) + '...');
                    await sendLaunchBatch(batch);
                }
            }
            if (launchFeedback) launchFeedback.textContent = 'Concluido: ' + totals.created_count + ' criada(s), ' + totals.linked_count + ' ja existente(s) vinculada(s), ' + totals.skipped_count + ' ignorada(s).';
            missingAllSelected = false;
            await loadMissing(false, 1);
        } catch (error) {
            if (launchFeedback) launchFeedback.textContent = error.message || 'Erro ao lancar no portal.';
        } finally {
            launchConfirm.disabled = false;
        }
    }
    document.querySelectorAll('[data-open-accounting-missing]').forEach(function (button) {
        button.addEventListener('click', function () {
            missingPage = 1;
            missingAllSelected = false;
            loadMissing(false, 1);
        });
    });
    if (missingRefresh) missingRefresh.addEventListener('click', function () {
        missingPage = 1;
        missingAllSelected = false;
        loadMissing(false, 1);
    });
    if (missingExport) missingExport.addEventListener('click', exportMissing);
    if (missingLaunch) missingLaunch.addEventListener('click', openLaunch);
    if (launchConfirm) launchConfirm.addEventListener('click', confirmLaunch);
    if (missingPrev) missingPrev.addEventListener('click', function () {
        if (missingPage > 1) {
            missingPage--;
            loadMissing(missingAllSelected, missingPage);
        }
    });
    if (missingNext) missingNext.addEventListener('click', function () {
        var pages = Math.max(1, Math.ceil(missingTotal / missingPerPage));
        if (missingPage < pages) {
            missingPage++;
            loadMissing(missingAllSelected, missingPage);
        }
    });
    if (missingSelectAllFilter) missingSelectAllFilter.addEventListener('click', function () {
        missingAllSelected = true;
        document.querySelectorAll('[data-accounting-missing-check]').forEach(function (input) { input.checked = true; });
        var checkAll = missingHead ? missingHead.querySelector('[data-accounting-missing-check-all]') : null;
        if (checkAll) checkAll.checked = true;
        updateMissingPagination();
    });
    if (missingClearSelection) missingClearSelection.addEventListener('click', function () {
        missingAllSelected = false;
        document.querySelectorAll('[data-accounting-missing-check]').forEach(function (input) { input.checked = false; });
        var checkAll = missingHead ? missingHead.querySelector('[data-accounting-missing-check-all]') : null;
        if (checkAll) checkAll.checked = false;
        updateMissingPagination();
    });
    function reloadMissingFromFirstPage() {
        missingPage = 1;
        missingAllSelected = false;
        loadMissing(false, 1);
    }
    if (missingType) missingType.addEventListener('change', reloadMissingFromFirstPage);
    if (missingDateStart) missingDateStart.addEventListener('change', reloadMissingFromFirstPage);
    if (missingDateEnd) missingDateEnd.addEventListener('change', reloadMissingFromFirstPage);
    [missingNumber, missingSupplier, missingFile, missingSheet].forEach(function (input) {
        if (!input) return;
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                reloadMissingFromFirstPage();
            }
        });
    });
    document.querySelectorAll('[data-close-accounting-details]').forEach(function (button) { button.addEventListener('click', closeDetails); });
    document.querySelectorAll('[data-close-accounting-missing]').forEach(function (button) { button.addEventListener('click', closeMissing); });
    document.querySelectorAll('[data-close-accounting-launch]').forEach(function (button) { button.addEventListener('click', closeLaunch); });
    detailsModal.addEventListener('click', function (event) { if (event.target === detailsModal) closeDetails(); });
    missingModal.addEventListener('click', function (event) { if (event.target === missingModal) closeMissing(); });
    if (launchModal) launchModal.addEventListener('click', function (event) { if (event.target === launchModal) closeLaunch(); });
})();
</script>

<script>
(function () {
    var button = document.querySelector('[data-check-cancel-queue]');
    var modal = document.getElementById('cancel-check-modal');
    var panel = document.getElementById('cancel-check-progress');
    var text = document.getElementById('cancel-check-progress-text');
    var bar = document.getElementById('cancel-check-progress-bar');
    var detail = document.getElementById('cancel-check-progress-detail');
    var csrf = document.querySelector('form.documents-card input[name="_csrf"]');
    if (!button || !modal || !panel || !text || !bar || !detail || !csrf) return;
    function pageIds() {
        return Array.from(document.querySelectorAll('[data-doc-checkbox]')).map(function (input) { return input.value; }).filter(Boolean);
    }
    function selectedIds() {
        return Array.from(document.querySelectorAll('[data-doc-checkbox]:checked')).map(function (input) { return input.value; }).filter(Boolean);
    }
    function selectedNfeConsultaCount() {
        return Array.from(document.querySelectorAll('[data-doc-checkbox]:checked')).filter(function (input) {
            var type = String(input.getAttribute('data-doc-type') || '').toUpperCase();
            return type === 'NFE' || type === 'NFCE';
        }).length;
    }
    async function filteredIds() {
        var params = new URLSearchParams(window.location.search);
        params.set('page', 'documents_filter_ids');
        params.set('limit', '10000');
        var response = await fetch('?' + params.toString(), {headers: {'Accept': 'application/json'}});
        var data = await response.json();
        if (!response.ok || !data.ok) throw new Error((data && data.message) || 'Falha ao carregar os documentos do filtro.');
        if (data.truncated) {
            alert('O filtro tem ' + data.total + ' documento(s). A fila carregou os primeiros ' + data.limit + ' para evitar travar o servidor. Refine o filtro e rode novamente para o restante.');
        }
        return (data.ids || []).map(function (id) { return String(id); }).filter(Boolean);
    }
    function setProgress(done, total, message) {
        var pct = total > 0 ? Math.round((done / total) * 100) : 0;
        text.textContent = done + ' de ' + total;
        bar.style.width = pct + '%';
        detail.textContent = message || '';
    }
    button.addEventListener('click', async function () {
        var ids = selectedIds();
        if (!ids.length) {
            alert('Selecione ao menos uma entrada.');
            return;
        }
        var nfeConsultaCount = selectedNfeConsultaCount();
        if (nfeConsultaCount > 1) {
            alert('A verifica\u00e7\u00e3o manual de cancelamento de NF-e/NFC-e deve ser feita uma nota por vez. Desmarque as notas adicionais e tente novamente. A consulta em lote permanece dispon\u00edvel para CT-e e NFS-e.');
            return;
        }
        button.disabled = true;
        modal.classList.remove('is-hidden');
        try {
            var visibleIds = pageIds();
            var filteredTotal = Number(button.getAttribute('data-filtered-total') || 0);
            if (nfeConsultaCount > 0 && visibleIds.length > 0 && ids.length === visibleIds.length && filteredTotal > ids.length) {
                modal.classList.add('is-hidden');
                button.disabled = false;
                alert('Para NF-e/NFC-e, a verifica\u00e7\u00e3o manual\u00a0\u00e9 permitida somente para uma nota por vez. Refine o filtro para uma \u00fanica nota e selecione apenas essa nota. CT-e e NFS-e podem ser verificados em lote.');
                return;
            }
            if (visibleIds.length > 0 && ids.length === visibleIds.length && filteredTotal > ids.length) {
                var useAll = confirm('Você marcou todos os documentos desta página. Deseja verificar todos os ' + filteredTotal + ' documento(s) do filtro atual?\\n\\nOK: todos do filtro\\nCancelar: somente os selecionados da página');
                if (useAll) {
                    setProgress(0, filteredTotal, 'Carregando documentos do filtro...');
                    ids = await filteredIds();
                }
            }
        } catch (error) {
            button.disabled = false;
            setProgress(0, ids.length, error.message || 'Erro ao preparar a fila.');
            return;
        }
        setProgress(0, ids.length, 'Iniciando fila de consulta...');
        var cancelled = 0;
        var errors = 0;
        var scheduled = 0;
        for (var i = 0; i < ids.length; i++) {
            var body = new URLSearchParams();
            body.set('_csrf', csrf.value);
            body.set('id', ids[i]);
            try {
                var response = await fetch('?page=documents_check_cancel', {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
                    body: body.toString()
                });
                var data = await response.json();
                if (!response.ok || !data.ok) throw new Error((data && data.message) || 'Falha ao consultar documento.');
                if (data.cancelled) cancelled++;
                if (data.queue_status === 'retry' || data.queue_status === 'pending' || data.queue_status === 'running') scheduled++;
                if (data.queue_status === 'failed') errors++;
                var progressMessage = data.message || 'Consulta concluida.';
                if (data.next_attempt_at) progressMessage += ' | Nova tentativa: ' + data.next_attempt_at;
                setProgress(i + 1, ids.length, progressMessage);
            } catch (error) {
                errors++;
                setProgress(i + 1, ids.length, error.message || 'Erro ao consultar documento.');
            }
        }
        detail.textContent = 'Fila concluida: ' + ids.length + ' consultado(s), ' + cancelled + ' cancelado(s), ' + scheduled + ' reagendado(s), ' + errors + ' erro(s). Atualizando a tela...';
        setTimeout(function () { window.location.reload(); }, 900);
    });
})();
</script>

<script>
(function () {
    var form = document.querySelector('form.documents-filter');
    var modal = document.getElementById('documents-loading-modal');
    if (!form || !modal) return;
    function syncGridFilters() {
        var gridForm = document.getElementById('column-filter-form');
        if (!gridForm) return;
        var fields = ['company_q', 'number_q', 'issuer_q', 'recipient_q', 'access_key_q', 'referenced_nfe_q', 'referenced_number_q', 'manifestation_status', 'source_q', 'integrated', 'accounting_posted', 'supplier_group_id'];
        fields.forEach(function (name) {
            var source = gridForm.querySelector('[name="' + name + '"]');
            if (!source || source.value === '') return;
            var target = form.querySelector('[name="' + name + '"]');
            if (!target) {
                target = document.createElement('input');
                target.type = 'hidden';
                target.name = name;
                form.appendChild(target);
            }
            target.value = source.value;
        });
    }
    form.addEventListener('submit', function () {
        syncGridFilters();
        modal.classList.remove('is-hidden');
    });
})();
</script>

<script>
(function () {
    var form = document.getElementById('column-filter-form');
    var modal = document.getElementById('documents-loading-modal');
    if (!form) return;

    var timer = null;
    var delay = 650;
    var controls = document.querySelectorAll('.grid-filters input[form="column-filter-form"], .grid-filters select[form="column-filter-form"]');

    function submitGridFilter() {
        if (modal) modal.classList.remove('is-hidden');
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }
        form.submit();
    }

    function scheduleSubmit() {
        window.clearTimeout(timer);
        timer = window.setTimeout(submitGridFilter, delay);
    }

    controls.forEach(function (control) {
        // Filtros do cabecalho consultam o servidor automaticamente para respeitar totais,
        // paginacao e busca parcial por texto, sem depender de botao Aplicar.
        control.addEventListener(control.tagName === 'SELECT' ? 'change' : 'input', scheduleSubmit);
        control.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                window.clearTimeout(timer);
                submitGridFilter();
            }
        });
    });
})();
</script>

<script>
(function () {
    var modals = {
        manifest: document.getElementById('documents-manifest-modal'),
        ignore: document.getElementById('documents-ignore-modal')
    };
    function selectedCount() {
        return document.querySelectorAll('[data-doc-checkbox]:checked').length;
    }
    function closeAll() {
        Object.keys(modals).forEach(function (key) {
            if (modals[key]) modals[key].classList.add('is-hidden');
        });
    }
    document.querySelectorAll('[data-open-action-modal]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (selectedCount() === 0) {
                alert('Selecione ao menos uma entrada.');
                return;
            }
            closeAll();
            var modal = modals[button.getAttribute('data-open-action-modal') || ''];
            if (modal) modal.classList.remove('is-hidden');
        });
    });
    document.querySelectorAll('[data-close-action-modal]').forEach(function (button) {
        button.addEventListener('click', closeAll);
    });
    Object.keys(modals).forEach(function (key) {
        var modal = modals[key];
        if (!modal) return;
        modal.addEventListener('click', function (event) {
            if (event.target === modal) closeAll();
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeAll();
    });
})();
</script>

<script>
(function () {
    var modal = document.getElementById('ignored-cfops-modal');
    if (!modal) return;
    function openModal() { modal.classList.remove('is-hidden'); }
    function closeModal() { modal.classList.add('is-hidden'); }
    document.querySelectorAll('[data-open-ignored-cfops]').forEach(function (button) {
        button.addEventListener('click', openModal);
    });
    document.querySelectorAll('[data-close-ignored-cfops]').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });
    modal.addEventListener('click', function (event) {
        if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeModal();
    });
})();
</script>

<script>
(function () {
    var modal = document.getElementById('document-danfe-modal');
    var frame = document.getElementById('document-danfe-frame');
    var currentLink = '';
    if (!modal || !frame) return;
    function closeModal() {
        modal.classList.add('is-hidden');
        frame.removeAttribute('src');
        currentLink = '';
    }
    function openModal(documentId) {
        modal.classList.remove('is-hidden');
        currentLink = new URL('?page=documents_danfe&id=' + encodeURIComponent(documentId), window.location.href).href;
        frame.src = currentLink;
    }
    document.querySelectorAll('[data-document-danfe]').forEach(function (button) {
        button.addEventListener('click', function () {
            openModal(button.getAttribute('data-document-danfe') || '');
        });
    });
    document.querySelectorAll('[data-close-document-danfe]').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });
    document.querySelectorAll('[data-print-document-danfe]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (frame.contentWindow) frame.contentWindow.print();
        });
    });
    document.querySelectorAll('[data-copy-document-danfe-link]').forEach(function (button) {
        button.addEventListener('click', async function () {
            if (!currentLink) return;
            try {
                await navigator.clipboard.writeText(currentLink);
                var original = button.textContent;
                button.textContent = 'Link copiado';
                window.setTimeout(function () { button.textContent = original; }, 1400);
            } catch (error) {
                window.prompt('Copie o link do espelho:', currentLink);
            }
        });
    });
    modal.addEventListener('click', function (event) {
        if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeModal();
    });
})();
</script>

<script>
(function () {
    var modal = document.getElementById('document-items-modal');
    var body = document.getElementById('document-items-body');
    var subtitle = document.getElementById('document-items-subtitle');
    if (!modal || !body) return;
    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char] || char;
        });
    }
    function closeModal() {
        modal.classList.add('is-hidden');
    }
    function openModal(documentId) {
        modal.classList.remove('is-hidden');
        body.innerHTML = '<tr><td colspan="11">Carregando produtos...</td></tr>';
        subtitle.textContent = '';
        fetch('?page=document_items&id=' + encodeURIComponent(documentId), {headers: {'Accept': 'application/json'}})
            .then(function (response) {
                if (!response.ok) throw new Error('Nao foi possivel carregar os produtos.');
                return response.json();
            })
            .then(function (data) {
                var doc = data.document || {};
                var location = [doc.city, doc.uf].filter(Boolean).join(' / ');
                subtitle.innerHTML = [doc.doc_type, doc.number, doc.issuer_name, doc.issue_date ? 'Emissao: ' + doc.issue_date : '', location ? 'Cidade/UF: ' + location : '', doc.total_value].filter(Boolean).map(escapeHtml).join(' | ')
                    + (doc.observation ? '<br><strong>Observacao:</strong> ' + escapeHtml(doc.observation) : '');
                var items = data.items || [];
                if (!items.length) {
                    body.innerHTML = '<tr><td colspan="11">Nenhum produto encontrado no XML desta entrada.</td></tr>';
                    return;
                }
                body.innerHTML = items.map(function (item) {
                    return '<tr>'
                        + '<td>' + escapeHtml(item.item_number) + '</td>'
                        + '<td>' + escapeHtml(item.product_code) + '</td>'
                        + '<td><strong>' + escapeHtml(item.product_name) + '</strong></td>'
                        + '<td>' + escapeHtml(item.ncm) + '</td>'
                        + '<td>' + escapeHtml(item.cfop) + '</td>'
                        + '<td>' + escapeHtml(item.city) + '</td>'
                        + '<td>' + escapeHtml(item.uf) + '</td>'
                        + '<td>' + escapeHtml(item.quantity) + '</td>'
                        + '<td>' + escapeHtml(item.unit) + '</td>'
                        + '<td>' + escapeHtml(item.unit_amount) + '</td>'
                        + '<td>' + escapeHtml(item.total_amount) + '</td>'
                        + '</tr>';
                }).join('');
            })
            .catch(function (error) {
                body.innerHTML = '<tr><td colspan="11">' + escapeHtml(error.message || 'Erro ao carregar produtos.') + '</td></tr>';
            });
    }
    document.querySelectorAll('[data-document-items]').forEach(function (button) {
        button.addEventListener('click', function () {
            openModal(button.getAttribute('data-document-items') || '');
        });
    });
    document.querySelectorAll('[data-close-document-items]').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });
    modal.addEventListener('click', function (event) {
        if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeModal();
    });
})();
</script>

<script>
(function () {
    var feedback = document.getElementById('local-export-feedback');
    function selectedIds() {
        return Array.from(document.querySelectorAll('[data-doc-checkbox]:checked')).map(function (input) { return input.value; }).filter(Boolean);
    }
    function selectedNfeCount() {
        return Array.from(document.querySelectorAll('[data-doc-checkbox]:checked')).filter(function (input) {
            var type = String(input.getAttribute('data-doc-type') || '').toUpperCase();
            return type === 'NFE' || type === 'NFCE';
        }).length;
    }
    function show(message, type) {
        if (!feedback) return;
        feedback.textContent = message;
        feedback.className = 'export-feedback ' + (type || '');
    }
    function downloadZip(scope, kind) {
        try {
            var ids = selectedIds();
            if (scope === 'selected' && ids.length === 0) throw new Error('Selecione ao menos uma entrada.');
            var params = new URLSearchParams(window.location.search);
            params.set('page', kind === 'danfe' ? 'documents_danfe_zip' : 'documents_xml_zip');
            params.set('scope', scope);
            if (scope === 'selected') params.set('ids', ids.join(','));
            show('Preparando arquivo para download...', 'info');
            window.location.href = '?' + params.toString();
        } catch (error) {
            show(error.message || 'Não foi possivel baixar o arquivo.', 'danger');
        }
    }
    document.querySelectorAll('[data-local-zip]').forEach(function (button) {
        button.addEventListener('click', function () { downloadZip(button.getAttribute('data-local-zip') || 'filtered', 'xml'); });
    });
    document.querySelectorAll('[data-danfe-zip]').forEach(function (button) {
        button.addEventListener('click', function () { downloadZip(button.getAttribute('data-danfe-zip') || 'filtered', 'danfe'); });
    });
})();
</script>
<script>
(function () {
    function money(value) {
        return value.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
    }
    function updateSelectedTotals() {
        var count = 0;
        var total = 0;
        document.querySelectorAll('[data-doc-checkbox]:checked').forEach(function (input) {
            count++;
            total += Number(input.getAttribute('data-doc-value') || 0);
        });
        document.getElementById('selected-count').textContent = String(count);
        document.getElementById('selected-value').textContent = money(total);
    }
    document.querySelectorAll('[data-doc-checkbox]').forEach(function (input) {
        input.addEventListener('change', updateSelectedTotals);
    });
    document.querySelectorAll('[data-select-all]').forEach(function (input) {
        input.addEventListener('change', function () {
            document.querySelectorAll('[data-doc-checkbox]').forEach(function (rowInput) {
                rowInput.checked = input.checked;
            });
            updateSelectedTotals();
        });
    });
})();
</script>
<script>
(function () {
    var widthKey = 'controls.documents.columnWidths';
    var table = document.querySelector('.documents-table');
    if (!table) return;
    function readWidths() {
        try { return JSON.parse(localStorage.getItem(widthKey) || '{}'); } catch (e) { return {}; }
    }
    function applyWidth(column, width) {
        document.querySelectorAll('.documents-table [data-column="' + column + '"]').forEach(function (cell) {
            cell.style.width = width + 'px';
            cell.style.minWidth = width + 'px';
            cell.style.maxWidth = 'none';
        });
    }
    var saved = readWidths();
    Object.keys(saved).forEach(function (column) { applyWidth(column, saved[column]); });
    document.querySelectorAll('.documents-table th.resizable').forEach(function (th) {
        if (th.querySelector('.column-resizer')) return;
        var handle = document.createElement('span');
        handle.className = 'column-resizer';
        handle.title = 'Arraste para redimensionar a coluna';
        th.appendChild(handle);
        handle.addEventListener('mousedown', function (event) {
            event.preventDefault();
            var column = th.getAttribute('data-column');
            var startX = event.pageX;
            var startWidth = th.offsetWidth;
            document.body.classList.add('is-resizing-column');
            function move(moveEvent) {
                var nextWidth = Math.max(72, startWidth + (moveEvent.pageX - startX));
                applyWidth(column, nextWidth);
            }
            function up() {
                document.removeEventListener('mousemove', move);
                document.removeEventListener('mouseup', up);
                document.body.classList.remove('is-resizing-column');
                var widths = readWidths();
                widths[column] = Math.round(th.getBoundingClientRect().width);
                localStorage.setItem(widthKey, JSON.stringify(widths));
            }
            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', up);
        });
    });
})();
</script>
<script>
(function () {
    var key = 'controls.documents.visibleColumns';
    var toggles = document.querySelectorAll('[data-column-toggle]');
    function readState() {
        try { return JSON.parse(localStorage.getItem(key) || '{}'); } catch (e) { return {}; }
    }
    function migrateState(state) {
        if (Object.prototype.hasOwnProperty.call(state, 'destinatario') && !Object.prototype.hasOwnProperty.call(state, 'tomador')) {
            state.tomador = true;
            delete state.destinatario;
            localStorage.setItem(key, JSON.stringify(state));
        }
        return state;
    }
    function applyColumns() {
        var state = migrateState(readState());
        toggles.forEach(function (toggle) {
            var column = toggle.getAttribute('data-column-toggle');
            var hasSaved = Object.prototype.hasOwnProperty.call(state, column);
            var defaultVisible = toggle.getAttribute('data-default-visible') !== '0';
            var visible = hasSaved ? state[column] !== false : defaultVisible;
            toggle.checked = visible;
            document.querySelectorAll('[data-column="' + column + '"]').forEach(function (cell) {
                cell.classList.toggle('is-hidden-column', !visible);
            });
        });
    }
    toggles.forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            var state = readState();
            state[toggle.getAttribute('data-column-toggle')] = toggle.checked;
            localStorage.setItem(key, JSON.stringify(state));
            applyColumns();
        });
    });
    applyColumns();
})();
</script>

<script>
(function () {
    var key = 'controls.documents.columnOrder';
    var table = document.querySelector('.documents-table');
    if (!table) return;
    function defaultOrder() {
        return Array.from(table.querySelectorAll('thead tr:first-child th[data-column]')).map(function (th) { return th.getAttribute('data-column'); });
    }
    function applyOrder(order) {
        if (!order || !order.length) return;
        table.querySelectorAll('tr').forEach(function (row) {
            var fixed = Array.from(row.children).filter(function (cell) { return !cell.hasAttribute('data-column'); });
            var byColumn = {};
            Array.from(row.children).forEach(function (cell) {
                var column = cell.getAttribute('data-column');
                if (column) byColumn[column] = cell;
            });
            fixed.forEach(function (cell) { row.appendChild(cell); });
            order.forEach(function (column) { if (byColumn[column]) row.appendChild(byColumn[column]); });
            Object.keys(byColumn).forEach(function (column) {
                if (order.indexOf(column) < 0) row.appendChild(byColumn[column]);
            });
        });
    }
    function migrateOrder(order) {
        if (!Array.isArray(order) || !order.length) return order;
        var oldIndex = order.indexOf('destinatario');
        var newIndex = order.indexOf('tomador');
        if (oldIndex >= 0) {
            order.splice(oldIndex, 1);
            if (newIndex < 0) order.splice(oldIndex, 0, 'tomador');
        } else if (newIndex < 0) {
            var issuerIndex = order.indexOf('emissor');
            if (issuerIndex >= 0) order.splice(issuerIndex + 1, 0, 'tomador');
        }
        if (order.indexOf('grupo') < 0) {
            var currentIssuerIndex = order.indexOf('emissor');
            if (currentIssuerIndex >= 0) {
                order.splice(currentIssuerIndex + 1, 0, 'grupo');
            } else {
                order.push('grupo');
            }
        }
        localStorage.setItem(key, JSON.stringify(order));
        return order;
    }
    try { applyOrder(migrateOrder(JSON.parse(localStorage.getItem(key) || '[]'))); } catch (e) {}
    table.querySelectorAll('thead tr:first-child th[data-column]').forEach(function (th) {
        th.draggable = true;
        th.title = (th.title ? th.title + ' | ' : '') + 'Arraste para reorganizar a coluna';
        th.addEventListener('dragstart', function (event) {
            if (event.target.classList.contains('column-resizer')) return;
            event.dataTransfer.setData('text/plain', th.getAttribute('data-column') || '');
        });
        th.addEventListener('dragover', function (event) { event.preventDefault(); });
        th.addEventListener('drop', function (event) {
            event.preventDefault();
            var from = event.dataTransfer.getData('text/plain');
            var to = th.getAttribute('data-column');
            if (!from || !to || from === to) return;
            var order = defaultOrder();
            order.splice(order.indexOf(from), 1);
            order.splice(order.indexOf(to), 0, from);
            localStorage.setItem(key, JSON.stringify(order));
            applyOrder(order);
        });
    });
})();
</script>
<?php include __DIR__ . '/layout_bottom.php'; ?>
