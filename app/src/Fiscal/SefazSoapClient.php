<?php
declare(strict_types=1);

namespace ControlS\Portal\Fiscal;

use ControlS\Portal\Http\MutualTlsHttpClient;
use RuntimeException;

final class SefazSoapClient
{
    public function __construct(
        private MutualTlsHttpClient $httpClient,
        private array $config
    ) {
    }

    public function send(
        string $url,
        string $soapAction,
        string $messageXml,
        string $methodNamespace = 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeDistribuicaoDFe',
        string $methodName = 'nfeDistDFeInteresse',
        string $messageNodeName = 'nfeDadosMsg',
        ?int $companyId = null
    ): string {
        $envelope = $this->buildEnvelope($messageXml, $methodNamespace, $methodName, $messageNodeName);

        $headers = [
            'Content-Type' => 'application/soap+xml; charset=utf-8; action="' . $soapAction . '"',
            'Accept' => 'application/soap+xml, text/xml, */*',
            'User-Agent' => $this->config['sefaz_user_agent'] ?? 'ControlSPortalFiscal/3.0',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
        ];

        $this->appendDebugLog(
            "URL: {$url}\n" .
            "ACTION: {$soapAction}\n" .
            "METHOD_NAMESPACE: {$methodNamespace}\n" .
            "METHOD_NAME: {$methodName}\n" .
            "MESSAGE_NODE: {$messageNodeName}\n" .
            "HEADERS:\n" . $this->formatHeaders($headers) . "\n\n" .
            "MESSAGE_XML:\n{$messageXml}\n\n" .
            "ENVELOPE:\n{$envelope}\n" .
            str_repeat('-', 120) . "\n"
        );

        $response = $this->httpClient->postXml(
            $url,
            $envelope,
            $headers,
            true,
            (int) ($this->config['sefaz_timeout'] ?? 60),
            $companyId
        );

        $this->appendDebugLog(
            "RESPONSE URL: {$url}\n" .
            "RESPONSE BODY:\n{$response}\n" .
            str_repeat('=', 120) . "\n"
        );

        if (
            stripos($response, '<soap:Fault') !== false
            || stripos($response, '<soap12:Fault') !== false
            || stripos($response, '<Fault>') !== false
        ) {
            throw new RuntimeException(
                'SOAP Fault retornado pela SEFAZ/ADN: ' . substr(trim($response), 0, 1500)
            );
        }

        return $response;
    }

    private function buildEnvelope(
        string $messageXml,
        string $methodNamespace,
        string $methodName,
        string $messageNodeName
    ): string {
        $messageXml = $this->compactXml($this->stripXmlDeclaration($messageXml));

        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                 xmlns:xsd="http://www.w3.org/2001/XMLSchema"
                 xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
  <soap12:Body>
    <{$methodName} xmlns="{$methodNamespace}">
      <{$messageNodeName}>{$messageXml}</{$messageNodeName}>
    </{$methodName}>
  </soap12:Body>
</soap12:Envelope>
XML;
    }

    private function stripXmlDeclaration(string $xml): string
    {
        return trim((string) preg_replace('/^(?:\xEF\xBB\xBF)?\s*<\?xml[^?]*\?>\s*/i', '', $xml, 1));
    }

    private function compactXml(string $xml): string
    {
        return trim((string) preg_replace('/>\s+</', '><', $xml));
    }

    private function appendDebugLog(string $content): void
    {
        $basePath = rtrim((string) ($this->config['base_path'] ?? dirname(__DIR__, 2)), '/\\');
        $logDir = $basePath . '/storage/logs';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        @file_put_contents(
            $logDir . '/soap_nfe_debug.log',
            '[' . date('Y-m-d H:i:s') . "]\n" . $content,
            FILE_APPEND
        );
    }

    private function formatHeaders(array $headers): string
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        return implode("\n", $lines);
    }
}
