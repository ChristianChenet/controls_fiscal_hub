<?php
declare(strict_types=1);

namespace ControlS\Portal;

use DateTimeImmutable;
use RuntimeException;

final class PeriodClosureService
{
    public const STATUSES = [
        'xml_completo' => 'XML completo',
        'apenas_resumo' => 'Apenas resumo',
        'pendente_manifestacao' => 'Pendente de manifestação',
        'aguardando_novo_download' => 'Aguardando novo download',
        'ja_existente' => 'Já existente',
        'fora_do_periodo_solicitado' => 'Fora do período solicitado',
        'indisponivel_por_limite_temporal' => 'Indisponível por limitação temporal',
        'nao_encontrado' => 'Não encontrado',
        'erro' => 'Erro',
    ];

    public function __construct(
        private array $config,
        private Repository $repo,
        private Storage $storage,
        private array $collectors,
        private ManifestationService $manifestation
    ) {
    }

    public function run(array $options): array
    {
        $periodStart = $this->normalizeDate((string)($options['period_start'] ?? ''));
        $periodEnd = $this->normalizeDate((string)($options['period_end'] ?? ''));
        if ($periodStart === '' || $periodEnd === '') {
            throw new RuntimeException('Informe data inicial e final do fechamento.');
        }
        if ($periodStart > $periodEnd) {
            throw new RuntimeException('A data inicial não pode ser maior que a data final.');
        }

        $companyIds = array_values(array_filter(array_map('intval', $options['company_ids'] ?? [])));
        if (!$companyIds) {
            $companyIds = array_map(fn(array $company) => (int)$company['id'], $this->repo->activeCompanies());
        }
        $docTypes = $this->normalizeDocTypes($options['doc_types'] ?? []);
        if (!$companyIds || !$docTypes) {
            throw new RuntimeException('Selecione ao menos uma empresa e um tipo de documento.');
        }

        $closureId = $this->repo->createPeriodClosure([
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'company_ids' => $companyIds,
            'doc_types' => $docTypes,
            'only_missing_complete' => !empty($options['only_missing_complete']),
            'try_manifestation' => !empty($options['try_manifestation']),
            'reprocess_after_manifestation' => !empty($options['reprocess_after_manifestation']),
            'generate_export' => !empty($options['generate_export']),
            'save_period_folder' => !empty($options['save_period_folder']),
        ]);

        $messages = [
            'A distribuição DF-e não possui filtro nativo por período. O portal filtra internamente os documentos retornados por NSU.',
        ];
        $runStartedAt = date('c');
        $hasErrors = false;
        $blockedByLimit = false;

        foreach ($companyIds as $companyId) {
            $company = $this->repo->findCompany($companyId);
            if (!$company) {
                $messages[] = 'Empresa ID ' . $companyId . ' não encontrada.';
                $hasErrors = true;
                continue;
            }

            foreach ($docTypes as $docType) {
                if ($docType === 'NFSE') {
                    $messages[] = $company['company_name'] . ' / NFS-e Nacional: o conector atual não garante a mesma recuperação por período. O fechamento considera NFS-e já existentes na base.';
                    continue;
                }

                $result = $this->runDistributionForCompanyType($company, $docType, $closureId, $periodStart, $periodEnd);
                $messages = array_merge($messages, $result['messages']);
                $hasErrors = $hasErrors || !empty($result['error']);
                $blockedByLimit = $blockedByLimit || !empty($result['blocked']);
            }
        }

        $this->rebuildItems($closureId, $companyIds, $docTypes, $periodStart, $periodEnd, $runStartedAt, $blockedByLimit);

        if (!empty($options['try_manifestation'])) {
            $pendingDocs = $this->repo->pendingNfeDocumentsForClosure($closureId);
            if ($pendingDocs) {
                $manifested = $this->manifestation->manifest(array_map(fn(array $doc) => (int)$doc['id'], $pendingDocs), (string)($options['manifest_type'] ?? 'science'), $options['manifest_justification'] ?? null);
                $messages[] = 'Manifestação NF-e executada para ' . $manifested . ' documento(s).';
                if (!empty($options['reprocess_after_manifestation'])) {
                    $messages[] = 'Reprocesso após manifestação solicitado. Se houver cooldown ativo da SEFAZ, o portal bloqueará para evitar consumo indevido.';
                    foreach ($companyIds as $companyId) {
                        $company = $this->repo->findCompany($companyId);
                        if ($company && in_array('NFE', $docTypes, true)) {
                            $result = $this->runDistributionForCompanyType($company, 'NFE', $closureId, $periodStart, $periodEnd);
                            $messages = array_merge($messages, $result['messages']);
                            $hasErrors = $hasErrors || !empty($result['error']);
                            $blockedByLimit = $blockedByLimit || !empty($result['blocked']);
                        }
                    }
                }
                $this->rebuildItems($closureId, $companyIds, $docTypes, $periodStart, $periodEnd, $runStartedAt, $blockedByLimit);
            } else {
                $messages[] = 'Nenhuma pendência NF-e elegível para manifestação no fechamento.';
            }
        }

        $items = $this->repo->periodClosureItems($closureId);
        $summary = $this->summarize($items);
        $zipPath = null;
        $csvPath = null;
        $docsForExport = $this->documentsForExport($companyIds, $docTypes, $periodStart, $periodEnd);
        if (!empty($options['save_period_folder'])) {
            $folderPath = $this->storage->copyPeriodFolder($docsForExport, $periodStart, $periodEnd);
            $messages[] = $folderPath ? 'Pasta consolidada do período gerada: ' . $folderPath : 'Pasta consolidada não gerada porque não havia XML completo no período.';
        }
        if (!empty($options['generate_export'])) {
            $zipPath = $this->storage->exportPeriodZip($docsForExport, $periodStart, $periodEnd);
            $csvPath = $this->storage->exportPeriodCsv($items, $periodStart, $periodEnd);
            $messages[] = $zipPath ? 'ZIP do fechamento gerado: ' . basename($zipPath) : 'ZIP não gerado porque não havia XML completo no período.';
            $messages[] = $csvPath ? 'CSV do fechamento gerado: ' . basename($csvPath) : 'CSV não gerado porque não havia itens.';
        }

        $this->repo->finishPeriodClosure($closureId, $hasErrors ? 'warning' : 'completed', $summary, $messages, $zipPath, $csvPath);
        $this->repo->logAction('period_closure', 'Fechamento #' . $closureId . ' de ' . $periodStart . ' a ' . $periodEnd . '. ' . implode(' | ', $messages));
        $this->storage->appendLog('period_closure.log', 'Fechamento #' . $closureId . ' empresas=' . implode(',', $companyIds) . ' tipos=' . implode(',', $docTypes) . ' periodo=' . $periodStart . '..' . $periodEnd . PHP_EOL . implode(PHP_EOL, $messages));

        return ['closure_id' => $closureId, 'summary' => $summary, 'messages' => $messages];
    }

    public function reprocessPending(int $closureId): array
    {
        $closure = $this->repo->findPeriodClosure($closureId);
        if (!$closure) {
            throw new RuntimeException('Fechamento não encontrado.');
        }
        $companyIds = json_decode((string)$closure['company_ids'], true) ?: [];
        $docTypes = json_decode((string)$closure['doc_types'], true) ?: [];
        if (!in_array('NFE', $docTypes, true)) {
            return ['messages' => ['Este fechamento não possui NF-e para reprocessar.']];
        }
        $messages = [];
        foreach ($companyIds as $companyId) {
            $company = $this->repo->findCompany((int)$companyId);
            if ($company) {
                $result = $this->runDistributionForCompanyType($company, 'NFE', $closureId, (string)$closure['period_start'], (string)$closure['period_end']);
                $messages = array_merge($messages, $result['messages']);
            }
        }
        $this->rebuildItems($closureId, array_map('intval', $companyIds), $docTypes, (string)$closure['period_start'], (string)$closure['period_end'], (string)$closure['started_at']);
        $items = $this->repo->periodClosureItems($closureId);
        $this->repo->finishPeriodClosure($closureId, 'completed', $this->summarize($items), $messages, $closure['export_zip_path'] ?? null, $closure['export_csv_path'] ?? null);
        return ['messages' => $messages];
    }

    private function runDistributionForCompanyType(array $company, string $docType, int $closureId, string $periodStart, string $periodEnd): array
    {
        $companyId = (int)$company['id'];
        $environment = (string)$this->config['sefaz_environment'];
        $control = $this->repo->ensureDistributionControl($companyId, $docType, $environment);
        $messages = [];

        if (!empty($control['cooldown_until']) && (new DateTimeImmutable((string)$control['cooldown_until'])) > new DateTimeImmutable()) {
            $messages[] = $company['company_name'] . ' / ' . $docType . ': nova consulta bloqueada temporariamente para evitar consumo indevido. Tente novamente após ' . (new DateTimeImmutable((string)$control['cooldown_until']))->format('H:i') . '.';
            return ['messages'=>$messages, 'blocked'=>true];
        }
        if (!empty($control['locked_by_job_id']) || $this->repo->hasRunningJob($companyId, strtolower($docType))) {
            $messages[] = $company['company_name'] . ' / ' . $docType . ': já existe coleta em andamento para este CNPJ/tipo.';
            return ['messages'=>$messages, 'blocked'=>true];
        }

        $collectorKey = strtolower($docType);
        if (!isset($this->collectors[$collectorKey])) {
            $messages[] = $company['company_name'] . ' / ' . $docType . ': conector não encontrado.';
            return ['messages'=>$messages, 'error'=>true];
        }

        $jobId = $this->repo->createJob('period_' . $collectorKey, $companyId, (string)$company['company_name']);
        $ultKey = $collectorKey . '_' . $companyId . '_ult_nsu';
        $maxKey = $collectorKey . '_' . $companyId . '_max_nsu';
        $initialUlt = (string)$this->repo->getSetting($ultKey, '0');
        $sourceContext = 'fechamento #' . $closureId . ' ' . $periodStart . '..' . $periodEnd;
        $this->repo->lockDistributionControl($companyId, $docType, $environment, $jobId, $sourceContext);

        try {
            $collector = $this->collectors[$collectorKey];
            $collector->setCompanyContext($company);
            $result = $collector->collect();
            $finalUlt = (string)$this->repo->getSetting($ultKey, $initialUlt);
            $maxNsu = (string)$this->repo->getSetting($maxKey, $finalUlt);
            $message = (string)($result['message'] ?? '');
            $cooldownUntil = null;
            $created = (int)($result['created'] ?? 0);
            $updated = (int)($result['updated'] ?? 0);
            if ($finalUlt !== '' && $maxNsu !== '' && $finalUlt === $maxNsu && ($created + $updated) === 0) {
                $cooldownUntil = (new DateTimeImmutable('+1 hour'))->format('c');
            }
            if (str_contains($message, '656') || str_contains($message, 'Consumo Indevido')) {
                $cooldownUntil = (new DateTimeImmutable('+1 hour'))->format('c');
            }
            $this->repo->releaseDistributionControl($companyId, $docType, $environment, [
                'last_distribution_result' => $message,
                'last_ult_nsu' => $finalUlt,
                'last_max_nsu' => $maxNsu,
                'cooldown_until' => $cooldownUntil,
                'source_context' => $sourceContext,
            ]);
            $this->repo->finishJob($jobId, 'success', $created, $updated, (int)($result['errors'] ?? 0), $message);
            $messages[] = $company['company_name'] . ' / ' . $docType . ': ' . $message . ' ultNSU ' . $initialUlt . ' -> ' . $finalUlt . ' maxNSU ' . $maxNsu . '.';
            if ($cooldownUntil) {
                $messages[] = $company['company_name'] . ' / ' . $docType . ': cooldown aplicado até ' . (new DateTimeImmutable($cooldownUntil))->format('H:i') . ' para evitar consumo indevido.';
            }
            return ['messages'=>$messages];
        } catch (\Throwable $e) {
            $this->repo->clearDistributionLock($companyId, $docType, $environment);
            $this->repo->finishJob($jobId, 'error', 0, 0, 1, $e->getMessage());
            $messages[] = $company['company_name'] . ' / ' . $docType . ': erro na coleta - ' . $e->getMessage();
            return ['messages'=>$messages, 'error'=>true];
        }
    }

    private function rebuildItems(int $closureId, array $companyIds, array $docTypes, string $periodStart, string $periodEnd, string $runStartedAt, bool $blockedByLimit = false): void
    {
        $this->repo->clearPeriodClosureItems($closureId);
        $docs = $this->repo->documentsForPeriod($companyIds, $docTypes, $periodStart, $periodEnd);
        foreach ($docs as $doc) {
            $this->repo->addPeriodClosureItem($closureId, $this->itemFromDocument($doc, $this->periodStatusForDocument($doc, $runStartedAt)));
        }

        $outside = $this->repo->recentlyImportedOutsidePeriod($companyIds, $docTypes, $runStartedAt, $periodStart, $periodEnd);
        for ($i = 0; $i < $outside; $i++) {
            $this->repo->addPeriodClosureItem($closureId, [
                'doc_type' => 'DFE',
                'status' => 'fora_do_periodo_solicitado',
                'notes' => 'Documento retornado pela distribuição por NSU, mas fora do período solicitado.',
            ]);
        }
        if (!$docs) {
            $this->repo->addPeriodClosureItem($closureId, [
                'doc_type' => implode(',', $docTypes),
                'status' => $blockedByLimit ? 'indisponivel_por_limite_temporal' : 'nao_encontrado',
                'notes' => $blockedByLimit ? 'Consulta bloqueada por cooldown/limite operacional do serviço para evitar consumo indevido.' : 'Nenhum documento do período foi encontrado na base do portal após a distribuição consultada.',
            ]);
        }
    }

    private function itemFromDocument(array $doc, string $status): array
    {
        return [
            'document_id' => $doc['id'] ?? null,
            'company_id' => $doc['company_id'] ?? null,
            'company_name' => $doc['company_name'] ?? null,
            'company_cnpj' => $doc['company_cnpj'] ?? null,
            'doc_type' => $doc['doc_type'] ?? 'DOC',
            'access_key' => $doc['access_key'] ?? null,
            'issuer_name' => $doc['issuer_name'] ?? null,
            'issuer_cnpj' => $doc['issuer_cnpj'] ?? null,
            'issue_date' => $doc['issue_date'] ?? null,
            'total_value' => (float)($doc['total_value'] ?? 0),
            'status' => $status,
            'xml_saved' => $this->documentHasCompleteXml($doc),
            'xml_path' => $doc['xml_path'] ?? null,
            'storage_dir' => $doc['storage_dir'] ?? null,
            'notes' => $doc['notes'] ?? null,
        ];
    }

    private function periodStatusForDocument(array $doc, string $runStartedAt): string
    {
        if ($this->documentHasCompleteXml($doc)) {
            if (!empty($doc['imported_at']) && (new DateTimeImmutable((string)$doc['imported_at'])) < new DateTimeImmutable($runStartedAt)) {
                return 'ja_existente';
            }
            return 'xml_completo';
        }
        if (($doc['status'] ?? '') === 'aguardando_novo_download') {
            return 'aguardando_novo_download';
        }
        if (($doc['doc_type'] ?? '') === 'NFE' && ($doc['status'] ?? '') === 'apenas_resumo') {
            return 'pendente_manifestacao';
        }
        if (($doc['status'] ?? '') === 'apenas_resumo') {
            return 'apenas_resumo';
        }
        return 'erro';
    }

    private function documentHasCompleteXml(array $doc): bool
    {
        if (($doc['status'] ?? '') !== 'xml_completo') {
            return false;
        }
        if (!empty($doc['raw_xml'])) {
            return true;
        }
        return !empty($doc['xml_path']) && is_file((string)$doc['xml_path']);
    }

    private function summarize(array $items): array
    {
        $summary = array_fill_keys(array_keys(self::STATUSES), 0);
        $summary['total_identificados'] = 0;
        foreach ($items as $item) {
            $status = (string)($item['status'] ?? 'erro');
            if (!array_key_exists($status, $summary)) {
                $summary[$status] = 0;
            }
            $summary[$status]++;
            if (!in_array($status, ['fora_do_periodo_solicitado', 'nao_encontrado', 'indisponivel_por_limite_temporal'], true)) {
                $summary['total_identificados']++;
            }
        }
        return $summary;
    }

    private function documentsForExport(array $companyIds, array $docTypes, string $periodStart, string $periodEnd): array
    {
        return array_filter(
            $this->repo->documentsForPeriod($companyIds, $docTypes, $periodStart, $periodEnd),
            fn(array $doc) => $this->documentHasCompleteXml($doc)
        );
    }

    private function normalizeDocTypes(array $docTypes): array
    {
        $map = ['nfe'=>'NFE', 'cte'=>'CTE', 'nfse'=>'NFSE', 'NFE'=>'NFE', 'CTE'=>'CTE', 'NFSE'=>'NFSE'];
        $normalized = [];
        foreach ($docTypes as $type) {
            if (isset($map[(string)$type])) {
                $normalized[] = $map[(string)$type];
            }
        }
        return array_values(array_unique($normalized));
    }

    private function normalizeDate(string $date): string
    {
        if ($date === '') {
            return '';
        }
        try {
            return (new DateTimeImmutable($date))->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }
}
