<?php
/**
 * IntuiFy Configuration — EXAMPLE FILE
 * Copy this to config.php and fill in real values.
 * config.php is in .gitignore and will NOT be committed.
 */

return [
    // SMTP Configuration
    'smtp_host' => 'smtp.hostinger.com',
    'smtp_port' => 465,
    'smtp_encryption' => 'ssl',
    'smtp_username' => 'info@intuify.net',
    'smtp_password' => 'YOUR_SMTP_PASSWORD',
    
    'mail_from' => 'info@intuify.net',
    'mail_from_name' => 'IntuiFy',
    'mail_to' => 'info@intuify.net',

    // Google reCAPTCHA v3
    'recaptcha_site_key' => 'YOUR_RECAPTCHA_SITE_KEY',
    'recaptcha_secret_key' => 'YOUR_RECAPTCHA_SECRET_KEY',
    'recaptcha_min_score' => 0.5,

    // Supabase Self-Hosted
    'supabase_url' => 'https://supabase.intuify.net',
    'supabase_anon_key' => 'YOUR_SUPABASE_ANON_KEY',
    'supabase_service_key' => 'YOUR_SUPABASE_SERVICE_KEY',

    // Admin Panel
    'admin_username' => 'alessio',
    'admin_password_hash' => '$2y$10$IntuiFyAdminHash2026.PlaceholderToBeSetOnFirstRun',
    'admin_password_plain' => 'YOUR_ADMIN_PASSWORD',
    
    // Company details
    'company_name' => 'IntuiFy',
    'company_legal_name' => 'Intuify Ventures SL',
    'company_vat' => 'B88769526',
    'company_address' => 'Calle Mussol 5 2Pta. B',
    'company_email' => 'info@intuify.net',
    'company_iban' => 'ESXX XXXX XXXX XXXX XXXX XXXX',
    
    'invoice_prefix' => 'INV',
    'contract_prefix' => 'CTR',

    // OpenAI
    'openai_api_key' => 'YOUR_OPENAI_API_KEY',
    'openai_model' => 'gpt-4o',
    'openai_vision_model' => 'gpt-4o',
];
