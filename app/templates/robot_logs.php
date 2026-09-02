<?php include __DIR__ . '/layout_top.php'; ?>
<?php
$robotLabels = [
    '' => 'Todos os tipos',
    'nfe_until_max' => 'Robo NF-e / NFC-e ate ultimo NSU',
    'nfe_until_max_science' => 'Robo NF-e + ciencia',
    'cte_until_max' => 'Robo CT-e ate ultimo NSU',
    'cte_xml_folder_export' => 'Robo CT-e XML na pasta ERP',
    'nfe_xml_folder_export' => 'Robo NF-e XML na pasta ERP',
    'nfse_xml_folder_export' => 'Robo NFS-e XML na pasta ERP',
    'nfe_cancellation_check' => 'Robo verificar cancelamento NF-e',
    'cte_cancellation_check' => 'Robo verificar cancelamento CT-e',
    'nfse_cancellation_check' => 'Robo verificar cancelamento NFS-e',
    'nfse_cancel_check_selected' => 'Verificar cancelamento NFS-e selecionado',
    'nfe_cancel_check_selected' => 'Verificar cancelamento NF-e selecionado',
    'certificate_check' => 'Validar certificado e pasta',
];
$selectedType = (string)($robotLogJobType ?? '');
?>
<div class="page-header"><h1>Logs dos Robos</h1><p>Consulte as execucoes dos robos sem abrir a tela de execucao manual.</p></div>
<form method="get" class="card form-grid"><input type="hidden" name="page" value="robot_logs"><div class="form-row three"><label>Data inicial<input type="date" name="date_start" value="<?= h((string)($robotLogDateStart ?? '')) ?>"></label><label>Data final<input type="date" name="date_end" value="<?= h((string)($robotLogDateEnd ?? '')) ?>"></label><label>Tipo do robo<select name="job_type"><?php foreach ($robotLabels as $value => $label): ?><option value="<?= h($value) ?>" <?= $selectedType === $value ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label></div><div class="form-actions"><button class="primary button-compact" type="submit">Filtrar logs</button><a class="button-link button-compact" href="<?= h(base_url('?page=robot_logs')) ?>">Limpar filtros</a></div></form>
<div class="card"><div class="section-title"><h2>Execucoes encontradas</h2><span class="muted"><?= h((string)count($robotLogs ?? [])) ?> registro(s)</span></div><div class="table-wrap"><table><thead><tr><th>Tipo</th><th>Empresa</th><th>Status</th><th>Inicio</th><th>Fim</th><th>Criados</th><th>Atualizados</th><th>Erros</th><th>Log</th></tr></thead><tbody><?php foreach (($robotLogs ?? []) as $log): ?><tr><td><?= h($robotLabels[(string)($log['job_type'] ?? '')] ?? (string)($log['job_type'] ?? '')) ?></td><td><?= h((string)($log['company_name'] ?? 'Todas as empresas')) ?></td><td><?= h((string)($log['status'] ?? '')) ?></td><td><?= h(format_date((string)($log['started_at'] ?? ''))) ?></td><td><?= h(format_date((string)($log['finished_at'] ?? ''))) ?></td><td><?= h((string)($log['created_count'] ?? 0)) ?></td><td><?= h((string)($log['updated_count'] ?? 0)) ?></td><td><?= h((string)($log['error_count'] ?? 0)) ?></td><td><details><summary>Ver log</summary><pre class="robot-log-text"><?= h((string)($log['log_text'] ?? '')) ?></pre></details></td></tr><?php endforeach; ?><?php if (empty($robotLogs)): ?><tr><td colspan="9">Nenhuma execucao encontrada para os filtros informados.</td></tr><?php endif; ?></tbody></table></div></div><style>.robot-log-text{white-space:pre-wrap;max-width:520px;max-height:240px;overflow:auto;margin:8px 0;font:12px/1.45 ui-monospace,Consolas,monospace;background:#f6faf8;padding:10px;border-radius:8px}</style>
<?php include __DIR__ . '/layout_bottom.php'; ?>
