<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/nfe_cancellation_queue_worker.php';

extract(app_container());
$nfeXmlFolderRobot = app_container()['nfeXmlFolderRobot'] ?? null;

function auto_worker_companies($repo, array $config, string $key): array
{
    $selected = trim((string)$repo->getSetting('auto_' . $key . '_company_ids', (string)($config['auto_' . $key . '_company_ids'] ?? '')));
    $ids = array_values(array_unique(array_filter(array_map('intval', preg_split('/[,;\s]+/', $selected)))));
    if (!$ids) {
        $legacy = (int)$repo->getSetting('auto_' . $key . '_company_id', (string)($config['auto_' . $key . '_company_id'] ?? 0));
        if ($legacy > 0) $ids = [$legacy];
    }
    if (!$ids) return $repo->activeCompanies();
    $companies = [];
    foreach ($ids as $id) { $company = $repo->findCompany($id); if ($company) $companies[] = $company; }
    return $companies;
}

function auto_worker_normalize_time(string $value, string $fallback): string
{
    if (!preg_match('/^(?:[01]?\d|2[0-3]):[0-5]\d$/', trim($value), $m)) return $fallback;
    [$hour, $minute] = array_map('intval', explode(':', trim($value)));
    return sprintf('%02d:%02d', $hour, $minute);
}

function auto_worker_seconds_until(string $time, string $lastRunDate): ?int
{
    $now = new DateTimeImmutable('now');
    if ($lastRunDate === $now->format('Y-m-d')) return null;
    [$hour, $minute] = array_map('intval', explode(':', $time));
    $target = $now->setTime($hour, $minute, 0);
    if ($now >= $target) return 1;
    return max(1, $target->getTimestamp() - $now->getTimestamp());
}

function auto_worker_min_sleep(int $base, array $candidates): int
{
    $sleep = max(1, $base);
    foreach ($candidates as $candidate) {
        if ($candidate !== null) $sleep = min($sleep, max(1, (int)$candidate));
    }
    return $sleep;
}

$storage->appendLog('auto_nfe_worker.log', 'Worker NF-e iniciado em ' . date('c'));
$queueRepairDone = false;
$legacyQueueRetired = false;
$officialNsuMode = 'official_nsu_v2';
if ((string)$repo->getSetting('nfe_cancellation_robot_schedule_mode', '') !== $officialNsuMode) {
    $repo->setSetting('nfe_cancellation_robot_last_run_date', '');
    $repo->setSetting('nfe_cancellation_robot_schedule_mode', $officialNsuMode);
    $storage->appendLog('nfe_cancellation_robot.log', '[' . date('c') . '] modo oficial por NSU ativado; proximo ciclo sera executado sem reutilizar a data antiga.');
}
$processLockPath = __DIR__ . '/../storage/auto_nfe_worker.process.lock';
$processLock = fopen($processLockPath, 'c');
if (!$processLock || !flock($processLock, LOCK_EX | LOCK_NB)) {
    $storage->appendLog('auto_nfe_worker.log', 'Worker NF-e ja esta em execucao. Nova instancia encerrada.');
    exit;
}
ftruncate($processLock, 0);
fwrite($processLock, (string)getmypid() . ' ' . date('c'));

$runNfeCancellationCycle = static function () use ($repo, $jobRunner, $storage): bool {
    if ((string)$repo->getSetting('nfe_cancellation_robot_enabled', '1') !== '1') return false;
    $time = auto_worker_normalize_time((string)$repo->getSetting('nfe_cancellation_robot_time', '00:45'), '00:45');
    $today = date('Y-m-d');
    $retryAt = (string)$repo->getSetting('nfe_cancellation_robot_retry_at', '');
    $retryTimestamp = $retryAt !== '' ? strtotime($retryAt) : false;
    if ($retryTimestamp !== false && $retryTimestamp > time()) return false;
    if ((string)$repo->getSetting('nfe_cancellation_robot_last_run_date', '') === $today || date('H:i') < $time) return false;
    try {
        $result = $jobRunner->run('nfe_until_max', 0);
        $encoded = json_encode($result, JSON_UNESCAPED_UNICODE);
        $lower = mb_strtolower((string)$encoded);
        $needsRetry = (int)($result['errors'] ?? 0) > 0
            || str_contains($lower, 'consumo indevido')
            || str_contains($lower, 'bloqueada')
            || str_contains($lower, 'bloqueado')
            || str_contains($lower, 'certificado ativo')
            || str_contains($lower, 'erro');
        if ($needsRetry) {
            $next = method_exists($repo, 'nextNfeDistributionCooldownAt') ? $repo->nextNfeDistributionCooldownAt() : null;
            $nextTimestamp = $next !== null ? strtotime($next) : false;
            if ($nextTimestamp === false || $nextTimestamp <= time()) $next = date('c', time() + 1800);
            $repo->setSetting('nfe_cancellation_robot_retry_at', $next);
            $storage->appendLog('nfe_cancellation_robot.log', '[' . date('c') . '] sincronizacao oficial por NSU reagendada para ' . $next . ' resultado=' . $encoded);
            return true;
        }
        $repo->setSetting('nfe_cancellation_robot_last_run_date', $today);
        $repo->setSetting('nfe_cancellation_robot_retry_at', '');
        $storage->appendLog('nfe_cancellation_robot.log', '[' . date('c') . '] sincronizacao oficial por NSU=' . $encoded);
        return true;
    } catch (Throwable $e) {
        $next = date('c', time() + 1800);
        $repo->setSetting('nfe_cancellation_robot_retry_at', $next);
        $storage->appendLog('nfe_cancellation_robot.log', '[' . date('c') . '] sincronizacao oficial por NSU reagendada para ' . $next . ' erro: ' . $e->getMessage());
        return true;
    }
};

while (true) {    $scheduledNsuRun = false;
    try {
        if (!$legacyQueueRetired && method_exists($repo, 'retireAutomaticNfeCancellationQueue')) {
            $retired = $repo->retireAutomaticNfeCancellationQueue(20000);
            if ($retired > 0) $storage->appendLog('nfe_cancellation_queue.log', '[' . date('c') . '] fila antiga por chave desativada: ' . $retired);
            $legacyQueueRetired = true;
        }
        $scheduledNsuRun = $runNfeCancellationCycle();
        $cancellationEnabled = (string)$repo->getSetting('nfe_cancellation_robot_enabled', '1') === '1';
        if ($cancellationEnabled) {
            $queueResult = processNfeCancellationQueue($repo, $collectors, $storage, $config, 50, null, true);
            if ((int)($queueResult['processed'] ?? 0) > 0) {
                $storage->appendLog('nfe_cancellation_queue.log', '[' . date('c') . '] fila manual processada: ' . json_encode($queueResult, JSON_UNESCAPED_UNICODE));
            }
        }
    } catch (Throwable $queueError) {
        $storage->appendLog('nfe_cancellation_queue.log', '[' . date('c') . '] ' . $queueError->getMessage());
    }

    $enabled = $repo->getSetting('auto_nfe_enabled', (string)($config['auto_nfe_enabled'] ?? '0')) === '1';
    $intervalMinutes = max(5, (int)$repo->getSetting('auto_nfe_interval_minutes', (string)($config['auto_nfe_interval_minutes'] ?? 60)));
    $sleepSeconds = $enabled ? ($intervalMinutes * 60) : 30;
    $candidates = [];
    $cancellationEnabled = (string)$repo->getSetting('nfe_cancellation_robot_enabled', '1') === '1';
    if ($cancellationEnabled) {
        $cancelTime = auto_worker_normalize_time((string)$repo->getSetting('nfe_cancellation_robot_time', '00:45'), '00:45');
        $retryAt = (string)$repo->getSetting('nfe_cancellation_robot_retry_at', '');
        $retryTimestamp = $retryAt !== '' ? strtotime($retryAt) : false;
        if ($retryTimestamp !== false && $retryTimestamp > time()) {
            $candidates[] = $retryTimestamp - time();
        } else {
            $candidates[] = auto_worker_seconds_until($cancelTime, (string)$repo->getSetting('nfe_cancellation_robot_last_run_date', ''));
        }
        if (method_exists($repo, 'nextNfeCancellationAttemptAt')) {
            $nextAt = $repo->nextNfeCancellationAttemptAt(true);
            if ($nextAt !== null) $candidates[] = strtotime($nextAt) - time();
        }
    }
    if ($nfeXmlFolderRobot && (string)$repo->getSetting('nfe_xml_folder_robot_enabled', '1') === '1') {
        $folderTime = auto_worker_normalize_time((string)$repo->getSetting('nfe_xml_folder_robot_time', '02:15'), '02:15');
        $candidates[] = auto_worker_seconds_until($folderTime, (string)$repo->getSetting('nfe_xml_folder_robot_last_run_date', ''));
    }
    $sleepSeconds = auto_worker_min_sleep($sleepSeconds, $candidates);

    if (!$enabled && !$scheduledNsuRun) {
        if ($nfeXmlFolderRobot && method_exists($nfeXmlFolderRobot, 'runScheduledIfDue')) {
            try { $nfeXmlFolderRobot->runScheduledIfDue(); } catch (Throwable $folderError) { $storage->appendLog('nfe_xml_folder_robot.log', '[' . date('c') . '] ' . $folderError->getMessage()); }
        }
        sleep($sleepSeconds);
        continue;
    }
    if (!$enabled || $scheduledNsuRun) {
        if ($nfeXmlFolderRobot && method_exists($nfeXmlFolderRobot, 'runScheduledIfDue')) {
            try { $nfeXmlFolderRobot->runScheduledIfDue(); } catch (Throwable $folderError) { $storage->appendLog('nfe_xml_folder_robot.log', '[' . date('c') . '] ' . $folderError->getMessage()); }
        }
        if (!$enabled) { sleep($sleepSeconds); continue; }
    }

    $lockPath = __DIR__ . '/../storage/auto_nfe_worker.lock';
    $lock = fopen($lockPath, 'c');
    if (!$lock) { $storage->appendLog('auto_nfe_worker.log', 'Nao foi possivel criar lock em ' . $lockPath); sleep($sleepSeconds); continue; }
    if (!flock($lock, LOCK_EX | LOCK_NB)) { $storage->appendLog('auto_nfe_worker.log', 'Execucao ignorada: lock ativo.'); fclose($lock); sleep($sleepSeconds); continue; }
    try {
        ftruncate($lock, 0); fwrite($lock, (string)getmypid() . ' ' . date('c'));
        if (!$scheduledNsuRun) {
            $manifestScience = $repo->getSetting('auto_nfe_manifest_science', (string)($config['auto_nfe_manifest_science'] ?? '0')) === '1';
            $jobType = $manifestScience ? 'nfe_until_max_science' : 'nfe_until_max';
            foreach (auto_worker_companies($repo, $config, 'nfe') as $company) {
                if (empty($company['is_active'])) continue;
                $storage->appendLog('auto_nfe_worker.log', '[' . ($company['company_name'] ?? $company['id']) . '] inicio individual em ' . date('c'));
                $result = $jobRunner->run($jobType, (int)$company['id']);
                $storage->appendLog('auto_nfe_worker.log', '[' . ($company['company_name'] ?? $company['id']) . '] fim individual em ' . date('c'));
                sleep(3);
                $storage->appendLog('auto_nfe_worker.log', '[' . ($company['company_name'] ?? $company['id']) . '] ' . json_encode($result, JSON_UNESCAPED_UNICODE));
            }
        }
    } catch (Throwable $e) {
        $storage->appendLog('auto_nfe_worker.log', 'Erro na automacao NF-e: ' . $e->getMessage());
        $repo->logAction('auto_nfe_error', $e->getMessage());
    } finally { flock($lock, LOCK_UN); fclose($lock); }
    if ($nfeXmlFolderRobot && method_exists($nfeXmlFolderRobot, 'runScheduledIfDue')) {
        try { $nfeXmlFolderRobot->runScheduledIfDue(); } catch (Throwable $folderError) { $storage->appendLog('nfe_xml_folder_robot.log', '[' . date('c') . '] ' . $folderError->getMessage()); }
    }
    sleep($sleepSeconds);
}