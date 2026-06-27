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
INSERT INTO products (name, type, url, description, status) VALUES
    ('Auterio', 'saas', NULL, 'Auterio — IntuiFy Product', 'active'),
    ('LingoBite', 'saas', 'https://lingobite.net', 'LingoBite — Language Learning Platform', 'active'),
    ('Orqesia', 'saas', NULL, 'Orqesia — IntuiFy Product', 'active'),
    ('Eco Andratx', 'website', NULL, 'Eco Andratx — IntuiFy Product', 'active')
ON CONFLICT DO NOTHING;

-- ============================================================================
-- Row Level Security (RLS) — Disabled for service_role access
-- ============================================================================
-- Since admin uses service_role key, RLS is bypassed.
-- If you later add multi-user access, enable RLS policies here.

-- Create storage bucket for expense files
-- Run this separately if needed:
-- INSERT INTO storage.buckets (id, name, public) VALUES ('expenses', 'expenses', false);
-- INSERT INTO storage.buckets (id, name, public) VALUES ('documents', 'documents', false);
