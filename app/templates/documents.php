<?php include __DIR__ . '/layout_top.php'; ?>
<?php
$filters = $documentFilters ?? [];
$totals = $documentTotals ?? ['total' => 0, 'total_value' => 0];
$currentPage = (int)($documentPage ?? 1);
$perPage = (int)($documentPerPage ?? 200);
$totalPages = max(1, (int)ceil(($totals['total'] ?? 0) / max(1, $perPage)));
$baseQuery = array_filter($filters, static fn($value) => $value !== '' && $value !== null);
$baseQuery['page'] = 'documents';
$exportQuery = $baseQuery;
$exportQuery['page'] = 'documents_export';
$documentFilterKeys = [
    'company_id','doc_type','status','manifestation_status','posted_to_erp','without_referenced_nfe','cte_taker_only','ignore_cfops','entry_only','date_start','date_end',
    'company_q','number_q','issuer_q','recipient_q','access_key_q','referenced_nfe_q','product_q','cfop_q','source_q','q','sort_by','sort_dir',
];
?>
<div class="page-header split-header documents-page-header">
    <div>
        <h1>Entradas</h1>
        <p>Conferência operacional de NF-e e CT-e importados, com filtros, DANFE e exportação dos XMLs.</p>
    </div>
</div>

<form method="get" class="card card-compact documents-filter">
    <input type="hidden" name="page" value="documents">
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
        <label>Produto
            <input type="text" name="product_q" placeholder="Descricao do produto" value="<?= h((string)($filters['product_q'] ?? '')) ?>">
        </label>
        <label>CFOP
            <input type="text" name="cfop_q" placeholder="CFOP do item" value="<?= h((string)($filters['cfop_q'] ?? '')) ?>">
        </label>
        <label class="cfop-ignore-field">CFOPs ignorados
            <input type="hidden" name="ignore_cfops" value="0">
            <span class="cfop-ignore-line">
                <input type="checkbox" name="ignore_cfops" value="1" <?= ((string)($filters['ignore_cfops'] ?? '1') !== '0') ? 'checked' : '' ?>>
                <button class="cfop-ignore-link" type="button" data-open-ignored-cfops>Ignorar CFOPs cadastrados</button>
            </span>
        </label>
        <label>Empresa
            <select name="company_id">
                <option value="">Todos os CNPJs</option>
                <?php foreach (($companies ?? []) as $co): ?>
                    <option value="<?= h((string)$co['id']) ?>" <?= (($filters['company_id'] ?? '') == (string)$co['id']) ? 'selected' : '' ?>><?= h($co['company_name']) ?> - <?= h($co['cnpj']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Tipo
            <select name="doc_type">
                <option value="">NF-e e CT-e</option>
                <?php foreach (['NFE' => 'NF-e', 'CTE' => 'CT-e'] as $type => $label): ?>
                    <option value="<?= h($type) ?>" <?= (($filters['doc_type'] ?? '') === $type) ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Status
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (['xml_completo','apenas_resumo','cancelado','denegado','pendente_manifestacao','aguardando_novo_download','ja_existente','erro'] as $status): ?>
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
        <a class="button-link button-compact" href="<?= h(base_url('?page=documents')) ?>">Limpar</a>
        <button class="primary">Filtrar entradas</button>
    </div>
    <div id="columns-panel" class="columns-panel">
        <?php foreach ([
            'empresa' => 'Empresa',
            'tipo' => 'Tipo',
            'numero' => 'Número',
            'emissor' => 'Emissor',
            'destinatario' => 'Destinatario',
            'chave' => 'Chave',
            'nfe_vinculada' => 'NF-e vinculada',
            'erp' => 'Nota lançada no ERP',
            'eventos_informativos' => 'Eventos informativos',
            'emissao' => 'Emissão',
            'valor' => 'Valor',
            'status' => 'Status',
            'manifestacao' => 'Manifestacao',
            'origem' => 'Origem',
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

<form method="post" class="card documents-card">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <?php foreach ($documentFilterKeys as $filterKey): ?>
        <input type="hidden" name="<?= h($filterKey) ?>" value="<?= h((string)($filters[$filterKey] ?? '')) ?>">
    <?php endforeach; ?>
    <div class="grid-toolbar documents-grid-toolbar"><div><h2>Grid de entradas</h2><small>Exportação respeita todos os filtros aplicados.</small></div></div>
    <div class="toolbar">
        <div class="inline">
            <select name="manifest_type">
                <option value="science">Ciencia da Operacao</option>
                <option value="confirm">Confirmacao da Operacao</option>
                <option value="unknown">Desconhecimento da Operacao</option>
                <option value="not_realized">Operacao nao Realizada</option>
            </select>
            <input type="text" name="manifest_justification" placeholder="Justificativa quando exigida">
            <button class="primary" name="bulk_manifest" value="1">Manifestar selecionados</button>
        </div>
    </div>
    <div class="export-panel is-collapsed compact-export-panel" id="documents-export-panel">
        <div class="inline export-panel-actions">
            <a class="button-link button-compact" href="<?= h(base_url('?' . http_build_query($exportQuery))) ?>">Exportar Excel</a>
            <button class="button-compact" type="button" data-local-zip="filtered">Baixar Zip todos os filtrados XML</button>
            <button class="button-compact" type="button" data-local-zip="selected">Baixar os selecionados XML</button>
            <button class="button-compact" type="button" data-danfe-zip="filtered">Baixar Zip todos os filtrados DANFE</button>
            <button class="button-compact" type="button" data-danfe-zip="selected">Baixar os selecionados DANFE</button>
        </div>
        <div id="local-export-feedback" class="export-feedback" aria-live="polite"></div>
        <small>As exportacoes respeitam filtros ou seleção atual. O navegador permite escolher a pasta conforme a configuração de downloads.</small>
    </div>

    <div class="table-wrap documents-table-wrap">
        <table class="table documents-table">
            <thead>
                <tr>
                    <th class="select-col"><input type="checkbox" data-select-all></th>
                    <th class="resizable" data-column="empresa">Empresa</th>
                    <th class="resizable" data-column="tipo">Tipo</th>
                    <th class="resizable" data-column="numero">Número</th>
                    <th class="resizable" data-column="emissor">Emissor</th>
                    <th class="resizable" data-column="destinatario">Destinatario</th>
                    <th class="resizable" data-column="chave">Chave</th>
                    <th class="resizable" data-column="nfe_vinculada">NF-e vinculada</th>
                    <th class="resizable" data-column="erp">Nota lançada no ERP</th>
                    <th class="resizable" data-column="eventos_informativos">Eventos informativos</th>
                    <th class="resizable" data-column="emissao">Emissão</th>
                    <th class="resizable" data-column="valor">Valor</th>
                    <th class="resizable" data-column="status">Status</th>
                    <th class="resizable" data-column="manifestacao">Manifestacao</th>
                    <th class="resizable" data-column="origem">Origem</th>
                    <th class="resizable actions-col" data-column="acoes">Acoes</th>
                </tr>
                <tr class="grid-filters">
                    <th></th>
                    <th data-column="empresa"><input form="column-filter-form" name="company_q" value="<?= h((string)($filters['company_q'] ?? '')) ?>" placeholder="Filtrar"></th>
                    <th data-column="tipo"></th>
                    <th data-column="numero"><input form="column-filter-form" name="number_q" value="<?= h((string)($filters['number_q'] ?? '')) ?>" placeholder="Filtrar"></th>
                    <th data-column="emissor"><input form="column-filter-form" name="issuer_q" value="<?= h((string)($filters['issuer_q'] ?? '')) ?>" placeholder="Filtrar"></th>
                    <th data-column="destinatario"><input form="column-filter-form" name="recipient_q" value="<?= h((string)($filters['recipient_q'] ?? '')) ?>" placeholder="Filtrar"></th>
                    <th data-column="chave"><input form="column-filter-form" name="access_key_q" value="<?= h((string)($filters['access_key_q'] ?? '')) ?>" placeholder="Filtrar"></th>
                    <th data-column="nfe_vinculada"><input form="column-filter-form" name="referenced_nfe_q" value="<?= h((string)($filters['referenced_nfe_q'] ?? '')) ?>" placeholder="Filtrar"></th>
                    <th data-column="erp"></th>
                    <th data-column="eventos_informativos"></th>
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
                    <th data-column="acoes"><button form="column-filter-form" class="button-compact">Aplicar</button></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($documents as $doc): ?>
                <tr>
                    <td><input type="checkbox" name="ids[]" value="<?= h((string)$doc['id']) ?>" data-doc-checkbox data-doc-value="<?= h((string)((float)($doc['total_value'] ?? 0))) ?>"></td>
                    <td data-column="empresa"><strong><?= h((string)$doc['company_name']) ?></strong><br><small><?= h((string)$doc['company_cnpj']) ?></small></td>
                    <td data-column="tipo"><span class="pill"><?= h((string)$doc['doc_type']) ?></span></td>
                    <td data-column="numero"><button type="button" class="link-button doc-products-link" data-document-items="<?= h((string)$doc['id']) ?>"><?= h((string)$doc['number']) ?></button></td>
                    <td data-column="emissor"><strong><?= h((string)$doc['issuer_name']) ?></strong><br><small><?= h((string)$doc['issuer_cnpj']) ?></small></td>
                    <td data-column="destinatario"><strong><?= h((string)($doc['recipient_name'] ?? '')) ?></strong><br><small><?= h((string)($doc['recipient_cnpj'] ?? '')) ?></small></td>
                    <td data-column="chave"><small><?= h((string)$doc['access_key']) ?></small></td>
                    <td data-column="nfe_vinculada"><small><?= h((string)($doc['referenced_nfe_keys'] ?? '')) ?></small></td>
                    <td data-column="erp"><?= !empty($doc['posted_to_erp']) ? 'Sim' : 'Não' ?></td>
                    <td data-column="eventos_informativos">
                        <?php if ((int)($doc['informative_events_count'] ?? 0) > 0): ?>
                            <strong><?= h((string)$doc['informative_events_count']) ?></strong><br>
                            <small><?= h((string)($doc['informative_events_names'] ?? '')) ?></small>
                        <?php else: ?>
                            <small>-</small>
                        <?php endif; ?>
                    </td>
                    <td data-column="emissao"><?= h(format_date($doc['issue_date'])) ?></td>
                    <td data-column="valor"><?= h(format_money((float)$doc['total_value'])) ?></td>
                    <td data-column="status"><?= h(document_status_label((string)$doc['status'])) ?></td>
                    <td data-column="manifestacao"><?= h(manifestation_status_label((string)$doc['manifestation_status'])) ?></td>
                    <td data-column="origem"><?= h((string)$doc['source']) ?></td>
                    <td data-column="acoes" class="row-actions">
                        <a class="row-action" target="_blank" href="<?= h(base_url('?page=view_xml&id=' . $doc['id'])) ?>">XML</a>
                        <button type="button" class="row-action row-action-button" data-document-items="<?= h((string)$doc['id']) ?>">Produtos</button>
                        <?php if ((string)($doc['status'] ?? '') !== 'apenas_resumo'): ?><a class="row-action" target="_blank" href="<?= h(base_url('?page=documents_danfe&id=' . $doc['id'])) ?>">Espelho DANFE</a><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$documents): ?>
                <tr><td colspan="16">Nenhuma entrada encontrada.</td></tr>
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
</form>

<div class="modal-backdrop ignored-cfops-modal is-hidden" id="ignored-cfops-modal" role="dialog" aria-modal="true" aria-labelledby="ignored-cfops-title">
    <div class="modal-panel">
        <div class="modal-header">
            <div>
                <h2 id="ignored-cfops-title">CFOPs ignorados em Entradas</h2>
                <small>Nao mostrar documentos com os CFOPs informados, pois nao realizamos a escrituracao Fiscal desses documentos.</small>
            </div>
            <button type="button" class="modal-close" data-close-ignored-cfops>&times;</button>
        </div>
        <form method="post" class="ignored-cfop-form">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
            <?php foreach ($documentFilterKeys as $filterKey): ?>
                <input type="hidden" name="<?= h($filterKey) ?>" value="<?= h((string)($filters[$filterKey] ?? '')) ?>">
            <?php endforeach; ?>
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
                                <?php foreach ($documentFilterKeys as $filterKey): ?>
                                    <input type="hidden" name="<?= h($filterKey) ?>" value="<?= h((string)($filters[$filterKey] ?? '')) ?>">
                                <?php endforeach; ?>
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
                        <th>Qtd</th>
                        <th>Un</th>
                        <th>Unitario</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody id="document-items-body">
                    <tr><td colspan="9">Carregando produtos...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<form id="column-filter-form" method="get">
    <input type="hidden" name="page" value="documents">
    <input type="hidden" name="q" value="<?= h((string)($filters['q'] ?? '')) ?>">
    <input type="hidden" name="company_id" value="<?= h((string)($filters['company_id'] ?? '')) ?>">
    <input type="hidden" name="doc_type" value="<?= h((string)($filters['doc_type'] ?? '')) ?>">
    <input type="hidden" name="status" value="<?= h((string)($filters['status'] ?? '')) ?>">
    <input type="hidden" name="posted_to_erp" value="<?= h((string)($filters['posted_to_erp'] ?? '')) ?>">
    <input type="hidden" name="without_referenced_nfe" value="<?= h((string)($filters['without_referenced_nfe'] ?? '')) ?>">
    <input type="hidden" name="cte_taker_only" value="<?= h((string)($filters['cte_taker_only'] ?? '')) ?>">
    <input type="hidden" name="ignore_cfops" value="<?= h((string)($filters['ignore_cfops'] ?? '1')) ?>">
    <input type="hidden" name="date_start" value="<?= h((string)($filters['date_start'] ?? '')) ?>">
    <input type="hidden" name="date_end" value="<?= h((string)($filters['date_end'] ?? '')) ?>">
    <input type="hidden" name="product_q" value="<?= h((string)($filters['product_q'] ?? '')) ?>">
    <input type="hidden" name="cfop_q" value="<?= h((string)($filters['cfop_q'] ?? '')) ?>">
    <!-- O campo recipient_q vem do input visivel do grid; duplicar como hidden sobrescrevia o valor digitado em Destinatario. -->
    <input type="hidden" name="sort_by" value="<?= h((string)($filters['sort_by'] ?? 'issue_date')) ?>">
    <input type="hidden" name="sort_dir" value="<?= h((string)($filters['sort_dir'] ?? 'desc')) ?>">
</form>

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
        body.innerHTML = '<tr><td colspan="9">Carregando produtos...</td></tr>';
        subtitle.textContent = '';
        fetch('?page=document_items&id=' + encodeURIComponent(documentId), {headers: {'Accept': 'application/json'}})
            .then(function (response) {
                if (!response.ok) throw new Error('Nao foi possivel carregar os produtos.');
                return response.json();
            })
            .then(function (data) {
                var doc = data.document || {};
                subtitle.textContent = [doc.doc_type, doc.number, doc.issuer_name, doc.total_value].filter(Boolean).join(' | ');
                var items = data.items || [];
                if (!items.length) {
                    body.innerHTML = '<tr><td colspan="9">Nenhum produto encontrado no XML desta entrada.</td></tr>';
                    return;
                }
                body.innerHTML = items.map(function (item) {
                    return '<tr>'
                        + '<td>' + escapeHtml(item.item_number) + '</td>'
                        + '<td>' + escapeHtml(item.product_code) + '</td>'
                        + '<td><strong>' + escapeHtml(item.product_name) + '</strong></td>'
                        + '<td>' + escapeHtml(item.ncm) + '</td>'
                        + '<td>' + escapeHtml(item.cfop) + '</td>'
                        + '<td>' + escapeHtml(item.quantity) + '</td>'
                        + '<td>' + escapeHtml(item.unit) + '</td>'
                        + '<td>' + escapeHtml(item.unit_amount) + '</td>'
                        + '<td>' + escapeHtml(item.total_amount) + '</td>'
                        + '</tr>';
                }).join('');
            })
            .catch(function (error) {
                body.innerHTML = '<tr><td colspan="9">' + escapeHtml(error.message || 'Erro ao carregar produtos.') + '</td></tr>';
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
    function applyColumns() {
        var state = readState();
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
        });
    }
    try { applyOrder(JSON.parse(localStorage.getItem(key) || '[]')); } catch (e) {}
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
