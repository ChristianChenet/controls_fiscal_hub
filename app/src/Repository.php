<?php
declare(strict_types=1);

namespace ControlS\Portal;

use PDO;
use PDOStatement;

final class Repository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function getSetting(string $key, ?string $default = null): ?string
    {
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE key = :key");
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) $value;
    }

    public function setSetting(string $key, ?string $value): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO settings(key, value, updated_at) VALUES(:key, :value, :updated_at)
            ON CONFLICT(key) DO UPDATE SET value=EXCLUDED.value, updated_at=EXCLUDED.updated_at");
        $stmt->execute(['key'=>$key,'value'=>$value,'updated_at'=>date('c')]);
    }

    public function ensureDefaultAdmin(string $email, string $password): void
    {
        $count = (int)$this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $this->saveUser([
            'name' => 'Administrador',
            'email' => $email,
            'password' => $password,
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function users(): array
    {
        return $this->pdo->query('SELECT id, name, email, role, can_view_revenue, can_view_cost, is_active, created_at, updated_at FROM users ORDER BY name ASC')->fetchAll();
    }

    public function findUser(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findUserByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE lower(email) = lower(:email) LIMIT 1');
        $stmt->execute(['email' => trim($email)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function saveUser(array $data): int
    {
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['name'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $role = (string)($data['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $canViewRevenue = $role === 'admin' || !empty($data['can_view_revenue']);
        $canViewCost = $role === 'admin' || ($canViewRevenue && !empty($data['can_view_cost']));
        $active = !empty($data['is_active']);
        $password = (string)($data['password'] ?? '');

        if ($name === '' || $email === '') {
            throw new \RuntimeException('Informe nome e e-mail do usuario.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('E-mail do usuario invalido.');
        }

        if ($id > 0) {
            $user = $this->findUser($id);
            if (!$user) {
                throw new \RuntimeException('Usuario nao encontrado.');
            }
            $fields = 'name = :name, email = :email, role = :role, can_view_revenue = :can_view_revenue, can_view_cost = :can_view_cost, is_active = :is_active, updated_at = :updated_at';
            $params = [
                'id' => $id,
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'can_view_revenue' => $canViewRevenue,
                'can_view_cost' => $canViewCost,
                'is_active' => $active,
                'updated_at' => date('c'),
            ];
            if ($password !== '') {
                $fields .= ', password_hash = :password_hash';
                $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }
            $stmt = $this->pdo->prepare("UPDATE users SET {$fields} WHERE id = :id");
            foreach ($params as $paramName => $paramValue) {
                $paramType = match ($paramName) {
                    'id' => PDO::PARAM_INT,
                    'can_view_revenue', 'can_view_cost', 'is_active' => PDO::PARAM_BOOL,
                    default => PDO::PARAM_STR,
                };
                $stmt->bindValue(':' . $paramName, $paramValue, $paramType);
            }
            $stmt->execute();
            return $id;
        }

        if ($password === '') {
            throw new \RuntimeException('Informe uma senha para criar o usuario.');
        }
        $stmt = $this->pdo->prepare('INSERT INTO users(name, email, password_hash, role, can_view_revenue, can_view_cost, is_active, created_at, updated_at)
            VALUES(:name, :email, :password_hash, :role, :can_view_revenue, :can_view_cost, :is_active, :created_at, :updated_at)');
        $params = [
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'can_view_revenue' => $canViewRevenue,
            'can_view_cost' => $canViewCost,
            'is_active' => $active,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
        foreach ($params as $paramName => $paramValue) {
            $paramType = match ($paramName) {
                'can_view_revenue', 'can_view_cost', 'is_active' => PDO::PARAM_BOOL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue(':' . $paramName, $paramValue, $paramType);
        }
        $stmt->execute();
        return (int)$this->pdo->lastInsertId();
    }

    public function companies(): array
    {
        return $this->pdo->query("SELECT * FROM companies ORDER BY company_name ASC")->fetchAll();
    }

    public function activeCompanies(): array
    {
        return $this->pdo->query("SELECT * FROM companies WHERE is_active = TRUE ORDER BY company_name ASC")->fetchAll();
    }

    public function findCompany(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM companies WHERE id = :id");
        $stmt->execute(['id'=>$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findCompanyByCnpj(string $cnpj): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM companies WHERE cnpj = :cnpj LIMIT 1");
        $stmt->execute(['cnpj'=>$cnpj]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function saveCompany(array $data): int
    {
        $cnpj = preg_replace('/\D+/', '', (string)($data['cnpj'] ?? ''));
        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $stmt = $this->pdo->prepare("UPDATE companies SET company_name=:company_name, cnpj=:cnpj, default_download_dir=:default_download_dir, is_active=:is_active, updated_at=:updated_at WHERE id=:id");
            $stmt->execute([
                'id'=>$id,
                'company_name'=>$data['company_name'],
                'cnpj'=>$cnpj,
                'default_download_dir'=>$data['default_download_dir'] ?: null,
                'is_active'=>!empty($data['is_active']),
                'updated_at'=>date('c'),
            ]);
            return $id;
        }

        $stmt = $this->pdo->prepare("INSERT INTO companies(company_name, cnpj, default_download_dir, is_active, created_at, updated_at)
            VALUES(:company_name,:cnpj,:default_download_dir,:is_active,:created_at,:updated_at)");
        $stmt->execute([
            'company_name'=>$data['company_name'],
            'cnpj'=>$cnpj,
            'default_download_dir'=>$data['default_download_dir'] ?: null,
            'is_active'=>!empty($data['is_active']),
            'created_at'=>date('c'),
            'updated_at'=>date('c'),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function companyDeleteBlockers(int $companyId): array
    {
        $checks = [
            'certificados' => 'SELECT COUNT(*) FROM certificates WHERE company_id = :company_id',
            'documentos' => 'SELECT COUNT(*) FROM documents WHERE company_id = :company_id',
            'jobs' => 'SELECT COUNT(*) FROM jobs WHERE company_id = :company_id',
            'auditoria' => 'SELECT COUNT(*) FROM actions_log WHERE company_id = :company_id',
            'controle de distribuição' => 'SELECT COUNT(*) FROM distribution_controls WHERE company_id = :company_id',
            'itens de fechamento' => 'SELECT COUNT(*) FROM period_closure_items WHERE company_id = :company_id',
        ];

        $blockers = [];
        foreach ($checks as $label => $sql) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['company_id' => $companyId]);
            $count = (int)$stmt->fetchColumn();
            if ($count > 0) {
                $blockers[$label] = $count;
            }
        }

        $stmt = $this->pdo->query('SELECT id, company_ids FROM period_closures');
        foreach ($stmt->fetchAll() as $closure) {
            $ids = json_decode((string)($closure['company_ids'] ?? ''), true);
            if (is_array($ids) && in_array($companyId, array_map('intval', $ids), true)) {
                $blockers['fechamentos por período'] = ($blockers['fechamentos por período'] ?? 0) + 1;
            }
        }

        return $blockers;
    }

    public function deleteCompanyIfUnlinked(int $companyId): void
    {
        $company = $this->findCompany($companyId);
        if (!$company) {
            throw new \RuntimeException('Empresa não encontrada.');
        }

        $blockers = $this->companyDeleteBlockers($companyId);
        if ($blockers) {
            $messages = [];
            foreach ($blockers as $label => $count) {
                $messages[] = $label . ': ' . $count;
            }
            throw new \RuntimeException('Empresa não pode ser excluída porque possui vínculo no banco: ' . implode(', ', $messages) . '.');
        }

        $stmt = $this->pdo->prepare('DELETE FROM companies WHERE id = :id');
        $stmt->execute(['id' => $companyId]);
    }

    public function insertCertificate(array $data): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO certificates
            (company_id, filename, storage_path, password_enc, subject_name, thumbprint, valid_from, valid_to, serial_number, is_active, created_at)
            VALUES (:company_id, :filename, :storage_path, :password_enc, :subject_name, :thumbprint, :valid_from, :valid_to, :serial_number, :is_active, :created_at)");
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function deactivateCertificates(?int $companyId = null): void
    {
        if ($companyId) {
            $stmt = $this->pdo->prepare("UPDATE certificates SET is_active = FALSE WHERE company_id = :company_id");
            $stmt->execute(['company_id'=>$companyId]);
            return;
        }
        $this->pdo->exec("UPDATE certificates SET is_active = FALSE");
    }

    public function getActiveCertificate(?int $companyId = null): ?array
    {
        if ($companyId) {
            $stmt = $this->pdo->prepare("SELECT c.*, co.company_name, co.cnpj AS company_cnpj FROM certificates c JOIN companies co ON co.id = c.company_id WHERE c.company_id = :company_id AND c.is_active = TRUE ORDER BY c.id DESC LIMIT 1");
            $stmt->execute(['company_id'=>$companyId]);
        } else {
            $stmt = $this->pdo->query("SELECT c.*, co.company_name, co.cnpj AS company_cnpj FROM certificates c JOIN companies co ON co.id = c.company_id WHERE c.is_active = TRUE ORDER BY c.id DESC LIMIT 1");
        }
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getActiveCertificateByCnpjRoot(string $cnpjRoot, ?int $excludeCompanyId = null): ?array
    {
        $cnpjRoot = substr(preg_replace('/\D+/', '', $cnpjRoot), 0, 8);
        if ($cnpjRoot === '') {
            return null;
        }

        $stmt = $this->pdo->query("SELECT c.*, co.company_name, co.cnpj AS company_cnpj FROM certificates c JOIN companies co ON co.id = c.company_id WHERE c.is_active = TRUE ORDER BY c.id DESC");
        foreach ($stmt->fetchAll() as $row) {
            if ($excludeCompanyId !== null && (int)$row['company_id'] === $excludeCompanyId) {
                continue;
            }
            $companyRoot = substr(preg_replace('/\D+/', '', (string)$row['company_cnpj']), 0, 8);
            if ($companyRoot === $cnpjRoot) {
                return $row;
            }
        }

        return null;
    }

    public function saveDocument(array $data): array
    {
        $existing = null;
        if (!empty($data['digest'])) {
            $stmt = $this->pdo->prepare("SELECT * FROM documents WHERE digest = :digest LIMIT 1");
            $stmt->execute(['digest'=>$data['digest']]);
            $existing = $stmt->fetch();
        }
        if (!$existing && !empty($data['access_key']) && !empty($data['doc_type']) && !empty($data['company_id'])) {
            $stmt = $this->pdo->prepare("SELECT * FROM documents WHERE company_id = :company_id AND doc_type = :doc_type AND access_key = :access_key LIMIT 1");
            $stmt->execute(['company_id'=>$data['company_id'],'doc_type'=>$data['doc_type'],'access_key'=>$data['access_key']]);
            $existing = $stmt->fetch();
        }
        if (!$existing && strtoupper((string)($data['doc_type'] ?? '')) === 'NFSE' && !empty($data['number']) && !empty($data['issuer_cnpj'])) {
            $companyCnpj = preg_replace('/\D+/', '', (string)($data['company_cnpj'] ?? ''));
            if ($companyCnpj === '' && !empty($data['company_id'])) {
                $company = $this->findCompany((int)$data['company_id']);
                $companyCnpj = preg_replace('/\D+/', '', (string)($company['cnpj'] ?? ''));
            }
            $issuerCnpj = preg_replace('/\D+/', '', (string)$data['issuer_cnpj']);
            $numberDigits = preg_replace('/\D+/', '', (string)$data['number']);
            if ($companyCnpj !== '' && $issuerCnpj !== '' && $numberDigits !== '') {
                $normalizedNumbers = $this->accountingNumberVariants((string)$data['number'], (string)($data['issue_date'] ?? ''));
                $variantPlaceholders = [];
                foreach ($normalizedNumbers as $idx => $variant) {
                    $variantPlaceholders[] = ':number_variant_' . $idx;
                }
                $normalizedNumberSql = $this->nfseNumberComparableSql($this->digitsOnlySql('number'));
                $stmt = $this->pdo->prepare("SELECT * FROM documents
                    WHERE doc_type = 'NFSE'
                      AND {$this->digitsOnlySql('company_cnpj')} = :company_cnpj
                      AND {$this->digitsOnlySql('issuer_cnpj')} = :issuer_cnpj
                      AND (
                          {$this->digitsOnlySql('number')} = :number_digits
                          OR COALESCE(NULLIF(LTRIM({$this->digitsOnlySql('number')}, '0'), ''), '0') IN (" . implode(',', $variantPlaceholders) . ")
                          OR {$normalizedNumberSql} IN (" . implode(',', $variantPlaceholders) . ")
                      )
                    ORDER BY id DESC
                    LIMIT 1");
                $params = [
                    'company_cnpj' => $companyCnpj,
                    'issuer_cnpj' => $issuerCnpj,
                    'number_digits' => $numberDigits,
                ];
                foreach ($normalizedNumbers as $idx => $variant) {
                    $params['number_variant_' . $idx] = $variant;
                }
                $stmt->execute($params);
                $existing = $stmt->fetch();
            }
        }

        $row = $this->normalizeDocumentRow($data);
        $this->applyNfeRecipientCompanyToDocumentRow($row);
        $this->applyCteTakerCompanyToDocumentRow($row);
        if ($existing) {
            foreach (['access_key', 'referenced_nfe_keys', 'referenced_document_numbers', 'xml_path', 'storage_dir', 'notes', 'raw_xml', 'schema_name'] as $key) {
                if (trim((string)($row[$key] ?? '')) === '' && trim((string)($existing[$key] ?? '')) !== '') {
                    $row[$key] = $existing[$key];
                }
            }
            foreach (['issue_date', 'issuer_name', 'issuer_cnpj', 'recipient_name', 'recipient_cnpj'] as $key) {
                if (($row[$key] ?? null) === null || trim((string)($row[$key] ?? '')) === '') {
                    $row[$key] = $existing[$key] ?? $row[$key];
                }
            }
            if ((float)($row['total_value'] ?? 0) === 0.0 && (float)($existing['total_value'] ?? 0) > 0) {
                $row['total_value'] = $existing['total_value'];
            }
            $row['id'] = $existing['id'];
            $stmt = $this->pdo->prepare("UPDATE documents SET
                company_id=:company_id, company_name=:company_name, company_cnpj=:company_cnpj, doc_type=:doc_type, model=:model, access_key=:access_key, referenced_nfe_keys=:referenced_nfe_keys, referenced_document_numbers=:referenced_document_numbers, number=:number, order_number=:order_number, posted_to_erp=:posted_to_erp,
                issuer_cnpj=:issuer_cnpj, issuer_name=:issuer_name, recipient_cnpj=:recipient_cnpj, recipient_name=:recipient_name,
                issue_date=:issue_date, total_value=:total_value, status=:status, manifestation_status=:manifestation_status,
                source=:source, xml_path=:xml_path, storage_dir=:storage_dir, notes=:notes, raw_xml=:raw_xml, digest=:digest,
                schema_name=:schema_name, updated_at=:updated_at WHERE id=:id");
            $this->executeDocumentStatement($stmt, $row, [
                'id','company_id','company_name','company_cnpj','doc_type','model','access_key','referenced_nfe_keys','referenced_document_numbers','number','order_number','posted_to_erp',
                'issuer_cnpj','issuer_name','recipient_cnpj','recipient_name','issue_date','total_value','status','manifestation_status',
                'source','xml_path','storage_dir','notes','raw_xml','digest','schema_name','updated_at',
            ]);
            $id = (int)$existing['id'];
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO documents
                (company_id, company_name, company_cnpj, doc_type, model, access_key, referenced_nfe_keys, referenced_document_numbers, number, order_number, posted_to_erp, issuer_cnpj, issuer_name, recipient_cnpj, recipient_name,
                issue_date, total_value, status, manifestation_status, source, xml_path, storage_dir, notes, raw_xml, digest, schema_name, imported_at, updated_at)
                VALUES (:company_id, :company_name, :company_cnpj, :doc_type, :model, :access_key, :referenced_nfe_keys, :referenced_document_numbers, :number, :order_number, :posted_to_erp, :issuer_cnpj, :issuer_name, :recipient_cnpj, :recipient_name,
                :issue_date, :total_value, :status, :manifestation_status, :source, :xml_path, :storage_dir, :notes, :raw_xml, :digest, :schema_name, :imported_at, :updated_at)");
            $this->executeDocumentStatement($stmt, $row, [
                'company_id','company_name','company_cnpj','doc_type','model','access_key','referenced_nfe_keys','referenced_document_numbers','number','order_number','posted_to_erp',
                'issuer_cnpj','issuer_name','recipient_cnpj','recipient_name','issue_date','total_value','status','manifestation_status',
                'source','xml_path','storage_dir','notes','raw_xml','digest','schema_name','imported_at','updated_at',
            ]);
            $id = (int)$this->pdo->lastInsertId();
        }
        $this->linkEventsToDocument($id);
        return $this->findDocument($id);
    }

    private function linkEventsToDocument(int $documentId): void
    {
        $document = $this->findDocument($documentId);
        $accessKey = preg_replace('/\D+/', '', (string)($document['access_key'] ?? ''));
        if (!$document || strlen($accessKey) !== 44) {
            return;
        }
        $stmt = $this->pdo->prepare('UPDATE document_events SET document_id = :document_id, company_id = COALESCE(company_id, :company_id) WHERE access_key = :access_key');
        $stmt->execute([
            'document_id' => $documentId,
            'company_id' => $document['company_id'] ?? null,
            'access_key' => $accessKey,
        ]);
    }

    public function saveDocumentEvent(array $data): void
    {
        $accessKey = preg_replace('/\D+/', '', (string)($data['access_key'] ?? ''));
        if (strlen($accessKey) !== 44) {
            return;
        }
        $digest = (string)($data['digest'] ?? hash('sha256', (string)($data['raw_xml'] ?? json_encode($data))));
        $document = $this->findDocumentByAccessKey('NFE', $accessKey, !empty($data['company_id']) ? (int)$data['company_id'] : null)
            ?: $this->findDocumentByAccessKey('NFCE', $accessKey, !empty($data['company_id']) ? (int)$data['company_id'] : null)
            ?: $this->findDocumentByAccessKey('CTE', $accessKey, !empty($data['company_id']) ? (int)$data['company_id'] : null);

        $stmt = $this->pdo->prepare("INSERT INTO document_events(company_id, document_id, access_key, event_type, event_name, event_date, protocol, issuer_cnpj, schema_name, raw_xml, digest, created_at)
            VALUES(:company_id, :document_id, :access_key, :event_type, :event_name, :event_date, :protocol, :issuer_cnpj, :schema_name, :raw_xml, :digest, :created_at)
            ON CONFLICT(digest) DO UPDATE SET document_id=EXCLUDED.document_id, company_id=EXCLUDED.company_id");
        $stmt->execute([
            'company_id' => $data['company_id'] ?? ($document['company_id'] ?? null),
            'document_id' => $document['id'] ?? null,
            'access_key' => $accessKey,
            'event_type' => $data['event_type'] ?? null,
            'event_name' => $data['event_name'] ?? null,
            'event_date' => $data['event_date'] ?? null,
            'protocol' => $data['protocol'] ?? null,
            'issuer_cnpj' => $data['issuer_cnpj'] ?? null,
            'schema_name' => $data['schema_name'] ?? null,
            'raw_xml' => $data['raw_xml'] ?? null,
            'digest' => $digest,
            'created_at' => date('c'),
        ]);
        if ($this->isCancellationEvent($data) && $document) {
            $this->markDocumentCancelledFromEvent((int)$document['id'], (string)($data['event_date'] ?? ''), (string)($data['protocol'] ?? ''));
        }
    }

    private function attachEventSummaries(array $documents): array
    {
        $keys = [];
        foreach ($documents as $doc) {
            $key = preg_replace('/\D+/', '', (string)($doc['access_key'] ?? ''));
            if (strlen($key) === 44) {
                $keys[$key] = true;
            }
        }
        if (!$keys) {
            return $documents;
        }

        $placeholders = [];
        $params = [];
        foreach (array_keys($keys) as $idx => $key) {
            $param = 'event_key_' . $idx;
            $placeholders[] = ':' . $param;
            $params[$param] = $key;
        }
        $stmt = $this->pdo->prepare('SELECT access_key, COUNT(*) AS total, MAX(event_date) AS last_event_date, ' .
            $this->stringAggregateExpression('event_name') . ' AS event_names FROM document_events WHERE access_key IN (' . implode(',', $placeholders) . ') GROUP BY access_key');
        $stmt->execute($params);
        $events = [];
        foreach ($stmt->fetchAll() as $row) {
            $events[(string)$row['access_key']] = $row;
        }
        foreach ($documents as &$doc) {
            $key = preg_replace('/\D+/', '', (string)($doc['access_key'] ?? ''));
            $event = $events[$key] ?? null;
            $doc['informative_events_count'] = $event ? (int)$event['total'] : 0;
            $doc['informative_events_names'] = $event['event_names'] ?? '';
            $doc['informative_events_last_date'] = $event['last_event_date'] ?? null;
        }
        unset($doc);
        return $documents;
    }

    private function stringAggregateExpression(string $column): string
    {
        return (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? "GROUP_CONCAT(DISTINCT {$column})"
            : "STRING_AGG(DISTINCT {$column}, ', ' ORDER BY {$column})";
    }

    private function normalizeDocumentRow(array $data): array
    {
        $row = $data + [
            'company_id' => null,
            'company_name' => null,
            'company_cnpj' => null,
            'doc_type' => null,
            'model' => null,
            'access_key' => null,
            'referenced_nfe_keys' => null,
            'referenced_document_numbers' => null,
            'number' => null,
            'order_number' => null,
            'posted_to_erp' => false,
            'accounting_posted' => 'N',
            'issuer_cnpj' => null,
            'issuer_name' => null,
            'recipient_cnpj' => null,
            'recipient_name' => null,
            'issue_date' => null,
            'total_value' => 0,
            'status' => 'imported',
            'manifestation_status' => 'not_applicable',
            'source' => 'manual_import',
            'xml_path' => null,
            'storage_dir' => null,
            'notes' => null,
            'raw_xml' => null,
            'digest' => null,
            'schema_name' => null,
            'imported_at' => date('c'),
            'updated_at' => date('c'),
        ];
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $row[$key] = $this->normalizeUtf8String($value);
            }
        }
        $row['posted_to_erp'] = $this->normalizeBoolean($row['posted_to_erp'] ?? false);
        $row['referenced_nfe_keys'] = $this->normalizeReferencedKeys((string)($row['referenced_nfe_keys'] ?? ''), (string)($row['access_key'] ?? '')) ?: null;
        $row['referenced_document_numbers'] = $this->normalizeReferencedNumbers((string)($row['referenced_document_numbers'] ?? ''), (string)($row['access_key'] ?? '')) ?: null;
        return $row;
    }

    private function normalizeUtf8String(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (!mb_check_encoding($value, 'UTF-8')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
            if (is_string($converted) && $converted !== '') {
                $value = $converted;
            } else {
                $fallback = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
                $value = is_string($fallback) ? $fallback : '';
            }
        }
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
        return is_string($clean) ? $clean : $value;
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return ((int)$value) === 1;
        }
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 't', 'yes', 'y', 'sim', 's'], true);
    }

    private function executeDocumentStatement(PDOStatement $stmt, array $row, array $keys): void
    {
        // PostgreSQL nao aceita string vazia em coluna boolean. O bind explicito
        // impede que XMLs de coleta travem o ciclo por tipo invalido.
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;
            if ($key === 'posted_to_erp') {
                $stmt->bindValue(':' . $key, $this->normalizeBoolean($value), PDO::PARAM_BOOL);
                continue;
            }
            if ($value === null) {
                $stmt->bindValue(':' . $key, null, PDO::PARAM_NULL);
                continue;
            }
            if ($key === 'id' || $key === 'company_id') {
                $stmt->bindValue(':' . $key, (int)$value, PDO::PARAM_INT);
                continue;
            }
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();
    }

    public function findDocument(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM documents WHERE id = :id");
        $stmt->execute(['id'=>$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findDocumentByDigest(string $digest): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM documents WHERE digest = :digest LIMIT 1");
        $stmt->execute(['digest'=>$digest]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findDocumentByAccessKey(string $docType, string $accessKey, ?int $companyId = null): ?array
    {
        if ($companyId) {
            $stmt = $this->pdo->prepare("SELECT * FROM documents WHERE company_id = :company_id AND doc_type = :doc_type AND access_key = :access_key LIMIT 1");
            $stmt->execute(['company_id'=>$companyId,'doc_type'=>$docType,'access_key'=>$accessKey]);
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM documents WHERE doc_type = :doc_type AND access_key = :access_key LIMIT 1");
            $stmt->execute(['doc_type'=>$docType,'access_key'=>$accessKey]);
        }
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateDocumentStatuses(array $ids, string $manifestationStatus, ?string $status = null): int
    {
        if (!$ids) return 0;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_map('intval', $ids);
        if ($status !== null) {
            $sql = "UPDATE documents SET manifestation_status = ?, status = ?, updated_at = ? WHERE id IN ($placeholders)";
            $params = array_merge([$manifestationStatus, $status, date('c')], $params);
        } else {
            $sql = "UPDATE documents SET manifestation_status = ?, updated_at = ? WHERE id IN ($placeholders)";
            $params = array_merge([$manifestationStatus, date('c')], $params);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function updateDocumentManifestedByAccessKey(string $docType, string $accessKey, string $manifestationStatus, ?string $status = null, ?string $notes = null, ?int $companyId = null): void
    {
        $sql = "UPDATE documents SET manifestation_status = :manifestation_status, updated_at = :updated_at";
        $params = ['manifestation_status'=>$manifestationStatus,'updated_at'=>date('c'),'doc_type'=>$docType,'access_key'=>$accessKey];
        if ($status !== null) { $sql .= ", status = :status"; $params['status'] = $status; }
        if ($notes !== null) { $sql .= ", notes = :notes"; $params['notes'] = $notes; }
        $sql .= " WHERE doc_type = :doc_type AND access_key = :access_key";
        if ($companyId) { $sql .= " AND company_id = :company_id"; $params['company_id'] = $companyId; }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function accountingImportExists(string $docType, string $fileName): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM accounting_imports WHERE doc_type = :doc_type AND file_name = :file_name LIMIT 1');
        $stmt->execute(['doc_type' => strtoupper(trim($docType)), 'file_name' => trim($fileName)]);
        return (bool)$stmt->fetchColumn();
    }

    public function deleteAccountingImportsForFile(string $docType, string $fileName): int
    {
        $docType = strtoupper(trim($docType));
        $fileName = trim($fileName);
        $stmt = $this->pdo->prepare('SELECT DISTINCT matched_document_id FROM accounting_entries e JOIN accounting_imports i ON i.id = e.import_id WHERE i.doc_type = :doc_type AND i.file_name = :file_name AND e.matched_document_id IS NOT NULL');
        $stmt->execute(['doc_type' => $docType, 'file_name' => $fileName]);
        $documentIds = array_map(static fn(array $row): int => (int)$row['matched_document_id'], $stmt->fetchAll());

        $delete = $this->pdo->prepare('DELETE FROM accounting_imports WHERE doc_type = :doc_type AND file_name = :file_name');
        $delete->execute(['doc_type' => $docType, 'file_name' => $fileName]);
        $deleted = $delete->rowCount();

        $this->refreshAccountingPostedForDocuments($documentIds);
        return $deleted;
    }

    public function importAccountingEntries(string $docType, string $fileName, array $sheets, array $mapping, ?array $user = null, bool $replaceExisting = false, bool $appendExisting = false): array
    {
        $docType = strtoupper(trim($docType));
        $fileName = trim($fileName);
        if (!in_array($docType, ['NFE', 'CTE', 'NFSE'], true)) {
            throw new \InvalidArgumentException('Tipo invalido para importacao da contabilidade.');
        }

        $rows = [];
        foreach ($sheets as $sheet) {
            $sheetName = trim((string)($sheet['name'] ?? ''));
            foreach (($sheet['rows'] ?? []) as $idx => $row) {
                if (!is_array($row) || !$this->accountingRowHasValue($row)) {
                    continue;
                }
                $rows[] = [
                    'sheet_name' => $sheetName,
                    'row_number' => (int)($row['_rowNumber'] ?? ($idx + 2)),
                    'raw' => $row,
                ];
            }
        }
        if (!$rows) {
            throw new \InvalidArgumentException('Nenhuma linha valida foi encontrada na planilha.');
        }

        $this->pdo->beginTransaction();
        try {
            if ($replaceExisting) {
                $this->deleteAccountingImportsForFile($docType, $fileName);
            } elseif (!$appendExisting && $this->accountingImportExists($docType, $fileName)) {
                throw new \RuntimeException('Esta planilha ja foi importada para este tipo.');
            }
            $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $insertSql = 'INSERT INTO accounting_imports(doc_type, file_name, sheets_count, row_count, matched_count, missing_count, mapping_json, user_id, user_name, created_at)
                VALUES(:doc_type, :file_name, :sheets_count, :row_count, 0, 0, :mapping_json, :user_id, :user_name, :created_at)';
            if ($driver !== 'sqlite') {
                $insertSql .= ' RETURNING id';
            }
            $insertImport = $this->pdo->prepare($insertSql);
            $insertImport->execute([
                'doc_type' => $docType,
                'file_name' => $fileName,
                'sheets_count' => count($sheets),
                'row_count' => count($rows),
                'mapping_json' => json_encode($mapping, JSON_UNESCAPED_UNICODE),
                'user_id' => $user['id'] ?? null,
                'user_name' => $user['name'] ?? null,
                'created_at' => date('c'),
            ]);
            $importId = $driver === 'sqlite' ? (int)$this->pdo->lastInsertId() : (int)$insertImport->fetchColumn();
            $insertEntry = $this->pdo->prepare('INSERT INTO accounting_entries(import_id, doc_type, sheet_name, row_number, access_key, document_number, party_document, matched_document_id, raw_json, created_at)
                VALUES(:import_id, :doc_type, :sheet_name, :row_number, :access_key, :document_number, :party_document, :matched_document_id, :raw_json, :created_at)');

            $matchedIds = [];
            $missing = 0;
            foreach ($rows as $entry) {
                $raw = $entry['raw'];
                $accessKey = $docType === 'NFSE' ? '' : $this->digits((string)($raw[(string)($mapping['access_key'] ?? '')] ?? ''));
                $documentNumber = $docType === 'NFSE' ? trim((string)($raw[(string)($mapping['number'] ?? '')] ?? '')) : '';
                $partyDocument = $docType === 'NFSE' ? $this->digits((string)($raw[(string)($mapping['party_document'] ?? '')] ?? '')) : '';
                $accountingIssueDate = $docType === 'NFSE' ? $this->parseAccountingDate((string)($raw[(string)($mapping['issue_date'] ?? '')] ?? '')) : null;
                $document = $docType === 'NFSE'
                    ? $this->findAccountingNFSeDocument($documentNumber, $partyDocument, $accountingIssueDate)
                    : $this->findAccountingAccessKeyDocument($docType, $accessKey);
                $documentId = $document ? (int)$document['id'] : null;
                if ($documentId) {
                    $matchedIds[$documentId] = true;
                } else {
                    $missing++;
                }
                $insertEntry->execute([
                    'import_id' => $importId,
                    'doc_type' => $docType,
                    'sheet_name' => $entry['sheet_name'],
                    'row_number' => $entry['row_number'],
                    'access_key' => $accessKey !== '' ? $accessKey : null,
                    'document_number' => $documentNumber !== '' ? $documentNumber : null,
                    'party_document' => $partyDocument !== '' ? $partyDocument : null,
                    'matched_document_id' => $documentId,
                    'raw_json' => json_encode($raw, JSON_UNESCAPED_UNICODE),
                    'created_at' => date('c'),
                ]);
            }

            if ($matchedIds) {
                $ids = array_map('intval', array_keys($matchedIds));
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $params = array_merge(['S', date('c')], $ids);
                $stmt = $this->pdo->prepare("UPDATE documents SET accounting_posted = ?, updated_at = ? WHERE id IN ($placeholders)");
                $stmt->execute($params);
            }
            $matchedCount = count($matchedIds);
            $updateImport = $this->pdo->prepare('UPDATE accounting_imports SET matched_count = :matched_count, missing_count = :missing_count WHERE id = :id');
            $updateImport->execute(['matched_count' => $matchedCount, 'missing_count' => $missing, 'id' => $importId]);
            $this->pdo->commit();
            return ['import_id' => $importId, 'sheets_count' => count($sheets), 'row_count' => count($rows), 'matched_count' => $matchedCount, 'missing_count' => $missing];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function accountingEntriesForDocument(int $documentId): array
    {
        $stmt = $this->pdo->prepare('SELECT e.*, i.file_name, i.created_at AS import_created_at, i.user_name AS import_user_name
            FROM accounting_entries e
            JOIN accounting_imports i ON i.id = e.import_id
            WHERE e.matched_document_id = :document_id
            ORDER BY i.created_at DESC, e.id DESC
            LIMIT 100');
        $stmt->execute(['document_id' => $documentId]);
        return $stmt->fetchAll();
    }

    public function accountingMissingEntries(?string $docType = null, int $limit = 500, string $supplier = '', string $number = ''): array
    {
        $where = ['e.matched_document_id IS NULL'];
        $params = [];
        $docType = strtoupper((string)$docType);
        if (in_array($docType, ['NFE', 'CTE', 'NFSE'], true)) {
            $where[] = 'e.doc_type = :doc_type';
            $params['doc_type'] = $docType;
        }
        $supplier = trim($supplier);
        if ($supplier !== '') {
            $where[] = '(COALESCE(e.raw_json, \'\') ILIKE :supplier OR COALESCE(e.party_document, \'\') ILIKE :supplier_digits)';
            $params['supplier'] = '%' . $supplier . '%';
            $params['supplier_digits'] = '%' . $this->digits($supplier) . '%';
        }
        $number = trim($number);
        if ($number !== '') {
            $where[] = '(COALESCE(e.document_number, \'\') ILIKE :number OR COALESCE(e.access_key, \'\') ILIKE :number OR COALESCE(e.raw_json, \'\') ILIKE :number)';
            $params['number'] = '%' . $number . '%';
        }
        $stmt = $this->pdo->prepare('SELECT e.*, i.file_name, i.created_at AS import_created_at, i.user_name AS import_user_name
            FROM accounting_entries e
            JOIN accounting_imports i ON i.id = e.import_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY i.created_at DESC, e.id DESC
            LIMIT :limit');
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', max(1, min(20000, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function launchAccountingEntriesToPortal(array $entryIds, array $mapping, array $sheetCompanies, ?array $user = null): array
    {
        $entryIds = array_values(array_unique(array_filter(array_map('intval', $entryIds))));
        if (!$entryIds) {
            throw new \InvalidArgumentException('Selecione ao menos uma nota da contabilidade.');
        }

        $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
        $stmt = $this->pdo->prepare("SELECT e.*, i.file_name
            FROM accounting_entries e
            JOIN accounting_imports i ON i.id = e.import_id
            WHERE e.id IN ($placeholders)
            ORDER BY e.id ASC");
        $stmt->execute($entryIds);
        $entries = $stmt->fetchAll();
        if (!$entries) {
            throw new \RuntimeException('Nenhuma nota selecionada foi encontrada.');
        }

        $this->pdo->beginTransaction();
        try {
            $created = 0;
            $linked = 0;
            $skipped = 0;
            $documentIds = [];
            $importIds = [];
            $updateEntry = $this->pdo->prepare('UPDATE accounting_entries SET matched_document_id = :document_id WHERE id = :id');

            foreach ($entries as $entry) {
                $raw = json_decode((string)($entry['raw_json'] ?? '{}'), true);
                if (!is_array($raw)) {
                    $raw = [];
                }
                $docType = strtoupper((string)($entry['doc_type'] ?? ''));
                if (!in_array($docType, ['NFE', 'CTE', 'NFSE'], true)) {
                    $skipped++;
                    continue;
                }
                $fileName = (string)($entry['file_name'] ?? '');
                $sheetName = (string)($entry['sheet_name'] ?? '');
                $groupKey = $fileName . '||' . $sheetName;
                $companyId = (int)($sheetCompanies[$groupKey] ?? $sheetCompanies[$sheetName] ?? 0);
                $company = $companyId > 0 ? $this->findCompany($companyId) : null;
                if (!$company) {
                    throw new \RuntimeException('Informe a empresa da planilha ' . $fileName . ' / aba ' . $sheetName . '.');
                }

                if (is_array($mapping['_sheets'][$groupKey] ?? null)) {
                    $entryMapping = $mapping['_sheets'][$groupKey];
                } elseif (is_array($mapping['_sheets'][$sheetName] ?? null)) {
                    $entryMapping = $mapping['_sheets'][$sheetName];
                } else {
                    $entryMapping = $mapping;
                }
                $accessKey = $this->digits($this->accountingMappedValue($raw, $entryMapping, 'access_key'));
                if ($accessKey === '') {
                    $accessKey = $this->digits((string)($entry['access_key'] ?? ''));
                }
                $number = trim($this->accountingMappedValue($raw, $entryMapping, 'number'));
                if ($number === '') {
                    $number = trim((string)($entry['document_number'] ?? ''));
                }
                $issuerDocument = $this->digits($this->accountingMappedValue($raw, $entryMapping, 'issuer_document'));
                if ($issuerDocument === '') {
                    $issuerDocument = $this->digits((string)($entry['party_document'] ?? ''));
                }
                $issuerName = trim($this->accountingMappedValue($raw, $entryMapping, 'issuer_name'));
                $issueDate = $this->parseAccountingDate($this->accountingMappedValue($raw, $entryMapping, 'issue_date'));
                $totalValue = $this->parseAccountingMoney($this->accountingMappedValue($raw, $entryMapping, 'total_value'));

                if ($docType === 'NFSE' && ($number === '' || $issuerDocument === '')) {
                    $skipped++;
                    continue;
                }
                if ($docType !== 'NFSE' && $accessKey === '') {
                    $skipped++;
                    continue;
                }

                $existing = $docType === 'NFSE'
                    ? $this->findAccountingNFSeDocument($number, $issuerDocument, $issueDate)
                    : $this->findAccountingAccessKeyDocument($docType, $accessKey);

                if ($existing) {
                    $documentId = (int)$existing['id'];
                    $linked++;
                } else {
                    $digest = hash('sha256', 'accounting-launch|' . $docType . '|' . (string)($entry['file_name'] ?? '') . '|' . (string)($entry['sheet_name'] ?? '') . '|' . $number . '|' . $issuerDocument . '|' . $accessKey);
                    $document = $this->saveDocument([
                        'company_id' => (int)$company['id'],
                        'company_name' => (string)$company['company_name'],
                        'company_cnpj' => (string)$company['cnpj'],
                        'doc_type' => $docType,
                        'model' => $docType === 'NFE' ? '55' : ($docType === 'CTE' ? '57' : 'NFSE'),
                        'access_key' => $accessKey !== '' ? $accessKey : null,
                        'number' => $number,
                        'posted_to_erp' => false,
                        'accounting_posted' => 'S',
                        'issuer_cnpj' => $issuerDocument !== '' ? $issuerDocument : null,
                        'issuer_name' => $issuerName !== '' ? $issuerName : null,
                        'recipient_cnpj' => (string)$company['cnpj'],
                        'recipient_name' => (string)$company['company_name'],
                        'issue_date' => $issueDate,
                        'total_value' => $totalValue,
                        'status' => 'apenas_resumo',
                        'manifestation_status' => 'not_applicable',
                        'source' => 'contabilidade_planilha',
                        'notes' => 'Documento lancado no portal a partir da planilha da contabilidade. Arquivo: ' . (string)($entry['file_name'] ?? '') . '; aba: ' . (string)($entry['sheet_name'] ?? '') . '; linha: ' . (string)($entry['row_number'] ?? '') . '.',
                        'raw_xml' => $this->buildAccountingRawXml($docType, $entry, $raw, $company, $entryMapping),
                        'digest' => $digest,
                        'schema_name' => 'accounting_spreadsheet',
                    ]);
                    $documentId = (int)($document['id'] ?? 0);
                    $created++;
                }

                if ($documentId > 0) {
                    $updateEntry->execute(['document_id' => $documentId, 'id' => (int)$entry['id']]);
                    $documentIds[$documentId] = true;
                    $importIds[(int)$entry['import_id']] = true;
                }
            }

            if ($documentIds) {
                $ids = array_map('intval', array_keys($documentIds));
                $documentPlaceholders = implode(',', array_fill(0, count($ids), '?'));
                $mark = $this->pdo->prepare("UPDATE documents SET accounting_posted = 'S', updated_at = ? WHERE id IN ($documentPlaceholders)");
                $mark->execute(array_merge([date('c')], $ids));
            }
            if ($importIds) {
                $ids = array_map('intval', array_keys($importIds));
                $importPlaceholders = implode(',', array_fill(0, count($ids), '?'));
                $this->pdo->prepare("UPDATE accounting_imports i SET
                    matched_count = stats.matched,
                    missing_count = stats.missing
                    FROM (
                        SELECT import_id, COUNT(matched_document_id) AS matched, SUM(CASE WHEN matched_document_id IS NULL THEN 1 ELSE 0 END) AS missing
                        FROM accounting_entries
                        WHERE import_id IN ($importPlaceholders)
                        GROUP BY import_id
                    ) stats
                    WHERE i.id = stats.import_id")->execute($ids);
            }

            $this->pdo->commit();
            return ['created_count' => $created, 'linked_count' => $linked, 'skipped_count' => $skipped];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function deduplicateAccountingEntries(): array
    {
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            return ['removed' => 0, 'imports_removed' => 0];
        }
        $documents = $this->pdo->query('SELECT DISTINCT matched_document_id FROM accounting_entries WHERE matched_document_id IS NOT NULL')->fetchAll();
        $documentIds = array_map(static fn(array $row): int => (int)$row['matched_document_id'], $documents);
        $removed = (int)$this->pdo->exec("WITH ranked AS (
            SELECT e.id,
                   ROW_NUMBER() OVER (
                       PARTITION BY i.doc_type, i.file_name, e.sheet_name, e.row_number, COALESCE(e.access_key, ''), COALESCE(e.document_number, ''), COALESCE(e.party_document, ''), COALESCE(e.raw_json, '')
                       ORDER BY e.id DESC
                   ) AS rn
            FROM accounting_entries e
            JOIN accounting_imports i ON i.id = e.import_id
        )
        DELETE FROM accounting_entries e USING ranked r WHERE e.id = r.id AND r.rn > 1");
        $importsRemoved = (int)$this->pdo->exec('DELETE FROM accounting_imports i WHERE NOT EXISTS (SELECT 1 FROM accounting_entries e WHERE e.import_id = i.id)');
        $this->pdo->exec("UPDATE accounting_imports i SET
            row_count = stats.total,
            matched_count = stats.matched,
            missing_count = stats.missing
            FROM (
                SELECT import_id, COUNT(*) AS total, COUNT(matched_document_id) AS matched, COUNT(*) FILTER (WHERE matched_document_id IS NULL) AS missing
                FROM accounting_entries
                GROUP BY import_id
            ) stats
            WHERE i.id = stats.import_id");
        $this->refreshAccountingPostedForDocuments($documentIds);
        return ['removed' => $removed, 'imports_removed' => $importsRemoved];
    }

    private function refreshAccountingPostedForDocuments(array $documentIds): void
    {
        $documentIds = array_values(array_unique(array_filter(array_map('intval', $documentIds))));
        if (!$documentIds) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($documentIds), '?'));
        $reset = $this->pdo->prepare("UPDATE documents SET accounting_posted = 'N', updated_at = ? WHERE id IN ($placeholders)");
        $reset->execute(array_merge([date('c')], $documentIds));
        $mark = $this->pdo->prepare("UPDATE documents SET accounting_posted = 'S', updated_at = ? WHERE id IN ($placeholders) AND EXISTS (SELECT 1 FROM accounting_entries e WHERE e.matched_document_id = documents.id)");
        $mark->execute(array_merge([date('c')], $documentIds));
    }

    private function accountingRowHasValue(array $row): bool
    {
        foreach ($row as $key => $value) {
            if ($key === '_rowNumber') {
                continue;
            }
            if (trim((string)$value) !== '') {
                return true;
            }
        }
        return false;
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function normalizeAccountingNumber(string $value): string
    {
        $value = trim($value);
        $digits = $this->digits($value);
        if ($digits === '') {
            return $value;
        }
        return ltrim($digits, '0') ?: '0';
    }

    private function accountingNumberVariants(string $value, ?string $issueDate = null): array
    {
        $digits = $this->digits($value);
        if ($digits === '') {
            return [];
        }
        $variants = [ltrim($digits, '0') ?: '0'];
        $timestamp = $issueDate ? strtotime($issueDate) : false;
        if ($timestamp) {
            $yyyy = date('Y', $timestamp);
            $yy = date('y', $timestamp);
            if (str_starts_with($digits, $yyyy)) {
                $variants[] = ltrim(substr($digits, 4), '0') ?: '0';
            }
            if (str_starts_with($digits, $yy)) {
                $variants[] = ltrim(substr($digits, 2), '0') ?: '0';
            }
        }
        return array_values(array_unique(array_filter($variants, static fn(string $variant): bool => $variant !== '')));
    }

    private function nfseNumberComparableSql(string $digitsExpression): string
    {
        if ((string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $year = "strftime('%Y', issue_date)";
            $shortYear = "substr(strftime('%Y', issue_date), 3, 2)";
            return "CASE
                WHEN issue_date IS NOT NULL AND substr({$digitsExpression}, 1, 4) = {$year} THEN COALESCE(NULLIF(LTRIM(substr({$digitsExpression}, 5), '0'), ''), '0')
                WHEN issue_date IS NOT NULL AND substr({$digitsExpression}, 1, 2) = {$shortYear} THEN COALESCE(NULLIF(LTRIM(substr({$digitsExpression}, 3), '0'), ''), '0')
                ELSE COALESCE(NULLIF(LTRIM({$digitsExpression}, '0'), ''), '0')
            END";
        }
        $year = "EXTRACT(YEAR FROM issue_date)::TEXT";
        $shortYear = "RIGHT(EXTRACT(YEAR FROM issue_date)::TEXT, 2)";
        return "CASE
            WHEN issue_date IS NOT NULL AND LEFT({$digitsExpression}, 4) = {$year} THEN COALESCE(NULLIF(LTRIM(SUBSTRING({$digitsExpression} FROM 5), '0'), ''), '0')
            WHEN issue_date IS NOT NULL AND LEFT({$digitsExpression}, 2) = {$shortYear} THEN COALESCE(NULLIF(LTRIM(SUBSTRING({$digitsExpression} FROM 3), '0'), ''), '0')
            ELSE COALESCE(NULLIF(LTRIM({$digitsExpression}, '0'), ''), '0')
        END";
    }

    private function accountingMappedValue(array $raw, array $mapping, string $field): string
    {
        $column = trim((string)($mapping[$field] ?? ''));
        if ($column === '') {
            return '';
        }
        return trim((string)($raw[$column] ?? ''));
    }

    private function parseAccountingDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (is_numeric($value)) {
            $serial = (float)$value;
            if ($serial > 20000 && $serial < 80000) {
                $base = new \DateTimeImmutable('1899-12-30 00:00:00');
                return $base->modify('+' . (int)floor($serial) . ' days')->format('Y-m-d H:i:s');
            }
        }
        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof \DateTimeImmutable) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        $timestamp = strtotime(str_replace('/', '-', $value));
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private function parseAccountingMoney(string $value): float
    {
        $value = trim(str_replace(["\xc2\xa0", 'R$'], ' ', $value));
        if ($value === '') {
            return 0.0;
        }
        $value = preg_replace('/[^0-9,\.\-]/', '', $value) ?? '';
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }
        return round((float)$value, 2);
    }

    private function buildAccountingRawXml(string $docType, array $entry, array $raw, array $company, array $mapping): string
    {
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<documentoContabilidade tipo="' . $escape($docType) . '">',
            '  <origem>contabilidade_planilha</origem>',
            '  <arquivo>' . $escape((string)($entry['file_name'] ?? '')) . '</arquivo>',
            '  <aba>' . $escape((string)($entry['sheet_name'] ?? '')) . '</aba>',
            '  <linha>' . $escape((string)($entry['row_number'] ?? '')) . '</linha>',
            '  <empresa cnpj="' . $escape((string)($company['cnpj'] ?? '')) . '">' . $escape((string)($company['company_name'] ?? '')) . '</empresa>',
            '  <numero>' . $escape($this->accountingMappedValue($raw, $mapping, 'number')) . '</numero>',
            '  <emitente documento="' . $escape($this->digits($this->accountingMappedValue($raw, $mapping, 'issuer_document'))) . '">' . $escape($this->accountingMappedValue($raw, $mapping, 'issuer_name')) . '</emitente>',
            '  <valor>' . $escape($this->accountingMappedValue($raw, $mapping, 'total_value')) . '</valor>',
            '  <campos>',
        ];
        foreach ($raw as $key => $value) {
            if ($key === '_rowNumber') {
                continue;
            }
            $lines[] = '    <campo nome="' . $escape((string)$key) . '">' . $escape((string)$value) . '</campo>';
        }
        $lines[] = '  </campos>';
        $lines[] = '</documentoContabilidade>';
        return implode("\n", $lines);
    }

    private function findAccountingAccessKeyDocument(string $docType, string $accessKey): ?array
    {
        if ($accessKey === '') {
            return null;
        }
        $types = $docType === 'NFE' ? ['NFE', 'NFCE'] : [$docType];
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM documents WHERE doc_type IN ($placeholders) AND access_key = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute(array_merge($types, [$accessKey]));
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function findAccountingNFSeDocument(string $number, string $partyDocument, ?string $issueDate = null): ?array
    {
        $normalizedNumbers = $this->accountingNumberVariants($number, $issueDate);
        if (!$normalizedNumbers || $partyDocument === '') {
            return null;
        }
        $issuerDigits = $this->digitsOnlySql('issuer_cnpj');
        $numberDigitsSql = $this->digitsOnlySql('number');
        $comparableNumberSql = $this->nfseNumberComparableSql($numberDigitsSql);
        $variantPlaceholders = [];
        $params = [
            'party_document' => $partyDocument,
            'number_raw' => trim($number),
            'number_digits' => $this->digits($number),
        ];
        foreach ($normalizedNumbers as $idx => $variant) {
            $key = 'number_variant_' . $idx;
            $variantPlaceholders[] = ':' . $key;
            $params[$key] = $variant;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM documents
            WHERE doc_type = 'NFSE'
              AND {$issuerDigits} = :party_document
              AND (
                  number = :number_raw
                  OR {$numberDigitsSql} = :number_digits
                  OR COALESCE(NULLIF(LTRIM({$numberDigitsSql}, '0'), ''), '0') IN (" . implode(',', $variantPlaceholders) . ")
                  OR {$comparableNumberSql} IN (" . implode(',', $variantPlaceholders) . ")
              )
            ORDER BY id DESC
            LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function documents(array $filters = []): array
    {
        if ($this->documentsNeedReferencedNumberRepair($filters)) {
            $this->ensureReferencedDocumentNumbers();
        }
        $this->ensureDocumentItemsForFilters($filters);
        [$where, $params] = $this->documentWhere($filters);
        $sql = 'SELECT * FROM documents';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ' . $this->documentOrderBy($filters);
        if ((string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $sql = str_replace(' NULLS LAST', '', $sql);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->attachEventSummaries($this->cleanReferencedDocumentRows($stmt->fetchAll()));
    }

    private function applyNfeRecipientCompanyToDocumentRow(array &$row): void
    {
        $type = strtoupper((string)($row['doc_type'] ?? ''));
        if ($type !== 'NFE') {
            return;
        }

        $recipientCnpj = preg_replace('/\D+/', '', (string)($row['recipient_cnpj'] ?? ''));
        $issuerCnpj = preg_replace('/\D+/', '', (string)($row['issuer_cnpj'] ?? ''));
        $recipientCompany = $recipientCnpj !== '' ? $this->findCompanyByCnpj($recipientCnpj) : null;

        if ($recipientCompany) {
            $row['company_id'] = (int)$recipientCompany['id'];
            $row['company_name'] = (string)$recipientCompany['company_name'];
            $row['company_cnpj'] = (string)$recipientCompany['cnpj'];
            return;
        }

        if ($issuerCnpj !== '' && $this->findCompanyByCnpj($issuerCnpj)) {
            $row['manifestation_status'] = 'not_applicable';
            if (in_array((string)($row['status'] ?? ''), ['apenas_resumo', 'pendente_manifestacao'], true)) {
                $row['notes'] = trim((string)($row['notes'] ?? '') . ' NF-e de saida emitida por empresa cadastrada; nao entra na rotina de Entradas.');
            }
        }
    }

    private function applyCteTakerCompanyToDocumentRow(array &$row): void
    {
        if (strtoupper((string)($row['doc_type'] ?? '')) !== 'CTE') {
            return;
        }

        $xml = (string)($row['raw_xml'] ?? '');
        $path = (string)($row['xml_path'] ?? '');
        if (trim($xml) === '' && $path !== '' && is_file($path)) {
            $xml = (string)file_get_contents($path);
        }
        if (trim($xml) === '') {
            return;
        }

        $takerCnpj = $this->parseCteTakerCnpjFromXml($xml);
        if ($takerCnpj === '') {
            return;
        }

        $company = $this->findCompanyByCnpj($takerCnpj);
        if (!$company) {
            return;
        }

        $row['company_id'] = (int)$company['id'];
        $row['company_name'] = (string)$company['company_name'];
        $row['company_cnpj'] = (string)$company['cnpj'];
    }

    public function documentIds(array $filters = [], int $limit = 5000): array
    {
        if ($this->documentsNeedReferencedNumberRepair($filters)) {
            $this->ensureReferencedDocumentNumbers();
        }
        $this->ensureDocumentItemsForFilters($filters);
        [$where, $params] = $this->documentWhere($filters);
        $sql = 'SELECT id FROM documents';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ' . $this->documentOrderBy($filters) . ' LIMIT :limit';
        if ((string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $sql = str_replace(' NULLS LAST', '', $sql);
        }
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(static fn(array $row): int => (int)$row['id'], $stmt->fetchAll());
    }

    public function documentsPage(array $filters = [], int $page = 1, int $perPage = 200): array
    {
        if ($this->documentsNeedReferencedNumberRepair($filters)) {
            $this->ensureReferencedDocumentNumbers();
        }
        $this->ensureDocumentItemsForFilters($filters);
        [$where, $params] = $this->documentWhere($filters);
        $offset = max(0, ($page - 1) * $perPage);
        // O grid de Entradas nao precisa trazer o XML bruto de cada linha.
        // Evitar raw_xml na pagina melhora o tempo de resposta em bases grandes.
        $sql = 'SELECT id, company_id, company_name, company_cnpj, doc_type, model, access_key, referenced_nfe_keys, referenced_document_numbers, number, order_number, posted_to_erp, accounting_posted,
                issuer_cnpj, issuer_name, recipient_cnpj, recipient_name, issue_date, total_value, status, manifestation_status,
                source, xml_path, storage_dir, notes, NULL AS raw_xml, digest, schema_name, imported_at, updated_at FROM documents';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ' . $this->documentOrderBy($filters) . ' LIMIT :limit OFFSET :offset';
        if ((string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $sql = str_replace(' NULLS LAST', '', $sql);
        }
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $this->repairCtePartiesForRows($rows);
        return $this->attachEventSummaries($this->cleanReferencedDocumentRows($rows));
    }

    public function documentsTotals(array $filters = []): array
    {
        if ($this->documentsNeedReferencedNumberRepair($filters)) {
            $this->ensureReferencedDocumentNumbers();
        }
        $this->ensureDocumentItemsForFilters($filters);
        [$where, $params] = $this->documentWhere($filters);
        $sql = 'SELECT COUNT(*) AS total, COALESCE(SUM(total_value), 0) AS total_value FROM documents';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: ['total'=>0, 'total_value'=>0];
        return ['total'=>(int)$row['total'], 'total_value'=>(float)$row['total_value']];
    }

    private function documentOrderBy(array $filters): string
    {
        $allowed = [
            'issue_date' => 'issue_date',
            'company_name' => 'company_name',
            'doc_type' => 'doc_type',
            'number' => 'number',
            'order_number' => 'order_number',
            'issuer_name' => 'issuer_name',
            'total_value' => 'total_value',
            'status' => 'status',
            'imported_at' => 'imported_at',
            'id' => 'id',
        ];
        $field = (string)($filters['sort_by'] ?? 'issue_date');
        $direction = strtolower((string)($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $column = $allowed[$field] ?? 'issue_date';
        return "ORDER BY {$column} {$direction} NULLS LAST, id DESC";
    }

    private function documentsNeedReferencedNumberRepair(array $filters): bool
    {
        return trim((string)($filters['referenced_number_q'] ?? '')) !== ''
            || trim((string)($filters['referenced_nfe_q'] ?? '')) !== ''
            || !empty($filters['without_referenced_nfe']);
    }

    private function documentWhere(array $filters): array
    {
        $where = ["status <> 'evento_informativo'"];
        $params = [];
        if (!empty($filters['entry_only'])) {
            $where[] = "doc_type IN ('NFE', 'CTE', 'NFSE')";
            $companyDigits = $this->digitsOnlySql('documents.company_cnpj');
            $recipientDigits = $this->digitsOnlySql('documents.recipient_cnpj');
            // Entradas de NF-e/NFS-e sao sempre documentos em que o CNPJ cadastrado e destinatario/tomador.
            // Notas emitidas por uma loja do grupo para cliente final ficam armazenadas para auditoria,
            // mas nao aparecem na aba Entradas nem entram como pendencia operacional.
            $where[] = "(doc_type NOT IN ('NFE', 'NFSE') OR ({$companyDigits} <> '' AND {$companyDigits} = {$recipientDigits}))";
        }
        $companyIds = $this->filterIntValues($filters['company_id'] ?? '');
        if ($companyIds) {
            $placeholders = [];
            foreach ($companyIds as $idx => $companyId) {
                $key = 'company_id_' . $idx;
                $placeholders[] = ':' . $key;
                $params[$key] = $companyId;
            }
            $where[] = 'company_id IN (' . implode(',', $placeholders) . ')';
        }
        if (!empty($filters['doc_type'])) { $where[] = 'doc_type = :doc_type'; $params['doc_type'] = $filters['doc_type']; }
        $statusFilter = (string)($filters['status'] ?? '');
        if ($statusFilter === 'not_cancelled') {
            $where[] = "status <> 'cancelado'";
        } elseif ($statusFilter !== '') {
            $where[] = 'status = :status';
            $params['status'] = $statusFilter;
        }
        if (!empty($filters['manifestation_status'])) { $where[] = 'manifestation_status = :manifestation_status'; $params['manifestation_status'] = $filters['manifestation_status']; }
        if ((string)($filters['posted_to_erp'] ?? '') === '1') { $where[] = 'COALESCE(posted_to_erp, FALSE) = TRUE'; }
        if ((string)($filters['posted_to_erp'] ?? '') === '0') {
            $where[] = 'COALESCE(posted_to_erp, FALSE) = FALSE';
        }
        if ((string)($filters['accounting_posted'] ?? '') === 'S') {
            $where[] = "COALESCE(accounting_posted, 'N') = 'S'";
        }
        if ((string)($filters['accounting_posted'] ?? '') === 'N') {
            $where[] = "COALESCE(accounting_posted, 'N') <> 'S'";
        }
        if (!empty($filters['without_referenced_nfe'])) { $where[] = "(doc_type = 'CTE' AND COALESCE(referenced_nfe_keys, '') = '')"; }
        if (!empty($filters['cte_taker_only'])) {
            $companyDigits = $this->digitsOnlySql('documents.company_cnpj');
            $registeredCompanyDigits = $this->digitsOnlySql('cte_company.cnpj');
            $where[] = "(doc_type <> 'CTE' OR EXISTS (
                SELECT 1
                FROM document_cte_takers dct
                WHERE dct.document_id = documents.id
                  AND COALESCE(dct.taker_cnpj, '') <> ''
                  AND (
                      dct.taker_cnpj = {$companyDigits}
                      OR EXISTS (
                          SELECT 1
                          FROM companies cte_company
                          WHERE {$registeredCompanyDigits} = dct.taker_cnpj
                      )
                  )
            ))";
        }

        $dateStart = $this->normalizeFilterDate((string)($filters['date_start'] ?? ''));
        $dateEnd = $this->normalizeFilterDate((string)($filters['date_end'] ?? ''));
        if ($dateStart !== null) { $where[] = 'issue_date >= :date_start'; $params['date_start'] = $dateStart . ' 00:00:00'; }
        if ($dateEnd !== null) { $where[] = 'issue_date <= :date_end'; $params['date_end'] = $dateEnd . ' 23:59:59'; }
        $recipientFilter = trim((string)($filters['recipient_q'] ?? ''));
        if ($recipientFilter !== '') {
            $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $recipientParts = ['recipient_name ILIKE :recipient_q', 'recipient_cnpj ILIKE :recipient_q'];
            $params['recipient_q'] = '%' . $recipientFilter . '%';

            $recipientDigits = preg_replace('/\D+/', '', $recipientFilter);
            if ($recipientDigits !== '') {
                // Destinatario pode ser filtrado por CNPJ com ou sem mascara.
                $recipientParts[] = $driver === 'sqlite'
                    ? 'recipient_cnpj LIKE :recipient_digits'
                    : "regexp_replace(COALESCE(recipient_cnpj, ''), '\\D', '', 'g') ILIKE :recipient_digits";
                $params['recipient_digits'] = '%' . $recipientDigits . '%';
            }
            $where[] = '(' . implode(' OR ', $recipientParts) . ')';
        }
        foreach ([
            'company_q' => ['company_name', 'company_cnpj'],
            'number_q' => ['number'],
            'issuer_q' => ['issuer_name', 'issuer_cnpj'],
            'access_key_q' => ['access_key'],
            'referenced_nfe_q' => ['referenced_nfe_keys'],
            'referenced_number_q' => ['referenced_document_numbers'],
            'source_q' => ['source'],
        ] as $filterKey => $columns) {
            if (!empty($filters[$filterKey])) {
                $parts = [];
                foreach ($columns as $column) {
                    $parts[] = $column . ' ILIKE :' . $filterKey;
                }
                $where[] = '(' . implode(' OR ', $parts) . ')';
                $params[$filterKey] = '%' . $filters[$filterKey] . '%';
            }
        }
        if (!empty($filters['q'])) {
            $where[] = '(issuer_name ILIKE :q OR issuer_cnpj ILIKE :q OR recipient_name ILIKE :q OR recipient_cnpj ILIKE :q OR access_key ILIKE :q OR number ILIKE :q OR company_name ILIKE :q OR company_cnpj ILIKE :q)';
            $params['q'] = '%'.$filters['q'].'%';
        }
        $itemParts = [];
        if (trim((string)($filters['product_q'] ?? '')) !== '') {
            $itemParts[] = 'LOWER(COALESCE(di.product_name, \'\')) LIKE :product_q';
            $params['product_q'] = '%' . mb_strtolower(trim((string)$filters['product_q'])) . '%';
        }
        if (trim((string)($filters['cfop_q'] ?? '')) !== '') {
            $itemParts[] = 'COALESCE(di.cfop, \'\') LIKE :cfop_q';
            $params['cfop_q'] = '%' . trim((string)$filters['cfop_q']) . '%';
        }
        if ($itemParts) {
            $where[] = 'EXISTS (SELECT 1 FROM document_items di WHERE di.document_id = documents.id AND ' . implode(' AND ', $itemParts) . ')';
        }
        if ((string)($filters['ignore_cfops'] ?? '1') !== '0') {
            $where[] = "NOT EXISTS (
                SELECT 1
                FROM document_items dix
                JOIN document_ignored_cfops dic ON dic.cfop = dix.cfop
                WHERE dix.document_id = documents.id
            )";
            $where[] = "NOT EXISTS (
                SELECT 1
                FROM document_ignored_documents did
                WHERE did.document_id = documents.id
                   OR (COALESCE(did.access_key, '') <> '' AND did.access_key = documents.access_key)
            )";
        }
        return [$where, $params];
    }

    private function ensureDocumentItemsForFilters(array $filters): void
    {
        $hasProductOrCfopFilter = trim((string)($filters['product_q'] ?? '')) !== '' || trim((string)($filters['cfop_q'] ?? '')) !== '';
        $ignoreCfopsEnabled = (string)($filters['ignore_cfops'] ?? '1') !== '0';
        $hasIgnoredCfops = false;
        if ($ignoreCfopsEnabled) {
            $hasIgnoredCfops = (bool)$this->pdo->query('SELECT 1 FROM document_ignored_cfops LIMIT 1')->fetchColumn();
        }
        if (!$hasProductOrCfopFilter && empty($filters['cte_taker_only']) && !$hasIgnoredCfops) {
            return;
        }
        if (!empty($filters['cte_taker_only'])) {
            $this->refreshCteTakersForFilter($filters);
        }
        // O filtro de CFOP ignorado tambem depende dos itens indexados.
        // Reprocessa somente documentos com XML e marcador ausente/vazio.
        $this->indexMissingDocumentItems(10000);
    }

    private function refreshCteTakersForFilter(array $filters, int $limit = 1200): void
    {
        $where = ["doc_type = 'CTE'", "(COALESCE(raw_xml, '') <> '' OR COALESCE(xml_path, '') <> '')"];
        $params = [];
        $dateStart = $this->normalizeFilterDate((string)($filters['date_start'] ?? ''));
        $dateEnd = $this->normalizeFilterDate((string)($filters['date_end'] ?? ''));
        if ($dateStart !== null) {
            $where[] = 'issue_date >= :date_start';
            $params['date_start'] = $dateStart . ' 00:00:00';
        }
        if ($dateEnd !== null) {
            $where[] = 'issue_date <= :date_end';
            $params['date_end'] = $dateEnd . ' 23:59:59';
        }
        $sql = 'SELECT id, raw_xml, xml_path FROM documents WHERE ' . implode(' AND ', $where) . ' ORDER BY issue_date DESC NULLS LAST, id DESC LIMIT :limit';
        if ((string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $sql = str_replace(' NULLS LAST', '', $sql);
        }
        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $deleteTaker = $this->pdo->prepare('DELETE FROM document_cte_takers WHERE document_id = :document_id');
            $insertTaker = $this->pdo->prepare('INSERT INTO document_cte_takers(document_id, taker_cnpj, created_at) VALUES(:document_id, :taker_cnpj, :created_at)');
            $updateCompany = $this->pdo->prepare('UPDATE documents
                SET company_id = :company_id,
                    company_name = :company_name,
                    company_cnpj = :company_cnpj,
                    updated_at = :updated_at
                WHERE id = :id');
            foreach ($stmt->fetchAll() as $doc) {
                $xml = $this->documentXmlFromRow($doc);
                if (trim($xml) === '') {
                    continue;
                }
                $takerCnpj = $this->parseCteTakerCnpjFromXml($xml);
                $deleteTaker->execute(['document_id' => (int)$doc['id']]);
                $insertTaker->execute([
                    'document_id' => (int)$doc['id'],
                    'taker_cnpj' => $takerCnpj,
                    'created_at' => date('c'),
                ]);
                $company = $takerCnpj !== '' ? $this->findCompanyByCnpj($takerCnpj) : null;
                if ($company) {
                    $updateCompany->execute([
                        'id' => (int)$doc['id'],
                        'company_id' => (int)$company['id'],
                        'company_name' => (string)$company['company_name'],
                        'company_cnpj' => (string)$company['cnpj'],
                        'updated_at' => date('c'),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            error_log('Falha ao atualizar tomadores CT-e filtrados: ' . $e->getMessage());
        }
    }

    private function repairCtePartiesForRows(array &$rows): void
    {
        if (!$rows) {
            return;
        }
        $updateDoc = $this->pdo->prepare('UPDATE documents
            SET company_id = COALESCE(:company_id, company_id),
                company_name = COALESCE(:company_name, company_name),
                company_cnpj = COALESCE(:company_cnpj, company_cnpj),
                recipient_cnpj = :recipient_cnpj,
                recipient_name = :recipient_name,
                updated_at = :updated_at
            WHERE id = :id');
        foreach ($rows as &$row) {
            if (strtoupper((string)($row['doc_type'] ?? '')) !== 'CTE') {
                continue;
            }
            try {
                $xml = $this->documentXmlFromRow($row);
                if (trim($xml) === '') {
                    continue;
                }
                $recipient = $this->parseCteRecipientFromXml($xml);
                $company = null;
                $takerCnpj = $this->parseCteTakerCnpjFromXml($xml);
                if ($takerCnpj !== '') {
                    $company = $this->findCompanyByCnpj($takerCnpj);
                }
                if ($recipient['cnpj'] !== '' || $recipient['name'] !== '') {
                    if ((string)($row['recipient_cnpj'] ?? '') !== $recipient['cnpj'] || (string)($row['recipient_name'] ?? '') !== $recipient['name'] || ($company && (int)($row['company_id'] ?? 0) !== (int)$company['id'])) {
                        $updateDoc->execute([
                            'id' => (int)$row['id'],
                            'company_id' => $company['id'] ?? null,
                            'company_name' => $company['company_name'] ?? null,
                            'company_cnpj' => $company['cnpj'] ?? null,
                            'recipient_cnpj' => $recipient['cnpj'],
                            'recipient_name' => $recipient['name'],
                            'updated_at' => date('c'),
                        ]);
                    }
                    if ($company) {
                        $row['company_id'] = (int)$company['id'];
                        $row['company_name'] = (string)$company['company_name'];
                        $row['company_cnpj'] = (string)$company['cnpj'];
                    }
                    $row['recipient_cnpj'] = $recipient['cnpj'];
                    $row['recipient_name'] = $recipient['name'];
                }
            } catch (\Throwable $e) {
                error_log('Falha ao reparar dados CT-e do documento ' . (string)($row['id'] ?? '') . ': ' . $e->getMessage());
            }
        }
        unset($row);
    }

    private function documentXmlFromRow(array $doc): string
    {
        $xml = (string)($doc['raw_xml'] ?? '');
        $path = (string)($doc['xml_path'] ?? '');
        if (trim($xml) === '' && $path !== '' && is_file($path)) {
            $xml = (string)file_get_contents($path);
        }
        return $xml;
    }

    private function ensureReferencedDocumentNumbers(int $limit = 10000): void
    {
        $stmt = $this->pdo->prepare("SELECT id, doc_type, access_key, referenced_nfe_keys, referenced_document_numbers, raw_xml, xml_path FROM documents
            WHERE doc_type IN ('NFE', 'NFCE', 'CTE')
              AND (
                  referenced_document_numbers IS NULL
                  OR referenced_nfe_keys IS NULL
                  OR COALESCE(referenced_nfe_keys, '') = COALESCE(access_key, '')
                  OR COALESCE(referenced_nfe_keys, '') <> ''
                  OR COALESCE(referenced_document_numbers, '') <> ''
              )
              AND (COALESCE(raw_xml, '') <> '' OR COALESCE(xml_path, '') <> '')
            ORDER BY id DESC
            LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $update = $this->pdo->prepare('UPDATE documents SET referenced_nfe_keys = :keys, referenced_document_numbers = :numbers, updated_at = :updated_at WHERE id = :id');
        foreach ($stmt->fetchAll() as $doc) {
            $xml = (string)($doc['raw_xml'] ?? '');
            $path = (string)($doc['xml_path'] ?? '');
            if (trim($xml) === '' && $path !== '' && is_file($path)) {
                $xml = (string)file_get_contents($path);
            }
            $type = strtoupper((string)($doc['doc_type'] ?? ''));
            $ownKey = (string)($doc['access_key'] ?? '');
            $keys = $this->parseReferencedNFeKeysFromXml($xml, $type, $ownKey);
            $numbers = $this->parseReferencedDocumentNumbersFromXml($xml, $type, $ownKey);
            $update->execute(['keys' => $keys !== '' ? $keys : null, 'numbers' => $numbers !== '' ? $numbers : null, 'updated_at' => date('c'), 'id' => (int)$doc['id']]);
        }
    }

    public function documentIgnoredCfops(): array
    {
        return $this->pdo->query('SELECT * FROM document_ignored_cfops ORDER BY cfop ASC')->fetchAll();
    }

    public function documentCfopOptions(): array
    {
        // A lista de CFOPs ignorados nao deve reprocessar XMLs ao abrir Entradas.
        // Itens faltantes sao indexados apenas quando o usuario filtra por produto/CFOP.
        $ignored = array_map(static fn(array $row): string => (string)$row['cfop'], $this->documentIgnoredCfops());
        $params = [];
        $where = "WHERE COALESCE(cfop, '') <> ''";
        if ($ignored) {
            $placeholders = [];
            foreach ($ignored as $idx => $cfop) {
                $key = 'cfop_' . $idx;
                $placeholders[] = ':' . $key;
                $params[$key] = $cfop;
            }
            $where .= ' AND cfop NOT IN (' . implode(',', $placeholders) . ')';
        }
        $stmt = $this->pdo->prepare("SELECT DISTINCT cfop FROM document_items {$where} ORDER BY cfop ASC LIMIT 500");
        $stmt->execute($params);
        return array_map(static fn(array $row): string => (string)$row['cfop'], $stmt->fetchAll());
    }

    public function documentStatusOptions(): array
    {
        $stmt = $this->pdo->query("SELECT status, COUNT(*) AS total
            FROM documents
            WHERE COALESCE(status, '') <> ''
              AND status <> 'evento_informativo'
            GROUP BY status
            ORDER BY status ASC");
        return $stmt->fetchAll();
    }

    public function documentCancellationEventXmls(array $documents): array
    {
        $keys = [];
        foreach ($documents as $doc) {
            if ((string)($doc['status'] ?? '') !== 'cancelado') {
                continue;
            }
            $key = preg_replace('/\D+/', '', (string)($doc['access_key'] ?? ''));
            if (strlen($key) === 44) {
                $keys[$key] = true;
            }
        }
        if (!$keys) {
            return [];
        }
        $params = [];
        $placeholders = [];
        foreach (array_keys($keys) as $idx => $key) {
            $param = 'key_' . $idx;
            $placeholders[] = ':' . $param;
            $params[$param] = $key;
        }
        $stmt = $this->pdo->prepare("SELECT access_key, event_type, event_name, protocol, raw_xml
            FROM document_events
            WHERE access_key IN (" . implode(',', $placeholders) . ")
              AND event_type IN ('110111')
              AND COALESCE(raw_xml, '') <> ''
            ORDER BY access_key ASC, event_date ASC, id ASC");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function nfeCancellationEventForDocument(int $documentId, string $accessKey, ?int $companyId = null): ?array
    {
        $accessKey = preg_replace('/\D+/', '', $accessKey) ?: '';
        if ($documentId <= 0 || strlen($accessKey) !== 44) {
            return null;
        }
        $whereCompany = '';
        $params = ['document_id' => $documentId, 'access_key' => $accessKey];
        if ($companyId !== null && $companyId > 0) {
            $whereCompany = ' AND (e.company_id = :company_id OR e.company_id IS NULL)';
            $params['company_id'] = $companyId;
        }
        $stmt = $this->pdo->prepare("SELECT e.id, e.document_id, e.company_id, e.access_key, e.event_type, e.event_name, e.event_date, e.protocol, e.raw_xml
            FROM document_events e
            WHERE (e.document_id = :document_id OR e.access_key = :access_key)
              AND e.event_type = '110111'
              {$whereCompany}
            ORDER BY e.event_date DESC NULLS LAST, e.id DESC
            LIMIT 1");
        $stmt->execute($params);
        $event = $stmt->fetch();
        return $event ?: null;
    }

    public function markNfeCancelledFromLocalEvent(int $documentId, string $accessKey, ?int $companyId = null): int
    {
        $event = $this->nfeCancellationEventForDocument($documentId, $accessKey, $companyId);
        if (!$event) {
            return 0;
        }
        return $this->markDocumentCancelledFromEvent(
            $documentId,
            (string)($event['event_date'] ?? ''),
            (string)($event['protocol'] ?? '')
        );
    }

    public function saveDocumentIgnoredCfop(string $cfop, string $reason, ?array $user): void
    {
        $cfop = preg_replace('/\D+/', '', $cfop) ?: '';
        if ($cfop === '') {
            throw new \RuntimeException('Selecione um CFOP para ignorar.');
        }
        $stmt = $this->pdo->prepare('INSERT INTO document_ignored_cfops(cfop, reason, user_id, user_name, created_at)
            VALUES(:cfop, :reason, :user_id, :user_name, :created_at)
            ON CONFLICT(cfop) DO UPDATE SET reason = excluded.reason, user_id = excluded.user_id, user_name = excluded.user_name, created_at = excluded.created_at');
        $stmt->execute([
            'cfop' => $cfop,
            'reason' => trim($reason),
            'user_id' => $user['id'] ?? null,
            'user_name' => $user['name'] ?? ($user['email'] ?? null),
            'created_at' => date('c'),
        ]);
    }

    public function deleteDocumentIgnoredCfop(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM document_ignored_cfops WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function documentIgnoredDocuments(): array
    {
        return $this->pdo->query('SELECT * FROM document_ignored_documents ORDER BY created_at DESC, id DESC LIMIT 500')->fetchAll();
    }

    public function saveDocumentIgnoredDocuments(array $ids, string $reason, ?array $user): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        $reason = trim($reason);
        if (!$ids) {
            throw new \RuntimeException('Selecione ao menos uma nota para ignorar.');
        }
        if ($reason === '') {
            throw new \RuntimeException('Informe a justificativa para ignorar a nota.');
        }

        $stmt = $this->pdo->prepare('INSERT INTO document_ignored_documents(document_id, access_key, doc_type, document_number, issuer_name, issuer_cnpj, company_name, company_cnpj, reason, user_id, user_name, created_at)
            VALUES(:document_id, :access_key, :doc_type, :document_number, :issuer_name, :issuer_cnpj, :company_name, :company_cnpj, :reason, :user_id, :user_name, :created_at)
            ON CONFLICT(document_id) DO UPDATE SET access_key = excluded.access_key, doc_type = excluded.doc_type, document_number = excluded.document_number, issuer_name = excluded.issuer_name, issuer_cnpj = excluded.issuer_cnpj, company_name = excluded.company_name, company_cnpj = excluded.company_cnpj, reason = excluded.reason, user_id = excluded.user_id, user_name = excluded.user_name, created_at = excluded.created_at');

        $saved = 0;
        foreach ($ids as $id) {
            $doc = $this->findDocument($id);
            if (!$doc) {
                continue;
            }
            $stmt->execute([
                'document_id' => (int)$doc['id'],
                'access_key' => (string)($doc['access_key'] ?? ''),
                'doc_type' => (string)($doc['doc_type'] ?? ''),
                'document_number' => (string)($doc['number'] ?? ''),
                'issuer_name' => (string)($doc['issuer_name'] ?? ''),
                'issuer_cnpj' => (string)($doc['issuer_cnpj'] ?? ''),
                'company_name' => (string)($doc['company_name'] ?? ''),
                'company_cnpj' => (string)($doc['company_cnpj'] ?? ''),
                'reason' => $reason,
                'user_id' => $user['id'] ?? null,
                'user_name' => $user['name'] ?? ($user['email'] ?? null),
                'created_at' => date('c'),
            ]);
            $saved++;
        }
        return $saved;
    }

    public function deleteDocumentIgnoredDocument(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM document_ignored_documents WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function repairCtePartiesAndTakersOnce(): array
    {
        $version = '20260724_dest_taker';
        if ($this->getSetting('repair_cte_parties_takers_version', '') === $version) {
            return ['processed' => 0, 'updated' => 0, 'takers' => 0, 'skipped' => true];
        }

        $stmt = $this->pdo->prepare("SELECT id, raw_xml, xml_path FROM documents
            WHERE doc_type = 'CTE'
              AND (COALESCE(raw_xml, '') <> '' OR COALESCE(xml_path, '') <> '')
            ORDER BY id DESC
            LIMIT 50000");
        $stmt->execute();
        $updateDoc = $this->pdo->prepare('UPDATE documents
            SET company_id = COALESCE(:company_id, company_id),
                company_name = COALESCE(:company_name, company_name),
                company_cnpj = COALESCE(:company_cnpj, company_cnpj),
                recipient_cnpj = :recipient_cnpj,
                recipient_name = :recipient_name,
                updated_at = :updated_at
            WHERE id = :id');
        $deleteTaker = $this->pdo->prepare('DELETE FROM document_cte_takers WHERE document_id = :document_id');
        $insertTaker = $this->pdo->prepare('INSERT INTO document_cte_takers(document_id, taker_cnpj, created_at) VALUES(:document_id, :taker_cnpj, :created_at)');

        $processed = 0;
        $updated = 0;
        $takers = 0;
        foreach ($stmt->fetchAll() as $doc) {
            $xml = (string)($doc['raw_xml'] ?? '');
            $path = (string)($doc['xml_path'] ?? '');
            if (trim($xml) === '' && $path !== '' && is_file($path)) {
                $xml = (string)file_get_contents($path);
            }
            if (trim($xml) === '') {
                continue;
            }
            $processed++;
            $recipient = $this->parseCteRecipientFromXml($xml);
            $takerCnpj = $this->parseCteTakerCnpjFromXml($xml);
            $company = $takerCnpj !== '' ? $this->findCompanyByCnpj($takerCnpj) : null;
            if ($recipient['cnpj'] !== '' || $recipient['name'] !== '') {
                $updateDoc->execute([
                    'id' => (int)$doc['id'],
                    'company_id' => $company['id'] ?? null,
                    'company_name' => $company['company_name'] ?? null,
                    'company_cnpj' => $company['cnpj'] ?? null,
                    'recipient_cnpj' => $recipient['cnpj'],
                    'recipient_name' => $recipient['name'],
                    'updated_at' => date('c'),
                ]);
                $updated += $updateDoc->rowCount();
            }

            $deleteTaker->execute(['document_id' => (int)$doc['id']]);
            $insertTaker->execute([
                'document_id' => (int)$doc['id'],
                'taker_cnpj' => $takerCnpj,
                'created_at' => date('c'),
            ]);
            $takers++;
        }

        $this->setSetting('repair_cte_parties_takers_version', $version);
        return ['processed' => $processed, 'updated' => $updated, 'takers' => $takers, 'skipped' => false];
    }

    private function indexMissingDocumentItems(int $limit = 10000): void
    {
        $stmt = $this->pdo->prepare("SELECT id, doc_type, raw_xml, xml_path FROM documents d
            WHERE d.status <> 'evento_informativo'
              AND d.doc_type IN ('NFE', 'CTE', 'NFSE')
              AND (
                  NOT EXISTS (SELECT 1 FROM document_item_index dix WHERE dix.document_id = d.id)
                  OR (
                      NOT EXISTS (SELECT 1 FROM document_items dim WHERE dim.document_id = d.id)
                      AND (
                          (d.doc_type = 'NFE' AND COALESCE(d.raw_xml, '') ILIKE '%<det%')
                          OR (d.doc_type = 'CTE' AND (COALESCE(d.raw_xml, '') ILIKE '%<Comp%' OR COALESCE(d.raw_xml, '') ILIKE '%<infCarga%'))
                          OR (d.doc_type = 'NFSE' AND (COALESCE(d.raw_xml, '') ILIKE '%Servico%' OR COALESCE(d.raw_xml, '') ILIKE '%Discriminacao%'))
                      )
                  )
                  OR (d.doc_type = 'CTE' AND NOT EXISTS (SELECT 1 FROM document_cte_takers dct WHERE dct.document_id = d.id))
              )
            ORDER BY d.id DESC
            LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll() as $doc) {
            $this->indexDocumentItems($doc);
        }
    }

    public function documentItems(int $documentId): array
    {
        $doc = $this->findDocument($documentId);
        if (!$doc) {
            return [];
        }
        $this->indexDocumentItems($doc);
        $stmt = $this->pdo->prepare('SELECT * FROM document_items WHERE document_id = :document_id ORDER BY item_number ASC, id ASC');
        $stmt->execute(['document_id' => $documentId]);
        return $stmt->fetchAll();
    }

    private function indexDocumentItems(array $doc): void
    {
        $documentId = (int)($doc['id'] ?? 0);
        if ($documentId <= 0) {
            return;
        }
        $exists = $this->pdo->prepare('SELECT 1 FROM document_item_index WHERE document_id = :document_id');
        $exists->execute(['document_id' => $documentId]);
        $itemsIndexed = (bool)$exists->fetchColumn();
        $hadItemIndex = $itemsIndexed;
        $itemCount = 0;
        if ($itemsIndexed) {
            $itemCountStmt = $this->pdo->prepare('SELECT COUNT(*) FROM document_items WHERE document_id = :document_id');
            $itemCountStmt->execute(['document_id' => $documentId]);
            $itemCount = (int)$itemCountStmt->fetchColumn();
            // Marcador sem itens pode ter sido criado antes da indexacao do XML.
            if ($itemCount === 0) {
                $itemsIndexed = false;
            }
        }
        $takerIndexed = true;
        if (strtoupper((string)($doc['doc_type'] ?? '')) === 'CTE') {
            $takerExists = $this->pdo->prepare('SELECT 1 FROM document_cte_takers WHERE document_id = :document_id');
            $takerExists->execute(['document_id' => $documentId]);
            $takerIndexed = (bool)$takerExists->fetchColumn();
        }
        if ($itemsIndexed && $takerIndexed) {
            return;
        }

        $xml = (string)($doc['raw_xml'] ?? '');
        $path = (string)($doc['xml_path'] ?? '');
        if (trim($xml) === '' && $path !== '' && is_file($path)) {
            $xml = (string)file_get_contents($path);
        }
        $type = strtoupper((string)($doc['doc_type'] ?? ''));
        $items = $itemsIndexed ? [] : $this->parseDocumentItemsFromXml($xml, $type);
        // Se o XML realmente nao tem itens, o marcador vazio continua valido.
        if (!$items && $hadItemIndex && $itemCount === 0 && $type !== 'CTE') {
            return;
        }
        $cteTaker = $type === 'CTE' && !$takerIndexed ? $this->parseCteTakerCnpjFromXml($xml) : null;

        $this->pdo->beginTransaction();
        try {
            if (!$itemsIndexed) {
                $delete = $this->pdo->prepare('DELETE FROM document_items WHERE document_id = :document_id');
                $delete->execute(['document_id' => $documentId]);
                $insert = $this->pdo->prepare('INSERT INTO document_items(document_id, item_number, product_code, product_name, ncm, cfop, quantity, unit, unit_amount, total_amount, created_at)
                    VALUES(:document_id, :item_number, :product_code, :product_name, :ncm, :cfop, :quantity, :unit, :unit_amount, :total_amount, :created_at)');
                foreach ($items as $item) {
                    $insert->execute([
                        'document_id' => $documentId,
                        'item_number' => (int)($item['item_number'] ?? 0),
                        'product_code' => (string)($item['product_code'] ?? ''),
                        'product_name' => (string)($item['product_name'] ?? ''),
                        'ncm' => (string)($item['ncm'] ?? ''),
                        'cfop' => (string)($item['cfop'] ?? ''),
                        'quantity' => (float)($item['quantity'] ?? 0),
                        'unit' => (string)($item['unit'] ?? ''),
                        'unit_amount' => (float)($item['unit_amount'] ?? 0),
                        'total_amount' => (float)($item['total_amount'] ?? 0),
                        'created_at' => date('c'),
                    ]);
                }
                $mark = $this->pdo->prepare('INSERT INTO document_item_index(document_id, indexed_at) VALUES(:document_id, :indexed_at)
                    ON CONFLICT(document_id) DO UPDATE SET indexed_at = excluded.indexed_at');
                $mark->execute(['document_id' => $documentId, 'indexed_at' => date('c')]);
            }
            if ($type === 'CTE' && !$takerIndexed) {
                $deleteTaker = $this->pdo->prepare('DELETE FROM document_cte_takers WHERE document_id = :document_id');
                $deleteTaker->execute(['document_id' => $documentId]);
                $insertTaker = $this->pdo->prepare('INSERT INTO document_cte_takers(document_id, taker_cnpj, created_at) VALUES(:document_id, :taker_cnpj, :created_at)');
                $insertTaker->execute(['document_id' => $documentId, 'taker_cnpj' => $cteTaker, 'created_at' => date('c')]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function parseDocumentItemsFromXml(string $xml, string $type): array
    {
        if (trim($xml) === '') {
            return [];
        }
        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
            return [];
        }
        $xp = new \DOMXPath($dom);
        if ($type === 'CTE') {
            $cfop = $this->xmlFirst($xp, ['//*[local-name()="ide"]/*[local-name()="CFOP"]']);
            $items = [];
            $index = 1;
            foreach ($xp->query('//*[local-name()="Comp"]') ?: [] as $node) {
                $local = new \DOMXPath($node->ownerDocument);
                $path = '(//*[local-name()="Comp"])[' . $index . ']';
                $items[] = [
                    'item_number' => $index,
                    'product_code' => '',
                    'product_name' => $this->xmlFirst($local, [$path . '/*[local-name()="xNome"]']),
                    'ncm' => '',
                    'cfop' => $cfop,
                    'quantity' => 0,
                    'unit' => '',
                    'unit_amount' => 0,
                    'total_amount' => $this->xmlNumber($this->xmlFirst($local, [$path . '/*[local-name()="vComp"]'])),
                ];
                $index++;
            }
            if (!$items) {
                $items[] = [
                    'item_number' => 1,
                    'product_code' => '',
                    'product_name' => $this->xmlFirst($xp, ['//*[local-name()="infCarga"]/*[local-name()="proPred"]', '//*[local-name()="ide"]/*[local-name()="natOp"]', '//*[local-name()="xObs"]']),
                    'ncm' => '',
                    'cfop' => $cfop,
                    'quantity' => 0,
                    'unit' => '',
                    'unit_amount' => 0,
                    'total_amount' => $this->xmlNumber($this->xmlFirst($xp, ['//*[local-name()="vPrest"]/*[local-name()="vTPrest"]', '//*[local-name()="vPrest"]/*[local-name()="vRec"]'])),
                ];
            }
            return $items;
        }

        if ($type === 'NFSE') {
            // NFS-e normalmente traz apenas a prestação do serviço, não itens de produto como a NF-e.
            // Indexamos um item sintético para permitir filtro por descrição/serviço na aba Entradas.
            $description = $this->xmlFirst($xp, [
                '//*[local-name()="Discriminacao"]',
                '//*[local-name()="discriminacao"]',
                '//*[local-name()="Descricao"]',
                '//*[local-name()="descricao"]',
                '//*[local-name()="xDescServ"]',
                '//*[local-name()="xServ"]',
            ]);
            if ($description === '') {
                $description = $this->xmlFirst($xp, [
                    '//*[local-name()="cServ"]',
                    '//*[local-name()="CodigoServico"]',
                    '//*[local-name()="ItemListaServico"]',
                ]);
            }
            $serviceValue = $this->xmlNumber($this->xmlFirst($xp, [
                '//*[local-name()="vLiq"]',
                '//*[local-name()="vServ"]',
                '//*[local-name()="ValorServicos"]',
                '//*[local-name()="ValorLiquidoNfse"]',
                '//*[local-name()="ValorLiquido"]',
            ]));
            return [[
                'item_number' => 1,
                'product_code' => $this->xmlFirst($xp, ['//*[local-name()="cServ"]', '//*[local-name()="CodigoServico"]']),
                'product_name' => $description !== '' ? $description : 'SERVIÇO NFS-E',
                'ncm' => '',
                'cfop' => '',
                'quantity' => 1,
                'unit' => 'UN',
                'unit_amount' => $serviceValue,
                'total_amount' => $serviceValue,
            ]];
        }

        $items = [];
        $index = 1;
        foreach ($xp->query('//*[local-name()="det"]') ?: [] as $det) {
            $nItem = $det instanceof \DOMElement ? $det->getAttribute('nItem') : '';
            $path = $nItem !== '' ? '//*[local-name()="det"][@nItem="' . $nItem . '"]' : '(//*[local-name()="det"])[' . $index . ']';
            $items[] = [
                'item_number' => $nItem !== '' ? (int)$nItem : $index,
                'product_code' => $this->xmlFirst($xp, [$path . '/*[local-name()="prod"]/*[local-name()="cProd"]']),
                'product_name' => $this->xmlFirst($xp, [$path . '/*[local-name()="prod"]/*[local-name()="xProd"]']),
                'ncm' => $this->xmlFirst($xp, [$path . '/*[local-name()="prod"]/*[local-name()="NCM"]']),
                'cfop' => $this->xmlFirst($xp, [$path . '/*[local-name()="prod"]/*[local-name()="CFOP"]']),
                'quantity' => $this->xmlNumber($this->xmlFirst($xp, [$path . '/*[local-name()="prod"]/*[local-name()="qCom"]'])),
                'unit' => $this->xmlFirst($xp, [$path . '/*[local-name()="prod"]/*[local-name()="uCom"]']),
                'unit_amount' => $this->xmlNumber($this->xmlFirst($xp, [$path . '/*[local-name()="prod"]/*[local-name()="vUnCom"]'])),
                'total_amount' => $this->xmlNumber($this->xmlFirst($xp, [$path . '/*[local-name()="prod"]/*[local-name()="vProd"]'])),
            ];
            $index++;
        }
        return $items;
    }

    private function parseCteTakerCnpjFromXml(string $xml): string
    {
        if (trim($xml) === '') {
            return '';
        }
        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
            return '';
        }
        $xp = new \DOMXPath($dom);
        $direct = $this->xmlFirst($xp, [
            '//*[local-name()="toma4"]/*[local-name()="CNPJ"]',
            '//*[local-name()="toma4"]/*[local-name()="CPF"]',
        ]);
        if ($direct !== '') {
            return preg_replace('/\D+/', '', $direct) ?: '';
        }
        $code = $this->xmlFirst($xp, ['//*[local-name()="toma3"]/*[local-name()="toma"]']);
        $tag = '';
        switch ($code) {
            case '0':
                $tag = 'rem';
                break;
            case '1':
                $tag = 'exped';
                break;
            case '2':
                $tag = 'receb';
                break;
            case '3':
                $tag = 'dest';
                break;
        }
        if ($tag === '') {
            return '';
        }
        $value = $this->xmlFirst($xp, [
            '//*[local-name()="' . $tag . '"]/*[local-name()="CNPJ"]',
            '//*[local-name()="' . $tag . '"]/*[local-name()="CPF"]',
        ]);
        return preg_replace('/\D+/', '', $value) ?: '';
    }

    private function parseCteRecipientFromXml(string $xml): array
    {
        $result = ['cnpj' => '', 'name' => ''];
        if (trim($xml) === '') {
            return $result;
        }
        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
            return $result;
        }
        $xp = new \DOMXPath($dom);
        $result['cnpj'] = preg_replace('/\D+/', '', $this->xmlFirst($xp, [
            '//*[local-name()="dest"]/*[local-name()="CNPJ"]',
            '//*[local-name()="dest"]/*[local-name()="CPF"]',
        ])) ?: '';
        $result['name'] = $this->xmlFirst($xp, ['//*[local-name()="dest"]/*[local-name()="xNome"]']);
        return $result;
    }

    private function parseReferencedDocumentNumbersFromXml(string $xml, string $type, string $ownAccessKey = ''): string
    {
        if (trim($xml) === '') {
            return '';
        }
        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
            return '';
        }
        $xp = new \DOMXPath($dom);
        $numbers = [];
        $ownNumber = $this->numberFromAccessKey($ownAccessKey);
        $keyExpr = $type === 'CTE'
            ? '//*[local-name()="infNFe"]/*[local-name()="chave" or local-name()="chNFe"]'
            : '//*[local-name()="NFref"]/*[local-name()="refNFe"]';
        foreach ($xp->query($keyExpr) ?: [] as $node) {
            $number = $this->numberFromAccessKey((string)$node->textContent);
            if ($number !== '' && $number !== $ownNumber) {
                $numbers[$number] = true;
            }
        }
        foreach ($xp->query('//*[local-name()="NFref"]//*[local-name()="nNF"]') ?: [] as $node) {
            $number = ltrim(preg_replace('/\D+/', '', trim((string)$node->textContent)) ?: '', '0');
            if ($number !== '' && $number !== $ownNumber) {
                $numbers[$number] = true;
            }
        }
        return $numbers ? implode(', ', array_keys($numbers)) : '';
    }

    private function parseReferencedNFeKeysFromXml(string $xml, string $type, string $ownAccessKey = ''): string
    {
        if (trim($xml) === '') {
            return '';
        }
        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
            return '';
        }
        $xp = new \DOMXPath($dom);
        $keys = [];
        $ownKey = preg_replace('/\D+/', '', $ownAccessKey) ?: '';
        $expr = $type === 'CTE'
            ? '//*[local-name()="infNFe"]/*[local-name()="chave" or local-name()="chNFe"]'
            : '//*[local-name()="NFref"]/*[local-name()="refNFe"]';
        foreach ($xp->query($expr) ?: [] as $node) {
            $key = preg_replace('/\D+/', '', trim((string)$node->textContent));
            if (strlen($key) === 44 && $key !== $ownKey) {
                $keys[$key] = true;
            }
        }
        return $keys ? implode(', ', array_keys($keys)) : '';
    }

    private function normalizeReferencedKeys(string $value, string $ownAccessKey): string
    {
        $ownKey = preg_replace('/\D+/', '', $ownAccessKey) ?: '';
        $keys = [];
        foreach (preg_split('/[\s,;|]+/', $value) ?: [] as $part) {
            $key = preg_replace('/\D+/', '', $part) ?: '';
            if (strlen($key) === 44 && $key !== $ownKey) {
                $keys[$key] = true;
            }
        }
        return $keys ? implode(', ', array_keys($keys)) : '';
    }

    private function normalizeReferencedNumbers(string $value, string $ownAccessKey): string
    {
        $ownKey = preg_replace('/\D+/', '', $ownAccessKey) ?: '';
        $ownNumber = $this->numberFromAccessKey($ownAccessKey);
        $numbers = [];
        foreach (preg_split('/[\s,;|]+/', $value) ?: [] as $part) {
            $digits = preg_replace('/\D+/', '', $part) ?: '';
            if (strlen($digits) === 44) {
                if ($digits === $ownKey) {
                    continue;
                }
                $digits = $this->numberFromAccessKey($digits);
            }
            $number = ltrim($digits, '0');
            if ($number !== '' && $number !== $ownNumber) {
                $numbers[$number] = true;
            }
        }
        return $numbers ? implode(', ', array_keys($numbers)) : '';
    }

    private function cleanReferencedDocumentRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $ownKey = (string)($row['access_key'] ?? '');
            $row['referenced_nfe_keys'] = $this->normalizeReferencedKeys((string)($row['referenced_nfe_keys'] ?? ''), $ownKey);
            $row['referenced_document_numbers'] = $this->normalizeReferencedNumbers((string)($row['referenced_document_numbers'] ?? ''), $ownKey);
        }
        unset($row);
        return $rows;
    }

    private function numberFromAccessKey(string $key): string
    {
        $digits = preg_replace('/\D+/', '', $key) ?: '';
        if (strlen($digits) !== 44) {
            return '';
        }
        return ltrim(substr($digits, 25, 9), '0') ?: '0';
    }

    private function xmlFirst(\DOMXPath $xp, array $exprs): string
    {
        foreach ($exprs as $expr) {
            $nodes = $xp->query($expr);
            if ($nodes && $nodes->length > 0) {
                $value = trim((string)$nodes->item(0)?->textContent);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return '';
    }

    private function xmlNumber(string $value): float
    {
        return (float)str_replace(',', '.', trim($value));
    }

    private function normalizeFilterDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }
        return null;
    }

    public function dashboard(array $filters = []): array
    {
        [$where, $params] = $this->dashboardWhere($filters);
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->pdo->prepare("SELECT doc_type, COUNT(*) AS total FROM documents{$whereSql} GROUP BY doc_type");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $stats = ['NFE'=>0,'NFCE'=>0,'CTE'=>0,'MDFE'=>0,'NFSE'=>0];
        foreach ($rows as $row) { $stats[strtoupper((string)$row['doc_type'])] = (int)$row['total']; }

        $typeTotals = $this->dashboardTypeTotals($filters);
        $pending = $this->dashboardCount("manifestation_status = 'pending'", $where, $params);
        $full = $this->dashboardCount("status = 'xml_completo'", $where, $params);
        $summary = $this->dashboardCount("status = 'apenas_resumo'", $where, $params);
        $awaiting = $this->dashboardCount("status = 'aguardando_novo_download'", $where, $params);
        $companiesCount = $this->dashboardCompaniesCount($filters);

        $stmt = $this->pdo->prepare("SELECT company_name, company_cnpj, COUNT(*) AS total, COALESCE(SUM(total_value), 0) AS total_value FROM documents{$whereSql} GROUP BY company_name, company_cnpj ORDER BY total DESC, company_name ASC LIMIT 10");
        $stmt->execute($params);
        $docsByCompany = $stmt->fetchAll();

        $topSuppliers = $this->dashboardTopParticipants($filters, ['NFE', 'NFCE', 'NFSE'], 'issuer_name', 'issuer_cnpj', 20);
        $topTransporters = $this->dashboardTopParticipants($filters, ['CTE'], 'issuer_name', 'issuer_cnpj', 20);
        $monthlyImports = $this->dashboardMonthlyImports($filters);
        $latestByCompany = $this->dashboardLatestByCompany($filters);

        return compact('stats', 'typeTotals', 'pending', 'full', 'summary', 'awaiting', 'companiesCount', 'docsByCompany', 'topSuppliers', 'topTransporters', 'monthlyImports', 'latestByCompany');
    }

    private function dashboardCompaniesCount(array $filters): int
    {
        if (!empty($filters['company_id'])) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM companies WHERE id = :company_id AND is_active = TRUE');
            $stmt->execute(['company_id' => (int)$filters['company_id']]);
            return (int)$stmt->fetchColumn();
        }

        $dateStart = $this->normalizeFilterDate((string)($filters['date_start'] ?? ''));
        $dateEnd = $this->normalizeFilterDate((string)($filters['date_end'] ?? ''));
        if ($dateStart !== null || $dateEnd !== null) {
            [$where, $params] = $this->dashboardWhere($filters);
            $stmt = $this->pdo->prepare('SELECT COUNT(DISTINCT company_id) FROM documents WHERE ' . implode(' AND ', $where));
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        }

        return (int)$this->pdo->query('SELECT COUNT(*) FROM companies WHERE is_active = TRUE')->fetchColumn();
    }

    private function dashboardTypeTotals(array $filters): array
    {
        [$where, $params] = $this->dashboardWhere($filters);
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $stmt = $this->pdo->prepare("SELECT doc_type, COUNT(*) AS total, COALESCE(SUM(total_value), 0) AS total_value, MIN(issue_date) AS first_issue_date, MAX(issue_date) AS last_issue_date FROM documents{$whereSql} GROUP BY doc_type");
        $stmt->execute($params);
        $totals = [
            'NFE' => ['total' => 0, 'total_value' => 0.0, 'first_issue_date' => null, 'last_issue_date' => null],
            'NFCE' => ['total' => 0, 'total_value' => 0.0, 'first_issue_date' => null, 'last_issue_date' => null],
            'CTE' => ['total' => 0, 'total_value' => 0.0, 'first_issue_date' => null, 'last_issue_date' => null],
            'MDFE' => ['total' => 0, 'total_value' => 0.0, 'first_issue_date' => null, 'last_issue_date' => null],
            'NFSE' => ['total' => 0, 'total_value' => 0.0, 'first_issue_date' => null, 'last_issue_date' => null],
        ];
        foreach ($stmt->fetchAll() as $row) {
            $type = strtoupper((string)$row['doc_type']);
            $totals[$type] = [
                'total' => (int)$row['total'],
                'total_value' => (float)$row['total_value'],
                'first_issue_date' => $row['first_issue_date'] ?? null,
                'last_issue_date' => $row['last_issue_date'] ?? null,
            ];
        }
        return $totals;
    }

    private function dashboardTopParticipants(array $filters, array $types, string $nameColumn, string $cnpjColumn, int $limit): array
    {
        [$where, $params] = $this->dashboardWhere($filters);
        $typeKeys = [];
        foreach (array_values($types) as $idx => $type) {
            $key = 'dash_type_' . $idx;
            $typeKeys[] = ':' . $key;
            $params[$key] = $type;
        }
        $where[] = 'doc_type IN (' . implode(',', $typeKeys) . ')';
        $where[] = "{$nameColumn} IS NOT NULL";
        $where[] = "{$nameColumn} <> ''";
        $stmt = $this->pdo->prepare("SELECT {$nameColumn} AS name, {$cnpjColumn} AS cnpj, COUNT(*) AS total, COALESCE(SUM(total_value), 0) AS total_value FROM documents WHERE " . implode(' AND ', $where) . " GROUP BY {$nameColumn}, {$cnpjColumn} ORDER BY total_value DESC, total DESC, {$nameColumn} ASC LIMIT :limit");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function dashboardMonthlyImports(array $filters): array
    {
        $where = ['imported_at IS NOT NULL'];
        $params = [];
        if (!empty($filters['company_id'])) {
            $where[] = 'company_id = :company_id';
            $params['company_id'] = (int)$filters['company_id'];
        }

        $dateStart = $this->normalizeFilterDate((string)($filters['date_start'] ?? ''));
        $dateEnd = $this->normalizeFilterDate((string)($filters['date_end'] ?? ''));
        if ($dateStart !== null) {
            $where[] = 'imported_at >= :date_start';
            $params['date_start'] = $dateStart . ' 00:00:00';
        }
        if ($dateEnd !== null) {
            $where[] = 'imported_at <= :date_end';
            $params['date_end'] = $dateEnd . ' 23:59:59';
        }

        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $monthExpr = $driver === 'sqlite' ? "strftime('%Y-%m', imported_at)" : "to_char(imported_at, 'YYYY-MM')";
        $stmt = $this->pdo->prepare("SELECT {$monthExpr} AS month_ref, doc_type, COUNT(*) AS total FROM documents WHERE " . implode(' AND ', $where) . " GROUP BY month_ref, doc_type ORDER BY month_ref ASC");
        $stmt->execute($params);

        $months = [];
        foreach ($stmt->fetchAll() as $row) {
            $month = (string)$row['month_ref'];
            if (!isset($months[$month])) {
                $months[$month] = ['month' => $month, 'NFE' => 0, 'NFCE' => 0, 'CTE' => 0, 'MDFE' => 0, 'NFSE' => 0, 'total' => 0];
            }
            $type = strtoupper((string)$row['doc_type']);
            $total = (int)$row['total'];
            if (array_key_exists($type, $months[$month])) {
                $months[$month][$type] = $total;
            }
            $months[$month]['total'] += $total;
        }

        return array_values($months);
    }

    private function dashboardLatestByCompany(array $filters): array
    {
        $companyWhere = ['c.is_active = TRUE'];
        $joinWhere = [
            'd.company_id = c.id',
            "d.status <> 'evento_informativo'",
            "d.doc_type IN ('NFE', 'NFCE', 'CTE', 'NFSE')",
        ];
        $params = [];

        if (!empty($filters['company_id'])) {
            $companyWhere[] = 'c.id = :company_id';
            $params['company_id'] = (int)$filters['company_id'];
        }

        $dateStart = $this->normalizeFilterDate((string)($filters['date_start'] ?? ''));
        $dateEnd = $this->normalizeFilterDate((string)($filters['date_end'] ?? ''));
        if ($dateStart !== null) {
            $joinWhere[] = 'd.issue_date >= :date_start';
            $params['date_start'] = $dateStart . ' 00:00:00';
        }
        if ($dateEnd !== null) {
            $joinWhere[] = 'd.issue_date <= :date_end';
            $params['date_end'] = $dateEnd . ' 23:59:59';
        }

        $sql = "SELECT
                    c.id AS company_id,
                    c.company_name,
                    c.cnpj AS company_cnpj,
                    COUNT(d.id) AS total_documents,
                    MAX(CASE WHEN d.doc_type IN ('NFE', 'NFCE') THEN d.issue_date ELSE NULL END) AS latest_note_date,
                    MAX(CASE WHEN d.doc_type = 'CTE' THEN d.issue_date ELSE NULL END) AS latest_cte_date,
                    MAX(CASE WHEN d.doc_type = 'NFSE' THEN d.issue_date ELSE NULL END) AS latest_nfse_date,
                    MAX(d.issue_date) AS latest_document_date
                FROM companies c
                LEFT JOIN documents d ON " . implode(' AND ', $joinWhere) . "
                WHERE " . implode(' AND ', $companyWhere) . "
                GROUP BY c.id, c.company_name, c.cnpj
                ORDER BY MAX(d.issue_date) IS NULL ASC, MAX(d.issue_date) DESC, c.company_name ASC
                LIMIT 200";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function dashboardCount(string $extraCondition, array $where, array $params): int
    {
        $where[] = $extraCondition;
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM documents WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    private function dashboardWhere(array $filters): array
    {
        $where = ["status <> 'evento_informativo'"];
        $params = [];
        if (!empty($filters['company_id'])) {
            $where[] = 'company_id = :company_id';
            $params['company_id'] = (int)$filters['company_id'];
        }
        $dateStart = $this->normalizeFilterDate((string)($filters['date_start'] ?? ''));
        $dateEnd = $this->normalizeFilterDate((string)($filters['date_end'] ?? ''));
        if ($dateStart !== null) {
            $where[] = 'issue_date >= :date_start';
            $params['date_start'] = $dateStart . ' 00:00:00';
        }
        if ($dateEnd !== null) {
            $where[] = 'issue_date <= :date_end';
            $params['date_end'] = $dateEnd . ' 23:59:59';
        }
        return [$where, $params];
    }

    public function logAction(string $actionType, string $details, ?int $companyId = null): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO actions_log(company_id, action_type, details, created_at) VALUES(:company_id, :action_type, :details, :created_at)");
        $stmt->execute(['company_id'=>$companyId,'action_type'=>$actionType,'details'=>$details,'created_at'=>date('c')]);
    }

    public function recentActions(int $limit = 15): array
    {
        $stmt = $this->pdo->prepare("SELECT a.*, c.company_name FROM actions_log a LEFT JOIN companies c ON c.id = a.company_id ORDER BY a.id DESC LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function enqueueNfeCancellationCheck(int $documentId, ?int $companyId, string $accessKey, ?int $requestedBy = null): int
    {
        $q=$this->pdo->prepare("SELECT id FROM nfe_cancellation_queue WHERE document_id=:document_id AND (status IN ('pending','retry','running') OR (status='completed' AND completed_at >= CURRENT_TIMESTAMP - INTERVAL '7 days')) ORDER BY id DESC LIMIT 1");
        $q->execute(['document_id'=>$documentId]); $existing=$q->fetchColumn(); if($existing){return (int)$existing;}
        $q=$this->pdo->prepare("INSERT INTO nfe_cancellation_queue(document_id,company_id,access_key,requested_by,status,next_attempt_at,created_at,updated_at) VALUES(:document_id,:company_id,:access_key,:requested_by,'pending',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
        $q->execute(['document_id'=>$documentId,'company_id'=>$companyId,'access_key'=>$accessKey,'requested_by'=>$requestedBy]); return (int)$this->pdo->lastInsertId();
    }

    public function repairStaleNfeCancellationPending(int $limit = 1000, bool $manualOnly = false): int
    {
        $limit = max(1, min(5000, $limit));
        $manualClause = $manualOnly ? " AND requested_by IS NOT NULL" : '';
        $sql = "WITH stale AS (
                    SELECT id FROM nfe_cancellation_queue
                    WHERE status = 'pending' AND attempts = 0
                      AND (next_attempt_at IS NULL OR next_attempt_at <= CURRENT_TIMESTAMP - INTERVAL '2 minutes')
                      {`$manualClause}
                    ORDER BY id ASC LIMIT :limit
                )
                UPDATE nfe_cancellation_queue q
                   SET next_attempt_at = CURRENT_TIMESTAMP + INTERVAL '1 minute',
                       updated_at = CURRENT_TIMESTAMP,
                       last_message = CASE WHEN COALESCE(q.last_message, '') = '' THEN 'Reagendado automaticamente para a proxima execucao.' ELSE q.last_message END
                  FROM stale
                 WHERE q.id = stale.id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function retireAutomaticNfeCancellationQueue(int $limit = 5000): int
    {
        $limit = max(1, min(20000, $limit));
        $sql = "WITH legacy AS (
                    SELECT id FROM nfe_cancellation_queue
                    WHERE requested_by IS NULL
                      AND status IN ('pending','retry','running')
                    ORDER BY id ASC
                    LIMIT :limit
                )
                UPDATE nfe_cancellation_queue q
                   SET status = 'superseded',
                       next_attempt_at = CURRENT_TIMESTAMP,
                       completed_at = CURRENT_TIMESTAMP,
                       error_kind = 'official_nsu_sync',
                       last_message = 'Fila antiga substituida pela sincronizacao oficial NF-e por NSU; nao sera consultada por chave.',
                       updated_at = CURRENT_TIMESTAMP
                  FROM legacy
                 WHERE q.id = legacy.id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }
    public function claimDueNfeCancellationChecks(int $limit = 20, ?int $queueId = null, bool $manualOnly = false): array
    {
        $queueSql = "SELECT * FROM nfe_cancellation_queue WHERE status IN ('pending','retry') AND next_attempt_at <= CURRENT_TIMESTAMP";
        if ($manualOnly) { $queueSql .= ' AND requested_by IS NOT NULL'; }
        if ($queueId !== null && $queueId > 0) { $queueSql .= " AND id = :queue_id"; }
        $queueSql .= " ORDER BY next_attempt_at,id LIMIT :limit";
        $q=$this->pdo->prepare($queueSql);
        if ($queueId !== null && $queueId > 0) { $q->bindValue(':queue_id', $queueId, PDO::PARAM_INT); }
        $q->bindValue(':limit',$limit,PDO::PARAM_INT); $q->execute(); $rows=$q->fetchAll();
        foreach($rows as &$row){$u=$this->pdo->prepare("UPDATE nfe_cancellation_queue SET status='running',attempts=attempts+1,last_attempt_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=:id AND status IN ('pending','retry')");$u->execute(['id'=>$row['id']]);$row['attempts']=(int)$row['attempts']+1;} return $rows;
    }

    public function finishNfeCancellationCheck(int $id, string $status, int $attempts, ?string $nextAt, ?string $cstat, string $message, ?string $errorKind): void
    {
        $q=$this->pdo->prepare("UPDATE nfe_cancellation_queue SET status=:status,attempts=:attempts,next_attempt_at=CASE WHEN :is_retry=1 THEN CAST(:next_attempt_at AS timestamp) ELSE CURRENT_TIMESTAMP END,completed_at=CASE WHEN :done=1 THEN CURRENT_TIMESTAMP ELSE completed_at END,last_cstat=:cstat,last_message=:message,error_kind=:error_kind,updated_at=CURRENT_TIMESTAMP WHERE id=:id");
        $q->execute(['status'=>$status,'attempts'=>$attempts,'next_attempt_at'=>$nextAt,'is_retry'=>$status === 'retry' ? 1 : 0,'done'=>in_array($status,['completed','failed'],true)?1:0,'cstat'=>$cstat,'message'=>$message,'error_kind'=>$errorKind,'id'=>$id]);
    }

    public function nfeCancellationQueue(int $limit=100): array
    {
        $q=$this->pdo->prepare("SELECT q.*,d.number,d.status AS document_status FROM nfe_cancellation_queue q LEFT JOIN documents d ON d.id=q.document_id ORDER BY q.id DESC LIMIT :limit");$q->bindValue(':limit',$limit,PDO::PARAM_INT);$q->execute();return $q->fetchAll();
    }

    public function nfeCancellationQueueForDocument(int $documentId): ?array
    {
        $q = $this->pdo->prepare("SELECT q.*, d.number, d.status AS document_status FROM nfe_cancellation_queue q LEFT JOIN documents d ON d.id = q.document_id WHERE q.document_id = :document_id ORDER BY q.id DESC LIMIT 1");
        $q->execute(['document_id' => $documentId]);
        $row = $q->fetch();
        return $row ?: null;
    }
    public function cancellationTrackingActiveCount(?int $requestedBy = null): int
    {
        $where = "q.requested_by IS NOT NULL AND (q.status = 'running' OR (q.status IN ('pending','retry') AND q.next_attempt_at > CURRENT_TIMESTAMP))";
        $params = [];
        if ($requestedBy !== null) {
            $where .= ' AND q.requested_by = :requested_by';
            $params['requested_by'] = $requestedBy;
        }
        $q = $this->pdo->prepare("SELECT COUNT(*) FROM nfe_cancellation_queue q WHERE {$where}");
        foreach ($params as $key => $value) { $q->bindValue(':' . $key, $value, PDO::PARAM_INT); }
        $q->execute();
        return (int)$q->fetchColumn();
    }

    public function cancellationTracking(?int $requestedBy = null, int $limit = 200): array
    {
        $where = " WHERE q.requested_by IS NOT NULL AND (q.status = 'running' OR (q.status IN ('pending','retry') AND q.next_attempt_at > CURRENT_TIMESTAMP))";
        $params = [];
        if ($requestedBy !== null) {
            $where .= ' AND q.requested_by = :requested_by';
            $params['requested_by'] = $requestedBy;
        }
        $limit = max(1, min(500, $limit));
        $sql = "SELECT q.*, d.number, d.issue_date, d.access_key AS document_access_key,
                       d.status AS document_status, c.company_name
                  FROM nfe_cancellation_queue q
                  LEFT JOIN documents d ON d.id = q.document_id
                  LEFT JOIN companies c ON c.id = q.company_id
                  {$where}
                 ORDER BY CASE WHEN q.status = 'running' THEN 0 ELSE 1 END,
                          q.next_attempt_at ASC, q.id DESC
                 LIMIT :limit";
        $q = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $q->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $q->bindValue(':limit', $limit, PDO::PARAM_INT);
        $q->execute();
        return $q->fetchAll();
    }

    public function nextNfeCancellationAttemptAt(bool $manualOnly = false): ?string
    {
        $sql = "SELECT MIN(next_attempt_at) FROM nfe_cancellation_queue WHERE status IN ('pending','retry') AND next_attempt_at IS NOT NULL";
        if ($manualOnly) { $sql .= ' AND requested_by IS NOT NULL'; }
        $value = $this->pdo->query($sql)->fetchColumn();
        return $value !== false && $value !== null ? (string)$value : null;
    }
    public function nextNfeDistributionCooldownAt(): ?string
    {
        $stmt = $this->pdo->query("SELECT key, value FROM settings WHERE key LIKE 'nfe%' AND key LIKE '%cooldown_until%'");
        $best = null;
        $bestTimestamp = PHP_INT_MAX;
        foreach ($stmt->fetchAll() as $row) {
            $key = (string)($row['key'] ?? '');
            $value = trim((string)($row['value'] ?? ''));
            if ($value === '' || !str_ends_with($key, 'cooldown_until')) continue;
            $timestamp = strtotime($value);
            if ($timestamp !== false && $timestamp > time() && $timestamp < $bestTimestamp) {
                $bestTimestamp = $timestamp;
                $best = $value;
            }
        }
        return $best;
    }
    public function createJob(string $jobType, ?int $companyId = null, ?string $companyName = null): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO jobs(company_id, company_name, job_type, status, started_at) VALUES(:company_id, :company_name, :job_type, :status, :started_at)");
        $stmt->execute(['company_id'=>$companyId,'company_name'=>$companyName,'job_type'=>$jobType,'status'=>'running','started_at'=>date('c')]);
        return (int)$this->pdo->lastInsertId();
    }

    public function finishJob(int $id, string $status, int $createdCount, int $updatedCount, int $errorCount, string $logText): void
    {
        $stmt = $this->pdo->prepare("UPDATE jobs SET status=:status, finished_at=:finished_at, created_count=:created_count, updated_count=:updated_count, error_count=:error_count, log_text=:log_text WHERE id=:id");
        $stmt->execute(['id'=>$id,'status'=>$status,'finished_at'=>date('c'),'created_count'=>$createdCount,'updated_count'=>$updatedCount,'error_count'=>$errorCount,'log_text'=>$logText]);
    }

    public function hasRecentRunningJob(string $jobType, int $companyId, int $seconds = 7200): bool
    {
        $stmt = $this->pdo->prepare("SELECT id, started_at FROM jobs WHERE job_type = :job_type AND company_id = :company_id AND status = 'running' ORDER BY id DESC LIMIT 10");
        $stmt->execute(['job_type' => $jobType, 'company_id' => $companyId]);
        $cutoff = time() - max(60, $seconds);
        foreach ($stmt->fetchAll() as $row) {
            $startedAt = strtotime((string)($row['started_at'] ?? ''));
            if ($startedAt !== false && $startedAt >= $cutoff) {
                return true;
            }
        }
        return false;
    }

    public function robotLogs(?string $dateStart = null, ?string $dateEnd = null, string $jobType = '', int $limit = 200): array
    {
        $where = [];
        $params = [];
        $dateStart = $this->normalizeFilterDate((string)($dateStart ?? ''));
        $dateEnd = $this->normalizeFilterDate((string)($dateEnd ?? ''));
        if ($dateStart !== null) { $where[] = 'j.started_at >= :robot_log_start'; $params['robot_log_start'] = $dateStart . ' 00:00:00'; }
        if ($dateEnd !== null) { $where[] = 'j.started_at <= :robot_log_end'; $params['robot_log_end'] = $dateEnd . ' 23:59:59'; }
        if (trim($jobType) !== '') { $where[] = 'j.job_type = :robot_log_type'; $params['robot_log_type'] = trim($jobType); }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $limit = max(1, min(1000, $limit));
        $stmt = $this->pdo->prepare('SELECT j.* FROM jobs j' . $whereSql . ' ORDER BY j.started_at DESC, j.id DESC LIMIT :robot_log_limit');
        foreach ($params as $key => $value) { $stmt->bindValue(':' . $key, $value); }
        $stmt->bindValue(':robot_log_limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    public function jobs(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM jobs ORDER BY id DESC LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function jobsByTypes(array $types, int $limit = 20): array
    {
        $types = array_values(array_filter(array_map('strval', $types)));
        if (!$types) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($types as $index => $type) {
            $key = ':type' . $index;
            $placeholders[] = $key;
            $params[$key] = $type;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM jobs WHERE job_type IN (' . implode(',', $placeholders) . ') ORDER BY id DESC LIMIT :limit');
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function createPeriodClosure(array $data): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO period_closures
            (status, period_start, period_end, company_ids, doc_types, only_missing_complete, try_manifestation, reprocess_after_manifestation, generate_export, save_period_folder, started_at)
            VALUES (:status, :period_start, :period_end, :company_ids, :doc_types, :only_missing_complete, :try_manifestation, :reprocess_after_manifestation, :generate_export, :save_period_folder, :started_at)");
        $stmt->execute([
            'status' => $data['status'] ?? 'running',
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'company_ids' => json_encode(array_values($data['company_ids'] ?? [])),
            'doc_types' => json_encode(array_values($data['doc_types'] ?? [])),
            'only_missing_complete' => !empty($data['only_missing_complete']) ? 1 : 0,
            'try_manifestation' => !empty($data['try_manifestation']) ? 1 : 0,
            'reprocess_after_manifestation' => !empty($data['reprocess_after_manifestation']) ? 1 : 0,
            'generate_export' => !empty($data['generate_export']) ? 1 : 0,
            'save_period_folder' => !empty($data['save_period_folder']) ? 1 : 0,
            'started_at' => date('c'),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function finishPeriodClosure(int $id, string $status, array $summary, array $messages = [], ?string $zipPath = null, ?string $csvPath = null): void
    {
        $stmt = $this->pdo->prepare("UPDATE period_closures SET status=:status, summary_json=:summary_json, messages=:messages, export_zip_path=:export_zip_path, export_csv_path=:export_csv_path, finished_at=:finished_at WHERE id=:id");
        $stmt->execute([
            'id' => $id,
            'status' => $status,
            'summary_json' => json_encode($summary, JSON_UNESCAPED_UNICODE),
            'messages' => implode(PHP_EOL, $messages),
            'export_zip_path' => $zipPath,
            'export_csv_path' => $csvPath,
            'finished_at' => date('c'),
        ]);
    }

    public function periodClosures(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM period_closures ORDER BY id DESC LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findPeriodClosure(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM period_closures WHERE id = :id");
        $stmt->execute(['id'=>$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function addPeriodClosureItem(int $closureId, array $item): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO period_closure_items
            (closure_id, document_id, company_id, company_name, company_cnpj, doc_type, access_key, issuer_name, issuer_cnpj, issue_date, total_value, status, xml_saved, xml_path, storage_dir, notes, created_at)
            VALUES (:closure_id, :document_id, :company_id, :company_name, :company_cnpj, :doc_type, :access_key, :issuer_name, :issuer_cnpj, :issue_date, :total_value, :status, :xml_saved, :xml_path, :storage_dir, :notes, :created_at)");
        $stmt->execute([
            'closure_id' => $closureId,
            'document_id' => $item['document_id'] ?? null,
            'company_id' => $item['company_id'] ?? null,
            'company_name' => $item['company_name'] ?? null,
            'company_cnpj' => $item['company_cnpj'] ?? null,
            'doc_type' => $item['doc_type'],
            'access_key' => $item['access_key'] ?? null,
            'issuer_name' => $item['issuer_name'] ?? null,
            'issuer_cnpj' => $item['issuer_cnpj'] ?? null,
            'issue_date' => $item['issue_date'] ?? null,
            'total_value' => (float)($item['total_value'] ?? 0),
            'status' => $item['status'],
            'xml_saved' => !empty($item['xml_saved']) ? 1 : 0,
            'xml_path' => $item['xml_path'] ?? null,
            'storage_dir' => $item['storage_dir'] ?? null,
            'notes' => $item['notes'] ?? null,
            'created_at' => date('c'),
        ]);
    }

    public function clearPeriodClosureItems(int $closureId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM period_closure_items WHERE closure_id = :closure_id");
        $stmt->execute(['closure_id'=>$closureId]);
    }

    public function periodClosureItems(int $closureId, array $filters = []): array
    {
        $where = ['closure_id = :closure_id'];
        $params = ['closure_id' => $closureId];
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }
        $sql = 'SELECT * FROM period_closure_items WHERE ' . implode(' AND ', $where) . ' ORDER BY issue_date ASC NULLS LAST, id ASC';
        if ((string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $sql = str_replace(' NULLS LAST', '', $sql);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function documentsForPeriod(array $companyIds, array $docTypes, string $startDate, string $endDate): array
    {
        if (!$companyIds || !$docTypes) {
            return [];
        }
        $companyPlaceholders = implode(',', array_fill(0, count($companyIds), '?'));
        $typePlaceholders = implode(',', array_fill(0, count($docTypes), '?'));
        $params = array_merge(array_map('intval', $companyIds), array_values($docTypes), [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $sql = "SELECT * FROM documents WHERE company_id IN ($companyPlaceholders) AND doc_type IN ($typePlaceholders) AND issue_date BETWEEN ? AND ? ORDER BY issue_date ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function recentlyImportedOutsidePeriod(array $companyIds, array $docTypes, string $runStartedAt, string $startDate, string $endDate): int
    {
        if (!$companyIds || !$docTypes) {
            return 0;
        }
        $companyPlaceholders = implode(',', array_fill(0, count($companyIds), '?'));
        $typePlaceholders = implode(',', array_fill(0, count($docTypes), '?'));
        $params = array_merge(array_map('intval', $companyIds), array_values($docTypes), [$runStartedAt, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $sql = "SELECT COUNT(*) FROM documents WHERE company_id IN ($companyPlaceholders) AND doc_type IN ($typePlaceholders) AND imported_at >= ? AND (issue_date IS NULL OR issue_date < ? OR issue_date > ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function pendingNfeDocumentsForClosure(int $closureId): array
    {
        $stmt = $this->pdo->prepare("SELECT d.* FROM period_closure_items i JOIN documents d ON d.id = i.document_id WHERE i.closure_id = :closure_id AND i.doc_type = 'NFE' AND i.status IN ('apenas_resumo', 'pendente_manifestacao', 'aguardando_novo_download')");
        $stmt->execute(['closure_id'=>$closureId]);
        return $stmt->fetchAll();
    }

    public function pendingNfeDocumentsForCompany(int $companyId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM documents
            WHERE company_id = :company_id
              AND doc_type = 'NFE'
              AND access_key IS NOT NULL
              AND status IN ('apenas_resumo', 'pendente_manifestacao')
              AND manifestation_status IN ('pending', 'error_science')
            ORDER BY issue_date ASC NULLS LAST, id ASC
            LIMIT :limit");
        if ((string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $this->pdo->prepare(str_replace(' NULLS LAST', '', "SELECT * FROM documents
                WHERE company_id = :company_id
                  AND doc_type = 'NFE'
                  AND access_key IS NOT NULL
                  AND status IN ('apenas_resumo', 'pendente_manifestacao')
                  AND manifestation_status IN ('pending', 'error_science')
                ORDER BY issue_date ASC NULLS LAST, id ASC
                LIMIT :limit"));
        }
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function ensureDistributionControl(int $companyId, string $docType, string $environment): array
    {
        $stmt = $this->pdo->prepare("INSERT INTO distribution_controls(company_id, doc_type, environment, updated_at)
            VALUES(:company_id, :doc_type, :environment, :updated_at)
            ON CONFLICT(company_id, doc_type, environment) DO NOTHING");
        $stmt->execute(['company_id'=>$companyId,'doc_type'=>$docType,'environment'=>$environment,'updated_at'=>date('c')]);
        return $this->distributionControl($companyId, $docType, $environment);
    }

    public function distributionControl(int $companyId, string $docType, string $environment): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM distribution_controls WHERE company_id = :company_id AND doc_type = :doc_type AND environment = :environment LIMIT 1");
        $stmt->execute(['company_id'=>$companyId,'doc_type'=>$docType,'environment'=>$environment]);
        return $stmt->fetch() ?: [];
    }

    public function lockDistributionControl(int $companyId, string $docType, string $environment, int $jobId, string $sourceContext): void
    {
        $this->ensureDistributionControl($companyId, $docType, $environment);
        $stmt = $this->pdo->prepare("UPDATE distribution_controls SET locked_by_job_id=:locked_by_job_id, locked_at=:locked_at, source_context=:source_context, updated_at=:updated_at WHERE company_id=:company_id AND doc_type=:doc_type AND environment=:environment");
        $stmt->execute([
            'company_id'=>$companyId,
            'doc_type'=>$docType,
            'environment'=>$environment,
            'locked_by_job_id'=>$jobId,
            'locked_at'=>date('c'),
            'source_context'=>$sourceContext,
            'updated_at'=>date('c'),
        ]);
    }

    public function releaseDistributionControl(int $companyId, string $docType, string $environment, array $result): void
    {
        $cooldownUntil = $result['cooldown_until'] ?? null;
        $stmt = $this->pdo->prepare("UPDATE distribution_controls SET
            last_distribution_check_at=:last_distribution_check_at,
            last_distribution_result=:last_distribution_result,
            last_ult_nsu=:last_ult_nsu,
            last_max_nsu=:last_max_nsu,
            cooldown_until=:cooldown_until,
            locked_by_job_id=NULL,
            locked_at=NULL,
            source_context=:source_context,
            updated_at=:updated_at
            WHERE company_id=:company_id AND doc_type=:doc_type AND environment=:environment");
        $stmt->execute([
            'company_id'=>$companyId,
            'doc_type'=>$docType,
            'environment'=>$environment,
            'last_distribution_check_at'=>date('c'),
            'last_distribution_result'=>$result['last_distribution_result'] ?? null,
            'last_ult_nsu'=>$result['last_ult_nsu'] ?? null,
            'last_max_nsu'=>$result['last_max_nsu'] ?? null,
            'cooldown_until'=>$cooldownUntil,
            'source_context'=>$result['source_context'] ?? null,
            'updated_at'=>date('c'),
        ]);
    }

    public function clearDistributionLock(int $companyId, string $docType, string $environment): void
    {
        $stmt = $this->pdo->prepare("UPDATE distribution_controls SET locked_by_job_id=NULL, locked_at=NULL, updated_at=:updated_at WHERE company_id=:company_id AND doc_type=:doc_type AND environment=:environment");
        $stmt->execute(['company_id'=>$companyId,'doc_type'=>$docType,'environment'=>$environment,'updated_at'=>date('c')]);
    }

    public function hasRunningJob(int $companyId, string $docType): bool
    {
        $jobTypes = [$docType, 'collect_all', 'collect_missing', 'period_' . strtolower($docType)];
        $placeholders = implode(',', array_fill(0, count($jobTypes), '?'));
        $params = array_merge([$companyId], $jobTypes);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM jobs WHERE company_id = ? AND job_type IN ($placeholders) AND status = 'running'");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function repairDocumentClassification(): array
    {
        $stmt = $this->pdo->query("SELECT id, doc_type, model, access_key, status, manifestation_status, raw_xml, schema_name, notes FROM documents
            WHERE status IN ('apenas_resumo', 'pendente_manifestacao')
               OR doc_type = 'NFE'
               OR model = '58'
               OR schema_name LIKE '%MDFe%'");
        $repaired = ['mdfe' => 0, 'canceladas' => 0, 'pendencias' => 0];

        foreach ($stmt->fetchAll() as $doc) {
            $rawXml = (string)($doc['raw_xml'] ?? '');
            $schema = (string)($doc['schema_name'] ?? '');
            $accessKey = preg_replace('/\D+/', '', (string)($doc['access_key'] ?? ''));
            $model = strlen($accessKey) === 44 ? substr($accessKey, 20, 2) : (string)($doc['model'] ?? '');
            $local = $this->xmlRootAndValues($rawXml);
            $eventText = mb_strtolower($local['xEvento']);
            $isEvent = in_array($local['root'], ['resevento', 'procevento', 'proceventonfe', 'proceventocte', 'proceventomdfe'], true);
            $isMdfeEvent = $isEvent && (str_contains($eventText, 'mdf-e') || str_contains($eventText, 'mdfe'));
            $isCteEvent = $isEvent && (str_contains($eventText, 'ct-e') || str_contains($eventText, 'cte'));
            $isMdfe = $model === '58'
                || stripos($schema, 'MDFe') !== false
                || in_array($local['root'], ['resmdfe', 'mdfe', 'mdfeproc'], true)
                || $local['chMDFe'] !== ''
                || $isMdfeEvent;
            $isNfe = !$isMdfe && (strtoupper((string)$doc['doc_type']) === 'NFE' || $model === '55' || $local['chNFe'] !== '');
            $nfeSituation = $local['cSitNFe'];

            $updates = [];
            if ($isMdfe) {
                $updates = [
                    'doc_type' => 'MDFE',
                    'model' => '58',
                    'status' => 'xml_completo',
                    'manifestation_status' => 'not_applicable',
                    'notes' => trim((string)($doc['notes'] ?? '') . ' MDF-e reclassificado automaticamente; nao exige manifestacao de NF-e.'),
                ];
                $repaired['mdfe']++;
            } elseif ($isCteEvent) {
                $updates = [
                    'doc_type' => 'CTE',
                    'model' => '57',
                    'status' => 'xml_completo',
                    'manifestation_status' => 'not_applicable',
                    'notes' => trim((string)($doc['notes'] ?? '') . ' Evento de CT-e reclassificado automaticamente; nao exige manifestacao de NF-e.'),
                ];
            } elseif ($isNfe && $nfeSituation === '3') {
                $updates = [
                    'status' => 'cancelado',
                    'manifestation_status' => 'not_applicable',
                    'notes' => trim((string)($doc['notes'] ?? '') . ' NF-e cancelada conforme cSitNFe=3 no resumo.'),
                ];
                $repaired['canceladas']++;
            } elseif ($isNfe && $nfeSituation !== '' && $nfeSituation !== '1') {
                $updates = [
                    'status' => 'denegado',
                    'manifestation_status' => 'not_applicable',
                    'notes' => trim((string)($doc['notes'] ?? '') . ' NF-e sem manifestacao aplicavel conforme cSitNFe=' . $nfeSituation . ' no resumo.'),
                ];
            } elseif ($isNfe && in_array((string)$doc['status'], ['apenas_resumo', 'pendente_manifestacao'], true) && in_array($nfeSituation, ['', '1'], true)) {
                $updates = [
                    'status' => 'pendente_manifestacao',
                    'manifestation_status' => 'pending',
                ];
                $repaired['pendencias']++;
            } elseif ($isNfe && in_array((string)$doc['status'], ['xml_completo', 'cancelado', 'denegado'], true) && (string)$doc['manifestation_status'] === 'pending') {
                $updates = ['manifestation_status' => 'not_applicable'];
            } elseif (strtoupper((string)$doc['doc_type']) !== 'NFE' && (string)$doc['manifestation_status'] === 'pending') {
                $updates = ['manifestation_status' => 'not_applicable'];
            }

            if ($updates) {
                $this->updateDocumentFields((int)$doc['id'], $updates);
            }
        }

        return $repaired;
    }

    public function repairNfeEntryCompanyAssignments(int $limit = 20000): array
    {
        $stmt = $this->pdo->prepare("SELECT id, company_id, company_name, company_cnpj, issuer_cnpj, issuer_name, recipient_cnpj, recipient_name, status, manifestation_status, notes, raw_xml, xml_path
            FROM documents
            WHERE doc_type = 'NFE'
              AND (COALESCE(raw_xml, '') <> '' OR COALESCE(xml_path, '') <> '')
            ORDER BY id DESC
            LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $updatedStore = 0;
        $ownSales = 0;
        foreach ($stmt->fetchAll() as $doc) {
            $xml = $this->documentXmlFromRow($doc);
            $parties = $this->parseNfePartiesFromXml($xml);
            if (!$parties) {
                continue;
            }

            $updates = [];
            $recipientCompany = $parties['recipient_cnpj'] !== '' ? $this->findCompanyByCnpj($parties['recipient_cnpj']) : null;
            if ($recipientCompany && (int)($doc['company_id'] ?? 0) !== (int)$recipientCompany['id']) {
                $updates['company_id'] = (int)$recipientCompany['id'];
                $updates['company_name'] = (string)$recipientCompany['company_name'];
                $updates['company_cnpj'] = (string)$recipientCompany['cnpj'];
                $updatedStore++;
            }

            if ($parties['issuer_cnpj'] !== '' && (string)($doc['issuer_cnpj'] ?? '') !== $parties['issuer_cnpj']) {
                $updates['issuer_cnpj'] = $parties['issuer_cnpj'];
            }
            if ($parties['issuer_name'] !== '' && (string)($doc['issuer_name'] ?? '') !== $parties['issuer_name']) {
                $updates['issuer_name'] = $parties['issuer_name'];
            }
            if ($parties['recipient_cnpj'] !== '' && (string)($doc['recipient_cnpj'] ?? '') !== $parties['recipient_cnpj']) {
                $updates['recipient_cnpj'] = $parties['recipient_cnpj'];
            }
            if ($parties['recipient_name'] !== '' && (string)($doc['recipient_name'] ?? '') !== $parties['recipient_name']) {
                $updates['recipient_name'] = $parties['recipient_name'];
            }

            $issuerCompany = $parties['issuer_cnpj'] !== '' ? $this->findCompanyByCnpj($parties['issuer_cnpj']) : null;
            if ($issuerCompany && !$recipientCompany) {
                $updates['manifestation_status'] = 'not_applicable';
                $notes = trim((string)($doc['notes'] ?? '') . ' NF-e de saida emitida por empresa cadastrada; removida da rotina de Entradas.');
                $updates['notes'] = $notes;
                $ownSales++;
            }

            if ($updates) {
                $this->updateDocumentFields((int)$doc['id'], $updates);
            }
        }

        return ['lojas_corrigidas' => $updatedStore, 'vendas_proprias' => $ownSales];
    }

    private function parseNfePartiesFromXml(string $xml): ?array
    {
        if (trim($xml) === '') {
            return null;
        }
        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
            return null;
        }
        $xp = new \DOMXPath($dom);
        $infNfe = $xp->query('//*[local-name()="infNFe"]');
        if (!$infNfe || $infNfe->length === 0) {
            return null;
        }
        $text = static function (string $query) use ($xp): string {
            $nodes = $xp->query($query);
            if (!$nodes || $nodes->length === 0) {
                return '';
            }
            return trim((string)$nodes->item(0)?->textContent);
        };

        return [
            'issuer_cnpj' => preg_replace('/\D+/', '', $text('//*[local-name()="emit"]/*[local-name()="CNPJ"]')) ?: '',
            'issuer_name' => $text('//*[local-name()="emit"]/*[local-name()="xNome"]'),
            'recipient_cnpj' => preg_replace('/\D+/', '', $text('//*[local-name()="dest"]/*[local-name()="CNPJ"]')) ?: '',
            'recipient_name' => $text('//*[local-name()="dest"]/*[local-name()="xNome"]'),
        ];
    }

    public function migrateInformativeEventsFromDocuments(): array
    {
        $stmt = $this->pdo->query("SELECT id, company_id, access_key, raw_xml, schema_name, digest FROM documents
            WHERE raw_xml LIKE '%<resEvento%' OR raw_xml LIKE '%<procEvento%' OR raw_xml LIKE '%<evento%'");
        $migrated = 0;
        $deleted = 0;
        $types = [];

        foreach ($stmt->fetchAll() as $doc) {
            $event = $this->parseInformativeEventXml((string)($doc['raw_xml'] ?? ''));
            if (!$event) {
                continue;
            }
            $event['company_id'] = $doc['company_id'] ?? null;
            $event['schema_name'] = $doc['schema_name'] ?? null;
            $event['raw_xml'] = $doc['raw_xml'] ?? null;
            $event['digest'] = $doc['digest'] ?: hash('sha256', (string)$doc['raw_xml']);
            $this->saveDocumentEvent($event);
            $migrated++;
            $types[$event['event_name'] ?: 'Evento informativo'] = ($types[$event['event_name'] ?: 'Evento informativo'] ?? 0) + 1;

            $delete = $this->pdo->prepare('DELETE FROM documents WHERE id = :id');
            $delete->execute(['id' => (int)$doc['id']]);
            $deleted += $delete->rowCount();
        }

        return ['migrated' => $migrated, 'deleted' => $deleted, 'types' => $types];
    }

    public function repairNFeCancellationByAccessKey(string $accessKey): int
    {
        $accessKey = preg_replace('/\D+/', '', $accessKey);
        if (strlen($accessKey) !== 44) { return 0; }
        $stmt = $this->pdo->prepare("SELECT id, raw_xml, xml_path FROM documents WHERE access_key = :access_key AND doc_type IN ('NFE', 'NFCE') ORDER BY id DESC");
        $stmt->execute(['access_key' => $accessKey]);
        $updated = 0;
        foreach ($stmt->fetchAll() as $document) {
            $xml = (string)($document['raw_xml'] ?? '');
            if ($xml === '' && is_file((string)($document['xml_path'] ?? ''))) { $xml = (string)file_get_contents((string)$document['xml_path']); }
            $cancelEvent = stripos($xml, '110111') !== false && (stripos($xml, '>135<') !== false || stripos($xml, '>136<') !== false || stripos($xml, '>155<') !== false);
            $cancelProtocol = stripos($xml, '>101<') !== false && stripos($xml, 'cancel') !== false;
            if ($cancelEvent || $cancelProtocol) { $updated += $this->markDocumentCancelledFromEvent((int)$document['id']); }
        }
        return $updated;
    }
    public function repairCancelledDocumentsFromEvents(): int
    {
        $stmt = $this->pdo->query("SELECT e.document_id, e.access_key, e.event_type, e.event_name, e.event_date, e.protocol
            FROM document_events e
            WHERE e.event_type IN ('110111')
               OR LOWER(COALESCE(e.event_name, '')) LIKE '%cancel%'");
        $updated = 0;
        foreach ($stmt->fetchAll() as $event) {
            $documentId = (int)($event['document_id'] ?? 0);
            $doc = null;
            if ($documentId <= 0) {
                $doc = $this->findDocumentByAccessKey('NFE', (string)$event['access_key'])
                    ?: $this->findDocumentByAccessKey('NFCE', (string)$event['access_key'])
                    ?: $this->findDocumentByAccessKey('CTE', (string)$event['access_key']);
                $documentId = (int)($doc['id'] ?? 0);
            } else {
                $doc = $this->findDocument($documentId);
            }
            if ($documentId > 0 && $doc && $this->isDocumentStatusCancellationEvent(strtoupper((string)($doc['doc_type'] ?? '')), $event)) {
                $updated += $this->markDocumentCancelledFromEvent($documentId, (string)($event['event_date'] ?? ''), (string)($event['protocol'] ?? ''));
            }
        }
        return $updated;
    }

    public function repairCteCancellationStatuses(?int $companyId = null): array
    {
        $now = date('c');
        $companySql = '';
        $companyParams = [];
        if ($companyId !== null && $companyId > 0) { $companySql = ' AND company_id = :company_id'; $companyParams['company_id'] = $companyId; }
        $downgrade = $this->pdo->prepare("UPDATE documents
            SET status = CASE WHEN COALESCE(raw_xml, '') <> '' OR COALESCE(xml_path, '') <> '' THEN 'xml_completo' ELSE 'apenas_resumo' END,
                updated_at = :updated_at
            WHERE doc_type = 'CTE' AND status = 'cancelado'
              AND COALESCE(notes, '') NOT LIKE '%cStat=101%'
              AND NOT EXISTS (
                  SELECT 1 FROM document_events e
                  WHERE (e.document_id = documents.id OR e.access_key = documents.access_key)
                    AND e.event_type IN ('110111')
              )" . $companySql);
        $downgrade->execute(array_merge(['updated_at' => $now], $companyParams));
        $reopened = $downgrade->rowCount();
        $upgrade = $this->pdo->prepare("UPDATE documents
            SET status = 'cancelado', manifestation_status = 'not_applicable',
                notes = TRIM(COALESCE(notes, '') || ' CT-e marcado como cancelado conforme evento 110111 recebido da SEFAZ.'),
                updated_at = :updated_at
            WHERE doc_type = 'CTE' AND status <> 'cancelado'
              AND EXISTS (
                  SELECT 1 FROM document_events e
                  WHERE (e.document_id = documents.id OR e.access_key = documents.access_key)
                    AND e.event_type IN ('110111')
              )" . $companySql);
        $upgrade->execute(array_merge(['updated_at' => $now], $companyParams));
        return ['reopened' => $reopened, 'cancelled' => $upgrade->rowCount()];
    }

    public function nfseCancellationCandidates(int $companyId, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $stmt = $this->pdo->prepare("SELECT *
            FROM documents
            WHERE company_id = :company_id
              AND doc_type = 'NFSE'
              AND COALESCE(status, '') <> 'cancelado'
              AND COALESCE(access_key, '') <> ''
            ORDER BY issue_date DESC, id DESC
            LIMIT :limit");
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function applyNFSeCancellationStatus(string $accessKey, array $status, ?int $companyId = null): int
    {
        $accessKey = preg_replace('/\D+/', '', $accessKey) ?: '';
        if (strlen($accessKey) < 40) {
            return 0;
        }
        $eventType = trim((string)($status['event_type'] ?? ''));
        $eventName = trim((string)($status['event_name'] ?? ''));
        $eventStatus = preg_replace('/\D+/', '', (string)($status['event_cStat'] ?? $status['cStat'] ?? '')) ?: '';
        $message = trim((string)($status['xMotivo'] ?? $status['message'] ?? ''));
        $protocol = trim((string)($status['nProt'] ?? $status['protocol'] ?? ''));
        $eventDate = trim((string)($status['dhRecbto'] ?? $status['event_date'] ?? ''));

        $eventTypeNormalized = mb_strtolower($eventType);
        $eventNameNormalized = mb_strtolower($eventName);
        $messageNormalized = mb_strtolower($message);
        $messageSaysCancel = preg_match('/\bcancelad[ao]\b|\bcancelamento\b/u', $messageNormalized) === 1
            && preg_match('/\b(nenhum|nao|não)\b.{0,80}\bcancel/u', $messageNormalized) !== 1;
        $isCancelled = (bool)($status['cancelled'] ?? false) && (
            in_array($eventStatus, ['101', '135', '136', '155'], true)
            || in_array($eventTypeNormalized, ['101101', '105102', '105104', '110111', 'cancelamento', 'cancelamento_nfse', 'cancelamento de nfs-e'], true)
            || preg_match('/\bcancelad[ao]\b|\bcancelamento\b/u', $eventNameNormalized) === 1
            || $messageSaysCancel
        );
        if (!$isCancelled) {
            return 0;
        }

        $note = 'NFS-e cancelada conforme consulta de eventos no Portal Nacional.';
        if ($eventType !== '') {
            $note .= ' Evento ' . $eventType . '.';
        }
        if ($eventStatus !== '') {
            $note .= ' cStat=' . $eventStatus . '.';
        }
        if ($message !== '') {
            $note .= ' ' . $message . '.';
        }
        if ($protocol !== '') {
            $note .= ' Protocolo ' . $protocol . '.';
        }
        if ($eventDate !== '') {
            $note .= ' Evento em ' . $eventDate . '.';
        }

        $params = [
            'access_key' => $accessKey,
            'note' => $note,
            'updated_at' => date('c'),
        ];
        $whereCompany = '';
        if ($companyId !== null && $companyId > 0) {
            $whereCompany = ' AND company_id = :company_id';
            $params['company_id'] = $companyId;
        }
        $stmt = $this->pdo->prepare("UPDATE documents
            SET status = 'cancelado',
                manifestation_status = 'not_applicable',
                notes = TRIM(COALESCE(notes, '') || ' ' || :note),
                updated_at = :updated_at
            WHERE access_key = :access_key
              AND doc_type = 'NFSE'
              {$whereCompany}
              AND status <> 'cancelado'");
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function markNFSeCancelledBySubstitution(string $accessKey, ?int $companyId = null): int
    {
        $accessKey = preg_replace('/\D+/', '', $accessKey) ?: '';
        if (strlen($accessKey) < 40) {
            return 0;
        }

        $params = [
            'access_key' => $accessKey,
            'needle' => '%<chSubstda>' . $accessKey . '</chSubstda>%',
            'note' => 'NFS-e cancelada por substituição conforme tag chSubstda localizada em NFS-e substituta importada no portal.',
            'updated_at' => date('c'),
        ];
        $whereCompany = '';
        if ($companyId !== null && $companyId > 0) {
            $whereCompany = ' AND d.company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        $stmt = $this->pdo->prepare("UPDATE documents d
            SET status = 'cancelado',
                manifestation_status = 'not_applicable',
                notes = TRIM(COALESCE(d.notes, '') || ' ' || :note),
                updated_at = :updated_at
            WHERE d.doc_type = 'NFSE'
              AND d.access_key = :access_key
              {$whereCompany}
              AND COALESCE(d.status, '') <> 'cancelado'
              AND EXISTS (
                  SELECT 1
                  FROM documents subst
                  WHERE subst.doc_type = 'NFSE'
                    AND subst.company_id = d.company_id
                    AND subst.id <> d.id
                    AND COALESCE(subst.raw_xml, '') LIKE :needle
              )");
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function applyNFeProtocolStatus(string $accessKey, array $status, ?int $companyId = null): int
    {
        $accessKey = preg_replace('/\D+/', '', $accessKey) ?: '';
        if (strlen($accessKey) !== 44) {
            return 0;
        }
        $cStat = preg_replace('/\D+/', '', (string)($status['cStat'] ?? '')) ?: '';
        $xMotivo = trim((string)($status['xMotivo'] ?? ''));
        $protocol = trim((string)($status['nProt'] ?? ''));
        $receivedAt = trim((string)($status['dhRecbto'] ?? ''));
        $eventType = trim((string)($status['event_type'] ?? ''));
        $eventStatus = trim((string)($status['event_cStat'] ?? ''));
        $newStatus = match ($cStat) {
            '101', '151', '155' => 'cancelado',
            '110', '301', '302', '303' => 'denegado',
            '100', '150' => 'xml_completo',
            default => null,
        };
        if ($newStatus === null) {
            return 0;
        }

        $note = 'Situação consultada na SEFAZ: cStat=' . $cStat . ($xMotivo !== '' ? ' - ' . $xMotivo : '') . '.';
        if ($eventType !== '' || $eventStatus !== '') {
            $note .= ' Evento' . ($eventType !== '' ? ' ' . $eventType : '') . ($eventStatus !== '' ? ' cStat=' . $eventStatus : '') . '.';
        }
        if ($protocol !== '') {
            $note .= ' Protocolo ' . $protocol . '.';
        }
        if ($receivedAt !== '') {
            $note .= ' Retorno em ' . $receivedAt . '.';
        }

        $params = [
            'access_key' => $accessKey,
            'status' => $newStatus,
            'manifestation_status' => 'not_applicable',
            'note' => $note,
            'updated_at' => date('c'),
        ];
        $whereCompany = '';
        if ($companyId !== null && $companyId > 0) {
            $whereCompany = ' AND company_id = :company_id';
            $params['company_id'] = $companyId;
        }
        $stmt = $this->pdo->prepare("UPDATE documents
            SET status = :status,
                manifestation_status = :manifestation_status,
                notes = TRIM(COALESCE(notes, '') || ' ' || :note),
                updated_at = :updated_at
            WHERE access_key = :access_key
              AND doc_type IN ('NFE', 'NFCE')
              {$whereCompany}
              AND status <> :status");
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function applyCTeProtocolStatus(string $accessKey, array $status, ?int $companyId = null): int
    {
        $accessKey = preg_replace('/\D+/', '', $accessKey) ?: '';
        if (strlen($accessKey) !== 44) {
            return 0;
        }
        $cStat = preg_replace('/\D+/', '', (string)($status['cStat'] ?? '')) ?: '';
        $xMotivo = trim((string)($status['xMotivo'] ?? ''));
        $protocol = trim((string)($status['nProt'] ?? ''));
        $receivedAt = trim((string)($status['dhRecbto'] ?? ''));
        $eventType = trim((string)($status['event_type'] ?? ''));
        $eventStatus = trim((string)($status['event_cStat'] ?? ''));
        $newStatus = match ($cStat) {
            '101', '151', '155' => 'cancelado',
            '110', '301', '302', '303' => 'denegado',
            '100', '150' => 'xml_completo',
            default => null,
        };
        if ($newStatus === null) {
            return 0;
        }
        if ($newStatus === 'cancelado' && $eventType !== '' && !in_array($eventType, ['110111'], true)) {
            return 0;
        }

        $note = 'Situacao CT-e consultada na SEFAZ: cStat=' . $cStat . ($xMotivo !== '' ? ' - ' . $xMotivo : '') . '.';
        if ($eventType !== '' || $eventStatus !== '') {
            $note .= ' Evento' . ($eventType !== '' ? ' ' . $eventType : '') . ($eventStatus !== '' ? ' cStat=' . $eventStatus : '') . '.';
        }
        if ($protocol !== '') {
            $note .= ' Protocolo ' . $protocol . '.';
        }
        if ($receivedAt !== '') {
            $note .= ' Retorno em ' . $receivedAt . '.';
        }

        $params = [
            'access_key' => $accessKey,
            'status' => $newStatus,
            'manifestation_status' => 'not_applicable',
            'note' => $note,
            'updated_at' => date('c'),
        ];
        $whereCompany = '';
        if ($companyId !== null && $companyId > 0) {
            $whereCompany = ' AND company_id = :company_id';
            $params['company_id'] = $companyId;
        }
        $stmt = $this->pdo->prepare("UPDATE documents
            SET status = :status,
                manifestation_status = :manifestation_status,
                notes = TRIM(COALESCE(notes, '') || ' ' || :note),
                updated_at = :updated_at
            WHERE access_key = :access_key
              AND doc_type = 'CTE'
              {$whereCompany}
              AND status <> :status");
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function parseInformativeEventXml(string $xml): ?array
    {
        if (trim($xml) === '') {
            return null;
        }
        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
            return null;
        }
        $root = strtolower((string)($dom->documentElement?->localName ?? ''));
        if (!in_array($root, ['resevento', 'evento', 'procevento', 'proceventonfe', 'proceventocte', 'proceventomdfe'], true)) {
            return null;
        }
        $xp = new \DOMXPath($dom);
        $value = static function (string $name) use ($xp): ?string {
            $nodes = $xp->query('//*[local-name()="' . $name . '"]');
            if (!$nodes || $nodes->length === 0) {
                return null;
            }
            $text = trim((string)$nodes->item(0)?->textContent);
            return $text === '' ? null : $text;
        };
        $accessKey = preg_replace('/\D+/', '', (string)($value('chNFe') ?: $value('chCTe')));
        if (strlen($accessKey) !== 44) {
            return null;
        }
        return [
            'access_key' => $accessKey,
            'event_type' => $value('tpEvento'),
            'event_name' => $value('xEvento') ?: 'Evento informativo',
            'event_date' => $value('dhEvento') ?: $value('dhRecbto'),
            'protocol' => $value('nProt'),
            'issuer_cnpj' => preg_replace('/\D+/', '', (string)$value('CNPJ')) ?: null,
        ];
    }

    private function updateDocumentFields(int $id, array $updates): void
    {
        $allowed = ['doc_type', 'model', 'status', 'manifestation_status', 'notes'];
        $sets = [];
        $params = ['id' => $id, 'updated_at' => date('c')];
        foreach ($updates as $field => $value) {
            if (!in_array($field, $allowed, true)) {
                continue;
            }
            $sets[] = "{$field} = :{$field}";
            $params[$field] = $value;
        }
        if (!$sets) {
            return;
        }
        $sets[] = 'updated_at = :updated_at';
        $stmt = $this->pdo->prepare('UPDATE documents SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
    }

    private function isCancellationEvent(array $event): bool
    {
        $type = preg_replace('/\D+/', '', (string)($event['event_type'] ?? ''));
        $name = mb_strtolower((string)($event['event_name'] ?? ''));
        return in_array($type, ['101101', '105102', '105104', '110111'], true) || str_contains($name, 'cancel');
    }

    private function isDocumentStatusCancellationEvent(string $docType, array $event): bool
    {
        $type = preg_replace('/\D+/', '', (string)($event['event_type'] ?? ''));
        $name = mb_strtolower((string)($event['event_name'] ?? ''));
        if ($docType === 'CTE') {
            return in_array($type, ['110111'], true);
        }
        return in_array($type, ['101101', '105102', '105104', '110111'], true) || str_contains($name, 'cancel');
    }

    private function markDocumentCancelledFromEvent(int $documentId, string $eventDate = '', string $protocol = ''): int
    {
        $noteParts = ['Documento cancelado conforme evento recebido da SEFAZ.'];
        if ($eventDate !== '') {
            $noteParts[] = 'Evento em ' . $eventDate . '.';
        }
        if ($protocol !== '') {
            $noteParts[] = 'Protocolo ' . $protocol . '.';
        }
        $stmt = $this->pdo->prepare("UPDATE documents
            SET status = 'cancelado',
                manifestation_status = 'not_applicable',
                notes = TRIM(COALESCE(notes, '') || ' ' || :note),
                updated_at = :updated_at
            WHERE id = :id
              AND status <> 'cancelado'");
        $stmt->execute([
            'id' => $documentId,
            'note' => implode(' ', $noteParts),
            'updated_at' => date('c'),
        ]);
        return $stmt->rowCount();
    }

    private function xmlRootAndValues(string $xml): array
    {
        $values = ['root' => '', 'chMDFe' => '', 'chNFe' => '', 'cSitNFe' => '', 'xEvento' => ''];
        if (trim($xml) === '') {
            return $values;
        }
        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
            return $values;
        }
        $values['root'] = strtolower((string)($dom->documentElement?->localName ?? ''));
        $xp = new \DOMXPath($dom);
        foreach (['chMDFe', 'chNFe', 'cSitNFe', 'xEvento'] as $name) {
            $nodes = $xp->query('//*[local-name()="' . $name . '"]');
            if ($nodes && $nodes->length > 0) {
                $values[$name] = trim((string)$nodes->item(0)?->textContent);
            }
        }
        return $values;
    }

    /**
     * Monta a cláusula de filtros da conferência de faturamento.
     * A rotina usa dados integrados do ERP, por isso os filtros são sempre
     * aplicados sobre as tabelas próprias de faturamento, não sobre XMLs.
     */
    private function revenueWhere(array $filters, string $alias = 'r'): array
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        $where = [];
        $params = [];
        $dateStart = $this->normalizeFilterDate((string)($filters['date_start'] ?? ''));
        $dateEnd = $this->normalizeFilterDate((string)($filters['date_end'] ?? ''));
        if ($dateStart !== null) { $where[] = "{$prefix}issue_date >= :date_start"; $params['date_start'] = $dateStart; }
        if ($dateEnd !== null) { $where[] = "{$prefix}issue_date <= :date_end"; $params['date_end'] = $dateEnd; }
        foreach ([
            'document_type' => 'document_type',
            'document_status' => 'document_status',
            'purpose' => 'purpose',
            'sale_return' => 'purpose',
        ] as $filterKey => $column) {
            if ((string)($filters[$filterKey] ?? '') !== '') {
                $where[] = "{$prefix}{$column} = :{$filterKey}";
                $params[$filterKey] = $filters[$filterKey];
            }
        }
        foreach (['issuing_store_cnpj' => 'issuing_store_cnpj', 'order_store_cnpj' => 'order_store_cnpj'] as $filterKey => $column) {
            $values = $this->filterDigitValues($filters[$filterKey] ?? '');
            if ($values) {
                $placeholders = [];
                foreach ($values as $idx => $value) {
                    $key = $filterKey . '_' . $idx;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $value;
                }
                $where[] = $this->digitsOnlySql("{$prefix}{$column}") . ' IN (' . implode(',', $placeholders) . ')';
            }
        }
        $orderStoreNames = $this->filterStringValues($filters['order_store_name'] ?? '');
        if ($orderStoreNames) {
            $placeholders = [];
            foreach ($orderStoreNames as $idx => $value) {
                $key = 'order_store_name_' . $idx;
                $placeholders[] = ':' . $key;
                $params[$key] = mb_strtolower($value);
            }
            $where[] = 'LOWER(COALESCE(' . $prefix . 'order_store_name, \'\')) IN (' . implode(',', $placeholders) . ')';
        }
        foreach ([
            'issuing_store_name' => 'issuing_store_name',
            'customer_name' => 'customer_name',
            'customer_document' => 'customer_document',
            'seller_name' => 'seller_name',
            'order_number' => 'order_number',
            'number' => 'number',
            'series' => 'series',
            'access_key' => 'access_key',
        ] as $filterKey => $column) {
            if ((string)($filters[$filterKey] ?? '') !== '') {
                $where[] = "LOWER(COALESCE({$prefix}{$column}, '')) LIKE :{$filterKey}";
                $params[$filterKey] = '%' . mb_strtolower((string)$filters[$filterKey]) . '%';
            }
        }
        if ((string)($filters['order_link'] ?? '') === 'with') { $where[] = "NULLIF(TRIM(COALESCE({$prefix}order_number, '')), '') IS NOT NULL"; }
        if ((string)($filters['order_link'] ?? '') === 'without') { $where[] = "NULLIF(TRIM(COALESCE({$prefix}order_number, '')), '') IS NULL"; }
        if ((string)($filters['xml_available'] ?? '') !== '') {
            $where[] = ((string)$filters['xml_available'] === '1') ? "{$prefix}xml_content IS NOT NULL AND {$prefix}xml_content <> ''" : "({$prefix}xml_content IS NULL OR {$prefix}xml_content = '')";
        }
        if ((string)($filters['amount_min'] ?? '') !== '') {
            $where[] = "{$prefix}gross_amount >= :amount_min";
            $params['amount_min'] = (float)str_replace(',', '.', (string)$filters['amount_min']);
        }
        if ((string)($filters['amount_max'] ?? '') !== '') {
            $where[] = "{$prefix}gross_amount <= :amount_max";
            $params['amount_max'] = (float)str_replace(',', '.', (string)$filters['amount_max']);
        }
        if (empty($filters['include_returns'])) {
            $where[] = "{$prefix}purpose <> 'devolucao'";
        }
        if ((string)($filters['product'] ?? '') !== '' || (string)($filters['product_group'] ?? '') !== '' || (string)($filters['cfop'] ?? '') !== '' || (string)($filters['ncm'] ?? '') !== '' || (string)($filters['cst_csosn'] ?? '') !== '') {
            $itemWhere = ["i.revenue_document_id = {$prefix}id"];
            foreach (['product' => 'product_name', 'product_group' => 'product_group', 'cfop' => 'cfop', 'ncm' => 'ncm', 'cst_csosn' => 'cst_csosn'] as $filterKey => $column) {
                if ((string)($filters[$filterKey] ?? '') !== '') {
                    $itemWhere[] = "LOWER(COALESCE(i.{$column}, '')) LIKE :item_{$filterKey}";
                    $params['item_' . $filterKey] = '%' . mb_strtolower((string)$filters[$filterKey]) . '%';
                }
            }
            $where[] = 'EXISTS (SELECT 1 FROM revenue_items i WHERE ' . implode(' AND ', $itemWhere) . ')';
        }
        return [$where, $params];
    }

    private function digitsOnlySql(string $expression): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$expression}, ''), '.', ''), '/', ''), '-', ''), ' ', '')";
    }

    public function revenueFilterOptions(): array
    {
        $simple = function (string $column): array {
            $stmt = $this->pdo->query("SELECT DISTINCT {$column} AS value FROM revenue_documents WHERE {$column} IS NOT NULL AND {$column} <> '' ORDER BY {$column} ASC LIMIT 300");
            return array_map(static fn(array $row): string => (string)$row['value'], $stmt->fetchAll());
        };
        return [
            'types' => $simple('document_type'),
            'statuses' => $simple('document_status'),
            'purposes' => $simple('purpose'),
            'issuingStores' => $this->revenueStoreOptions('issuing'),
            'orderStores' => $this->revenueStoreOptions('order'),
        ];
    }

    private function revenueStoreOptions(string $kind): array
    {
        $nameColumn = $kind === 'issuing' ? 'issuing_store_name' : 'order_store_name';
        $cnpjColumn = $kind === 'issuing' ? 'issuing_store_cnpj' : 'order_store_cnpj';
        $stmt = $this->pdo->query("SELECT DISTINCT {$nameColumn} AS name, {$cnpjColumn} AS cnpj FROM revenue_documents WHERE {$nameColumn} IS NOT NULL AND {$nameColumn} <> '' ORDER BY {$nameColumn} ASC LIMIT 300");
        return $stmt->fetchAll();
    }

    public function revenueDashboard(array $filters): array
    {
        [$where, $params] = $this->revenueWhere($filters);
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $totals = $this->revenueTotals($filters);
        $considerCredits = !empty($filters['include_tax_credits']);
        return [
            'totals' => $totals,
            'today' => $this->revenueAmountForPeriod($filters, date('Y-m-d'), date('Y-m-d')),
            'week' => $this->revenueAmountForPeriod($filters, (new \DateTimeImmutable('monday this week'))->format('Y-m-d'), date('Y-m-d')),
            'month' => $this->revenueAmountForPeriod($filters, date('Y-m-01'), date('Y-m-d')),
            'previousMonth' => $this->revenueAmountForPeriod($filters, (new \DateTimeImmutable('first day of previous month'))->format('Y-m-d'), (new \DateTimeImmutable('last day of previous month'))->format('Y-m-d')),
            'todayBreakdown' => $this->revenueAmountBreakdownForPeriod($filters, date('Y-m-d'), date('Y-m-d')),
            'weekBreakdown' => $this->revenueAmountBreakdownForPeriod($filters, (new \DateTimeImmutable('monday this week'))->format('Y-m-d'), date('Y-m-d')),
            'monthBreakdown' => $this->revenueAmountBreakdownForPeriod($filters, date('Y-m-01'), date('Y-m-d')),
            'previousMonthBreakdown' => $this->revenueAmountBreakdownForPeriod($filters, (new \DateTimeImmutable('first day of previous month'))->format('Y-m-d'), (new \DateTimeImmutable('last day of previous month'))->format('Y-m-d')),
            'periodBreakdowns' => $this->revenueMetricBreakdowns($filters),
            'byCfop' => $this->revenueCfopGroup($filters, 500),
            'byIssuingStore' => $this->revenueGroup($filters, 'issuing_store_name', 'issuing_store_cnpj', 500),
            'byOrderStore' => $this->revenueGroup($filters, 'order_store_name', 'order_store_cnpj', 500),
            'bySeller' => $this->revenueGroup($filters, 'seller_name', null, 20),
            'topCustomers' => $this->revenueGroup($filters, 'customer_name', 'customer_document', 20),
            'dailyEvolution' => $this->revenueDailyEvolution($whereSql, $params),
            'topProducts' => $this->revenueItemGroup($where, $params, 'product_name', 20),
            'topGroups' => $this->revenueItemGroup($where, $params, 'product_group', 20),
            'taxes' => $this->revenueTaxSummary($whereSql, $params, $considerCredits),
        ];
    }

    public function revenueTotals(array $filters): array
    {
        [$where, $params] = $this->revenueWhere($filters);
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        // Devolucao pode vir do ERP com valor positivo; o dashboard sempre calcula gerencialmente como estorno.
        $returnExpr = "(purpose = 'devolucao' OR document_type = 'DEVOLUCAO_NFE' OR return_amount <> 0)";
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS documents_count,
            COALESCE(SUM(CASE WHEN NOT {$returnExpr} THEN gross_amount ELSE 0 END),0) AS gross_amount,
            COALESCE(SUM(CASE WHEN {$returnExpr} THEN ABS(CASE WHEN return_amount <> 0 THEN return_amount WHEN gross_amount <> 0 THEN gross_amount ELSE net_amount END) ELSE 0 END),0) AS return_amount,
            COALESCE(SUM(CASE WHEN {$returnExpr} THEN -ABS(CASE WHEN return_amount <> 0 THEN return_amount WHEN gross_amount <> 0 THEN gross_amount ELSE net_amount END) ELSE gross_amount END),0) AS net_amount,
            COALESCE(SUM(CASE WHEN {$returnExpr} THEN -ABS(taxes_amount) ELSE taxes_amount END),0) AS taxes_amount,
            COALESCE(SUM(CASE WHEN {$returnExpr} THEN -ABS(tax_credits_amount) ELSE tax_credits_amount END),0) AS tax_credits_amount,
            COALESCE(SUM(CASE WHEN NOT {$returnExpr} THEN 1 ELSE 0 END),0) AS sales_count
            FROM revenue_documents r{$whereSql}");
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];
        $documents = (int)($row['documents_count'] ?? 0);
        $salesCount = (int)($row['sales_count'] ?? 0);
        $taxes = (float)($row['taxes_amount'] ?? 0);
        $taxCredits = !empty($filters['include_tax_credits']) ? (float)($row['tax_credits_amount'] ?? 0) : 0.0;
        $taxBalance = $taxes - $taxCredits;
        return [
            'documents_count' => $documents,
            'gross_amount' => (float)($row['gross_amount'] ?? 0),
            'return_amount' => (float)($row['return_amount'] ?? 0),
            'net_amount' => (float)($row['net_amount'] ?? 0),
            'taxes_amount' => $taxes,
            'tax_credits_amount' => $taxCredits,
            'tax_balance' => $taxBalance,
            'average_ticket' => $salesCount > 0 ? ((float)($row['gross_amount'] ?? 0) / $salesCount) : 0.0,
        ];
    }

    private function revenueAmountForPeriod(array $filters, string $start, string $end): float
    {
        $periodFilters = $filters;
        $periodFilters['date_start'] = $start;
        $periodFilters['date_end'] = $end;
        return (float)$this->revenueMoneyBreakdown($periodFilters, 'net_amount')['total'];
    }

    private function revenueAmountBreakdownForPeriod(array $filters, string $start, string $end): array
    {
        $periodFilters = $filters;
        $periodFilters['date_start'] = $start;
        $periodFilters['date_end'] = $end;
        return $this->revenueMoneyBreakdown($periodFilters, 'net_amount');
    }

    private function revenueMetricBreakdowns(array $filters): array
    {
        return [
            'gross_amount' => $this->revenueMoneyBreakdown($filters, 'gross_amount'),
            'return_amount' => $this->revenueMoneyBreakdown($filters, 'return_amount'),
            'net_amount' => $this->revenueMoneyBreakdown($filters, 'net_amount'),
            'taxes_amount' => $this->revenueMoneyBreakdown($filters, 'taxes_amount', false),
            'tax_credits_amount' => $this->revenueMoneyBreakdown($filters, 'tax_credits_amount', false),
            'tax_balance' => $this->revenueMoneyBreakdown($filters, 'tax_balance', false),
            'average_ticket' => $this->revenueAverageTicketBreakdown($filters),
        ];
    }

    private function revenueAverageTicketBreakdown(array $filters): array
    {
        [$where, $params] = $this->revenueWhere($filters);
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $returnExpr = "(purpose = 'devolucao' OR document_type = 'DEVOLUCAO_NFE' OR return_amount <> 0)";
        $serviceExpr = "UPPER(REPLACE(REPLACE(REPLACE(COALESCE(document_type, ''), '-', ''), '_', ''), ' ', '')) = 'NFSE'";
        $stmt = $this->pdo->prepare("SELECT
            COALESCE(SUM(CASE WHEN NOT {$returnExpr} THEN gross_amount ELSE 0 END),0) AS gross_total,
            COALESCE(SUM(CASE WHEN NOT {$returnExpr} THEN 1 ELSE 0 END),0) AS gross_count,
            COALESCE(SUM(CASE WHEN {$serviceExpr} AND NOT {$returnExpr} THEN gross_amount ELSE 0 END),0) AS services_total,
            COALESCE(SUM(CASE WHEN {$serviceExpr} AND NOT {$returnExpr} THEN 1 ELSE 0 END),0) AS services_count,
            COALESCE(SUM(CASE WHEN NOT {$serviceExpr} AND NOT {$returnExpr} THEN gross_amount ELSE 0 END),0) AS resale_total,
            COALESCE(SUM(CASE WHEN NOT {$serviceExpr} AND NOT {$returnExpr} THEN 1 ELSE 0 END),0) AS resale_count
            FROM revenue_documents r{$whereSql}");
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];
        $grossCount = (int)($row['gross_count'] ?? 0);
        $serviceCount = (int)($row['services_count'] ?? 0);
        $resaleCount = (int)($row['resale_count'] ?? 0);
        return [
            'total' => $grossCount > 0 ? (float)($row['gross_total'] ?? 0) / $grossCount : 0.0,
            'services' => $serviceCount > 0 ? (float)($row['services_total'] ?? 0) / $serviceCount : 0.0,
            'resale' => $resaleCount > 0 ? (float)($row['resale_total'] ?? 0) / $resaleCount : 0.0,
        ];
    }
    private function revenueMoneyBreakdown(array $filters, string $metric, bool $includeCost = true): array
    {
        [$where, $params] = $this->revenueWhere($filters);
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $returnExpr = "(purpose = 'devolucao' OR document_type = 'DEVOLUCAO_NFE' OR return_amount <> 0)";
        $returnValue = "ABS(CASE WHEN return_amount <> 0 THEN return_amount WHEN gross_amount <> 0 THEN gross_amount ELSE net_amount END)";
        $expressions = [
            'gross_amount' => "CASE WHEN NOT {$returnExpr} THEN gross_amount ELSE 0 END",
            'return_amount' => "CASE WHEN {$returnExpr} THEN ABS({$returnValue}) ELSE 0 END",
            'net_amount' => "CASE WHEN {$returnExpr} THEN -ABS({$returnValue}) ELSE gross_amount END",
            'taxes_amount' => "CASE WHEN {$returnExpr} THEN -ABS(taxes_amount) ELSE taxes_amount END",
            'tax_credits_amount' => "CASE WHEN {$returnExpr} THEN -ABS(tax_credits_amount) ELSE tax_credits_amount END",
            'tax_balance' => "(CASE WHEN {$returnExpr} THEN -ABS(taxes_amount) ELSE taxes_amount END) - (CASE WHEN {$returnExpr} THEN -ABS(tax_credits_amount) ELSE tax_credits_amount END)",
        ];
        $expr = $expressions[$metric] ?? $expressions['net_amount'];
        $serviceExpr = "UPPER(REPLACE(REPLACE(REPLACE(COALESCE(document_type, ''), '-', ''), '_', ''), ' ', '')) = 'NFSE'";
        $stmt = $this->pdo->prepare("SELECT
            COALESCE(SUM({$expr}),0) AS total,
            COALESCE(SUM(CASE WHEN {$serviceExpr} THEN {$expr} ELSE 0 END),0) AS services,
            COALESCE(SUM(CASE WHEN NOT {$serviceExpr} THEN {$expr} ELSE 0 END),0) AS resale
            FROM revenue_documents r{$whereSql}");
        $stmt->execute($params);
        $row = $stmt->fetch() ?: ['total' => 0, 'services' => 0, 'resale' => 0];
        $result = ['total' => (float)$row['total'], 'resale' => (float)$row['resale'], 'services' => (float)$row['services']];
        if ($includeCost) {
            $result += $this->revenueCostBreakdown($filters, $metric);
        }
        return $result;
    }

    private function revenueCostBreakdown(array $filters, string $metric = 'net_amount'): array
    {
        [$where, $params] = $this->revenueWhere($filters);
        return $this->revenueCostForWhere($where, $params, $metric);
    }

    private function revenueCostForWhere(array $where, array $params, string $metric = 'net_amount'): array
    {
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $returnExpr = "(r.purpose = 'devolucao' OR r.document_type = 'DEVOLUCAO_NFE' OR r.return_amount <> 0)";
        $costExpr = "CASE WHEN {$returnExpr} THEN -ABS(COALESCE(ri.cost_amount, 0)) ELSE COALESCE(ri.cost_amount, 0) END";
        $costCondition = '1 = 1';
        if ($metric === 'gross_amount') {
            $costCondition = "NOT {$returnExpr}";
        } elseif ($metric === 'return_amount') {
            $costCondition = $returnExpr;
        }
        $serviceExpr = "UPPER(REPLACE(REPLACE(REPLACE(COALESCE(r.document_type, ''), '-', ''), '_', ''), ' ', '')) = 'NFSE'";
        $stmt = $this->pdo->prepare("SELECT
            COALESCE(SUM(CASE WHEN {$costCondition} THEN {$costExpr} ELSE 0 END),0) AS cost_total,
            COALESCE(SUM(CASE WHEN {$costCondition} AND {$serviceExpr} THEN {$costExpr} ELSE 0 END),0) AS cost_services,
            COALESCE(SUM(CASE WHEN {$costCondition} AND NOT {$serviceExpr} THEN {$costExpr} ELSE 0 END),0) AS cost_resale
            FROM revenue_documents r JOIN revenue_items ri ON ri.revenue_document_id = r.id{$whereSql}");
        $stmt->execute($params);
        $row = $stmt->fetch() ?: ['cost_total' => 0, 'cost_resale' => 0, 'cost_services' => 0];
        return [
            'cost_total' => (float)$row['cost_total'],
            'cost_resale' => (float)$row['cost_resale'],
            'cost_services' => (float)$row['cost_services'],
        ];
    }

    private function revenueGroup(array $filters, string $labelColumn, ?string $extraColumn = null, int $limit = 50): array
    {
        [$where, $params] = $this->revenueWhere($filters);
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $extraSelect = $extraColumn ? ", {$extraColumn} AS extra" : ", NULL AS extra";
        $extraGroup = $extraColumn ? ", {$extraColumn}" : "";
        $returnExpr = "(purpose = 'devolucao' OR document_type = 'DEVOLUCAO_NFE' OR return_amount <> 0)";
        $returnValue = "ABS(CASE WHEN return_amount <> 0 THEN return_amount WHEN gross_amount <> 0 THEN gross_amount ELSE net_amount END)";
        $netExpr = "CASE WHEN {$returnExpr} THEN -ABS({$returnValue}) ELSE gross_amount END";
        $serviceExpr = "UPPER(REPLACE(REPLACE(REPLACE(COALESCE(document_type, ''), '-', ''), '_', ''), ' ', '')) = 'NFSE'";
        $stmt = $this->pdo->prepare("SELECT {$labelColumn} AS label{$extraSelect}, COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN NOT {$returnExpr} THEN gross_amount ELSE 0 END),0) AS gross_amount,
            COALESCE(SUM(CASE WHEN {$returnExpr} THEN {$returnValue} ELSE 0 END),0) AS return_amount,
            COALESCE(SUM({$netExpr}),0) AS net_amount,
            COALESCE(SUM(CASE WHEN {$serviceExpr} THEN {$netExpr} ELSE 0 END),0) AS services,
            COALESCE(SUM(CASE WHEN NOT {$serviceExpr} THEN {$netExpr} ELSE 0 END),0) AS resale
            FROM revenue_documents r{$whereSql} GROUP BY {$labelColumn}{$extraGroup} ORDER BY net_amount DESC, total DESC LIMIT :limit");
        foreach ($params as $key => $value) { $stmt->bindValue(':' . $key, $value); }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $costWhere = $where;
            $costParams = $params;
            $costWhere[] = "COALESCE(r.{$labelColumn}, '') = :group_label";
            $costParams['group_label'] = (string)($row['label'] ?? '');
            if ($extraColumn) {
                $costWhere[] = "COALESCE(r.{$extraColumn}, '') = :group_extra";
                $costParams['group_extra'] = (string)($row['extra'] ?? '');
            }
            $row += $this->revenueCostForWhere($costWhere, $costParams, 'net_amount');
        }
        unset($row);
        return $rows;
    }

    private function revenueCfopGroup(array $filters, int $limit = 50): array
    {
        [$where, $params] = $this->revenueWhere($filters);
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        // O painel por CFOP usa itens como origem e aplica devolucoes como valor negativo para leitura gerencial/fiscal correta.
        $returnExpr = "(r.purpose = 'devolucao' OR r.document_type = 'DEVOLUCAO_NFE' OR r.return_amount <> 0)";
        $netExpr = "CASE WHEN {$returnExpr} THEN -ABS(ri.total_amount) ELSE ri.total_amount END";
        $costExpr = "CASE WHEN {$returnExpr} THEN -ABS(COALESCE(ri.cost_amount, 0)) ELSE COALESCE(ri.cost_amount, 0) END";
        $serviceExpr = "UPPER(REPLACE(REPLACE(REPLACE(COALESCE(r.document_type, ''), '-', ''), '_', ''), ' ', '')) = 'NFSE'";
        $stmt = $this->pdo->prepare("SELECT COALESCE(NULLIF(ri.cfop, ''), 'Sem CFOP') AS label, NULL AS extra, COUNT(DISTINCT r.id) AS total,
            COALESCE(SUM({$netExpr}),0) AS net_amount,
            COALESCE(SUM(CASE WHEN {$serviceExpr} THEN {$netExpr} ELSE 0 END),0) AS services,
            COALESCE(SUM(CASE WHEN NOT {$serviceExpr} THEN {$netExpr} ELSE 0 END),0) AS resale,
            COALESCE(SUM({$costExpr}),0) AS cost_total,
            COALESCE(SUM(CASE WHEN {$serviceExpr} THEN {$costExpr} ELSE 0 END),0) AS cost_services,
            COALESCE(SUM(CASE WHEN NOT {$serviceExpr} THEN {$costExpr} ELSE 0 END),0) AS cost_resale
            FROM revenue_items ri JOIN revenue_documents r ON r.id = ri.revenue_document_id{$whereSql}
            GROUP BY COALESCE(NULLIF(ri.cfop, ''), 'Sem CFOP') ORDER BY net_amount DESC, total DESC LIMIT :limit");
        foreach ($params as $key => $value) { $stmt->bindValue(':' . $key, $value); }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $period = $this->revenueMoneyBreakdown($filters, 'net_amount');
        $diff = [
            'net_amount' => (float)($period['total'] ?? 0) - array_sum(array_map(static fn($row) => (float)($row['net_amount'] ?? 0), $rows)),
            'resale' => (float)($period['resale'] ?? 0) - array_sum(array_map(static fn($row) => (float)($row['resale'] ?? 0), $rows)),
            'services' => (float)($period['services'] ?? 0) - array_sum(array_map(static fn($row) => (float)($row['services'] ?? 0), $rows)),
            'cost_total' => (float)($period['cost_total'] ?? 0) - array_sum(array_map(static fn($row) => (float)($row['cost_total'] ?? 0), $rows)),
            'cost_resale' => (float)($period['cost_resale'] ?? 0) - array_sum(array_map(static fn($row) => (float)($row['cost_resale'] ?? 0), $rows)),
            'cost_services' => (float)($period['cost_services'] ?? 0) - array_sum(array_map(static fn($row) => (float)($row['cost_services'] ?? 0), $rows)),
        ];
        if (abs($diff['net_amount']) >= 0.01 || abs($diff['cost_total']) >= 0.01) {
            $rows[] = [
                'label' => 'Sem CFOP / ajustes',
                'extra' => 'Diferen?a entre total dos documentos e itens por CFOP',
                'total' => 0,
                'net_amount' => $diff['net_amount'],
                'services' => $diff['services'],
                'resale' => $diff['resale'],
                'cost_total' => $diff['cost_total'],
                'cost_services' => $diff['cost_services'],
                'cost_resale' => $diff['cost_resale'],
            ];
        }
        return $rows;
    }

    private function revenueDailyEvolution(string $whereSql, array $params): array
    {
        $returnExpr = "(purpose = 'devolucao' OR document_type = 'DEVOLUCAO_NFE' OR return_amount <> 0)";
        $stmt = $this->pdo->prepare("SELECT issue_date,
            COALESCE(SUM(CASE WHEN NOT {$returnExpr} THEN gross_amount ELSE 0 END),0) AS gross_amount,
            COALESCE(SUM(CASE WHEN {$returnExpr} THEN ABS(CASE WHEN return_amount <> 0 THEN return_amount WHEN gross_amount <> 0 THEN gross_amount ELSE net_amount END) ELSE 0 END),0) AS return_amount,
            COALESCE(SUM(CASE WHEN {$returnExpr} THEN -ABS(CASE WHEN return_amount <> 0 THEN return_amount WHEN gross_amount <> 0 THEN gross_amount ELSE net_amount END) ELSE gross_amount END),0) AS net_amount
            FROM revenue_documents r{$whereSql} GROUP BY issue_date ORDER BY issue_date ASC LIMIT 370");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function revenueItemGroup(array $documentWhere, array $params, string $column, int $limit): array
    {
        $whereSql = $documentWhere ? ' WHERE ' . implode(' AND ', $documentWhere) : '';
        $returnExpr = "(r.purpose = 'devolucao' OR r.document_type = 'DEVOLUCAO_NFE' OR r.return_amount <> 0)";
        $stmt = $this->pdo->prepare("SELECT i.{$column} AS label, COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN {$returnExpr} THEN -ABS(i.total_amount) ELSE i.total_amount END),0) AS total_amount,
            COALESCE(SUM(CASE WHEN {$returnExpr} THEN -ABS(COALESCE(i.cost_amount, 0)) ELSE COALESCE(i.cost_amount, 0) END),0) AS cost_amount
            FROM revenue_items i JOIN revenue_documents r ON r.id = i.revenue_document_id{$whereSql}
            GROUP BY i.{$column} ORDER BY total_amount DESC, total DESC LIMIT :limit");
        foreach ($params as $key => $value) { $stmt->bindValue(':' . $key, $value); }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function revenueTaxSummary(string $whereSql, array $params, bool $considerCredits): array
    {
        $returnExpr = "(purpose = 'devolucao' OR document_type = 'DEVOLUCAO_NFE' OR return_amount <> 0)";
        $stmt = $this->pdo->prepare("SELECT
            COALESCE(SUM(CASE WHEN {$returnExpr} THEN -ABS(taxes_amount) ELSE taxes_amount END),0) AS total_taxes,
            COALESCE(SUM(CASE WHEN {$returnExpr} THEN -ABS(tax_credits_amount) ELSE tax_credits_amount END),0) AS total_credits,
            COALESCE(SUM((CASE WHEN {$returnExpr} THEN -ABS(taxes_amount) ELSE taxes_amount END) - (CASE WHEN {$returnExpr} THEN -ABS(tax_credits_amount) ELSE tax_credits_amount END)),0) AS tax_balance
            FROM revenue_documents r{$whereSql}");
        $stmt->execute($params);
        $row = $stmt->fetch() ?: ['total_taxes' => 0, 'total_credits' => 0, 'tax_balance' => 0];
        if (!$considerCredits) {
            $row['total_credits'] = 0;
            $row['tax_balance'] = (float)$row['total_taxes'];
        }
        return $row;
    }

    private function filterStringValues(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        $values = array_map(static fn(mixed $item): string => trim((string)$item), $values);
        return array_values(array_unique(array_filter($values, static fn(string $item): bool => $item !== '')));
    }

    private function filterDigitValues(mixed $value): array
    {
        $values = array_map(static fn(string $item): string => preg_replace('/\D+/', '', $item) ?: '', $this->filterStringValues($value));
        return array_values(array_unique(array_filter($values, static fn(string $item): bool => $item !== '')));
    }

    private function filterIntValues(mixed $value): array
    {
        $values = array_map('intval', $this->filterStringValues($value));
        return array_values(array_unique(array_filter($values, static fn(int $item): bool => $item > 0)));
    }

    public function revenueDocumentsPage(array $filters, int $page = 1, int $perPage = 200): array
    {
        [$where, $params] = $this->revenueWhere($filters);
        $offset = max(0, ($page - 1) * $perPage);
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $order = $this->revenueOrderBy($filters);
        $stmt = $this->pdo->prepare("SELECT * FROM revenue_documents r{$whereSql} {$order} LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) { $stmt->bindValue(':' . $key, $value); }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function revenueDocuments(array $filters): array
    {
        [$where, $params] = $this->revenueWhere($filters);
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $stmt = $this->pdo->prepare("SELECT * FROM revenue_documents r{$whereSql} " . $this->revenueOrderBy($filters));
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function revenueOrderBy(array $filters): string
    {
        $allowed = [
            'issue_date' => 'issue_date',
            'authorization_datetime' => 'authorization_datetime',
            'document_type' => 'document_type',
            'number' => 'number',
            'issuing_store_name' => 'issuing_store_name',
            'order_store_name' => 'order_store_name',
            'customer_name' => 'customer_name',
            'seller_name' => 'seller_name',
            'gross_amount' => 'gross_amount',
            'net_amount' => 'net_amount',
        ];
        $column = $allowed[(string)($filters['sort_by'] ?? 'issue_date')] ?? 'issue_date';
        $direction = strtolower((string)($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        return "ORDER BY {$column} {$direction}, id DESC";
    }

    public function findRevenueDocument(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM revenue_documents WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findRevenueDocumentInContext(int $id, array $filters): ?array
    {
        [$where, $params] = $this->revenueWhere($filters);
        $where[] = 'r.id = :id';
        $params['id'] = $id;
        $stmt = $this->pdo->prepare('SELECT * FROM revenue_documents r WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function revenueItems(array $filters = [], ?int $documentId = null, int $limit = 500): array
    {
        [$where, $params] = $this->revenueWhere($filters);
        if ($documentId) {
            $where[] = 'r.id = :document_id';
            $params['document_id'] = $documentId;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $stmt = $this->pdo->prepare("SELECT i.*, r.issue_date, r.document_type, r.series, r.number, r.order_number, r.customer_name, r.seller_name, r.issuing_store_name, r.order_store_name FROM revenue_items i JOIN revenue_documents r ON r.id = i.revenue_document_id{$whereSql} ORDER BY r.issue_date DESC, i.total_amount DESC LIMIT :limit");
        foreach ($params as $key => $value) { $stmt->bindValue(':' . $key, $value); }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function importCompaniesFromCsv(string $csvContent): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($csvContent));
        if (!$lines) {
            return ['created' => 0, 'updated' => 0, 'errors' => 0, 'messages' => []];
        }

        $header = null;
        $created = 0; $updated = 0; $errors = 0; $messages = [];
        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }
            $row = str_getcsv($line, ';');
            if ($index === 0) {
                $normalized = array_map(fn($v) => strtolower(trim((string)$v)), $row);
                if (in_array('cnpj', $normalized, true)) {
                    $header = $normalized;
                    continue;
                }
            }

            if ($header) {
                $assoc = [];
                foreach ($header as $i => $col) {
                    $assoc[$col] = trim((string)($row[$i] ?? ''));
                }
            } else {
                $assoc = [
                    'company_name' => trim((string)($row[0] ?? '')),
                    'cnpj' => trim((string)($row[1] ?? '')),
                    'default_download_dir' => trim((string)($row[2] ?? '')),
                    'is_active' => trim((string)($row[3] ?? '1')),
                ];
            }

            try {
                $existing = $this->findCompanyByCnpj((string)($assoc['cnpj'] ?? ''));
                $id = $this->saveCompany([
                    'id' => (int)($existing['id'] ?? 0),
                    'company_name' => $assoc['company_name'] ?? ($assoc['razao_social'] ?? ''),
                    'cnpj' => $assoc['cnpj'] ?? '',
                    'default_download_dir' => $assoc['default_download_dir'] ?? '',
                    'is_active' => !in_array(strtolower((string)($assoc['is_active'] ?? '1')), ['0', 'false', 'nao', 'não', 'n'], true),
                ]);
                if ($existing) {
                    $updated++;
                } else {
                    $created++;
                }
                $messages[] = 'Linha ' . ($index + 1) . ': empresa salva ID ' . $id;
            } catch (\Throwable $e) {
                $errors++;
                $messages[] = 'Linha ' . ($index + 1) . ': ' . $e->getMessage();
            }
        }

        return compact('created', 'updated', 'errors', 'messages');
    }

    public function countDocumentsByCompany(int $companyId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM documents WHERE company_id = :company_id");
        $stmt->execute(['company_id' => $companyId]);
        return (int)$stmt->fetchColumn();
    }


    public function enqueuePendingNfeCancellationChecks(int $limit = 500, ?int $companyId = null): int
    {
        $companySql = '';
        if ($companyId !== null && $companyId > 0) { $companySql = ' AND d.company_id = :company_id'; }
        $sql = "SELECT d.id, d.company_id, d.access_key
                FROM documents d
                WHERE d.doc_type = 'NFE'
                  AND COALESCE(d.status, '') NOT IN ('cancelado', 'cancelled', 'evento_informativo')
                  AND COALESCE(d.posted_to_erp, FALSE) = FALSE
                  AND COALESCE(d.access_key, '') <> ''
                  AND (
                    d.issue_date >= CURRENT_DATE - INTERVAL '7 days'
                    OR NOT EXISTS (
                        SELECT 1 FROM nfe_cancellation_queue q0
                        WHERE q0.document_id = d.id AND q0.status = 'completed'
                    )
                  )
                  AND NOT EXISTS (
                    SELECT 1 FROM nfe_cancellation_queue q
                    WHERE q.document_id = d.id
                      AND (
                        q.status IN ('pending', 'retry', 'running')
                        OR (q.status = 'completed' AND q.completed_at >= CURRENT_TIMESTAMP - INTERVAL '7 days')
                      )
                  )" . $companySql . "
                ORDER BY d.issue_date ASC NULLS FIRST, d.id ASC
                LIMIT :limit";
        $q = $this->pdo->prepare($sql);
        if ($companyId !== null && $companyId > 0) { $q->bindValue(':company_id', $companyId, PDO::PARAM_INT); }
        $q->bindValue(':limit', $limit, PDO::PARAM_INT);
        $q->execute();
        $count = 0;
        foreach ($q->fetchAll() as $row) {
            $this->enqueueNfeCancellationCheck((int)$row['id'], $row['company_id'] !== null ? (int)$row['company_id'] : null, (string)$row['access_key']);
            $count++;
        }
        return $count;
    }

}

