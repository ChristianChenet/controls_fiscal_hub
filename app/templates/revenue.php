<?php include __DIR__ . '/layout_top.php'; ?>
<?php
$filters = $revenueFilters ?? [];
$totals = $revenueTotals ?? [];
$dash = $revenueDashboard ?? [];
$activeTab = $revenueTab ?? 'dashboard';
$canViewCost = !empty($canViewCost);
$totalPages = max(1, (int)ceil(((int)($totals['documents_count'] ?? 0)) / max(1, (int)$revenuePerPage)));
$baseQuery = array_filter($filters, static fn($value) => $value !== '' && $value !== null);
$baseQuery['page'] = 'revenue';
$showPreviousMonth = (string)($filters['show_previous_month'] ?? '1') === '1';

// A rotina usa um unico contexto global de filtros; todos os links mantem esse contexto.
$tabUrl = static function (string $tab) use ($baseQuery): string {
    return base_url('?' . http_build_query($baseQuery + ['tab' => $tab]));
};
$docUrl = static function (int $id, string $tab = 'detail') use ($baseQuery): string {
    return base_url('?' . http_build_query($baseQuery + ['page' => 'revenue', 'tab' => $tab, 'id' => $id]));
};
$exportUrl = static function (string $grid) use ($baseQuery): string {
    return base_url('?' . http_build_query(array_merge($baseQuery, ['page' => 'revenue_export', 'grid' => $grid])));
};
$hint = static function (string $text): string {
    return '<span class="hint" title="' . h($text) . '">?</span>';
};
$taxFields = [
    'icms_amount' => 'ICMS',
    'pis_amount' => 'PIS',
    'cofins_amount' => 'COFINS',
    'ipi_amount' => 'IPI',
    'iss_amount' => 'ISS',
    'st_amount' => 'ST',
    'ibs_amount' => 'IBS',
    'cbs_amount' => 'CBS',
    'difal_amount' => 'DIFAL',
    'other_taxes_amount' => 'Outros',
];
$breakdownHtml = static function (?array $breakdown) use ($canViewCost): string {
    if (!$breakdown) { return ''; }
    $resale = '<span>Revenda: ' . h(format_money((float)($breakdown['resale'] ?? 0)));
    $services = '<span>Serviços: ' . h(format_money((float)($breakdown['services'] ?? 0)));
    if ($canViewCost && array_key_exists('cost_resale', $breakdown)) {
        $resale .= '<em>Custo: ' . h(format_money((float)($breakdown['cost_resale'] ?? 0))) . '</em>';
        $services .= '<em>Custo: ' . h(format_money((float)($breakdown['cost_services'] ?? 0))) . '</em>';
    }
    return '<small class="stat-breakdown">' . $resale . '</span>' . $services . '</span></small>';
};
$gridCard = static function (string $label, float $value, string $class = 'ok', ?array $breakdown = null) use ($breakdownHtml): string {
    return '<div class="card stat revenue-stat ' . h($class) . '"><strong>' . h(format_money($value)) . '</strong><span>' . h($label) . '</span>' . $breakdownHtml($breakdown) . '</div>';
};
$storeChart = static function (string $title, array $rows) use ($breakdownHtml): void {
    $max = max(1, ...array_map(static fn($row) => abs((float)($row['net_amount'] ?? 0)), $rows ?: [['net_amount' => 1]]));
    $visibleRows = array_slice($rows, 0, 10);
    $totalRow = [
        'net_amount' => array_sum(array_map(static fn($row) => (float)($row['net_amount'] ?? 0), $visibleRows)),
        'resale' => array_sum(array_map(static fn($row) => (float)($row['resale'] ?? 0), $visibleRows)),
        'services' => array_sum(array_map(static fn($row) => (float)($row['services'] ?? 0), $visibleRows)),
        'cost_resale' => array_sum(array_map(static fn($row) => (float)($row['cost_resale'] ?? 0), $visibleRows)),
        'cost_services' => array_sum(array_map(static fn($row) => (float)($row['cost_services'] ?? 0), $visibleRows)),
    ];
    ?>
    <section class="card revenue-store-chart">
        <h2><?= h($title) ?></h2>

        <?php foreach ($visibleRows as $row): ?>
            <?php $pct = max(4, (int)((abs((float)($row['net_amount'] ?? 0)) / $max) * 100)); ?>
            <div class="bar-row">
                <div><strong><?= h((string)($row['label'] ?? '-')) ?></strong><small><?= h((string)($row['extra'] ?? '')) ?></small></div>
                <span><i style="width:<?= h((string)$pct) ?>%"></i></span>
                <b><?= h(format_money((float)($row['net_amount'] ?? 0))) ?><?= $breakdownHtml(['resale' => (float)($row['resale'] ?? 0), 'services' => (float)($row['services'] ?? 0), 'cost_resale' => (float)($row['cost_resale'] ?? 0), 'cost_services' => (float)($row['cost_services'] ?? 0)]) ?></b>
            </div>
        <?php endforeach; ?>
        <?php if ($visibleRows): ?>
            <div class="bar-row store-total-row">
                <div><strong>Total</strong><small>Lojas exibidas</small></div>
                <span><i style="width:100%"></i></span>
                <b><?= h(format_money((float)$totalRow['net_amount'])) ?><?= $breakdownHtml($totalRow) ?></b>
            </div>
        <?php endif; ?>
        <?php if (!$rows): ?><p class="empty-state">Sem dados no filtro atual.</p><?php endif; ?>
    </section>
    <?php
};
?>

<div class="page-header split-header is-collapsed">
    <div>
        <h1>Faturamento</h1>
        <p>Análise gerencial e fiscal de vendas e devoluções integradas do ERP.</p>
    </div>
</div>

<form method="get" class="card card-compact revenue-filter">
    <input type="hidden" name="page" value="revenue">
    <input type="hidden" name="tab" value="<?= h($activeTab) ?>">
    <div class="filter-heading">
        <div>
            <strong>Filtros globais</strong>
            <small>Todos os filtros abaixo afetam cards, gráficos, grids, totais e detalhes.</small>
        </div>
        <button class="button-link" type="button" data-collapse-target="#revenue-all-filters" data-collapse-key="controls.revenue.filters.collapsed" data-hide-label="Recolher filtros" data-show-label="Mostrar filtros">Recolher filtros</button>
    </div>

    <div id="revenue-all-filters" class="revenue-filter-body">
        <div class="form-row revenue-filter-main">
            <label>Data inicial
                <input type="date" name="date_start" value="<?= h((string)($filters['date_start'] ?? '')) ?>">
            </label>
            <label>Data final
                <input type="date" name="date_end" value="<?= h((string)($filters['date_end'] ?? '')) ?>">
            </label>
            <label>Venda / Devolução
                <select name="sale_return">
                    <option value="">Tudo</option>
                    <option value="venda" <?= (($filters['sale_return'] ?? '') === 'venda') ? 'selected' : '' ?>>Venda</option>
                    <option value="devolucao" <?= (($filters['sale_return'] ?? '') === 'devolucao') ? 'selected' : '' ?>>Devolução</option>
                </select>
            </label>
            <label>Tipo de documento
                <select name="document_type">
                    <option value="">Todos</option>
                    <?php foreach (['NFE' => 'NF-e', 'NFCE' => 'NFC-e', 'NFSE' => 'NFS-e', 'DEVOLUCAO_NFE' => 'Devolução NF-e'] as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= (($filters['document_type'] ?? '') === $value) ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Situação
                <select name="document_status">
                    <option value="">Todas</option>
                    <?php foreach (['autorizado','cancelado','denegado','inutilizado','rejeitado'] as $status): ?>
                        <option value="<?= h($status) ?>" <?= (($filters['document_status'] ?? '') === $status) ? 'selected' : '' ?>><?= h(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Loja de emissão <?= $hint('CNPJ responsável pela emissão fiscal e pelo certificado digital.') ?>
                <select name="issuing_store_cnpj">
                    <option value="">Todas</option>
                    <?php foreach (($revenueOptions['issuingStores'] ?? []) as $store): ?>
                        <option value="<?= h((string)$store['cnpj']) ?>" <?= (($filters['issuing_store_cnpj'] ?? '') === (string)$store['cnpj']) ? 'selected' : '' ?>><?= h((string)$store['name']) ?> - <?= h((string)$store['cnpj']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Loja do pedido <?= $hint('Loja onde a venda nasceu comercialmente, mesmo que a emissão ocorra em outro CNPJ.') ?>
                <select name="order_store_name">
                    <option value="">Todas</option>
                    <?php foreach (($revenueOptions['orderStores'] ?? []) as $store): ?>
                        <option value="<?= h((string)$store['name']) ?>" <?= (($filters['order_store_name'] ?? '') === (string)$store['name']) ? 'selected' : '' ?>><?= h((string)$store['name']) ?> - <?= h((string)$store['cnpj']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Cliente
                <input type="text" name="customer_name" value="<?= h((string)($filters['customer_name'] ?? '')) ?>" placeholder="Nome do cliente">
            </label>
            <label>CPF/CNPJ cliente
                <input type="text" name="customer_document" value="<?= h((string)($filters['customer_document'] ?? '')) ?>">
            </label>
            <label>Vendedor
                <input type="text" name="seller_name" value="<?= h((string)($filters['seller_name'] ?? '')) ?>">
            </label>
            <label>Número
                <input type="text" name="number" value="<?= h((string)($filters['number'] ?? '')) ?>">
            </label>
            <label>Vínculo do pedido
                <select name="order_link">
                    <option value="">Todos</option>
                    <option value="with" <?= (($filters['order_link'] ?? '') === 'with') ? 'selected' : '' ?>>Somente com pedido</option>
                    <option value="without" <?= (($filters['order_link'] ?? '') === 'without') ? 'selected' : '' ?>>Somente sem pedido</option>
                </select>
            </label>            <label>Pedido
                <input type="text" name="order_number" value="<?= h((string)($filters['order_number'] ?? '')) ?>" placeholder="Código ou número do pedido">
            </label>
            <label>Série
                <input type="text" name="series" value="<?= h((string)($filters['series'] ?? '')) ?>">
            </label>
            <label>Chave de acesso
                <input type="text" name="access_key" value="<?= h((string)($filters['access_key'] ?? '')) ?>">
            </label>
            <label>Produto
                <input type="text" name="product" value="<?= h((string)($filters['product'] ?? '')) ?>">
            </label>
            <label>Grupo de produto
                <input type="text" name="product_group" value="<?= h((string)($filters['product_group'] ?? '')) ?>">
            </label>
            <label>CFOP
                <input type="text" name="cfop" value="<?= h((string)($filters['cfop'] ?? '')) ?>">
            </label>
            <label>NCM
                <input type="text" name="ncm" value="<?= h((string)($filters['ncm'] ?? '')) ?>">
            </label>
            <label>CST/CSOSN
                <input type="text" name="cst_csosn" value="<?= h((string)($filters['cst_csosn'] ?? '')) ?>">
            </label>
            <label>XML disponível
                <select name="xml_available">
                    <option value="">Todos</option>
                    <option value="1" <?= (($filters['xml_available'] ?? '') === '1') ? 'selected' : '' ?>>Sim</option>
                    <option value="0" <?= (($filters['xml_available'] ?? '') === '0') ? 'selected' : '' ?>>Não</option>
                </select>
            </label>
            <label>Valor mínimo
                <input type="text" name="amount_min" value="<?= h((string)($filters['amount_min'] ?? '')) ?>">
            </label>
            <label>Valor máximo
                <input type="text" name="amount_max" value="<?= h((string)($filters['amount_max'] ?? '')) ?>">
            </label>
        </div>

        <div class="revenue-filter-options">
            <input type="hidden" name="include_returns" value="0">
            <label class="checkbox-inline"><input type="checkbox" name="include_returns" value="1" <?= !empty($filters['include_returns']) ? 'checked' : '' ?>> Considerar devoluções</label>
            <input type="hidden" name="include_tax_credits" value="0">
            <label class="checkbox-inline"><input type="checkbox" name="include_tax_credits" value="1" <?= !empty($filters['include_tax_credits']) ? 'checked' : '' ?>> Considerar créditos tributários</label>
            <input type="hidden" name="show_previous_month" value="0">
            <label class="checkbox-inline"><input type="checkbox" name="show_previous_month" value="1" <?= $showPreviousMonth ? 'checked' : '' ?>> Apresentar resumo <?= $hint('Mostra os cards de total faturado no dia, semana, mês e mês anterior.') ?></label>
        </div>
    </div>

    <div class="form-actions">
        <a class="button-link" href="<?= h(base_url('?page=revenue')) ?>">Limpar</a>
        <button class="primary">Atualizar dashboard</button>
    </div>
</form>

<nav class="module-tabs">
    <?php foreach ([
        'dashboard' => 'Visão Geral',
        'documents' => 'Documentos Fiscais',
        'detail' => 'Detalhamento Fiscal',
        'taxes' => 'Análise Tributária',
        'items' => 'Itens / Produtos',
    ] as $tab => $label): ?>
        <a class="<?= $activeTab === $tab ? 'active' : '' ?>" href="<?= h($tabUrl($tab)) ?>"><?= h($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($activeTab === 'dashboard'): ?>
    <?php if ($showPreviousMonth): ?>
    <p class="info-note">Total faturado no dia, semana, mês e mês anterior consideram venda menos devolução, respeitando os filtros globais aplicados.</p>
    <div class="grid revenue-top-kpis <?= $showPreviousMonth ? 'with-previous' : 'without-previous' ?>">
        <?= $gridCard('Total faturado no dia', (float)($dash['today'] ?? 0), 'ok', $dash['todayBreakdown'] ?? null) ?>
        <?= $gridCard('Total faturado na semana', (float)($dash['week'] ?? 0), 'ok', $dash['weekBreakdown'] ?? null) ?>
        <?= $gridCard('Total faturado no Mês', (float)($dash['month'] ?? 0), 'ok', $dash['monthBreakdown'] ?? null) ?>
        <?= $gridCard('Total faturado no Mês Anterior', (float)($dash['previousMonth'] ?? 0), 'neutral', $dash['previousMonthBreakdown'] ?? null) ?>
    </div>
    <?php endif; ?>

    <section class="card revenue-period-card">
        <div class="section-title">
            <div>
                <h2>Faturamento do Período</h2>
                <p>Resumo líquido do período filtrado, com devoluções estornando faturamento e impostos.</p>
            </div>
        </div>
        <div class="grid revenue-period-main">
            <div class="metric-box"><small>Bruto <?= $hint('Somente vendas do período filtrado.') ?></small><strong><?= h(format_money((float)($totals['gross_amount'] ?? 0))) ?></strong><?= $breakdownHtml($dash['periodBreakdowns']['gross_amount'] ?? null) ?></div>
            <div class="metric-box negative"><small>Devolução (negativo) <?= $hint('Valor de devoluções apresentado como estorno do faturamento.') ?></small><strong>-<?= h(format_money((float)($totals['return_amount'] ?? 0))) ?></strong><?= $breakdownHtml($dash['periodBreakdowns']['return_amount'] ?? null) ?></div>
            <div class="metric-box ok"><small>Líquido <?= $hint('Bruto menos devoluções, usando o valor líquido integrado.') ?></small><strong><?= h(format_money((float)($totals['net_amount'] ?? 0))) ?></strong><?= $breakdownHtml($dash['periodBreakdowns']['net_amount'] ?? null) ?></div>
        </div>
        <div class="grid revenue-period-tax">
            <div class="metric-box"><small>Saldo tributário <?= $hint('Impostos menos créditos, com devoluções estornando impostos.') ?></small><strong><?= h(format_money((float)($totals['tax_balance'] ?? 0))) ?></strong></div>
            <div class="metric-box"><small>Emitidos <?= $hint('Quantidade de documentos fiscais dentro do contexto filtrado.') ?></small><strong><?= h((string)($totals['documents_count'] ?? 0)) ?></strong></div>
            <div class="metric-box"><small>Ticket Médio (vendas) <?= $hint('Média calculada sobre vendas, sem dividir por devoluções.') ?></small><strong><?= h(format_money((float)($totals['average_ticket'] ?? 0))) ?></strong><?= $breakdownHtml($dash['periodBreakdowns']['average_ticket'] ?? null) ?></div>
            <div class="metric-box"><small>Impostos <?= $hint('Total de impostos com devoluções estornadas.') ?></small><strong><?= h(format_money((float)($totals['taxes_amount'] ?? 0))) ?></strong><?= $breakdownHtml($dash['periodBreakdowns']['taxes_amount'] ?? null) ?></div>
            <div class="metric-box"><small>Créditos <?= $hint('Créditos tributários integrados do ERP e respeitando o filtro aplicado.') ?></small><strong><?= h(format_money((float)($totals['tax_credits_amount'] ?? 0))) ?></strong></div>
        </div>
    </section>

    <?php $daily = $dash['dailyEvolution'] ?? []; $maxDaily = max(1, ...array_map(static fn($r) => abs((float)($r['net_amount'] ?? 0)), $daily ?: [['net_amount' => 1]])); ?>
    <section class="card">
        <div class="section-title">
            <div>
                <h2>Evolução diária</h2>
                <p>Comparativo entre vendas, devoluções e líquido no período filtrado.</p>
            </div>
            <div class="chart-legend"><span class="legend-sale">Venda</span><span class="legend-return">Devolução</span><span class="legend-net">Líquido</span></div>
        </div>
        <div class="revenue-evolution">
            <?php foreach ($daily as $row): ?>
                <?php
                $saleHeight = max(4, (int)((abs((float)$row['gross_amount']) / $maxDaily) * 120));
                $returnHeight = max(0, (int)((abs((float)$row['return_amount']) / $maxDaily) * 120));
                $netHeight = max(4, (int)((abs((float)$row['net_amount']) / $maxDaily) * 120));
                ?>
                <div class="revenue-day" title="<?= h(format_date_short($row['issue_date'])) ?> venda <?= h(format_money((float)$row['gross_amount'])) ?> | devolução <?= h(format_money((float)$row['return_amount'])) ?> | líquido <?= h(format_money((float)$row['net_amount'])) ?>">
                    <span class="daily-bars">
                        <i class="sale" style="height:<?= h((string)$saleHeight) ?>px"></i>
                        <i class="return" style="height:<?= h((string)$returnHeight) ?>px"></i>
                        <i class="net" style="height:<?= h((string)$netHeight) ?>px"></i>
                    </span>
                    <small><?= h((new DateTimeImmutable((string)$row['issue_date']))->format('d/m')) ?></small>
                </div>
            <?php endforeach; ?>
            <?php if (!$daily): ?><p class="empty-state">Nenhum dado integrado para o filtro selecionado.</p><?php endif; ?>
        </div>
    </section>

    <div class="grid two revenue-store-charts">
        <?php $storeChart('Faturamento por loja do pedido', $dash['byOrderStore'] ?? []); ?>
        <?php $storeChart('Faturamento por loja de emissão', $dash['byIssuingStore'] ?? []); ?>
    </div>

    <div class="grid three revenue-analytics">
        <?php foreach ([['Faturamento por CFOP', $dash['byCfop'] ?? []], ['Loja de emissão', $dash['byIssuingStore'] ?? []], ['Loja do pedido', $dash['byOrderStore'] ?? []], ['Vendedor', $dash['bySeller'] ?? []], ['Top 20 clientes', $dash['topCustomers'] ?? []], ['Top 20 produtos', $dash['topProducts'] ?? []]] as [$title, $rows]): ?>
            <section class="card compact-rank-card">
                <h2><?= h($title) ?></h2>
                <?php if ($title === 'Faturamento por CFOP'): ?><small class="panel-rule">Vendas somam e devolucoes reduzem o total por CFOP.</small><?php endif; ?>
                <?php $panelTotal = array_sum(array_map(static fn($row) => (float)($row['net_amount'] ?? $row['total_amount'] ?? 0), $rows)); ?>
                <div class="rank-total"><span>Total</span><strong><?= h(format_money((float)$panelTotal)) ?></strong></div>
                <div class="table-wrap"><table class="table ranking-table">
                    <thead><tr><th>Nome</th><th>Qtd</th><th>Valor</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr><td><?= h((string)($row['label'] ?? '-')) ?><br><small><?= h((string)($row['extra'] ?? '')) ?></small></td><td><?= h((string)($row['total'] ?? 0)) ?></td><td><?= h(format_money((float)($row['net_amount'] ?? $row['total_amount'] ?? 0))) ?><?php if ($title === 'Faturamento por CFOP' || $title === 'Loja de emissão' || $title === 'Loja do pedido'): ?><?= $breakdownHtml(['resale' => (float)($row['resale'] ?? 0), 'services' => (float)($row['services'] ?? 0), 'cost_resale' => (float)($row['cost_resale'] ?? 0), 'cost_services' => (float)($row['cost_services'] ?? 0)]) ?><?php elseif ($canViewCost && array_key_exists('cost_amount', $row)): ?><small class="stat-breakdown"><span>Custo: <?= h(format_money((float)($row['cost_amount'] ?? 0))) ?></span></small><?php endif; ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr><td colspan="3">Sem dados no filtro.</td></tr><?php endif; ?>
                    </tbody>
                </table></div>
            </section>
        <?php endforeach; ?>
    </div>
<?php elseif ($activeTab === 'documents'): ?>
    <section class="card documents-card">
        <div class="grid-toolbar">
            <div><h2>Documentos Fiscais</h2><small>Filtros locais atuam somente nas linhas carregadas desta página.</small></div>
            <div class="inline"><a class="button-link" href="<?= h($exportUrl('documents')) ?>">Exportar grid</a><button class="button-link" type="button" data-column-panel="#documents-columns">Colunas</button></div>
        </div>
        <div class="columns-panel is-collapsed" id="documents-columns"></div>
        <?php $documentHeaders = ['Emissão','Autorização','Tipo','Finalidade','Situação','Série/Número','Pedido','Chave','Loja emissão','Loja pedido','Cliente','Vendedor','Total','Impostos','Créditos','Líquido','ICMS','PIS','COFINS','IPI','ISS','ST','IBS','CBS','DIFAL','Outros','XML','Ações']; ?>
        <div class="table-wrap documents-table-wrap">
            <table class="table documents-table revenue-documents-table js-filterable-table js-column-table">
                <thead>
                    <tr>
                        <?php foreach ($documentHeaders as $idx => $head): ?>
                            <th class="resizable" data-col="<?= h((string)$idx) ?>"><?= h($head) ?></th>
                        <?php endforeach; ?>
                    </tr>
                    <tr class="grid-filter-row">
                        <?php for ($i = 0, $n = count($documentHeaders); $i < $n; $i++): ?><th><input type="text" data-col="<?= h((string)$i) ?>" placeholder="Filtrar"></th><?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (($revenueDocuments ?? []) as $doc): ?>
                    <tr>
                        <td><?= h(format_date_short($doc['issue_date'])) ?></td>
                        <td><?= h(format_date($doc['authorization_datetime'])) ?></td>
                        <td><span class="pill"><?= h((string)$doc['document_type']) ?></span></td>
                        <td><?= h((string)$doc['purpose']) ?></td>
                        <td><?= h((string)$doc['document_status']) ?></td>
                        <td><?= h((string)$doc['series']) ?>/<?= h((string)$doc['number']) ?></td>
                        <td><?= h((string)($doc['order_number'] ?? '')) ?></td>
                        <td><small><?= h((string)$doc['access_key']) ?></small></td>
                        <td><strong><?= h((string)$doc['issuing_store_name']) ?></strong><br><small><?= h((string)$doc['issuing_store_cnpj']) ?></small></td>
                        <td><strong><?= h((string)$doc['order_store_name']) ?></strong><br><small><?= h((string)$doc['order_store_cnpj']) ?></small></td>
                        <td><?= h((string)$doc['customer_name']) ?><br><small><?= h((string)$doc['customer_document']) ?></small></td>
                        <td><?= h((string)$doc['seller_name']) ?></td>
                        <td><?= h(format_money((float)$doc['gross_amount'])) ?></td>
                        <td><?= h(format_money((float)$doc['taxes_amount'])) ?></td>
                        <td><?= h(format_money((float)$doc['tax_credits_amount'])) ?></td>
                        <td><?= h(format_money((float)$doc['net_amount'])) ?></td>
                        <?php foreach ($taxFields as $field => $label): ?><td><?= h(format_money((float)($doc[$field] ?? 0))) ?></td><?php endforeach; ?>
                        <td><?= !empty($doc['xml_content']) ? 'Sim' : 'Não' ?></td>
                        <td class="row-actions"><a class="row-action" href="<?= h($docUrl((int)$doc['id'], 'detail')) ?>">Detalhar</a> <a class="row-action" href="<?= h($docUrl((int)$doc['id'], 'taxes')) ?>">Tributos</a> <a class="row-action" href="#" data-copy="<?= h((string)$doc['access_key']) ?>">Copiar chave</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($revenueDocuments)): ?><tr><td colspan="<?= h((string)count($documentHeaders)) ?>">Nenhum documento fiscal integrado para o filtro selecionado.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php $prevQuery = $baseQuery + ['page' => 'revenue', 'tab' => 'documents', 'p' => max(1, $revenuePage - 1)]; $nextQuery = $baseQuery + ['page' => 'revenue', 'tab' => 'documents', 'p' => min($totalPages, $revenuePage + 1)]; ?>
        <div class="pagination-bar"><span>Página <?= h((string)$revenuePage) ?> de <?= h((string)$totalPages) ?> | 200 registros por página</span><div class="inline"><a class="button-link" href="<?= h(base_url('?' . http_build_query($prevQuery))) ?>">Anterior</a><a class="button-link" href="<?= h(base_url('?' . http_build_query($nextQuery))) ?>">Próxima</a></div></div>
    </section>
<?php elseif (in_array($activeTab, ['detail','taxes','xml'], true)): ?>
    <?php $doc = $revenueSelectedDocument ?? null; ?>
    <?php if (!$doc): ?>
        <section class="card"><p class="empty-state">Selecione um documento na aba Documentos Fiscais para visualizar esta análise.</p></section>
    <?php else: ?>
        <section class="card">
            <div class="section-title"><div><h2><?= h((string)$doc['document_type']) ?> <?= h((string)$doc['series']) ?>/<?= h((string)$doc['number']) ?></h2><p><?= h((string)$doc['customer_name']) ?> | <?= h(format_money((float)$doc['net_amount'])) ?></p></div><div class="inline"><a class="button-link" href="<?= h($docUrl((int)$doc['id'], 'taxes')) ?>">Abrir análise tributária</a><a class="button-link" href="<?= h($docUrl((int)$doc['id'], 'documents')) ?>">Voltar ao grid</a></div></div>
            <?php if ($activeTab === 'xml'): ?>
                <?php if (!empty($doc['xml_content'])): ?><pre class="xml-preview"><?= h((string)$doc['xml_content']) ?></pre><a class="button-link" target="_blank" href="<?= h(base_url('?page=revenue_xml&id=' . $doc['id'])) ?>">Abrir XML bruto</a><?php else: ?><p class="empty-state">XML não disponível na integração.</p><?php endif; ?>
            <?php else: ?>
                <dl class="details revenue-details">
                    <dt>Loja de emissão</dt><dd><?= h((string)$doc['issuing_store_name']) ?> - <?= h((string)$doc['issuing_store_cnpj']) ?></dd>
                    <dt>Loja do pedido</dt><dd><?= h((string)$doc['order_store_name']) ?> - <?= h((string)$doc['order_store_cnpj']) ?></dd>
                    <dt>Cliente</dt><dd><?= h((string)$doc['customer_name']) ?> - <?= h((string)$doc['customer_document']) ?></dd>
                    <dt>Vendedor</dt><dd><?= h((string)$doc['seller_name']) ?></dd>
                    <dt>Número do pedido</dt><dd><?= h((string)($doc['order_number'] ?? '')) ?></dd>
                    <dt>Chave de acesso</dt><dd><?= h((string)$doc['access_key']) ?></dd>
                    <dt>Valor total</dt><dd><?= h(format_money((float)$doc['gross_amount'])) ?></dd>
                    <dt>Devolução</dt><dd><?= h(format_money((float)$doc['return_amount'])) ?></dd>
                    <dt>Impostos</dt><dd><?= h(format_money((float)$doc['taxes_amount'])) ?></dd>
                    <dt>Créditos</dt><dd><?= h(format_money((float)$doc['tax_credits_amount'])) ?></dd>
                    <dt>Impostos detalhados</dt><dd><?php foreach ($taxFields as $field => $label): ?><span class="tax-chip"><?= h($label) ?>: <?= h(format_money((float)($doc[$field] ?? 0))) ?></span><?php endforeach; ?></dd>
                    <dt>Valor líquido</dt><dd><?= h(format_money((float)$doc['net_amount'])) ?></dd>
                    <dt>Origem integração</dt><dd><?= h((string)$doc['integration_source']) ?></dd>
                </dl>
                <h2><?= $activeTab === 'taxes' ? 'Análise Tributária do Documento' : 'Itens' ?></h2>
                <?php $detailHeaders = ['Produto','Código ERP','Qtd','Total']; if ($canViewCost) { $detailHeaders[] = 'Custo'; } $detailHeaders = array_merge($detailHeaders, ['CFOP','NCM','CST/CSOSN','Tributos','Créditos']); ?>
                <div class="table-wrap"><table class="table documents-table tax-detail-table js-column-table"><thead><tr><?php foreach ($detailHeaders as $idx => $head): ?><th class="resizable" data-col="<?= h((string)$idx) ?>"><?= h($head) ?></th><?php endforeach; ?><?php $baseTaxCol = count($detailHeaders); foreach (array_values($taxFields) as $offset => $label): ?><th class="resizable" data-col="<?= h((string)($baseTaxCol + $offset)) ?>"><?= h($label) ?></th><?php endforeach; ?></tr></thead><tbody>
                    <?php foreach (($revenueSelectedItems ?? []) as $item): ?><tr><td><?= h((string)$item['product_name']) ?></td><td><?= h((string)$item['erp_code']) ?></td><td><?= h((string)$item['quantity']) ?></td><td><?= h(format_money((float)$item['total_amount'])) ?></td><?php if ($canViewCost): ?><td><?= h(format_money((float)($item['cost_amount'] ?? 0))) ?></td><?php endif; ?><td><?= h((string)$item['cfop']) ?></td><td><?= h((string)$item['ncm']) ?></td><td><?= h((string)$item['cst_csosn']) ?></td><td><?= h(format_money((float)$item['taxes_amount'])) ?></td><td><?= h(format_money((float)$item['tax_credits_amount'])) ?></td><?php foreach ($taxFields as $field => $label): ?><td><?= h(format_money((float)($item[$field] ?? 0))) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
                    <?php if (empty($revenueSelectedItems)): ?><tr><td colspan="<?= h((string)(count($detailHeaders) + count($taxFields))) ?>">Itens ainda não integrados para este documento.</td></tr><?php endif; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php elseif ($activeTab === 'items'): ?>
    <section class="card documents-card">
        <div class="grid-toolbar">
            <div><h2>Itens / Produtos</h2><small>Filtro local por coluna sobre os itens carregados.</small></div>
            <div class="inline"><a class="button-link" href="<?= h($exportUrl('items')) ?>">Exportar grid</a><button class="button-link" type="button" data-column-panel="#items-columns">Colunas</button></div>
        </div>
        <div class="columns-panel is-collapsed" id="items-columns"></div>
        <?php $itemHeaders = ['Produto','Código interno','Código ERP','Grupo','Qtd','Unidade','Total','CFOP','NCM','CST/CSOSN','Loja emissão','Loja pedido','Pedido','Documento','ICMS','PIS','COFINS','IPI','ISS','ST','IBS','CBS','DIFAL','Outros']; if ($canViewCost) { array_splice($itemHeaders, 7, 0, ['Custo']); } ?>
        <div class="table-wrap documents-table-wrap"><table class="table documents-table js-filterable-table js-column-table">
            <thead>
                <tr>
                    <?php foreach ($itemHeaders as $idx => $head): ?>
                        <th class="resizable" data-col="<?= h((string)$idx) ?>"><?= h($head) ?></th>
                    <?php endforeach; ?>
                </tr>
                <tr class="grid-filter-row"><?php for ($i = 0, $n = count($itemHeaders); $i < $n; $i++): ?><th><input type="text" data-col="<?= h((string)$i) ?>" placeholder="Filtrar"></th><?php endfor; ?></tr>
            </thead>
            <tbody>
            <?php foreach (($revenueItems ?? []) as $item): ?><tr><td><?= h((string)$item['product_name']) ?></td><td><?= h((string)$item['internal_code']) ?></td><td><?= h((string)$item['erp_code']) ?></td><td><?= h((string)$item['product_group']) ?></td><td><?= h((string)$item['quantity']) ?></td><td><?= h((string)$item['unit']) ?></td><td><?= h(format_money((float)$item['total_amount'])) ?></td><?php if ($canViewCost): ?><td><?= h(format_money((float)($item['cost_amount'] ?? 0))) ?></td><?php endif; ?><td><?= h((string)$item['cfop']) ?></td><td><?= h((string)$item['ncm']) ?></td><td><?= h((string)$item['cst_csosn']) ?></td><td><?= h((string)$item['issuing_store_name']) ?></td><td><?= h((string)$item['order_store_name']) ?></td><td><?= h((string)($item['order_number'] ?? '')) ?></td><td><?= h((string)$item['document_type']) ?> <?= h((string)$item['series']) ?>/<?= h((string)$item['number']) ?></td><?php foreach ($taxFields as $field => $label): ?><td><?= h(format_money((float)($item[$field] ?? 0))) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
            <?php if (empty($revenueItems)): ?><tr><td colspan="<?= h((string)count($itemHeaders)) ?>">Nenhum item integrado para o filtro selecionado.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
<?php endif; ?>

<script>
document.querySelectorAll('[data-copy]').forEach(function (link) {
    link.addEventListener('click', function (event) {
        event.preventDefault();
        navigator.clipboard.writeText(link.getAttribute('data-copy') || '');
        link.textContent = 'Copiado';
    });
});

var revenueFilterKey = 'controls.revenue.lastFilters';
var currentParams = new URLSearchParams(window.location.search);
if (currentParams.get('page') === 'revenue') {
    var hasBusinessFilter = Array.from(currentParams.keys()).some(function (key) {
        return ['date_start','date_end','sale_return','document_type','document_status','issuing_store_cnpj','order_store_name','customer_name','customer_document','seller_name','order_link','order_number','number','series','access_key','product','product_group','cfop','ncm','cst_csosn','xml_available','amount_min','amount_max'].indexOf(key) >= 0;
    });
    if (!hasBusinessFilter && localStorage.getItem(revenueFilterKey)) {
        var saved = localStorage.getItem(revenueFilterKey);
        if (saved && saved !== window.location.search.substring(1)) {
            window.location.replace(window.location.pathname + '?' + saved);
        }
    } else if (hasBusinessFilter) {
        localStorage.setItem(revenueFilterKey, window.location.search.substring(1));
    }
}
document.querySelector('.revenue-filter')?.addEventListener('submit', function () {
    localStorage.setItem(revenueFilterKey, new URLSearchParams(new FormData(this)).toString());
});

// Filtros locais, seletor de colunas e redimensionamento ficam no cliente para nao alterar o contexto global da consulta.
document.querySelectorAll('.js-filterable-table').forEach(function (table) {
    var inputs = table.querySelectorAll('.grid-filter-row input');
    inputs.forEach(function (input) {
        input.addEventListener('input', function () {
            var filters = Array.from(inputs).map(function (i) { return (i.value || '').toLowerCase(); });
            table.querySelectorAll('tbody tr').forEach(function (row) {
                var visible = filters.every(function (filter, idx) {
                    return !filter || ((row.children[idx] && row.children[idx].innerText.toLowerCase().indexOf(filter) >= 0));
                });
                row.style.display = visible ? '' : 'none';
            });
        });
    });
});

document.querySelectorAll('[data-column-panel]').forEach(function (button) {
    var panel = document.querySelector(button.getAttribute('data-column-panel'));
    var table = button.closest('.documents-card').querySelector('.js-column-table');
    if (!panel || !table) return;
    var key = 'controls.revenue.columns.' + (panel.id || 'grid');
    var state = {};
    try { state = JSON.parse(localStorage.getItem(key) || '{}'); } catch (e) { state = {}; }
    function tagCells() {
        table.querySelectorAll('tr').forEach(function (row) {
            Array.from(row.children).forEach(function (cell, idx) {
                if (!cell.hasAttribute('data-col')) cell.setAttribute('data-col', String(idx));
            });
        });
    }
    function applyColumn(idx, visible) {
        tagCells();
        table.querySelectorAll('[data-col="' + idx + '"]').forEach(function (cell) {
            cell.classList.toggle('is-hidden-column', !visible);
        });
    }
    tagCells();
    Array.from(table.querySelectorAll('thead tr:first-child th')).forEach(function (th, idx) {
        var checked = state[idx] !== false;
        var label = document.createElement('label');
        label.className = 'checkbox-inline';
        label.innerHTML = '<input type="checkbox" ' + (checked ? 'checked' : '') + ' data-column-toggle="' + idx + '"> ' + th.innerText;
        panel.appendChild(label);
        applyColumn(idx, checked);
    });
    button.addEventListener('click', function () { panel.classList.toggle('is-collapsed'); });
    panel.addEventListener('change', function (event) {
        var input = event.target.closest('[data-column-toggle]');
        if (!input) return;
        var idx = parseInt(input.getAttribute('data-column-toggle'), 10);
        state[idx] = input.checked;
        localStorage.setItem(key, JSON.stringify(state));
        applyColumn(idx, input.checked);
    });
});

document.querySelectorAll('.js-column-table th.resizable').forEach(function (th) {
    var grip = document.createElement('span');
    grip.className = 'column-resizer';
    th.appendChild(grip);
    grip.addEventListener('mousedown', function (event) {
        event.preventDefault();
        var startX = event.pageX;
        var startWidth = th.offsetWidth;
        document.body.classList.add('is-resizing-column');
        function move(moveEvent) { th.style.width = Math.max(70, startWidth + moveEvent.pageX - startX) + 'px'; }
        function stop() {
            document.removeEventListener('mousemove', move);
            document.removeEventListener('mouseup', stop);
            document.body.classList.remove('is-resizing-column');
        }
        document.addEventListener('mousemove', move);
        document.addEventListener('mouseup', stop);
    });
});


// Permite reorganizar colunas dos grids de faturamento sem recarregar a página.
document.querySelectorAll('.js-column-table').forEach(function (table, tableIndex) {
    var title = (table.closest('.documents-card, .card')?.querySelector('h2')?.innerText || ('grid-' + tableIndex)).toLowerCase().replace(/\W+/g, '-');
    var orderKey = 'controls.revenue.columnOrder.' + title;
    function tagCells() {
        table.querySelectorAll('tr').forEach(function (row) {
            Array.from(row.children).forEach(function (cell, idx) {
                if (!cell.hasAttribute('data-col')) cell.setAttribute('data-col', String(idx));
            });
        });
    }
    function defaultOrder() {
        tagCells();
        return Array.from(table.querySelectorAll('thead tr:first-child th')).map(function (th) { return th.getAttribute('data-col'); }).filter(Boolean);
    }
    function applyOrder(order) {
        if (!order || !order.length) return;
        table.querySelectorAll('tr').forEach(function (row) {
            var byKey = {};
            Array.from(row.children).forEach(function (cell) { byKey[cell.getAttribute('data-col')] = cell; });
            order.forEach(function (key) { if (byKey[key]) row.appendChild(byKey[key]); });
        });
    }
    tagCells();
    try { applyOrder(JSON.parse(localStorage.getItem(orderKey) || '[]')); } catch (e) {}
    table.querySelectorAll('thead tr:first-child th').forEach(function (th) {
        th.draggable = true;
        th.title = (th.title ? th.title + ' | ' : '') + 'Arraste para reorganizar a coluna';
        th.addEventListener('dragstart', function (event) {
            if (event.target.classList.contains('column-resizer')) return;
            event.dataTransfer.setData('text/plain', th.getAttribute('data-col') || '');
        });
        th.addEventListener('dragover', function (event) { event.preventDefault(); });
        th.addEventListener('drop', function (event) {
            event.preventDefault();
            var from = event.dataTransfer.getData('text/plain');
            var to = th.getAttribute('data-col');
            if (!from || !to || from === to) return;
            var order = defaultOrder();
            order.splice(order.indexOf(from), 1);
            order.splice(order.indexOf(to), 0, from);
            localStorage.setItem(orderKey, JSON.stringify(order));
            applyOrder(order);
        });
    });
});

</script>
<?php include __DIR__ . '/layout_bottom.php'; ?>
