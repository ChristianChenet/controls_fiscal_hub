<?php
require_once __DIR__ . '/../src/XmlParser.php';
$parser = new ControlS\Portal\XmlParser();

$sampleNfe = '<?xml version="1.0" encoding="UTF-8"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
  <NFe>
    <infNFe Id="NFe35160512345678000123550010000000011000000016">
      <ide><mod>55</mod><nNF>123</nNF><dhEmi>2026-05-12T10:00:00-03:00</dhEmi></ide>
      <emit><CNPJ>12345678000123</CNPJ><xNome>Fornecedor Teste</xNome></emit>
      <dest><CNPJ>21421411000120</CNPJ><xNome>Control S Consultoria</xNome></dest>
      <total><ICMSTot><vNF>1500.40</vNF></ICMSTot></total>
    </infNFe>
  </NFe>
</nfeProc>';
print_r($parser->parse($sampleNfe));
