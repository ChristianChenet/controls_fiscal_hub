<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
extract(app_container());

$start = $argv[1] ?? date('Y-m-01');
$end = $argv[2] ?? date('Y-m-d');
$types = isset($argv[3]) ? explode(',', $argv[3]) : ['cte'];

$result = $periodClosure->run([
    'period_start' => $start,
    'period_end' => $end,
    'doc_types' => $types,
    'company_ids' => [],
    'only_missing_complete' => true,
    'try_manifestation' => false,
    'reprocess_after_manifestation' => false,
    'generate_export' => false,
    'save_period_folder' => false,
]);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
