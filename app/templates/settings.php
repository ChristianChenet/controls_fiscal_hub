<?php include __DIR__ . '/layout_top.php'; ?>

<?php
$selectedAutoCte = array_values(array_filter(array_map('intval', explode(',', (string)($settings['auto_cte_company_ids'] ?? '')))));
if (!$selectedAutoCte && (int)($settings['auto_cte_company_id'] ?? 0) > 0) { $selectedAutoCte = [(int)$settings['auto_cte_company_id']]; }
$selectedAutoNfe = array_values(array_filter(array_map('intval', explode(',', (string)($settings['auto_nfe_company_ids'] ?? '')))));
if (!$selectedAutoNfe && (int)($settings['auto_nfe_company_id'] ?? 0) > 0) { $selectedAutoNfe = [(int)$settings['auto_nfe_company_id']]; }
$selectedAutoNfse = array_values(array_filter(array_map('intval', explode(',', (string)($settings['auto_nfse_company_ids'] ?? '')))));
if (!$selectedAutoNfse && (int)($settings['auto_nfse_company_id'] ?? 0) > 0) { $selectedAutoNfse = [(int)$settings['auto_nfse_company_id']]; }
$activeAutomationCompanyIds = array_map(static fn(array $co): int => (int)$co['id'], array_filter(($companies ?? []), static fn(array $co): bool => !empty($co['is_active'])));
$autoCteAll = ($settings['auto_cte_all_companies'] ?? '0') === '1' || ($activeAutomationCompanyIds && count(array_intersect($activeAutomationCompanyIds, $selectedAutoCte)) === count($activeAutomationCompanyIds));
$autoNfeAll = ($settings['auto_nfe_all_companies'] ?? '0') === '1' || ($activeAutomationCompanyIds && count(array_intersect($activeAutomationCompanyIds, $selectedAutoNfe)) === count($activeAutomationCompanyIds));
$autoNfseAll = ($settings['auto_nfse_all_companies'] ?? '0') === '1' || ($activeAutomationCompanyIds && count(array_intersect($activeAutomationCompanyIds, $selectedAutoNfse)) === count($activeAutomationCompanyIds));
?>
<div class="page-header">
    <h1>Configurações</h1>
    <p>Armazenamento global, conectores e estrutura das pastas de download.</p>
</div>

<div class="grid two">
    <form method="post" enctype="multipart/form-data" class="card form-grid">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <h2>Identidade do cliente</h2>
        <label>Nome exibido no topo
            <input type="text" name="client_display_name" value="<?= h((string)($settings['client_display_name'] ?? 'Cliente integrado')) ?>">
        </label>
        <label>Descricao curta
            <input type="text" name="client_label" value="<?= h((string)($settings['client_label'] ?? 'Ambiente fiscal')) ?>">
        </label>
        <label>Logo do cliente
            <input type="file" name="client_logo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
            <small>Use uma imagem horizontal ou quadrada em JPG, PNG ou WEBP.</small>
        </label>
        <h2>Armazenamento global</h2>
        <label>Pasta padrão global de download no servidor
            <input type="text" name="default_download_dir" value="<?= h($settings['default_download_dir']) ?>">
        </label>
        <label>Estrutura de pastas
            <select name="storage_path_mode">
                <option value="flat" <?= $settings['storage_path_mode'] === 'flat' ? 'selected' : '' ?>>Tudo na mesma pasta</option>
                <option value="segmented" <?= $settings['storage_path_mode'] === 'segmented' ? 'selected' : '' ?>>/CNPJ/TIPO/ANO/MÊS</option>
                <option value="template" <?= $settings['storage_path_mode'] === 'template' ? 'selected' : '' ?>>Template personalizado</option>
            </select>
        </label>
        <label>Template personalizado
            <input type="text" name="storage_path_template" value="<?= h($settings['storage_path_template']) ?>">
            <small>Placeholders: {base} {cnpj} {doc_type} {year} {month} {day}</small>
        </label>

        <h2>SEFAZ</h2>
        <label>Ambiente SEFAZ
            <select name="sefaz_environment">
                <option value="1" <?= $settings['sefaz_environment'] === '1' ? 'selected' : '' ?>>Produção</option>
                <option value="2" <?= $settings['sefaz_environment'] === '2' ? 'selected' : '' ?>>Homologação</option>
            </select>
        </label>
        <label>UF autor (código IBGE da UF, ex.: 41 = PR)
            <input type="text" name="sefaz_uf_author" value="<?= h($settings['sefaz_uf_author']) ?>">
        </label>

        <h2>NF-e / NFC-e</h2>
        <label>URL distribuição
            <input type="text" name="nfe_distribution_url" value="<?= h($settings['nfe_distribution_url']) ?>">
        </label>
        <label>SOAP Action distribuição
            <input type="text" name="nfe_distribution_action" value="<?= h($settings['nfe_distribution_action']) ?>">
        </label>
        <label>URL recepção de evento
            <input type="text" name="nfe_recepcaoevento_url" value="<?= h($settings['nfe_recepcaoevento_url']) ?>">
        </label>
        <label>SOAP Action recepção de evento
            <input type="text" name="nfe_recepcaoevento_action" value="<?= h($settings['nfe_recepcaoevento_action']) ?>">
        </label>

        <h2>CT-e</h2>
        <label>URL distribuição
            <input type="text" name="cte_distribution_url" value="<?= h($settings['cte_distribution_url']) ?>">
        </label>
        <label>SOAP Action distribuição
            <input type="text" name="cte_distribution_action" value="<?= h($settings['cte_distribution_action']) ?>">
        </label>
        <h2>Automação CT-e</h2>
        <label class="checkbox-inline">
            <input type="checkbox" name="auto_cte_enabled" value="1" <?= ($settings['auto_cte_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
            Ativar robô automático de CT-e
        </label>
        <label>Empresas da automação CT-e
            <label class="checkbox-inline">
                <input type="checkbox" name="auto_cte_all_companies" value="1" data-select-all-target="auto_cte_company_ids" <?= $autoCteAll ? 'checked' : '' ?>>
                Executar em todos os CNPJs ativos
            </label>
            <select name="auto_cte_company_ids[]" multiple size="6">
                <?php foreach (($companies ?? []) as $co): ?>
                    <option value="<?= h((string)$co['id']) ?>" <?= in_array((int)$co['id'], $selectedAutoCte, true) ? 'selected' : '' ?>><?= h($co['company_name']) ?> - <?= h($co['cnpj']) ?></option>
                <?php endforeach; ?>
            </select>
            <small>Use o checkbox acima para marcar todos rapidamente. O worker processa uma empresa por vez e grava log individual.</small>
        </label>
        <label>Recuar NSU CT-e na próxima execução
            <input type="text" name="auto_cte_rewind_nsu_once" value="<?= h((string)($settings['auto_cte_rewind_nsu_once'] ?? '0')) ?>">
            <small>Use apenas quando o portal indicar maxNSU, mas houver suspeita de documentos faltantes. Ex.: 500 ou 1000. O recuo é aplicado uma única vez e volta para 0 automaticamente.</small>
        </label>
        <label>Intervalo entre execuções automáticas (minutos)
            <input type="text" name="auto_cte_interval_minutes" value="<?= h((string)($settings['auto_cte_interval_minutes'] ?? '30')) ?>">
        </label>
        <label>Ciclos máximos do robô por execução
            <input type="text" name="cte_robot_max_cycles" value="<?= h((string)($settings['cte_robot_max_cycles'] ?? '10')) ?>">
        </label>
        <label>Tempo máximo por execução (segundos)
            <input type="text" name="cte_robot_time_limit_seconds" value="<?= h((string)($settings['cte_robot_time_limit_seconds'] ?? '240')) ?>">
        </label>
        <h3>Recuo CT-e por CNPJ</h3>
        <div class="table-wrap compact-table">
            <table>
                <thead><tr><th>Empresa</th><th>ultNSU</th><th>maxNSU</th><th>Recuo na próxima execução</th></tr></thead>
                <tbody>
                <?php foreach (($companies ?? []) as $co): ?>
                    <?php $rw = $automationRewinds[(int)$co['id']] ?? []; ?>
                    <tr>
                        <td><?= h($co['company_name']) ?><br><small><?= h($co['cnpj']) ?></small></td>
                        <td><?= h((string)($rw['cte_ult_nsu'] ?? '0')) ?></td>
                        <td><?= h((string)($rw['cte_max_nsu'] ?? '0')) ?></td>
                        <td><input type="number" min="0" max="50000" step="1" name="auto_cte_rewind_company[<?= h((string)$co['id']) ?>]" value="<?= h((string)($rw['cte'] ?? '0')) ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h2>NFS-e Nacional</h2>
        <label>Base URL ADN
            <input type="text" name="nfse_base_url" value="<?= h($settings['nfse_base_url']) ?>">
        </label>
        <label>Path de distribuição
            <input type="text" name="nfse_distribution_path" value="<?= h($settings['nfse_distribution_path']) ?>">
            <small>Padrao atual do ADN Contribuintes: /contribuintes/DFe/{nsu}. O portal acrescenta o CNPJ de consulta na URL.</small>
        </label>
        <label>Autenticação NFS-e Nacional
            <select name="nfse_auth_type">
                <option value="certificate" <?= $settings['nfse_auth_type'] === 'certificate' ? 'selected' : '' ?>>Certificado</option>
                <option value="token" <?= $settings['nfse_auth_type'] === 'token' ? 'selected' : '' ?>>Bearer Token</option>
            </select>
        </label>
        <label>Token NFS-e Nacional
            <input type="password" name="nfse_token" value="<?= h($settings['nfse_token']) ?>">
        </label>
        <label>NSUs por execucao NFS-e
            <input type="text" name="auto_nfse_nsu_limit" value="<?= h((string)($settings['auto_nfse_nsu_limit'] ?? '10')) ?>">
            <small>Limite conservador. O ADN pode bloquear excesso de requisicoes com HTTP 429.</small>
        </label>
        <h2>Automacao NFS-e Nacional</h2>
        <label class="checkbox-inline">
            <input type="checkbox" name="auto_nfse_enabled" value="1" <?= ($settings['auto_nfse_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
            Ativar robo automatico de NFS-e Nacional
        </label>
        <label>Empresas da automação NFS-e
            <label class="checkbox-inline">
                <input type="checkbox" name="auto_nfse_all_companies" value="1" data-select-all-target="auto_nfse_company_ids" <?= $autoNfseAll ? 'checked' : '' ?>>
                Executar em todos os CNPJs ativos
            </label>
            <select name="auto_nfse_company_ids[]" multiple size="6">
                <?php foreach (($companies ?? []) as $co): ?>
                    <option value="<?= h((string)$co['id']) ?>" <?= in_array((int)$co['id'], $selectedAutoNfse, true) ? 'selected' : '' ?>><?= h($co['company_name']) ?> - <?= h($co['cnpj']) ?></option>
                <?php endforeach; ?>
            </select>
            <small>Use o checkbox acima para marcar todos rapidamente. O worker processa uma empresa por vez e grava log individual.</small>
        </label>
        <label>Intervalo entre execuções automáticas NFS-e (minutos)
            <input type="text" name="auto_nfse_interval_minutes" value="<?= h((string)($settings['auto_nfse_interval_minutes'] ?? '60')) ?>">
            <small>Minimo operacional aplicado pelo worker: 60 minutos.</small>
        </label>
        <h2>Automação NF-e / NFC-e</h2>
        <label class="checkbox-inline">
            <input type="checkbox" name="auto_nfe_enabled" value="1" <?= ($settings['auto_nfe_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
            Ativar robô automático de NF-e / NFC-e
        </label>
        <label>Empresas da automação NF-e
            <label class="checkbox-inline">
                <input type="checkbox" name="auto_nfe_all_companies" value="1" data-select-all-target="auto_nfe_company_ids" <?= $autoNfeAll ? 'checked' : '' ?>>
                Executar em todos os CNPJs ativos
            </label>
            <select name="auto_nfe_company_ids[]" multiple size="6">
                <?php foreach (($companies ?? []) as $co): ?>
                    <option value="<?= h((string)$co['id']) ?>" <?= in_array((int)$co['id'], $selectedAutoNfe, true) ? 'selected' : '' ?>><?= h($co['company_name']) ?> - <?= h($co['cnpj']) ?></option>
                <?php endforeach; ?>
            </select>
            <small>Use o checkbox acima para marcar todos rapidamente. O worker processa uma empresa por vez e grava log individual.</small>
        </label>
        <label>Recuar NSU NF-e na próxima execução
            <input type="text" name="auto_nfe_rewind_nsu_once" value="<?= h((string)($settings['auto_nfe_rewind_nsu_once'] ?? '0')) ?>">
            <small>Use apenas para diagnóstico. Reprocessa os últimos NSUs uma vez, sem zerar o cursor e com deduplicação dos XMLs já existentes.</small>
        </label>
        <label class="checkbox-inline">
            <input type="checkbox" name="auto_nfe_manifest_science" value="1" <?= ($settings['auto_nfe_manifest_science'] ?? '0') === '1' ? 'checked' : '' ?>>
            Enviar ciência da operação para NF-e pendente
        </label>
        <label>Intervalo entre execuções automáticas NF-e (minutos)
            <input type="text" name="auto_nfe_interval_minutes" value="<?= h((string)($settings['auto_nfe_interval_minutes'] ?? '60')) ?>">
        </label>
        <label>Ciclos máximos NF-e por execução
            <input type="text" name="nfe_robot_max_cycles" value="<?= h((string)($settings['nfe_robot_max_cycles'] ?? '4')) ?>">
        </label>
        <label>Tempo máximo NF-e por execução (segundos)
            <input type="text" name="nfe_robot_time_limit_seconds" value="<?= h((string)($settings['nfe_robot_time_limit_seconds'] ?? '180')) ?>">
        </label>
        <label>Limite de ciência por execução
            <input type="text" name="nfe_science_limit_per_run" value="<?= h((string)($settings['nfe_science_limit_per_run'] ?? '30')) ?>">
        </label>
        <h3>Recuo NF-e por CNPJ</h3>
        <div class="table-wrap compact-table">
            <table>
                <thead><tr><th>Empresa</th><th>ultNSU</th><th>maxNSU</th><th>Recuo na próxima execução</th></tr></thead>
                <tbody>
                <?php foreach (($companies ?? []) as $co): ?>
                    <?php $rw = $automationRewinds[(int)$co['id']] ?? []; ?>
                    <tr>
                        <td><?= h($co['company_name']) ?><br><small><?= h($co['cnpj']) ?></small></td>
                        <td><?= h((string)($rw['nfe_ult_nsu'] ?? '0')) ?></td>
                        <td><?= h((string)($rw['nfe_max_nsu'] ?? '0')) ?></td>
                        <td><input type="number" min="0" max="50000" step="1" name="auto_nfe_rewind_company[<?= h((string)$co['id']) ?>]" value="<?= h((string)($rw['nfe'] ?? '0')) ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button class="primary" name="save_settings" value="1">Salvar configurações</button>
    </form>

    <div class="card">
        <h2>Exemplos de armazenamento</h2>
        <p><strong>Tudo na mesma pasta</strong><br><code><?= h($settings['default_download_dir']) ?></code></p>
        <p><strong>Estrutura padrão</strong><br><code><?= h($settings['default_download_dir']) ?>/12345678000199/NFE/2026/05</code></p>
        <p><strong>Template</strong><br><code>{base}/{year}/{month}/{doc_type}</code></p>
        <p>O upload de certificado continua na tela <strong>Empresas</strong>, um certificado por CNPJ.</p>
        <p><strong>Automação CT-e</strong><br>O worker do Docker executa o robô em segundo plano quando a opção estiver ativa. Os XMLs completos são salvos na estrutura configurada acima.</p>
    </div>
    <div class="card">
        <h2>Historico das automacoes</h2>
        <p>Ultimas execucoes dos robos automaticos e das rotinas robo acionadas manualmente.</p>
        <div class="table-wrap compact-table">
            <table>
                <thead>
                    <tr>
                        <th>Rotina</th>
                        <th>Empresa</th>
                        <th>Status</th>
                        <th>Inicio</th>
                        <th>Fim</th>
                        <th>Criados</th>
                        <th>Atualizados</th>
                        <th>Erros</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($automationJobs ?? []) as $job): ?>
                        <?php
                            $routineLabel = match ((string)$job['job_type']) {
                                'cte_until_max' => 'Robo CT-e',
                                'nfe_until_max' => 'Robo NF-e',
                                'nfe_until_max_science' => 'Robo NF-e + ciencia',
                                'nfse' => 'Robo NFS-e Nacional',
                                default => (string)$job['job_type'],
                            };
                        ?>
                        <tr title="<?= h((string)($job['log_text'] ?? '')) ?>">
                            <td><?= h($routineLabel) ?></td>
                            <td><?= h((string)($job['company_name'] ?? '-')) ?></td>
                            <td><?= h((string)$job['status']) ?></td>
                            <td><?= h(format_date((string)($job['started_at'] ?? ''))) ?></td>
                            <td><?= h(format_date((string)($job['finished_at'] ?? ''))) ?></td>
                            <td><?= h((string)($job['created_count'] ?? 0)) ?></td>
                            <td><?= h((string)($job['updated_count'] ?? 0)) ?></td>
                            <td><?= h((string)($job['error_count'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($automationJobs)): ?>
                        <tr><td colspan="8">Nenhuma execucao registrada ainda.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('[data-select-all-target]').forEach((checkbox) => {
    const syncSelection = () => {
        const select = document.querySelector(`select[name="${checkbox.dataset.selectAllTarget}[]"]`);
        if (!select) return;
        Array.from(select.options).forEach((option) => {
            option.selected = checkbox.checked && !option.disabled;
        });
    };
    checkbox.addEventListener('change', syncSelection);
});
</script>
<?php include __DIR__ . '/layout_bottom.php'; ?>
