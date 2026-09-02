<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

extract(app_container());

function auto_worker_companies($repo, array $config, string $key): array
{
    $selected = trim((string)$repo->getSetting('auto_' . $key . '_company_ids', (string)($config['auto_' . $key . '_company_ids'] ?? '')));
    $ids = array_values(array_unique(array_filter(array_map('intval', preg_split('/[,;\s]+/', $selected)))));
    if (!$ids) {
        $legacy = (int)$repo->getSetting('auto_' . $key . '_company_id', (string)($config['auto_' . $key . '_company_id'] ?? 0));
        if ($legacy > 0) {
            $ids = [$legacy];
        }
    }
    if (!$ids) {
        return $repo->activeCompanies();
    }
    $companies = [];
    foreach ($ids as $id) {
        $company = $repo->findCompany($id);
        if ($company) {
            $companies[] = $company;
        }
    }
    return $companies;
}

function auto_worker_normalize_time(string $value, string $fallback): string
{
    if (!preg_match('/^(?:[01]?\d|2[0-3]):[0-5]\d$/', trim($value))) {
        return $fallback;
    }
    [$hour, $minute] = array_map('intval', explode(':', trim($value)));
    return sprintf('%02d:%02d', $hour, $minute);
}

function auto_worker_seconds_until(string $time, string $lastRunDate): ?int
{
    $now = new DateTimeImmutable('now');
    if ($lastRunDate === $now->format('Y-m-d')) {
        return null;
    }
    [$hour, $minute] = array_map('intval', explode(':', $time));
    $target = $now->setTime($hour, $minute, 0);
    if ($now >= $target) {
        return 1;
    }
    return max(1, $target->getTimestamp() - $now->getTimestamp());
}

function auto_worker_min_sleep(int $base, array $candidates): int
{
    $sleep = max(1, $base);
    foreach ($candidates as $candidate) {
        if ($candidate !== null) {
            $sleep = min($sleep, max(1, (int)$candidate));
        }
    }
    return $sleep;
}

$storage->appendLog('auto_nfse_worker.log', 'Worker NFS-e iniciado em ' . date('c'));

$processLockPath = __DIR__ . '/../storage/auto_nfse_worker.process.lock';
$processLock = fopen($processLockPath, 'c');
if (!$processLock || !flock($processLock, LOCK_EX | LOCK_NB)) {
    $storage->appendLog('auto_nfse_worker.log', 'Worker NFS-e ja esta em execucao. Nova instancia encerrada.');
    exit;
}
ftruncate($processLock, 0);
fwrite($processLock, (string)getmypid() . ' ' . date('c'));

$runNfseCancellationCycle = static function () use ($repo, $jobRunner, $storage): void {
    if ((string)$repo->getSetting('nfse_cancellation_robot_enabled', '1') !== '1') {
        return;
    }
    $time = auto_worker_normalize_time((string)$repo->getSetting('nfse_cancellation_robot_time', '01:00'), '01:00');
    $today = date('Y-m-d');
    if ((string)$repo->getSetting('nfse_cancellation_robot_last_run_date', '') === $today || date('H:i') < $time) {
        return;
    }
    try {
        $result = $jobRunner->run('nfse_cancellation_check', 0);
        $repo->setSetting('nfse_cancellation_robot_last_run_date', $today);
        $storage->appendLog('nfse_cancellation_robot.log', '[' . date('c') . '] resultado=' . json_encode($result, JSON_UNESCAPED_UNICODE));
    } catch (Throwable $e) {
        $storage->appendLog('nfse_cancellation_robot.log', '[' . date('c') . '] erro: ' . $e->getMessage());
    }
};

while (true) {
    $runNfseCancellationCycle();
    $enabled = $repo->getSetting('auto_nfse_enabled', (string)($config['auto_nfse_enabled'] ?? '0')) === '1';
    $intervalMinutes = max(60, (int)$repo->getSetting('auto_nfse_interval_minutes', (string)($config['auto_nfse_interval_minutes'] ?? 60)));
    $sleepSeconds = $enabled ? ($intervalMinutes * 60) : 30;

    try {
        $folderResult = $nfseXmlFolderRobot->runScheduledIfDue();
        if ($folderResult) {
            $storage->appendLog('auto_nfse_worker.log', 'Robo pasta XML NFS-e executado: ' . json_encode($folderResult, JSON_UNESCAPED_UNICODE));
        }
    } catch (Throwable $e) {
        $storage->appendLog('auto_nfse_worker.log', 'Erro no robo pasta XML NFS-e: ' . $e->getMessage());
        $repo->logAction('nfse_xml_folder_export_error', $e->getMessage());
    }

    $candidates = [];
    if ((string)$repo->getSetting('nfse_cancellation_robot_enabled', '1') === '1') {
        $cancelTime = auto_worker_normalize_time((string)$repo->getSetting('nfse_cancellation_robot_time', '01:00'), '01:00');
        $candidates[] = auto_worker_seconds_until($cancelTime, (string)$repo->getSetting('nfse_cancellation_robot_last_run_date', ''));
    }
    if ((string)$repo->getSetting('nfse_xml_folder_robot_enabled', '0') === '1') {
        $folderTime = auto_worker_normalize_time((string)$repo->getSetting('nfse_xml_folder_robot_time', '02:30'), '02:30');
        $candidates[] = auto_worker_seconds_until($folderTime, (string)$repo->getSetting('nfse_xml_folder_robot_last_run_date', ''));
    }
    $sleepSeconds = auto_worker_min_sleep($sleepSeconds, $candidates);

    if (!$enabled) {
        sleep($sleepSeconds);
        continue;
    }

    $lockPath = __DIR__ . '/../storage/auto_nfse_worker.lock';
    $lock = fopen($lockPath, 'c');
    if (!$lock) {
        $storage->appendLog('auto_nfse_worker.log', 'Nao foi possivel criar lock em ' . $lockPath);
        sleep($sleepSeconds);
        continue;
    }

    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        $storage->appendLog('auto_nfse_worker.log', 'Execucao ignorada: lock ativo.');
        fclose($lock);
        sleep($sleepSeconds);
        continue;
    }

    try {
        ftruncate($lock, 0);
        fwrite($lock, (string)getmypid() . ' ' . date('c'));
        $companies = auto_worker_companies($repo, $config, 'nfse');

        foreach ($companies as $company) {
            if (empty($company['is_active'])) {
                continue;
            }
            $storage->appendLog('auto_nfse_worker.log', '[' . ($company['company_name'] ?? $company['id']) . '] inicio individual em ' . date('c'));
            // A rotina automatica usa ciclos seguros por CNPJ, igual ao padrão operacional do CT-e/NF-e.
            $result = $jobRunner->run('nfse_until_max', (int)$company['id']);
            $storage->appendLog('auto_nfse_worker.log', '[' . ($company['company_name'] ?? $company['id']) . '] fim individual em ' . date('c'));
            sleep(3);
            $storage->appendLog('auto_nfse_worker.log', '[' . ($company['company_name'] ?? $company['id']) . '] ' . json_encode($result, JSON_UNESCAPED_UNICODE));
        }
    } catch (Throwable $e) {
        $storage->appendLog('auto_nfse_worker.log', 'Erro na automacao NFS-e: ' . $e->getMessage());
        $repo->logAction('auto_nfse_error', $e->getMessage());
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    sleep($sleepSeconds);
}
