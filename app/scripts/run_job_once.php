<?php
declare(strict_types=1);

if (function_exists('set_time_limit')) {
    @set_time_limit(0);
}
ignore_user_abort(true);

require_once __DIR__ . '/../bootstrap.php';
extract(app_container());

$jobType = (string)($argv[1] ?? '');
$companyId = (int)($argv[2] ?? 0);

if (!in_array($jobType, ['nfse_until_max'], true)) {
    throw new RuntimeException('Job em segundo plano invalido.');
}

$lockPath = __DIR__ . '/../storage/manual_' . preg_replace('/[^a-z0-9_]+/i', '_', $jobType) . '.lock';
$lock = fopen($lockPath, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    $storage->appendLog('manual_jobs.log', 'Job manual em segundo plano ignorado porque ja existe execucao ativa: ' . $jobType . ' empresa=' . $companyId . ' em ' . date('c'));
    echo json_encode(['created' => 0, 'updated' => 0, 'errors' => 0, 'logs' => ['Robô NFS-e Nacional já está em execução em segundo plano. Aguarde concluir antes de iniciar outro.']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit;
}
ftruncate($lock, 0);
fwrite($lock, (string)getmypid() . ' ' . date('c'));

$storage->appendLog('manual_jobs.log', 'Inicio job manual em segundo plano: ' . $jobType . ' empresa=' . $companyId . ' em ' . date('c'));
try {
    $result = $jobRunner->run($jobType, $companyId);
    $storage->appendLog('manual_jobs.log', 'Fim job manual em segundo plano: ' . $jobType . ' empresa=' . $companyId . ' resultado=' . json_encode($result, JSON_UNESCAPED_UNICODE));
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
