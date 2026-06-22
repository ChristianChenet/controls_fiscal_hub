<?php include __DIR__ . '/layout_top.php'; ?>
<div class="page-header split-header">
    <div>
        <h1>Documentação do Radar por Período</h1>
        <p>Resumo prático para operar a busca manual por período com segurança.</p>
    </div>
    <a class="button-link" href="<?= h(base_url('?page=period_closure')) ?>">Voltar ao fechamento</a>
</div>

<section class="card doc-page">
    <h2>Como funciona</h2>
    <p>NF-e e CT-e são consultados por NSU. O período informado não é enviado como filtro para a SEFAZ; o portal recebe o que a distribuição retornar, processa os documentos e depois classifica o que pertence ao intervalo solicitado.</p>

    <h2>Quando usar</h2>
    <p>Use para fechamento operacional de um intervalo, como o mês até a data atual ou um dia específico no acompanhamento diário.</p>

    <h2>Cuidados</h2>
    <ul class="simple-list">
        <li>Não execute a mesma coleta repetidamente. O portal aplica bloqueio para evitar consumo indevido.</li>
        <li>NF-e pode chegar apenas como resumo e exigir manifestação antes do XML completo.</li>
        <li>O portal não garante recuperar documentos antigos fora da janela disponível do serviço.</li>
        <li>Documentos que já possuem XML completo não são baixados novamente.</li>
    </ul>

    <h2>Status</h2>
    <dl class="details">
        <dt>XML completo</dt><dd>Documento com XML salvo no portal.</dd>
        <dt>Apenas resumo</dt><dd>Documento recebido sem XML completo.</dd>
        <dt>Pendente</dt><dd>NF-e que precisa de manifestação.</dd>
        <dt>Aguardando novo download</dt><dd>NF-e manifestada aguardando nova consulta.</dd>
        <dt>Já existente</dt><dd>O XML completo já estava na base.</dd>
        <dt>Fora do período</dt><dd>Retorno da distribuição por NSU que não pertence ao intervalo solicitado.</dd>
    </dl>
</section>
<?php include __DIR__ . '/layout_bottom.php'; ?>
