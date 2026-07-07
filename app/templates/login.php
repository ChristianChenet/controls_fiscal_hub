<?php extract($viewData); ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title><?= h($title) ?> - Acesso</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?= h(base_url('assets/logo-s-novo.jpg')) ?>">
    <link rel="stylesheet" href="<?= h(base_url('assets/app.css?v=20260522-mdfe-usuarios')) ?>">
</head>
<body class="login-body">
    <main class="login-shell">
        <section class="login-panel">
            <div class="login-brand">
                <img src="<?= h(base_url('assets/logo-s-novo.jpg')) ?>" alt="Control S">
                <div>
                    <span>Plataforma fiscal</span>
                    <strong>Control S Fiscal Hub</strong>
                </div>
            </div>
            <div class="login-copy">
                <h1>Acesso ao portal</h1>
                <p>Entre com seu usuario para consultar XMLs, dashboard e rotinas fiscais autorizadas.</p>
            </div>
            <?php foreach ($flash as $msg): ?>
                <div class="alert <?= h($msg['type']) ?>"><?= h($msg['message']) ?></div>
            <?php endforeach; ?>
            <form method="post" class="login-form">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <label>E-mail
                    <input type="email" name="user" autocomplete="username" required autofocus>
                </label>
                <label>Senha
                    <input type="password" name="pass" autocomplete="current-password" required>
                </label>
                <button class="primary">Entrar</button>
            </form>
            <small class="login-foot">CONTROL S CONSULTORIA - Direitos Reservados | CNPJ: 21.421.411/0001-20</small>
        </section>
    </main>
</body>
</html>
