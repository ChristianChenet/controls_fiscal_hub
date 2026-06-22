<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

extract(app_container());

$docs = $repo->documents(['q' => '']);
$checked = 0;
$updated = 0;
$errors = 0;

foreach ($docs as $doc) {
    $xml = (string)($doc['raw_xml'] ?? '');
    if ($xml === '' && !empty($doc['xml_path']) && is_file((string)$doc['xml_path'])) {
        $xml = (string)file_get_contents((string)$doc['xml_path']);
    }
    if (trim($xml) === '') {
        continue;
    }

    $checked++;
    try {
        $parsed = $parser->parse($xml);
        $changed = false;
        foreach (['doc_type', 'access_key', 'number', 'issuer_cnpj', 'issuer_name', 'recipient_cnpj', 'recipient_name', 'issue_date'] as $field) {
            if ((string)($doc[$field] ?? '') !== (string)($parsed[$field] ?? '')) {
                $changed = true;
                break;
            }
        }
        if (!$changed && abs((float)($doc['total_value'] ?? 0) - (float)($parsed['total_value'] ?? 0)) > 0.009) {
            $changed = true;
        }
        if (!$changed) {
            continue;
        }

        $repo->saveDocument($parsed + [
            'company_id' => $doc['company_id'] ?? null,
            'company_name' => $doc['company_name'] ?? null,
            'company_cnpj' => $doc['company_cnpj'] ?? null,
            'source' => $doc['source'] ?? 'manual_import',
            'xml_path' => $doc['xml_path'] ?? null,
            'storage_dir' => $doc['storage_dir'] ?? null,
            'schema_name' => $doc['schema_name'] ?? null,
            'imported_at' => $doc['imported_at'] ?? date('c'),
            'updated_at' => date('c'),
            'notes' => trim((string)($parsed['notes'] ?? '') . ' Reclassificado pela rotina de reparo.'),
        ]);
        $updated++;
    } catch (Throwable $e) {
        $errors++;
        $repo->logAction('xml_repair_error', 'Documento #' . ($doc['id'] ?? '?') . ': ' . $e->getMessage(), isset($doc['company_id']) ? (int)$doc['company_id'] : null);
    }
}

$repo->logAction('xml_repair', 'Reparo de documentos concluído: ' . $checked . ' verificados, ' . $updated . ' atualizados, ' . $errors . ' erro(s).');
echo "Verificados: {$checked}\n";
echo "Atualizados: {$updated}\n";
echo "Erros: {$errors}\n";
