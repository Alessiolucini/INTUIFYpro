<?php
/**
 * IntuiFy Configuration — EXAMPLE FILE
 * Reference of the keys returned by config.php.
 * config.php is committed and reads every secret from environment variables;
 * this file only documents the keys. Never put real values in either file.
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
    'admin_password_hash' => 'BCRYPT_HASH_FROM_ADMIN_PASSWORD_HASH', // preferred
    'admin_password' => 'YOUR_ADMIN_PASSWORD',               // fallback if no hash
    
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

    // Server Control Center (internal Docker network URLs)
    'prometheus_url' => 'http://intuify-prometheus:9090',
    'prometheus_timeout' => 5,
    'alertmanager_url' => 'http://intuify-alertmanager:9093',
    'monitoring_server_name' => 'INTUIFY SERVER',
    'monitoring_server_env' => 'Production',
    'monitoring_critical_containers' => 'dokploy|traefik',
];
