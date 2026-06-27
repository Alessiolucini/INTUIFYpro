<?php
/**
 * IntuiFy — Contact Form Diagnostic Script
 * 
 * Deploy this file and visit it in the browser to test:
 * 1. SMTP connectivity (email sending)
 * 2. Supabase connectivity (lead saving)
 * 3. OpenAI connectivity (AI auto-reply)
 * 4. reCAPTCHA configuration
 * 5. Environment variables
 *
 * ⚠️  DELETE THIS FILE AFTER DEBUGGING — it exposes config details.
 * Access: https://intuify.net/test_contact_debug.php
 */

declare(strict_types=1);

// Simple access protection — change this token or use ?token=intuify-debug-2026
$accessToken = 'intuify-debug-2026';
if (($_GET['token'] ?? '') !== $accessToken) {
    http_response_code(403);
    echo '🔒 Access denied. Use ?token=YOUR_TOKEN';
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><title>IntuiFy Debug</title>
<style>
body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:2rem;max-width:800px;margin:0 auto}
h1{color:#818cf8}h2{color:#a78bfa;border-bottom:1px solid #334155;padding-bottom:0.5rem}
.ok{color:#34d399}.fail{color:#f87171}.warn{color:#fbbf24}
pre{background:#1e293b;padding:1rem;border-radius:8px;overflow-x:auto;border:1px solid #334155}
.section{margin:1.5rem 0;padding:1rem;border:1px solid #334155;border-radius:8px;background:#1e293b}
</style></head><body>";

echo "<h1>🔧 IntuiFy Contact Form Diagnostics</h1>";
echo "<p>Time: " . date('Y-m-d H:i:s T') . "</p>";

// ============================================================================
// 1. Environment Variables Check
// ============================================================================
echo "<h2>1. Environment Variables</h2><div class='section'>";

$envVars = [
    'SMTP_PASSWORD' => !empty(getenv('SMTP_PASSWORD')),
    'SUPABASE_URL' => !empty(getenv('SUPABASE_URL')),
    'SUPABASE_ANON_KEY' => !empty(getenv('SUPABASE_ANON_KEY')),
    'SUPABASE_SERVICE_KEY' => !empty(getenv('SUPABASE_SERVICE_KEY')),
    'RECAPTCHA_SITE_KEY' => !empty(getenv('RECAPTCHA_SITE_KEY')),
    'RECAPTCHA_SECRET_KEY' => !empty(getenv('RECAPTCHA_SECRET_KEY')),
    'OPENAI_API_KEY' => !empty(getenv('OPENAI_API_KEY')),
    'ADMIN_PASSWORD' => !empty(getenv('ADMIN_PASSWORD')),
];

foreach ($envVars as $var => $isSet) {
    $status = $isSet ? "<span class='ok'>✅ SET</span>" : "<span class='fail'>❌ NOT SET</span>";
    echo "<p>{$var}: {$status}</p>";
}

// Check resolved config values
echo "<p><br><strong>Resolved SMTP Config:</strong></p>";
echo "<pre>";
echo "Host: {$config['smtp_host']}\n";
echo "Port: {$config['smtp_port']}\n";
echo "Encryption: {$config['smtp_encryption']}\n";
echo "Username: {$config['smtp_username']}\n";
echo "Password: " . (empty($config['smtp_password']) ? '❌ EMPTY' : '✅ SET (' . strlen($config['smtp_password']) . ' chars)') . "\n";
echo "From: {$config['mail_from']}\n";
echo "To: {$config['mail_to']}\n";
echo "</pre>";

echo "<p><strong>Supabase:</strong></p><pre>";
echo "URL: {$config['supabase_url']}\n";
echo "Anon Key: " . (empty($config['supabase_anon_key']) ? '❌ EMPTY' : '✅ SET (' . strlen($config['supabase_anon_key']) . ' chars)') . "\n";
echo "Service Key: " . (empty($config['supabase_service_key']) ? '❌ EMPTY' : '✅ SET (' . strlen($config['supabase_service_key']) . ' chars)') . "\n";
echo "</pre>";

echo "</div>";

// ============================================================================
// 2. SMTP Connection Test
// ============================================================================
echo "<h2>2. SMTP Connection Test</h2><div class='section'>";

if (empty($config['smtp_password'])) {
    echo "<p class='fail'>❌ CANNOT TEST — SMTP_PASSWORD is empty!</p>";
    echo "<p class='warn'>⚠️ Set SMTP_PASSWORD in Dokploy → Environment Variables</p>";
} else {
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_username'];
        $mail->Password   = $config['smtp_password'];
        $mail->SMTPSecure = $config['smtp_encryption'];
        $mail->Port       = $config['smtp_port'];
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 10;
        
        $mail->setFrom($config['mail_from'], 'IntuiFy Debug');
        $mail->addAddress($config['mail_to']);
        $mail->isHTML(true);
        $mail->Subject = '🔧 IntuiFy Debug — Test Email';
        $mail->Body = '<h2>✅ SMTP Test Successful</h2><p>If you see this, the SMTP configuration is working.</p><p>Time: ' . date('Y-m-d H:i:s') . '</p>';
        $mail->AltBody = 'SMTP Test OK — ' . date('Y-m-d H:i:s');
        
        $mail->send();
        echo "<p class='ok'>✅ SMTP test email sent to {$config['mail_to']}</p>";
        echo "<p>Check your inbox (and spam folder) for the test email.</p>";
    } catch (\Throwable $e) {
        echo "<p class='fail'>❌ SMTP FAILED: " . htmlspecialchars($e->getMessage()) . "</p>";
        
        // Additional diagnostics
        echo "<p class='warn'>Attempting raw socket connection to {$config['smtp_host']}:{$config['smtp_port']}...</p>";
        $errno = 0;
        $errstr = '';
        $conn = @fsockopen(
            ($config['smtp_encryption'] === 'ssl' ? 'ssl://' : '') . $config['smtp_host'],
            $config['smtp_port'],
            $errno,
            $errstr,
            10
        );
        if ($conn) {
            $response = fgets($conn, 256);
            echo "<p class='ok'>✅ Socket connection OK. Server response: " . htmlspecialchars($response) . "</p>";
            echo "<p class='warn'>→ Connection works but auth may be failing. Check SMTP_PASSWORD value.</p>";
            fclose($conn);
        } else {
            echo "<p class='fail'>❌ Socket FAILED: [{$errno}] {$errstr}</p>";
            echo "<p class='warn'>→ The server cannot reach smtp.hostinger.com:{$config['smtp_port']}. Check firewall/network.</p>";
        }
    }
}
echo "</div>";

// ============================================================================
// 3. Supabase Connection Test
// ============================================================================
echo "<h2>3. Supabase Connection Test</h2><div class='section'>";

try {
    require_once __DIR__ . '/admin/includes/supabase.php';
    $sb = getSupabase();
    
    // Try to read from leads table
    $leads = $sb->select('leads', ['limit' => 1, 'order' => 'created_at.desc']);
    echo "<p class='ok'>✅ Supabase connection OK</p>";
    echo "<p>Latest lead count query returned " . count($leads) . " row(s)</p>";
    
    if (!empty($leads)) {
        echo "<pre>Latest lead: " . htmlspecialchars($leads[0]['name'] ?? 'N/A') . " — " . htmlspecialchars($leads[0]['email'] ?? 'N/A') . " (" . htmlspecialchars($leads[0]['created_at'] ?? '') . ")</pre>";
    } else {
        echo "<p class='warn'>⚠️ No leads found in database — table may be empty or RLS is blocking reads</p>";
    }
    
    // Test insert/delete (non-destructive)
    echo "<p>Testing write access (insert + delete)...</p>";
    $testLead = $sb->insert('leads', [
        'name' => '__DEBUG_TEST__',
        'email' => 'debug@test.invalid',
        'company' => 'Debug Test',
        'message' => 'Automated test — safe to delete',
        'source' => 'other',
        'status' => 'new',
    ]);
    
    if ($testLead && isset($testLead['id'])) {
        echo "<p class='ok'>✅ Write access OK (inserted test lead ID: " . htmlspecialchars($testLead['id']) . ")</p>";
        // Clean up
        $deleted = $sb->delete('leads', $testLead['id']);
        echo "<p class='ok'>✅ Delete OK (cleaned up test lead)</p>";
    } else {
        echo "<p class='fail'>❌ Write FAILED — insert returned null</p>";
        echo "<p class='warn'>→ Check RLS policies: the service_role key should bypass RLS. Verify SUPABASE_SERVICE_KEY is correct.</p>";
    }
} catch (\Throwable $e) {
    echo "<p class='fail'>❌ Supabase FAILED: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// ============================================================================
// 4. OpenAI Connection Test
// ============================================================================
echo "<h2>4. OpenAI Connection Test</h2><div class='section'>";

try {
    require_once __DIR__ . '/admin/includes/openai.php';
    $ai = getOpenAI();
    
    if (empty($config['openai_api_key'])) {
        echo "<p class='warn'>⚠️ OPENAI_API_KEY not set — AI auto-reply will be skipped (emails still work)</p>";
    } else {
        $response = $ai->chat('You are a test assistant.', 'Say "OK" in one word.', 0.1);
        if ($response) {
            echo "<p class='ok'>✅ OpenAI API OK — Response: " . htmlspecialchars(substr($response, 0, 100)) . "</p>";
        } else {
            echo "<p class='fail'>❌ OpenAI returned empty response — API key may be invalid or expired</p>";
        }
    }
} catch (\Throwable $e) {
    echo "<p class='fail'>❌ OpenAI FAILED: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// ============================================================================
// 5. reCAPTCHA Configuration
// ============================================================================
echo "<h2>5. reCAPTCHA Configuration</h2><div class='section'>";

$siteKey = $config['recaptcha_site_key'] ?? '';
$secretKey = $config['recaptcha_secret_key'] ?? '';

if (empty($siteKey) || $siteKey === 'YOUR_RECAPTCHA_SITE_KEY') {
    echo "<p class='warn'>⚠️ reCAPTCHA site key not configured — verification will be skipped</p>";
} else {
    echo "<p class='ok'>✅ Site key configured: " . htmlspecialchars(substr($siteKey, 0, 20)) . "...</p>";
}

if (empty($secretKey) || $secretKey === 'YOUR_RECAPTCHA_SECRET_KEY') {
    echo "<p class='warn'>⚠️ reCAPTCHA secret key not configured — verification will be skipped</p>";
} else {
    echo "<p class='ok'>✅ Secret key configured: " . htmlspecialchars(substr($secretKey, 0, 20)) . "...</p>";
    echo "<p>Min score threshold: {$config['recaptcha_min_score']}</p>";
}
echo "</div>";

// ============================================================================
// 6. PHP Configuration
// ============================================================================
echo "<h2>6. PHP & Server Info</h2><div class='section'>";
echo "<pre>";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "cURL: " . (function_exists('curl_version') ? '✅ ' . curl_version()['version'] : '❌ Missing') . "\n";
echo "OpenSSL: " . (defined('OPENSSL_VERSION_TEXT') ? '✅ ' . OPENSSL_VERSION_TEXT : '❌ Missing') . "\n";
echo "PHPMailer: " . (class_exists('PHPMailer\PHPMailer\PHPMailer') ? '✅ Loaded' : '❌ Not found') . "\n";
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? '✅ On' : '⚠️ Off (reCAPTCHA may fail)') . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";
echo "</pre>";
echo "</div>";

echo "<p><br><span class='warn'>⚠️ DELETE this file after debugging: <code>rm test_contact_debug.php</code></span></p>";
echo "</body></html>";
