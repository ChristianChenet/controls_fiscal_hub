<?php
declare(strict_types=1);

namespace ControlS\Portal\Fiscal;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

final class XmlSigner
{
    public function signInfEvento(string $xml, string $certificatePem, string $privateKeyPem, string $privateKeyPass = ''): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        if (!$dom->loadXML($xml, LIBXML_NOBLANKS | LIBXML_NOCDATA)) {
            throw new RuntimeException('XML inválido para assinatura.');
        }

        $xp = new DOMXPath($dom);
        $infEvento = $xp->query('//*[local-name()="infEvento"]')->item(0);
        if (!$infEvento instanceof DOMElement) {
            throw new RuntimeException('Elemento infEvento não encontrado.');
        }

        $id = $infEvento->getAttribute('Id');
        if ($id === '') {
            throw new RuntimeException('A assinatura exige atributo Id em infEvento.');
        }

        $this->removeExistingSignature($infEvento);

        $canonical = $infEvento->C14N(true, false);
        $digestValue = base64_encode(hash('sha1', $canonical, true));

        $signature = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'Signature');
        $signedInfo = $dom->createElement('SignedInfo');
        $canonMethod = $dom->createElement('CanonicalizationMethod');
        $canonMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $sigMethod = $dom->createElement('SignatureMethod');
        $sigMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
        $reference = $dom->createElement('Reference');
        $reference->setAttribute('URI', '#' . $id);
        $transforms = $dom->createElement('Transforms');
        $transform = $dom->createElement('Transform');
        $transform->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $digestMethod = $dom->createElement('DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $digest = $dom->createElement('DigestValue', $digestValue);
        $transforms->appendChild($transform);
        $reference->appendChild($transforms);
        $reference->appendChild($digestMethod);
        $reference->appendChild($digest);
        $signedInfo->appendChild($canonMethod);
        $signedInfo->appendChild($sigMethod);
        $signedInfo->appendChild($reference);

        $private = openssl_pkey_get_private($privateKeyPem, $privateKeyPass);
        if ($private === false) {
            throw new RuntimeException('Não foi possível abrir a chave privada do certificado.');
        }

        $signedInfoCanonical = $signedInfo->C14N(true, false);
        $signatureValueRaw = '';
        if (!openssl_sign($signedInfoCanonical, $signatureValueRaw, $private, OPENSSL_ALGO_SHA1)) {
            throw new RuntimeException('Falha ao assinar o XML do evento.');
        }

        $signatureValue = $dom->createElement('SignatureValue', base64_encode($signatureValueRaw));
        $keyInfo = $dom->createElement('KeyInfo');
        $x509Data = $dom->createElement('X509Data');
        $clean = preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $certificatePem);
        $x509Certificate = $dom->createElement('X509Certificate', $clean);

        $signature->appendChild($signedInfo);
        $signature->appendChild($signatureValue);
        $x509Data->appendChild($x509Certificate);
        $keyInfo->appendChild($x509Data);
        $signature->appendChild($keyInfo);

        $infEvento->parentNode?->appendChild($signature);

        return $dom->saveXML();
    }

    private function removeExistingSignature(DOMElement $infEvento): void
    {
        $parent = $infEvento->parentNode;
        if (!$parent) {
            return;
        }
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMElement && $node->localName === 'Signature') {
                $parent->removeChild($node);
            }
        }
    }
}
