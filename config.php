<?php
/**
 * IntuiFy Configuration File
 * 
 * IMPORTANT: This file contains sensitive credentials.
 * DO NOT commit this file to version control!
 * Make sure config.php is listed in .gitignore
 */

return [
    // SMTP Configuration for contact form emails
    'smtp_host' => 'smtp.hostinger.com',
    'smtp_port' => 465,
    'smtp_encryption' => 'ssl',
    'smtp_username' => 'info@intuify.net',
    'smtp_password' => 'Alessi016011983$',
    
    // Email addresses
    'mail_from' => 'info@intuify.net',
    'mail_from_name' => 'IntuiFy',
    'mail_to' => 'info@intuify.net',

    // Google reCAPTCHA v3 Keys
    'recaptcha_site_key' => '6Ld3N1UsAAAAAGF8GWQMgUAUkG9ZRktVQlVFMCha',
    'recaptcha_secret_key' => '6Ld3N1UsAAAAAOr8Pm5kL7zBDU9Jx-hGYRX3FoyK',
    'recaptcha_min_score' => 0.5,

    // =========================================================================
    // Supabase Self-Hosted Configuration
    // =========================================================================
    'supabase_url' => 'https://supabase.intuify.net',
    'supabase_anon_key' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpYXQiOjE3ODI1NDQyNzcsImV4cCI6MTg5MzQ1NjAwMCwicm9sZSI6ImFub24iLCJpc3MiOiJzdXBhYmFzZSJ9.gv4aaTpbQDbUllZPEumfcjosltaqzUvwR3OcbUg0jAQ',
    'supabase_service_key' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpYXQiOjE3ODI1NDQyNzcsImV4cCI6MTg5MzQ1NjAwMCwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlzcyI6InN1cGFiYXNlIn0.VX_BDazkuR54AyjFQMhB4hUZkFHRRnGefWsx5J_mH3w',

    // =========================================================================
    // Admin Panel Configuration
    // =========================================================================
    'admin_username' => 'alessio',
    // Pre-computed bcrypt hash — update via: php -r "echo password_hash('YOUR_NEW_PASSWORD', PASSWORD_BCRYPT);"
    'admin_password_hash' => '$2y$10$IntuiFyAdminHash2026.PlaceholderToBeSetOnFirstRun',
    'admin_password_plain' => 'Alessi016011983$', // Fallback: used to auto-hash on first login
    
    // Company details for invoices/contracts (UPDATE THESE!)
    'company_name' => 'IntuiFy',
    'company_legal_name' => 'Intuify Ventures SL',
    'company_vat' => 'B88769526',
    'company_address' => 'Calle Mussol 5 2Pta. B',
    'company_email' => 'info@intuify.net',
    'company_iban' => 'ESXX XXXX XXXX XXXX XXXX XXXX', // TODO: Inserire IBAN reale
    
    // Invoice/Contract numbering
    'invoice_prefix' => 'INV',
    'contract_prefix' => 'CTR',
];
