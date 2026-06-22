<?php
declare(strict_types=1);

namespace ControlS\Portal\Collectors;

final class NFSeNationalConnector extends AbstractFiscalCollector
{
    public function collect(): array
    {
        $company = $this->currentCompany();
        $this->certificates->requireActive((int)$company['id']);
        $companyCnpj = preg_replace('/\D+/', '', (string) $company['cnpj']);
        if ($companyCnpj === '') {
            throw new \RuntimeException('Informe o CNPJ da empresa nas configuracoes.');
        }

        $settingPrefix = 'nfse_' . (int)$company['id'] . '_';
        $cooldownUntil = (string)$this->repo->getSetting($settingPrefix . 'cooldown_until', '');
        if ($cooldownUntil !== '' && strtotime($cooldownUntil) > time()) {
            throw new \RuntimeException('NFS-e Nacional bloqueada temporariamente para evitar excesso de requisicoes ao ADN. Tente novamente apos ' . date('H:i', strtotime($cooldownUntil)) . '.');
        }

        $baseUrl = rtrim((string) $this->config['nfse_base_url'], '/');
        $path = trim((string) $this->config['nfse_distribution_path']);
        $lastNsu = preg_replace('/\D+/', '', (string) $this->repo->getSetting($settingPrefix . 'ult_nsu', '0'));
        $lastNsu = $lastNsu === '' ? '0' : $lastNsu;
        $limit = max(1, min(10, (int)($this->repo->getSetting('auto_nfse_nsu_limit', (string)($this->config['nfse_page_size'] ?? 10)))));
        $headers = [
            'Accept' => 'application/json, application/xml, text/xml',
            'User-Agent' => $this->config['sefaz_user_agent'] ?? 'ControlSPortalFiscal/3.0',
        ];

        $useCertificate = (($this->config['nfse_auth_type'] ?? 'certificate') === 'certificate');
        if (($this->config['nfse_auth_type'] ?? '') === 'token' && !empty($this->config['nfse_token'])) {
            $headers['Authorization'] = 'Bearer ' . $this->config['nfse_token'];
            $useCertificate = false;
        }

        $created = 0;
        $updated = 0;
        $itemsCount = 0;
        $checked = 0;
        $currentNsu = $lastNsu;
        $firstChecked = null;
        $emptyResponses = 0;
        $notes = [];

        while ($checked < $limit) {
            $requestNsu = $this->incrementNsu($currentNsu);
            $firstChecked ??= $requestNsu;
            $url = $this->buildUrl($baseUrl, $path, $requestNsu, $companyCnpj);
            try {
                $response = $this->httpClient->get($url, $headers, $useCertificate, (int) ($this->config['sefaz_timeout'] ?? 60), (int)$company['id']);
            } catch (\RuntimeException $e) {
                if (str_contains($e->getMessage(), 'HTTP 429')) {
                    $until = date('c', time() + 3600);
                    $this->repo->setSetting($settingPrefix . 'cooldown_until', $until);
                    $this->repo->setSetting($settingPrefix . 'last_error', 'HTTP 429 Too Many Requests em ' . date('c'));
                    throw new \RuntimeException('NFS-e Nacional bloqueada temporariamente pelo ADN por excesso de requisicoes. O portal pausou novas tentativas ate ' . date('H:i', strtotime($until)) . '.');
                }
                throw $e;
            }
            $parsedResponse = $this->parseResponse($response);
            $items = $parsedResponse['items'];
            $checked++;
            $currentNsu = $requestNsu;

            if (!$items) {
                $emptyResponses++;
                if (count($notes) < 3) {
                    $notes[] = 'NSU ' . $requestNsu . ' sem XML reconhecido. Resposta: ' . $this->responseSnippet($response);
                }
                continue;
            }

            foreach ($items as $item) {
                $xml = $this->xmlFromItem($item);
                if ($xml === '') {
                    continue;
                }

                $itemsCount++;
                $parsed = $this->parser->parse($xml);
                $parsed['source'] = 'nfse_nacional_api';
                $parsed['schema_name'] = 'nfse_api';
                $saved = $this->storage->saveXml(
                    $parsed['doc_type'],
                    (string) ($parsed['issue_date'] ?? ''),
                    $xml,
                    null,
                    (string)$company['cnpj'],
                    (string)($company['default_download_dir'] ?? '')
                );
                $existing = !empty($parsed['access_key'])
                    ? $this->repo->findDocumentByAccessKey($parsed['doc_type'], (string) $parsed['access_key'], (int)$company['id'])
                    : null;

                $this->repo->saveDocument($parsed + $saved + [
                    'company_id' => (int)$company['id'],
                    'company_name' => (string)$company['company_name'],
                    'company_cnpj' => (string)$company['cnpj'],
                    'raw_xml' => $xml,
                    'imported_at' => $existing ? ($existing['imported_at'] ?? date('c')) : date('c'),
                    'updated_at' => date('c'),
                ]);

                if ($existing) {
                    $updated++;
                } else {
                    $created++;
                }
            }
        }

        $this->repo->setSetting($settingPrefix . 'ult_nsu', $currentNsu);
        $this->repo->setSetting($settingPrefix . 'cooldown_until', date('c', time() + 300));

        $range = ($firstChecked ?? $lastNsu) . ' a ' . $currentNsu;
        $log = 'NFS-e Nacional coletada. nsus=' . $range . ' consultados=' . $checked . ' itens=' . $itemsCount . ' vazios=' . $emptyResponses . ' ultNSU=' . $currentNsu;
        if ($notes) {
            $log .= ' | ' . implode(' | ', $notes);
        }
        $this->storage->appendLog('collector_nfse.log', $log);

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => 0,
            'message' => 'NFS-e Nacional: ' . $itemsCount . ' item(ns) processado(s). NSUs consultados: ' . $range . '.',
        ];
    }

    private function buildUrl(string $baseUrl, string $path, string $nsu, string $companyCnpj): string
    {
        if ($path === '' || $path === '/contribuintes/api/v1/distribuicao') {
            $path = '/contribuintes/DFe/{nsu}';
        }

        $path = str_replace(['{NSU}', '{nsu}', '{ultNsu}'], rawurlencode($nsu), $path);
        $url = $baseUrl . '/' . ltrim($path, '/');
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'cnpj=' . urlencode($companyCnpj);
    }

    private function parseResponse(string $response): array
    {
        $body = trim($response);
        if ($body === '') {
            return ['items' => [], 'next_nsu' => null];
        }

        if (str_starts_with($body, '<')) {
            return ['items' => [['xml' => $body]], 'next_nsu' => null];
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            $xml = $this->decodeXmlPayload($body);
            if ($xml !== '') {
                return ['items' => [['xml' => $xml]], 'next_nsu' => null];
            }

            throw new \RuntimeException('Resposta da NFS-e Nacional nao veio em JSON/XML reconhecivel. Confira o endpoint ADN e a autenticacao da empresa.');
        }

        $items = $data['items'] ?? $data['documentos'] ?? $data['nfse'] ?? $data['dfes'] ?? $data['DFe'] ?? $data['dfe'] ?? null;
        if ($items === null && $this->xmlFromItem($data) !== '') {
            $items = [$data];
        }
        if (is_array($items) && !array_is_list($items) && $this->xmlFromItem($items) !== '') {
            $items = [$items];
        }

        return [
            'items' => is_array($items) ? $items : [],
            'next_nsu' => isset($data['ultNsu']) || isset($data['proximoNsu']) || isset($data['nsu'])
                ? (string)($data['ultNsu'] ?? $data['proximoNsu'] ?? $data['nsu'])
                : null,
        ];
    }

    private function xmlFromItem(array|string $item): string
    {
        if (is_string($item)) {
            return $this->decodeXmlPayload($item);
        }

        foreach (['xml', 'conteudoXml', 'conteudo', 'documento', 'dfe', 'DFe', 'payload'] as $key) {
            if (isset($item[$key]) && is_string($item[$key])) {
                $xml = $this->decodeXmlPayload($item[$key]);
                if ($xml !== '') {
                    return $xml;
                }
            }
        }

        return '';
    }

    private function decodeXmlPayload(string $payload): string
    {
        $payload = trim($payload);
        if ($payload === '') {
            return '';
        }
        if (str_starts_with($payload, '<')) {
            return $payload;
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false || $decoded === '') {
            return '';
        }

        $unzipped = @gzdecode($decoded);
        if (is_string($unzipped) && str_starts_with(ltrim($unzipped), '<')) {
            return ltrim($unzipped);
        }

        return str_starts_with(ltrim($decoded), '<') ? ltrim($decoded) : '';
    }

    private function incrementNsu(string $nsu): string
    {
        $next = (string)(((int)$nsu) + 1);
        return str_pad($next, max(strlen($nsu), strlen($next)), '0', STR_PAD_LEFT);
    }

    private function responseSnippet(string $response): string
    {
        $snippet = preg_replace('/\s+/', ' ', trim($response));
        return substr((string)$snippet, 0, 220);
    }
}
