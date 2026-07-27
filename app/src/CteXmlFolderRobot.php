<?php
declare(strict_types=1);

namespace ControlS\Portal;

use DateTimeImmutable;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class CteXmlFolderRobot
{
    public function __construct(
        private array $config,
        private Repository $repo,
        private Storage $storage
    ) {
    }

    public function run(string $origin = 'manual'): array
    {
        $targetDir = trim((string)$this->repo->getSetting('xml_download_dir_cte', ''));
        if ($targetDir === '') {
            throw new RuntimeException('Configure a Pasta CT-e antes de executar o robô de geração dos XMLs.');
        }

        $delayDays = max(0, min(30, (int)$this->repo->getSetting('cte_xml_folder_robot_delay_days', '2')));
        $limit = max(1, min(20000, (int)$this->repo->getSetting('cte_xml_folder_robot_limit', '5000')));
        $endDate = (new DateTimeImmutable('today'))->modify('-' . $delayDays . ' days')->format('Y-m-d');
        $filters = [
            'entry_only' => '1',
            'doc_type' => 'CTE',
            'status' => 'not_cancelled',
            'posted_to_erp' => '0',
            'cte_taker_only' => '1',
            'ignore_cfops' => '1',
            'date_end' => $endDate,
            'sort_by' => 'issue_date',
            'sort_dir' => 'asc',
        ];

        $jobId = $this->repo->createJob('cte_xml_folder_export', null, 'Pasta XML CT-e');
        $deleted = 0;
        $copied = 0;
        $skipped = 0;
        $errors = 0;
        $logs = [];

        try {
            $directory = $this->prepareTargetDirectory($targetDir);
            $deleted = $this->clearDirectory($directory);
            $total = $this->repo->documentsTotals($filters);
            $allDocuments = $this->repo->documents($filters);
            $documents = array_slice($allDocuments, 0, $limit);
            $usedNames = [];

            foreach ($documents as $doc) {
                $xml = $this->documentXml($doc);
                if (trim($xml) === '') {
                    $skipped++;
                    continue;
                }

                $filename = $this->safeFilename($doc, $usedNames);
                $target = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $filename;
                if (@file_put_contents($target, $xml) === false) {
                    $errors++;
                    $logs[] = 'Falha ao gravar ' . $filename;
                    continue;
                }
                $copied++;
            }

            $message = 'Origem: ' . $origin
                . ' | Filtro: CT-e, não lançado no ERP, exceto cancelados, somente tomador, ignorando CFOPs/notas'
                . ' | Data final aplicada: ' . $endDate
                . ' | Total do filtro: ' . (int)($total['total'] ?? count($allDocuments))
                . ' | Limite: ' . $limit
                . ' | Pasta limpa: ' . $directory
                . ' | Removidos: ' . $deleted
                . ' | Documentos avaliados: ' . count($documents)
                . ' | XMLs adicionados: ' . $copied
                . ' | Sem XML: ' . $skipped
                . ' | Erros: ' . $errors;
            if ($logs) {
                $message .= PHP_EOL . implode(PHP_EOL, array_slice($logs, 0, 20));
            }

            $this->repo->finishJob($jobId, $errors > 0 ? 'warning' : 'success', $copied, $deleted, $errors, $message);
            $this->repo->logAction('cte_xml_folder_export', $message);
            $this->storage->appendLog('cte_xml_folder_robot.log', '[' . date('c') . '] ' . $message);

            return [
                'job_id' => $jobId,
                'directory' => $directory,
                'end_date' => $endDate,
                'evaluated' => count($documents),
                'copied' => $copied,
                'deleted' => $deleted,
                'skipped' => $skipped,
                'errors' => $errors,
                'logs' => [$message],
            ];
        } catch (\Throwable $e) {
            $errors++;
            $message = 'Erro no robô de geração dos XMLs CT-e: ' . $e->getMessage();
            $this->repo->finishJob($jobId, 'error', $copied, $deleted, $errors, $message);
            $this->repo->logAction('cte_xml_folder_export_error', $message);
            $this->storage->appendLog('cte_xml_folder_robot.log', '[' . date('c') . '] ' . $message);
            throw $e;
        }
    }

    public function runScheduledIfDue(): ?array
    {
        if ($this->repo->getSetting('cte_xml_folder_robot_enabled', '0') !== '1') {
            return null;
        }

        $time = $this->normalizeTime($this->repo->getSetting('cte_xml_folder_robot_time', '02:00'));
        $today = date('Y-m-d');
        if ($this->repo->getSetting('cte_xml_folder_robot_last_run_date', '') === $today) {
            return null;
        }
        if (date('H:i') < $time) {
            return null;
        }

        $result = $this->run('agendado');
        $this->repo->setSetting('cte_xml_folder_robot_last_run_date', $today);
        return $result;
    }

    private function prepareTargetDirectory(string $targetDir): string
    {
        $normalized = str_replace('\\', '/', trim($targetDir));
        $normalized = rtrim(preg_replace('#/+#', '/', $normalized) ?: $normalized, '/');
        if ($normalized === '' || preg_match('#^[A-Za-z]:$#', $normalized) || $normalized === '/' || strlen($normalized) < 6) {
            throw new RuntimeException('Pasta CT-e inválida para limpeza automática: ' . $targetDir);
        }
        if (preg_match('#/Windows(/|$)|/Program Files(/|$)|/Users(/|$)#i', $normalized)) {
            throw new RuntimeException('Pasta CT-e bloqueada por segurança: ' . $targetDir);
        }
        if (!is_dir($normalized) && !mkdir($normalized, 0775, true)) {
            throw new RuntimeException('Não foi possível criar a Pasta CT-e: ' . $targetDir);
        }
        if (!is_writable($normalized)) {
            throw new RuntimeException('Pasta CT-e sem permissão de gravação: ' . $targetDir);
        }
        return $normalized;
    }

    private function clearDirectory(string $directory): int
    {
        $deleted = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if ($item->isDir()) {
                if (@rmdir($path)) {
                    $deleted++;
                }
                continue;
            }
            if (@unlink($path)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    private function documentXml(array $doc): string
    {
        $xml = (string)($doc['raw_xml'] ?? '');
        $path = (string)($doc['xml_path'] ?? '');
        if (trim($xml) === '' && $path !== '' && is_file($path)) {
            $xml = (string)file_get_contents($path);
        }
        return $xml;
    }

    private function safeFilename(array $doc, array &$usedNames): string
    {
        $base = 'CTE_' . (string)($doc['access_key'] ?? $doc['id'] ?? uniqid('', true)) . '.xml';
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $base) ?: ('CTE_' . uniqid('', true) . '.xml');
        if (isset($usedNames[$name])) {
            $name = preg_replace('/\.xml$/i', '', $name) . '_' . (string)($doc['id'] ?? count($usedNames) + 1) . '.xml';
        }
        $usedNames[$name] = true;
        return $name;
    }

    private function normalizeTime(string $time): string
    {
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', trim($time), $match)) {
            return '02:00';
        }
        return str_pad($match[1], 2, '0', STR_PAD_LEFT) . ':' . $match[2];
    }
}
