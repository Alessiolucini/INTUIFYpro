<?php
/**
 * IntuiFy Admin — Step 1: Generate targeted questions before drafting the contract.
 * Returns JSON: { questions: [{ id, question, type, placeholder, required }] }
 */
declare(strict_types=1);

ini_set('max_execution_time', '60');
set_time_limit(60);

require_once dirname(__DIR__) . '/includes/auth.php';
requireAuth();
require_once dirname(__DIR__) . '/includes/supabase.php';
require_once dirname(__DIR__) . '/includes/openai.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input       = $_POST;
$clientId    = trim($input['client_id']   ?? '');
$description = trim($input['description'] ?? '');

if (empty($clientId) || empty($description)) {
    http_response_code(400);
    echo json_encode(['error' => 'client_id e description sono obbligatori']);
    exit;
}

$config     = require dirname(__DIR__, 2) . '/config.php';
$sb         = getSupabase();
$ai         = getOpenAI();
$clientInfo = $sb->find('clients', $clientId) ?? [];
$clientName = $clientInfo['company_name'] ?? 'il Cliente';

// ── System prompt ─────────────────────────────────────────────────────────────
$system = <<<SYSTEM
Sei un avvocato d'affari senior specializzato in contratti IT/SaaS/consulenza, con 20 anni di esperienza nella redazione di contratti commerciali in Italia e Spagna.

Il tuo compito in questo momento è SOLO analizzare la descrizione di un contratto che ti verrà fornita e generare una lista di 6-8 domande mirate e specifiche per raccogliere tutte le informazioni necessarie a redigere un contratto professionale, completo e privo di ambiguità.

Le domande devono:
- Essere specifiche, non generiche
- Coprire aspetti critici che spesso mancano (SLA, penali, riservatezza, recesso, proprietà intellettuale, GDPR, ecc.)
- Essere formulate in italiano professionale
- Evitare domande la cui risposta sia già evidente dalla descrizione

Rispondi ESCLUSIVAMENTE con un oggetto JSON valido, senza testo aggiuntivo:
{
  "questions": [
    {
      "id": "identificatore_snake_case",
      "question": "Testo della domanda",
      "type": "text|textarea|number|select",
      "placeholder": "Testo di esempio o guida",
      "options": ["opzione1", "opzione2"],  // solo per type=select
      "required": true
    }
  ]
}
SYSTEM;

$userMsg = <<<MSG
CLIENTE: {$clientName}
DESCRIZIONE DEL CONTRATTO:
{$description}

Genera le domande necessarie per redigere un contratto professionale completo.
MSG;

$result = $ai->generateContract($userMsg, [], [], '');  // piggyback the chat method
// Actually use chat directly:
$questions = null;
if (method_exists($ai, 'chat')) {
    $raw = $ai->chat($system, $userMsg, 0.2);
    if ($raw) {
        $raw = trim(preg_replace('/^```json\s*/i', '', preg_replace('/\s*```$/', '', trim($raw))));
        $questions = json_decode($raw, true);
    }
}

// Fallback: use reflection to call request() or use a workaround
if (!$questions) {
    // Direct OpenAI call
    $apiKey = $config['openai_api_key'] ?? '';
    $model  = $config['openai_model'] ?? 'gpt-4o';

    if (empty($apiKey)) {
        http_response_code(500);
        echo json_encode(['error' => 'Chiave API OpenAI non configurata in Dokploy.']);
        exit;
    }

    $payload = [
        'model'       => $model,
        'temperature' => 0.2,
        'messages'    => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $userMsg],
        ],
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://api.openai.com/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_TIMEOUT        => 45,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $httpCode !== 200) {
        $decoded = json_decode($response, true);
        $msg = $decoded['error']['message'] ?? ($curlErr ?: "HTTP $httpCode");
        http_response_code(500);
        echo json_encode(['error' => 'OpenAI error: ' . $msg]);
        exit;
    }

    $decoded = json_decode($response, true);
    $raw = $decoded['choices'][0]['message']['content'] ?? '';
    $raw = trim(preg_replace('/^```json\s*/i', '', preg_replace('/\s*```$/', '', trim($raw))));
    $questions = json_decode($raw, true);
}

if (!is_array($questions) || empty($questions['questions'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Impossibile generare le domande. Riprova.']);
    exit;
}

echo json_encode($questions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
