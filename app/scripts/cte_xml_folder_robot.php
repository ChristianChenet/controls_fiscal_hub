<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

extract(app_container());

try {
    $result = $cteXmlFolderRobot->run('cli');
    echo implode(PHP_EOL, $result['logs']) . PHP_EOL;
    exit((int)$result['errors'] > 0 ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
