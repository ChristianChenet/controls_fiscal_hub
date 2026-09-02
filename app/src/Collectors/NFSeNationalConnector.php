<?php
declare(strict_types=1);

namespace ControlS\Portal\Collectors;

final class NFSeNationalConnector extends AbstractFiscalCollector
{
    public function queryCancellationStatus(string $accessKey): array
    {
        $company = $this->currentCompany();
        $this->certificates->assertMatchesCompany((int)$company['id'], (string)$company['cnpj']);
        $accessKey = preg_replace('/\D+/', '', $accessKey);
        if (strlen($accessKey) < 40) {
            throw new \RuntimeException('Chave NFS-e inválida para consulta de cancelamento.');
        }

        $baseUrl = rtrim((string)($this->config['nfse_event_base_url'] ?? 'https://sefin.nfse.gov.br/SefinNacional'), '/');
        $path = trim((string)($this->config['nfse_event_path'] ?? '/nfse/{ChaveAcesso}/eventos'));
        $path = str_replace(['{chaveAcesso}', '{ChaveAcesso}', '{CHAVE_ACESSO}', '{accessKey}'], rawurlencode($accessKey), $path);
        $headers = [
            'Accept' => 'application/json, application/xml, text/xml',
            'User-Agent' => $this->config['sefaz_user_agent'] ?? 'ControlSPortalFiscal/3.0',
        ];
        $useCertificate = (($this->config['nfse_auth_type'] ?? 'certificate') === 'certificate');
        if (($this->config['nfse_auth_type'] ?? '') === 'token' && !empty($this->config['nfse_token'])) {
            $headers['Authorization'] = 'Bearer ' . $this->config['nfse_token'];
            $useCertificate = false;
        }

        $baseUrls = $this->eventBaseUrls($baseUrl);
        $status = ['cancelled' => false, 'cStat' => '', 'xMotivo' => 'Nenhum evento de cancelamento localizado.'];
        $lastError = null;
        foreach ($baseUrls as $candidateBaseUrl) {
            try {
                $response = $this->getWithRetry($candidateBaseUrl . '/' . ltrim($path, '/'), $headers, $useCertificate, (int)($this->config['sefaz_timeout'] ?? 60), (int)$company['id']);
                $status = $this->parseCancellationStatus($response, $accessKey);
                if (!empty($status['cancelled'])) {
                    break;
                }
            } catch (\RuntimeException $e) {
                $lastError = $e;
                if (!$this->canIgnoreEventLookupError($e)) {
                    continue;
                }
            }
            if (empty($status['cancelled'])) {
                $specificStatus = $this->querySpecificCancellationEvents($candidateBaseUrl, $path, $accessKey, $headers, $useCertificate, (int)$company['id']);
                if (!empty($specificStatus['cancelled'])) {
                    $status = $specificStatus;
                    break;
                }
            }
        }
        if (empty($status['cancelled']) && $lastError !== null && !$this->canIgnoreEventLookupError($lastError)) {
            throw $lastError;
        }
        $updated = $this->repo->applyNFSeCancellationStatus($accessKey, $status, (int)$company['id']);
        $cancelled = $updated > 0 || (bool)($status['cancelled'] ?? false);
        return [
            'updated' => $updated,
            'errors' => 0,
            'cancelled' => $cancelled,
            'cStat' => (string)($status['cStat'] ?? ''),
            'event_cStat' => (string)($status['event_cStat'] ?? ''),
            'message' => $cancelled
                ? 'Cancelamento NFS-e confirmado por evento oficial.'
                : 'NFS-e consultada; nenhum evento de cancelamento localizado.',
        ];
    }

    private function querySpecificCancellationEvents(string $baseUrl, string $configuredPath, string $accessKey, array $headers, bool $useCertificate, int $companyId): array
    {
        $basePath = preg_replace('#/eventos(?:/.*)?$#i', '/eventos', $configuredPath) ?: $configuredPath;
        $basePath = str_replace(['{chaveAcesso}', '{ChaveAcesso}', '{CHAVE_ACESSO}', '{accessKey}'], rawurlencode($accessKey), $basePath);
        foreach (['101101', '105102', '105104', '101103', '105105', '110111'] as $eventType) {
            foreach ([$basePath . '/' . $eventType, $basePath . '/' . $eventType . '/1'] as $eventPath) {
                try {
                    $eventUrl = $baseUrl . '/' . ltrim($eventPath, '/');
                    $response = $this->getWithRetry($eventUrl, $headers, $useCertificate, (int)($this->config['sefaz_timeout'] ?? 60), $companyId);
                    $status = $this->parseCancellationStatus($response, $accessKey);
                    if (!empty($status['cancelled'])) {
                        return $status;
                    }
                } catch (\RuntimeException $e) {
                    if (!$this->canIgnoreEventLookupError($e)) {
                        throw $e;
                    }
                }
            }
        }
        return ['cancelled' => false, 'cStat' => '', 'xMotivo' => 'Nenhum evento de cancelamento localizado.'];
    }

    private function eventBaseUrls(string $configuredBaseUrl): array
    {
        $urls = [
            'https://sefin.nfse.gov.br/SefinNacional',
            $configuredBaseUrl,
            'https://adn.nfse.gov.br',
            (string)($this->config['nfse_base_url'] ?? ''),
        ];
        $normalized = [];
        foreach ($urls as $url) {
            $url = rtrim(trim($url), '/');
            if ($url !== '' && !in_array($url, $normalized, true)) {
                $normalized[] = $url;
            }
        }
        return $normalized;
    }

    private function canIgnoreEventLookupError(\RuntimeException $e): bool
    {
        return str_contains($e->getMessage(), 'HTTP 404')
            || str_contains($e->getMessage(), 'HTTP 405')
            || str_contains($e->getMessage(), 'HTTP 400');
    }

    public function collect(): array
    {
        $company = $this->currentCompany();
        $this->certificates->assertMatchesCompany((int)$company['id'], (string)$company['cnpj']);
        $companyCnpj = preg_replace('/\D+/', '', (string) $company['cnpj']);
        if ($companyCnpj === '') {
            throw new \RuntimeException('Informe o CNPJ da empresa nas configuracoes.');
        }

        $settingPrefix = 'nfse_' . (int)$company['id'] . '_';
        $cooldownUntil = (string)$this->repo->getSetting($settingPrefix . 'cooldown_until', '');
        if ($cooldownUntil !== '' && strtotime($cooldownUntil) > time()) {
            return [
                'created' => 0,
                'updated' => 0,
                'errors' => 0,
                'message' => 'NFS-e Nacional bloqueada temporariamente para evitar excesso de requisicoes ao ADN. Tente novamente apos ' . date('H:i', strtotime($cooldownUntil)) . '.',
            ];
        }

        $baseUrl = rtrim((string) $this->config['nfse_base_url'], '/');
        $path = trim((string) $this->config['nfse_distribution_path']);
        $lastNsu = preg_replace('/\D+/', '', (string) $this->repo->getSetting($settingPrefix . 'ult_nsu', '0'));
        $lastNsu = $lastNsu === '' ? '0' : $lastNsu;
        $limit = max(1, min(50, (int)($this->repo->getSetting('auto_nfse_nsu_limit', (string)($this->config['auto_nfse_nsu_limit'] ?? 50)))));
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
        $skippedNotRecipient = 0;
        $notes = [];

        while ($checked < $limit) {
            $requestNsu = $currentNsu === '' ? '0' : $currentNsu;
            $firstChecked ??= $requestNsu;
            $url = $this->buildUrl($baseUrl, $path, $requestNsu, $companyCnpj);
            try {
                $response = $this->getWithRetry($url, $headers, $useCertificate, (int) ($this->config['sefaz_timeout'] ?? 60), (int)$company['id']);
            } catch (\RuntimeException $e) {
                if (str_contains($e->getMessage(), 'HTTP 429')) {
                    $until = date('c', time() + 3600);
                    $this->repo->setSetting($settingPrefix . 'cooldown_until', $until);
                    $this->repo->setSetting($settingPrefix . 'last_error', 'HTTP 429 Too Many Requests em ' . date('c'));
                    throw new \RuntimeException('NFS-e Nacional bloqueada temporariamente pelo ADN por excesso de requisicoes. O portal pausou novas tentativas ate ' . date('H:i', strtotime($until)) . '.');
                }
                if (str_contains($e->getMessage(), 'HTTP 404')) {
                    // No ADN Nacional a consulta é pontual por NSU; muitos NSUs simplesmente não têm DFe para o CNPJ.
                    // Tratamos 404 como lacuna normal, avançando o cursor para permitir busca retroativa/contínua sem travar.
                    $checked++;
                    $currentNsu = $this->incrementNsu($requestNsu);
                    $emptyResponses++;
                    if (count($notes) < 3) {
                        $notes[] = 'NSU ' . $requestNsu . ' sem NFS-e no ADN.';
                    }
                    continue;
                }
                throw $e;
            }
            $parsedResponse = $this->parseResponse($response);
            $items = $parsedResponse['items'];
            $checked++;
            $maxReturnedNsu = $this->maxReturnedNsu($items);
            $currentNsu = $maxReturnedNsu !== '' ? $maxReturnedNsu : $this->incrementNsu($requestNsu);

            if (!$items) {
                $emptyResponses++;
                if (count($notes) < 3) {
                    $notes[] = 'NSU ' . $requestNsu . ' sem XML reconhecido. Resposta: ' . $this->responseSnippet($response);
                }
                continue;
            }

            foreach ($items as $item) {
                if (is_array($item) && strtoupper((string)($item['TipoDocumento'] ?? $item['tipoDocumento'] ?? 'NFSE')) !== 'NFSE') {
                    if (count($notes) < 3) {
                        $notes[] = 'NSU ' . $requestNsu . ' ignorado: tipo ' . (string)($item['TipoDocumento'] ?? $item['tipoDocumento'] ?? 'desconhecido') . '.';
                    }
                    continue;
                }
                $xml = $this->xmlFromItem($item);
                if ($xml === '') {
                    continue;
                }

                $itemsCount++;
                try {
                    $parsed = $this->parser->parse($xml);
                } catch (\RuntimeException $e) {
                    if (str_contains($e->getMessage(), 'XML de evento ignorado')) {
                        if (count($notes) < 3) {
                            $notes[] = 'NSU ' . $requestNsu . ' ignorado: XML de evento de NFS-e.';
                        }
                        continue;
                    }
                    throw $e;
                }
                if (empty($parsed['access_key']) && is_array($item) && !empty($item['ChaveAcesso'])) {
                    $parsed['access_key'] = preg_replace('/\D+/', '', (string)$item['ChaveAcesso']);
                }
                $recipientCnpj = preg_replace('/\D+/', '', (string)($parsed['recipient_cnpj'] ?? ''));
                if ($recipientCnpj !== '' && $recipientCnpj !== $companyCnpj) {
                    // Entradas de NFS-e sao apenas notas emitidas contra o CNPJ consultado.
                    // Se o ADN devolver documento de outro tomador, guardamos a ocorrencia no log e não importamos.
                    $skippedNotRecipient++;
                    if (count($notes) < 3) {
                        $notes[] = 'NSU ' . $requestNsu . ' ignorado: tomador ' . $recipientCnpj . ' diferente da empresa ' . $companyCnpj . '.';
                    }
                    continue;
                }
                $parsed['source'] = 'nfse_nacional_api';
                $parsed['schema_name'] = 'nfse_api';
                $downloadDir = (string)$this->repo->getSetting('xml_download_dir_nfse', '');
                $saved = $this->storage->saveXml(
                    'NFSE',
                    (string)($parsed['issue_date'] ?? date('c')),
                    $xml,
                    $this->guessFileName($parsed, 'nfse_api', $requestNsu),
                    $companyCnpj,
                    $downloadDir !== '' ? $downloadDir : (string)($company['default_download_dir'] ?? '')
                );
                $existing = !empty($parsed['access_key'])
                    ? $this->repo->findDocumentByAccessKey($parsed['doc_type'], (string) $parsed['access_key'], (int)$company['id'])
                    : $this->repo->findDocumentByDigest(hash('sha256', $xml));

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
        $this->repo->setSetting($settingPrefix . 'last_check_at', date('c'));
        $this->repo->setSetting($settingPrefix . 'cooldown_until', '');

        $range = ($firstChecked ?? $lastNsu) . ' a ' . $currentNsu;
        $log = 'NFS-e Nacional coletada. nsus=' . $range . ' consultados=' . $checked . ' itens=' . $itemsCount . ' fora_do_cnpj=' . $skippedNotRecipient . ' vazios=' . $emptyResponses . ' ultNSU=' . $currentNsu;
        if ($notes) {
            $log .= ' | ' . implode(' | ', $notes);
        }
        $this->storage->appendLog('collector_nfse.log', $log);

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => 0,
            'message' => 'NFS-e Nacional: ' . $itemsCount . ' item(ns) processado(s), ' . $skippedNotRecipient . ' fora do CNPJ. NSUs consultados: ' . $range . '.',
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

    private function getWithRetry(string $url, array $headers, bool $useCertificate, int $timeout, int $companyId): string
    {
        $lastError = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return $this->httpClient->get($url, $headers, $useCertificate, $timeout, $companyId);
            } catch (\RuntimeException $e) {
                $lastError = $e;
                if (str_contains($e->getMessage(), 'HTTP 429') || str_contains($e->getMessage(), 'HTTP 404')) {
                    throw $e;
                }
                // O ADN as vezes fecha a conexao sem corpo/codigo HTTP; repetir evita pausar a empresa por instabilidade pontual.
                if ($attempt < 3) {
                    usleep(500000 * $attempt);
                    continue;
                }
            }
        }

        throw $lastError ?? new \RuntimeException('Falha desconhecida na chamada NFS-e Nacional.');
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

        $items = $data['items'] ?? $data['documentos'] ?? $data['nfse'] ?? $data['dfes'] ?? $data['LoteDFe'] ?? $data['loteDFe'] ?? $data['DFe'] ?? $data['dfe'] ?? null;
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

    private function parseCancellationStatus(string $response, string $accessKey): array
    {
        $events = $this->eventPayloads($response);
        foreach ($events as $event) {
            $text = mb_strtolower($this->flattenText($event));
            $eventType = $this->valueFromPayload($event, ['tipoEvento', 'tpEvento', 'codigoEvento', 'codEvento', 'evento']);
            $eventName = $this->valueFromPayload($event, ['nomeEvento', 'xEvento', 'descricaoEvento', 'descEvento', 'descricao']);
            $eventStatus = preg_replace('/\D+/', '', $this->valueFromPayload($event, ['cStat', 'codigoStatus', 'status', 'situacao'])) ?: '';
            $protocol = $this->valueFromPayload($event, ['nProt', 'protocolo', 'numeroProtocolo']);
            $eventDate = $this->valueFromPayload($event, ['dhEvento', 'dhRegEvento', 'dataEvento', 'dataHoraEvento']);
            $reason = $this->valueFromPayload($event, ['xMotivo', 'motivo', 'mensagem', 'descricao']);

            $eventTypeNormalized = mb_strtolower($eventType);
            $eventNameNormalized = mb_strtolower($eventName);
            $reasonNormalized = mb_strtolower($reason);
            $hasStructuredEvent = $eventType !== '' || $eventStatus !== '' || $eventName !== '';
            $reasonSaysCancel = preg_match('/\bcancelad[ao]\b|\bcancelamento\b/u', $reasonNormalized) === 1
                && preg_match('/\b(nenhum|nao|não)\b.{0,80}\bcancel/u', $reasonNormalized) !== 1;
            $isCancel = in_array($eventStatus, ['101', '135', '136', '155'], true)
                || in_array($eventTypeNormalized, ['101101', '105102', '105104', '101103', '105105', '110111', 'cancelamento', 'cancelamento_nfse', 'cancelamento de nfs-e'], true)
                || ($hasStructuredEvent && preg_match('/\bcancelad[ao]\b|\bcancelamento\b/u', $eventNameNormalized) === 1)
                || (($eventType !== '' || $eventStatus !== '') && $reasonSaysCancel);
            if ($isCancel) {
                return [
                    'cancelled' => true,
                    'cStat' => $eventStatus !== '' ? $eventStatus : '101',
                    'event_cStat' => $eventStatus,
                    'event_type' => $eventType,
                    'event_name' => $eventName !== '' ? $eventName : 'Cancelamento NFS-e',
                    'xMotivo' => $reason,
                    'nProt' => $protocol,
                    'dhRecbto' => $eventDate,
                ];
            }
        }

        return [
            'cancelled' => false,
            'cStat' => '',
            'xMotivo' => 'Nenhum evento de cancelamento localizado.',
        ];
    }

    private function eventPayloads(string $response): array
    {
        $body = trim($response);
        if ($body === '') {
            return [];
        }
        if (str_starts_with($body, '<')) {
            return $this->xmlEventPayloads($body);
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            $xml = $this->decodeXmlPayload($body);
            return $xml !== '' ? $this->xmlEventPayloads($xml) : [];
        }

        $events = $data['eventos'] ?? $data['Eventos'] ?? $data['items'] ?? $data['documentos'] ?? $data['LoteDFe'] ?? $data['loteDFe'] ?? null;
        if ($events === null) {
            $events = [$data];
        } elseif (is_array($events) && !array_is_list($events)) {
            $events = [$events];
        }

        $payloads = [];
        foreach (is_array($events) ? $events : [] as $event) {
            if (is_array($event)) {
                $xml = $this->xmlFromItem($event);
                if ($xml !== '') {
                    $payloads = array_merge($payloads, $this->xmlEventPayloads($xml));
                    continue;
                }
            }
            $payloads[] = $event;
        }
        return $payloads;
    }

    private function xmlEventPayloads(string $xml): array
    {
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
            return [['raw' => $xml]];
        }
        $xp = new \DOMXPath($dom);
        $nodes = $xp->query('//*[contains(translate(local-name(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "evento")]');
        $events = [];
        foreach ($nodes ?: [] as $node) {
            $events[] = [
                'raw' => $dom->saveXML($node) ?: '',
                'tipoEvento' => $this->xmlFirstText($xp, $node, ['tpEvento', 'tipoEvento', 'codigoEvento']),
                'nomeEvento' => $this->xmlFirstText($xp, $node, ['xEvento', 'nomeEvento', 'descricaoEvento']),
                'cStat' => $this->xmlFirstText($xp, $node, ['cStat', 'codigoStatus', 'status']),
                'nProt' => $this->xmlFirstText($xp, $node, ['nProt', 'protocolo', 'numeroProtocolo']),
                'dhEvento' => $this->xmlFirstText($xp, $node, ['dhEvento', 'dhRegEvento', 'dataEvento']),
                'xMotivo' => $this->xmlFirstText($xp, $node, ['xMotivo', 'motivo', 'mensagem']),
            ];
        }
        return $events ?: [['raw' => $xml]];
    }

    private function xmlFirstText(\DOMXPath $xp, \DOMNode $context, array $names): string
    {
        foreach ($names as $name) {
            $nodes = $xp->query('.//*[local-name()="' . $name . '"]', $context);
            if ($nodes && $nodes->length > 0) {
                $value = trim((string)$nodes->item(0)?->textContent);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return '';
    }

    private function valueFromPayload(mixed $payload, array $keys): string
    {
        if (!is_array($payload)) {
            return '';
        }
        foreach ($keys as $key) {
            if (isset($payload[$key]) && !is_array($payload[$key])) {
                return trim((string)$payload[$key]);
            }
        }
        foreach ($payload as $value) {
            if (is_array($value)) {
                $found = $this->valueFromPayload($value, $keys);
                if ($found !== '') {
                    return $found;
                }
            }
        }
        return '';
    }

    private function flattenText(mixed $payload): string
    {
        if (is_array($payload)) {
            return implode(' ', array_map(fn($item): string => $this->flattenText($item), $payload));
        }
        return (string)$payload;
    }

    private function xmlFromItem(array|string $item): string
    {
        if (is_string($item)) {
            return $this->decodeXmlPayload($item);
        }

        foreach (['xml', 'ArquivoXml', 'arquivoXml', 'conteudoXml', 'conteudo', 'documento', 'dfe', 'DFe', 'payload', 'eventoXmlGZipB64', 'nfseXmlGZipB64', 'dfeXmlGZipB64'] as $key) {
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

    private function maxReturnedNsu(array $items): string
    {
        $max = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $nsu = (int)preg_replace('/\D+/', '', (string)($item['NSU'] ?? $item['nsu'] ?? '0'));
            if ($nsu > $max) {
                $max = $nsu;
            }
        }

        return $max > 0 ? str_pad((string)$max, 15, '0', STR_PAD_LEFT) : '';
    }

    private function responseSnippet(string $response): string
    {
        $snippet = preg_replace('/\s+/', ' ', trim($response));
        return substr((string)$snippet, 0, 220);
    }
}
