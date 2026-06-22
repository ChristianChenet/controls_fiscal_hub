<?php extract($viewData); ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title><?= h($title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?= h(base_url('assets/logo-s-novo.jpg')) ?>">
    <link rel="stylesheet" href="<?= h(base_url('assets/app.css?v=20260520-hub')) ?>">
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <a class="brand-lockup" href="<?= h(base_url()) ?>" aria-label="Control S Fiscal Hub">
            <img class="brand-logo" src="<?= h(base_url('assets/logo-s-novo.jpg')) ?>" alt="Control S">
            <span class="brand-text">
                <strong>Control S</strong>
                <span>Fiscal Hub</span>
            </span>
        </a>

        <nav class="nav-menu">
            <a class="<?= $page === 'dashboard' ? 'active' : '' ?>" href="<?= h(base_url()) ?>" title="Dashboard"><span class="nav-icon">&#9638;</span><span class="nav-label">Dashboard</span></a>
            <a class="<?= $page === 'companies' ? 'active' : '' ?>" href="<?= h(base_url('?page=companies')) ?>" title="Empresas"><span class="nav-icon">&#9636;</span><span class="nav-label">Empresas</span></a>
            <a class="<?= $page === 'import' ? 'active' : '' ?>" href="<?= h(base_url('?page=import')) ?>" title="Importar XML"><span class="nav-icon">&#8679;</span><span class="nav-label">Importar XML</span></a>
            <a class="<?= $page === 'documents' ? 'active' : '' ?>" href="<?= h(base_url('?page=documents')) ?>" title="Documentos"><span class="nav-icon">&#9776;</span><span class="nav-label">Documentos</span></a>
            <a class="<?= $page === 'jobs' ? 'active' : '' ?>" href="<?= h(base_url('?page=jobs')) ?>" title="Radar de XML"><span class="nav-icon">&#8635;</span><span class="nav-label">Radar de XML</span></a>
            <a class="<?= $page === 'nfe_keys' ? 'active' : '' ?>" href="<?= h(base_url('?page=nfe_keys')) ?>" title="Busca por Chave"><span class="nav-icon">#</span><span class="nav-label">Busca por Chave</span></a>
            <a class="<?= $page === 'nfe_rescan' ? 'active' : '' ?>" href="<?= h(base_url('?page=nfe_rescan')) ?>" title="Revarrer NF-e"><span class="nav-icon">&#8634;</span><span class="nav-label">Revarrer NF-e</span></a>
            <a class="<?= $page === 'settings' ? 'active' : '' ?>" href="<?= h(base_url('?page=settings')) ?>" title="Configuracoes"><span class="nav-icon">&#9881;</span><span class="nav-label">Configuracoes</span></a>
            <?php if ($config['auth_enabled']): ?>
                <a href="<?= h(base_url('?page=logout')) ?>" title="Sair"><span class="nav-icon">&#8594;</span><span class="nav-label">Sair</span></a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <small><?= h((string)($dashboard['companiesCount'] ?? count($companies ?? []))) ?> CNPJs no portal</small>
        </div>

        <button class="sidebar-toggle" type="button" aria-label="Recolher menu" title="Recolher menu" data-sidebar-toggle>
            <span class="toggle-arrow">&#8249;</span>
            <span class="toggle-label">Recolher menu</span>
        </button>
    </aside>

    <main class="content">
        <header class="topbar">
            <div>
                <span class="topbar-eyebrow">Plataforma fiscal</span>
                <h1 class="topbar-title"><?= h($title) ?></h1>
                <p class="topbar-subtitle">Captura, conferencia, manifestacao e organizacao de XMLs fiscais.</p>
            </div>
            <div class="topbar-actions">
                <button class="button-link topbar-refresh" type="button" onclick="window.location.reload()">Atualizar</button>
                <div class="client-brand">
                    <img class="client-brand-logo" src="<?= h(base_url('assets/logo-s-novo.jpg')) ?>" alt="Control S">
                    <div>
                        <span>Ambiente fiscal</span>
                        <strong>Cliente integrado</strong>
                    </div>
                </div>
            </div>
        </header>

        <?php foreach ($flash as $msg): ?>
            <div class="alert <?= h($msg['type']) ?>"><?= h($msg['message']) ?></div>
        <?php endforeach; ?>
