<?php
/**
 * IntuiFy Admin — Diagnostics
 * Tests Supabase and OpenAI connectivity. DELETE after use.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/supabase.php';
require_once __DIR__ . '/includes/openai.php';

$config = require dirname(__DIR__) . '/config.php';
$sb = getSupabase();
$ai = getOpenAI();

header('Content-Type: text/html; charset=UTF-8');

function ok(string $msg): string  { return "<span style='color:#4ade80'>✓ $msg</span>"; }
function err(string $msg): string { return "<span style='color:#f87171'>✗ $msg</span>"; }
function row(string $label, string $val): string {
    return "<tr><td style='padding:6px 12px;color:#94a3b8;min-width:200px'>$label</td><td style='padding:6px 12px;color:#e2e8f0'>$val</td></tr>";
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Diagnostics — IntuiFy</title>
<style>
  body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:40px;font-size:14px}
  h2{color:#818cf8;border-bottom:1px solid #1e293b;padding-bottom:8px;margin-top:32px}
  table{border-collapse:collapse;width:100%;margin-bottom:16px}
  td{border:1px solid #1e293b}
  pre{background:#1e293b;padding:16px;border-radius:8px;overflow:auto;max-height:300px;color:#94a3b8;font-size:12px}
  .box{background:#1e293b;border-radius:12px;padding:24px;margin-bottom:24px}
</style>
</head>
<body>
<h1 style="color:#6366f1">⚙ IntuiFy Diagnostics</h1>
<p style="color:#64748b">Pagina temporanea — eliminare dopo l'uso.</p>

<?php

// ──────────────────────────────────────────────────────────────
// 1. CONFIG CHECK
// ──────────────────────────────────────────────────────────────
echo '<h2>1. Configurazione</h2><div class="box"><table>';
$supabaseUrl     = $config['supabase_url'] ?? '';
$supabaseAnon    = $config['supabase_anon_key'] ?? '';
$supabaseService = $config['supabase_service_key'] ?? '';
$openaiKey       = $config['openai_api_key'] ?? '';
$iban            = $config['company_iban'] ?? '';

echo row('SUPABASE_URL',         $supabaseUrl ?: err('MANCANTE'));
echo row('SUPABASE_ANON_KEY',    $supabaseAnon    ? ok(substr($supabaseAnon,0,20).'…')    : err('MANCANTE'));
echo row('SUPABASE_SERVICE_KEY', $supabaseService ? ok(substr($supabaseService,0,20).'…') : err('MANCANTE'));
echo row('OPENAI_API_KEY',       $openaiKey       ? ok(substr($openaiKey,0,10).'…')        : err('MANCANTE — AI non funziona'));
echo row('COMPANY_IBAN',         $iban ?: '<span style="color:#fbbf24">⚠ non impostato (opzionale)</span>');
echo '</table></div>';

// ──────────────────────────────────────────────────────────────
// 2. SUPABASE — SELECT clients
// ──────────────────────────────────────────────────────────────
echo '<h2>2. Supabase — SELECT clients</h2><div class="box">';
$clients = $sb->select('clients', ['select' => 'id,company_name', 'limit' => 3]);
if (empty($clients)) {
    echo err('Nessun cliente trovato (tabella vuota o errore connessione)');
} else {
    echo ok(count($clients) . ' cliente/i trovati');
    echo '<pre>' . htmlspecialchars(json_encode($clients, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) . '</pre>';
}
echo '</div>';

// ──────────────────────────────────────────────────────────────
// 3. SUPABASE — SELECT subscriptions
// ──────────────────────────────────────────────────────────────
echo '<h2>3. Supabase — SELECT subscriptions</h2><div class="box">';
$subs = $sb->select('subscriptions', ['select' => 'id,plan_name,status', 'limit' => 3]);
if (empty($subs)) {
    echo '<span style="color:#fbbf24">⚠ Nessun abbonamento — tabella vuota o errore</span>';
} else {
    echo ok(count($subs) . ' abbonamento/i trovati');
    echo '<pre>' . htmlspecialchars(json_encode($subs, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) . '</pre>';
}
echo '</div>';

// ──────────────────────────────────────────────────────────────
// 4. SUPABASE — TEST INSERT subscriptions
// ──────────────────────────────────────────────────────────────
echo '<h2>4. Supabase — TEST INSERT subscriptions</h2><div class="box">';

// Get first client id for test
$firstClient = !empty($clients) ? $clients[0]['id'] : null;

if (!$firstClient) {
    echo err('Nessun cliente disponibile per il test INSERT. Crea prima un cliente.');
} else {
    // Try a raw cURL to see the real response
    $testUrl = rtrim($supabaseUrl, '/') . '/rest/v1/subscriptions';
    $testData = [
        'client_id'     => $firstClient,
        'plan_name'     => '__DIAG_TEST__',
        'amount'        => 1.00,
        'currency'      => 'EUR',
        'billing_cycle' => 'monthly',
        'auto_renew'    => true,
        'status'        => 'active',
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $testUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => json_encode($testData),
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . $supabaseService,
            'Authorization: Bearer ' . $supabaseService,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HEADER         => true,
    ]);
    $raw = curl_exec($ch);
    $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $curlErr    = curl_error($ch);
    curl_close($ch);

    $responseBody = substr($raw, $headerSize);

    echo '<table>';
    echo row('HTTP Status', $httpCode >= 200 && $httpCode < 300
        ? ok((string)$httpCode)
        : err((string)$httpCode . ' — ' . $responseBody));
    if ($curlErr) echo row('cURL Error', err($curlErr));
    echo '</table>';
    echo '<pre>' . htmlspecialchars($responseBody) . '</pre>';

    // If insert succeeded, delete the test row
    if ($httpCode >= 200 && $httpCode < 300) {
        $inserted = json_decode($responseBody, true);
        $testId = $inserted[0]['id'] ?? null;
        if ($testId) {
            $delUrl = rtrim($supabaseUrl, '/') . '/rest/v1/subscriptions?id=eq.' . $testId;
            $chDel = curl_init();
            curl_setopt_array($chDel, [
                CURLOPT_URL            => $delUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'DELETE',
                CURLOPT_HTTPHEADER     => [
                    'apikey: ' . $supabaseService,
                    'Authorization: Bearer ' . $supabaseService,
                ],
                CURLOPT_TIMEOUT => 10,
            ]);
            curl_exec($chDel);
            curl_close($chDel);
            echo ok('Riga di test eliminata (id: ' . $testId . ')');
        }
    }
}
echo '</div>';

// ──────────────────────────────────────────────────────────────
// 5. OPENAI — connectivity test
// ──────────────────────────────────────────────────────────────
echo '<h2>5. OpenAI — Test connessione</h2><div class="box">';
if (empty($openaiKey)) {
    echo err('OPENAI_API_KEY non configurata in Dokploy → OpenAI non funzionerà mai');
} else {
    $testUrl = 'https://api.openai.com/v1/models';
    $chOai = curl_init();
    curl_setopt_array($chOai, [
        CURLOPT_URL            => $testUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $openaiKey],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $oaiResp = curl_exec($chOai);
    $oaiCode = curl_getinfo($chOai, CURLINFO_HTTP_CODE);
    $oaiErr  = curl_error($chOai);
    curl_close($chOai);

    if ($oaiErr) {
        echo err('cURL error: ' . $oaiErr);
    } elseif ($oaiCode === 200) {
        echo ok('Connessione OpenAI OK (HTTP 200)');
        // Check if gpt-4o is available
        $models = json_decode($oaiResp, true);
        $ids = array_column($models['data'] ?? [], 'id');
        $modelToUse = $config['openai_model'] ?? 'gpt-4o';
        echo '<br><table>';
        echo row('Modello configurato', $modelToUse);
        echo row('Modello disponibile', in_array($modelToUse, $ids)
            ? ok('Sì')
            : err('NO — modello non trovato nell\'account. Modelli disponibili: ' . implode(', ', array_slice($ids, 0, 5))));
        echo '</table>';
    } else {
        $decoded = json_decode($oaiResp, true);
        $errMsg = $decoded['error']['message'] ?? $oaiResp;
        echo err("HTTP $oaiCode: $errMsg");
    }
}
echo '</div>';

// ──────────────────────────────────────────────────────────────
// 6. PHP info
// ──────────────────────────────────────────────────────────────
echo '<h2>6. PHP Environment</h2><div class="box"><table>';
echo row('PHP Version',          phpversion());
echo row('max_execution_time',   ini_get('max_execution_time') . 's');
echo row('curl extension',       function_exists('curl_init') ? ok('disponibile') : err('MANCANTE'));
echo row('json extension',       function_exists('json_encode') ? ok('disponibile') : err('MANCANTE'));
echo '</table></div>';
?>

</body>
</html>
