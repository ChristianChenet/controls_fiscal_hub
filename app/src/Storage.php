<?php
declare(strict_types=1);

namespace ControlS\Portal;

use DateTimeImmutable;
use ZipArchive;

final class Storage
{
    public function __construct(private array $config, private Repository $repo)
    {
        $this->ensureDirectory($this->config['base_path'] . '/storage/certificates');
        $this->ensureDirectory($this->config['base_path'] . '/storage/xmls');
        $this->ensureDirectory($this->config['base_path'] . '/storage/exports');
        $this->ensureDirectory($this->config['base_path'] . '/storage/logs');
        $this->ensureDirectory($this->config['base_path'] . '/storage/runtime');
    }

    public function ensureDirectory(string $path): string
    {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
        return $path;
    }

    public function saveUploadedCertificate(array $file, string $companyCnpj = 'geral'): string
    {
        $targetDir = $this->ensureDirectory($this->config['base_path'] . '/storage/certificates/' . preg_replace('/\D+/', '', $companyCnpj));
        $filename = date('YmdHis') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
        $target = $targetDir . '/' . $filename;
        move_uploaded_file($file['tmp_name'], $target);
        return $target;
    }

    public function saveXml(string $docType, string $issueDate, string $xml, ?string $preferredName = null, ?string $companyCnpj = null, ?string $companyDownloadDir = null): array
    {
        $paths = $this->resolveXmlPaths($docType, $issueDate, $companyCnpj, $companyDownloadDir);
        $safe = $preferredName ?: strtoupper($docType) . '_' . date('Ymd_His') . '_' . substr(sha1($xml), 0, 10) . '.xml';
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $safe);
        if (!str_ends_with(strtolower($safe), '.xml')) {
            $safe .= '.xml';
        }
        $path = rtrim($paths['directory'], '/') . '/' . $safe;
        file_put_contents($path, $xml);

        return [
            'xml_path' => $path,
            'storage_dir' => $paths['directory'],
        ];
    }

    public function resolveXmlPaths(string $docType, string $issueDate, ?string $companyCnpj = null, ?string $companyDownloadDir = null): array
    {
        $baseDir = $this->resolveBaseDownloadDir($companyDownloadDir);
        $context = $this->buildPathContext($baseDir, $docType, $issueDate, $companyCnpj);
        $mode = $this->repo->getSetting('storage_path_mode', 'segmented');
        $template = $this->repo->getSetting('storage_path_template', '{base}/{cnpj}/{doc_type}/{year}/{month}');

        $relative = match ($mode) {
            'flat' => '',
            'template' => $this->renderTemplatePath($template, $context, $baseDir),
            default => trim($context['cnpj'] . '/' . $context['doc_type'] . '/' . $context['year'] . '/' . $context['month'], '/'),
        };

        $fullDir = $this->normalizePath($baseDir . ($relative !== '' ? '/' . $relative : ''));
        return [
            'base_dir' => $baseDir,
            'directory' => $this->ensureDirectory($fullDir),
            'mode' => $mode,
            'template' => $template,
            'relative' => ltrim(str_replace($baseDir, '', $fullDir), '/'),
            'preview' => $fullDir,
        ];
    }

    public function previewXmlPath(string $docType, string $issueDate, ?string $companyCnpj = null, ?string $companyDownloadDir = null): string
    {
        $paths = $this->resolveXmlPaths($docType, $issueDate, $companyCnpj, $companyDownloadDir);
        return $paths['directory'];
    }

    public function exportZip(array $documents): ?string
    {
        if (!$documents) {
            return null;
        }

        $zipName = 'export_' . date('Ymd_His') . '_' . substr(sha1(json_encode($documents)), 0, 10) . '.zip';
        $zipPath = $this->ensureDirectory($this->config['base_path'] . '/storage/exports') . '/' . $zipName;
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($documents as $doc) {
            $path = (string)($doc['xml_path'] ?? '');
            $entryName = basename($path ?: ((string)($doc['doc_type'] ?? 'DOC') . '_' . (string)($doc['access_key'] ?? $doc['id']) . '.xml'));
            if ($path && is_file($path)) {
                $zip->addFile($path, $entryName);
            } elseif (!empty($doc['raw_xml'])) {
                $zip->addFromString($entryName, (string)$doc['raw_xml']);
            }
        }

        $zip->close();
        return $zipPath;
    }

    public function exportPeriodZip(array $items, string $periodStart, string $periodEnd): ?string
    {
        $documents = [];
        foreach ($items as $item) {
            if (!empty($item['xml_path']) || !empty($item['raw_xml'])) {
                $documents[] = $item;
            }
        }
        if (!$documents) {
            return null;
        }

        $safePeriod = preg_replace('/[^0-9_-]/', '_', $periodStart . '_' . $periodEnd);
        $zipPath = $this->ensureDirectory($this->config['base_path'] . '/storage/exports/fechamentos') . '/fechamento_' . $safePeriod . '_' . date('Ymd_His') . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($documents as $doc) {
            $entryName = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)($doc['doc_type'] ?? 'DOC') . '_' . ((string)($doc['access_key'] ?? $doc['document_id'] ?? uniqid())) . '.xml');
            if (!empty($doc['xml_path']) && is_file((string)$doc['xml_path'])) {
                $zip->addFile((string)$doc['xml_path'], $entryName);
            } elseif (!empty($doc['raw_xml'])) {
                $zip->addFromString($entryName, (string)$doc['raw_xml']);
            }
        }
        $zip->close();
        return $zipPath;
    }

    public function exportPeriodCsv(array $items, string $periodStart, string $periodEnd): ?string
    {
        if (!$items) {
            return null;
        }
        $safePeriod = preg_replace('/[^0-9_-]/', '_', $periodStart . '_' . $periodEnd);
        $path = $this->ensureDirectory($this->config['base_path'] . '/storage/exports/fechamentos') . '/fechamento_' . $safePeriod . '_' . date('Ymd_His') . '.csv';
        $fh = fopen($path, 'wb');
        if (!$fh) {
            return null;
        }
        fputcsv($fh, ['empresa', 'cnpj', 'tipo', 'chave', 'emitente', 'cnpj_emitente', 'data', 'valor', 'status', 'xml_salvo', 'pasta', 'observacao'], ';');
        foreach ($items as $item) {
            fputcsv($fh, [
                $item['company_name'] ?? '',
                $item['company_cnpj'] ?? '',
                $item['doc_type'] ?? '',
                $item['access_key'] ?? '',
                $item['issuer_name'] ?? '',
                $item['issuer_cnpj'] ?? '',
                $item['issue_date'] ?? '',
                $item['total_value'] ?? '',
                $item['status'] ?? '',
                !empty($item['xml_saved']) ? 'sim' : 'nao',
                $item['storage_dir'] ?? '',
                $item['notes'] ?? '',
            ], ';');
        }
        fclose($fh);
        return $path;
    }

    public function copyPeriodFolder(array $documents, string $periodStart, string $periodEnd): ?string
    {
        if (!$documents) {
            return null;
        }
        $safePeriod = preg_replace('/[^0-9_-]/', '_', $periodStart . '_' . $periodEnd);
        $dir = $this->ensureDirectory($this->config['base_path'] . '/storage/exports/fechamentos/periodo_' . $safePeriod);
        $copied = 0;
        foreach ($documents as $doc) {
            $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)($doc['doc_type'] ?? 'DOC') . '_' . ((string)($doc['access_key'] ?? $doc['id'] ?? uniqid())) . '.xml');
            $target = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
            if (!empty($doc['xml_path']) && is_file((string)$doc['xml_path']) && @copy((string)$doc['xml_path'], $target)) {
                $copied++;
            } elseif (!empty($doc['raw_xml']) && @file_put_contents($target, (string)$doc['raw_xml']) !== false) {
                $copied++;
            }
        }
        return $copied > 0 ? $dir : null;
    }

    public function canWriteDirectory(string $path): bool
    {
        $this->ensureDirectory($path);
        $testFile = rtrim($path, '/') . '/.__controls_write_test';
        $ok = @file_put_contents($testFile, 'ok') !== false;
        if ($ok && is_file($testFile)) {
            @unlink($testFile);
        }
        return $ok;
    }

    public function appendLog(string $filename, string $content): void
    {
        $dir = $this->ensureDirectory($this->config['base_path'] . '/storage/logs');
        $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (is_file($path) && !is_writable($path)) {
            @chmod($path, 0664);
        }

        @file_put_contents($path, $content . PHP_EOL, FILE_APPEND);
    }

    private function resolveBaseDownloadDir(?string $companyDownloadDir = null): string
    {
        return rtrim(
            $companyDownloadDir
                ?: ($this->repo->getSetting('default_download_dir', $this->config['default_download_dir']) ?: $this->config['default_download_dir']),
            '/'
        );
    }

    private function buildPathContext(string $baseDir, string $docType, string $issueDate, ?string $companyCnpj): array
    {
        $year = 'sem-data';
        $month = '00';
        $day = '00';

        if ($issueDate) {
            try {
                $dt = new DateTimeImmutable($issueDate);
                $year = $dt->format('Y');
                $month = $dt->format('m');
                $day = $dt->format('d');
            } catch (\Throwable) {
            }
        }

        return [
            'base' => $baseDir,
            'cnpj' => preg_replace('/\D+/', '', (string)$companyCnpj) ?: 'sem-cnpj',
            'doc_type' => strtoupper($docType),
            'year' => $year,
            'month' => $month,
            'day' => $day,
        ];
    }

    private function renderTemplatePath(string $template, array $context, string $baseDir): string
    {
        $path = $template;
        foreach ($context as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
        }
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?: $path;

        if (str_starts_with($path, $baseDir)) {
            $path = substr($path, strlen($baseDir));
        }

        return trim((string)$path, '/');
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        return preg_replace('#/+#', '/', $path) ?: $path;
    }
}
