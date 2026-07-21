<?php
declare(strict_types=1);

namespace ControlS\Portal\Collectors;

final class NFeConnector extends AbstractFiscalCollector
{
    public function collectByAccessKey(string $accessKey): array
    {
        $company = $this->currentCompany();
        $this->certificates->assertMatchesCompany((int)$company['id'], (string)$company['cnpj']);
        $accessKey = preg_replace('/\D+/', '', $accessKey);
        if (strlen($accessKey) !== 44) {
            throw new \RuntimeException('Chave NF-e inválida.');
        }

        $settingPrefix = 'nfe_' . (int)$company['id'] . '_';
        $companyCnpj = preg_replace('/\D+/', '', (string)$company['cnpj']);
        $rootPrefix = $this->rootSettingPrefix($companyCnpj);
        $this->synchronizeRootDistributionState($settingPrefix, $rootPrefix, $company);
        $cooldownUntil = $this->effectiveCooldownUntil($settingPrefix, $rootPrefix);
        if ($cooldownUntil !== '' && strtotime($cooldownUntil) !== false && strtotime($cooldownUntil) > time()) {
            return [
                'created'=>0,
                'updated'=>0,
                'errors'=>0,
                'message'=>'NF-e/NFC-e ['.$company['company_name'].']: consulta por chave bloqueada localmente para evitar consumo indevido. Tente após ' . date('d/m/Y H:i', (int)strtotime($cooldownUntil)),
            ];
        }

        $requestXml = $this->buildConsChNFeXml($accessKey, $companyCnpj);
        $this->storage->appendLog(
            'collector_nfe_key_request.log',
            'Empresa: ' . $company['cnpj'] . PHP_EOL .
            'Chave: ' . $accessKey . PHP_EOL .
            $requestXml . PHP_EOL . str_repeat('-', 80)
        );

        $soap = $this->soapClient()->send(
            (string)$this->config['nfe_distribution_url'],
            (string)$this->config['nfe_distribution_action'],
            $requestXml,
            'http://www.portalfiscal.inf.br/nfe/wsdl/NFeDistribuicaoDFe',
            'nfeDistDFeInteresse',
            'nfeDadosMsg',
            (int)$company['id']
        );

        $result = $this->processDistributionResponse($soap, 'NFE');
        if ((string)($result['cStat'] ?? '') === '641') {
            $alternateCnpj = $this->alternateInterestedCnpjForOwnIssuedKey($accessKey, $companyCnpj);
            if ($alternateCnpj !== null) {
                $requestXml = $this->buildConsChNFeXml($accessKey, $alternateCnpj);
                $this->storage->appendLog(
                    'collector_nfe_key_request.log',
                    'Retry 641 usando outro CNPJ da mesma raiz.' . PHP_EOL .
                    'Empresa selecionada: ' . $company['cnpj'] . PHP_EOL .
                    'CNPJ interessado na consulta: ' . $alternateCnpj . PHP_EOL .
                    'Chave: ' . $accessKey . PHP_EOL .
                    $requestXml . PHP_EOL . str_repeat('-', 80)
                );
                $soap = $this->soapClient()->send(
                    (string)$this->config['nfe_distribution_url'],
                    (string)$this->config['nfe_distribution_action'],
                    $requestXml,
                    'http://www.portalfiscal.inf.br/nfe/wsdl/NFeDistribuicaoDFe',
                    'nfeDistDFeInteresse',
                    'nfeDadosMsg',
                    (int)$company['id']
                );
                $result = $this->processDistributionResponse($soap, 'NFE');
            }
        }
        $message = trim(($result['cStat'] ?? '') . ' ' . ($result['xMotivo'] ?? ''));
        if ((string)$result['cStat'] === '656') {
            $until = date('c', time() + 3600);
            $this->repo->setSetting($settingPrefix . 'cooldown_until', $until);
            $this->repo->setSetting($rootPrefix . 'cooldown_until', $until);
        }
        $this->storage->appendLog('collector_nfe_key.log', 'NF-e [' . $company['cnpj'] . '] chave=' . $accessKey . ' cStat=' . $result['cStat'] . ' mensagem=' . $message);

        return [
            'created'=>(int)$result['created'],
            'updated'=>(int)$result['updated'],
            'errors'=>(int)$result['errors'],
            'message'=>'NF-e chave ' . $accessKey . ': ' . ($message ?: 'sem retorno'),
        ];
    }

    public function queryProtocolStatus(string $accessKey): array
    {
        $company = $this->currentCompany();
        $this->certificates->assertMatchesCompany((int)$company['id'], (string)$company['cnpj']);
        $accessKey = preg_replace('/\D+/', '', $accessKey);
        if (strlen($accessKey) !== 44) {
            throw new \RuntimeException('Chave NF-e inválida.');
        }

        $requestXml = $this->buildConsSitNFeXml($accessKey);
        $url = $this->consultaProtocoloUrl($accessKey);
        $soap = $this->soapClient()->send(
            $url,
            (string)$this->config['nfe_consulta_protocolo_action'],
            $requestXml,
            'http://www.portalfiscal.inf.br/nfe/wsdl/NFeConsultaProtocolo4',
            'nfeConsultaNF',
            'nfeDadosMsg',
            (int)$company['id']
        );
        $status = $this->parseProtocolStatusResponse($soap);
        $updated = $this->repo->applyNFeProtocolStatus($accessKey, $status, (int)$company['id']);
        $message = trim(($status['cStat'] ?? '') . ' ' . ($status['xMotivo'] ?? ''));
        $this->storage->appendLog('collector_nfe_status.log', 'NF-e situação [' . $company['cnpj'] . '] chave=' . $accessKey . ' URL=' . $url . ' retorno=' . $message);
        return [
            'updated' => $updated,
            'errors' => 0,
            'cStat' => (string)($status['cStat'] ?? ''),
            'message' => 'Situação NF-e ' . $accessKey . ': ' . ($message ?: 'sem retorno'),
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

        $settingPrefix = 'nfe_' . (int)$company['id'] . '_';
        $rootPrefix = $this->rootSettingPrefix($companyCnpj);
        $syncMessage = $this->synchronizeRootDistributionState($settingPrefix, $rootPrefix, $company);
        $ultNSU = str_pad(preg_replace('/\D+/', '', (string)$this->repo->getSetting($settingPrefix . 'ult_nsu', '0')), 15, '0', STR_PAD_LEFT);
        $knownMaxNSU = str_pad(preg_replace('/\D+/', '', (string)$this->repo->getSetting($settingPrefix . 'max_nsu', '0')), 15, '0', STR_PAD_LEFT);
        $cooldownUntil = $this->effectiveCooldownUntil($settingPrefix, $rootPrefix);
        if ($cooldownUntil !== '' && strtotime($cooldownUntil) !== false && strtotime($cooldownUntil) > time()) {
            return [
                'created'=>0,
                'updated'=>0,
                'errors'=>0,
                'message'=>'NF-e/NFC-e ['.$company['company_name'].']: consulta bloqueada localmente para evitar consumo indevido. Tente após ' . date('d/m/Y H:i', (int)strtotime($cooldownUntil)),
            ];
        }

        $lastCheckAt = (string)$this->repo->getSetting($settingPrefix . 'last_check_at', '');
        if ($knownMaxNSU !== '000000000000000' && $ultNSU >= $knownMaxNSU && $lastCheckAt !== '' && strtotime($lastCheckAt) !== false && (time() - (int)strtotime($lastCheckAt)) < 3600) {
            $until = (int)strtotime($lastCheckAt) + 3600;
            return [
                'created'=>0,
                'updated'=>0,
                'errors'=>0,
                'message'=>'NF-e/NFC-e ['.$company['company_name'].']: consulta bloqueada localmente porque ultNSU já alcançou maxNSU. Tente após ' . date('d/m/Y H:i', $until),
            ];
        }

        $maxLoops = max(1, (int)($this->config['sefaz_max_loops'] ?? 8));

        $created = 0; $updated = 0; $errors = 0; $messages = [];
        if ($syncMessage !== null) {
            $messages[] = $syncMessage;
        }
        for ($i = 0; $i < $maxLoops; $i++) {
            $requestXml = $this->buildDistDFeXml($companyCnpj, $ultNSU);
            $this->storage->appendLog(
                'collector_nfe_request.log',
                'Empresa: ' . $company['cnpj'] . PHP_EOL .
                'URL: ' . $this->config['nfe_distribution_url'] . PHP_EOL .
                'ultNSU: ' . $ultNSU . PHP_EOL .
                $requestXml . PHP_EOL . str_repeat('-', 80)
            );

            $soap = $this->soapClient()->send(
                (string)$this->config['nfe_distribution_url'],
                (string)$this->config['nfe_distribution_action'],
                $requestXml,
                'http://www.portalfiscal.inf.br/nfe/wsdl/NFeDistribuicaoDFe',
                'nfeDistDFeInteresse',
                'nfeDadosMsg',
                (int)$company['id']
            );

            $result = $this->processDistributionResponse($soap, 'NFE');
            $created += $result['created']; $updated += $result['updated']; $errors += $result['errors'];
            $messages[] = trim(($result['cStat'] ?? '') . ' ' . ($result['xMotivo'] ?? ''));
            $this->repo->setSetting($settingPrefix . 'last_check_at', date('c'));

            $resultUlt = str_pad(preg_replace('/\D+/', '', (string)$result['ultNSU']), 15, '0', STR_PAD_LEFT);
            $resultMax = str_pad(preg_replace('/\D+/', '', (string)$result['maxNSU']), 15, '0', STR_PAD_LEFT);
            if (in_array($result['cStat'], ['137', '138'], true) && $resultUlt !== '000000000000000') {
                $this->repo->setSetting($settingPrefix . 'ult_nsu', $resultUlt);
                $this->repo->setSetting($rootPrefix . 'ult_nsu', $resultUlt);
            }
            if (in_array($result['cStat'], ['137', '138'], true) && $resultMax !== '000000000000000') {
                $this->repo->setSetting($settingPrefix . 'max_nsu', $resultMax);
                $this->repo->setSetting($rootPrefix . 'max_nsu', $resultMax);
            }
            if ((string)$result['cStat'] === '656') {
                $until = date('c', time() + 3600);
                $this->repo->setSetting($settingPrefix . 'cooldown_until', $until);
                $this->repo->setSetting($rootPrefix . 'cooldown_until', $until);
            } elseif (in_array($result['cStat'], ['137', '138'], true)) {
                $this->repo->setSetting($settingPrefix . 'cooldown_until', '');
                $this->repo->setSetting($rootPrefix . 'cooldown_until', '');
            }
            $this->repo->setSetting($rootPrefix . 'last_check_at', date('c'));
            $this->storage->appendLog('collector_nfe.log', 'NF-e/NFC-e [' . $company['cnpj'] . '] cStat=' . $result['cStat'] . ' ultNSU=' . $result['ultNSU'] . ' maxNSU=' . $result['maxNSU']);

            if ($result['ultNSU'] === $ultNSU || $result['ultNSU'] === $result['maxNSU'] || !in_array($result['cStat'], ['138', '137'], true)) {
                break;
            }
            $ultNSU = str_pad((string)$result['ultNSU'], 15, '0', STR_PAD_LEFT);
        }

        return ['created'=>$created,'updated'=>$updated,'errors'=>$errors,'message'=>'NF-e/NFC-e ['.$company['company_name'].']: ' . ($messages ? end($messages) : 'sem retorno')];
    }

    private function rootSettingPrefix(string $companyCnpj): string
    {
        $root = substr(preg_replace('/\D+/', '', $companyCnpj), 0, 8);
        return 'nfe_root_' . ($root !== '' ? $root : 'sem_cnpj') . '_';
    }

    private function effectiveCooldownUntil(string $companyPrefix, string $rootPrefix): string
    {
        $companyCooldown = (string)$this->repo->getSetting($companyPrefix . 'cooldown_until', '');
        $rootCooldown = (string)$this->repo->getSetting($rootPrefix . 'cooldown_until', '');
        $companyTime = strtotime($companyCooldown) ?: 0;
        $rootTime = strtotime($rootCooldown) ?: 0;
        return $rootTime > $companyTime ? $rootCooldown : $companyCooldown;
    }

    private function synchronizeRootDistributionState(string $companyPrefix, string $rootPrefix, array $company): ?string
    {
        // O cursor de NSU da NF-e deve permanecer individual por CNPJ.
        // Matriz e filial podem compartilhar certificado/cooldown, mas nao devem herdar ultNSU/maxNSU,
        // pois isso pode fazer uma filial pular documentos que so aparecem na fila dela.
        return null;
    }

    private function knownRootStateFromCompanies(array $company): array
    {
        $companyRoot = substr(preg_replace('/\D+/', '', (string)($company['cnpj'] ?? '')), 0, 8);
        $bestUlt = '000000000000000';
        $bestMax = '000000000000000';
        foreach ($this->repo->activeCompanies() as $candidate) {
            $candidateRoot = substr(preg_replace('/\D+/', '', (string)($candidate['cnpj'] ?? '')), 0, 8);
            if ($candidateRoot === '' || $candidateRoot !== $companyRoot) {
                continue;
            }
            $prefix = 'nfe_' . (int)$candidate['id'] . '_';
            $candidateUlt = $this->formattedNsu((string)$this->repo->getSetting($prefix . 'ult_nsu', '0'));
            $candidateMax = $this->formattedNsu((string)$this->repo->getSetting($prefix . 'max_nsu', '0'));
            if ($candidateUlt > $bestUlt) {
                $bestUlt = $candidateUlt;
                $bestMax = $candidateMax;
            }
        }
        return [$bestUlt, $bestMax];
    }

    private function formattedNsu(string $value): string
    {
        return str_pad(preg_replace('/\D+/', '', $value), 15, '0', STR_PAD_LEFT);
    }

    private function alternateInterestedCnpjForOwnIssuedKey(string $accessKey, string $companyCnpj): ?string
    {
        $issuerCnpj = substr($accessKey, 6, 14);
        $companyCnpj = preg_replace('/\D+/', '', $companyCnpj);
        if ($issuerCnpj === '' || $issuerCnpj !== $companyCnpj) {
            return null;
        }

        $root = substr($companyCnpj, 0, 8);
        foreach ($this->repo->activeCompanies() as $candidate) {
            $candidateCnpj = preg_replace('/\D+/', '', (string)($candidate['cnpj'] ?? ''));
            if ($candidateCnpj !== '' && $candidateCnpj !== $companyCnpj && substr($candidateCnpj, 0, 8) === $root) {
                return $candidateCnpj;
            }
        }

        return null;
    }

    private function buildDistDFeXml(string $cnpj, string $ultNSU): string
    {
        $tpAmb = (string)$this->config['sefaz_environment'];
        $cUFAutor = (string)$this->config['sefaz_uf_author'];
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<distDFeInt xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.01">
  <tpAmb>{$tpAmb}</tpAmb>
  <cUFAutor>{$cUFAutor}</cUFAutor>
  <CNPJ>{$cnpj}</CNPJ>
  <distNSU>
    <ultNSU>{$ultNSU}</ultNSU>
  </distNSU>
</distDFeInt>
XML;
    }

    private function buildConsChNFeXml(string $accessKey, string $cnpj): string
    {
        $tpAmb = (string)$this->config['sefaz_environment'];
        $cUFAutor = (string)$this->config['sefaz_uf_author'];
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<distDFeInt xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.01">
  <tpAmb>{$tpAmb}</tpAmb>
  <cUFAutor>{$cUFAutor}</cUFAutor>
  <CNPJ>{$cnpj}</CNPJ>
  <consChNFe>
    <chNFe>{$accessKey}</chNFe>
  </consChNFe>
</distDFeInt>
XML;
    }

    private function buildConsSitNFeXml(string $accessKey): string
    {
        $tpAmb = (string)$this->config['sefaz_environment'];
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<consSitNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
  <tpAmb>{$tpAmb}</tpAmb>
  <xServ>CONSULTAR</xServ>
  <chNFe>{$accessKey}</chNFe>
</consSitNFe>
XML;
    }

    private function consultaProtocoloUrl(string $accessKey): string
    {
        $configured = trim((string)($this->config['nfe_consulta_protocolo_url'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }
        $environment = (string)($this->config['sefaz_environment'] ?? '1');
        $uf = substr($accessKey, 0, 2);
        $production = [
            '41' => 'https://nfe.sefa.pr.gov.br/nfe/NFeConsultaProtocolo4',
        ];
        $homologation = [
            '41' => 'https://homologacao.nfe.sefa.pr.gov.br/nfe/NFeConsultaProtocolo4',
        ];
        $map = $environment === '2' ? $homologation : $production;
        if (!empty($map[$uf])) {
            return $map[$uf];
        }
        throw new \RuntimeException('URL de consulta de protocolo NF-e não configurada para a UF da chave ' . $uf . '. Configure NFE_CONSULTA_PROTOCOLO_URL no ambiente.');
    }

    private function parseProtocolStatusResponse(string $soap): array
    {
        $xml = $this->extractReturnXml($soap);
        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
            return ['cStat' => '', 'xMotivo' => 'Retorno inválido da consulta de situação.'];
        }
        $xp = new \DOMXPath($dom);
        $text = static function (string $query, ?\DOMNode $context = null) use ($xp): string {
            $nodes = $context ? $xp->query($query, $context) : $xp->query($query);
            return ($nodes && $nodes->length > 0) ? trim((string)$nodes->item(0)?->textContent) : '';
        };

        $events = $xp->query('//*[local-name()="procEventoNFe" or local-name()="retEvento" or local-name()="evento"]');
        foreach ($events ?: [] as $event) {
            $eventType = $text('.//*[local-name()="tpEvento"]', $event);
            $eventName = $text('.//*[local-name()="xEvento"]', $event);
            $eventStatus = $text('.//*[local-name()="retEvento"]/*[local-name()="infEvento"]/*[local-name()="cStat"]', $event);
            if ($eventStatus === '') {
                $eventStatus = $text('.//*[local-name()="infEvento"]/*[local-name()="cStat"]', $event);
            }
            $isCancellation = $eventType === '110111' || str_contains(strtolower($eventName), 'cancel');
            $isAccepted = in_array($eventStatus, ['101', '135', '136', '155'], true);
            if ($isCancellation && ($isAccepted || $eventStatus === '')) {
                $reason = $text('.//*[local-name()="retEvento"]/*[local-name()="infEvento"]/*[local-name()="xMotivo"]', $event);
                if ($reason === '') {
                    $reason = $text('.//*[local-name()="infEvento"]/*[local-name()="xMotivo"]', $event);
                }
                return [
                    'cStat' => '101',
                    'xMotivo' => $reason !== '' ? $reason : 'Cancelamento localizado na consulta de protocolo.',
                    'nProt' => $text('.//*[local-name()="retEvento"]/*[local-name()="infEvento"]/*[local-name()="nProt"]', $event)
                        ?: $text('.//*[local-name()="infEvento"]/*[local-name()="nProt"]', $event),
                    'dhRecbto' => $text('.//*[local-name()="retEvento"]/*[local-name()="infEvento"]/*[local-name()="dhRegEvento"]', $event)
                        ?: $text('.//*[local-name()="infEvento"]/*[local-name()="dhRegEvento"]', $event),
                    'event_cStat' => $eventStatus,
                    'event_type' => $eventType,
                ];
            }
        }

        $retConsSit = $xp->query('//*[local-name()="retConsSitNFe"]')->item(0);
        $context = $retConsSit instanceof \DOMNode ? $retConsSit : null;

        return [
            'cStat' => $context ? $text('./*[local-name()="cStat"]', $context) : $text('//*[local-name()="cStat"]'),
            'xMotivo' => $context ? $text('./*[local-name()="xMotivo"]', $context) : $text('//*[local-name()="xMotivo"]'),
            'nProt' => $text('//*[local-name()="protNFe"]/*[local-name()="infProt"]/*[local-name()="nProt"]')
                ?: $text('//*[local-name()="nProt"]'),
            'dhRecbto' => $text('//*[local-name()="protNFe"]/*[local-name()="infProt"]/*[local-name()="dhRecbto"]')
                ?: $text('//*[local-name()="dhRecbto"]'),
        ];
    }
}
