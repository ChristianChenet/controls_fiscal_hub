<?php include __DIR__ . '/layout_top.php'; ?>
<div class="page-header">
    <h1>Importar XML</h1>
    <p>Importação manual de NF-e, NFC-e, CT-e e NFS-e Nacional.</p>
</div>

<form method="post" enctype="multipart/form-data" class="card form-grid">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <label>Arquivos XML
        <input type="file" name="xml_files[]" multiple accept=".xml" required>
    </label>
    <button class="primary">Importar arquivos</button>
</form>

<section class="card">
    <h2>O que esta tela faz</h2>
    <ul class="simple-list">
        <li>Lê o XML e identifica o tipo do documento.</li>
        <li>Extrai dados principais: emissor, destinatário, valor, data e chave.</li>
        <li>Salva o XML na pasta padrão do servidor organizada por tipo/ano/mês.</li>
        <li>Registra o documento no PostgreSQL e evita duplicidade por hash.</li>
    </ul>
</section>
<?php include __DIR__ . '/layout_bottom.php'; ?>
