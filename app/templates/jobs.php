<?php include __DIR__ . '/layout_top.php'; ?>
<?php
$selectedCompanyId = (string)($selectedJobCompanyId ?? ($_GET['company_id'] ?? '0'));
$selectedJobType = (string)($selectedJobType ?? ($_GET['job_type'] ?? 'cte_xml_folder_export'));
$routineLabels = [
    'nfe_until_max' => 'Robo NF-e / NFC-e ate ultimo NSU',
    'cte_until_max' => 'Robo CT-e ate ultimo NSU',
    'cte_xml_folder_export' => 'Gerar pasta XML CT-e para ERP',
    'certificate_check' => 'Validar certificado e pasta',
];
?>
<div class="page-header">
    <h1>Execucao manual de Robos</h1>
    <p>Execute rotinas operacionais sob demanda e acompanhe o historico.</p>
</div>

<form method="post" class="card form-grid routine-form">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <div class="form-row two">
        <label>Empresa
            <select name="company_id">
                <option value="0" <?= $selectedCompanyId === '0' ? 'selected' : '' ?>>Todos os CNPJs ativos</option>
                <?php foreach (($companies ?? []) as $co): ?>
                    <option value="<?= h((string)$co['id']) ?>" <?= $selectedCompanyId === (string)$co['id'] ? 'selected' : '' ?>><?= h($co['company_name']) ?> - <?= h($co['cnpj']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Execucao manual de robos
            <select name="job_type">
                <?php foreach ($routineLabels as $value => $label): ?>
                    <option value="<?= h($value) ?>" <?= $selectedJobType === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="notice subtle">
        Use execucoes manuais quando precisar antecipar uma rotina ou conferir o ambiente. Em NF-e, ao selecionar uma matriz ou filial, o portal executa todos os CNPJs ativos da mesma raiz, um por vez.
    </div>
    <div class="form-actions">
        <button class="primary" name="run_job" value="1">Executar robo</button>
    </div>
</form>

<section class="card card-compact">
    <h2>Historico</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Empresa</th><th>Rotina</th><th>Status</th><th>Criados</th><th>Atualizados</th><th>Erros</th><th>Inicio</th><th>Fim</th><th>Log</th></tr></thead>
            <tbody>
            <?php foreach ($jobs as $job): ?>
                <?php $jobType = (string)($job['job_type'] ?? ''); ?>
                <tr>
                    <td><?= h($job['company_name'] ?: 'Todas') ?></td>
                    <td><?= h($routineLabels[$jobType] ?? $jobType) ?></td>
                    <td><?= h($job['status']) ?></td>
                    <td><?= h((string) $job['created_count']) ?></td>
                    <td><?= h((string) $job['updated_count']) ?></td>
                    <td><?= h((string) $job['error_count']) ?></td>
                    <td><?= h(format_date($job['started_at'])) ?></td>
                    <td><?= h(format_date($job['finished_at'])) ?></td>
                    <td><small><?= nl2br_safe($job['log_text']) ?></small></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$jobs): ?>
                <tr><td colspan="9">Nenhuma rotina executada.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/layout_bottom.php'; ?>
