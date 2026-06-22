<?php include __DIR__ . '/layout_top.php'; ?>
<div class="page-header">
    <h1>Empresas / CNPJs</h1>
    <p>Cadastre os CNPJs diretamente no portal, envie o certificado e gerencie tudo sem planilhas.</p>
</div>

<div class="grid two">
    <form method="post" class="card form-grid">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="company_id" value="<?= h($_GET['edit_company_id'] ?? '0') ?>">
        <h2><?= !empty($editCompany['id']) ? 'Editar empresa' : 'Nova empresa' ?></h2>
        <label>Razão social
            <input type="text" name="company_name" value="<?= h($editCompany['company_name'] ?? '') ?>" required>
        </label>
        <label>CNPJ
            <input type="text" name="company_cnpj" value="<?= h($editCompany['cnpj'] ?? '') ?>" required>
        </label>
        <label>Pasta padrão por empresa (opcional)
            <input type="text" name="default_download_dir" value="<?= h($editCompany['default_download_dir'] ?? '') ?>" placeholder="/dados/xmls ou deixe em branco para usar a configuração geral">
        </label>
        <label class="checkbox-inline">
            <input type="checkbox" name="is_active" value="1" <?= (!isset($editCompany['is_active']) || $editCompany['is_active']) ? 'checked' : '' ?>>
            Empresa ativa para coleta
        </label>
        <div class="actions">
            <button class="primary" name="save_company" value="1"><?= !empty($editCompany['id']) ? 'Salvar alterações' : 'Cadastrar empresa' ?></button>
            <?php if (!empty($editCompany['id'])): ?>
                <a class="button secondary" href="<?= h(base_url('?page=companies')) ?>">Nova empresa</a>
            <?php endif; ?>
        </div>
    </form>

    <form method="post" enctype="multipart/form-data" class="card form-grid">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <h2>Certificado A1 por empresa</h2>
        <label>Empresa
            <select name="certificate_company_id" required>
                <option value="">Selecione</option>
                <?php foreach ($companies as $co): ?>
                    <option value="<?= h((string)$co['id']) ?>" <?= (string)($editCompany['id'] ?? '') === (string)$co['id'] ? 'selected' : '' ?>><?= h($co['company_name']) ?> — <?= h($co['cnpj']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Arquivo .pfx / .p12
            <input type="file" name="certificate" accept=".pfx,.p12" required>
        </label>
        <label>Senha do certificado
            <input type="password" name="certificate_password" required>
        </label>
        <button class="primary" name="upload_certificate" value="1">Enviar certificado</button>
    </form>
</div>

<div class="grid one">
    <form method="post" class="card form-grid">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <h2>Verificação rápida</h2>
        <label>Empresa
            <select name="check_company_id" required>
                <option value="">Selecione</option>
                <?php foreach ($companies as $co): ?>
                    <option value="<?= h((string)$co['id']) ?>" <?= (string)($editCompany['id'] ?? '') === (string)$co['id'] ? 'selected' : '' ?>><?= h($co['company_name']) ?> — <?= h($co['cnpj']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="primary" name="check_company_certificate" value="1">Validar certificado e pasta</button>
    </form>
</div>

<section class="card">
    <h2>Empresas cadastradas</h2>
    <table class="table">
        <thead><tr><th>Empresa</th><th>CNPJ</th><th>Pasta</th><th>Ativa</th><th>Certificado</th><th>Diagnóstico</th><th>Docs</th><th>Ação</th></tr></thead>
        <tbody>
        <?php foreach ($companies as $co): ?>
            <?php $cert = $companyCertificates[$co['id']] ?? null; $health = $companyHealth[$co['id']] ?? null; ?>
            <tr>
                <td><?= h($co['company_name']) ?></td>
                <td><?= h($co['cnpj']) ?></td>
                <td><small><?= h($co['default_download_dir'] ?: $settings['default_download_dir']) ?></small></td>
                <td><?= !empty($co['is_active']) ? 'Sim' : 'Não' ?></td>
                <td>
                    <?php if ($cert): ?>
                        <strong><?= h($cert['filename']) ?></strong><br>
                        <small>Validade: <?= h(format_date($cert['valid_to'])) ?></small>
                    <?php else: ?>
                        <span>Sem certificado</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($health): ?>
                        <strong><?= h($health['certificate']['status']) ?></strong><br>
                        <small><?= h($health['certificate']['message']) ?></small><br>
                        <small><?= h($health['path_preview']) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= h((string)($health['documents_count'] ?? 0)) ?></td>
                <td><a href="<?= h(base_url('?page=companies&edit_company_id=' . $co['id'])) ?>">Editar</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$companies): ?>
            <tr><td colspan="8">Nenhuma empresa cadastrada.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php include __DIR__ . '/layout_bottom.php'; ?>
