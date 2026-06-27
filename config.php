<?php
/**
 * IntuiFy Configuration
 * All secrets are loaded from environment variables.
 * Set them in Dokploy → Environment → Service Environment Variables.
 */

return [
    // =========================================================================
    // SMTP Configuration (Hostinger)
    // =========================================================================
    'smtp_host' => 'smtp.hostinger.com',
    'smtp_port' => 465,
    'smtp_encryption' => 'ssl',
    'smtp_username' => 'info@intuify.net',
    'smtp_password' => getenv('SMTP_PASSWORD') ?: '',

    'mail_from' => 'info@intuify.net',
    'mail_from_name' => 'IntuiFy',
    'mail_to' => 'info@intuify.net',

    // =========================================================================
    // Google reCAPTCHA v3
    // =========================================================================
    'recaptcha_site_key' => getenv('RECAPTCHA_SITE_KEY') ?: '6Ld3N1UsAAAAAGF8GWQMgUAUkG9ZRktVQlVFMCha',
    'recaptcha_secret_key' => getenv('RECAPTCHA_SECRET_KEY') ?: '6Ld3N1UsAAAAAOr8Pm5kL7zBDU9Jx-hGYRX3FoyK',
    'recaptcha_min_score' => 0.5,

    // =========================================================================
    // Supabase Self-Hosted
    // =========================================================================
    'supabase_url' => getenv('SUPABASE_URL') ?: 'https://supabase.intuify.net',
    'supabase_anon_key' => getenv('SUPABASE_ANON_KEY') ?: '',
    'supabase_service_key' => getenv('SUPABASE_SERVICE_KEY') ?: '',

    // =========================================================================
    // Admin Panel Authentication
    // =========================================================================
    'admin_username' => 'alessio',
    'admin_password_hash' => password_hash(getenv('ADMIN_PASSWORD') ?: 'changeme', PASSWORD_DEFAULT),
    'admin_password_plain' => getenv('ADMIN_PASSWORD') ?: 'changeme',

    // =========================================================================
    // Company Details (for invoices & contracts)
    // =========================================================================
    'company_name' => 'IntuiFy',
    'company_legal_name' => 'Intuify Ventures SL',
    'company_vat' => 'B88769526',
    'company_address' => 'Calle Mussol 5 2Pta. B',
    'company_email' => 'info@intuify.net',
    'company_iban' => getenv('COMPANY_IBAN') ?: '',

    // Invoice/Contract numbering
    'invoice_prefix' => 'INV',
    'contract_prefix' => 'CTR',

    // =========================================================================
    // OpenAI API Configuration (for AI Assistants)
    // =========================================================================
    'openai_api_key' => getenv('OPENAI_API_KEY') ?: '',
    'openai_model' => 'gpt-4o',
    'openai_vision_model' => 'gpt-4o',
];
