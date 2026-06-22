CREATE TABLE IF NOT EXISTS settings (key VARCHAR(120) PRIMARY KEY, value TEXT, updated_at TIMESTAMP NULL);

CREATE TABLE IF NOT EXISTS companies (
    id SERIAL PRIMARY KEY,
    company_name TEXT NOT NULL,
    cnpj VARCHAR(20) NOT NULL UNIQUE,
    default_download_dir TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS certificates (
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
);

CREATE TABLE IF NOT EXISTS documents (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NULL REFERENCES companies(id) ON DELETE SET NULL,
    company_name TEXT NULL,
    company_cnpj VARCHAR(20) NULL,
    doc_type VARCHAR(30) NOT NULL,
    model VARCHAR(20) NULL,
    access_key VARCHAR(60) NULL,
    referenced_nfe_keys TEXT NULL,
    number VARCHAR(60) NULL,
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
);
CREATE INDEX IF NOT EXISTS idx_documents_company ON documents(company_id);
CREATE INDEX IF NOT EXISTS idx_documents_type ON documents(doc_type);
CREATE INDEX IF NOT EXISTS idx_documents_status ON documents(status);
CREATE INDEX IF NOT EXISTS idx_documents_access_key ON documents(access_key);
ALTER TABLE documents ADD COLUMN IF NOT EXISTS referenced_nfe_keys TEXT NULL;

CREATE TABLE IF NOT EXISTS jobs (
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
);

CREATE TABLE IF NOT EXISTS actions_log (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NULL REFERENCES companies(id) ON DELETE SET NULL,
    action_type VARCHAR(60) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS distribution_controls (
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
);

CREATE TABLE IF NOT EXISTS period_closures (
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
);

CREATE TABLE IF NOT EXISTS period_closure_items (
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
);
CREATE INDEX IF NOT EXISTS idx_period_items_closure ON period_closure_items(closure_id);
CREATE INDEX IF NOT EXISTS idx_period_items_status ON period_closure_items(status);
