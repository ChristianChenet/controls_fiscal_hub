<?php
declare(strict_types=1);

namespace ControlS\Portal;

use ControlS\Portal\Collectors\CollectorInterface;

final class JobRunner
{
    public function __construct(
        private array $config,
        private Repository $repo,
        /** @var array<string, CollectorInterface> */
        private array $collectors,
        private XmlParser $parser,
        private Storage $storage,
        private ?CertificateService $certificates = null,
        private ?ManifestationService $manifestation = null
    ) {
    }

    public function run(string $jobType, int $companyId = 0): array
    {
        $companies = $this->companiesForJob($jobType, $companyId);
        if (!$companies) {
            throw new \RuntimeException('Nenhuma empresa ativa cadastrada.');
        }

        $created = 0; $updated = 0; $errors = 0; $logs = [];
        foreach ($companies as $company) {
            $jobId = $this->repo->createJob($jobType, (int)$company['id'], (string)$company['company_name']);
            $companyCreated = 0; $companyUpdated = 0; $companyErrors = 0; $companyLogs = [];
            try {
                if ($jobType === 'certificate_check') {
                    if (!$this->certificates) {
                        throw new \RuntimeException('Serviço de certificado não disponível.');
                    }
                    $health = $this->certificates->healthCheck((int)$company['id']);
                    $okPath = $this->storage->canWriteDirectory($this->storage->previewXmlPath('NFE', date('c'), (string)$company['cnpj'], (string)($company['default_download_dir'] ?? '')));
                    $companyLogs[] = $health['message'] . ' Pasta gravável: ' . ($okPath ? 'sim' : 'não');
                } elseif ($jobType === 'cte_until_max') {
                    $result = $this->runCteUntilMax($company);
                    $companyCreated += (int)$result['created'];
                    $companyUpdated += (int)$result['updated'];
                    $companyErrors += (int)$result['errors'];
                    $companyLogs = array_merge($companyLogs, $result['logs']);
                } elseif (in_array($jobType, ['nfe_until_max', 'nfe_until_max_science'], true)) {
                    $result = $this->runNfeUntilMax($company, $jobType === 'nfe_until_max_science');
                    $companyCreated += (int)$result['created'];
                    $companyUpdated += (int)$result['updated'];
                    $companyErrors += (int)$result['errors'];
                    $companyLogs = array_merge($companyLogs, $result['logs']);
                } else {
                    $collectorsToRun = match ($jobType) {
                        'collect_all', 'collect_missing' => $this->collectors,
                        default => isset($this->collectors[$jobType]) ? [$this->collectors[$jobType]] : null,
                    };
                    if ($collectorsToRun === null) {
                        throw new \RuntimeException('Job inválido.');
                    }

                    foreach ($collectorsToRun as $collector) {
                        $collector->setCompanyContext($company);
                        $result = $collector->collect();
                        $companyCreated += (int)$result['created'];
                        $companyUpdated += (int)$result['updated'];
                        $companyErrors += (int)$result['errors'];
                        $companyLogs[] = (string)$result['message'];
                    }
                }

                $this->repo->finishJob($jobId, $companyErrors > 0 ? 'warning' : 'success', $companyCreated, $companyUpdated, $companyErrors, implode(PHP_EOL, $companyLogs));
                $this->repo->logAction('job_run', $jobType . ' => ' . implode(' | ', $companyLogs), (int)$company['id']);
            } catch (\Throwable $e) {
                $companyErrors++;
                $companyLogs[] = $e->getMessage();
                $this->repo->finishJob($jobId, 'error', $companyCreated, $companyUpdated, $companyErrors, implode(PHP_EOL, $companyLogs));
                $this->repo->logAction('job_error', $jobType . ' => ' . $e->getMessage(), (int)$company['id']);
            }

            $created += $companyCreated;
            $updated += $companyUpdated;
            $errors += $companyErrors;
            $logs[] = '[' . $company['company_name'] . '] ' . implode(' | ', $companyLogs);
        }

        return compact('created', 'updated', 'errors', 'logs');
    }

    private function companiesForJob(string $jobType, int $companyId): array
    {
        if ($companyId <= 0) {
            return $this->repo->activeCompanies();
        }

        $selected = $this->repo->findCompany($companyId);
        if (!$selected) {
            return [];
        }

        if (!in_array($jobType, ['nfe', 'nfe_until_max', 'nfe_until_max_science'], true)) {
            return [$selected];
        }

        $root = substr(preg_replace('/\D+/', '', (string)$selected['cnpj']), 0, 8);
        if ($root === '') {
            return [$selected];
        }

        $sameRootCompanies = [];
        foreach ($this->repo->activeCompanies() as $company) {
            $companyRoot = substr(preg_replace('/\D+/', '', (string)($company['cnpj'] ?? '')), 0, 8);
            if ($companyRoot === $root) {
                $sameRootCompanies[(int)$company['id']] = $company;
            }
        }

        return $sameRootCompanies ? array_values($sameRootCompanies) : [$selected];
    }


    private function applyNsuRewindOnce(string $docKey, array $company): ?string
    {
        $companyId = (int)$company['id'];
        $companySettingName = 'auto_' . $docKey . '_rewind_nsu_once_company_' . $companyId;
        $globalSettingName = 'auto_' . $docKey . '_rewind_nsu_once';
        $companyRewind = max(0, min(50000, (int)$this->repo->getSetting($companySettingName, '0')));
        $globalRewind = max(0, min(50000, (int)$this->repo->getSetting($globalSettingName, '0')));
        $settingName = $companyRewind > 0 ? $companySettingName : $globalSettingName;
        $rewind = $companyRewind > 0 ? $companyRewind : $globalRewind;
        if ($rewind <= 0) {
            return null;
        }
        $prefix = $docKey . '_' . $companyId . '_';
        $current = (int)preg_replace('/\D+/', '', (string)$this->repo->getSetting($prefix . 'ult_nsu', '0'));
        if ($current <= 0) {
            $this->repo->setSetting($settingName, '0');
            return 'Recuo seguro solicitado, mas o ultNSU atual estava zerado; nada foi alterado.';
        }
        $new = max(0, $current - $rewind);
        $newFormatted = str_pad((string)$new, 15, '0', STR_PAD_LEFT);
        $oldFormatted = str_pad((string)$current, 15, '0', STR_PAD_LEFT);
        $this->repo->setSetting($prefix . 'ult_nsu', $newFormatted);
        $this->repo->setSetting($prefix . 'cooldown_until', '');
        $this->repo->setSetting($settingName, '0');
        $scope = $settingName === $companySettingName ? 'por CNPJ' : 'global';
        $message = 'Recuo seguro de NSU aplicado uma vez (' . $scope . '): ultNSU ' . $oldFormatted . ' -> ' . $newFormatted . ' (' . $rewind . ' NSU). XMLs já existentes serão deduplicados.';
        $this->repo->logAction('nsu_rewind_' . $docKey, $message, $companyId);
        $this->storage->appendLog('nsu_rewind.log', '[' . ($company['company_name'] ?? $company['id']) . '] ' . strtoupper($docKey) . ' ' . $message);
        return $message;
    }

    private function runCteUntilMax(array $company): array
    {
        if (empty($this->collectors['cte'])) {
            throw new \RuntimeException('Coletor de CT-e não disponível.');
        }

        $collector = $this->collectors['cte'];
        $collector->setCompanyContext($company);
        $settingPrefix = 'cte_' . (int)$company['id'] . '_';
        $maxCycles = max(1, (int)$this->repo->getSetting('cte_robot_max_cycles', (string)($this->config['cte_robot_max_cycles'] ?? 10)));
        $timeLimit = max(60, (int)$this->repo->getSetting('cte_robot_time_limit_seconds', (string)($this->config['cte_robot_time_limit_seconds'] ?? 240)));
        $startedAt = time();
        $created = 0;
        $updated = 0;
        $errors = 0;
        $logs = [];
        $rewindMessage = $this->applyNsuRewindOnce('cte', $company);
        if ($rewindMessage) {
            $logs[] = $rewindMessage;
        }

        for ($cycle = 1; $cycle <= $maxCycles; $cycle++) {
            if ((time() - $startedAt) >= $timeLimit) {
                $logs[] = 'Robô CT-e pausado por limite de tempo seguro. Execute novamente para continuar.';
                break;
            }

            $beforeUlt = str_pad(preg_replace('/\D+/', '', (string)$this->repo->getSetting($settingPrefix . 'ult_nsu', '0')), 15, '0', STR_PAD_LEFT);
            $beforeMax = str_pad(preg_replace('/\D+/', '', (string)$this->repo->getSetting($settingPrefix . 'max_nsu', '0')), 15, '0', STR_PAD_LEFT);

            $result = $collector->collect();
            $created += (int)$result['created'];
            $updated += (int)$result['updated'];
            $errors += (int)$result['errors'];

            $afterUlt = str_pad(preg_replace('/\D+/', '', (string)$this->repo->getSetting($settingPrefix . 'ult_nsu', '0')), 15, '0', STR_PAD_LEFT);
            $afterMax = str_pad(preg_replace('/\D+/', '', (string)$this->repo->getSetting($settingPrefix . 'max_nsu', '0')), 15, '0', STR_PAD_LEFT);
            $logs[] = 'Ciclo ' . $cycle . ': ' . (string)$result['message'] . ' ultNSU ' . $beforeUlt . ' -> ' . $afterUlt . ' maxNSU ' . $afterMax . '.';

            if ($afterUlt === $beforeUlt) {
                $logs[] = 'Robô CT-e pausado: não houve avanço de NSU nesta execução.';
                break;
            }
            if ($afterMax !== '000000000000000' && $afterUlt >= $afterMax) {
                $logs[] = 'Robô CT-e finalizado: ultNSU alcançou maxNSU.';
                break;
            }
            if ((int)$result['errors'] > 0) {
                $logs[] = 'Robô CT-e pausado por erro no ciclo.';
                break;
            }
        }

        return compact('created', 'updated', 'errors', 'logs');
    }

    private function runNfeUntilMax(array $company, bool $manifestScience): array
    {
        if (empty($this->collectors['nfe'])) {
            throw new \RuntimeException('Coletor de NF-e não disponível.');
        }

        $collector = $this->collectors['nfe'];
        $collector->setCompanyContext($company);
        $settingPrefix = 'nfe_' . (int)$company['id'] . '_';
        $maxCycles = max(1, (int)$this->repo->getSetting('nfe_robot_max_cycles', (string)($this->config['nfe_robot_max_cycles'] ?? 4)));
        $timeLimit = max(60, (int)$this->repo->getSetting('nfe_robot_time_limit_seconds', (string)($this->config['nfe_robot_time_limit_seconds'] ?? 180)));
        $manifestLimit = max(1, (int)$this->repo->getSetting('nfe_science_limit_per_run', (string)($this->config['nfe_science_limit_per_run'] ?? 30)));
        $startedAt = time();
        $created = 0;
        $updated = 0;
        $errors = 0;
        $logs = [];
        $rewindMessage = $this->applyNsuRewindOnce('nfe', $company);
        if ($rewindMessage) {
            $logs[] = $rewindMessage;
        }

        for ($cycle = 1; $cycle <= $maxCycles; $cycle++) {
            if ((time() - $startedAt) >= $timeLimit) {
                $logs[] = 'Robô NF-e pausado por limite de tempo seguro. Execute novamente para continuar.';
                break;
            }

            $beforeUlt = str_pad(preg_replace('/\D+/', '', (string)$this->repo->getSetting($settingPrefix . 'ult_nsu', '0')), 15, '0', STR_PAD_LEFT);
            $beforeMax = str_pad(preg_replace('/\D+/', '', (string)$this->repo->getSetting($settingPrefix . 'max_nsu', '0')), 15, '0', STR_PAD_LEFT);

            $result = $collector->collect();
            $created += (int)$result['created'];
            $updated += (int)$result['updated'];
            $errors += (int)$result['errors'];

            $afterUlt = str_pad(preg_replace('/\D+/', '', (string)$this->repo->getSetting($settingPrefix . 'ult_nsu', '0')), 15, '0', STR_PAD_LEFT);
            $afterMax = str_pad(preg_replace('/\D+/', '', (string)$this->repo->getSetting($settingPrefix . 'max_nsu', '0')), 15, '0', STR_PAD_LEFT);
            $logs[] = 'Ciclo ' . $cycle . ': ' . (string)$result['message'] . ' ultNSU ' . $beforeUlt . ' -> ' . $afterUlt . ' maxNSU ' . $afterMax . '.';

            if (str_contains((string)$result['message'], 'Consumo Indevido')) {
                $logs[] = 'Robô NF-e pausado por consumo indevido. Aguarde 1 hora antes de nova tentativa.';
                break;
            }
            if ($afterUlt === $beforeUlt) {
                $logs[] = 'Robô NF-e pausado: não houve avanço de NSU nesta execução.';
                break;
            }
            if ($afterMax !== '000000000000000' && $afterUlt >= $afterMax) {
                $logs[] = 'Robô NF-e finalizado: ultNSU alcançou maxNSU.';
                break;
            }
            if ((int)$result['errors'] > 0) {
                $logs[] = 'Robô NF-e pausado por erro no ciclo.';
                break;
            }
        }

        if ($manifestScience) {
            if (!$this->manifestation) {
                throw new \RuntimeException('Serviço de manifestação não disponível.');
            }
            $pending = $this->repo->pendingNfeDocumentsForCompany((int)$company['id'], $manifestLimit);
            if ($pending) {
                $ids = array_map(static fn(array $doc): int => (int)$doc['id'], $pending);
                $manifested = $this->manifestation->manifest($ids, 'science');
                $logs[] = 'Ciência da operação enviada para ' . $manifested . ' NF-e(s) pendente(s).';
            } else {
                $logs[] = 'Nenhuma NF-e pendente elegível para ciência da operação.';
            }
        }

        $cancelCheck = $this->checkRetroactiveNfeCancellations($company);
        $updated += (int)$cancelCheck['updated'];
        $errors += (int)$cancelCheck['errors'];
        $logs[] = (string)$cancelCheck['message'];

        return compact('created', 'updated', 'errors', 'logs');
    }

    private function checkRetroactiveNfeCancellations(array $company): array
    {
        if (empty($this->collectors['nfe']) || !method_exists($this->collectors['nfe'], 'collectByAccessKey')) {
            return ['updated' => 0, 'errors' => 0, 'message' => 'Verificação de cancelamentos retroativos indisponível.'];
        }

        $limit = max(1, min(100, (int)$this->repo->getSetting('nfe_cancel_check_limit_per_run', '20')));
        $docs = $this->repo->documents([
            'company_id' => [(string)$company['id']],
            'doc_type' => 'NFE',
            'posted_to_erp' => '0',
            'entry_only' => '1',
            'sort_by' => 'imported_at',
            'sort_dir' => 'desc',
        ]);
        $docs = array_values(array_filter($docs, static function (array $doc): bool {
            $key = preg_replace('/\D+/', '', (string)($doc['access_key'] ?? ''));
            return strlen($key) === 44 && !in_array((string)($doc['status'] ?? ''), ['cancelado', 'denegado'], true);
        }));
        $docs = array_slice($docs, 0, $limit);
        if (!$docs) {
            return ['updated' => 0, 'errors' => 0, 'message' => 'Verificação de cancelamentos retroativos: nenhuma NF-e não lançada elegível.'];
        }

        $collector = $this->collectors['nfe'];
        $collector->setCompanyContext($company);
        $checked = 0;
        $cancelled = 0;
        $updated = 0;
        $errors = 0;

        foreach ($docs as $doc) {
            $key = preg_replace('/\D+/', '', (string)$doc['access_key']);
            try {
                $result = $collector->collectByAccessKey($key);
                $statusResult = method_exists($collector, 'queryProtocolStatus') ? $collector->queryProtocolStatus($key) : ['updated' => 0, 'message' => 'Consulta de situação indisponível.'];
                $checked++;
                $updated += (int)($result['updated'] ?? 0) + (int)($statusResult['updated'] ?? 0);
                $message = (string)($result['message'] ?? '');
                if (str_contains(mb_strtolower($message), 'bloquead') || str_contains($message, 'Consumo Indevido')) {
                    break;
                }
                $after = $this->repo->findDocumentByAccessKey('NFE', $key, (int)$company['id']);
                if ($after && (string)($after['status'] ?? '') === 'cancelado') {
                    $cancelled++;
                }
            } catch (\Throwable $e) {
                $errors++;
            }
        }

        return [
            'updated' => $updated,
            'errors' => $errors,
            'message' => 'Verificação de cancelamentos retroativos: ' . $checked . ' chave(s), ' . $cancelled . ' cancelada(s), ' . $errors . ' erro(s).',
        ];
    }
}
