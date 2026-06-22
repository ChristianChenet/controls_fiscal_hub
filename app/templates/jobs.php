<?php include __DIR__ . '/layout_top.php'; ?>
<?php
$selectedCompanyId = (string)($selectedJobCompanyId ?? ($_GET['company_id'] ?? '0'));
$selectedJobType = (string)($selectedJobType ?? ($_GET['job_type'] ?? 'collect_all'));
?>
<div class="page-header">
    <h1>Radar de XML</h1>
    <p>Execute buscas pontuais por empresa, tipo de documento ou validação operacional.</p>
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
        <label>Rotina
            <select name="job_type">
                <option value="collect_all" <?= $selectedJobType === 'collect_all' ? 'selected' : '' ?>>Coletar documentos disponíveis</option>
                <option value="collect_missing" <?= $selectedJobType === 'collect_missing' ? 'selected' : '' ?>>Reprocessar faltantes e pendentes</option>
                <option value="nfe" <?= $selectedJobType === 'nfe' ? 'selected' : '' ?>>Coletar NF-e / NFC-e</option>
                <option value="nfe_until_max" <?= $selectedJobType === 'nfe_until_max' ? 'selected' : '' ?>>Robô NF-e / NFC-e até último NSU</option>
                <option value="nfe_until_max_science" <?= $selectedJobType === 'nfe_until_max_science' ? 'selected' : '' ?>>Robô NF-e + ciência da operação</option>
                <option value="cte" <?= $selectedJobType === 'cte' ? 'selected' : '' ?>>Coletar CT-e</option>
                <option value="cte_until_max" <?= $selectedJobType === 'cte_until_max' ? 'selected' : '' ?>>Robô CT-e até último NSU</option>
                <option value="nfse" <?= $selectedJobType === 'nfse' ? 'selected' : '' ?>>Coletar NFS-e Nacional</option>
                <option value="certificate_check" <?= $selectedJobType === 'certificate_check' ? 'selected' : '' ?>>Validar certificado e pasta</option>
            </select>
        </label>
    </div>
    <div class="notice subtle">
        Use ações pontuais com cuidado. O robô CT-e avança a fila de NSU em ciclos seguros e exige uma empresa específica.
    </div>
    <div class="form-actions">
        <button class="primary" name="run_job" value="1">Executar rotina</button>
    </div>
</form>

<section class="card card-compact">
    <h2>Histórico</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Empresa</th><th>Rotina</th><th>Status</th><th>Criados</th><th>Atualizados</th><th>Erros</th><th>Início</th><th>Fim</th><th>Log</th></tr></thead>
            <tbody>
            <?php foreach ($jobs as $job): ?>
                <tr>
                    <td><?= h($job['company_name'] ?: 'Todas') ?></td>
                    <td><?= h($job['job_type']) ?></td>
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
