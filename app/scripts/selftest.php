<?php
declare(strict_types=1);

$base = realpath(__DIR__ . '/..');
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base . '/src'));
$errors = [];
foreach ($files as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $cmd = 'php -l ' . escapeshellarg($file->getPathname());
    exec($cmd, $output, $code);
    if ($code !== 0) {
        $errors[] = [$file->getPathname(), implode("\n", $output)];
    }
}
$public = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base . '/public'));
foreach ($public as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $cmd = 'php -l ' . escapeshellarg($file->getPathname());
    exec($cmd, $output, $code);
    if ($code !== 0) {
        $errors[] = [$file->getPathname(), implode("\n", $output)];
    }
}
if ($errors) {
    echo "Falhas:\n";
    foreach ($errors as [$path, $log]) {
        echo $path . "\n" . $log . "\n\n";
    }
    exit(1);
}
echo "Sintaxe OK.\n";
