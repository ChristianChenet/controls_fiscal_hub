<?php include __DIR__ . '/layout_top.php'; ?>
<?php $edit = $editUser ?? null; ?>
<div class="page-header split-header">
    <div>
        <h1>Usuarios</h1>
        <p>Controle de acesso ao Fiscal Hub por perfil operacional.</p>
    </div>
</div>

<div class="grid two">
    <form method="post" class="card form-grid">
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
            <select name="role">
                <option value="admin" <?= (($edit['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Administrador</option>
                <option value="user" <?= (($edit['role'] ?? 'user') === 'user') ? 'selected' : '' ?>>Usuario</option>
            </select>
        </label>
        <label class="checkbox-inline"><input type="checkbox" name="is_active" value="1" <?= empty($edit) || !empty($edit['is_active']) ? 'checked' : '' ?>> Usuario ativo</label>
        <button class="primary" name="save_user" value="1">Salvar usuario</button>
    </form>

    <div class="card">
        <h2>Perfis</h2>
        <p><strong>Administrador:</strong> acessa todas as rotinas, configuracoes, empresas, certificados, robos e usuarios.</p>
        <p><strong>Usuario:</strong> acessa apenas Dashboard e Documentos para conferencia, filtros, visualizacao e exportacao.</p>
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
                    <th>Status</th>
                    <th>Acao</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($users ?? []) as $user): ?>
                    <tr>
                        <td><?= h((string)$user['name']) ?></td>
                        <td><?= h((string)$user['email']) ?></td>
                        <td><?= ((string)$user['role'] === 'admin') ? 'Administrador' : 'Usuario' ?></td>
                        <td><?= !empty($user['is_active']) ? 'Ativo' : 'Inativo' ?></td>
                        <td><a class="row-action" href="<?= h(base_url('?page=users&edit_user_id=' . $user['id'])) ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                    <tr><td colspan="5">Nenhum usuario cadastrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/layout_bottom.php'; ?>
