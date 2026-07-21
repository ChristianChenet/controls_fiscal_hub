<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

extract(app_container());

$page = $_GET['page'] ?? 'dashboard';

function request_values(array $source, string $key): array|string
{
    if (!array_key_exists($key, $source)) {
        return '';
    }
    $value = $source[$key];
    if (is_array($value)) {
        return array_values(array_filter(array_map('strval', $value), static fn(string $item): bool => trim($item) !== ''));
    }
    return (string)$value;
}

function revenue_filters_from_request(array $source): array
{
    return [
        'date_start' => $source['date_start'] ?? date('Y-m-01'),
        'date_end' => $source['date_end'] ?? date('Y-m-d'),
        'document_type' => $source['document_type'] ?? '',
        'document_status' => $source['document_status'] ?? '',
        'purpose' => $source['purpose'] ?? '',
        'sale_return' => $source['sale_return'] ?? '',
        'issuing_store_cnpj' => request_values($source, 'issuing_store_cnpj'),
        'issuing_store_name' => $source['issuing_store_name'] ?? '',
        'order_store_cnpj' => $source['order_store_cnpj'] ?? '',
        'order_store_name' => request_values($source, 'order_store_name'),
        'customer_name' => $source['customer_name'] ?? '',
        'customer_document' => $source['customer_document'] ?? '',
        'seller_name' => $source['seller_name'] ?? '',
        'order_number' => $source['order_number'] ?? '',
        'order_link' => $source['order_link'] ?? '',
        'number' => $source['number'] ?? '',
        'series' => $source['series'] ?? '',
        'access_key' => $source['access_key'] ?? '',
        'product' => $source['product'] ?? '',
        'product_group' => $source['product_group'] ?? '',
        'cfop' => $source['cfop'] ?? '',
        'ncm' => $source['ncm'] ?? '',
        'cst_csosn' => $source['cst_csosn'] ?? '',
        'xml_available' => $source['xml_available'] ?? '',
        'amount_min' => $source['amount_min'] ?? '',
        'amount_max' => $source['amount_max'] ?? '',
        'include_returns' => array_key_exists('include_returns', $source) ? (string)$source['include_returns'] : '1',
        'include_tax_credits' => array_key_exists('include_tax_credits', $source) ? (string)$source['include_tax_credits'] : '1',
        'show_previous_month' => array_key_exists('show_previous_month', $source) ? (string)$source['show_previous_month'] : '1',
        'sort_by' => $source['sort_by'] ?? 'issue_date',
        'sort_dir' => $source['sort_dir'] ?? 'desc',
    ];
}

function document_filters_from_request(array $source): array
{
    $docType = strtoupper((string)($source['doc_type'] ?? ''));
    if (!in_array($docType, ['NFE', 'CTE'], true)) {
        $docType = '';
    }
    return [
        'company_id' => request_values($source, 'company_id'),
        'doc_type' => $docType,
        'status' => $source['status'] ?? '',
        'manifestation_status' => $source['manifestation_status'] ?? '',
        'posted_to_erp' => $source['posted_to_erp'] ?? '',
        'without_referenced_nfe' => $source['without_referenced_nfe'] ?? '',
        'entry_only' => '1',
        'date_start' => $source['date_start'] ?? '',
        'date_end' => $source['date_end'] ?? '',
        'company_q' => $source['company_q'] ?? '',
        'number_q' => $source['number_q'] ?? '',
        'issuer_q' => $source['issuer_q'] ?? '',
        'recipient_q' => $source['recipient_q'] ?? '',
        'access_key_q' => $source['access_key_q'] ?? '',
        'referenced_nfe_q' => $source['referenced_nfe_q'] ?? '',
        'referenced_number_q' => $source['referenced_number_q'] ?? '',
        'product_q' => $source['product_q'] ?? '',
        'cfop_q' => $source['cfop_q'] ?? '',
        'cte_taker_only' => $source['cte_taker_only'] ?? '',
        'ignore_cfops' => array_key_exists('ignore_cfops', $source) ? (string)$source['ignore_cfops'] : '1',
        'source_q' => $source['source_q'] ?? '',
        'q' => $source['q'] ?? '',
        'sort_by' => $source['sort_by'] ?? 'issue_date',
        'sort_dir' => $source['sort_dir'] ?? 'desc',
    ];
}

function documents_xml_content(array $doc): string
{
    $xml = (string)($doc['raw_xml'] ?? '');
    $path = (string)($doc['xml_path'] ?? '');
    if (trim($xml) === '' && $path !== '' && is_file($path)) {
        $xml = (string)file_get_contents($path);
    }
    return $xml;
}

function documents_download_filename(array $doc, string $extension): string
{
    $base = strtoupper((string)($doc['doc_type'] ?? 'DOC')) . '_' . ((string)($doc['access_key'] ?? '') ?: ((string)($doc['number'] ?? '') ?: (string)($doc['id'] ?? uniqid())));
    return preg_replace('/[^a-zA-Z0-9._-]/', '_', $base) . '.' . $extension;
}

function documents_danfe_filename(array $doc): string
{
    return documents_download_filename($doc, 'html');
}

function xml_first(DOMXPath $xp, array $exprs): string
{
    foreach ($exprs as $expr) {
        $nodes = $xp->query($expr);
        if ($nodes && $nodes->length > 0) {
            $value = trim((string)$nodes->item(0)?->textContent);
            if ($value !== '') {
                return $value;
            }
        }
    }
    return '';
}

function xml_attr_first(DOMXPath $xp, array $exprs): string
{
    foreach ($exprs as $expr) {
        $nodes = $xp->query($expr);
        if ($nodes && $nodes->length > 0) {
            $value = trim((string)$nodes->item(0)?->nodeValue);
            if ($value !== '') {
                return $value;
            }
        }
    }
    return '';
}

function documents_money(?string $value): string
{
    $number = (float)str_replace(',', '.', trim((string)$value));
    return format_money($number);
}

function documents_party(DOMXPath $xp, string $tag): array
{
    $base = '//*[local-name()="' . $tag . '"]';
    return [
        'nome' => xml_first($xp, [$base . '/*[local-name()="xNome"]']),
        'documento' => xml_first($xp, [$base . '/*[local-name()="CNPJ"]', $base . '/*[local-name()="CPF"]']),
        'ie' => xml_first($xp, [$base . '/*[local-name()="IE"]']),
        'endereco' => trim(implode(', ', array_filter([
            xml_first($xp, [$base . '//*[local-name()="xLgr"]']),
            xml_first($xp, [$base . '//*[local-name()="nro"]']),
            xml_first($xp, [$base . '//*[local-name()="xBairro"]']),
            xml_first($xp, [$base . '//*[local-name()="xMun"]']),
            xml_first($xp, [$base . '//*[local-name()="UF"]']),
            xml_first($xp, [$base . '//*[local-name()="CEP"]']),
        ]))),
    ];
}

function documents_danfe_details(array $doc): array
{
    $xml = documents_xml_content($doc);
    $type = strtoupper((string)($doc['doc_type'] ?? ''));
    $details = ['items' => [], 'totals' => [], 'extra' => []];
    if (trim($xml) === '') {
        return $details;
    }
    $dom = new DOMDocument();
    if (!$dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
        return $details;
    }
    $xp = new DOMXPath($dom);
    if ($type === 'CTE') {
        foreach ($xp->query('//*[local-name()="Comp"]') ?: [] as $node) {
            $local = new DOMXPath($node->ownerDocument);
            $details['items'][] = [
                'codigo' => '',
                'descricao' => xml_first($local, ['//*[local-name()="Comp"]/*[local-name()="xNome"]']),
                'ncm' => '',
                'cfop' => xml_first($xp, ['//*[local-name()="ide"]/*[local-name()="CFOP"]']),
                'quantidade' => '',
                'unidade' => '',
                'unitario' => '',
                'total' => documents_money(xml_first($local, ['//*[local-name()="Comp"]/*[local-name()="vComp"]'])),
                'icms' => '',
                'pis' => '',
                'cofins' => '',
                'ipi' => '',
                'st' => '',
                'iss' => '',
            ];
        }
        $details['extra'] = [
            'CFOP' => xml_first($xp, ['//*[local-name()="ide"]/*[local-name()="CFOP"]']),
            'Natureza da operacao' => xml_first($xp, ['//*[local-name()="ide"]/*[local-name()="natOp"]']),
            'Inicio/Fim' => trim(xml_first($xp, ['//*[local-name()="ide"]/*[local-name()="UFIni"]']) . ' / ' . xml_first($xp, ['//*[local-name()="ide"]/*[local-name()="UFFim"]'])),
            'Valor a receber' => documents_money(xml_first($xp, ['//*[local-name()="vPrest"]/*[local-name()="vRec"]'])),
            'NF-e vinculadas' => (string)($doc['referenced_nfe_keys'] ?? ''),
        ];
        $details['totals'] = [
            'Valor total do servico' => documents_money(xml_first($xp, ['//*[local-name()="vPrest"]/*[local-name()="vTPrest"]'])),
            'Base ICMS' => documents_money(xml_first($xp, ['//*[local-name()="ICMS"]//*[local-name()="vBC"]'])),
            'Valor ICMS' => documents_money(xml_first($xp, ['//*[local-name()="ICMS"]//*[local-name()="vICMS"]'])),
        ];
        return $details;
    }

    foreach ($xp->query('//*[local-name()="det"]') ?: [] as $det) {
        $itemXp = new DOMXPath($det->ownerDocument);
        $nItem = $det instanceof DOMElement ? $det->getAttribute('nItem') : '';
        $path = '//*[local-name()="det"][@nItem="' . $nItem . '"]';
        $details['items'][] = [
            'codigo' => xml_first($itemXp, [$path . '/*[local-name()="prod"]/*[local-name()="cProd"]']),
            'descricao' => xml_first($itemXp, [$path . '/*[local-name()="prod"]/*[local-name()="xProd"]']),
            'ncm' => xml_first($itemXp, [$path . '/*[local-name()="prod"]/*[local-name()="NCM"]']),
            'cfop' => xml_first($itemXp, [$path . '/*[local-name()="prod"]/*[local-name()="CFOP"]']),
            'quantidade' => xml_first($itemXp, [$path . '/*[local-name()="prod"]/*[local-name()="qCom"]']),
            'unidade' => xml_first($itemXp, [$path . '/*[local-name()="prod"]/*[local-name()="uCom"]']),
            'unitario' => documents_money(xml_first($itemXp, [$path . '/*[local-name()="prod"]/*[local-name()="vUnCom"]'])),
            'total' => documents_money(xml_first($itemXp, [$path . '/*[local-name()="prod"]/*[local-name()="vProd"]'])),
            'icms' => documents_money(xml_first($itemXp, [$path . '/*[local-name()="imposto"]/*[local-name()="ICMS"]//*[local-name()="vICMS"]'])),
            'pis' => documents_money(xml_first($itemXp, [$path . '/*[local-name()="imposto"]/*[local-name()="PIS"]//*[local-name()="vPIS"]'])),
            'cofins' => documents_money(xml_first($itemXp, [$path . '/*[local-name()="imposto"]/*[local-name()="COFINS"]//*[local-name()="vCOFINS"]'])),
            'ipi' => documents_money(xml_first($itemXp, [$path . '/*[local-name()="imposto"]/*[local-name()="IPI"]//*[local-name()="vIPI"]'])),
            'st' => documents_money(xml_first($itemXp, [$path . '/*[local-name()="imposto"]/*[local-name()="ICMS"]//*[local-name()="vICMSST"]', $path . '/*[local-name()="imposto"]/*[local-name()="ICMS"]//*[local-name()="vST"]'])),
            'iss' => documents_money(xml_first($itemXp, [$path . '/*[local-name()="imposto"]/*[local-name()="ISSQN"]/*[local-name()="vISSQN"]'])),
        ];
    }
    $details['extra'] = [
        'Natureza da operacao' => xml_first($xp, ['//*[local-name()="ide"]/*[local-name()="natOp"]']),
        'Serie' => xml_first($xp, ['//*[local-name()="ide"]/*[local-name()="serie"]']),
        'Modelo' => xml_first($xp, ['//*[local-name()="ide"]/*[local-name()="mod"]']),
        'Protocolo' => xml_first($xp, ['//*[local-name()="protNFe"]//*[local-name()="nProt"]']),
        'Data autorizacao' => format_date(xml_first($xp, ['//*[local-name()="protNFe"]//*[local-name()="dhRecbto"]'])),
    ];
    $details['totals'] = [
        'Base ICMS' => documents_money(xml_first($xp, ['//*[local-name()="ICMSTot"]/*[local-name()="vBC"]'])),
        'Valor ICMS' => documents_money(xml_first($xp, ['//*[local-name()="ICMSTot"]/*[local-name()="vICMS"]'])),
        'ICMS ST' => documents_money(xml_first($xp, ['//*[local-name()="ICMSTot"]/*[local-name()="vST"]'])),
        'Produtos' => documents_money(xml_first($xp, ['//*[local-name()="ICMSTot"]/*[local-name()="vProd"]'])),
        'Frete' => documents_money(xml_first($xp, ['//*[local-name()="ICMSTot"]/*[local-name()="vFrete"]'])),
        'Seguro' => documents_money(xml_first($xp, ['//*[local-name()="ICMSTot"]/*[local-name()="vSeg"]'])),
        'Desconto' => documents_money(xml_first($xp, ['//*[local-name()="ICMSTot"]/*[local-name()="vDesc"]'])),
        'IPI' => documents_money(xml_first($xp, ['//*[local-name()="ICMSTot"]/*[local-name()="vIPI"]'])),
        'PIS' => documents_money(xml_first($xp, ['//*[local-name()="ICMSTot"]/*[local-name()="vPIS"]'])),
        'COFINS' => documents_money(xml_first($xp, ['//*[local-name()="ICMSTot"]/*[local-name()="vCOFINS"]'])),
        'Total NF-e' => documents_money(xml_first($xp, ['//*[local-name()="ICMSTot"]/*[local-name()="vNF"]'])),
    ];
    return $details;
}

function documents_xml_flat_fields(string $xml, int $limit = 5000): array
{
    $fields = [];
    if (trim($xml) === '') {
        return $fields;
    }
    $dom = new DOMDocument();
    if (!$dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
        return $fields;
    }

    // Mantem o espelho completo para auditoria: atributos e todos os campos folha do XML.
    $walk = function (DOMNode $node, string $path) use (&$walk, &$fields, $limit): void {
        if (count($fields) >= $limit) {
            return;
        }
        if ($node instanceof DOMElement) {
            $name = $node->localName ?: $node->nodeName;
            $sameBefore = 0;
            for ($prev = $node->previousSibling; $prev; $prev = $prev->previousSibling) {
                if ($prev instanceof DOMElement && ($prev->localName ?: $prev->nodeName) === $name) {
                    $sameBefore++;
                }
            }
            $currentPath = $path === '' ? $name : $path . '/' . $name . ($sameBefore > 0 ? '[' . ($sameBefore + 1) . ']' : '');
            if ($node->hasAttributes()) {
                foreach ($node->attributes as $attr) {
                    $fields[] = [$currentPath . '/@' . $attr->nodeName, trim((string)$attr->nodeValue)];
                    if (count($fields) >= $limit) {
                        return;
                    }
                }
            }
            $childElements = [];
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $childElements[] = $child;
                }
            }
            $value = trim((string)$node->textContent);
            if (!$childElements && $value !== '') {
                $fields[] = [$currentPath, $value];
                return;
            }
            foreach ($childElements as $child) {
                $walk($child, $currentPath);
                if (count($fields) >= $limit) {
                    return;
                }
            }
        }
    };
    if ($dom->documentElement) {
        $walk($dom->documentElement, '');
    }
    return $fields;
}

function documents_zip_response(array $files, string $downloadName, string $emptyMessage): void
{
    $files = array_values(array_filter($files, static fn(array $file): bool => trim((string)($file['content'] ?? '')) !== ''));
    if (!$files) {
        header('Content-Type: text/plain; charset=utf-8');
        echo $emptyMessage;
        exit;
    }
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    @ini_set('memory_limit', '1024M');
    $zipPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'controls_export_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Nao foi possivel criar o arquivo ZIP. Verifique permissao na pasta temporaria do servidor.';
        exit;
    }
    $used = [];
    foreach ($files as $file) {
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)($file['name'] ?? ('arquivo_' . count($used))));
        if ($name === '') {
            $name = 'arquivo_' . count($used) . '.txt';
        }
        $entry = $name;
        $suffix = 2;
        while (isset($used[$entry])) {
            $entry = preg_replace('/(\.[^.]+)$/', '_' . $suffix . '$1', $name);
            if ($entry === $name) {
                $entry = $name . '_' . $suffix;
            }
            $suffix++;
        }
        $used[$entry] = true;
        $zip->addFromString($entry, (string)$file['content']);
    }
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    @unlink($zipPath);
    exit;
}

function documents_danfe_html(array $doc, bool $autoPrint = false): string
{
    $type = strtoupper((string)($doc['doc_type'] ?? ''));
    $title = $type === 'CTE' ? 'DACTE' : 'DANFE';
    $subtitle = $type === 'CTE' ? 'Documento Auxiliar do Conhecimento de Transporte Eletronico' : 'Documento Auxiliar da Nota Fiscal Eletronica';
    $details = documents_danfe_details($doc);
    $xml = documents_xml_content($doc);
    $allFields = documents_xml_flat_fields($xml);
    $emit = ['nome' => (string)($doc['issuer_name'] ?? ''), 'documento' => (string)($doc['issuer_cnpj'] ?? ''), 'ie' => '', 'endereco' => ''];
    $dest = ['nome' => (string)($doc['recipient_name'] ?? ''), 'documento' => (string)($doc['recipient_cnpj'] ?? ''), 'ie' => '', 'endereco' => ''];
    $summary = ['Pedidos (xPed)' => [], 'Observacoes' => []];
    if (trim($xml) !== '') {
        $dom = new DOMDocument();
        if ($dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
            $xp = new DOMXPath($dom);
            $emit = documents_party($xp, 'emit');
            $dest = documents_party($xp, 'dest');
            if ($type === 'CTE' && $dest['nome'] === '') {
                $dest = documents_party($xp, 'rem');
            }
            foreach ($xp->query('//*[local-name()="xPed"]') ?: [] as $node) {
                $value = trim((string)$node->textContent);
                if ($value !== '') { $summary['Pedidos (xPed)'][] = $value; }
            }
            foreach (['infCpl','infAdFisco','xObs','xTexto','infAdic','obsCont','obsFisco'] as $tag) {
                foreach ($xp->query('//*[local-name()="' . $tag . '"]') ?: [] as $node) {
                    $value = trim((string)$node->textContent);
                    if ($value !== '') { $summary['Observacoes'][] = $value; }
                }
            }
        }
    }
    $rows = [
        'Tipo' => $type,
        'Numero' => (string)($doc['number'] ?? ''),
        'Modelo' => (string)($doc['model'] ?? ''),
        'Chave de acesso' => (string)($doc['access_key'] ?? ''),
        'Empresa do portal' => trim((string)($doc['company_name'] ?? '') . ' - ' . (string)($doc['company_cnpj'] ?? '')),
        'Emissao' => format_date($doc['issue_date'] ?? null),
        'Valor' => format_money((float)($doc['total_value'] ?? 0)),
        'Status' => document_status_label((string)($doc['status'] ?? '')),
    ];
    foreach ($details['extra'] as $label => $value) {
        if ((string)$value !== '') { $rows[(string)$label] = (string)$value; }
    }
    foreach ($summary as $label => $values) {
        $values = array_values(array_unique(array_filter($values)));
        if ($values) { $rows[$label] = implode(' | ', $values); }
    }
    $docRows = '';
    foreach ($rows as $label => $value) {
        $docRows .= '<tr><th>' . h($label) . '</th><td>' . h($value) . '</td></tr>';
    }
    $party = static function (string $title, array $data): string {
        return '<section class="box party"><h2>' . h($title) . '</h2><strong>' . h($data['nome']) . '</strong><span>CNPJ/CPF: ' . h($data['documento']) . '</span><span>IE: ' . h($data['ie']) . '</span><small>' . h($data['endereco']) . '</small></section>';
    };
    $totalHtml = '';
    foreach ($details['totals'] as $label => $value) {
        $totalHtml .= '<div><span>' . h((string)$label) . '</span><strong>' . h((string)$value) . '</strong></div>';
    }
    $itemsHtml = '';
    foreach ($details['items'] as $item) {
        $itemsHtml .= '<tr><td>' . h((string)$item['codigo']) . '</td><td>' . h((string)$item['descricao']) . '</td><td>' . h((string)$item['ncm']) . '</td><td>' . h((string)$item['cfop']) . '</td><td>' . h((string)$item['quantidade']) . '</td><td>' . h((string)$item['unidade']) . '</td><td>' . h((string)$item['unitario']) . '</td><td>' . h((string)$item['total']) . '</td><td>' . h((string)($item['icms'] ?? '')) . '</td><td>' . h((string)($item['pis'] ?? '')) . '</td><td>' . h((string)($item['cofins'] ?? '')) . '</td><td>' . h((string)($item['ipi'] ?? '')) . '</td><td>' . h((string)($item['st'] ?? '')) . '</td><td>' . h((string)($item['iss'] ?? '')) . '</td></tr>';
    }
    if ($itemsHtml === '') {
        $itemsHtml = '<tr><td colspan="14">Itens/componentes nao localizados no XML disponivel.</td></tr>';
    }
    $fieldsHtml = '';
    foreach ($allFields as [$field, $value]) {
        $fieldsHtml .= '<tr><td>' . h((string)$field) . '</td><td>' . h((string)$value) . '</td></tr>';
    }
    if ($fieldsHtml === '') {
        $fieldsHtml = '<tr><td colspan="2">XML nao disponivel ou sem campos detalhados para exibir.</td></tr>';
    }
    $obs = implode(' | ', array_values(array_unique(array_filter($summary['Observacoes']))));
    $printScript = $autoPrint ? '<script>window.addEventListener("load", function(){ window.print(); });</script>' : '';
    return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>' . h($title) . '</title><style>
        *{box-sizing:border-box}body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:8px;background:#fff;font-size:10px}.sheet{max-width:1120px;margin:auto;border:2px solid #222;padding:10px;position:relative}.receipt{display:grid;grid-template-columns:1fr 1fr 1fr 160px;border:1px solid #222;margin-bottom:6px}.receipt div{border-right:1px solid #222;min-height:42px;padding:4px}.receipt div:last-child{border-right:0}.center{text-align:center}.doc-head{display:grid;grid-template-columns:1.4fr 220px 1.2fr;gap:0;border:1px solid #222}.doc-head>div{border-right:1px solid #222;padding:6px}.doc-head>div:last-child{border-right:0}.doc-title h1{font-size:24px;margin:0}.doc-title strong{display:block;font-size:12px}.barcode{font-family:monospace;font-size:13px;letter-spacing:1px;border:1px solid #222;padding:6px;text-align:center;margin:6px 0;word-break:break-all}.barcode-bars{height:44px;margin:4px 0;border:1px solid #222;background:repeating-linear-gradient(90deg,#111 0 2px,#fff 2px 4px,#111 4px 5px,#fff 5px 8px,#111 8px 11px,#fff 11px 14px)}.access{font-size:12px;text-align:center;font-weight:bold}.grid2{display:grid;grid-template-columns:1fr 1fr;gap:0}.box{border:1px solid #222;border-top:0;padding:6px;min-height:58px}.box h2{font-size:10px;text-transform:uppercase;text-align:center;background:#f1f1f1;border:1px solid #222;margin:0 0 5px;padding:3px}.section-title{font-size:10px;text-transform:uppercase;text-align:center;background:#f1f1f1;border:1px solid #222;margin:8px 0 0;padding:4px}.party{display:grid;gap:2px}.doc-table,.items,.xml-fields{width:100%;border-collapse:collapse;margin:0}.doc-table th,.doc-table td,.items th,.items td,.xml-fields th,.xml-fields td{border:1px solid #222;padding:4px;text-align:left;vertical-align:top;word-break:break-word}.doc-table th{width:180px;background:#f7f7f7}.totals{display:grid;grid-template-columns:repeat(4,1fr);border-left:1px solid #222}.totals div{border-right:1px solid #222;border-bottom:1px solid #222;padding:5px;min-height:42px}.totals span{display:block;text-transform:uppercase;font-size:9px}.totals strong{font-size:12px}.items th,.xml-fields th{background:#f1f1f1;text-transform:uppercase;font-size:8px}.items td,.xml-fields td{font-size:8px}.xml-fields td:first-child{width:34%;font-family:Consolas,monospace}.watermark{font-size:74px;color:#999;opacity:.45;text-align:center;font-weight:800;letter-spacing:2px;margin:22px 0}.obs{min-height:58px}.xml-section{page-break-before:always;break-before:page;margin-top:18px}.no-print{position:fixed;right:18px;top:14px;padding:8px 12px}@media print{body{margin:0}.sheet{border:1px solid #222;max-width:none}.no-print{display:none}.watermark{font-size:66px}.xml-fields{page-break-before:auto}}
    </style></head><body><button class="no-print" onclick="window.print()">Imprimir</button><div class="sheet"><div class="receipt"><div><strong>Recebimento</strong><br>Declaro que recebi os produtos/servicos constantes neste documento.</div><div>Data / hora</div><div>Identificacao e assinatura</div><div class="center"><strong>' . h($title) . '</strong><br>N. ' . h((string)($doc['number'] ?? '')) . '</div></div><div class="doc-head"><div><h2>Identificacao do emitente</h2><strong>' . h($emit['nome']) . '</strong><br>CNPJ/CPF: ' . h($emit['documento']) . '<br>IE: ' . h($emit['ie']) . '<br>' . h($emit['endereco']) . '</div><div class="doc-title center"><h1>' . h($title) . '</h1><strong>' . h($subtitle) . '</strong><p>Espelho operacional</p></div><div><strong>Chave de acesso</strong><div class="barcode-bars" aria-label="Codigo de barras da chave"></div><div class="barcode">' . h((string)($doc['access_key'] ?? '')) . '</div><div class="access">' . h((string)($doc['access_key'] ?? '')) . '</div></div></div><table class="doc-table">' . $docRows . '</table><div class="grid2">' . $party('Destinatario / Tomador', $dest) . $party('Emitente', $emit) . '</div><div class="section-title">Totais</div><div class="totals">' . $totalHtml . '</div><div class="section-title">Dados do produto / servico</div><table class="items"><thead><tr><th>Codigo</th><th>Descricao</th><th>NCM</th><th>CFOP</th><th>Qtd</th><th>Un</th><th>Unitario</th><th>Total</th><th>ICMS</th><th>PIS</th><th>COFINS</th><th>IPI</th><th>ST</th><th>ISS</th></tr></thead><tbody>' . $itemsHtml . '</tbody></table><div class="watermark">SEM VALOR FISCAL</div><div class="box obs"><h2>Observacoes</h2>' . h($obs) . '</div><section class="xml-section"><div class="section-title">Todos os campos do XML</div><table class="xml-fields"><thead><tr><th>Campo XML</th><th>Valor</th></tr></thead><tbody>' . $fieldsHtml . '</tbody></table></section></div>' . $printScript . '</body></html>';
}



function documents_xml_payload(array $documents, int $maxFiles = 1000, int $maxBytes = 62914560): array
{
    $files = [];
    $skipped = 0;
    $bytes = 0;
    $truncated = false;
    foreach ($documents as $doc) {
        if (count($files) >= $maxFiles || $bytes >= $maxBytes) {
            $truncated = true;
            break;
        }
        $xml = (string)($doc['raw_xml'] ?? '');
        $path = (string)($doc['xml_path'] ?? '');
        if ($xml === '' && $path !== '' && is_file($path)) {
            $xml = (string)file_get_contents($path);
        }
        if (trim($xml) === '') {
            $skipped++;
            continue;
        }
        $bytes += strlen($xml);
        if ($bytes > $maxBytes) {
            $truncated = true;
            break;
        }
        $nameBase = strtoupper((string)($doc['doc_type'] ?? 'XML')) . '_' . ((string)($doc['access_key'] ?? '') ?: ((string)($doc['number'] ?? '') ?: (string)($doc['id'] ?? uniqid())));
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $nameBase) . '.xml';
        $files[] = ['name' => $filename, 'content' => $xml];
    }
    return ['ok' => true, 'files' => $files, 'skipped' => $skipped, 'truncated' => $truncated, 'bytes' => $bytes, 'limit_files' => $maxFiles];
}

if ($page === 'logout') {
    $auth->logout();
    redirect_to(base_url('?page=login'));
}

function first_allowed_page_for_user(\ControlS\Portal\Auth $auth): string
{
    foreach (['dashboard', 'revenue', 'documents'] as $candidatePage) {
        if ($auth->canAccess($candidatePage)) {
            return $candidatePage;
        }
    }
    return 'login';
}

function page_url(string $page): string
{
    if ($page === 'dashboard') {
        return base_url();
    }
    return base_url('?page=' . $page);
}

if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        flash_set('danger', 'Token CSRF inválido.');
        redirect_to(base_url('?page=login'));
    }
    if ($auth->login(trim($_POST['user'] ?? ''), trim($_POST['pass'] ?? ''))) {
        flash_set('success', 'Acesso liberado.');
        redirect_to(page_url(first_allowed_page_for_user($auth)));
    }
    flash_set('danger', 'Usuário ou senha inválidos.');
    redirect_to(base_url('?page=login'));
}

if ($page !== 'login') {
    $auth->require();
    if ($page === 'dashboard' && !$auth->canAccess('dashboard')) {
        redirect_to(page_url(first_allowed_page_for_user($auth)));
    }
    if (!$auth->canAccess($page)) {
        flash_set('danger', 'Seu perfil tem acesso somente a Faturamento e Entradas.');
        redirect_to(page_url(first_allowed_page_for_user($auth)));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        flash_set('danger', 'Token CSRF inválido.');
        redirect_to(base_url('?page=' . $page));
    }

    try {
        if (!$auth->canAccess($page)) {
            throw new RuntimeException('Seu perfil nao tem permissao para esta operacao.');
        }
        switch ($page) {
            case 'users':
                if (!$auth->isAdmin()) {
                    throw new RuntimeException('Apenas administradores podem gerenciar usuarios.');
                }
                if (isset($_POST['save_user'])) {
                    $id = $repo->saveUser([
                        'id' => (int)($_POST['user_id'] ?? 0),
                        'name' => trim((string)($_POST['name'] ?? '')),
                        'email' => trim((string)($_POST['email'] ?? '')),
                        'password' => (string)($_POST['password'] ?? ''),
                        'role' => (string)($_POST['role'] ?? 'user'),
                        'can_view_cost' => !empty($_POST['can_view_cost']),
                        'is_active' => !empty($_POST['is_active']),
                    ]);
                    flash_set('success', 'Usuario salvo. ID ' . $id);
                }
                break;

            case 'settings':
                if (isset($_POST['save_settings'])) {
                    foreach ([
                        'default_download_dir',
                        'storage_path_mode',
                        'storage_path_template',
                        'client_display_name',
                        'client_label',
                        'sefaz_environment',
                        'sefaz_uf_author',
                        'nfe_distribution_url',
                        'nfe_distribution_action',
                        'nfe_recepcaoevento_url',
                        'nfe_recepcaoevento_action',
                        'nfe_consulta_protocolo_url',
                        'nfe_consulta_protocolo_action',
                        'cte_distribution_url',
                        'cte_distribution_action',
                        'nfse_base_url',
                        'nfse_distribution_path',
                        'nfse_auth_type',
                        'nfse_token',
                        'nfse_page_size',
                        'auto_cte_company_id',
                        'auto_cte_rewind_nsu_once',
                        'auto_cte_interval_minutes',
                        'cte_robot_max_cycles',
                        'cte_robot_time_limit_seconds',
                        'auto_nfe_company_id',
                        'auto_nfe_rewind_nsu_once',
                        'auto_nfe_interval_minutes',
                        'nfe_robot_max_cycles',
                        'nfe_robot_time_limit_seconds',
                        'nfe_science_limit_per_run',
                        'auto_nfse_company_id',
                        'auto_nfse_interval_minutes',
                        'auto_nfse_nsu_limit',
                    ] as $settingKey) {
                        if (array_key_exists($settingKey, $_POST)) {
                            $repo->setSetting($settingKey, trim((string)$_POST[$settingKey]));
                        }
                    }
                    $activeCompanyIds = array_map(static fn(array $co): int => (int)$co['id'], $repo->activeCompanies());
                    $autoCteCompanyIds = !empty($_POST['auto_cte_all_companies'])
                        ? $activeCompanyIds
                        : array_values(array_unique(array_filter(array_map('intval', $_POST['auto_cte_company_ids'] ?? []))));
                    $autoNfeCompanyIds = !empty($_POST['auto_nfe_all_companies'])
                        ? $activeCompanyIds
                        : array_values(array_unique(array_filter(array_map('intval', $_POST['auto_nfe_company_ids'] ?? []))));
                    $autoNfseCompanyIds = !empty($_POST['auto_nfse_all_companies'])
                        ? $activeCompanyIds
                        : array_values(array_unique(array_filter(array_map('intval', $_POST['auto_nfse_company_ids'] ?? []))));
                    $repo->setSetting('auto_cte_company_ids', implode(',', $autoCteCompanyIds));
                    $repo->setSetting('auto_nfe_company_ids', implode(',', $autoNfeCompanyIds));
                    $repo->setSetting('auto_nfse_company_ids', implode(',', $autoNfseCompanyIds));
                    $repo->setSetting('auto_cte_company_id', (string)($autoCteCompanyIds[0] ?? 0));
                    $repo->setSetting('auto_nfe_company_id', (string)($autoNfeCompanyIds[0] ?? 0));
                    $repo->setSetting('auto_nfse_company_id', (string)($autoNfseCompanyIds[0] ?? 0));
                    $repo->setSetting('auto_cte_enabled', !empty($_POST['auto_cte_enabled']) ? '1' : '0');
                    $repo->setSetting('auto_nfe_enabled', !empty($_POST['auto_nfe_enabled']) ? '1' : '0');
                    $repo->setSetting('auto_nfe_manifest_science', !empty($_POST['auto_nfe_manifest_science']) ? '1' : '0');
                    $repo->setSetting('auto_nfse_enabled', !empty($_POST['auto_nfse_enabled']) ? '1' : '0');
                    $repo->setSetting('auto_cte_all_companies', !empty($_POST['auto_cte_all_companies']) ? '1' : '0');
                    $repo->setSetting('auto_nfe_all_companies', !empty($_POST['auto_nfe_all_companies']) ? '1' : '0');
                    $repo->setSetting('auto_nfse_all_companies', !empty($_POST['auto_nfse_all_companies']) ? '1' : '0');
                    foreach ($activeCompanyIds as $companyIdForRewind) {
                        $repo->setSetting(
                            'auto_cte_rewind_nsu_once_company_' . $companyIdForRewind,
                            (string)max(0, min(50000, (int)($_POST['auto_cte_rewind_company'][$companyIdForRewind] ?? 0)))
                        );
                        $repo->setSetting(
                            'auto_nfe_rewind_nsu_once_company_' . $companyIdForRewind,
                            (string)max(0, min(50000, (int)($_POST['auto_nfe_rewind_company'][$companyIdForRewind] ?? 0)))
                        );
                    }
                    if (!empty($_FILES['client_logo']['name']) && ($_FILES['client_logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                        $extension = strtolower(pathinfo((string)$_FILES['client_logo']['name'], PATHINFO_EXTENSION));
                        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                            throw new RuntimeException('Logo do cliente deve ser JPG, PNG ou WEBP.');
                        }
                        $assetDir = __DIR__ . '/assets/client';
                        if (!is_dir($assetDir)) {
                            mkdir($assetDir, 0775, true);
                        }
                        $filename = 'logo-cliente.' . ($extension === 'jpeg' ? 'jpg' : $extension);
                        if (!move_uploaded_file($_FILES['client_logo']['tmp_name'], $assetDir . '/' . $filename)) {
                            throw new RuntimeException('Nao foi possivel salvar o logo do cliente.');
                        }
                        $repo->setSetting('client_logo_path', 'assets/client/' . $filename . '?v=' . time());
                    }
                    flash_set('success', 'Configurações salvas.');
                }
                break;

            case 'companies':
                if (isset($_POST['save_company'])) {
                    $id = $repo->saveCompany([
                        'id' => (int)($_POST['company_id'] ?? 0),
                        'company_name' => trim((string)($_POST['company_name'] ?? '')),
                        'cnpj' => trim((string)($_POST['company_cnpj'] ?? '')),
                        'default_download_dir' => trim((string)($_POST['default_download_dir'] ?? '')),
                        'is_active' => !empty($_POST['is_active']),
                    ]);
                    flash_set('success', 'Empresa salva. ID ' . $id);
                }
                if (isset($_POST['delete_company'])) {
                    $companyId = (int)($_POST['delete_company_id'] ?? 0);
                    $company = $repo->findCompany($companyId);
                    $repo->deleteCompanyIfUnlinked($companyId);
                    flash_set('success', 'Empresa excluída: ' . (string)($company['company_name'] ?? $companyId));
                    redirect_to(base_url('?page=companies'));
                }
                if (isset($_POST['upload_certificate'])) {
                    $companyId = (int)($_POST['certificate_company_id'] ?? 0);
                    $certificates->uploadAndActivate($_FILES['certificate'], (string)($_POST['certificate_password'] ?? ''), $companyId);
                    flash_set('success', 'Certificado enviado e ativado.');
                }
                if (isset($_POST['check_company_certificate'])) {
                    $companyId = (int)($_POST['check_company_id'] ?? 0);
                    $company = $repo->findCompany($companyId);
                    if (!$company) {
                        throw new RuntimeException('Empresa não encontrada.');
                    }
                    $health = $certificates->healthCheck($companyId);
                    $preview = $storage->previewXmlPath('NFE', date('c'), (string)$company['cnpj'], (string)($company['default_download_dir'] ?? ''));
                    $writeOk = $storage->canWriteDirectory($preview);
                    flash_set($health['ok'] ? 'success' : 'warning', $company['company_name'] . ': ' . $health['message'] . ' Pasta: ' . $preview . ' | Gravável: ' . ($writeOk ? 'Sim' : 'Não'));
                }
                break;

            case 'import':
                if (!empty($_FILES['xml_files']['name'][0])) {
                    $count = 0;
                    $duplicates = 0;
                    $errors = 0;
                    foreach ($_FILES['xml_files']['tmp_name'] as $idx => $tmp) {
                        if ($_FILES['xml_files']['error'][$idx] !== UPLOAD_ERR_OK) {
                            $errors++;
                            continue;
                        }
                        try {
                            $xml = (string)file_get_contents($tmp);
                            $digest = hash('sha256', $xml);
                            if ($repo->findDocumentByDigest($digest)) {
                                $duplicates++;
                                continue;
                            }
                            $parsed = $parser->parse($xml);

                            $company = null;
                            $docCnpj = preg_replace('/\D+/', '', (string)($parsed['recipient_cnpj'] ?? $parsed['issuer_cnpj'] ?? ''));
                            if ($docCnpj !== '') {
                                $company = $repo->findCompanyByCnpj($docCnpj);
                            }
                            if (!$company && !empty($_POST['company_id'])) {
                                $company = $repo->findCompany((int)$_POST['company_id']);
                            }

                            $existingComplete = null;
                            if (!empty($parsed['access_key'])) {
                                $existingComplete = $repo->findDocumentByAccessKey((string)$parsed['doc_type'], (string)$parsed['access_key'], (int)($company['id'] ?? 0) ?: null);
                            }
                            if ($existingComplete && ($existingComplete['status'] ?? '') === 'xml_completo' && (!empty($existingComplete['raw_xml']) || !empty($existingComplete['xml_path']))) {
                                $duplicates++;
                                continue;
                            }

                            $saved = $storage->saveXml(
                                $parsed['doc_type'],
                                (string)$parsed['issue_date'],
                                $xml,
                                ($_FILES['xml_files']['name'][$idx] ?? null),
                                (string)($company['cnpj'] ?? ''),
                                (string)($company['default_download_dir'] ?? '')
                            );
                            $repo->saveDocument($parsed + $saved + [
                                'company_id' => (int)($company['id'] ?? 0) ?: null,
                                'company_name' => $company['company_name'] ?? null,
                                'company_cnpj' => $company['cnpj'] ?? null,
                                'imported_at' => date('c'),
                                'updated_at' => date('c'),
                            ]);
                            $count++;
                        } catch (Throwable $e) {
                            $errors++;
                            $repo->logAction('xml_import_error', 'Falha ao importar ' . ($_FILES['xml_files']['name'][$idx] ?? 'XML') . ': ' . $e->getMessage());
                        }
                    }
                    $repo->logAction('xml_import', 'Importação manual: ' . $count . ' XMLs gravados, ' . $duplicates . ' duplicados ignorados, ' . $errors . ' erro(s).');
                    $type = $errors > 0 ? 'warning' : 'success';
                    flash_set($type, $count . ' XML(s) importado(s), ' . $duplicates . ' duplicado(s) ignorado(s), ' . $errors . ' erro(s).');
                } else {
                    flash_set('warning', 'Selecione ao menos um XML.');
                }
                break;

            case 'documents':
                $ids = array_map('intval', $_POST['ids'] ?? []);
                $documentFilterKeys = [
                    'company_id',
                    'doc_type',
                    'status',
                    'manifestation_status',
                    'order_presence',
                    'date_start',
                    'date_end',
                    'company_q',
                    'number_q',
                    'order_number_q',
                    'issuer_q',
                    'recipient_q',
                    'access_key_q',
                    'referenced_nfe_q',
                    'referenced_number_q',
                    'product_q',
                    'cfop_q',
                    'cte_taker_only',
                    'ignore_cfops',
                    'source_q',
                    'q',
                    'sort_by',
                    'sort_dir',
                ];
                $postFilters = [];
                foreach ($documentFilterKeys as $filterKey) {
                    $postFilters[$filterKey] = $_POST[$filterKey] ?? '';
                }
                $returnQuery = array_filter($postFilters, static fn($value) => $value !== '' && $value !== null);
                $returnQuery['page'] = 'documents';
                $exportDir = trim((string)($_POST['export_dir'] ?? ''));

                if (isset($_POST['save_ignored_cfop'])) {
                    $repo->saveDocumentIgnoredCfop((string)($_POST['ignored_cfop'] ?? ''), (string)($_POST['ignored_reason'] ?? ''), $auth->user());
                    flash_set('success', 'CFOP adicionado a lista de ignorados.');
                } elseif (isset($_POST['delete_ignored_cfop'])) {
                    $repo->deleteDocumentIgnoredCfop((int)($_POST['ignored_cfop_id'] ?? 0));
                    flash_set('success', 'CFOP removido da lista de ignorados.');
                } elseif (isset($_POST['bulk_manifest'])) {
                    if (!$ids) {
                        flash_set('warning', 'Selecione ao menos um documento.');
                        redirect_to(base_url('?' . http_build_query($returnQuery)));
                    }
                    $type = (string)($_POST['manifest_type'] ?? 'science');
                    $justification = trim((string)($_POST['manifest_justification'] ?? ''));
                    $count = $manifestation->manifest($ids, $type, $justification ?: null);
                    flash_set('success', $count . ' documento(s) manifestado(s).');
                } elseif (isset($_POST['bulk_check_cancelled'])) {
                    $checkFilters = $postFilters;
                    $checkFilters['posted_to_erp'] = '0';
                    $checkFilters['status'] = '';
                    $docs = array_values(array_filter($repo->documents($checkFilters), static function (array $doc): bool {
                        $type = strtoupper((string)($doc['doc_type'] ?? ''));
                        $status = (string)($doc['status'] ?? '');
                        $key = preg_replace('/\D+/', '', (string)($doc['access_key'] ?? ''));
                        return in_array($type, ['NFE', 'NFCE'], true)
                            && strlen($key) === 44
                            && !in_array($status, ['cancelado', 'denegado'], true);
                    }));
                    $limit = 100;
                    $candidateCount = count($docs);
                    $docs = array_slice($docs, 0, $limit);
                    if (!$docs) {
                        flash_set('warning', 'Nenhuma NF-e/NFC-e não lançada no ERP disponível para verificar com os filtros atuais.');
                        redirect_to(base_url('?' . http_build_query($returnQuery)));
                    }

                    $byCompany = [];
                    foreach ($docs as $doc) {
                        $byCompany[(int)$doc['company_id']][] = $doc;
                    }

                    $checked = 0;
                    $cancelled = 0;
                    $updated = 0;
                    $errors = 0;
                    $logs = [];
                    $jobId = $repo->createJob('nfe_cancel_check', null, 'Verificação em massa de cancelamento');

                    foreach ($byCompany as $companyId => $companyDocs) {
                        $company = $repo->findCompany((int)$companyId);
                        if (!$company) {
                            $errors += count($companyDocs);
                            $logs[] = 'Empresa #' . $companyId . ': cadastro não encontrado.';
                            continue;
                        }
                        $connector = $collectors['nfe'];
                        $connector->setCompanyContext($company);
                        foreach ($companyDocs as $doc) {
                            $key = preg_replace('/\D+/', '', (string)$doc['access_key']);
                            try {
                                $beforeStatus = (string)($doc['status'] ?? '');
                                $result = $connector->collectByAccessKey($key);
                                $statusResult = method_exists($connector, 'queryProtocolStatus') ? $connector->queryProtocolStatus($key) : ['updated' => 0, 'message' => 'Consulta de situação indisponível.'];
                                $checked++;
                                $updated += (int)($statusResult['updated'] ?? 0) + (int)$result['updated'];
                                $after = $repo->findDocumentByAccessKey('NFE', $key, (int)$companyId)
                                    ?: $repo->findDocumentByAccessKey('NFCE', $key, (int)$companyId);
                                if ($after && $beforeStatus !== 'cancelado' && (string)($after['status'] ?? '') === 'cancelado') {
                                    $cancelled++;
                                }
                            } catch (Throwable $e) {
                                $errors++;
                                $logs[] = 'Chave ' . $key . ': ' . $e->getMessage();
                            }
                        }
                    }

                    $repo->finishJob($jobId, $errors > 0 ? 'warning' : 'success', 0, $updated, $errors, implode(PHP_EOL, $logs));
                    $repo->logAction('nfe_cancel_check', 'Verificação em massa de cancelamento: ' . $checked . ' chave(s), ' . $cancelled . ' cancelada(s), ' . $errors . ' erro(s).');
                    $message = 'Verificação concluída: ' . $checked . ' chave(s) consultada(s), ' . $cancelled . ' marcada(s) como cancelada(s).';
                    if ($candidateCount > $limit) {
                        $message .= ' Limite de ' . $limit . ' por execução; rode novamente para continuar.';
                    }
                    flash_set($errors > 0 ? 'warning' : 'success', $message);
                } elseif (isset($_POST['bulk_export'])) {
                    if (!$ids) {
                        flash_set('warning', 'Selecione ao menos um documento.');
                        redirect_to(base_url('?' . http_build_query($returnQuery)));
                    }
                    $docs = array_filter(array_map(fn(int $id) => $repo->findDocument($id), $ids));
                    $zip = $storage->exportZip($docs, $exportDir ?: null);
                    if ($zip) {
                        flash_set('success', count($docs) . ' documento(s) exportado(s). ZIP gerado em: ' . $zip);
                    } else {
                        flash_set('warning', 'Nenhum XML disponível nos documentos selecionados.');
                    }
                } elseif (isset($_POST['bulk_export_filtered'])) {
                    $docs = $repo->documents($postFilters);
                    $zip = $storage->exportZip($docs, $exportDir ?: null);
                    if ($zip) {
                        flash_set('success', count($docs) . ' documento(s) filtrado(s) avaliados. ZIP gerado em: ' . $zip);
                    } else {
                        flash_set('warning', 'Nenhum XML disponível nos filtros atuais.');
                    }
                } elseif (isset($_POST['bulk_copy_filtered'])) {
                    if ($exportDir === '') {
                        flash_set('warning', 'Informe a pasta de destino no servidor para copiar os XMLs filtrados.');
                        redirect_to(base_url('?' . http_build_query($returnQuery)));
                    }
                    $docs = $repo->documents($postFilters);
                    $copied = $storage->copyDocumentsToFolder($docs, $exportDir);
                    flash_set($copied > 0 ? 'success' : 'warning', $copied . ' XML(s) copiado(s) para: ' . $exportDir);
                } elseif (isset($_POST['bulk_copy_selected'])) {
                    if (!$ids) {
                        flash_set('warning', 'Selecione ao menos um documento.');
                        redirect_to(base_url('?' . http_build_query($returnQuery)));
                    }
                    if ($exportDir === '') {
                        flash_set('warning', 'Informe a pasta de destino no servidor para copiar os XMLs selecionados.');
                        redirect_to(base_url('?' . http_build_query($returnQuery)));
                    }
                    $docs = array_filter(array_map(fn(int $id) => $repo->findDocument($id), $ids));
                    $copied = $storage->copyDocumentsToFolder($docs, $exportDir);
                    flash_set($copied > 0 ? 'success' : 'warning', $copied . ' XML(s) copiado(s) para: ' . $exportDir);
                } elseif (isset($_POST['bulk_mark_downloaded'])) {
                    if (!$ids) {
                        flash_set('warning', 'Selecione ao menos um documento.');
                        redirect_to(base_url('?' . http_build_query($returnQuery)));
                    }
                    $count = $repo->updateDocumentStatuses($ids, 'downloaded_in_portal', 'xml_completo');
                    flash_set('success', $count . ' documento(s) marcados como baixados.');
                }
                redirect_to(base_url('?' . http_build_query($returnQuery)));
                break;

            case 'period_closure':
                if (isset($_POST['run_period_closure'])) {
                    $companyIds = array_map('intval', $_POST['company_ids'] ?? []);
                    if (in_array(0, $companyIds, true)) {
                        $companyIds = [];
                    }
                    $result = $periodClosure->run([
                        'company_ids' => $companyIds,
                        'doc_types' => $_POST['doc_types'] ?? [],
                        'period_start' => (string)($_POST['period_start'] ?? ''),
                        'period_end' => (string)($_POST['period_end'] ?? ''),
                        'only_missing_complete' => !empty($_POST['only_missing_complete']),
                        'try_manifestation' => !empty($_POST['try_manifestation']),
                        'reprocess_after_manifestation' => !empty($_POST['reprocess_after_manifestation']),
                        'generate_export' => !empty($_POST['generate_export']),
                        'save_period_folder' => !empty($_POST['save_period_folder']),
                        'manifest_type' => (string)($_POST['manifest_type'] ?? 'science'),
                        'manifest_justification' => trim((string)($_POST['manifest_justification'] ?? '')),
                    ]);
                    flash_set('success', 'Fechamento #' . $result['closure_id'] . ' executado. ' . implode(' | ', array_slice($result['messages'], -3)));
                    redirect_to(base_url('?page=period_closure&id=' . $result['closure_id']));
                }
                if (isset($_POST['manifest_period_selected'])) {
                    $ids = array_map('intval', $_POST['ids'] ?? []);
                    if (!$ids) {
                        throw new RuntimeException('Selecione ao menos um documento do fechamento.');
                    }
                    $type = (string)($_POST['manifest_type'] ?? 'science');
                    $justification = trim((string)($_POST['manifest_justification'] ?? ''));
                    $count = $manifestation->manifest($ids, $type, $justification ?: null);
                    flash_set('success', $count . ' documento(s) manifestado(s). Use reprocessar pendentes após o prazo operacional da SEFAZ.');
                }
                if (isset($_POST['reprocess_period_pending'])) {
                    $closureId = (int)($_POST['closure_id'] ?? 0);
                    $result = $periodClosure->reprocessPending($closureId);
                    flash_set('success', 'Reprocesso concluído: ' . implode(' | ', $result['messages']));
                    redirect_to(base_url('?page=period_closure&id=' . $closureId));
                }
                break;

            case 'jobs':
                if (isset($_POST['run_job'])) {
                    $jobType = (string)($_POST['job_type'] ?? 'collect_all');
                    $companyId = (int)($_POST['company_id'] ?? 0);
                    $result = $jobRunner->run($jobType, $companyId);
                    flash_set('success', 'Job executado: ' . implode(' | ', $result['logs']));
                    redirect_to(base_url('?page=jobs&company_id=' . $companyId . '&job_type=' . urlencode($jobType)));
                }
                break;

            case 'nfe_keys':
                if (isset($_POST['lookup_nfe_keys'])) {
                    $companyId = (int)($_POST['company_id'] ?? 0);
                    $company = $repo->findCompany($companyId);
                    if (!$company) {
                        throw new RuntimeException('Selecione uma empresa válida.');
                    }
                    $rawKeys = (string)($_POST['access_keys'] ?? '');
                    preg_match_all('/\d{44}/', $rawKeys, $matches);
                    $keys = array_values(array_unique($matches[0] ?? []));
                    if (!$keys) {
                        throw new RuntimeException('Informe ao menos uma chave NF-e com 44 dígitos.');
                    }
                    if (count($keys) > 200) {
                        throw new RuntimeException('Limite de 200 chaves por execução para evitar consumo indevido.');
                    }

                    $connector = $collectors['nfe'];
                    $connector->setCompanyContext($company);
                    $jobId = $repo->createJob('nfe_key_lookup', $companyId, (string)$company['company_name']);
                    $created = 0;
                    $updated = 0;
                    $errors = 0;
                    $manifested = 0;
                    $logs = [];

                    foreach ($keys as $key) {
                        try {
                            $result = $connector->collectByAccessKey($key);
                            $created += (int)$result['created'];
                            $updated += (int)$result['updated'];
                            $errors += (int)$result['errors'];
                            $logs[] = (string)$result['message'];

                            $doc = $repo->findDocumentByAccessKey('NFE', $key, $companyId);
                            if (!empty($_POST['manifest_science']) && $doc && in_array(($doc['status'] ?? ''), ['apenas_resumo', 'pendente_manifestacao'], true)) {
                                $manifested += $manifestation->manifest([(int)$doc['id']], 'science');
                            }
                            if (!empty($_POST['retry_after_science']) && $doc && ($doc['status'] ?? '') === 'aguardando_novo_download') {
                                $retry = $connector->collectByAccessKey($key);
                                $created += (int)$retry['created'];
                                $updated += (int)$retry['updated'];
                                $errors += (int)$retry['errors'];
                                $logs[] = 'Reprocesso ' . (string)$retry['message'];
                            }
                        } catch (Throwable $e) {
                            $errors++;
                            $logs[] = 'Chave ' . $key . ': ' . $e->getMessage();
                        }
                    }

                    if ($manifested > 0) {
                        $logs[] = 'Ciência da operação enviada para ' . $manifested . ' NF-e(s).';
                    }
                    $status = $errors > 0 ? 'warning' : 'success';
                    $repo->finishJob($jobId, $status, $created, $updated, $errors, implode(PHP_EOL, $logs));
                    $repo->logAction('nfe_key_lookup', 'Busca por chave: ' . count($keys) . ' chave(s), ' . $created . ' criada(s), ' . $updated . ' atualizada(s), ' . $manifested . ' ciência(s), ' . $errors . ' erro(s).', $companyId);
                    flash_set($status, 'Busca por chave concluída: ' . count($keys) . ' chave(s), ' . $created . ' criada(s), ' . $updated . ' atualizada(s), ' . $manifested . ' ciência(s), ' . $errors . ' erro(s).');
                    redirect_to(base_url('?page=nfe_keys&company_id=' . $companyId));
                }
                break;

            case 'nfe_rescan':
                if (isset($_POST['reset_nfe_nsu'])) {
                    $companyId = (int)($_POST['company_id'] ?? 0);
                    $company = $repo->findCompany($companyId);
                    if (!$company) {
                        throw new RuntimeException('Selecione uma empresa válida.');
                    }
                    $confirm = trim((string)($_POST['confirm_text'] ?? ''));
                    if ($confirm !== 'REINICIAR NFE') {
                        throw new RuntimeException('Confirmação inválida. Digite REINICIAR NFE para liberar a revarredura.');
                    }
                    $prefix = 'nfe_' . $companyId . '_';
                    $cooldownUntil = (string)$repo->getSetting($prefix . 'cooldown_until', '');
                    if ($cooldownUntil !== '' && strtotime($cooldownUntil) !== false && strtotime($cooldownUntil) > time()) {
                        throw new RuntimeException('NF-e bloqueada localmente por consumo indevido. Tente após ' . date('d/m/Y H:i', (int)strtotime($cooldownUntil)) . '.');
                    }
                    $oldUlt = (string)$repo->getSetting($prefix . 'ult_nsu', '0');
                    $oldMax = (string)$repo->getSetting($prefix . 'max_nsu', '0');
                    $root = substr(preg_replace('/\\D+/', '', (string)$company['cnpj']), 0, 8);
                    $rootPrefix = 'nfe_root_' . ($root !== '' ? $root : 'sem_cnpj') . '_';
                    $repo->setSetting($prefix . 'ult_nsu', '0');
                    $repo->setSetting($prefix . 'max_nsu', '0');
                    $repo->setSetting($prefix . 'cooldown_until', '');
                    $repo->setSetting($prefix . 'last_check_at', '');
                    $repo->setSetting($rootPrefix . 'ult_nsu', '0');
                    $repo->setSetting($rootPrefix . 'max_nsu', '0');
                    $repo->setSetting($rootPrefix . 'last_check_at', '');
                    $repo->logAction('nfe_rescan_reset', 'Revarredura NF-e liberada para ' . $company['company_name'] . '. ultNSU anterior=' . $oldUlt . ', maxNSU anterior=' . $oldMax . '. XMLs completos preservados.', $companyId);
                    flash_set('success', 'Cursor NF-e reiniciado com segurança para ' . $company['company_name'] . '. Execute o Robô NF-e em Radar de XML.');
                    redirect_to(base_url('?page=nfe_rescan&company_id=' . $companyId));
                }
                break;
        }
    } catch (Throwable $e) {
        flash_set('danger', $e->getMessage());
    }

    redirect_to(base_url('?page=' . $page));
}

if ($page === 'view_xml') {
    $doc = $repo->findDocument((int)($_GET['id'] ?? 0));
    if (!$doc) {
        http_response_code(404);
        exit('Documento não encontrado.');
    }
    header('Content-Type: application/xml; charset=utf-8');
    echo $doc['raw_xml'] ?: (is_file((string)$doc['xml_path']) ? file_get_contents((string)$doc['xml_path']) : '<xml/>');
    exit;
}

if ($page === 'document_items') {
    $doc = $repo->findDocument((int)($_GET['id'] ?? 0));
    if (!$doc) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Documento nao encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'document' => [
            'id' => (int)$doc['id'],
            'doc_type' => (string)($doc['doc_type'] ?? ''),
            'number' => (string)($doc['number'] ?? ''),
            'issuer_name' => (string)($doc['issuer_name'] ?? ''),
            'issue_date' => format_date($doc['issue_date'] ?? null),
            'total_value' => format_money((float)($doc['total_value'] ?? 0)),
        ],
        'items' => array_map(static fn(array $item): array => [
            'item_number' => (int)($item['item_number'] ?? 0),
            'product_code' => (string)($item['product_code'] ?? ''),
            'product_name' => (string)($item['product_name'] ?? ''),
            'ncm' => (string)($item['ncm'] ?? ''),
            'cfop' => (string)($item['cfop'] ?? ''),
            'quantity' => (float)($item['quantity'] ?? 0),
            'unit' => (string)($item['unit'] ?? ''),
            'unit_amount' => format_money((float)($item['unit_amount'] ?? 0)),
            'total_amount' => format_money((float)($item['total_amount'] ?? 0)),
        ], $repo->documentItems((int)$doc['id'])),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($page === 'download_export') {
    $closure = $repo->findPeriodClosure((int)($_GET['id'] ?? 0));
    $type = (string)($_GET['type'] ?? '');
    $path = $type === 'csv' ? ($closure['export_csv_path'] ?? '') : ($closure['export_zip_path'] ?? '');
    if (!$closure || !$path || !is_file((string)$path)) {
        http_response_code(404);
        exit('Exportação não encontrada.');
    }
    header('Content-Type: ' . ($type === 'csv' ? 'text/csv; charset=utf-8' : 'application/zip'));
    header('Content-Disposition: attachment; filename="' . basename((string)$path) . '"');
    readfile((string)$path);
    exit;
}

if ($page === 'documents_xml_payload') {
    $scope = (string)($_GET['scope'] ?? 'filtered');
    if ($scope === 'selected') {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? '')))));
        $docs = array_filter(array_map(fn(int $id) => $repo->findDocument($id), $ids));
    } else {
        $filters = document_filters_from_request($_GET);
        $docs = $repo->documents($filters);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(documents_xml_payload($docs), JSON_UNESCAPED_UNICODE);
    exit;
}


if ($page === 'documents_xml_zip') {
    try {
        $scope = (string)($_GET['scope'] ?? 'filtered');
        if ($scope === 'selected') {
            $ids = array_values(array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? '')))));
            $docs = array_values(array_filter(array_map(fn(int $id) => $repo->findDocument($id), $ids)));
            if (count($docs) === 1) {
                $xml = documents_xml_content($docs[0]);
                if (trim($xml) === '') {
                    header('Content-Type: text/plain; charset=utf-8');
                    exit('XML nao disponivel para o documento selecionado.');
                }
                $filename = documents_download_filename($docs[0], 'xml');
                header('Content-Type: application/xml; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                echo $xml;
                exit;
            }
        } else {
            $docs = $repo->documents(document_filters_from_request($_GET));
        }
        $files = [];
        foreach ($docs as $doc) {
            $xml = documents_xml_content($doc);
            if (trim($xml) !== '') {
                $files[] = ['name' => documents_download_filename($doc, 'xml'), 'content' => $xml];
            }
        }
        documents_zip_response($files, 'xmls_entradas_' . date('Ymd_His') . '.zip', 'Nenhum XML disponivel para exportacao nos filtros informados.');
    } catch (Throwable $e) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Nao foi possivel gerar a exportacao de XML: ' . $e->getMessage();
        exit;
    }
}
if ($page === 'documents_danfe') {
    $doc = $repo->findDocument((int)($_GET['id'] ?? 0));
    if (!$doc || !in_array(strtoupper((string)($doc['doc_type'] ?? '')), ['NFE', 'CTE'], true)) {
        http_response_code(404);
        exit('Documento NF-e/CT-e nao encontrado.');
    }
    if ((string)($doc['status'] ?? '') === 'apenas_resumo') {
        http_response_code(409);
        exit('Espelho DANFE/DACTE indisponivel para documento apenas resumo. Baixe o XML completo antes de imprimir.');
    }
    header('Content-Type: text/html; charset=utf-8');
    echo documents_danfe_html($doc, true);
    exit;
}


if ($page === 'documents_danfe_zip') {
    try {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ini_set('memory_limit', '1024M');
        $scope = (string)($_GET['scope'] ?? 'filtered');
        if ($scope === 'selected') {
            $ids = array_values(array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? '')))));
            $docs = array_values(array_filter(array_map(fn(int $id) => $repo->findDocument($id), $ids)));
        } else {
            $docs = $repo->documents(document_filters_from_request($_GET));
        }
        $docs = array_values(array_filter($docs, static fn($doc) => in_array(strtoupper((string)($doc['doc_type'] ?? '')), ['NFE', 'CTE'], true) && (string)($doc['status'] ?? '') !== 'apenas_resumo'));
        if (!$docs) {
            header('Content-Type: text/plain; charset=utf-8');
            exit('Nenhum DANFE/DACTE disponivel para os filtros informados.');
        }
        if ($scope === 'selected' && count($docs) === 1) {
            header('Content-Type: text/html; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . documents_danfe_filename($docs[0]) . '"');
            echo documents_danfe_html($docs[0], false);
            exit;
        }
        $zipPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'danfes_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            header('Content-Type: text/plain; charset=utf-8');
            exit('Nao foi possivel criar o ZIP de DANFE/DACTE. Verifique permissao na pasta temporaria do servidor.');
        }
        $used = [];
        foreach ($docs as $doc) {
            $name = documents_danfe_filename($doc);
            $entry = $name;
            $suffix = 2;
            while (isset($used[$entry])) {
                $entry = preg_replace('/(\.[^.]+)$/', '_' . $suffix . '$1', $name);
                if ($entry === $name) {
                    $entry = $name . '_' . $suffix;
                }
                $suffix++;
            }
            $used[$entry] = true;
            $zip->addFromString($entry, documents_danfe_html($doc, false));
        }
        $zip->close();
        if (!is_file($zipPath) || filesize($zipPath) <= 0) {
            @unlink($zipPath);
            header('Content-Type: text/plain; charset=utf-8');
            exit('Nenhum DANFE/DACTE foi gerado para os filtros informados.');
        }
        $filename = 'danfes_entradas_' . date('Ymd_His') . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    } catch (Throwable $e) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Nao foi possivel gerar a exportacao de DANFE/DACTE: ' . $e->getMessage();
        exit;
    }
}
if ($page === 'documents_export') {
    $filters = document_filters_from_request($_GET);
    $docs = $repo->documents($filters);
    $filename = 'entradas_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    echo '<table border="1">';
    echo '<tr><th>Empresa</th><th>CNPJ</th><th>Tipo</th><th>N&uacute;mero</th><th>Pedido</th><th>Emissor</th><th>CNPJ emissor</th><th>Destinat&aacute;rio</th><th>Documento destinat&aacute;rio</th><th>Chave</th><th>NF-e vinculada</th><th>N&uacute;mero doc. referenciado</th><th>Nota lan&ccedil;ada no ERP</th><th>Eventos informativos</th><th>Emiss&atilde;o</th><th>Valor</th><th>Status</th><th>Manifesta&ccedil;&atilde;o</th><th>Origem</th><th>Pasta</th></tr>';
    foreach ($docs as $doc) {
        echo '<tr>';
        foreach ([
            $doc['company_name'] ?? '',
            $doc['company_cnpj'] ?? '',
            $doc['doc_type'] ?? '',
            $doc['number'] ?? '',
            $doc['order_number'] ?? '',
            $doc['issuer_name'] ?? '',
            $doc['issuer_cnpj'] ?? '',
            $doc['recipient_name'] ?? '',
            $doc['recipient_cnpj'] ?? '',
            $doc['access_key'] ?? '',
            $doc['referenced_nfe_keys'] ?? '',
            $doc['referenced_document_numbers'] ?? '',
            !empty($doc['posted_to_erp']) ? 'Sim' : 'Nao',
            ((int)($doc['informative_events_count'] ?? 0) > 0 ? ((string)$doc['informative_events_count'] . ' - ' . (string)($doc['informative_events_names'] ?? '')) : ''),
            format_date($doc['issue_date'] ?? null),
            number_format((float)($doc['total_value'] ?? 0), 2, ',', '.'),
            document_status_label((string)($doc['status'] ?? '')),
            manifestation_status_label((string)($doc['manifestation_status'] ?? '')),
            $doc['source'] ?? '',
            $doc['storage_dir'] ?? '',
        ] as $value) {
            echo '<td>' . h((string)$value) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    exit;
}

if ($page === 'revenue_export') {
    $filters = revenue_filters_from_request($_GET);
    $grid = (string)($_GET['grid'] ?? 'documents');
    $canViewCost = $auth->canViewCost();
    if ($grid === 'items') {
        $items = $repo->revenueItems($filters, null, 100000);
        $filename = 'itens_faturamento_' . date('Ymd_His') . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF";
        echo '<table border="1">';
        echo '<tr><th>Produto</th><th>Codigo interno</th><th>Codigo ERP</th><th>Grupo</th><th>Quantidade</th><th>Unidade</th><th>Valor unitario</th><th>Total</th>' . ($canViewCost ? '<th>Custo</th>' : '') . '<th>Desconto</th><th>CFOP</th><th>NCM</th><th>CST/CSOSN</th><th>Tributos</th><th>Creditos</th><th>Loja emissao</th><th>Loja pedido</th><th>Cliente</th><th>Vendedor</th><th>Pedido</th><th>Documento</th><th>ICMS</th><th>PIS</th><th>COFINS</th><th>IPI</th><th>ISS</th><th>ST</th><th>IBS</th><th>CBS</th><th>DIFAL</th><th>Outros impostos</th></tr>';
        foreach ($items as $item) {
            echo '<tr>';
            $values = [
                $item['product_name'] ?? '',
                $item['internal_code'] ?? '',
                $item['erp_code'] ?? '',
                $item['product_group'] ?? '',
                $item['quantity'] ?? '',
                $item['unit'] ?? '',
                number_format((float)($item['unit_amount'] ?? 0), 4, ',', '.'),
                number_format((float)($item['total_amount'] ?? 0), 2, ',', '.'),
            ];
            if ($canViewCost) {
                $values[] = number_format((float)($item['cost_amount'] ?? 0), 2, ',', '.');
            }
            $values = array_merge($values, [
                number_format((float)($item['discount_amount'] ?? 0), 2, ',', '.'),
                $item['cfop'] ?? '',
                $item['ncm'] ?? '',
                $item['cst_csosn'] ?? '',
                number_format((float)($item['taxes_amount'] ?? 0), 2, ',', '.'),
                number_format((float)($item['tax_credits_amount'] ?? 0), 2, ',', '.'),
                $item['issuing_store_name'] ?? '',
                $item['order_store_name'] ?? '',
                $item['customer_name'] ?? '',
                $item['seller_name'] ?? '',
                $item['order_number'] ?? '',
                trim(($item['document_type'] ?? '') . ' ' . ($item['series'] ?? '') . '/' . ($item['number'] ?? '')),
                number_format((float)($item['icms_amount'] ?? 0), 2, ',', '.'),
                number_format((float)($item['pis_amount'] ?? 0), 2, ',', '.'),
                number_format((float)($item['cofins_amount'] ?? 0), 2, ',', '.'),
                number_format((float)($item['ipi_amount'] ?? 0), 2, ',', '.'),
                number_format((float)($item['iss_amount'] ?? 0), 2, ',', '.'),
                number_format((float)($item['st_amount'] ?? 0), 2, ',', '.'),
                number_format((float)($item['ibs_amount'] ?? 0), 2, ',', '.'),
                number_format((float)($item['cbs_amount'] ?? 0), 2, ',', '.'),
                number_format((float)($item['difal_amount'] ?? 0), 2, ',', '.'),
                number_format((float)($item['other_taxes_amount'] ?? 0), 2, ',', '.'),
            ]);
            foreach ($values as $value) {
                echo '<td>' . h((string)$value) . '</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
        exit;
    }
    $docs = $repo->revenueDocuments($filters);
    $filename = 'conferencia_faturamento_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    echo '<table border="1">';
    echo '<tr><th>Data de emissão</th><th>Autorização</th><th>Tipo</th><th>Finalidade</th><th>Situação</th><th>Série</th><th>Número</th><th>Pedido</th><th>Chave</th><th>Loja de emissão</th><th>CNPJ emissão</th><th>Loja do pedido</th><th>CNPJ loja pedido</th><th>Cliente</th><th>CPF/CNPJ cliente</th><th>Vendedor</th><th>Valor total</th><th>Produtos</th><th>Serviços</th><th>Frete</th><th>Desconto</th><th>Devolução</th><th>Impostos</th><th>Créditos</th><th>Líquido</th><th>ICMS</th><th>PIS</th><th>COFINS</th><th>IPI</th><th>ISS</th><th>ST</th><th>IBS</th><th>CBS</th><th>DIFAL</th><th>Outros impostos</th><th>Origem</th><th>XML disponível</th></tr>';
    foreach ($docs as $doc) {
        echo '<tr>';
        foreach ([
            format_date_short($doc['issue_date'] ?? null),
            format_date($doc['authorization_datetime'] ?? null),
            $doc['document_type'] ?? '',
            $doc['purpose'] ?? '',
            $doc['document_status'] ?? '',
            $doc['series'] ?? '',
            $doc['number'] ?? '',
            $doc['order_number'] ?? '',
            $doc['access_key'] ?? '',
            $doc['issuing_store_name'] ?? '',
            $doc['issuing_store_cnpj'] ?? '',
            $doc['order_store_name'] ?? '',
            $doc['order_store_cnpj'] ?? '',
            $doc['customer_name'] ?? '',
            $doc['customer_document'] ?? '',
            $doc['seller_name'] ?? '',
            number_format((float)($doc['gross_amount'] ?? 0), 2, ',', '.'),
            number_format((float)($doc['products_amount'] ?? 0), 2, ',', '.'),
            number_format((float)($doc['services_amount'] ?? 0), 2, ',', '.'),
            number_format((float)($doc['freight_amount'] ?? 0), 2, ',', '.'),
            number_format((float)($doc['discount_amount'] ?? 0), 2, ',', '.'),
            number_format((float)($doc['return_amount'] ?? 0), 2, ',', '.'),
            number_format((float)($doc['taxes_amount'] ?? 0), 2, ',', '.'),
            number_format((float)($doc['tax_credits_amount'] ?? 0), 2, ',', '.'),
            number_format((float)($doc['net_amount'] ?? 0), 2, ',', '.'),
            number_format((float)($doc['icms_amount'] ?? 0), 2, ',', '.'),
            number_format((float)($doc['pis_amount'] ?? 0), 2, ',', '.'),
            number_format((float)($doc['cofins_amount'] ?? 0), 2, ',', '.'),
            number_format((float)($doc['ipi_amount'] ?? 0), 2, ',', '.'),
            number_format((float)($doc['iss_amount'] ?? 0), 2, ',', '.'),
            number_format((float)($doc['st_amount'] ?? 0), 2, ',', '.'),
            number_format((float)($doc['ibs_amount'] ?? 0), 2, ',', '.'),
            number_format((float)($doc['cbs_amount'] ?? 0), 2, ',', '.'),
            number_format((float)($doc['difal_amount'] ?? 0), 2, ',', '.'),
            number_format((float)($doc['other_taxes_amount'] ?? 0), 2, ',', '.'),
            $doc['integration_source'] ?? '',
            !empty($doc['xml_content']) ? 'Sim' : 'Não',
        ] as $value) {
            echo '<td>' . h((string)$value) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    exit;
}

if ($page === 'revenue_xml') {
    $doc = $repo->findRevenueDocument((int)($_GET['id'] ?? 0));
    if (!$doc || empty($doc['xml_content'])) {
        http_response_code(404);
        exit('XML de faturamento não encontrado.');
    }
    header('Content-Type: application/xml; charset=utf-8');
    echo (string)$doc['xml_content'];
    exit;
}

$companies = $repo->companies();
$companyCertificates = [];
$companyHealth = [];
foreach ($companies as $co) {
    $companyCertificates[$co['id']] = $certificates->active((int)$co['id']);
    $companyHealth[$co['id']] = [
        'certificate' => $certificates->healthCheck((int)$co['id']),
        'documents_count' => $repo->countDocumentsByCompany((int)$co['id']),
        'path_preview' => $storage->previewXmlPath('NFE', date('c'), (string)$co['cnpj'], (string)($co['default_download_dir'] ?? '')),
        'delete_blockers' => $repo->companyDeleteBlockers((int)$co['id']),
    ];
}
$automationRewinds = [];
foreach ($companies as $co) {
    $companyIdForRewind = (int)$co['id'];
    $automationRewinds[$companyIdForRewind] = [
        'cte' => $repo->getSetting('auto_cte_rewind_nsu_once_company_' . $companyIdForRewind, '0'),
        'nfe' => $repo->getSetting('auto_nfe_rewind_nsu_once_company_' . $companyIdForRewind, '0'),
        'cte_ult_nsu' => $repo->getSetting('cte_' . $companyIdForRewind . '_ult_nsu', '0'),
        'cte_max_nsu' => $repo->getSetting('cte_' . $companyIdForRewind . '_max_nsu', '0'),
        'nfe_ult_nsu' => $repo->getSetting('nfe_' . $companyIdForRewind . '_ult_nsu', '0'),
        'nfe_max_nsu' => $repo->getSetting('nfe_' . $companyIdForRewind . '_max_nsu', '0'),
    ];
}
$viewData = [
    'page' => $page,
    'title' => $config['app_name'],
    'flash' => flash_get(),
    'certificate' => $certificates->active(),
    'companies' => $companies,
    'companyCertificates' => $companyCertificates,
    'companyHealth' => $companyHealth,
    'automationRewinds' => $automationRewinds,
    'currentUser' => $auth->user(),
    'isAdmin' => $auth->isAdmin(),
    'settings' => [
        'default_download_dir' => $repo->getSetting('default_download_dir', $config['default_download_dir']),
        'storage_path_mode' => $repo->getSetting('storage_path_mode', 'segmented'),
        'storage_path_template' => $repo->getSetting('storage_path_template', '{base}/{cnpj}/{doc_type}/{year}/{month}'),
        'company_cnpj' => $repo->getSetting('company_cnpj', 'multiempresa'),
        'company_name' => $repo->getSetting('company_name', 'Control S Consultoria'),
        'client_display_name' => $repo->getSetting('client_display_name', 'Cliente integrado'),
        'client_label' => $repo->getSetting('client_label', 'Ambiente fiscal'),
        'client_logo_path' => $repo->getSetting('client_logo_path', 'assets/logo-s-novo.jpg'),
        'sefaz_environment' => $repo->getSetting('sefaz_environment', (string)$config['sefaz_environment']),
        'sefaz_uf_author' => $repo->getSetting('sefaz_uf_author', (string)$config['sefaz_uf_author']),
        'nfe_distribution_url' => $repo->getSetting('nfe_distribution_url', $config['nfe_distribution_url']),
        'nfe_distribution_action' => $repo->getSetting('nfe_distribution_action', $config['nfe_distribution_action']),
        'nfe_recepcaoevento_url' => $repo->getSetting('nfe_recepcaoevento_url', $config['nfe_recepcaoevento_url']),
        'nfe_recepcaoevento_action' => $repo->getSetting('nfe_recepcaoevento_action', $config['nfe_recepcaoevento_action']),
        'nfe_consulta_protocolo_url' => $repo->getSetting('nfe_consulta_protocolo_url', $config['nfe_consulta_protocolo_url'] ?? ''),
        'nfe_consulta_protocolo_action' => $repo->getSetting('nfe_consulta_protocolo_action', $config['nfe_consulta_protocolo_action'] ?? ''),
        'cte_distribution_url' => $repo->getSetting('cte_distribution_url', $config['cte_distribution_url']),
        'cte_distribution_action' => $repo->getSetting('cte_distribution_action', $config['cte_distribution_action']),
        'nfse_base_url' => $repo->getSetting('nfse_base_url', $config['nfse_base_url']),
        'nfse_distribution_path' => $repo->getSetting('nfse_distribution_path', $config['nfse_distribution_path']),
        'nfse_auth_type' => $repo->getSetting('nfse_auth_type', $config['nfse_auth_type']),
        'nfse_token' => $repo->getSetting('nfse_token', $config['nfse_token']),
        'nfse_page_size' => $repo->getSetting('nfse_page_size', (string)$config['nfse_page_size']),
        'auto_cte_enabled' => $repo->getSetting('auto_cte_enabled', (string)$config['auto_cte_enabled']),
        'auto_cte_all_companies' => $repo->getSetting('auto_cte_all_companies', '0'),
        'auto_cte_company_id' => $repo->getSetting('auto_cte_company_id', (string)$config['auto_cte_company_id']),
        'auto_cte_company_ids' => $repo->getSetting('auto_cte_company_ids', (string)($config['auto_cte_company_ids'] ?? '')),
        'auto_cte_interval_minutes' => $repo->getSetting('auto_cte_interval_minutes', (string)$config['auto_cte_interval_minutes']),
        'cte_robot_max_cycles' => $repo->getSetting('cte_robot_max_cycles', (string)$config['cte_robot_max_cycles']),
        'cte_robot_time_limit_seconds' => $repo->getSetting('cte_robot_time_limit_seconds', (string)$config['cte_robot_time_limit_seconds']),
        'auto_nfe_enabled' => $repo->getSetting('auto_nfe_enabled', (string)$config['auto_nfe_enabled']),
        'auto_nfe_all_companies' => $repo->getSetting('auto_nfe_all_companies', '0'),
        'auto_nfe_company_id' => $repo->getSetting('auto_nfe_company_id', (string)$config['auto_nfe_company_id']),
        'auto_nfe_company_ids' => $repo->getSetting('auto_nfe_company_ids', (string)($config['auto_nfe_company_ids'] ?? '')),
        'auto_nfe_interval_minutes' => $repo->getSetting('auto_nfe_interval_minutes', (string)$config['auto_nfe_interval_minutes']),
        'auto_nfe_manifest_science' => $repo->getSetting('auto_nfe_manifest_science', (string)$config['auto_nfe_manifest_science']),
        'nfe_robot_max_cycles' => $repo->getSetting('nfe_robot_max_cycles', (string)$config['nfe_robot_max_cycles']),
        'nfe_robot_time_limit_seconds' => $repo->getSetting('nfe_robot_time_limit_seconds', (string)$config['nfe_robot_time_limit_seconds']),
        'nfe_science_limit_per_run' => $repo->getSetting('nfe_science_limit_per_run', (string)$config['nfe_science_limit_per_run']),
        'auto_nfse_enabled' => $repo->getSetting('auto_nfse_enabled', (string)$config['auto_nfse_enabled']),
        'auto_nfse_all_companies' => $repo->getSetting('auto_nfse_all_companies', '0'),
        'auto_nfse_company_id' => $repo->getSetting('auto_nfse_company_id', (string)$config['auto_nfse_company_id']),
        'auto_nfse_company_ids' => $repo->getSetting('auto_nfse_company_ids', (string)($config['auto_nfse_company_ids'] ?? '')),
        'auto_nfse_interval_minutes' => $repo->getSetting('auto_nfse_interval_minutes', (string)$config['auto_nfse_interval_minutes']),
        'auto_nfse_nsu_limit' => $repo->getSetting('auto_nfse_nsu_limit', (string)$config['auto_nfse_nsu_limit']),
    ],
];

switch ($page) {
    case 'login':
        include __DIR__ . '/../templates/login.php';
        break;
    case 'dashboard':
        $dashboardFilters = [
            'company_id' => $_GET['company_id'] ?? '',
            'date_start' => $_GET['date_start'] ?? '',
            'date_end' => $_GET['date_end'] ?? '',
        ];
        $viewData['dashboardFilters'] = $dashboardFilters;
        $viewData['dashboard'] = $repo->dashboard($dashboardFilters);
        $viewData['jobs'] = $repo->jobs(10);
        $viewData['actions'] = $repo->recentActions();
        include __DIR__ . '/../templates/dashboard.php';
        break;
    case 'revenue':
        $revenueFilters = revenue_filters_from_request($_GET);
        $revenuePage = max(1, (int)($_GET['p'] ?? 1));
        $revenuePerPage = 200;
        $selectedRevenueId = (int)($_GET['id'] ?? 0);
        $viewData['revenueFilters'] = $revenueFilters;
        $viewData['canViewCost'] = $auth->canViewCost();
        $viewData['revenueTab'] = (string)($_GET['tab'] ?? 'dashboard');
        $viewData['revenuePage'] = $revenuePage;
        $viewData['revenuePerPage'] = $revenuePerPage;
        $viewData['revenueOptions'] = $repo->revenueFilterOptions();
        $viewData['revenueDashboard'] = $repo->revenueDashboard($revenueFilters);
        $viewData['revenueTotals'] = $repo->revenueTotals($revenueFilters);
        $viewData['revenueDocuments'] = $repo->revenueDocumentsPage($revenueFilters, $revenuePage, $revenuePerPage);
        $viewData['revenueSelectedDocument'] = $selectedRevenueId > 0 ? $repo->findRevenueDocumentInContext($selectedRevenueId, $revenueFilters) : null;
        $viewData['revenueSelectedItems'] = $selectedRevenueId > 0 ? $repo->revenueItems($revenueFilters, $selectedRevenueId) : [];
        $viewData['revenueItems'] = $repo->revenueItems($revenueFilters, null, 300);
        $viewData['moduleTitle'] = 'Faturamento';
        $viewData['moduleSubtitle'] = 'Análise gerencial e fiscal de vendas e devoluções integradas do ERP.';
        include __DIR__ . '/../templates/revenue.php';
        break;
    case 'settings':
        $viewData['automationJobs'] = $repo->jobsByTypes(['cte_until_max', 'nfe_until_max', 'nfe_until_max_science', 'nfse'], 30);
        include __DIR__ . '/../templates/settings.php';
        break;
    case 'companies':
        $viewData['editCompany'] = !empty($_GET['edit_company_id']) ? $repo->findCompany((int)$_GET['edit_company_id']) : null;
        include __DIR__ . '/../templates/companies.php';
        break;
    case 'users':
        if (!$auth->isAdmin()) {
            http_response_code(403);
            echo 'Acesso negado.';
            break;
        }
        $viewData['users'] = $repo->users();
        $viewData['editUser'] = !empty($_GET['edit_user_id']) ? $repo->findUser((int)$_GET['edit_user_id']) : null;
        include __DIR__ . '/../templates/users.php';
        break;
    case 'import':
        include __DIR__ . '/../templates/import.php';
        break;
    case 'documents':
        $documentFilters = document_filters_from_request($_GET);
        $documentPage = max(1, (int)($_GET['p'] ?? 1));
        $documentPerPage = 200;
        $documentShouldQuery = count(array_diff(array_keys($_GET), ['page'])) > 0;
        $viewData['documentFilters'] = $documentFilters;
        $viewData['documentPage'] = $documentPage;
        $viewData['documentPerPage'] = $documentPerPage;
        $viewData['documentsDeferred'] = !$documentShouldQuery;
        $viewData['documentTotals'] = $documentShouldQuery ? $repo->documentsTotals($documentFilters) : ['total' => 0, 'total_value' => 0];
        $viewData['documents'] = $documentShouldQuery ? $repo->documentsPage($documentFilters, $documentPage, $documentPerPage) : [];
        $viewData['documentIgnoredCfops'] = $repo->documentIgnoredCfops();
        $viewData['documentCfopOptions'] = $repo->documentCfopOptions();
        include __DIR__ . '/../templates/documents.php';
        break;
    case 'period_closure':
        $closureId = (int)($_GET['id'] ?? 0);
        $viewData['periodClosures'] = $repo->periodClosures(10);
        $viewData['selectedClosure'] = $closureId > 0 ? $repo->findPeriodClosure($closureId) : ($viewData['periodClosures'][0] ?? null);
        $viewData['periodItems'] = $viewData['selectedClosure'] ? $repo->periodClosureItems((int)$viewData['selectedClosure']['id'], ['status' => $_GET['status'] ?? '']) : [];
        include __DIR__ . '/../templates/period_closure.php';
        break;
    case 'period_closure_docs':
        include __DIR__ . '/../templates/period_closure_docs.php';
        break;
    case 'jobs':
        $viewData['selectedJobCompanyId'] = (string)($_GET['company_id'] ?? '0');
        $viewData['selectedJobType'] = (string)($_GET['job_type'] ?? 'collect_all');
        $viewData['jobs'] = $repo->jobs(20);
        include __DIR__ . '/../templates/jobs.php';
        break;
    case 'nfe_keys':
        $viewData['selectedKeyCompanyId'] = (string)($_GET['company_id'] ?? '0');
        $viewData['jobs'] = $repo->jobs(12);
        include __DIR__ . '/../templates/nfe_keys.php';
        break;
    case 'nfe_rescan':
        $viewData['selectedRescanCompanyId'] = (string)($_GET['company_id'] ?? '0');
        include __DIR__ . '/../templates/nfe_rescan.php';
        break;
    default:
        http_response_code(404);
        echo 'Página não encontrada.';
}
