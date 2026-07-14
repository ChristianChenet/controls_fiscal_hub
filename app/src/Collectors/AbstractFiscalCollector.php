<?php
declare(strict_types=1);

namespace ControlS\Portal\Collectors;

use ControlS\Portal\CertificateService;
use ControlS\Portal\Fiscal\DocZipDecoder;
use ControlS\Portal\Fiscal\SefazSoapClient;
use ControlS\Portal\Http\MutualTlsHttpClient;
use ControlS\Portal\Repository;
use ControlS\Portal\Storage;
use ControlS\Portal\XmlParser;
use DOMDocument;
use DOMXPath;

abstract class AbstractFiscalCollector implements CollectorInterface
{
    protected ?array $company = null;

    public function __construct(
        protected array $config,
        protected Repository $repo,
        protected Storage $storage,
        protected CertificateService $certificates,
        protected MutualTlsHttpClient $httpClient,
        protected XmlParser $parser
    ) {
    }

    public function setCompanyContext(?array $company): void
    {
        $this->company = $company;
    }

    protected function currentCompany(): array
    {
        if (!$this->company) {
            throw new \RuntimeException('Contexto da empresa não definido para a coleta.');
        }
        return $this->company;
    }

    protected function processDistributionResponse(string $response, string $docType): array
    {
        $retXml = $this->extractReturnXml($response);
        $dom = new DOMDocument();
        $dom->loadXML($retXml, LIBXML_NOCDATA | LIBXML_NOBLANKS);
        $xp = new DOMXPath($dom);

        $cStat = $this->first($xp, '//*[local-name()="cStat"]');
        $xMotivo = $this->first($xp, '//*[local-name()="xMotivo"]');
        $ultNSU = $this->first($xp, '//*[local-name()="ultNSU"]') ?: '0';
        $maxNSU = $this->first($xp, '//*[local-name()="maxNSU"]') ?: '0';

        $created = 0; $updated = 0; $errors = 0; $details = [];
        foreach ($xp->query('//*[local-name()="docZip"]') ?: [] as $docNode) {
            $schema = (string)($docNode->attributes?->getNamedItem('schema')?->nodeValue ?? '');
            $nsu = (string)($docNode->attributes?->getNamedItem('NSU')?->nodeValue ?? '');
            try {
                $xml = DocZipDecoder::decode((string)$docNode->textContent);
                $result = $this->persistDistributedXml($docType, $schema, $nsu, $xml);
                $created += $result['created']; $updated += $result['updated'];
                $details[] = $schema . ':' . $result['status'];
            } catch (\Throwable $e) {
                $errors++; $details[] = 'erro ' . $schema . ': ' . $e->getMessage();
                $this->storage->appendLog('collector_' . strtolower($docType) . '.log', 'Erro docZip ' . $schema . ': ' . $e->getMessage());
            }
        }

        return [
            'cStat'=>$cStat, 'xMotivo'=>$xMotivo,
            'ultNSU'=>preg_replace('/\D+/', '', $ultNSU),
            'maxNSU'=>preg_replace('/\D+/', '', $maxNSU),
            'created'=>$created, 'updated'=>$updated, 'errors'=>$errors, 'details'=>$details, 'ret_xml'=>$retXml,
        ];
    }

    protected function extractReturnXml(string $soapResponse): string
    {
        $dom = new DOMDocument();
        $dom->loadXML($soapResponse, LIBXML_NOCDATA | LIBXML_NOBLANKS);
        $xp = new DOMXPath($dom);
        $nodes = $xp->query('//*[local-name()="nfeDistDFeInteresseResult" or local-name()="cteDistDFeInteresseResult" or local-name()="nfeRecepcaoEventoResult" or local-name()="nfeRecepcaoEventoNFResult"]');
        if ($nodes && $nodes->length > 0) {
            $resultNode = $nodes->item(0);
            foreach ($resultNode?->childNodes ?? [] as $childNode) {
                if ($childNode->nodeType === XML_ELEMENT_NODE) {
                    return (string)$dom->saveXML($childNode);
                }
            }
            return html_entity_decode((string)$resultNode?->textContent, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        $nodes = $xp->query('//*[local-name()="retDistDFeInt" or local-name()="retEnvEvento"]');
        if ($nodes && $nodes->length > 0) return $dom->saveXML($nodes->item(0));
        return $soapResponse;
    }

    protected function persistDistributedXml(string $docType, string $schema, string $nsu, string $xml): array
    {
        if ($this->isEventDocument($schema, $xml)) {
            $company = $this->currentCompany();
            $event = $this->repo->parseInformativeEventXml($xml);
            if ($event) {
                $event['company_id'] = (int)$company['id'];
                $event['schema_name'] = $schema;
                $event['raw_xml'] = $xml;
                $event['digest'] = hash('sha256', $xml);
                $this->repo->saveDocumentEvent($event);
                $this->storage->appendLog('collector_' . strtolower($docType) . '.log', 'Evento informativo vinculado: schema=' . $schema . ' NSU=' . $nsu . ' chave=' . $event['access_key']);
                return ['created'=>0, 'updated'=>1, 'status'=>'evento_informativo'];
            }
            $this->storage->appendLog('collector_' . strtolower($docType) . '.log', 'Evento ignorado na distribuicao: schema=' . $schema . ' NSU=' . $nsu);
            return ['created'=>0, 'updated'=>0, 'status'=>'evento_ignorado'];
        }

        $company = $this->currentCompany();
        $parsed = $this->parseDistributedXml($docType, $schema, $xml);
        $saved = ['xml_path'=>null, 'storage_dir'=>null];

        $existing = !empty($parsed['access_key']) ? $this->repo->findDocumentByAccessKey($parsed['doc_type'], (string)$parsed['access_key'], (int)$company['id']) : null;
        $existingHasCompleteXml = $existing && $this->documentHasCompleteXml($existing);

        if ($existingHasCompleteXml) {
            return ['created'=>0, 'updated'=>0, 'status'=>'ja_existente_xml_completo'];
        }

        if (($parsed['status'] ?? '') !== 'apenas_resumo') {
            $saved = $this->storage->saveXml(
                $parsed['doc_type'],
                (string)($parsed['issue_date'] ?? ''),
                $xml,
                $this->guessFileName($parsed, $schema, $nsu),
                (string)$company['cnpj'],
                (string)($company['default_download_dir'] ?? '')
            );
        }

        $row = $this->repo->saveDocument($parsed + $saved + [
            'company_id' => (int)$company['id'],
            'company_name' => (string)$company['company_name'],
            'company_cnpj' => (string)$company['cnpj'],
            'schema_name' => $schema,
            'raw_xml' => $xml,
            'imported_at' => $existing ? ($existing['imported_at'] ?? date('c')) : date('c'),
            'updated_at' => date('c'),
        ]);

        return ['created'=>$existing ? 0 : 1, 'updated'=>$existing ? 1 : 0, 'status'=>(string)($row['status'] ?? 'imported')];
    }

    protected function parseDistributedXml(string $docType, string $schema, string $xml): array
    {
        if ($this->isEventDocument($schema, $xml)) {
            throw new \RuntimeException('Evento ignorado. Nao e XML de documento fiscal.');
        }
        if (str_starts_with($schema, 'resNFe') || str_starts_with($schema, 'resCTe') || str_starts_with($schema, 'resMDFe')) {
            return $this->parseSummary($docType, $schema, $xml);
        }
        $parsed = $this->parser->parse($xml);
        $parsed['source'] = 'distribution_service';
        $parsed['schema_name'] = $schema;
        if (($parsed['status'] ?? '') === 'imported') {
            $parsed['status'] = 'xml_completo';
        }
        return $parsed;
    }

    protected function isEventDocument(string $schema, string $xml): bool
    {
        $schema = strtolower($schema);
        if (str_contains($schema, 'evento')) {
            return true;
        }

        $dom = new DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
            return false;
        }
        $root = strtolower((string)($dom->documentElement?->localName ?? ''));
        return in_array($root, [
            'evento',
            'eventocte',
            'eventomdfe',
            'procevento',
            'proceventonfe',
            'proceventocte',
            'proceventomdfe',
            'resevento',
        ], true);
    }

    protected function parseSummary(string $docType, string $schema, string $xml): array
    {
        $dom = new DOMDocument();
        $dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS);
        $xp = new DOMXPath($dom);

        $accessKey = $this->first($xp, '//*[local-name()="chNFe" or local-name()="chCTe" or local-name()="chMDFe"]');
        $model = strlen((string)$accessKey) === 44 ? substr((string)$accessKey, 20, 2) : null;
        $normalizedDocType = match ($model) {
            '57' => 'CTE',
            '58' => 'MDFE',
            '65' => 'NFCE',
            default => $docType === 'CTE' ? 'CTE' : 'NFE',
        };
        $issuerCnpj = $this->first($xp, '//*[local-name()="CNPJ"]');
        $issuerName = $this->first($xp, '//*[local-name()="xNome"]');
        $issueDate = $this->first($xp, '//*[local-name()="dhEmi"]');
        $value = $this->first($xp, '//*[local-name()="vNF" or local-name()="vCT"]');
        $nfeSituation = $normalizedDocType === 'NFE' ? ($this->first($xp, '//*[local-name()="cSitNFe"]') ?: '') : '';
        $manifestationStatus = $normalizedDocType === 'NFE' ? 'pending' : 'not_applicable';
        $status = 'apenas_resumo';
        $notes = 'Documento resumido via ' . $schema;
        if ($normalizedDocType === 'MDFE') {
            $status = 'xml_completo';
            $manifestationStatus = 'not_applicable';
            $notes .= '. MDF-e identificado na distribuicao; nao exige manifestacao de NF-e.';
        }
        if ($normalizedDocType === 'NFE' && $nfeSituation !== '' && $nfeSituation !== '1') {
            $status = $nfeSituation === '3' ? 'cancelado' : 'denegado';
            $manifestationStatus = 'not_applicable';
            $notes .= '. Manifestacao nao aplicavel: situacao da NF-e no resumo cSitNFe=' . $nfeSituation . '.';
        }
        $number = null;
        if (is_string($accessKey) && strlen($accessKey) === 44) {
            $number = ltrim(substr($accessKey, 25, 9), '0') ?: '0';
        }

        return [
            'doc_type'=>$normalizedDocType,
            'model'=>$model ?: ($normalizedDocType === 'CTE' ? '57' : ($normalizedDocType === 'MDFE' ? '58' : '55')),
            'access_key'=>$accessKey ?: null,
            'referenced_nfe_keys'=>$normalizedDocType === 'CTE' ? ($this->referencedNFeKeys($xp, (string)$accessKey) ?: null) : null,
            'number'=>$number,
            'issuer_cnpj'=>$issuerCnpj ?: null,
            'issuer_name'=>$issuerName ?: null,
            'recipient_cnpj'=>(string)($this->currentCompany()['cnpj'] ?? ''),
            'recipient_name'=>(string)($this->currentCompany()['company_name'] ?? ''),
            'issue_date'=>$issueDate ?: null,
            'total_value'=>(float)($value ?: 0),
            'status'=>$status,
            'manifestation_status'=>$manifestationStatus,
            'source'=>'distribution_service',
            'notes'=>$notes,
            'raw_xml'=>$xml,
            'digest'=>hash('sha256', $xml),
        ];
    }

    protected function guessFileName(array $parsed, string $schema, string $nsu): string
    {
        $key = preg_replace('/\D+/', '', (string)($parsed['access_key'] ?? ''));
        $docType = strtoupper((string)($parsed['doc_type'] ?? 'DOC'));
        return $docType . '_' . ($key ?: $nsu) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $schema) . '.xml';
    }

    protected function documentHasCompleteXml(array $doc): bool
    {
        if (($doc['status'] ?? '') !== 'xml_completo') {
            return false;
        }
        if (!empty($doc['raw_xml'])) {
            return true;
        }
        return !empty($doc['xml_path']) && is_file((string)$doc['xml_path']);
    }

    protected function soapClient(): SefazSoapClient
    {
        return new SefazSoapClient($this->httpClient, $this->config);
    }

    protected function first(DOMXPath $xp, string $expr): ?string
    {
        $nodes = $xp->query($expr);
        if (!$nodes || $nodes->length === 0) return null;
        return trim((string)$nodes->item(0)?->textContent);
    }

    protected function referencedNFeKeys(DOMXPath $xp, string $ownAccessKey): string
    {
        $ownKey = preg_replace('/\D+/', '', $ownAccessKey) ?: '';
        $keys = [];
        foreach ($xp->query('//*[local-name()="infNFe"]/*[local-name()="chave" or local-name()="chNFe"]') ?: [] as $node) {
            $key = preg_replace('/\D+/', '', trim((string)$node->textContent));
            if (strlen($key) === 44 && $key !== $ownKey) {
                $keys[$key] = true;
            }
        }
        return $keys ? implode(', ', array_keys($keys)) : '';
    }
}
