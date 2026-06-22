<?php
declare(strict_types=1);

namespace ControlS\Portal\Http;

use ControlS\Portal\CertificateService;
use ControlS\Portal\Storage;
use RuntimeException;

final class MutualTlsHttpClient
{
    public function __construct(
        private array $config,
        private Storage $storage,
        private CertificateService $certificates
    ) {
    }

    public function postXml(string $url, string $body, array $headers = [], bool $useCertificate = true, int $timeout = 60, ?int $companyId = null): string
    {
        return $this->request('POST', $url, $body, $headers, $useCertificate, $timeout, $companyId);
    }

    public function get(string $url, array $headers = [], bool $useCertificate = true, int $timeout = 60, ?int $companyId = null): string
    {
        return $this->request('GET', $url, null, $headers, $useCertificate, $timeout, $companyId);
    }

    private function request(string $method, string $url, ?string $body, array $headers, bool $useCertificate, int $timeout, ?int $companyId = null): string
    {
        $headerLines = [];
        foreach ($headers as $key => $value) {
            $headerLines[] = is_string($key) ? ($key . ': ' . $value) : (string) $value;
        }

        $ssl = [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'SNI_enabled' => true,
        ];

        if ($useCertificate) {
            $pem = $this->certificates->exportPemBundle($companyId);
            $ssl['local_cert'] = $pem['bundle'];
            $ssl['passphrase'] = $pem['password'];
        }

        $http = [
            'method' => $method,
            'ignore_errors' => true,
            'timeout' => $timeout,
            'header' => implode("\r\n", $headerLines),
        ];
        if ($body !== null) {
            $http['content'] = $body;
        }

        $context = stream_context_create([
            'ssl' => $ssl,
            'http' => $http,
        ]);

        $result = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];

        if ($result === false) {
            $error = error_get_last();
            throw new RuntimeException('Falha na chamada HTTP para ' . $url . ': ' . ($error['message'] ?? 'erro desconhecido'));
        }

        $statusLine = $responseHeaders[0] ?? '';
        if (preg_match('/\s(\d{3})\s/', $statusLine, $m)) {
            $status = (int) $m[1];
            if ($status >= 400) {
                $snippet = substr(trim($result), 0, 500);
                throw new RuntimeException('Resposta HTTP ' . $status . ' em ' . $url . ': ' . $snippet);
            }
        }

        return $result;
    }
}
