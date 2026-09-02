<?php
include __DIR__ . '/layout_top.php';
$edit = $editUser ?? null;
$isAdminProfile = (($edit['role'] ?? 'user') === 'admin');
$canViewRevenue = $isAdminProfile || !empty($edit['can_view_revenue']);
$canViewProfit = $isAdminProfile || ($canViewRevenue && !empty($edit['can_view_cost']));
?>
<div class="page-header split-header">
    <div>
        <h1>Usuarios</h1>
        <p>Controle de acesso ao Fiscal Hub por perfil operacional.</p>
    </div>
</div>

<div class="grid two">
    <form method="post" class="card form-grid" id="user-form">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="user_id" value="<?= h((string)($edit['id'] ?? 0)) ?>">
        <h2><?= $edit ? 'Editar usuario' : 'Novo usuario' ?></h2>
        <label>Nome
            <input type="text" name="name" required value="<?= h((string)($edit['name'] ?? '')) ?>">
        </label>
        <label>E-mail
            <input type="email" name="email" required value="<?= h((string)($edit['email'] ?? '')) ?>">
        </label>
        <label>Senha <?= $edit ? '(preencha apenas para trocar)' : '' ?>
            <input type="password" name="password" <?= $edit ? '' : 'required' ?>>
        </label>
        <label>Perfil
            <select name="role" id="user-role">
                <option value="admin" <?= (($edit['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Administrador</option>
                <option value="user" <?= (($edit['role'] ?? 'user') === 'user') ? 'selected' : '' ?>>Usuario</option>
            </select>
        </label>
        <label class="checkbox-inline"><input type="checkbox" name="is_active" value="1" <?= empty($edit) || !empty($edit['is_active']) ? 'checked' : '' ?>> Usuario ativo</label>
        <label class="checkbox-inline"><input type="checkbox" name="can_view_revenue" value="1" id="user-can-view-revenue" data-user-revenue <?= $canViewRevenue ? 'checked' : '' ?>> Pode ver Faturamento</label>
        <label class="checkbox-inline"><input type="checkbox" name="can_view_cost" value="1" id="user-can-view-profit" data-user-profit <?= $canViewProfit ? 'checked' : '' ?>> Pode ver lucro</label>
        <small class="form-help">Para usuarios comuns, o acesso ao lucro somente fica disponivel quando Pode ver Faturamento estiver marcado.</small>
        <button class="primary" name="save_user" value="1">Salvar usuario</button>
    </form>

    <div class="card">
        <h2>Perfis</h2>
        <p><strong>Administrador:</strong> acessa todas as rotinas e ve Faturamento e lucro.</p>
        <p><strong>Usuario:</strong> acessa apenas os modulos e indicadores liberados pelo administrador.</p>
    </div>
</div>

<div class="card">
    <h2>Usuarios cadastrados</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Perfil</th>
                    <th>Faturamento</th>
                    <th>Lucro</th>
                    <th>Status</th>
                    <th>Acao</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($users ?? []) as $user): ?>
                    <?php $userIsAdmin = ((string)$user['role'] === 'admin'); ?>
                    <tr>
                        <td><?= h((string)$user['name']) ?></td>
                        <td><?= h((string)$user['email']) ?></td>
                        <td><?= $userIsAdmin ? 'Administrador' : 'Usuario' ?></td>
                        <td><?= ($userIsAdmin || !empty($user['can_view_revenue'])) ? 'Sim' : 'Nao' ?></td>
                        <td><?= ($userIsAdmin || (!empty($user['can_view_revenue']) && !empty($user['can_view_cost']))) ? 'Sim' : 'Nao' ?></td>
                        <td><?= !empty($user['is_active']) ? 'Ativo' : 'Inativo' ?></td>
                        <td><a class="row-action" href="<?= h(base_url('?page=users&edit_user_id=' . $user['id'])) ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                    <tr><td colspan="7">Nenhum usuario cadastrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
(function () {
    var role = document.getElementById('user-role');
    var revenue = document.getElementById('user-can-view-revenue');
    var profit = document.getElementById('user-can-view-profit');
    if (!role || !revenue || !profit) return;
    function syncUserPermissions() {
        var admin = role.value === 'admin';
        if (admin) {
            revenue.checked = true;
            profit.checked = true;
        }
        profit.disabled = !admin && !revenue.checked;
        if (!admin && !revenue.checked) profit.checked = false;
    }
    role.addEventListener('change', syncUserPermissions);
    revenue.addEventListener('change', syncUserPermissions);
    syncUserPermissions();
})();
</script>
<?php include __DIR__ . '/layout_bottom.php'; ?>
