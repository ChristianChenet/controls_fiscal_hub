<?php include __DIR__ . '/layout_top.php'; ?>
<div class="page-header">
    <h1>Acesso</h1>
    <p>Login simples para operação interna.</p>
</div>
<form method="post" class="card form-grid narrow">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <label>Usuário
        <input type="text" name="user" value="admin">
    </label>
    <label>Senha
        <input type="password" name="pass" value="admin">
    </label>
    <button class="primary">Entrar</button>
</form>
<?php include __DIR__ . '/layout_bottom.php'; ?>
