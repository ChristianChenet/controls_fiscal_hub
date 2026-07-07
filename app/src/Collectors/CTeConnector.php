<?php
declare(strict_types=1);

namespace ControlS\Portal\Collectors;

final class CTeConnector extends AbstractFiscalCollector
{
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
}
