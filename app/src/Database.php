<?php
declare(strict_types=1);

namespace ControlS\Portal;

use PDO;

final class Database
{
    private array $config;
    private ?PDO $pdo = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        $dsn = $this->config['db_dsn'];
        $user = $this->config['db_user'];
        $pass = $this->config['db_pass'];

        $this->pdo = str_starts_with($dsn, 'sqlite:')
            ? new PDO($dsn, null, null, $options)
            : new PDO($dsn, $user, $pass, $options);

        return $this->pdo;
    }

    public function ensureSchema(): void
    {
        $pdo = $this->pdo();
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite' ? $this->sqliteSchema() : $this->pgsqlSchema();

        foreach ($sql as $statement) {
            $pdo->exec($statement);
        }
        $this->ensurePortableColumns($driver);
    }

    private function ensurePortableColumns(string $driver): void
    {
        if ($driver !== 'sqlite') {
            return;
        }

        $this->ensureSqliteColumn('users', 'can_view_cost', 'INTEGER DEFAULT 0');
        $this->ensureSqliteColumn('documents', 'referenced_document_numbers', 'TEXT NULL');
        $this->ensureSqliteColumn('revenue_items', 'cost_amount', 'REAL DEFAULT 0');
    }

    private function ensureSqliteColumn(string $table, string $column, string $definition): void
    {
        $stmt = $this->pdo()->query("PRAGMA table_info({$table})");
        foreach ($stmt->fetchAll() as $row) {
            if ((string)($row['name'] ?? '') === $column) {
                return;
            }
        }
        $this->pdo()->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }

    private function sqliteSchema(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT, updated_at TEXT)",
            "CREATE TABLE IF NOT EXISTS companies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_name TEXT NOT NULL,
                cnpj TEXT NOT NULL UNIQUE,
                default_download_dir TEXT NULL,
                is_active INTEGER DEFAULT 1,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'user',
                can_view_cost INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS certificates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL,
                filename TEXT NOT NULL,
                storage_path TEXT NOT NULL,
                password_enc TEXT NOT NULL,
                subject_name TEXT,
                thumbprint TEXT,
                valid_from TEXT,
                valid_to TEXT,
                serial_number TEXT,
                is_active INTEGER DEFAULT 1,
                created_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NULL,
                company_name TEXT NULL,
                company_cnpj TEXT NULL,
                doc_type TEXT NOT NULL,
                model TEXT,
                access_key TEXT,
                referenced_nfe_keys TEXT NULL,
                referenced_document_numbers TEXT NULL,
                number TEXT,
                order_number TEXT NULL,
                issuer_cnpj TEXT,
                issuer_name TEXT,
                recipient_cnpj TEXT,
                recipient_name TEXT,
                issue_date TEXT,
                total_value REAL DEFAULT 0,
                status TEXT DEFAULT 'imported',
                manifestation_status TEXT DEFAULT 'not_applicable',
                source TEXT DEFAULT 'manual_import',
                xml_path TEXT,
                storage_dir TEXT,
                notes TEXT,
                raw_xml TEXT,
                digest TEXT,
                schema_name TEXT,
                imported_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )",
            "CREATE UNIQUE INDEX IF NOT EXISTS idx_documents_digest ON documents(digest)",
            "CREATE INDEX IF NOT EXISTS idx_documents_type ON documents(doc_type)",
            "CREATE INDEX IF NOT EXISTS idx_documents_status ON documents(status)",
            "CREATE INDEX IF NOT EXISTS idx_documents_company ON documents(company_id)",
            "CREATE TABLE IF NOT EXISTS document_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                document_id INTEGER NOT NULL,
                item_number INTEGER DEFAULT 0,
                product_code TEXT NULL,
                product_name TEXT NULL,
                ncm TEXT NULL,
                cfop TEXT NULL,
                quantity REAL DEFAULT 0,
                unit TEXT NULL,
                unit_amount REAL DEFAULT 0,
                total_amount REAL DEFAULT 0,
                created_at TEXT NOT NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_document_items_document ON document_items(document_id)",
            "CREATE INDEX IF NOT EXISTS idx_document_items_product ON document_items(product_name)",
            "CREATE INDEX IF NOT EXISTS idx_document_items_cfop ON document_items(cfop)",
            "CREATE TABLE IF NOT EXISTS document_item_index (
                document_id INTEGER PRIMARY KEY,
                indexed_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS document_cte_takers (
                document_id INTEGER PRIMARY KEY,
                taker_cnpj TEXT NULL,
                created_at TEXT NOT NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_document_cte_takers_cnpj ON document_cte_takers(taker_cnpj)",
            "CREATE TABLE IF NOT EXISTS document_ignored_cfops (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cfop TEXT NOT NULL UNIQUE,
                reason TEXT NULL,
                user_id INTEGER NULL,
                user_name TEXT NULL,
                created_at TEXT NOT NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_document_ignored_cfops_cfop ON document_ignored_cfops(cfop)",
            "CREATE TABLE IF NOT EXISTS document_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NULL,
                document_id INTEGER NULL,
                access_key TEXT NOT NULL,
                event_type TEXT NULL,
                event_name TEXT NULL,
                event_date TEXT NULL,
                protocol TEXT NULL,
                issuer_cnpj TEXT NULL,
                schema_name TEXT NULL,
                raw_xml TEXT NULL,
                digest TEXT NULL UNIQUE,
                created_at TEXT NOT NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_document_events_access_key ON document_events(access_key)",
            "CREATE INDEX IF NOT EXISTS idx_document_events_document ON document_events(document_id)",
            "CREATE TABLE IF NOT EXISTS revenue_documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                integration_id TEXT NULL UNIQUE,
                issue_date TEXT NOT NULL,
                authorization_datetime TEXT NULL,
                document_type TEXT NOT NULL,
                purpose TEXT NOT NULL,
                document_status TEXT NOT NULL,
                series TEXT NULL,
                number TEXT NULL,
                order_number TEXT NULL,
                access_key TEXT NULL,
                issuing_store_name TEXT NOT NULL,
                issuing_store_cnpj TEXT NOT NULL,
                order_store_name TEXT NULL,
                order_store_cnpj TEXT NULL,
                customer_name TEXT NULL,
                customer_document TEXT NULL,
                seller_name TEXT NULL,
                seller_code TEXT NULL,
                gross_amount REAL DEFAULT 0,
                products_amount REAL DEFAULT 0,
                services_amount REAL DEFAULT 0,
                freight_amount REAL DEFAULT 0,
                discount_amount REAL DEFAULT 0,
                return_amount REAL DEFAULT 0,
                taxes_amount REAL DEFAULT 0,
                tax_credits_amount REAL DEFAULT 0,
                icms_amount REAL DEFAULT 0,
                pis_amount REAL DEFAULT 0,
                cofins_amount REAL DEFAULT 0,
                ipi_amount REAL DEFAULT 0,
                iss_amount REAL DEFAULT 0,
                st_amount REAL DEFAULT 0,
                ibs_amount REAL DEFAULT 0,
                cbs_amount REAL DEFAULT 0,
                difal_amount REAL DEFAULT 0,
                other_taxes_amount REAL DEFAULT 0,
                net_amount REAL DEFAULT 0,
                integration_source TEXT NULL,
                integration_payload TEXT NULL,
                xml_content TEXT NULL,
                xml_integrated_at TEXT NULL,
                notes TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS revenue_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                revenue_document_id INTEGER NOT NULL,
                product_name TEXT NOT NULL,
                internal_code TEXT NULL,
                erp_code TEXT NULL,
                gtin TEXT NULL,
                product_group TEXT NULL,
                quantity REAL DEFAULT 0,
                unit TEXT NULL,
                unit_amount REAL DEFAULT 0,
                cost_amount REAL DEFAULT 0,
                total_amount REAL DEFAULT 0,
                discount_amount REAL DEFAULT 0,
                cfop TEXT NULL,
                ncm TEXT NULL,
                cst_csosn TEXT NULL,
                taxes_amount REAL DEFAULT 0,
                tax_credits_amount REAL DEFAULT 0,
                icms_amount REAL DEFAULT 0,
                pis_amount REAL DEFAULT 0,
                cofins_amount REAL DEFAULT 0,
                ipi_amount REAL DEFAULT 0,
                iss_amount REAL DEFAULT 0,
                st_amount REAL DEFAULT 0,
                ibs_amount REAL DEFAULT 0,
                cbs_amount REAL DEFAULT 0,
                difal_amount REAL DEFAULT 0,
                other_taxes_amount REAL DEFAULT 0,
                taxes_json TEXT NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_revenue_documents_issue ON revenue_documents(issue_date)",
            "CREATE INDEX IF NOT EXISTS idx_revenue_documents_access_key ON revenue_documents(access_key)",
            "CREATE INDEX IF NOT EXISTS idx_revenue_documents_type ON revenue_documents(document_type)",
            "CREATE INDEX IF NOT EXISTS idx_revenue_documents_emission_store ON revenue_documents(issuing_store_cnpj)",
            "CREATE INDEX IF NOT EXISTS idx_revenue_documents_order_store ON revenue_documents(order_store_cnpj)",
            "CREATE INDEX IF NOT EXISTS idx_revenue_documents_customer ON revenue_documents(customer_document)",
            "CREATE INDEX IF NOT EXISTS idx_revenue_items_document ON revenue_items(revenue_document_id)",
            "CREATE INDEX IF NOT EXISTS idx_revenue_items_product ON revenue_items(product_name)",
            "CREATE TABLE IF NOT EXISTS jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NULL,
                company_name TEXT NULL,
                job_type TEXT NOT NULL,
                status TEXT NOT NULL,
                started_at TEXT NOT NULL,
                finished_at TEXT,
                created_count INTEGER DEFAULT 0,
                updated_count INTEGER DEFAULT 0,
                error_count INTEGER DEFAULT 0,
                log_text TEXT
            )",
            "CREATE TABLE IF NOT EXISTS actions_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NULL,
                action_type TEXT NOT NULL,
                details TEXT,
                created_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS distribution_controls (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL,
                doc_type TEXT NOT NULL,
                environment TEXT NOT NULL,
                last_distribution_check_at TEXT NULL,
                last_distribution_result TEXT NULL,
                last_ult_nsu TEXT NULL,
                last_max_nsu TEXT NULL,
                cooldown_until TEXT NULL,
                locked_by_job_id INTEGER NULL,
                locked_at TEXT NULL,
                source_context TEXT NULL,
                manual_override_reason TEXT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE(company_id, doc_type, environment)
            )",
            "CREATE TABLE IF NOT EXISTS period_closures (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                status TEXT NOT NULL,
                period_start TEXT NOT NULL,
                period_end TEXT NOT NULL,
                company_ids TEXT NOT NULL,
                doc_types TEXT NOT NULL,
                only_missing_complete INTEGER DEFAULT 1,
                try_manifestation INTEGER DEFAULT 0,
                reprocess_after_manifestation INTEGER DEFAULT 0,
                generate_export INTEGER DEFAULT 0,
                save_period_folder INTEGER DEFAULT 0,
                export_zip_path TEXT NULL,
                export_csv_path TEXT NULL,
                summary_json TEXT NULL,
                messages TEXT NULL,
                started_at TEXT NOT NULL,
                finished_at TEXT NULL
            )",
            "CREATE TABLE IF NOT EXISTS period_closure_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                closure_id INTEGER NOT NULL,
                document_id INTEGER NULL,
                company_id INTEGER NULL,
                company_name TEXT NULL,
                company_cnpj TEXT NULL,
                doc_type TEXT NOT NULL,
                access_key TEXT NULL,
                issuer_name TEXT NULL,
                issuer_cnpj TEXT NULL,
                issue_date TEXT NULL,
                total_value REAL DEFAULT 0,
                status TEXT NOT NULL,
                xml_saved INTEGER DEFAULT 0,
                xml_path TEXT NULL,
                storage_dir TEXT NULL,
                notes TEXT NULL,
                created_at TEXT NOT NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_period_items_closure ON period_closure_items(closure_id)",
            "CREATE INDEX IF NOT EXISTS idx_period_items_status ON period_closure_items(status)",
            "UPDATE documents SET status = 'xml_completo' WHERE status IN ('downloaded', 'downloaded_in_portal', 'imported')",
            "UPDATE documents SET status = 'apenas_resumo' WHERE status = 'summary_only'",
            "UPDATE documents SET status = 'aguardando_novo_download' WHERE status = 'awaiting_redownload'",
            ];
    }

    private function pgsqlSchema(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS settings (key VARCHAR(120) PRIMARY KEY, value TEXT, updated_at TIMESTAMP NULL)",
            "CREATE TABLE IF NOT EXISTS companies (
                id SERIAL PRIMARY KEY,
                company_name TEXT NOT NULL,
                cnpj VARCHAR(20) NOT NULL UNIQUE,
                default_download_dir TEXT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP NOT NULL DEFAULT NOW()
            )",
            "CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                name TEXT NOT NULL,
                email VARCHAR(180) NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'user',
                can_view_cost BOOLEAN DEFAULT FALSE,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP NOT NULL DEFAULT NOW()
            )",
            "CREATE TABLE IF NOT EXISTS certificates (
                id SERIAL PRIMARY KEY,
                company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
                filename VARCHAR(255) NOT NULL,
                storage_path TEXT NOT NULL,
                password_enc TEXT NOT NULL,
                subject_name TEXT NULL,
                thumbprint VARCHAR(128) NULL,
                valid_from TIMESTAMP NULL,
                valid_to TIMESTAMP NULL,
                serial_number VARCHAR(255) NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP NOT NULL DEFAULT NOW()
            )",
            "CREATE TABLE IF NOT EXISTS documents (
                id SERIAL PRIMARY KEY,
                company_id INTEGER NULL REFERENCES companies(id) ON DELETE SET NULL,
                company_name TEXT NULL,
                company_cnpj VARCHAR(20) NULL,
                doc_type VARCHAR(30) NOT NULL,
                model VARCHAR(20) NULL,
                access_key VARCHAR(60) NULL,
                referenced_nfe_keys TEXT NULL,
                referenced_document_numbers TEXT NULL,
                number VARCHAR(60) NULL,
                order_number VARCHAR(80) NULL,
                issuer_cnpj VARCHAR(20) NULL,
                issuer_name TEXT NULL,
                recipient_cnpj VARCHAR(20) NULL,
                recipient_name TEXT NULL,
                issue_date TIMESTAMP NULL,
                total_value NUMERIC(15,2) DEFAULT 0,
                status VARCHAR(40) DEFAULT 'imported',
                manifestation_status VARCHAR(40) DEFAULT 'not_applicable',
                source VARCHAR(40) DEFAULT 'manual_import',
                xml_path TEXT NULL,
                storage_dir TEXT NULL,
                notes TEXT NULL,
                raw_xml TEXT NULL,
                digest VARCHAR(128) NULL UNIQUE,
                schema_name VARCHAR(80) NULL,
                imported_at TIMESTAMP NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP NOT NULL DEFAULT NOW()
            )",
            "CREATE INDEX IF NOT EXISTS idx_documents_company ON documents(company_id)",
            "CREATE INDEX IF NOT EXISTS idx_documents_type ON documents(doc_type)",
            "CREATE INDEX IF NOT EXISTS idx_documents_status ON documents(status)",
            "CREATE INDEX IF NOT EXISTS idx_documents_access_key ON documents(access_key)",
            "ALTER TABLE documents ADD COLUMN IF NOT EXISTS referenced_nfe_keys TEXT NULL",
            "ALTER TABLE documents ADD COLUMN IF NOT EXISTS referenced_document_numbers TEXT NULL",
            "ALTER TABLE documents ADD COLUMN IF NOT EXISTS order_number VARCHAR(80) NULL",
            "ALTER TABLE documents ADD COLUMN IF NOT EXISTS posted_to_erp BOOLEAN DEFAULT FALSE",
            "CREATE INDEX IF NOT EXISTS idx_documents_order_number ON documents(order_number)",
            "CREATE INDEX IF NOT EXISTS idx_documents_posted_to_erp ON documents(posted_to_erp)",
            "CREATE TABLE IF NOT EXISTS document_items (
                id SERIAL PRIMARY KEY,
                document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
                item_number INTEGER DEFAULT 0,
                product_code TEXT NULL,
                product_name TEXT NULL,
                ncm VARCHAR(20) NULL,
                cfop VARCHAR(10) NULL,
                quantity NUMERIC(15,4) DEFAULT 0,
                unit VARCHAR(20) NULL,
                unit_amount NUMERIC(15,4) DEFAULT 0,
                total_amount NUMERIC(15,2) DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT NOW()
            )",
            "CREATE INDEX IF NOT EXISTS idx_document_items_document ON document_items(document_id)",
            "CREATE INDEX IF NOT EXISTS idx_document_items_product ON document_items(product_name)",
            "CREATE INDEX IF NOT EXISTS idx_document_items_cfop ON document_items(cfop)",
            "CREATE TABLE IF NOT EXISTS document_item_index (
                document_id INTEGER PRIMARY KEY REFERENCES documents(id) ON DELETE CASCADE,
                indexed_at TIMESTAMP NOT NULL DEFAULT NOW()
            )",
            "CREATE TABLE IF NOT EXISTS document_cte_takers (
                document_id INTEGER PRIMARY KEY REFERENCES documents(id) ON DELETE CASCADE,
                taker_cnpj VARCHAR(20) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT NOW()
            )",
            "CREATE INDEX IF NOT EXISTS idx_document_cte_takers_cnpj ON document_cte_takers(taker_cnpj)",
            "CREATE TABLE IF NOT EXISTS document_ignored_cfops (
                id SERIAL PRIMARY KEY,
                cfop VARCHAR(10) NOT NULL UNIQUE,
                reason TEXT NULL,
                user_id INTEGER NULL,
                user_name TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT NOW()
            )",
            "CREATE INDEX IF NOT EXISTS idx_document_ignored_cfops_cfop ON document_ignored_cfops(cfop)",
            "CREATE TABLE IF NOT EXISTS document_events (
                id SERIAL PRIMARY KEY,
                company_id INTEGER NULL REFERENCES companies(id) ON DELETE SET NULL,
                document_id INTEGER NULL REFERENCES documents(id) ON DELETE SET NULL,
                access_key VARCHAR(60) NOT NULL,
                event_type VARCHAR(30) NULL,
                event_name TEXT NULL,
                event_date TIMESTAMP NULL,
                protocol VARCHAR(80) NULL,
                issuer_cnpj VARCHAR(20) NULL,
                schema_name VARCHAR(80) NULL,
                raw_xml TEXT NULL,
                digest VARCHAR(128) NULL UNIQUE,
                created_at TIMESTAMP NOT NULL DEFAULT NOW()
            )",
            "CREATE INDEX IF NOT EXISTS idx_document_events_access_key ON document_events(access_key)",
            "CREATE INDEX IF NOT EXISTS idx_document_events_document ON document_events(document_id)",
            "CREATE TABLE IF NOT EXISTS revenue_documents (
                id SERIAL PRIMARY KEY,
                integration_id VARCHAR(120) NULL UNIQUE,
                issue_date DATE NOT NULL,
                authorization_datetime TIMESTAMP NULL,
                document_type VARCHAR(30) NOT NULL,
                purpose VARCHAR(40) NOT NULL,
                document_status VARCHAR(40) NOT NULL,
                series VARCHAR(20) NULL,
                number VARCHAR(40) NULL,
                order_number VARCHAR(80) NULL,
                access_key VARCHAR(60) NULL,
                issuing_store_name TEXT NOT NULL,
                issuing_store_cnpj VARCHAR(20) NOT NULL,
                order_store_name TEXT NULL,
                order_store_cnpj VARCHAR(20) NULL,
                customer_name TEXT NULL,
                customer_document VARCHAR(20) NULL,
                seller_name TEXT NULL,
                seller_code VARCHAR(80) NULL,
                gross_amount NUMERIC(15,2) DEFAULT 0,
                products_amount NUMERIC(15,2) DEFAULT 0,
                services_amount NUMERIC(15,2) DEFAULT 0,
                freight_amount NUMERIC(15,2) DEFAULT 0,
                discount_amount NUMERIC(15,2) DEFAULT 0,
                return_amount NUMERIC(15,2) DEFAULT 0,
                taxes_amount NUMERIC(15,2) DEFAULT 0,
                tax_credits_amount NUMERIC(15,2) DEFAULT 0,
                icms_amount NUMERIC(15,2) DEFAULT 0,
                pis_amount NUMERIC(15,2) DEFAULT 0,
                cofins_amount NUMERIC(15,2) DEFAULT 0,
                ipi_amount NUMERIC(15,2) DEFAULT 0,
                iss_amount NUMERIC(15,2) DEFAULT 0,
                st_amount NUMERIC(15,2) DEFAULT 0,
                ibs_amount NUMERIC(15,2) DEFAULT 0,
                cbs_amount NUMERIC(15,2) DEFAULT 0,
                difal_amount NUMERIC(15,2) DEFAULT 0,
                other_taxes_amount NUMERIC(15,2) DEFAULT 0,
                net_amount NUMERIC(15,2) DEFAULT 0,
                integration_source TEXT NULL,
                integration_payload JSONB NULL,
                xml_content TEXT NULL,
                xml_integrated_at TIMESTAMP NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP NOT NULL DEFAULT NOW()
            )",
            "CREATE TABLE IF NOT EXISTS revenue_items (
                id SERIAL PRIMARY KEY,
                revenue_document_id INTEGER NOT NULL REFERENCES revenue_documents(id) ON DELETE CASCADE,
                product_name TEXT NOT NULL,
                internal_code VARCHAR(80) NULL,
                erp_code VARCHAR(80) NULL,
                gtin VARCHAR(40) NULL,
                product_group TEXT NULL,
                quantity NUMERIC(15,4) DEFAULT 0,
                unit VARCHAR(20) NULL,
                unit_amount NUMERIC(15,4) DEFAULT 0,
                cost_amount NUMERIC(15,2) DEFAULT 0,
                total_amount NUMERIC(15,2) DEFAULT 0,
                discount_amount NUMERIC(15,2) DEFAULT 0,
                cfop VARCHAR(10) NULL,
                ncm VARCHAR(20) NULL,
                cst_csosn VARCHAR(20) NULL,
                taxes_amount NUMERIC(15,2) DEFAULT 0,
                tax_credits_amount NUMERIC(15,2) DEFAULT 0,
                icms_amount NUMERIC(15,2) DEFAULT 0,
                pis_amount NUMERIC(15,2) DEFAULT 0,
                cofins_amount NUMERIC(15,2) DEFAULT 0,
                ipi_amount NUMERIC(15,2) DEFAULT 0,
                iss_amount NUMERIC(15,2) DEFAULT 0,
                st_amount NUMERIC(15,2) DEFAULT 0,
                ibs_amount NUMERIC(15,2) DEFAULT 0,
                cbs_amount NUMERIC(15,2) DEFAULT 0,
                difal_amount NUMERIC(15,2) DEFAULT 0,
                other_taxes_amount NUMERIC(15,2) DEFAULT 0,
                taxes_json JSONB NULL
            )",
            "ALTER TABLE revenue_documents ADD COLUMN IF NOT EXISTS order_number VARCHAR(80) NULL",
            "ALTER TABLE revenue_documents ADD COLUMN IF NOT EXISTS icms_amount NUMERIC(15,2) DEFAULT 0",
            "ALTER TABLE revenue_documents ADD COLUMN IF NOT EXISTS pis_amount NUMERIC(15,2) DEFAULT 0",
            "ALTER TABLE revenue_documents ADD COLUMN IF NOT EXISTS cofins_amount NUMERIC(15,2) DEFAULT 0",
            "ALTER TABLE revenue_documents ADD COLUMN IF NOT EXISTS ipi_amount NUMERIC(15,2) DEFAULT 0",
            "ALTER TABLE revenue_documents ADD COLUMN IF NOT EXISTS iss_amount NUMERIC(15,2) DEFAULT 0",
            "ALTER TABLE revenue_documents ADD COLUMN IF NOT EXISTS st_amount NUMERIC(15,2) DEFAULT 0",
            "ALTER TABLE revenue_documents ADD COLUMN IF NOT EXISTS ibs_amount NUMERIC(15,2) DEFAULT 0",
            "ALTER TABLE revenue_documents ADD COLUMN IF NOT EXISTS cbs_amount NUMERIC(15,2) DEFAULT 0",
            "ALTER TABLE revenue_documents ADD COLUMN IF NOT EXISTS difal_amount NUMERIC(15,2) DEFAULT 0",
            "ALTER TABLE revenue_documents ADD COLUMN IF NOT EXISTS other_taxes_amount NUMERIC(15,2) DEFAULT 0",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS can_view_cost BOOLEAN DEFAULT FALSE",
            "ALTER TABLE revenue_items ADD COLUMN IF NOT EXISTS cost_amount NUMERIC(15,2) DEFAULT 0",
            "ALTER TABLE revenue_items ADD COLUMN IF NOT EXISTS icms_amount NUMERIC(15,2) DEFAULT 0",
            "ALTER TABLE revenue_items ADD COLUMN IF NOT EXISTS pis_amount NUMERIC(15,2) DEFAULT 0",
            "ALTER TABLE revenue_items ADD COLUMN IF NOT EXISTS cofins_amount NUMERIC(15,2) DEFAULT 0",
            "ALTER TABLE revenue_items ADD COLUMN IF NOT EXISTS ipi_amount NUMERIC(15,2) DEFAULT 0",
            "ALTER TABLE revenue_items ADD COLUMN IF NOT EXISTS iss_amount NUMERIC(15,2) DEFAULT 0",
            "ALTER TABLE revenue_items ADD COLUMN IF NOT EXISTS st_amount NUMERIC(15,2) DEFAULT 0",
            "ALTER TABLE revenue_items ADD COLUMN IF NOT EXISTS ibs_amount NUMERIC(15,2) DEFAULT 0",
            "ALTER TABLE revenue_items ADD COLUMN IF NOT EXISTS cbs_amount NUMERIC(15,2) DEFAULT 0",
            "ALTER TABLE revenue_items ADD COLUMN IF NOT EXISTS difal_amount NUMERIC(15,2) DEFAULT 0",
            "ALTER TABLE revenue_items ADD COLUMN IF NOT EXISTS other_taxes_amount NUMERIC(15,2) DEFAULT 0",
            "CREATE INDEX IF NOT EXISTS idx_revenue_documents_issue ON revenue_documents(issue_date)",
            "CREATE INDEX IF NOT EXISTS idx_revenue_documents_access_key ON revenue_documents(access_key)",
            "CREATE INDEX IF NOT EXISTS idx_revenue_documents_type ON revenue_documents(document_type)",
            "CREATE INDEX IF NOT EXISTS idx_revenue_documents_emission_store ON revenue_documents(issuing_store_cnpj)",
            "CREATE INDEX IF NOT EXISTS idx_revenue_documents_order_store ON revenue_documents(order_store_cnpj)",
            "CREATE INDEX IF NOT EXISTS idx_revenue_documents_customer ON revenue_documents(customer_document)",
            "CREATE INDEX IF NOT EXISTS idx_revenue_items_document ON revenue_items(revenue_document_id)",
            "CREATE INDEX IF NOT EXISTS idx_revenue_items_product ON revenue_items(product_name)",
            "CREATE TABLE IF NOT EXISTS jobs (
                id SERIAL PRIMARY KEY,
                company_id INTEGER NULL REFERENCES companies(id) ON DELETE SET NULL,
                company_name TEXT NULL,
                job_type VARCHAR(50) NOT NULL,
                status VARCHAR(30) NOT NULL,
                started_at TIMESTAMP NOT NULL DEFAULT NOW(),
                finished_at TIMESTAMP NULL,
                created_count INTEGER DEFAULT 0,
                updated_count INTEGER DEFAULT 0,
                error_count INTEGER DEFAULT 0,
                log_text TEXT NULL
            )",
            "CREATE TABLE IF NOT EXISTS actions_log (
                id SERIAL PRIMARY KEY,
                company_id INTEGER NULL REFERENCES companies(id) ON DELETE SET NULL,
                action_type VARCHAR(60) NOT NULL,
                details TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT NOW()
            )",
            "CREATE TABLE IF NOT EXISTS distribution_controls (
                id SERIAL PRIMARY KEY,
                company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
                doc_type VARCHAR(30) NOT NULL,
                environment VARCHAR(20) NOT NULL,
                last_distribution_check_at TIMESTAMP NULL,
                last_distribution_result TEXT NULL,
                last_ult_nsu VARCHAR(30) NULL,
                last_max_nsu VARCHAR(30) NULL,
                cooldown_until TIMESTAMP NULL,
                locked_by_job_id INTEGER NULL,
                locked_at TIMESTAMP NULL,
                source_context TEXT NULL,
                manual_override_reason TEXT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
                UNIQUE(company_id, doc_type, environment)
            )",
            "CREATE TABLE IF NOT EXISTS period_closures (
                id SERIAL PRIMARY KEY,
                status VARCHAR(30) NOT NULL,
                period_start DATE NOT NULL,
                period_end DATE NOT NULL,
                company_ids TEXT NOT NULL,
                doc_types TEXT NOT NULL,
                only_missing_complete BOOLEAN DEFAULT TRUE,
                try_manifestation BOOLEAN DEFAULT FALSE,
                reprocess_after_manifestation BOOLEAN DEFAULT FALSE,
                generate_export BOOLEAN DEFAULT FALSE,
                save_period_folder BOOLEAN DEFAULT FALSE,
                export_zip_path TEXT NULL,
                export_csv_path TEXT NULL,
                summary_json TEXT NULL,
                messages TEXT NULL,
                started_at TIMESTAMP NOT NULL DEFAULT NOW(),
                finished_at TIMESTAMP NULL
            )",
            "CREATE TABLE IF NOT EXISTS period_closure_items (
                id SERIAL PRIMARY KEY,
                closure_id INTEGER NOT NULL REFERENCES period_closures(id) ON DELETE CASCADE,
                document_id INTEGER NULL REFERENCES documents(id) ON DELETE SET NULL,
                company_id INTEGER NULL REFERENCES companies(id) ON DELETE SET NULL,
                company_name TEXT NULL,
                company_cnpj VARCHAR(20) NULL,
                doc_type VARCHAR(30) NOT NULL,
                access_key VARCHAR(60) NULL,
                issuer_name TEXT NULL,
                issuer_cnpj VARCHAR(20) NULL,
                issue_date TIMESTAMP NULL,
                total_value NUMERIC(15,2) DEFAULT 0,
                status VARCHAR(60) NOT NULL,
                xml_saved BOOLEAN DEFAULT FALSE,
                xml_path TEXT NULL,
                storage_dir TEXT NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT NOW()
            )",
            "CREATE INDEX IF NOT EXISTS idx_period_items_closure ON period_closure_items(closure_id)",
            "CREATE INDEX IF NOT EXISTS idx_period_items_status ON period_closure_items(status)",
            "UPDATE documents SET status = 'xml_completo' WHERE status IN ('downloaded', 'downloaded_in_portal', 'imported')",
            "UPDATE documents SET status = 'apenas_resumo' WHERE status = 'summary_only'",
            "UPDATE documents SET status = 'aguardando_novo_download' WHERE status = 'awaiting_redownload'",
        ];
    }
}
