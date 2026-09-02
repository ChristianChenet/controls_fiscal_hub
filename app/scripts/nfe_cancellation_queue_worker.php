<?php
declare(strict_types=1);

function nfe_queue_next_check_at(string $message): string
{
    $now = new DateTimeImmutable('now');
    $minimum = $now->modify('+1 minute');
    if (preg_match('/(?:ap[o\x{00F3}]s|at[e\x{00E9}])\s+(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})\s+(\d{1,2}):(\d{2})/iu', $message, $m)) {
        $dt = DateTimeImmutable::createFromFormat('!d/m/Y H:i', sprintf('%02d/%02d/%04d %02d:%02d', (int)$m[1], (int)$m[2], (int)$m[3], (int)$m[4], (int)$m[5]));
        if ($dt instanceof DateTimeImmutable) return ($dt < $minimum ? $minimum : $dt)->format('Y-m-d H:i:s');
    }
    if (preg_match('/(?:ap[o\x{00F3}]s|at[e\x{00E9}])\s+(\d{1,2}):(\d{2})/iu', $message, $m)) {
        $dt = (new DateTimeImmutable('today'))->setTime((int)$m[1], (int)$m[2]);
        if ($dt < $minimum) $dt = $dt->modify('+1 day');
        return ($dt < $minimum ? $minimum : $dt)->format('Y-m-d H:i:s');
    }
    return $minimum->format('Y-m-d H:i:s');
}
function processNfeCancellationQueue($repo, array $collectors, $storage, array $config, int $limit = 20, ?int $queueId = null, bool $manualOnly = false): array
{
    $rows = $repo->claimDueNfeCancellationChecks($limit, $queueId, $manualOnly);
    $out = ['processed' => 0, 'completed' => 0, 'retry' => 0, 'failed' => 0];
    foreach ($rows as $item) {
        $out['processed']++;
        $attempts = (int)$item['attempts'];
        $cstat = null;
        $message = '';
        try {
            $company = $repo->findCompany((int)$item['company_id']);
            if (!$company) throw new RuntimeException('Empresa da solicitação não encontrada.');
            $connector = $collectors['nfe'];
            $connector->setCompanyContext($company);
            $result = method_exists($connector, 'queryProtocolStatus')
                ? $connector->queryProtocolStatus((string)$item['access_key'])
                : ['errors' => 1, 'message' => 'Consulta de protocolo não disponível.'];
            $cstat = $result['cStat'] ?? $result['cstat'] ?? null;
            $message = (string)($result['message'] ?? $result['xMotivo'] ?? '');
            $statusCode = preg_replace('/\D+/', '', (string)$cstat) ?: '';
            $next = null;

            // A fila nao consulta por chave como fallback: a distribuicao oficial e executada apenas pelo robo NSU.
            $doc = $repo->findDocumentByAccessKey('NFE', (string)$item['access_key'], (int)$item['company_id']);
            if ($doc && (string)($doc['status'] ?? '') === 'cancelado') {
                $repo->finishNfeCancellationCheck((int)$item['id'], 'completed', $attempts, null, $cstat, $message, null);
                $out['completed']++;
                continue;
            }
            $temporary = (bool)preg_match('/656|consumo indevido|timeout|tempor|indispon|bloquead|soap/iu', $message);
            $definitive = in_array($statusCode, ['100', '150', '101', '151', '155', '110', '301', '302', '303'], true);
            if (!$temporary && $definitive) {
                $finalStatus = 'completed';
                $repo->finishNfeCancellationCheck((int)$item['id'], 'completed', $attempts, null, $cstat, $message, null);
                $out['completed']++;
            } elseif ($temporary && $attempts < (int)$item['max_attempts']) {
                $finalStatus = 'retry';
                $next = nfe_queue_next_check_at($message);
                $repo->finishNfeCancellationCheck((int)$item['id'], 'retry', $attempts, $next, $cstat, $message, 'temporary');
                $out['retry']++;
            } else {
                $finalStatus = 'failed';
                $repo->finishNfeCancellationCheck((int)$item['id'], 'failed', $attempts, null, $cstat, $message, $temporary ? 'max_attempts' : 'permanent');
                $out['failed']++;
            }
            $storage->appendLog('nfe_cancellation_queue.log', '[' . date('c') . '] item=' . $item['id'] . ' status=' . $finalStatus . ' next=' . ($finalStatus === 'retry' ? ($next ?? '') : '') . ' message=' . $message);
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $temporary = (bool)preg_match('/timeout|tempor|indispon|656|consumo indevido|soap/iu', $message);
            if ($temporary && $attempts < (int)$item['max_attempts']) {
                $next = nfe_queue_next_check_at($message);
                $repo->finishNfeCancellationCheck((int)$item['id'], 'retry', $attempts, $next, $cstat, $message, 'exception');
                $out['retry']++;
            } else {
                $repo->finishNfeCancellationCheck((int)$item['id'], 'failed', $attempts, null, $cstat, $message, 'exception');
                $out['failed']++;
            }
            $storage->appendLog('nfe_cancellation_queue.log', '[' . date('c') . '] erro item=' . $item['id'] . ' next=' . ($next ?? '') . ' message=' . $message);
        }
    }
    return $out;
}
