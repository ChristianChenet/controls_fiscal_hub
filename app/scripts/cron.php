<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
extract(app_container());

$jobType = $argv[1] ?? 'collect_all';
$result = $jobRunner->run($jobType);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
