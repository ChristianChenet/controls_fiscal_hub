<?php include __DIR__ . '/layout_top.php'; ?>
<div class="page-header split-header">
    <div>
        <h1>Dashboard Fiscal</h1>
        <p>Visao geral dos XMLs, valores, certificado e filas operacionais.</p>
    </div>
</div>

<form method="get" class="card dashboard-filter">
    <input type="hidden" name="page" value="dashboard">
    <div class="form-row four">
        <label>Empresa
            <select name="company_id">
                <option value="">Todos os CNPJs</option>
                <?php foreach ($companies as $co): ?>
                    <option value="<?= h((string)$co['id']) ?>" <?= (string)($dashboardFilters['company_id'] ?? '') === (string)$co['id'] ? 'selected' : '' ?>>
                        <?= h($co['company_name']) ?> - <?= h($co['cnpj']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Data inicial
            <input type="date" name="date_start" value="<?= h((string)($dashboardFilters['date_start'] ?? '')) ?>">
        </label>
        <label>Data final
            <input type="date" name="date_end" value="<?= h((string)($dashboardFilters['date_end'] ?? '')) ?>">
        </label>
        <div class="form-actions dashboard-actions">
            <a class="button-link" href="<?= h(base_url()) ?>">Limpar</a>
            <button class="primary">Atualizar</button>
        </div>
    </div>
</form>

<div class="grid four">
    <div class="card stat"><strong><?= h((string) $dashboard['companiesCount']) ?></strong><span>CNPJs no filtro</span></div>
    <?php foreach (['NFE' => 'NF-e', 'NFCE' => 'NFC-e', 'CTE' => 'CT-e', 'MDFE' => 'MDF-e', 'NFSE' => 'NFS-e Nacional'] as $type => $label): ?>
        <?php $total = $dashboard['typeTotals'][$type] ?? ['total' => 0, 'total_value' => 0]; ?>
        <div class="card stat type-stat">
            <strong><?= h((string)$total['total']) ?></strong>
            <span><?= h($label) ?></span>
            <small><?= h(format_money((float)$total['total_value'])) ?></small>
            <em><?= h(format_date_short($total['first_issue_date'] ?? null)) ?> a <?= h(format_date_short($total['last_issue_date'] ?? null)) ?></em>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid four">
    <div class="card stat neutral"><strong><?= h((string) $dashboard['summary']) ?></strong><span>Apenas resumo</span></div>
    <div class="card stat warn"><strong><?= h((string) $dashboard['pending']) ?></strong><span>Pendentes</span></div>
    <div class="card stat neutral"><strong><?= h((string) $dashboard['awaiting']) ?></strong><span>Aguardando novo download</span></div>
    <div class="card stat ok"><strong><?= h((string) $dashboard['full']) ?></strong><span>XML completo</span></div>
</div>

<section class="card dashboard-latest-docs">
    <div class="section-title">
        <div>
            <h2>Ultimo XML por CNPJ</h2>
            <p>Data da nota ou CT-e mais recente para cada CNPJ no filtro atual.</p>
        </div>
    </div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Empresa</th><th>CNPJ</th><th>Ultima NF-e/NFC-e</th><th>Ultimo CT-e</th><th>Ultimo documento</th><th>Docs</th></tr></thead>
        <tbody>
        <?php foreach (($dashboard['latestByCompany'] ?? []) as $row): ?>
            <tr>
                <td><?= h((string)$row['company_name']) ?></td>
                <td class="nowrap"><?= h((string)$row['company_cnpj']) ?></td>
                <td><?= h(format_date_short($row['latest_note_date'] ?? null)) ?></td>
                <td><?= h(format_date_short($row['latest_cte_date'] ?? null)) ?></td>
                <td><strong><?= h(format_date_short($row['latest_document_date'] ?? null)) ?></strong></td>
                <td><?= h((string)$row['total_documents']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($dashboard['latestByCompany'])): ?>
            <tr><td colspan="6">Nenhum CNPJ encontrado no filtro selecionado.</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
</section>

<?php
    $monthlyImports = $dashboard['monthlyImports'] ?? [];
    $maxMonthlyTotal = 1;
    foreach ($monthlyImports as $monthRow) {
        $maxMonthlyTotal = max($maxMonthlyTotal, (int)($monthRow['total'] ?? 0));
    }
?>
<section class="card dashboard-chart-card">
    <div class="section-title">
        <div>
            <h2>Documentos importados por mes</h2>
            <p>Volume mensal por tipo, respeitando empresa e periodo selecionados.</p>
        </div>
    </div>
    <div class="monthly-chart">
        <?php foreach ($monthlyImports as $monthRow): ?>
            <?php
                $height = max(8, (int)round(((int)$monthRow['total'] / $maxMonthlyTotal) * 150));
                $monthLabel = DateTimeImmutable::createFromFormat('!Y-m', (string)$monthRow['month']);
            ?>
            <div class="month-bar" title="<?= h((string)$monthRow['month']) ?>: <?= h((string)$monthRow['total']) ?> documentos">
                <div class="month-total"><?= h((string)$monthRow['total']) ?></div>
                <div class="bar-stack" style="height: <?= h((string)$height) ?>px">
                    <?php foreach (['NFE' => 'nfe', 'NFCE' => 'nfce', 'CTE' => 'cte', 'MDFE' => 'mdfe', 'NFSE' => 'nfse'] as $type => $class): ?>
                        <?php if ((int)($monthRow[$type] ?? 0) > 0): ?>
                            <span class="<?= h($class) ?>" style="height: <?= h((string)max(4, round(((int)$monthRow[$type] / (int)$monthRow['total']) * $height))) ?>px"></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div class="month-label"><?= h($monthLabel ? $monthLabel->format('m/Y') : (string)$monthRow['month']) ?></div>
            </div>
        <?php endforeach; ?>
        <?php if (!$monthlyImports): ?>
            <p class="empty-state">Nenhum documento importado no filtro selecionado.</p>
        <?php endif; ?>
    </div>
    <div class="chart-legend">
        <span><i class="nfe"></i>NF-e</span>
        <span><i class="nfce"></i>NFC-e</span>
        <span><i class="cte"></i>CT-e</span>
        <span><i class="mdfe"></i>MDF-e</span>
        <span><i class="nfse"></i>NFS-e</span>
    </div>
</section>

<div class="grid two dashboard-rankings">
    <section class="card">
        <h2>Top 20 fornecedores</h2>
        <div class="table-wrap"><table class="table ranking-table">
            <thead><tr><th>Fornecedor</th><th>CNPJ</th><th>Docs</th><th>Valor</th></tr></thead>
            <tbody>
            <?php foreach (($dashboard['topSuppliers'] ?? []) as $row): ?>
                <tr>
                    <td><?= h((string)$row['name']) ?></td>
                    <td class="nowrap"><?= h((string)$row['cnpj']) ?></td>
                    <td><?= h((string)$row['total']) ?></td>
                    <td class="money-cell"><?= h(format_money((float)$row['total_value'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($dashboard['topSuppliers'])): ?>
                <tr><td colspan="4">Nenhum fornecedor no filtro selecionado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table></div>
    </section>

    <section class="card">
        <h2>Top 20 transportadoras CT-e</h2>
        <div class="table-wrap"><table class="table ranking-table" style="font-size: 12px;">
            <thead><tr><th>Transportadora</th><th>CNPJ</th><th>CT-es</th><th>Valor</th></tr></thead>
            <tbody>
            <?php foreach (($dashboard['topTransporters'] ?? []) as $row): ?>
                <tr>
                    <td><?= h((string)$row['name']) ?></td>
                    <td class="nowrap"><?= h((string)$row['cnpj']) ?></td>
                    <td><?= h((string)$row['total']) ?></td>
                    <td class="money-cell"><?= h(format_money((float)$row['total_value'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($dashboard['topTransporters'])): ?>
                <tr><td colspan="4">Nenhuma transportadora no filtro selecionado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table></div>
    </section>
</div>

<div class="grid two">
    <section class="card">
        <h2>Empresas com maior volume</h2>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Empresa</th><th>CNPJ</th><th>Total</th><th>Valor</th></tr></thead>
            <tbody>
            <?php foreach (($dashboard['docsByCompany'] ?? []) as $row): ?>
                <tr>
                    <td><?= h($row['company_name']) ?></td>
                    <td><?= h($row['company_cnpj']) ?></td>
                    <td><?= h((string)$row['total']) ?></td>
                    <td><?= h(format_money((float)$row['total_value'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>

    <section class="card">
        <h2>Ultimo certificado ativo</h2>
        <?php if ($certificate): ?>
            <dl class="details certificate-details">
                <dt>Arquivo</dt><dd><?= h($certificate['filename']) ?></dd>
                <dt>Assunto</dt><dd><?= h($certificate['subject_name']) ?></dd>
                <dt>Validade</dt><dd><?= h(format_date($certificate['valid_to'])) ?></dd>
                <dt>Thumbprint</dt><dd><?= h($certificate['thumbprint']) ?></dd>
            </dl>
        <?php else: ?>
            <p>Nenhum certificado ativo.</p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Conectores</h2>
        <dl class="details compact-details">
            <dt>NF-e / NFC-e</dt><dd><?= h($settings['nfe_distribution_url']) ?></dd>
            <dt>CT-e</dt><dd><?= h($settings['cte_distribution_url']) ?></dd>
            <dt>NFS-e Nacional</dt><dd><?= h($settings['nfse_base_url'] . $settings['nfse_distribution_path']) ?></dd>
        </dl>
    </section>
</div>

<div class="grid two">
    <section class="card">
        <h2>Jobs recentes</h2>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Tipo</th><th>Status</th><th>Inicio</th></tr></thead>
            <tbody>
            <?php foreach ($jobs as $job): ?>
                <tr>
                    <td><?= h($job['job_type']) ?></td>
                    <td><?= h($job['status']) ?></td>
                    <td><?= h(format_date($job['started_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>

    <section class="card">
        <h2>Auditoria recente</h2>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Acao</th><th>Detalhes</th><th>Data</th></tr></thead>
            <tbody>
            <?php foreach ($actions as $item): ?>
                <tr>
                    <td><?= h($item['action_type']) ?></td>
                    <td class="clip-text"><?= h($item['details']) ?></td>
                    <td><?= h(format_date($item['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>
</div>
<?php include __DIR__ . '/layout_bottom.php'; ?>
