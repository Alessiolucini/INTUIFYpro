-- ============================================================================
-- IntuiFy Admin Panel — Database Migration
-- Run this in Supabase SQL Editor (https://supabase.intuify.net)
-- ============================================================================

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ============================================================================
-- 1. PRODUCTS
-- ============================================================================
CREATE TABLE IF NOT EXISTS products (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name TEXT NOT NULL,
    type TEXT NOT NULL DEFAULT 'saas' CHECK (type IN ('saas', 'aaas', 'website', 'app', 'other')),
    url TEXT,
    description TEXT,
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'development', 'archived')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ============================================================================
-- 2. CLIENTS
-- ============================================================================
CREATE TABLE IF NOT EXISTS clients (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    company_name TEXT NOT NULL,
    contact_person TEXT,
    email TEXT,
    phone TEXT,
    address TEXT,
    vat_number TEXT,
    notes TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ============================================================================
-- 3. CLIENT_PRODUCTS (many-to-many)
-- ============================================================================
CREATE TABLE IF NOT EXISTS client_products (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    client_id UUID NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    subscribed_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'cancelled', 'trial')),
    UNIQUE(client_id, product_id)
);

-- ============================================================================
-- 4. LEADS
-- ============================================================================
CREATE TABLE IF NOT EXISTS leads (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name TEXT NOT NULL,
    email TEXT,
    phone TEXT,
    company TEXT,
    message TEXT,
    source TEXT NOT NULL DEFAULT 'landing_form' CHECK (source IN ('landing_form', 'email', 'referral', 'other')),
    status TEXT NOT NULL DEFAULT 'new' CHECK (status IN ('new', 'contacted', 'qualified', 'converted', 'lost')),
    converted_client_id UUID REFERENCES clients(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ============================================================================
-- 5. CONTRACTS
-- ============================================================================
CREATE TABLE IF NOT EXISTS contracts (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    contract_number TEXT NOT NULL UNIQUE,
    client_id UUID NOT NULL REFERENCES clients(id) ON DELETE RESTRICT,
    product_id UUID REFERENCES products(id) ON DELETE SET NULL,
    title TEXT NOT NULL,
    description TEXT,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency TEXT NOT NULL DEFAULT 'EUR',
    start_date DATE,
    end_date DATE,
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'sent', 'signed', 'expired', 'cancelled')),
    pdf_path TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ============================================================================
-- 6. INVOICES
-- ============================================================================
CREATE TABLE IF NOT EXISTS invoices (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    invoice_number TEXT NOT NULL UNIQUE,
    client_id UUID NOT NULL REFERENCES clients(id) ON DELETE RESTRICT,
    contract_id UUID REFERENCES contracts(id) ON DELETE SET NULL,
    items JSONB NOT NULL DEFAULT '[]'::jsonb,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 21.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency TEXT NOT NULL DEFAULT 'EUR',
    issue_date DATE NOT NULL DEFAULT CURRENT_DATE,
    due_date DATE,
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'sent', 'paid', 'overdue', 'cancelled')),
    pdf_path TEXT,
    notes TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ============================================================================
-- 7. EXPENSES
-- ============================================================================
CREATE TABLE IF NOT EXISTS expenses (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    description TEXT NOT NULL,
    vendor TEXT,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency TEXT NOT NULL DEFAULT 'EUR',
    category TEXT NOT NULL DEFAULT 'other' CHECK (category IN ('hosting', 'software', 'marketing', 'legal', 'design', 'hardware', 'office', 'other')),
    date DATE NOT NULL DEFAULT CURRENT_DATE,
    file_url TEXT,
    notes TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ============================================================================
-- 8. DOMAINS
-- ============================================================================
CREATE TABLE IF NOT EXISTS domains (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    domain_name TEXT NOT NULL UNIQUE,
    registrar TEXT,
    purchase_date DATE,
    expiry_date DATE,
    auto_renew BOOLEAN NOT NULL DEFAULT false,
    annual_cost DECIMAL(8,2) NOT NULL DEFAULT 0,
    associated_product_id UUID REFERENCES products(id) ON DELETE SET NULL,
    notes TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ============================================================================
-- INDEXES for performance
-- ============================================================================
CREATE INDEX IF NOT EXISTS idx_leads_status ON leads(status);
CREATE INDEX IF NOT EXISTS idx_leads_source ON leads(source);
CREATE INDEX IF NOT EXISTS idx_leads_created ON leads(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_contracts_client ON contracts(client_id);
CREATE INDEX IF NOT EXISTS idx_contracts_status ON contracts(status);
CREATE INDEX IF NOT EXISTS idx_invoices_client ON invoices(client_id);
CREATE INDEX IF NOT EXISTS idx_invoices_status ON invoices(status);
CREATE INDEX IF NOT EXISTS idx_invoices_issue_date ON invoices(issue_date DESC);
CREATE INDEX IF NOT EXISTS idx_expenses_category ON expenses(category);
CREATE INDEX IF NOT EXISTS idx_expenses_date ON expenses(date DESC);
CREATE INDEX IF NOT EXISTS idx_domains_expiry ON domains(expiry_date);
CREATE INDEX IF NOT EXISTS idx_client_products_client ON client_products(client_id);
CREATE INDEX IF NOT EXISTS idx_client_products_product ON client_products(product_id);

-- ============================================================================
-- UPDATED_AT trigger function
-- ============================================================================
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Apply trigger to all tables
DO $$
DECLARE
    tbl TEXT;
BEGIN
    FOR tbl IN SELECT unnest(ARRAY['products','clients','leads','contracts','invoices','expenses','domains'])
    LOOP
        EXECUTE format('DROP TRIGGER IF EXISTS set_updated_at ON %I', tbl);
        EXECUTE format('CREATE TRIGGER set_updated_at BEFORE UPDATE ON %I FOR EACH ROW EXECUTE FUNCTION update_updated_at_column()', tbl);
    END LOOP;
END;
$$;

-- ============================================================================
-- RPC Functions for Dashboard KPIs
-- ============================================================================

-- Monthly revenue (paid invoices)
CREATE OR REPLACE FUNCTION get_monthly_revenue(months_back INT DEFAULT 12)
RETURNS TABLE(month TEXT, total DECIMAL) AS $$
BEGIN
    RETURN QUERY
    SELECT
        TO_CHAR(date_trunc('month', i.issue_date), 'YYYY-MM') as month,
        COALESCE(SUM(i.total), 0) as total
    FROM invoices i
    WHERE i.status = 'paid'
      AND i.issue_date >= (CURRENT_DATE - (months_back || ' months')::INTERVAL)
    GROUP BY date_trunc('month', i.issue_date)
    ORDER BY month;
END;
$$ LANGUAGE plpgsql;

-- Monthly expenses
CREATE OR REPLACE FUNCTION get_monthly_expenses(months_back INT DEFAULT 12)
RETURNS TABLE(month TEXT, total DECIMAL) AS $$
BEGIN
    RETURN QUERY
    SELECT
        TO_CHAR(date_trunc('month', e.date), 'YYYY-MM') as month,
        COALESCE(SUM(e.amount), 0) as total
    FROM expenses e
    WHERE e.date >= (CURRENT_DATE - (months_back || ' months')::INTERVAL)
    GROUP BY date_trunc('month', e.date)
    ORDER BY month;
END;
$$ LANGUAGE plpgsql;

-- Dashboard summary KPIs
CREATE OR REPLACE FUNCTION get_dashboard_kpis()
RETURNS JSON AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'total_revenue', COALESCE((SELECT SUM(total) FROM invoices WHERE status = 'paid'), 0),
        'total_expenses', COALESCE((SELECT SUM(amount) FROM expenses), 0),
        'pending_invoices', COALESCE((SELECT SUM(total) FROM invoices WHERE status IN ('sent', 'overdue')), 0),
        'new_leads_month', (SELECT COUNT(*) FROM leads WHERE created_at >= date_trunc('month', CURRENT_DATE)),
        'active_contracts', (SELECT COUNT(*) FROM contracts WHERE status = 'signed'),
        'expiring_domains', (SELECT COUNT(*) FROM domains WHERE expiry_date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '30 days'),
        'total_clients', (SELECT COUNT(*) FROM clients),
        'total_products', (SELECT COUNT(*) FROM products WHERE status = 'active')
    ) INTO result;
    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- ============================================================================
-- SEED: Initial Products
-- ============================================================================
-- products.name has no UNIQUE constraint: insert only missing names so the
-- whole file can be re-run safely (ON CONFLICT alone never fired → duplicates).
-- Descriptions mirror the landing page (i18n/it.json) and feed the AI prompts.
INSERT INTO products (name, type, url, description, status)
SELECT v.name, v.type, v.url, v.description, 'active'
FROM (VALUES
    ('Auterio', 'saas', 'https://auterio.net',
     'Piattaforma digitale per concessionarie auto: gestione veicoli, perizie, wallet digitale e dashboard analytics.'),
    ('LingoBite', 'saas', 'https://lingobite.net',
     'SaaS per ristoranti che traduce automaticamente i menù in 30+ lingue con IA, con QR code dinamici.'),
    ('Orqesia', 'aaas', 'https://orqesia.com',
     'Piattaforma AaaS (Agents as a Service) di gestione lead: orchestrazione di 6+ agenti AI che convertono ogni lead in vendita in modo automatico.'),
    ('Eco Andratx', 'app', 'https://ecoandratx.es',
     'App civica per la gestione dei rifiuti e il riciclo nel comune di Andratx: calendario raccolta, segnalazioni e punti verdi.')
) AS v(name, type, url, description)
WHERE NOT EXISTS (SELECT 1 FROM products p WHERE p.name = v.name);

-- ============================================================================
-- Row Level Security (RLS) — Disabled for service_role access
-- ============================================================================
-- Since admin uses service_role key, RLS is bypassed.
-- If you later add multi-user access, enable RLS policies here.

-- Create storage bucket for expense files
-- Run this separately if needed:
-- INSERT INTO storage.buckets (id, name, public) VALUES ('expenses', 'expenses', false);
-- INSERT INTO storage.buckets (id, name, public) VALUES ('documents', 'documents', false);

-- ============================================================================
-- 9. SUBSCRIPTIONS
-- ============================================================================
CREATE TABLE IF NOT EXISTS subscriptions (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    client_id UUID NOT NULL REFERENCES clients(id) ON DELETE RESTRICT,
    product_id UUID REFERENCES products(id) ON DELETE SET NULL,
    plan_name TEXT NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency TEXT NOT NULL DEFAULT 'EUR',
    billing_cycle TEXT NOT NULL DEFAULT 'monthly' CHECK (billing_cycle IN ('monthly', 'quarterly', 'semiannual', 'annual', 'one_time')),
    start_date DATE,
    end_date DATE,
    next_billing DATE,
    auto_renew BOOLEAN NOT NULL DEFAULT true,
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'trial', 'paused', 'cancelled', 'expired')),
    notes TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Indexes for subscriptions
CREATE INDEX IF NOT EXISTS idx_subscriptions_client ON subscriptions(client_id);
CREATE INDEX IF NOT EXISTS idx_subscriptions_product ON subscriptions(product_id);
CREATE INDEX IF NOT EXISTS idx_subscriptions_status ON subscriptions(status);
CREATE INDEX IF NOT EXISTS idx_subscriptions_next_billing ON subscriptions(next_billing);
CREATE INDEX IF NOT EXISTS idx_subscriptions_end_date ON subscriptions(end_date);

-- Apply updated_at trigger to subscriptions
DROP TRIGGER IF EXISTS set_updated_at ON subscriptions;
CREATE TRIGGER set_updated_at BEFORE UPDATE ON subscriptions
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================================
-- 10. INVOICE PAYMENT DETAILS
-- ============================================================================
-- Stores per-invoice banking/payment info (IBAN, beneficiary, etc.)
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS payment_details TEXT;

-- ============================================================================
-- 11. CONTRACT AI FIELDS
-- ============================================================================
-- Structured AI-generated clauses stored as JSONB
ALTER TABLE contracts ADD COLUMN IF NOT EXISTS clauses JSONB;
-- Payment terms / IBAN specific to this contract
ALTER TABLE contracts ADD COLUMN IF NOT EXISTS payment_terms TEXT;
-- Whether this contract was generated by the AI assistant
ALTER TABLE contracts ADD COLUMN IF NOT EXISTS ai_generated BOOLEAN NOT NULL DEFAULT false;


-- ============================================================================
-- 12. AUDIT LOG (admin security) — required before any Server Control action
-- ============================================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    actor TEXT NOT NULL DEFAULT 'anonymous',      -- admin username (until admin_users exists)
    user_id UUID,                                  -- future: admin_users.id
    server_id UUID,                                -- future: servers.id (FK added below)
    action TEXT NOT NULL,                          -- login, login_failed, logout, delete, convert, ai-reply…
    resource_type TEXT,                            -- page/module or resource kind
    resource_id TEXT,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    ip TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs(action);

-- ============================================================================
-- 13. SERVER CONTROL CENTER — inventory & events (metrics stay in Prometheus)
-- ============================================================================
CREATE TABLE IF NOT EXISTS servers (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    slug TEXT NOT NULL UNIQUE,                     -- used by the API (?server=primary)
    name TEXT NOT NULL,
    hostname TEXT,
    provider TEXT,
    environment TEXT NOT NULL DEFAULT 'production',
    role TEXT NOT NULL DEFAULT 'primary',
    public_ip TEXT,
    internal_ip TEXT,
    location TEXT,
    os TEXT,
    cpu_cores INT,
    memory_total BIGINT,
    disk_total BIGINT,
    status TEXT NOT NULL DEFAULT 'unknown' CHECK (status IN ('healthy', 'warning', 'critical', 'offline', 'unknown')),
    prometheus_target TEXT,                        -- node-exporter instance label
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS monitored_projects (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    server_id UUID NOT NULL REFERENCES servers(id) ON DELETE CASCADE,
    product_id UUID REFERENCES products(id) ON DELETE SET NULL,
    name TEXT NOT NULL,
    dokploy_project_id TEXT,
    environment TEXT NOT NULL DEFAULT 'production',
    repository TEXT,
    branch TEXT,
    domain TEXT,
    supabase_domain TEXT,
    is_critical BOOLEAN NOT NULL DEFAULT false,
    enabled BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS monitored_endpoints (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    project_id UUID REFERENCES monitored_projects(id) ON DELETE CASCADE,
    domain_id UUID REFERENCES domains(id) ON DELETE SET NULL,
    name TEXT NOT NULL,
    url TEXT NOT NULL UNIQUE,
    expected_status INT NOT NULL DEFAULT 200,
    check_interval INT NOT NULL DEFAULT 30,        -- seconds
    critical BOOLEAN NOT NULL DEFAULT false,
    enabled BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS alert_rules (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    server_id UUID REFERENCES servers(id) ON DELETE CASCADE,
    metric TEXT NOT NULL,
    operator TEXT NOT NULL DEFAULT '>' CHECK (operator IN ('>', '>=', '<', '<=')),
    warning_threshold NUMERIC,
    critical_threshold NUMERIC,
    duration TEXT,                                 -- e.g. '10m'
    enabled BOOLEAN NOT NULL DEFAULT true,
    UNIQUE (server_id, metric)
);

CREATE TABLE IF NOT EXISTS incidents (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    number TEXT UNIQUE,                            -- INC-2026-0001
    server_id UUID REFERENCES servers(id) ON DELETE SET NULL,
    project_id UUID REFERENCES monitored_projects(id) ON DELETE SET NULL,
    severity TEXT NOT NULL DEFAULT 'warning' CHECK (severity IN ('warning', 'critical')),
    type TEXT,
    title TEXT NOT NULL,
    description TEXT,
    started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    resolved_at TIMESTAMPTZ,
    acknowledged_by TEXT,
    acknowledged_at TIMESTAMPTZ
);

CREATE TABLE IF NOT EXISTS backups (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    server_id UUID REFERENCES servers(id) ON DELETE SET NULL,
    project_id UUID REFERENCES monitored_projects(id) ON DELETE SET NULL,
    backup_type TEXT NOT NULL,
    started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    completed_at TIMESTAMPTZ,
    status TEXT NOT NULL DEFAULT 'running' CHECK (status IN ('running', 'success', 'failed')),
    size_bytes BIGINT,
    destination TEXT,
    verified BOOLEAN NOT NULL DEFAULT false
);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'audit_logs_server_id_fkey') THEN
        ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_server_id_fkey
            FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE SET NULL;
    END IF;
END;
$$;

CREATE INDEX IF NOT EXISTS idx_monitored_projects_server ON monitored_projects(server_id);
CREATE INDEX IF NOT EXISTS idx_monitored_endpoints_project ON monitored_endpoints(project_id);
CREATE INDEX IF NOT EXISTS idx_incidents_server_started ON incidents(server_id, started_at DESC);
CREATE INDEX IF NOT EXISTS idx_backups_project_started ON backups(project_id, started_at DESC);

DROP TRIGGER IF EXISTS set_updated_at ON servers;
CREATE TRIGGER set_updated_at BEFORE UPDATE ON servers
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
DROP TRIGGER IF EXISTS set_updated_at ON monitored_projects;
CREATE TRIGGER set_updated_at BEFORE UPDATE ON monitored_projects
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Seed: primary server + initial alert thresholds (spec §6, §34)
INSERT INTO servers (slug, name, environment, role, status)
VALUES ('primary', 'INTUIFY SERVER', 'production', 'primary', 'unknown')
ON CONFLICT (slug) DO NOTHING;

INSERT INTO alert_rules (server_id, metric, operator, warning_threshold, critical_threshold, duration)
SELECT s.id, r.metric, '>', r.warn, r.crit, r.dur
FROM servers s
CROSS JOIN (VALUES
    ('cpu_percent',    75, 90, '10m'),
    ('memory_percent', 80, 92, '10m'),
    ('disk_percent',   75, 90, NULL),
    ('inode_percent',  75, 90, NULL)
) AS r(metric, warn, crit, dur)
WHERE s.slug = 'primary'
ON CONFLICT (server_id, metric) DO NOTHING;
