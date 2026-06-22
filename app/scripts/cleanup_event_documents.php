<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

extract(app_container());

$pdo = $database->pdo();
$baseStorage = realpath(__DIR__ . '/../storage');
$stmt = $pdo->query("SELECT id, xml_path, schema_name FROM documents
    WHERE LOWER(COALESCE(schema_name, '')) LIKE '%evento%'
       OR LOWER(COALESCE(raw_xml, '')) LIKE '%<evento%'
       OR LOWER(COALESCE(raw_xml, '')) LIKE '%<procevento%'
       OR LOWER(COALESCE(raw_xml, '')) LIKE '%<resevento%'");
$docs = $stmt->fetchAll();

$ids = [];
$filesDeleted = 0;
$filesSkipped = 0;

foreach ($docs as $doc) {
    $ids[] = (int)$doc['id'];
    $path = (string)($doc['xml_path'] ?? '');
    if ($path === '') {
        continue;
    }
    $real = realpath($path);
    if (!$real || !$baseStorage || !str_starts_with($real, $baseStorage)) {
        $filesSkipped++;
        continue;
    }
    if (is_file($real) && @unlink($real)) {
        $filesDeleted++;
    } else {
        $filesSkipped++;
    }
}

$deletedItems = 0;
$deletedDocs = 0;
if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM period_closure_items WHERE document_id IN ($placeholders)");
    $stmt->execute($ids);
    $deletedItems = $stmt->rowCount();

    $stmt = $pdo->prepare("DELETE FROM documents WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $deletedDocs = $stmt->rowCount();
}

$message = 'Limpeza de eventos: ' . $deletedDocs . ' documento(s) removido(s), ' . $deletedItems . ' item(ns) de fechamento removido(s), ' . $filesDeleted . ' arquivo(s) apagado(s), ' . $filesSkipped . ' arquivo(s) ignorado(s).';
$repo->logAction('cleanup_event_documents', $message);
$storage->appendLog('cleanup_event_documents.log', $message);

echo $message . PHP_EOL;
