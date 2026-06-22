<?php include __DIR__ . '/layout_top.php'; ?>
<?php
$selectedClosure = $selectedClosure ?? null;
$summary = $selectedClosure && !empty($selectedClosure['summary_json']) ? (json_decode((string)$selectedClosure['summary_json'], true) ?: []) : [];
$periodItems = $periodItems ?? [];
?>
<div class="page-header split-header">
    <div>
        <h1>Radar por Período</h1>
        <p>Localize XMLs do intervalo informado com controle de consumo e filtro interno por data.</p>
    </div>
    <a class="button-link" href="<?= h(base_url('?page=period_closure_docs')) ?>">Ler documentação</a>
</div>

<?php if ($selectedClosure && !empty($selectedClosure['messages'])): ?>
    <section class="notice highlight">
        <strong>Fechamento #<?= h((string)$selectedClosure['id']) ?>:</strong>
        <?= nl2br_safe($selectedClosure['messages']) ?>
    </section>
<?php else: ?>
    <section class="notice highlight">
        A distribuição DF-e não possui filtro nativo por período. O portal consulta por NSU e classifica internamente os documentos retornados.
    </section>
<?php endif; ?>

<section class="card card-compact">
    <div class="section-title">
        <div>
            <h2>Filtros do radar</h2>
            <p>Selecione empresas, tipos e período em uma única etapa.</p>
        </div>
    </div>

    <form method="post" class="form-grid period-form">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <div id="period-form-body" class="collapsible-area">
        <div class="form-row radar-main">
            <label>Empresas
                <select name="company_ids[]" multiple size="3">
                    <option value="0">Todos os CNPJs ativos</option>
                    <?php foreach (($companies ?? []) as $co): ?>
                        <option value="<?= h((string)$co['id']) ?>"><?= h($co['company_name']) ?> - <?= h($co['cnpj']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Tipos
                <select name="doc_types[]" multiple size="3">
                    <option value="nfe" selected>NF-e</option>
                    <option value="cte" selected>CT-e</option>
                    <option value="nfse">NFS-e Nacional</option>
                </select>
            </label>
            <label>Data inicial
                <span class="date-field"><input type="date" name="period_start" value="<?= h(date('Y-m-01')) ?>"></span>
            </label>
            <label>Data final
                <span class="date-field"><input type="date" name="period_end" value="<?= h(date('Y-m-d')) ?>"></span>
            </label>
        </div>
        <div class="form-row radar-extra">
            <label>Manifestação NF-e
                <select name="manifest_type">
                    <option value="science">Ciência da Operação</option>
                    <option value="confirm">Confirmação da Operação</option>
                    <option value="unknown">Desconhecimento da Operação</option>
                    <option value="not_realized">Operação não Realizada</option>
                </select>
            </label>
            <label>Justificativa
                <input type="text" name="manifest_justification" placeholder="Obrigatória para operação não realizada">
            </label>
        </div>
        <div class="option-grid">
            <label class="checkbox-inline"><input type="checkbox" name="only_missing_complete" value="1" checked> Baixar apenas documentos sem XML completo</label>
            <label class="checkbox-inline"><input type="checkbox" name="try_manifestation" value="1"> Tentar manifestação para pendências NF-e</label>
            <label class="checkbox-inline"><input type="checkbox" name="reprocess_after_manifestation" value="1"> Reprocessar após manifestação</label>
            <label class="checkbox-inline"><input type="checkbox" name="generate_export" value="1" checked> Gerar exportação do período</label>
            <label class="checkbox-inline"><input type="checkbox" name="save_period_folder" value="1"> Salvar em pasta final do período</label>
        </div>
        </div>
        <div class="form-actions">
            <button class="primary" name="run_period_closure" value="1">Executar fechamento</button>
            <button type="button" class="button-link" data-collapse-target="#period-form-body" data-hide-label="Recolher filtros" data-show-label="Mostrar filtros">Recolher filtros</button>
        </div>
    </form>
</section>

<?php if ($selectedClosure): ?>
<section class="metrics-grid">
    <div class="card stat neutral"><strong><?= h((string)($summary['total_identificados'] ?? 0)) ?></strong><span>Total identificados</span></div>
    <div class="card stat ok"><strong><?= h((string)($summary['xml_completo'] ?? 0)) ?></strong><span>XML completo</span></div>
    <div class="card stat ok"><strong><?= h((string)($summary['ja_existente'] ?? 0)) ?></strong><span>Já existentes</span></div>
    <div class="card stat warn"><strong><?= h((string)($summary['pendente_manifestacao'] ?? 0)) ?></strong><span>Pendentes</span></div>
    <div class="card stat warn"><strong><?= h((string)($summary['aguardando_novo_download'] ?? 0)) ?></strong><span>Aguardando novo download</span></div>
    <div class="card stat neutral"><strong><?= h((string)($summary['fora_do_periodo_solicitado'] ?? 0)) ?></strong><span>Fora do período</span></div>
</section>

<section class="card card-compact">
    <div class="section-title">
        <div>
            <h2>Fechamento #<?= h((string)$selectedClosure['id']) ?></h2>
            <p><?= h((string)$selectedClosure['period_start']) ?> a <?= h((string)$selectedClosure['period_end']) ?> | <?= h((string)$selectedClosure['status']) ?></p>
        </div>
        <div class="inline">
            <?php if (!empty($selectedClosure['export_zip_path'])): ?>
                <a class="button-link" href="<?= h(base_url('?page=download_export&type=zip&id=' . $selectedClosure['id'])) ?>">Baixar ZIP</a>
            <?php endif; ?>
            <?php if (!empty($selectedClosure['export_csv_path'])): ?>
                <a class="button-link" href="<?= h(base_url('?page=download_export&type=csv&id=' . $selectedClosure['id'])) ?>">Baixar CSV</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<form method="post" class="card card-compact">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="closure_id" value="<?= h((string)$selectedClosure['id']) ?>">
    <div class="section-title">
        <div>
            <h2>Documentos do fechamento</h2>
            <p>Selecione NF-e pendentes para manifestação ou reprocesse documentos aguardando novo download.</p>
        </div>
        <button type="button" class="button-link" data-collapse-target="#period-actions" data-hide-label="Recolher ações" data-show-label="Mostrar ações">Recolher ações</button>
    </div>
    <div id="period-actions" class="toolbar">
        <div class="inline">
            <select name="manifest_type">
                <option value="science">Ciência da Operação</option>
                <option value="confirm">Confirmação da Operação</option>
                <option value="unknown">Desconhecimento da Operação</option>
                <option value="not_realized">Operação não Realizada</option>
            </select>
            <input type="text" name="manifest_justification" placeholder="Justificativa para Operação não Realizada">
            <button class="primary" name="manifest_period_selected" value="1">Manifestar selecionados</button>
        </div>
        <button name="reprocess_period_pending" value="1">Reprocessar pendentes</button>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('input[name=&quot;ids[]&quot;]').forEach(el => el.checked = this.checked)"></th><th>Empresa</th><th>Tipo</th><th>Chave</th><th>Emitente/Tomador</th><th>Data</th><th>Valor</th><th>Status</th><th>XML</th><th>Pasta</th><th>Ação</th></tr></thead>
            <tbody>
            <?php foreach ($periodItems as $item): ?>
                <tr>
                    <td><?php if (!empty($item['document_id'])): ?><input type="checkbox" name="ids[]" value="<?= h((string)$item['document_id']) ?>"><?php endif; ?></td>
                    <td><strong><?= h($item['company_name']) ?></strong><br><small><?= h($item['company_cnpj']) ?></small></td>
                    <td><?= h($item['doc_type']) ?></td>
                    <td><small><?= h($item['access_key']) ?></small></td>
                    <td><strong><?= h($item['issuer_name']) ?></strong><br><small><?= h($item['issuer_cnpj']) ?></small></td>
                    <td><?= h(format_date($item['issue_date'])) ?></td>
                    <td><?= h(format_money((float)$item['total_value'])) ?></td>
                    <td><?= h(document_status_label($item['status'])) ?></td>
                    <td><?= !empty($item['xml_saved']) ? 'Sim' : 'Não' ?></td>
                    <td><small><?= h($item['storage_dir']) ?></small></td>
                    <td><?php if (!empty($item['document_id'])): ?><a class="row-action" href="<?= h(base_url('?page=view_xml&id=' . $item['document_id'])) ?>" target="_blank">Abrir</a><?php else: ?>-<?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$periodItems): ?>
                <tr><td colspan="11">Nenhum item para este fechamento.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</form>
<?php endif; ?>

<section class="card card-compact">
    <h2>Histórico de fechamentos</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>ID</th><th>Período</th><th>Status</th><th>Início</th><th>Fim</th><th>Abrir</th></tr></thead>
            <tbody>
            <?php foreach (($periodClosures ?? []) as $closure): ?>
                <tr>
                    <td><?= h((string)$closure['id']) ?></td>
                    <td><?= h($closure['period_start'] . ' a ' . $closure['period_end']) ?></td>
                    <td><?= h($closure['status']) ?></td>
                    <td><?= h(format_date($closure['started_at'])) ?></td>
                    <td><?= h(format_date($closure['finished_at'])) ?></td>
                <td><a class="row-action" href="<?= h(base_url('?page=period_closure&id=' . $closure['id'])) ?>">Abrir</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($periodClosures)): ?>
                <tr><td colspan="6">Nenhum fechamento executado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/layout_bottom.php'; ?>
