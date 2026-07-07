<?php
declare(strict_types=1);

namespace ControlS\Portal;

use ControlS\Portal\Fiscal\SefazSoapClient;
use ControlS\Portal\Fiscal\XmlSigner;
use ControlS\Portal\Http\MutualTlsHttpClient;
use DOMDocument;
use DOMXPath;
use RuntimeException;

final class ManifestationService
{
    public const TYPES = [
        'science' => 'Ciência da Operação',
        'confirm' => 'Confirmação da Operação',
        'unknown' => 'Desconhecimento da Operação',
        'not_realized' => 'Operação não Realizada',
    ];

    private const EVENT_CODES = [
        'science' => '210210',
        'confirm' => '210200',
        'unknown' => '210220',
        'not_realized' => '210240',
    ];

    private const EVENT_DESCRIPTIONS = [
        'science' => 'Ciencia da Operacao',
        'confirm' => 'Confirmacao da Operacao',
        'unknown' => 'Desconhecimento da Operacao',
        'not_realized' => 'Operacao nao Realizada',
    ];

    public function __construct(
        private array $config,
        private Repository $repo,
        private Storage $storage,
        private CertificateService $certificates,
        private MutualTlsHttpClient $httpClient,
        private XmlParser $parser
    ) {
    }

    public function manifest(array $ids, string $type, ?string $justification = null): int
    {
        $label = self::TYPES[$type] ?? null;
        if (!$label) {
            throw new RuntimeException('Tipo de manifestação inválido.');
        }
        $docs = array_filter(array_map(fn(int $id) => $this->repo->findDocument($id), array_map('intval', $ids)));
        if (!$docs) return 0;

        $byCompany = [];
        foreach ($docs as $doc) {
            $byCompany[(int)($doc['company_id'] ?? 0)][] = $doc;
        }

        $success = 0;
        foreach ($byCompany as $companyId => $companyDocs) {
            if ($companyId <= 0) continue;
            $company = $this->repo->findCompany($companyId);
            if (!$company) continue;

            $pem = $this->certificates->exportPemBundle($companyId);
            $signer = new XmlSigner();
            $soap = new SefazSoapClient($this->httpClient, $this->config);
            $cnpj = preg_replace('/\D+/', '', (string)$company['cnpj']);

            foreach ($companyDocs as $doc) {
                if (($doc['doc_type'] ?? '') !== 'NFE') continue;
                if (!in_array((string)($doc['status'] ?? ''), ['apenas_resumo', 'pendente_manifestacao'], true)) continue;
                if (!in_array((string)($doc['manifestation_status'] ?? ''), ['pending', 'error_science', 'error_confirm', 'error_unknown', 'error_not_realized'], true)) continue;
                $accessKey = preg_replace('/\D+/', '', (string)($doc['access_key'] ?? ''));
                if (strlen($accessKey) !== 44) continue;

                $eventXml = $this->buildEnvEventoXml($type, $cnpj, $accessKey, $justification);
                $signed = $signer->signInfEvento($eventXml, (string)file_get_contents($pem['cert']), (string)file_get_contents($pem['key']), (string)$pem['password']);
                try {
                    $response = $soap->send(
                        (string)$this->config['nfe_recepcaoevento_url'],
                        (string)$this->config['nfe_recepcaoevento_action'],
                        $signed,
                        'http://www.portalfiscal.inf.br/nfe/wsdl/NFeRecepcaoEvento4',
                        'nfeRecepcaoEventoNF',
                        'nfeDadosMsg',
                        $companyId
                    );
                } catch (RuntimeException $e) {
                    $notes = 'SEFAZ rejeitou a manifestação no webservice. Verifique certificado, ambiente e tente novamente após alguns minutos.';
                    $this->repo->updateDocumentManifestedByAccessKey('NFE', $accessKey, 'error_' . $type, null, $notes, $companyId);
                    $this->repo->logAction('manifest_' . $type, 'NF-e ' . $accessKey . ' => ' . $notes, $companyId);
                    $this->storage->appendLog('manifestation.log', '[' . $company['cnpj'] . '] NF-e ' . $accessKey . ' => ' . $notes . ' Detalhe tecnico: ' . $e->getMessage());
                    continue;
                }

                $result = $this->parseResult($response);
                $notes = $result['cStat'] . ' - ' . $result['xMotivo'];

                if (in_array($result['cStat'], ['128', '135', '136', '155'], true) && in_array($result['event_cStat'], ['135', '136', '155'], true)) {
                    $this->repo->updateDocumentManifestedByAccessKey('NFE', $accessKey, 'manifested_' . $type, 'aguardando_novo_download', $notes, $companyId);
                    $success++;
                } else {
                    $this->repo->updateDocumentManifestedByAccessKey('NFE', $accessKey, 'error_' . $type, null, $notes, $companyId);
                }

                $this->repo->logAction('manifest_' . $type, 'NF-e ' . $accessKey . ' => ' . $notes, $companyId);
                $this->storage->appendLog('manifestation.log', '[' . $company['cnpj'] . '] NF-e ' . $accessKey . ' => ' . $notes);
            }
        }
        return $success;
    }

    private function buildEnvEventoXml(string $type, string $cnpj, string $accessKey, ?string $justification = null): string
    {
        $code = self::EVENT_CODES[$type] ?? '';
        if ($code === '') throw new RuntimeException('Evento inválido.');
        if ($type === 'not_realized') {
            $justification = trim((string)$justification);
            if (mb_strlen($justification) < 15) throw new RuntimeException('A operação não realizada exige justificativa com pelo menos 15 caracteres.');
        }

        $seq = '1';
        $id = 'ID' . $code . $accessKey . str_pad($seq, 2, '0', STR_PAD_LEFT);
        $batchId = (string) random_int(100000000000000, 999999999999999);
        $timestamp = date('c');
        $justTag = $type === 'not_realized' ? '<xJust>' . htmlspecialchars((string)$justification, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</xJust>' : '';
        $eventDescription = htmlspecialchars(self::EVENT_DESCRIPTIONS[$type], ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<envEvento xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.00">
  <idLote>{$batchId}</idLote>
  <evento versao="1.00">
    <infEvento Id="{$id}">
      <cOrgao>91</cOrgao>
      <tpAmb>{$this->config['sefaz_environment']}</tpAmb>
      <CNPJ>{$cnpj}</CNPJ>
      <chNFe>{$accessKey}</chNFe>
      <dhEvento>{$timestamp}</dhEvento>
      <tpEvento>{$code}</tpEvento>
      <nSeqEvento>{$seq}</nSeqEvento>
      <verEvento>1.00</verEvento>
      <detEvento versao="1.00">
        <descEvento>{$eventDescription}</descEvento>
        {$justTag}
      </detEvento>
    </infEvento>
  </evento>
</envEvento>
XML;
    }

    private function parseResult(string $response): array
    {
        $dom = new DOMDocument();
        $dom->loadXML($response, LIBXML_NOCDATA | LIBXML_NOBLANKS);
        $xp = new DOMXPath($dom);
        return [
            'cStat' => $this->first($xp, '//*[local-name()="cStat"]') ?: '',
            'xMotivo' => $this->first($xp, '//*[local-name()="xMotivo"]') ?: '',
            'event_cStat' => $this->first($xp, '//*[local-name()="retEvento"]//*[local-name()="cStat"]') ?: '',
        ];
    }

    private function first(DOMXPath $xp, string $expr): ?string
    {
        $nodes = $xp->query($expr);
        return ($nodes && $nodes->length > 0) ? trim((string)$nodes->item(0)?->textContent) : null;
    }
}
