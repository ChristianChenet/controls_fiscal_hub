<?php
declare(strict_types=1);

namespace ControlS\Portal\Fiscal;

use RuntimeException;

final class DocZipDecoder
{
    public static function decode(string $content): string
    {
        $binary = base64_decode(trim($content), true);
        if ($binary === false) {
            throw new RuntimeException('docZip inválido.');
        }

        $xml = @gzdecode($binary);
        if ($xml === false) {
            $xml = @gzinflate(substr($binary, 10));
        }
        if ($xml === false) {
            throw new RuntimeException('Falha ao descompactar docZip.');
        }

        return $xml;
    }
}
