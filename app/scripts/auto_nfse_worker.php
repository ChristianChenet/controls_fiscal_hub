<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

extract(app_container());

$storage->appendLog('auto_nfse_worker.log', 'Worker NFS-e iniciado em ' . date('c'));

while (true) {
    $enabled = $repo->getSetting('auto_nfse_enabled', (string)($config['auto_nfse_enabled'] ?? '0')) === '1';
    $intervalMinutes = max(60, (int)$repo->getSetting('auto_nfse_interval_minutes', (string)($config['auto_nfse_interval_minutes'] ?? 60)));
    $sleepSeconds = $enabled ? ($intervalMinutes * 60) : 30;

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

        $configuredCompanyId = (int)$repo->getSetting('auto_nfse_company_id', (string)($config['auto_nfse_company_id'] ?? 0));
        $companies = $configuredCompanyId > 0 ? array_filter([$repo->findCompany($configuredCompanyId)]) : $repo->activeCompanies();

        foreach ($companies as $company) {
            if (empty($company['is_active'])) {
                continue;
            }
            $result = $jobRunner->run('nfse', (int)$company['id']);
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
