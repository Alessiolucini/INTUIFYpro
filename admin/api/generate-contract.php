<?php
/**
 * IntuiFy Admin — AI Contract Generation API
 * Called via fetch() from contracts.php — returns JSON.
 */
declare(strict_types=1);

// Must be first: allow up to 3 minutes for GPT-4o to generate
ini_set('max_execution_time', '180');
set_time_limit(180);

require_once dirname(__DIR__) . '/includes/auth.php';
requireAuth();
require_once dirname(__DIR__) . '/includes/supabase.php';
require_once dirname(__DIR__) . '/includes/openai.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    // Also accept form-encoded
    $input = $_POST;
}

$clientId    = trim($input['client_id']    ?? '');
$description = trim($input['description']  ?? '');
$contractNum = trim($input['contract_number'] ?? '');

if (empty($clientId) || empty($description)) {
    http_response_code(400);
    echo json_encode(['error' => 'client_id e description sono obbligatori']);
    exit;
}

$config = require dirname(__DIR__, 2) . '/config.php';
$sb     = getSupabase();
$ai     = getOpenAI();

$clientInfo  = $sb->find('clients', $clientId) ?? [];

if (empty($contractNum)) {
    // Generate contract number
    $year    = date('Y');
    $prefix  = $config['contract_prefix'] ?? 'CTR';
    $existing = $sb->select('contracts', [
        'select'  => 'contract_number',
        'filters' => ['contract_number' => 'like.' . $prefix . '-' . $year . '-%'],
        'order'   => 'contract_number.desc',
        'limit'   => 1,
    ]);
    $lastNum = 0;
    if (!empty($existing)) {
        $parts   = explode('-', $existing[0]['contract_number']);
        $lastNum = (int) end($parts);
    }
    $contractNum = $prefix . '-' . $year . '-' . str_pad((string)($lastNum + 1), 3, '0', STR_PAD_LEFT);
}

$result = $ai->generateContract($description, $config, $clientInfo, $contractNum);

if (!$result) {
    $errMsg = $ai->getLastError() ?? 'OpenAI non ha risposto. Riprova tra qualche secondo.';
    http_response_code(500);
    echo json_encode(['error' => $errMsg]);
    exit;
}

$result['contract_number'] = $contractNum;
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
