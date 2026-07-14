<?php
declare(strict_types=1);

namespace ControlS\Portal;

use DOMDocument;
use DOMXPath;

final class XmlParser
{
    public function parse(string $xml): array
    {
        $dom = new DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
            throw new \RuntimeException('XML inválido ou corrompido.');
        }
        $root = strtolower($dom->documentElement?->localName ?? '');

        if (in_array($root, ['evento', 'eventocte', 'eventomdfe', 'procevento', 'proceventonfe', 'proceventocte', 'proceventomdfe', 'resevento'], true)) {
            throw new \RuntimeException('XML de evento ignorado. O portal armazena apenas XML de documento fiscal.');
        }

        if (
            in_array($root, ['cteproc', 'cte', 'ctosproc', 'cteosproc', 'cteos'], true)
            || $this->hasAny($dom, ['CTe', 'infCte', 'CTeOS', 'infCteOS', 'chCTe', 'vTPrest'])
        ) {
            return $this->parseCTe($dom, $xml);
        }
        if (in_array($root, ['mdfeproc', 'mdfe'], true) || $this->hasAny($dom, ['MDFe', 'infMDFe', 'chMDFe'])) {
            return $this->parseMDFe($dom, $xml);
        }
        if (in_array($root, ['nfeproc', 'nfe'], true) || $this->hasAny($dom, ['NFe', 'infNFe', 'chNFe'])) {
            return $this->parseNFe($dom, $xml);
        }

        return $this->parseNFSeNational($dom, $xml);
    }

    private function parseNFe(DOMDocument $dom, string $xml): array
    {
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('n', 'http://www.portalfiscal.inf.br/nfe');

        $mod = $this->x($xp, '//n:ide/n:mod') ?: $this->firstAny($xp, ['//*[local-name()="ide"]/*[local-name()="mod"]']);
        $docType = $mod === '65' ? 'NFCE' : 'NFE';
        $issueDate = $this->x($xp, '//n:ide/n:dhEmi') ?: $this->x($xp, '//n:ide/n:dEmi') ?: $this->firstAny($xp, ['//*[local-name()="ide"]/*[local-name()="dhEmi"]', '//*[local-name()="ide"]/*[local-name()="dEmi"]']);
        $accessKey = $this->attr($xp, '//*[local-name()="infNFe"]/@Id') ?: $this->attr($xp, '//@Id');
        $accessKey = preg_replace('/^NFe/', '', $accessKey ?? '');
        $status = $accessKey ? 'xml_completo' : 'apenas_resumo';

        return [
            'doc_type' => $docType,
            'model' => $mod,
            'access_key' => $accessKey ?: null,
            'referenced_nfe_keys' => $this->referencedNFeKeys($xp, 'NFE'),
            'referenced_document_numbers' => $this->referencedDocumentNumbers($xp, 'NFE'),
            'number' => $this->x($xp, '//n:ide/n:nNF'),
            'order_number' => $this->firstAny($xp, ['//*[local-name()="xPed"]']),
            'issuer_cnpj' => $this->x($xp, '//n:emit/n:CNPJ'),
            'issuer_name' => $this->x($xp, '//n:emit/n:xNome'),
            'recipient_cnpj' => $this->x($xp, '//n:dest/n:CNPJ') ?: $this->x($xp, '//n:dest/n:CPF'),
            'recipient_name' => $this->x($xp, '//n:dest/n:xNome'),
            'issue_date' => $issueDate ?: null,
            'total_value' => $this->toFloat($this->x($xp, '//n:ICMSTot/n:vNF') ?: $this->firstAny($xp, ['//*[local-name()="ICMSTot"]/*[local-name()="vNF"]'])),
            'status' => $status,
            'manifestation_status' => ($docType === 'NFE' && $status === 'apenas_resumo') ? 'pending' : 'not_applicable',
            'source' => 'manual_import',
            'notes' => null,
            'raw_xml' => $xml,
            'digest' => hash('sha256', $xml),
        ];
    }

    private function parseCTe(DOMDocument $dom, string $xml): array
    {
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('c', 'http://www.portalfiscal.inf.br/cte');

        $issueDate = $this->x($xp, '//c:ide/c:dhEmi') ?: $this->x($xp, '//c:ide/c:dEmi') ?: $this->firstAny($xp, ['//*[local-name()="ide"]/*[local-name()="dhEmi"]', '//*[local-name()="ide"]/*[local-name()="dEmi"]']);
        $accessKey = $this->attr($xp, '//*[local-name()="infCte" or local-name()="infCteOS"]/@Id') ?: $this->firstAny($xp, ['//*[local-name()="chCTe"]']) ?: $this->attr($xp, '//@Id');
        $accessKey = preg_replace('/^CTe/', '', $accessKey ?? '');

        return [
            'doc_type' => 'CTE',
            'model' => '57',
            'access_key' => $accessKey ?: null,
            'referenced_nfe_keys' => $this->referencedNFeKeys($xp, 'CTE'),
            'referenced_document_numbers' => $this->referencedDocumentNumbers($xp, 'CTE'),
            'number' => $this->x($xp, '//c:ide/c:nCT') ?: $this->firstAny($xp, ['//*[local-name()="ide"]/*[local-name()="nCT"]']),
            'order_number' => $this->firstAny($xp, ['//*[local-name()="xPed"]']),
            'issuer_cnpj' => $this->x($xp, '//c:emit/c:CNPJ') ?: $this->firstAny($xp, ['//*[local-name()="emit"]/*[local-name()="CNPJ"]']),
            'issuer_name' => $this->x($xp, '//c:emit/c:xNome') ?: $this->firstAny($xp, ['//*[local-name()="emit"]/*[local-name()="xNome"]']),
            'recipient_cnpj' => $this->x($xp, '//c:rem/c:CNPJ') ?: $this->x($xp, '//c:dest/c:CNPJ') ?: $this->firstAny($xp, ['//*[local-name()="dest"]/*[local-name()="CNPJ"]', '//*[local-name()="rem"]/*[local-name()="CNPJ"]', '//*[local-name()="toma4"]/*[local-name()="CNPJ"]']),
            'recipient_name' => $this->x($xp, '//c:dest/c:xNome') ?: $this->x($xp, '//c:rem/c:xNome') ?: $this->firstAny($xp, ['//*[local-name()="dest"]/*[local-name()="xNome"]', '//*[local-name()="rem"]/*[local-name()="xNome"]', '//*[local-name()="toma4"]/*[local-name()="xNome"]']),
            'issue_date' => $issueDate ?: null,
            'total_value' => $this->toFloat($this->x($xp, '//c:vPrest/c:vTPrest') ?: $this->firstAny($xp, ['//*[local-name()="vPrest"]/*[local-name()="vTPrest"]', '//*[local-name()="vTPrest"]', '//*[local-name()="vRec"]'])),
            'status' => 'xml_completo',
            'manifestation_status' => 'not_applicable',
            'source' => 'manual_import',
            'notes' => null,
            'raw_xml' => $xml,
            'digest' => hash('sha256', $xml),
        ];
    }

    private function parseNFSeNational(DOMDocument $dom, string $xml): array
    {
        $xp = new DOMXPath($dom);
        $rootName = strtolower($dom->documentElement?->localName ?? 'nfse');
        $issueDate = $this->firstAny($xp, ['//*[contains(local-name(),"DataHoraEmissao")]', '//*[contains(local-name(),"dhEmi")]', '//*[contains(local-name(),"DataEmissao")]']);
        $number = $this->firstAny($xp, ['//*[contains(local-name(),"numero")]', '//*[contains(local-name(),"nNFSe")]', '//*[contains(local-name(),"Numero")]']);
        $issuer = $this->firstAny($xp, ['//*[contains(local-name(),"Prestador")]//*[contains(local-name(),"Cnpj")]', '//*[contains(local-name(),"emit")]//*[contains(local-name(),"CNPJ")]']);
        $issuerName = $this->firstAny($xp, ['//*[contains(local-name(),"Prestador")]//*[contains(local-name(),"Razao")]', '//*[contains(local-name(),"xNome")]']);
        $recipient = $this->firstAny($xp, ['//*[contains(local-name(),"Tomador")]//*[contains(local-name(),"Cnpj")]', '//*[contains(local-name(),"dest")]//*[contains(local-name(),"CNPJ")]']);
        $recipientName = $this->firstAny($xp, ['//*[contains(local-name(),"Tomador")]//*[contains(local-name(),"Razao")]', '//*[contains(local-name(),"xNome")]']);
        $value = $this->firstAny($xp, ['//*[contains(local-name(),"ValorServicos")]', '//*[contains(local-name(),"vNF")]', '//*[contains(local-name(),"ValorLiquido")]']);

        return [
            'doc_type' => 'NFSE',
            'model' => strtoupper($rootName),
            'access_key' => null,
            'number' => $number,
            'order_number' => $this->firstAny($xp, ['//*[local-name()="xPed"]', '//*[contains(local-name(),"Pedido")]', '//*[contains(local-name(),"pedido")]']),
            'issuer_cnpj' => $issuer,
            'issuer_name' => $issuerName,
            'recipient_cnpj' => $recipient,
            'recipient_name' => $recipientName,
            'issue_date' => $issueDate ?: null,
            'total_value' => $this->toFloat($value),
            'status' => 'xml_completo',
            'manifestation_status' => 'not_applicable',
            'source' => 'manual_import',
            'notes' => 'Leitura para cenário nacional de NFS-e.',
            'raw_xml' => $xml,
            'digest' => hash('sha256', $xml),
        ];
    }

    private function parseMDFe(DOMDocument $dom, string $xml): array
    {
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('m', 'http://www.portalfiscal.inf.br/mdfe');

        $issueDate = $this->x($xp, '//m:ide/m:dhEmi') ?: $this->firstAny($xp, ['//*[local-name()="ide"]/*[local-name()="dhEmi"]']);
        $accessKey = $this->attr($xp, '//*[local-name()="infMDFe"]/@Id') ?: $this->firstAny($xp, ['//*[local-name()="chMDFe"]']);
        $accessKey = preg_replace('/^MDFe/', '', $accessKey ?? '');

        return [
            'doc_type' => 'MDFE',
            'model' => '58',
            'access_key' => $accessKey ?: null,
            'number' => $this->x($xp, '//m:ide/m:nMDF') ?: $this->firstAny($xp, ['//*[local-name()="ide"]/*[local-name()="nMDF"]']),
            'order_number' => null,
            'issuer_cnpj' => $this->x($xp, '//m:emit/m:CNPJ') ?: $this->firstAny($xp, ['//*[local-name()="emit"]/*[local-name()="CNPJ"]']),
            'issuer_name' => $this->x($xp, '//m:emit/m:xNome') ?: $this->firstAny($xp, ['//*[local-name()="emit"]/*[local-name()="xNome"]']),
            'recipient_cnpj' => null,
            'recipient_name' => null,
            'issue_date' => $issueDate ?: null,
            'total_value' => 0.0,
            'status' => $accessKey ? 'xml_completo' : 'apenas_resumo',
            'manifestation_status' => 'not_applicable',
            'source' => 'manual_import',
            'notes' => 'Documento MDF-e identificado para nao ser tratado como NF-e.',
            'raw_xml' => $xml,
            'digest' => hash('sha256', $xml),
        ];
    }

    private function x(DOMXPath $xp, string $expr): ?string
    {
        $list = $xp->query($expr);
        if (!$list || $list->length === 0) {
            return null;
        }
        return trim((string) $list->item(0)?->textContent);
    }

    private function attr(DOMXPath $xp, string $expr): ?string
    {
        $list = $xp->query($expr);
        if (!$list || $list->length === 0) {
            return null;
        }
        return trim((string) $list->item(0)?->nodeValue);
    }

    private function firstAny(DOMXPath $xp, array $exprs): ?string
    {
        foreach ($exprs as $expr) {
            $list = $xp->query($expr);
            if ($list && $list->length > 0) {
                $value = trim((string) $list->item(0)?->textContent);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return null;
    }

    private function hasAny(DOMDocument $dom, array $localNames): bool
    {
        $xp = new DOMXPath($dom);
        foreach ($localNames as $name) {
            $nodes = $xp->query('//*[local-name()="' . $name . '"]');
            if ($nodes && $nodes->length > 0) {
                return true;
            }
        }
        return false;
    }

    private function toFloat(?string $value): float
    {
        $value = trim((string)$value);
        if ($value === '') {
            return 0.0;
        }
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '.', $value);
        }
        return (float)$value;
    }

    private function referencedNFeKeys(DOMXPath $xp, string $type): ?string
    {
        $keys = [];
        $expr = $type === 'CTE'
            ? '//*[local-name()="infNFe"]/*[local-name()="chave" or local-name()="chNFe"]'
            : '//*[local-name()="NFref"]/*[local-name()="refNFe"]';
        foreach ($xp->query($expr) ?: [] as $node) {
            $key = preg_replace('/\D+/', '', trim((string)$node->textContent));
            if (strlen($key) === 44) {
                $keys[$key] = true;
            }
        }
        return $keys ? implode(', ', array_keys($keys)) : null;
    }

    private function referencedDocumentNumbers(DOMXPath $xp, string $type): ?string
    {
        $numbers = [];
        $keys = $this->referencedNFeKeys($xp, $type);
        foreach (array_filter(array_map('trim', explode(',', (string)$keys))) as $key) {
            $number = $this->numberFromAccessKey($key);
            if ($number !== '') {
                $numbers[$number] = true;
            }
        }
        foreach ($xp->query('//*[local-name()="NFref"]//*[local-name()="nNF"]') ?: [] as $node) {
            $number = ltrim(preg_replace('/\D+/', '', trim((string)$node->textContent)) ?: '', '0');
            if ($number !== '') {
                $numbers[$number] = true;
            }
        }
        return $numbers ? implode(', ', array_keys($numbers)) : null;
    }

    private function numberFromAccessKey(string $key): string
    {
        $digits = preg_replace('/\D+/', '', $key) ?: '';
        if (strlen($digits) !== 44) {
            return '';
        }
        return ltrim(substr($digits, 25, 9), '0') ?: '0';
    }
}
