<?php include __DIR__ . '/layout_top.php'; ?>
<?php
$selectedCompanyId = (string)($selectedRescanCompanyId ?? ($_GET['company_id'] ?? '0'));
$selectedCompany = $selectedCompanyId !== '0' ? $repo->findCompany((int)$selectedCompanyId) : null;
$prefix = $selectedCompany ? 'nfe_' . (int)$selectedCompany['id'] . '_' : '';
$ultNsu = $selectedCompany ? $repo->getSetting($prefix . 'ult_nsu', '0') : '-';
$maxNsu = $selectedCompany ? $repo->getSetting($prefix . 'max_nsu', '0') : '-';
$lastCheckAt = $selectedCompany ? $repo->getSetting($prefix . 'last_check_at', '') : '';
$cooldownUntil = $selectedCompany ? $repo->getSetting($prefix . 'cooldown_until', '') : '';
?>
<div class="page-header">
    <h1>Revarrer NF-e</h1>
    <p>Reinicie o cursor local de NF-e quando houver suspeita de perda de resumos ou histórico incompleto.</p>
</div>

<div class="notice highlight">
    Esta rotina não apaga XMLs completos. Ela reinicia apenas o cursor local de NSU da empresa. Use com cuidado e aguarde pelo menos 1 hora após qualquer consulta NF-e.
</div>

<form method="get" class="card card-compact form-grid">
    <input type="hidden" name="page" value="nfe_rescan">
    <label>Empresa
        <select name="company_id" onchange="this.form.submit()">
            <option value="0">Selecione</option>
            <?php foreach (($companies ?? []) as $co): ?>
                <option value="<?= h((string)$co['id']) ?>" <?= $selectedCompanyId === (string)$co['id'] ? 'selected' : '' ?>><?= h($co['company_name']) ?> - <?= h($co['cnpj']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
</form>

<?php if ($selectedCompany): ?>
    <section class="grid four documents-summary">
        <div class="card stat neutral"><strong><?= h((string)$ultNsu) ?></strong><span>ultNSU atual</span></div>
        <div class="card stat neutral"><strong><?= h((string)$maxNsu) ?></strong><span>maxNSU atual</span></div>
        <div class="card stat neutral"><strong><?= h($lastCheckAt ? format_date((string)$lastCheckAt) : '-') ?></strong><span>Última consulta</span></div>
        <div class="card stat warn"><strong><?= h($cooldownUntil ? format_date((string)$cooldownUntil) : '-') ?></strong><span>Bloqueio local até</span></div>
    </section>

    <form method="post" class="card form-grid">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="company_id" value="<?= h((string)$selectedCompany['id']) ?>">
        <h2>Confirmar revarredura NF-e</h2>
        <p>Digite <strong>REINICIAR NFE</strong> para zerar o cursor local desta empresa. Depois execute o <strong>Robô NF-e / NFC-e até último NSU</strong> no Radar de XML.</p>
        <label>Confirmação
            <input type="text" name="confirm_text" placeholder="REINICIAR NFE">
        </label>
        <div class="form-actions">
            <button class="primary" name="reset_nfe_nsu" value="1">Reiniciar cursor NF-e</button>
        </div>
    </form>
<?php endif; ?>
<?php include __DIR__ . '/layout_bottom.php'; ?>
