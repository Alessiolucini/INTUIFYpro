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
    // Get your keys at: https://www.google.com/recaptcha/admin
    // Site Key: Used in frontend (visible in source code - this is OK)
    'recaptcha_site_key' => '6Ld3N1UsAAAAAGF8GWQMgUAUkG9ZRktVQlVFMCha',

    // Secret Key: Used in backend only (NEVER expose this!)
    'recaptcha_secret_key' => '6Ld3N1UsAAAAAOr8Pm5kL7zBDU9Jx-hGYRX3FoyK',

    // Minimum reCAPTCHA score (0.0 to 1.0)
    // 0.0 = definitely bot, 1.0 = definitely human
    // Recommended: 0.5 for balanced protection
    'recaptcha_min_score' => 0.5,
];
