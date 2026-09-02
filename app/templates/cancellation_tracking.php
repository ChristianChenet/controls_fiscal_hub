<?php include __DIR__ . '/layout_top.php'; ?>

<div class="page-header split-header">
    <div>
        <h1>Acompanhamento de cancelamentos</h1>
        <p>Documentos reagendados, retorno da SEFAZ, tentativas e proxima execucao.</p>
    </div>
    <div class="page-actions">
        <a class="button-compact" href="<?= h(base_url('?page=cancellation_tracking')) ?>">Atualizar</a>
    </div>
</div>

<?php
$trackingRows = $cancellationTracking ?? [];
$activeRows = $trackingRows;
$statusLabel = static function (string $status): string {
    return match ($status) {
        'pending' => 'Aguardando execucao',
        'retry' => 'Reagendado',
        'running' => 'Executando',
        'completed' => 'Concluido',
        'failed' => 'Falhou',
        default => $status !== '' ? $status : 'Sem status',
    };
};
?>

<div class="grid four metrics-grid">
    <div class="metric-card"><span>Ativos</span><strong><?= h((string)count($activeRows)) ?></strong><small>Pendentes, reagendados ou em execucao</small></div>
    <div class="metric-card"><span>Proxima execucao</span><strong><?= h((string)count($activeRows)) ?></strong><small>Somente tentativas futuras ou em execucao</small></div>
</div>

<div class="card">
    <div class="section-heading">
        <div>
            <h2>Fila de cancelamentos</h2>
            <p class="muted">A lista mostra somente tentativas futuras ou que estao em execucao e atualiza automaticamente.</p>
        </div>
    </div>
    <?php if (!$trackingRows): ?>
        <div class="empty-state">Nenhuma tentativa futura de cancelamento encontrada para este usuario.</div>
    <?php else: ?>
        <div class="table-wrap compact-table">
            <table>
                <thead><tr><th>Documento</th><th>Empresa</th><th>Status</th><th>Proxima execucao</th><th>Tentativas</th><th>Ultima resposta</th><th>Atualizado</th></tr></thead>
                <tbody>
                <?php foreach ($trackingRows as $row): ?>
                    <?php $status = (string)($row['status'] ?? ''); $isActive = in_array($status, ['pending', 'retry', 'running'], true); ?>
                    <tr class="<?= $isActive ? 'tracking-active-row' : '' ?>">
                        <td><strong>NF-e <?= h((string)($row['number'] ?? $row['document_id'] ?? '-')) ?></strong><br><small><?= h((string)($row['access_key'] ?? $row['document_access_key'] ?? '')) ?></small></td>
                        <td><?= h((string)($row['company_name'] ?? '-')) ?></td>
                        <td><span class="status-pill status-<?= h($status) ?>"><?= h($statusLabel($status)) ?></span><?php if (!empty($row['error_kind'])): ?><br><small><?= h((string)$row['error_kind']) ?></small><?php endif; ?></td>
                        <td><?php if ($status === 'running'): ?>Em execucao<?php elseif (!empty($row['next_attempt_at']) && strtotime((string)$row['next_attempt_at']) > time()): ?><?= h(format_date((string)$row['next_attempt_at'])) ?><?php else: ?>Aguardando execucao<?php endif; ?></td>
                        <td><?= h((string)($row['attempts'] ?? 0)) ?> / <?= h((string)($row['max_attempts'] ?? 0)) ?></td>
                        <td class="tracking-message"><?= h((string)($row['last_message'] ?? 'Aguardando primeira resposta.')) ?></td>
                        <td><?= !empty($row['updated_at']) ? h(format_date((string)$row['updated_at'])) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($activeRows): ?>
<script>
window.setTimeout(function(){ window.location.reload(); }, 30000);
</script>
<?php endif; ?>

<?php include __DIR__ . '/layout_bottom.php'; ?>