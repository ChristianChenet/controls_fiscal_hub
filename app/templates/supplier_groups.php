<?php include __DIR__ . '/layout_top.php'; ?>
<?php
$filters = $supplierGroupFilters ?? [];
$groups = $supplierGroups ?? [];
$selectedGroupId = (int)($selectedSupplierGroupId ?? 0);
$selectedGroup = null;
foreach ($groups as $group) {
    if ((int)$group['id'] === $selectedGroupId) {
        $selectedGroup = $group;
        break;
    }
}
?>

<div class="page-header split-header">
    <div>
        <h1>Grupos de fornecedores</h1>
        <p>Cadastre grupos e organize os emissores das entradas para facilitar filtros e conferências.</p>
    </div>
</div>

<section class="supplier-groups-layout">
    <div class="card supplier-groups-panel">
        <div class="grid-toolbar">
            <div>
                <h2>Grupos</h2>
                <small>Um fornecedor pode pertencer a um grupo por vez.</small>
            </div>
        </div>

        <form method="post" class="supplier-group-form">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="edit_group_id" value="<?= h((string)($selectedGroup['id'] ?? 0)) ?>">
            <label>Descrição do grupo
                <input type="text" name="description" placeholder="Ex.: Marketplaces" value="<?= h((string)($selectedGroup['description'] ?? '')) ?>" required>
            </label>
            <div class="inline">
                <button class="primary button-compact" name="save_group" value="1"><?= $selectedGroup ? 'Salvar grupo' : 'Criar grupo' ?></button>
                <?php if ($selectedGroup): ?>
                    <a class="button-link button-compact" href="<?= h(base_url('?page=supplier_groups')) ?>">Novo</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="supplier-group-list">
            <?php foreach ($groups as $group): ?>
                <?php $active = (int)$group['id'] === $selectedGroupId; ?>
                <div class="supplier-group-card <?= $active ? 'is-active' : '' ?>">
                    <a href="<?= h(base_url('?page=supplier_groups&group_id=' . (int)$group['id'])) ?>">
                        <strong><?= h((string)$group['description']) ?></strong>
                        <span><?= h((string)($group['supplier_count'] ?? 0)) ?> fornecedor(es)</span>
                    </a>
                    <form method="post" onsubmit="return confirm('Remover este grupo? Os fornecedores ficarão sem grupo.');">
                        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="delete_group_id" value="<?= h((string)$group['id']) ?>">
                        <button class="row-action row-action-button" name="delete_group" value="1">Remover</button>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if (!$groups): ?>
                <div class="empty-state">Nenhum grupo cadastrado.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card supplier-groups-panel">
        <div class="grid-toolbar">
            <div>
                <h2>Fornecedores emissores</h2>
                <small>Filtre por tipo de entrada e adicione os fornecedores ao grupo desejado.</small>
            </div>
        </div>

        <form method="get" class="supplier-filter-form">
            <input type="hidden" name="page" value="supplier_groups">
            <input type="hidden" name="group_id" value="<?= h((string)$selectedGroupId) ?>">
            <div class="form-row four">
                <label>Tipo
                    <select name="doc_type">
                        <option value="">NFS-e, NF-e e CT-e</option>
                        <?php foreach (['NFSE' => 'NFS-e', 'NFE' => 'NF-e', 'CTE' => 'CT-e'] as $type => $label): ?>
                            <option value="<?= h($type) ?>" <?= (($filters['doc_type'] ?? '') === $type) ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Fornecedor
                    <input type="text" name="q" placeholder="Nome ou CNPJ" value="<?= h((string)($filters['q'] ?? '')) ?>">
                </label>
                <label>Origem
                    <input type="text" name="source_q" placeholder="Ex.: nfse_pdf" value="<?= h((string)($filters['source_q'] ?? '')) ?>">
                </label>
                <label>Data inicial
                    <input type="date" name="date_start" value="<?= h((string)($filters['date_start'] ?? '')) ?>">
                </label>
                <label>Data final
                    <input type="date" name="date_end" value="<?= h((string)($filters['date_end'] ?? '')) ?>">
                </label>
                <label class="cfop-ignore-field">Sem grupo
                    <span class="cfop-ignore-line">
                        <input type="checkbox" name="without_group" value="1" <?= !empty($filters['without_group']) ? 'checked' : '' ?>>
                        <span>Mostrar só sem grupo</span>
                    </span>
                </label>
                <label class="form-action-label">
                    <span>&nbsp;</span>
                    <button class="primary button-compact">Filtrar</button>
                </label>
            </div>
        </form>

        <form method="post" class="supplier-assignment-form">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="doc_type" value="<?= h((string)($filters['doc_type'] ?? '')) ?>">
            <input type="hidden" name="q" value="<?= h((string)($filters['q'] ?? '')) ?>">
            <input type="hidden" name="source_q" value="<?= h((string)($filters['source_q'] ?? '')) ?>">
            <input type="hidden" name="date_start" value="<?= h((string)($filters['date_start'] ?? '')) ?>">
            <input type="hidden" name="date_end" value="<?= h((string)($filters['date_end'] ?? '')) ?>">
            <input type="hidden" name="without_group" value="<?= h((string)($filters['without_group'] ?? '')) ?>">
            <input type="hidden" name="group_id" value="<?= h((string)$selectedGroupId) ?>">

            <div class="supplier-assign-toolbar">
                <label>Adicionar selecionados em
                    <select name="target_group_id" required>
                        <option value="">Selecione o grupo</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?= h((string)$group['id']) ?>" <?= (int)$group['id'] === $selectedGroupId ? 'selected' : '' ?>><?= h((string)$group['description']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="primary button-compact" name="add_suppliers" value="1">Adicionar ao grupo</button>
            </div>
            <div class="supplier-quick-filter">
                <label>Filtro rápido na lista
                    <input type="text" data-supplier-quick-filter placeholder="Digite parte do nome, CNPJ ou grupo sem recarregar">
                </label>
                <div class="supplier-quick-actions">
                    <button class="button-compact" type="button" data-mark-visible-suppliers>Marcar todos filtrados</button>
                    <button class="button-compact" type="button" data-clear-visible-suppliers>Limpar marcação</button>
                    <small data-supplier-visible-count></small>
                </div>
            </div>

            <div class="supplier-table-wrap">
                <table class="table supplier-table">
                    <thead>
                        <tr>
                            <th class="select-col"><input type="checkbox" data-select-suppliers></th>
                            <th>Fornecedor</th>
                            <th>Grupo atual</th>
                            <th>Entradas</th>
                            <th>NFS-e</th>
                            <th>NF-e</th>
                            <th>CT-e</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach (($supplierOptions ?? []) as $idx => $supplier): ?>
                        <?php $supplierSearch = trim((string)$supplier['issuer_name'] . ' ' . (string)$supplier['issuer_cnpj'] . ' ' . (string)($supplier['group_description'] ?? '')); ?>
                        <tr data-supplier-row data-supplier-search="<?= h(mb_strtolower($supplierSearch, 'UTF-8')) ?>">
                            <td>
                                <input type="checkbox" data-supplier-checkbox>
                                <input type="hidden" name="supplier_cnpj[]" value="<?= h((string)$supplier['issuer_cnpj']) ?>" disabled data-supplier-cnpj>
                                <input type="hidden" name="supplier_name[]" value="<?= h((string)$supplier['issuer_name']) ?>" disabled data-supplier-name>
                            </td>
                            <td><strong><?= h((string)$supplier['issuer_name']) ?></strong><br><small><?= h((string)$supplier['issuer_cnpj']) ?></small></td>
                            <td><?= h((string)($supplier['group_description'] ?? '')) ?></td>
                            <td><?= h((string)($supplier['documents_count'] ?? 0)) ?></td>
                            <td><?= h((string)($supplier['nfse_count'] ?? 0)) ?></td>
                            <td><?= h((string)($supplier['nfe_count'] ?? 0)) ?></td>
                            <td><?= h((string)($supplier['cte_count'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($supplierOptions)): ?>
                        <tr><td colspan="7">Nenhum fornecedor encontrado.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</section>

<?php if ($selectedGroup): ?>
<section class="card supplier-members-card">
    <div class="grid-toolbar">
        <div>
            <h2>Fornecedores em <?= h((string)$selectedGroup['description']) ?></h2>
            <small>Remova fornecedores que não pertencem mais a este grupo.</small>
        </div>
    </div>
    <div class="supplier-chip-list">
        <?php foreach (($selectedSupplierGroupMembers ?? []) as $member): ?>
            <form method="post" class="supplier-chip">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="group_id" value="<?= h((string)$selectedGroupId) ?>">
                <input type="hidden" name="remove_issuer_cnpj" value="<?= h((string)$member['issuer_cnpj']) ?>">
                <span><strong><?= h((string)$member['issuer_name']) ?></strong><small><?= h((string)$member['issuer_cnpj']) ?></small></span>
                <button class="row-action row-action-button" name="remove_supplier" value="1">Remover</button>
            </form>
        <?php endforeach; ?>
        <?php if (empty($selectedSupplierGroupMembers)): ?>
            <div class="empty-state">Este grupo ainda não tem fornecedores.</div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<script>
(function () {
    var quickFilter = document.querySelector('[data-supplier-quick-filter]');
    var visibleCount = document.querySelector('[data-supplier-visible-count]');
    function normalize(value) {
        return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }
    function visibleRows() {
        return Array.from(document.querySelectorAll('[data-supplier-row]')).filter(function (row) {
            return row.style.display !== 'none';
        });
    }
    function syncHiddenInputs(checkbox) {
        var row = checkbox.closest('tr');
        row?.querySelectorAll('[data-supplier-cnpj], [data-supplier-name]').forEach(function (input) {
            input.disabled = !checkbox.checked;
        });
    }
    function updateVisibleCount() {
        if (!visibleCount) return;
        var rows = visibleRows();
        visibleCount.textContent = rows.length + ' fornecedor(es) visível(is)';
    }
    function applyQuickFilter() {
        var text = normalize(quickFilter ? quickFilter.value : '');
        document.querySelectorAll('[data-supplier-row]').forEach(function (row) {
            var haystack = normalize(row.getAttribute('data-supplier-search') || '');
            row.style.display = !text || haystack.indexOf(text) >= 0 ? '' : 'none';
        });
        updateVisibleCount();
    }
    function setRowsChecked(rows, checked) {
        rows.forEach(function (row) {
            var checkbox = row.querySelector('[data-supplier-checkbox]');
            if (!checkbox) return;
            checkbox.checked = checked;
            syncHiddenInputs(checkbox);
        });
    }
    document.querySelector('[data-select-suppliers]')?.addEventListener('change', function (event) {
        setRowsChecked(visibleRows(), event.target.checked);
    });
    document.querySelector('[data-mark-visible-suppliers]')?.addEventListener('click', function () {
        setRowsChecked(visibleRows(), true);
    });
    document.querySelector('[data-clear-visible-suppliers]')?.addEventListener('click', function () {
        setRowsChecked(visibleRows(), false);
        var selectAll = document.querySelector('[data-select-suppliers]');
        if (selectAll) selectAll.checked = false;
    });
    document.querySelectorAll('[data-supplier-checkbox]').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            syncHiddenInputs(checkbox);
        });
    });
    quickFilter?.addEventListener('input', applyQuickFilter);
    updateVisibleCount();
})();
</script>

<?php include __DIR__ . '/layout_bottom.php'; ?>
