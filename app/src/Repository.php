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
        return $this->pdo->query('SELECT id, name, email, role, can_view_cost, is_active, created_at, updated_at FROM users ORDER BY name ASC')->fetchAll();
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
        $canViewCost = $role === 'admin' || !empty($data['can_view_cost']);
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
            $fields = 'name = :name, email = :email, role = :role, can_view_cost = :can_view_cost, is_active = :is_active, updated_at = :updated_at';
            $params = [
                'id' => $id,
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'can_view_cost' => $canViewCost,
                'is_active' => $active,
                'updated_at' => date('c'),
            ];
            if ($password !== '') {
                $fields .= ', password_hash = :password_hash';
                $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }
            $stmt = $this->pdo->prepare("UPDATE users SET {$fields} WHERE id = :id");
            $stmt->execute($params);
            return $id;
        }

        if ($password === '') {
            throw new \RuntimeException('Informe uma senha para criar o usuario.');
        }
        $stmt = $this->pdo->prepare('INSERT INTO users(name, email, password_hash, role, can_view_cost, is_active, created_at, updated_at)
            VALUES(:name, :email, :password_hash, :role, :can_view_cost, :is_active, :created_at, :updated_at)');
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'can_view_cost' => $canViewCost,
            'is_active' => $active,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ]);
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
            'controle de distribuiÃƒÂ§ÃƒÂ£o' => 'SELECT COUNT(*) FROM distribution_controls WHERE company_id = :company_id',
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
                $blockers['fechamentos por perÃƒÂ­odo'] = ($blockers['fechamentos por perÃƒÂ­odo'] ?? 0) + 1;
            }
        }

        return $blockers;
    }

    public function deleteCompanyIfUnlinked(int $companyId): void
    {
        $company = $this->findCompany($companyId);
        if (!$company) {
            throw new \RuntimeException('Empresa nÃƒÂ£o encontrada.');
        }

        $blockers = $this->companyDeleteBlockers($companyId);
        if ($blockers) {
            $messages = [];
            foreach ($blockers as $label => $count) {
                $messages[] = $label . ': ' . $count;
            }
            throw new \RuntimeException('Empresa nÃƒÂ£o pode ser excluÃƒÂ­da porque possui vÃƒÂ­nculo no banco: ' . implode(', ', $messages) . '.');
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

        $row = $this->normalizeDocumentRow($data);
        if ($existing) {
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
            ?: $this->findDocumentByAccessKey('NFCE', $accessKey, !empty($data['company_id']) ? (int)$data['company_id'] : null);

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
        $row['posted_to_erp'] = $this->normalizeBoolean($row['posted_to_erp'] ?? false);
        return $row;
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

    public function documents(array $filters = []): array
    {
        $this->ensureReferencedDocumentNumbers();
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
        return $this->attachEventSummaries($stmt->fetchAll());
    }

    public function documentsPage(array $filters = [], int $page = 1, int $perPage = 200): array
    {
        $this->ensureReferencedDocumentNumbers();
        $this->ensureDocumentItemsForFilters($filters);
        [$where, $params] = $this->documentWhere($filters);
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT * FROM documents';
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
        return $this->attachEventSummaries($stmt->fetchAll());
    }

    public function documentsTotals(array $filters = []): array
    {
        $this->ensureReferencedDocumentNumbers();
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

    private function documentWhere(array $filters): array
    {
        $where = ["status <> 'evento_informativo'"];
        $params = [];
        if (!empty($filters['entry_only'])) {
            $where[] = "doc_type IN ('NFE', 'CTE')";
        }
        if (!empty($filters['company_id'])) { $where[] = 'company_id = :company_id'; $params['company_id'] = (int)$filters['company_id']; }
        if (!empty($filters['doc_type'])) { $where[] = 'doc_type = :doc_type'; $params['doc_type'] = $filters['doc_type']; }
        if (!empty($filters['status'])) { $where[] = 'status = :status'; $params['status'] = $filters['status']; }
        if (!empty($filters['manifestation_status'])) { $where[] = 'manifestation_status = :manifestation_status'; $params['manifestation_status'] = $filters['manifestation_status']; }
        if ((string)($filters['posted_to_erp'] ?? '') === '1') { $where[] = 'COALESCE(posted_to_erp, FALSE) = TRUE'; }
        if ((string)($filters['posted_to_erp'] ?? '') === '0') { $where[] = 'COALESCE(posted_to_erp, FALSE) = FALSE'; }
        if (!empty($filters['without_referenced_nfe'])) { $where[] = "(doc_type = 'CTE' AND COALESCE(referenced_nfe_keys, '') = '')"; }
        if (!empty($filters['cte_taker_only'])) {
            $companyDigits = $this->digitsOnlySql('documents.company_cnpj');
            $where[] = "(doc_type <> 'CTE' OR EXISTS (SELECT 1 FROM document_cte_takers dct WHERE dct.document_id = documents.id AND COALESCE(dct.taker_cnpj, '') <> '' AND dct.taker_cnpj = {$companyDigits}))";
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
        }
        return [$where, $params];
    }

    private function ensureDocumentItemsForFilters(array $filters): void
    {
        if (trim((string)($filters['product_q'] ?? '')) === '' && trim((string)($filters['cfop_q'] ?? '')) === '' && empty($filters['cte_taker_only']) && (string)($filters['ignore_cfops'] ?? '1') === '0') {
            return;
        }
        $this->indexMissingDocumentItems(10000);
    }

    private function ensureReferencedDocumentNumbers(int $limit = 10000): void
    {
        $stmt = $this->pdo->prepare("SELECT id, doc_type, access_key, referenced_nfe_keys, raw_xml, xml_path FROM documents
            WHERE doc_type IN ('NFE', 'NFCE', 'CTE')
              AND (
                  referenced_document_numbers IS NULL
                  OR referenced_nfe_keys IS NULL
                  OR COALESCE(referenced_nfe_keys, '') = COALESCE(access_key, '')
              )
              AND (COALESCE(raw_xml, '') <> '' OR COALESCE(xml_path, '') <> '')
            ORDER BY id DESC
            LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $update = $this->pdo->prepare('UPDATE documents SET referenced_nfe_keys = COALESCE(referenced_nfe_keys, :keys), referenced_document_numbers = COALESCE(referenced_document_numbers, :numbers), updated_at = :updated_at WHERE id = :id');
        foreach ($stmt->fetchAll() as $doc) {
            $xml = (string)($doc['raw_xml'] ?? '');
            $path = (string)($doc['xml_path'] ?? '');
            if (trim($xml) === '' && $path !== '' && is_file($path)) {
                $xml = (string)file_get_contents($path);
            }
            $type = strtoupper((string)($doc['doc_type'] ?? ''));
            $keys = $this->parseReferencedNFeKeysFromXml($xml, $type);
            $numbers = $this->parseReferencedDocumentNumbersFromXml($xml, $type);
            $currentKey = preg_replace('/\D+/', '', (string)($doc['referenced_nfe_keys'] ?? '')) ?: '';
            $ownKey = preg_replace('/\D+/', '', (string)($doc['access_key'] ?? '')) ?: '';
            $shouldReplaceKeys = $currentKey === '' || ($ownKey !== '' && $currentKey === $ownKey);
            $update->execute(['keys' => $shouldReplaceKeys ? $keys : (string)($doc['referenced_nfe_keys'] ?? ''), 'numbers' => $numbers, 'updated_at' => date('c'), 'id' => (int)$doc['id']]);
        }
    }

    public function documentIgnoredCfops(): array
    {
        return $this->pdo->query('SELECT * FROM document_ignored_cfops ORDER BY cfop ASC')->fetchAll();
    }

    public function documentCfopOptions(): array
    {
        $this->indexMissingDocumentItems(10000);
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

    private function indexMissingDocumentItems(int $limit = 10000): void
    {
        $stmt = $this->pdo->prepare("SELECT id, doc_type, raw_xml, xml_path FROM documents d
            WHERE d.status <> 'evento_informativo'
              AND d.doc_type IN ('NFE', 'CTE')
              AND (
                  NOT EXISTS (SELECT 1 FROM document_item_index dix WHERE dix.document_id = d.id)
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
                $mark = $this->pdo->prepare('INSERT INTO document_item_index(document_id, indexed_at) VALUES(:document_id, :indexed_at)');
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

    private function parseReferencedDocumentNumbersFromXml(string $xml, string $type): string
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
        $keyExpr = $type === 'CTE'
            ? '//*[local-name()="infNFe"]/*[local-name()="chave" or local-name()="chNFe"]'
            : '//*[local-name()="NFref"]/*[local-name()="refNFe"]';
        foreach ($xp->query($keyExpr) ?: [] as $node) {
            $number = $this->numberFromAccessKey((string)$node->textContent);
            if ($number !== '') {
                $numbers[$number] = true;
            }
        }
        foreach ($xp->query('//*[local-name()="NFref"]//*[local-name()="nNF"]') ?: [] as $node) {
            $number = ltrim(preg_replace('/\D+/', '', trim((string)$node->textContent)) ?: '', '0');
            if ($number !== '') {
                $numbers[$number] = true;
            }
        }
        return $numbers ? implode(', ', array_keys($numbers)) : '';
    }

    private function parseReferencedNFeKeysFromXml(string $xml, string $type): string
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
        $expr = $type === 'CTE'
            ? '//*[local-name()="infNFe"]/*[local-name()="chave" or local-name()="chNFe"]'
            : '//*[local-name()="NFref"]/*[local-name()="refNFe"]';
        foreach ($xp->query($expr) ?: [] as $node) {
            $key = preg_replace('/\D+/', '', trim((string)$node->textContent));
            if (strlen($key) === 44) {
                $keys[$key] = true;
            }
        }
        return $keys ? implode(', ', array_keys($keys)) : '';
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
            "d.doc_type IN ('NFE', 'NFCE', 'CTE')",
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
        $accessKey = preg_replace('/\D+/', '', (string)$value('chNFe'));
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
     * Monta a clÃƒÂ¡usula de filtros da conferÃƒÂªncia de faturamento.
     * A rotina usa dados integrados do ERP, por isso os filtros sÃƒÂ£o sempre
     * aplicados sobre as tabelas prÃƒÂ³prias de faturamento, nÃƒÂ£o sobre XMLs.
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
            $digits = preg_replace('/\D+/', '', (string)($filters[$filterKey] ?? ''));
            if ($digits !== '') {
                $where[] = $this->digitsOnlySql("{$prefix}{$column}") . " = :{$filterKey}";
                $params[$filterKey] = $digits;
            }
        }
        foreach ([
            'issuing_store_name' => 'issuing_store_name',
            'order_store_name' => 'order_store_name',
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
                'extra' => 'Diferença entre total dos documentos e itens por CFOP',
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
                    'is_active' => !in_array(strtolower((string)($assoc['is_active'] ?? '1')), ['0', 'false', 'nao', 'nÃƒÂ£o', 'n'], true),
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


}

