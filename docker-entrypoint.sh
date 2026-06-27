#!/bin/bash
# Generate config.php from environment variables if it doesn't exist
if [ ! -f /var/www/html/config.php ]; then
    cat > /var/www/html/config.php << 'PHPCONFIG'
<?php
return [
    'smtp_host' => 'smtp.hostinger.com',
    'smtp_port' => 465,
    'smtp_encryption' => 'ssl',
    'smtp_username' => 'info@intuify.net',
    'smtp_password' => getenv('SMTP_PASSWORD') ?: 'Alessi016011983$',
    
    'mail_from' => 'info@intuify.net',
    'mail_from_name' => 'IntuiFy',
    'mail_to' => 'info@intuify.net',

    'recaptcha_site_key' => '6Ld3N1UsAAAAAGF8GWQMgUAUkG9ZRktVQlVFMCha',
    'recaptcha_secret_key' => '6Ld3N1UsAAAAAOr8Pm5kL7zBDU9Jx-hGYRX3FoyK',
    'recaptcha_min_score' => 0.5,

    'supabase_url' => getenv('SUPABASE_URL') ?: 'https://supabase.intuify.net',
    'supabase_anon_key' => getenv('SUPABASE_ANON_KEY') ?: '',
    'supabase_service_key' => getenv('SUPABASE_SERVICE_KEY') ?: '',

    'admin_username' => 'alessio',
    'admin_password_hash' => '$2y$10$IntuiFyAdminHash2026.PlaceholderToBeSetOnFirstRun',
    'admin_password_plain' => getenv('ADMIN_PASSWORD') ?: 'Alessi016011983$',
    
    'company_name' => 'IntuiFy',
    'company_legal_name' => 'Intuify Ventures SL',
    'company_vat' => 'B88769526',
    'company_address' => 'Calle Mussol 5 2Pta. B',
    'company_email' => 'info@intuify.net',
    'company_iban' => getenv('COMPANY_IBAN') ?: 'ESXX XXXX XXXX XXXX XXXX XXXX',
    
    'invoice_prefix' => 'INV',
    'contract_prefix' => 'CTR',

    'openai_api_key' => getenv('OPENAI_API_KEY') ?: '',
    'openai_model' => 'gpt-4o',
    'openai_vision_model' => 'gpt-4o',
];
PHPCONFIG
    chown www-data:www-data /var/www/html/config.php
    echo "✅ config.php generated from environment"
fi

# Start Apache
exec apache2-foreground
