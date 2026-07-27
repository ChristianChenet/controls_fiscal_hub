<?php
declare(strict_types=1);

namespace ControlS\Portal\Collectors;

final class CTeConnector extends AbstractFiscalCollector
{
    public function queryProtocolStatus(string $accessKey): array
    {
        $company = $this->currentCompany();
        $this->certificates->assertMatchesCompany((int)$company['id'], (string)$company['cnpj']);
        $accessKey = preg_replace('/\D+/', '', $accessKey);
        if (strlen($accessKey) !== 44) {
            throw new \RuntimeException('Chave CT-e invalida.');
        }

        $requestXml = $this->buildConsSitCTeXml($accessKey);
        $url = $this->consultaProtocoloUrl($accessKey);
        $soap = $this->soapClient()->send(
            $url,
            (string)($this->config['cte_consulta_protocolo_action'] ?? 'http://www.portalfiscal.inf.br/cte/wsdl/CTeConsultaV4/cteConsultaCT'),
            $requestXml,
            'http://www.portalfiscal.inf.br/cte/wsdl/CTeConsultaV4',
            '',
            'cteDadosMsg',
            (int)$company['id']
        );
        $status = $this->parseProtocolStatusResponse($soap);
        $updated = $this->repo->applyCTeProtocolStatus($accessKey, $status, (int)$company['id']);
        $message = trim(($status['cStat'] ?? '') . ' ' . ($status['xMotivo'] ?? ''));
        $this->storage->appendLog('collector_cte_status.log', 'CT-e situacao [' . $company['cnpj'] . '] chave=' . $accessKey . ' URL=' . $url . ' retorno=' . $message);

        return [
            'updated' => $updated,
            'errors' => 0,
            'cStat' => (string)($status['cStat'] ?? ''),
            'message' => 'Situacao CT-e ' . $accessKey . ': ' . ($message ?: 'sem retorno'),
        ];
    }

    public function collect(): array
    {
        $company = $this->currentCompany();
        $this->certificates->assertMatchesCompany((int)$company['id'], (string)$company['cnpj']);
        $companyCnpj = preg_replace('/\D+/', '', (string)$company['cnpj']);
        if ($companyCnpj === '') {
            throw new \RuntimeException('CNPJ da empresa não informado.');
        }

        $settingPrefix = 'cte_' . (int)$company['id'] . '_';
        $ultNSU = str_pad(preg_replace('/\D+/', '', (string)$this->repo->getSetting($settingPrefix . 'ult_nsu', '0')), 15, '0', STR_PAD_LEFT);
        $maxLoops = max(1, (int)($this->config['sefaz_max_loops'] ?? 8));

        $created = 0; $updated = 0; $errors = 0; $messages = [];
        for ($i = 0; $i < $maxLoops; $i++) {
            $requestXml = $this->buildDistDFeXml($companyCnpj, $ultNSU);
            $this->storage->appendLog(
                'collector_cte_request.log',
                'Empresa: ' . $company['cnpj'] . PHP_EOL .
                'URL: ' . $this->config['cte_distribution_url'] . PHP_EOL .
                'ultNSU: ' . $ultNSU . PHP_EOL .
                $requestXml . PHP_EOL . str_repeat('-', 80)
            );

            $soap = $this->soapClient()->send(
                (string)$this->config['cte_distribution_url'],
                (string)$this->config['cte_distribution_action'],
                $requestXml,
                'http://www.portalfiscal.inf.br/cte/wsdl/CTeDistribuicaoDFe',
                'cteDistDFeInteresse',
                'cteDadosMsg',
                (int)$company['id']
            );

            $result = $this->processDistributionResponse($soap, 'CTE');
            $created += $result['created']; $updated += $result['updated']; $errors += $result['errors'];
            $messages[] = trim(($result['cStat'] ?? '') . ' ' . ($result['xMotivo'] ?? ''));

            $resultUlt = str_pad(preg_replace('/\D+/', '', (string)$result['ultNSU']), 15, '0', STR_PAD_LEFT);
            $resultMax = str_pad(preg_replace('/\D+/', '', (string)$result['maxNSU']), 15, '0', STR_PAD_LEFT);
            if (in_array($result['cStat'], ['137', '138'], true) && $resultUlt !== '000000000000000') {
                $this->repo->setSetting($settingPrefix . 'ult_nsu', $resultUlt);
            }
            if (in_array($result['cStat'], ['137', '138'], true) && $resultMax !== '000000000000000') {
                $this->repo->setSetting($settingPrefix . 'max_nsu', $resultMax);
            }
            $this->storage->appendLog('collector_cte.log', 'CT-e [' . $company['cnpj'] . '] cStat=' . $result['cStat'] . ' ultNSU=' . $result['ultNSU'] . ' maxNSU=' . $result['maxNSU']);

            if ($result['ultNSU'] === $ultNSU || $result['ultNSU'] === $result['maxNSU'] || !in_array($result['cStat'], ['138', '137'], true)) {
                break;
            }
            $ultNSU = str_pad((string)$result['ultNSU'], 15, '0', STR_PAD_LEFT);
        }

        return ['created'=>$created,'updated'=>$updated,'errors'=>$errors,'message'=>'CT-e ['.$company['company_name'].']: ' . ($messages ? end($messages) : 'sem retorno')];
    }

    private function buildDistDFeXml(string $cnpj, string $ultNSU): string
    {
        $tpAmb = (string)$this->config['sefaz_environment'];
        $cUFAutor = (string)$this->config['sefaz_uf_author'];
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<distDFeInt xmlns="http://www.portalfiscal.inf.br/cte" versao="1.00">
  <tpAmb>{$tpAmb}</tpAmb>
  <cUFAutor>{$cUFAutor}</cUFAutor>
  <CNPJ>{$cnpj}</CNPJ>
  <distNSU>
    <ultNSU>{$ultNSU}</ultNSU>
  </distNSU>
</distDFeInt>
XML;
    }

    private function buildConsSitCTeXml(string $accessKey): string
    {
        $tpAmb = (string)$this->config['sefaz_environment'];
        return '<consSitCTe xmlns="http://www.portalfiscal.inf.br/cte" versao="4.00"><tpAmb>'
            . $tpAmb
            . '</tpAmb><xServ>CONSULTAR</xServ><chCTe>'
            . $accessKey
            . '</chCTe></consSitCTe>';
    }

    private function consultaProtocoloUrl(string $accessKey): string
    {
        $configured = trim((string)($this->config['cte_consulta_protocolo_url'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }
        $environment = (string)($this->config['sefaz_environment'] ?? '1');
        $uf = substr($accessKey, 0, 2);
        $production = [
            '35' => 'https://nfe.fazenda.sp.gov.br/CTeWS/WS/CTeConsultaV4.asmx',
            '41' => 'https://cte.fazenda.pr.gov.br/cte4/CTeConsultaV4',
        ];
        $homologation = [
            '35' => 'https://homologacao.nfe.fazenda.sp.gov.br/CTeWS/WS/CTeConsultaV4.asmx',
            '41' => 'https://homologacao.cte.fazenda.pr.gov.br/cte4/CTeConsultaV4',
        ];
        $map = $environment === '2' ? $homologation : $production;
        if (!empty($map[$uf])) {
            return $map[$uf];
        }
        return $environment === '2'
            ? 'https://cte-homologacao.svrs.rs.gov.br/ws/CTeConsultaV4/CTeConsultaV4.asmx'
            : 'https://cte.svrs.rs.gov.br/ws/CTeConsultaV4/CTeConsultaV4.asmx';
    }

    private function parseProtocolStatusResponse(string $soap): array
    {
        $xml = $this->extractReturnXml($soap);
        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
            return ['cStat' => '', 'xMotivo' => 'Retorno invalido da consulta de situacao CT-e.'];
        }
        $xp = new \DOMXPath($dom);
        $text = static function (string $query, ?\DOMNode $context = null) use ($xp): string {
            $nodes = $context ? $xp->query($query, $context) : $xp->query($query);
            return ($nodes && $nodes->length > 0) ? trim((string)$nodes->item(0)?->textContent) : '';
        };

        $events = $xp->query('//*[local-name()="procEventoCTe" or local-name()="retEventoCTe" or local-name()="eventoCTe"]');
        foreach ($events ?: [] as $event) {
            $eventType = $text('.//*[local-name()="tpEvento"]', $event);
            $eventName = $text('.//*[local-name()="xEvento"]', $event);
            $eventStatus = $text('.//*[local-name()="retEventoCTe"]/*[local-name()="infEvento"]/*[local-name()="cStat"]', $event);
            if ($eventStatus === '') {
                $eventStatus = $text('.//*[local-name()="infEvento"]/*[local-name()="cStat"]', $event);
            }
            $isCancellation = $eventType === '110111' || str_contains(strtolower($eventName), 'cancel');
            $isAccepted = in_array($eventStatus, ['101', '135', '136', '155'], true);
            if ($isCancellation && ($isAccepted || $eventStatus === '')) {
                $reason = $text('.//*[local-name()="retEventoCTe"]/*[local-name()="infEvento"]/*[local-name()="xMotivo"]', $event);
                if ($reason === '') {
                    $reason = $text('.//*[local-name()="infEvento"]/*[local-name()="xMotivo"]', $event);
                }
                return [
                    'cStat' => '101',
                    'xMotivo' => $reason !== '' ? $reason : 'Cancelamento localizado na consulta de protocolo CT-e.',
                    'nProt' => $text('.//*[local-name()="retEventoCTe"]/*[local-name()="infEvento"]/*[local-name()="nProt"]', $event)
                        ?: $text('.//*[local-name()="infEvento"]/*[local-name()="nProt"]', $event),
                    'dhRecbto' => $text('.//*[local-name()="retEventoCTe"]/*[local-name()="infEvento"]/*[local-name()="dhRegEvento"]', $event)
                        ?: $text('.//*[local-name()="infEvento"]/*[local-name()="dhRegEvento"]', $event),
                    'event_cStat' => $eventStatus,
                    'event_type' => $eventType,
                ];
            }
        }

        $retConsSit = $xp->query('//*[local-name()="retConsSitCTe"]')->item(0);
        $context = $retConsSit instanceof \DOMNode ? $retConsSit : null;
        return [
            'cStat' => $context ? $text('./*[local-name()="cStat"]', $context) : $text('//*[local-name()="cStat"]'),
            'xMotivo' => $context ? $text('./*[local-name()="xMotivo"]', $context) : $text('//*[local-name()="xMotivo"]'),
            'nProt' => $text('//*[local-name()="protCTe"]/*[local-name()="infProt"]/*[local-name()="nProt"]')
                ?: $text('//*[local-name()="nProt"]'),
            'dhRecbto' => $text('//*[local-name()="protCTe"]/*[local-name()="infProt"]/*[local-name()="dhRecbto"]')
                ?: $text('//*[local-name()="dhRecbto"]'),
        ];
    }
}
