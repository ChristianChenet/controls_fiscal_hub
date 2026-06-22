
<?php
require_once __DIR__ . '/app/bootstrap.php';

use ControlS\Portal\Fiscal\XmlSigner;

$priv = null;
$configargs = ["private_key_bits"=>2048,"private_key_type"=>OPENSSL_KEYTYPE_RSA];
$res = openssl_pkey_new($configargs);
$csr = openssl_csr_new(["commonName"=>"Teste"], $res);
$x509 = openssl_csr_sign($csr, null, $res, 1);
openssl_x509_export($x509, $certout);
openssl_pkey_export($res, $pkeyout);

$xml = '<?xml version="1.0" encoding="UTF-8"?><envEvento xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.00"><idLote>1</idLote><evento versao="1.00"><infEvento Id="ID210210123456789012345678901234567890123456789001"><cOrgao>91</cOrgao><tpAmb>2</tpAmb></infEvento></evento></envEvento>';
$signed = (new XmlSigner())->signInfEvento($xml, $certout, $pkeyout, '');
echo strpos($signed, '<Signature') !== false ? "SIGNED\n" : "NO\n";
