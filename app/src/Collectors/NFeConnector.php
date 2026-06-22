<?php
declare(strict_types=1);

namespace ControlS\Portal\Collectors;

final class NFeConnector extends AbstractFiscalCollector
{
    public function collectByAccessKey(string $accessKey): array
    {
        $company = $this->currentCompany();
        $this->certificates->requireActive((int)$company['id']);
        $accessKey = preg_replace('/\D+/', '', $accessKey);
        if (strlen($accessKey) !== 44) {
            throw new \RuntimeException('Chave NF-e inválida.');
        }

        $settingPrefix = 'nfe_' . (int)$company['id'] . '_';
        $cooldownUntil = (string)$this->repo->getSetting($settingPrefix . 'cooldown_until', '');
        if ($cooldownUntil !== '' && strtotime($cooldownUntil) !== false && strtotime($cooldownUntil) > time()) {
            return [
                'created'=>0,
                'updated'=>0,
                'errors'=>0,
                'message'=>'NF-e/NFC-e ['.$company['company_name'].']: consulta por chave bloqueada localmente para evitar consumo indevido. Tente após ' . date('d/m/Y H:i', (int)strtotime($cooldownUntil)),
            ];
        }

        $requestXml = $this->buildConsChNFeXml($accessKey);
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
            'nfeDadosMsg'
        );

        $result = $this->processDistributionResponse($soap, 'NFE');
        $message = trim(($result['cStat'] ?? '') . ' ' . ($result['xMotivo'] ?? ''));
        if ((string)$result['cStat'] === '656') {
            $this->repo->setSetting($settingPrefix . 'cooldown_until', date('c', time() + 3600));
        }
        $this->storage->appendLog('collector_nfe_key.log', 'NF-e [' . $company['cnpj'] . '] chave=' . $accessKey . ' cStat=' . $result['cStat'] . ' mensagem=' . $message);

        return [
            'created'=>(int)$result['created'],
            'updated'=>(int)$result['updated'],
            'errors'=>(int)$result['errors'],
            'message'=>'NF-e chave ' . $accessKey . ': ' . ($message ?: 'sem retorno'),
        ];
    }

    public function collect(): array
    {
        $company = $this->currentCompany();
        $this->certificates->requireActive((int)$company['id']);
        $companyCnpj = preg_replace('/\D+/', '', (string)$company['cnpj']);
        if ($companyCnpj === '') {
            throw new \RuntimeException('CNPJ da empresa não informado.');
        }

        $settingPrefix = 'nfe_' . (int)$company['id'] . '_';
        $ultNSU = str_pad(preg_replace('/\D+/', '', (string)$this->repo->getSetting($settingPrefix . 'ult_nsu', '0')), 15, '0', STR_PAD_LEFT);
        $knownMaxNSU = str_pad(preg_replace('/\D+/', '', (string)$this->repo->getSetting($settingPrefix . 'max_nsu', '0')), 15, '0', STR_PAD_LEFT);
        $cooldownUntil = (string)$this->repo->getSetting($settingPrefix . 'cooldown_until', '');
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
                'nfeDadosMsg'
            );

            $result = $this->processDistributionResponse($soap, 'NFE');
            $created += $result['created']; $updated += $result['updated']; $errors += $result['errors'];
            $messages[] = trim(($result['cStat'] ?? '') . ' ' . ($result['xMotivo'] ?? ''));
            $this->repo->setSetting($settingPrefix . 'last_check_at', date('c'));

            $resultUlt = str_pad(preg_replace('/\D+/', '', (string)$result['ultNSU']), 15, '0', STR_PAD_LEFT);
            $resultMax = str_pad(preg_replace('/\D+/', '', (string)$result['maxNSU']), 15, '0', STR_PAD_LEFT);
            if (in_array($result['cStat'], ['137', '138'], true) && $resultUlt !== '000000000000000') {
                $this->repo->setSetting($settingPrefix . 'ult_nsu', $resultUlt);
            }
            if (in_array($result['cStat'], ['137', '138'], true) && $resultMax !== '000000000000000') {
                $this->repo->setSetting($settingPrefix . 'max_nsu', $resultMax);
            }
            if ((string)$result['cStat'] === '656') {
                $this->repo->setSetting($settingPrefix . 'cooldown_until', date('c', time() + 3600));
            } elseif (in_array($result['cStat'], ['137', '138'], true)) {
                $this->repo->setSetting($settingPrefix . 'cooldown_until', '');
            }
            $this->storage->appendLog('collector_nfe.log', 'NF-e/NFC-e [' . $company['cnpj'] . '] cStat=' . $result['cStat'] . ' ultNSU=' . $result['ultNSU'] . ' maxNSU=' . $result['maxNSU']);

            if ($result['ultNSU'] === $ultNSU || $result['ultNSU'] === $result['maxNSU'] || !in_array($result['cStat'], ['138', '137'], true)) {
                break;
            }
            $ultNSU = str_pad((string)$result['ultNSU'], 15, '0', STR_PAD_LEFT);
        }

        return ['created'=>$created,'updated'=>$updated,'errors'=>$errors,'message'=>'NF-e/NFC-e ['.$company['company_name'].']: ' . ($messages ? end($messages) : 'sem retorno')];
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

    private function buildConsChNFeXml(string $accessKey): string
    {
        $tpAmb = (string)$this->config['sefaz_environment'];
        $cUFAutor = (string)$this->config['sefaz_uf_author'];
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<distDFeInt xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.01">
  <tpAmb>{$tpAmb}</tpAmb>
  <cUFAutor>{$cUFAutor}</cUFAutor>
  <consChNFe>
    <chNFe>{$accessKey}</chNFe>
  </consChNFe>
</distDFeInt>
XML;
    }
}
