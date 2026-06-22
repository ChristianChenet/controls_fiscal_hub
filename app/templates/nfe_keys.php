<?php include __DIR__ . '/layout_top.php'; ?>
<?php $selectedCompanyId = (string)($selectedKeyCompanyId ?? ($_GET['company_id'] ?? '0')); ?>
<div class="page-header">
    <h1>Busca NF-e por Chave</h1>
    <p>Consulte chaves específicas quando a distribuição por NSU não retornar documentos antigos.</p>
</div>

<div class="notice highlight">
    A busca por chave não usa filtro por data. Informe chaves de NF-e com 44 dígitos. Se vier apenas resumo, use ciência da operação e tente reprocessar depois.
</div>

<form method="post" class="card form-grid">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <label>Empresa
        <select name="company_id" required>
            <option value="">Selecione</option>
            <?php foreach (($companies ?? []) as $co): ?>
                <option value="<?= h((string)$co['id']) ?>" <?= $selectedCompanyId === (string)$co['id'] ? 'selected' : '' ?>><?= h($co['company_name']) ?> - <?= h($co['cnpj']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Chaves NF-e
        <textarea name="access_keys" rows="10" placeholder="Cole uma chave por linha ou uma lista com chaves de 44 dígitos"></textarea>
        <small>Limite operacional: 200 chaves por execução.</small>
    </label>
    <div class="option-grid">
        <label class="checkbox-inline">
            <input type="checkbox" name="manifest_science" value="1">
            Enviar ciência da operação para resumo pendente
        </label>
        <label class="checkbox-inline">
            <input type="checkbox" name="retry_after_science" value="1">
            Tentar baixar novamente após ciência
        </label>
    </div>
    <div class="form-actions">
        <button class="primary" name="lookup_nfe_keys" value="1">Buscar chaves NF-e</button>
    </div>
</form>

<section class="card card-compact">
    <h2>Últimas execuções</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Empresa</th><th>Rotina</th><th>Status</th><th>Criados</th><th>Atualizados</th><th>Erros</th><th>Início</th><th>Log</th></tr></thead>
            <tbody>
            <?php foreach (($jobs ?? []) as $job): ?>
                <?php if (($job['job_type'] ?? '') !== 'nfe_key_lookup') continue; ?>
                <tr>
                    <td><?= h($job['company_name'] ?: '-') ?></td>
                    <td>Busca por chave</td>
                    <td><?= h($job['status']) ?></td>
                    <td><?= h((string)$job['created_count']) ?></td>
                    <td><?= h((string)$job['updated_count']) ?></td>
                    <td><?= h((string)$job['error_count']) ?></td>
                    <td><?= h(format_date($job['started_at'])) ?></td>
                    <td class="clip-text"><small><?= nl2br_safe($job['log_text']) ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/layout_bottom.php'; ?>
