<?php extract($viewData); ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title><?= h($title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?= h(base_url('assets/logo-s-novo.jpg')) ?>">
    <link rel="stylesheet" href="<?= h(base_url('assets/app.css?v=20260526-dashboard-faturamento')) ?>">
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
            <?php if (!empty($isAdmin)): ?>
            <a class="<?= $page === 'dashboard' ? 'active' : '' ?>" href="<?= h(base_url()) ?>" title="Resumo XMLs"><span class="nav-icon">&#9638;</span><span class="nav-label">Resumo XMLs</span></a>
            <?php endif; ?>
            <a class="<?= $page === 'revenue' ? 'active' : '' ?>" href="<?= h(base_url('?page=revenue')) ?>" title="Faturamento"><span class="nav-icon">&#9635;</span><span class="nav-label">Faturamento</span></a>
            <?php if (!empty($isAdmin)): ?>
            <a class="<?= $page === 'companies' ? 'active' : '' ?>" href="<?= h(base_url('?page=companies')) ?>" title="Empresas"><span class="nav-icon">&#9636;</span><span class="nav-label">Empresas</span></a>
            <a class="<?= $page === 'import' ? 'active' : '' ?>" href="<?= h(base_url('?page=import')) ?>" title="Importar XML"><span class="nav-icon">&#8679;</span><span class="nav-label">Importar XML</span></a>
            <?php endif; ?>
            <a class="<?= $page === 'documents' ? 'active' : '' ?>" href="<?= h(base_url('?page=documents')) ?>" title="Entradas"><span class="nav-icon">&#9776;</span><span class="nav-label">Entradas</span></a>
            <?php if (!empty($isAdmin)): ?>
            <a class="<?= $page === 'jobs' ? 'active' : '' ?>" href="<?= h(base_url('?page=jobs')) ?>" title="Radar de XML"><span class="nav-icon">&#8635;</span><span class="nav-label">Radar de XML</span></a>
            <a class="<?= $page === 'nfe_keys' ? 'active' : '' ?>" href="<?= h(base_url('?page=nfe_keys')) ?>" title="Busca por Chave"><span class="nav-icon">#</span><span class="nav-label">Busca por Chave</span></a>
            <a class="<?= $page === 'nfe_rescan' ? 'active' : '' ?>" href="<?= h(base_url('?page=nfe_rescan')) ?>" title="Revarrer NF-e"><span class="nav-icon">&#8634;</span><span class="nav-label">Revarrer NF-e</span></a>
            <a class="<?= $page === 'users' ? 'active' : '' ?>" href="<?= h(base_url('?page=users')) ?>" title="Usuarios"><span class="nav-icon">&#9673;</span><span class="nav-label">Usuarios</span></a>
            <a class="<?= $page === 'settings' ? 'active' : '' ?>" href="<?= h(base_url('?page=settings')) ?>" title="Configuracoes"><span class="nav-icon">&#9881;</span><span class="nav-label">Configuracoes</span></a>
            <?php endif; ?>
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
        <?php
            $clientName = (string)($settings['client_display_name'] ?? 'Cliente integrado');
            $clientLabel = (string)($settings['client_label'] ?? 'Ambiente fiscal');
            $clientLogo = (string)($settings['client_logo_path'] ?? 'assets/logo-s-novo.jpg');
            $clientLogoUrl = str_starts_with($clientLogo, 'http') ? $clientLogo : base_url($clientLogo);
        ?>
        <header class="topbar">
            <div class="topbar-copy">
                <div class="topbar-product">
                    <span class="topbar-eyebrow">Plataforma fiscal</span>
                    <h1 class="topbar-title">Control S Fiscal Hub</h1>
                </div>
                <div class="topbar-module" data-topbar-module>
                    <?php if (!empty($moduleTitle)): ?>
                        <h1 class="topbar-title"><?= h((string)$moduleTitle) ?></h1>
                        <p class="topbar-subtitle"><?= h((string)($moduleSubtitle ?? '')) ?></p>
                    <?php else: ?>
                        <p class="topbar-subtitle">Captura, conferencia, manifestacao e organizacao de XMLs fiscais.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="topbar-actions">
                <div class="client-brand">
                    <img class="client-brand-logo" src="<?= h($clientLogoUrl) ?>" alt="<?= h($clientName) ?>">
                    <div>
                        <span><?= h($clientLabel) ?></span>
                        <strong><?= h($clientName) ?></strong>
                    </div>
                </div>
            </div>
        </header>

        <?php foreach ($flash as $msg): ?>
            <div class="alert <?= h($msg['type']) ?>"><?= h($msg['message']) ?></div>
        <?php endforeach; ?>
