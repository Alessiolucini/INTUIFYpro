<?php
/**
 * IntuiFy Admin — Step 2: Generate professional contract from description + Q&A answers.
 * Returns structured JSON with title, clauses[], payment_terms, etc.
 */
declare(strict_types=1);

ini_set('max_execution_time', '180');
set_time_limit(180);

require_once dirname(__DIR__) . '/includes/auth.php';
requireAuth();
require_once dirname(__DIR__) . '/includes/supabase.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$clientId    = trim($_POST['client_id']    ?? '');
$description = trim($_POST['description']  ?? '');
$answers     = trim($_POST['answers']      ?? '{}'); // JSON string of Q&A
$contractNum = trim($_POST['contract_number'] ?? '');

if (empty($clientId) || empty($description)) {
    http_response_code(400);
    echo json_encode(['error' => 'client_id e description sono obbligatori']);
    exit;
}

$config     = require dirname(__DIR__, 2) . '/config.php';
$sb         = getSupabase();
$clientInfo = $sb->find('clients', $clientId) ?? [];

// Generate contract number if not provided
if (empty($contractNum)) {
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

// Parse answers JSON
$answersData = json_decode($answers, true) ?? [];

// Build Q&A text for the prompt
$qaText = '';
if (!empty($answersData)) {
    $qaText = "\n\nRISPOSTE ALLE DOMANDE DI APPROFONDIMENTO:\n";
    foreach ($answersData as $qid => $answer) {
        if (trim($answer) !== '') {
            $qaText .= "- {$qid}: {$answer}\n";
        }
    }
}

// Company info
$companyName  = $config['company_legal_name'] ?? 'IntuiFy Ventures SL';
$companyVat   = $config['company_vat']        ?? '';
$companyAddr  = $config['company_address']    ?? '';
$companyEmail = $config['company_email']      ?? '';
$companyIban  = $config['company_iban']       ?? '';
$companyRea   = $config['company_rea']        ?? '';

$clientName   = $clientInfo['company_name']    ?? '';
$clientVat    = $clientInfo['vat_number']      ?? '';
$clientAddr   = $clientInfo['address']         ?? '';
$clientPerson = $clientInfo['contact_person']  ?? '';
$clientEmail  = $clientInfo['email']           ?? '';

$today      = date('d/m/Y');
$todayISO   = date('Y-m-d');
$apiKey     = $config['openai_api_key'] ?? '';
$model      = $config['openai_model']   ?? 'gpt-4o';

if (empty($apiKey)) {
    http_response_code(500);
    echo json_encode(['error' => 'Chiave API OpenAI non configurata. Imposta OPENAI_API_KEY in Dokploy.']);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// SYSTEM PROMPT — Avvocato senior IT/SaaS
// ══════════════════════════════════════════════════════════════════════════════
$systemPrompt = <<<SYSTEM
Sei un avvocato d'affari senior con 20 anni di esperienza nella redazione di contratti commerciali complessi in ambito IT, SaaS e consulenza tecnologica, sia in Italia che in Spagna. Hai lavorato per importanti studi legali e per aziende tech. I tuoi contratti sono noti per essere precisi, bilanciati, legalmente robusti e immediatamente firmabili.

DATI DEL PRESTATORE (tuo cliente che ti ha incaricato):
- Ragione Sociale: {$companyName}
- CIF/P.IVA: {$companyVat}
- Sede Legale: {$companyAddr}
- Email: {$companyEmail}
- IBAN per pagamenti: {$companyIban}

REGOLE ASSOLUTE — rispetta ogni punto:

1. FORMATO: Rispondi ESCLUSIVAMENTE con un oggetto JSON valido. Zero testo prima o dopo. Zero markdown. Zero ```json.

2. QUALITÀ LEGALE:
   - Ogni clausola deve essere sostanzialmente diversa dalle altre (nessuna sovrapposizione concettuale)
   - Usa terminologia giuridica precisa: "Parti", "Inadempimento", "Recesso", "Penale convenzionale", "Clausola risolutiva espressa", "Foro competente", "Legge applicabile", ecc.
   - Includi riferimenti normativi pertinenti (es. art. 1456 c.c., GDPR Reg. UE 2016/679, D.Lgs. 196/2003, ecc.)
   - Le clausole devono essere dettagliate: minimo 3-5 frasi complete per clausola, non una sola frase generica
   - Ogni obbligazione deve avere la sua conseguenza per inadempimento

3. STRUTTURA OBBLIGATORIA — esattamente queste sezioni, nell'ordine, ciascuna unica:
   - Art. 1: Definizioni e Premesse (definire i termini tecnici usati nel contratto)
   - Art. 2: Oggetto del Contratto (descrizione dettagliata del servizio/prodotto specifico)
   - Art. 3: Durata e Decorrenza (con gestione del rinnovo automatico se previsto)
   - Art. 4: Corrispettivo, Fatturazione e Modalità di Pagamento (importo, scadenze, interessi di mora ex D.Lgs. 231/2002)
   - Art. 5: Obbligazioni e Livelli di Servizio del Prestatore (SLA specifici, uptime, tempi di risposta)
   - Art. 6: Obbligazioni del Committente (cosa deve fare il cliente, credenziali, dati, cooperazione)
   - Art. 7: Proprietà Intellettuale e Licenza d'Uso (software, dati, output — chi possiede cosa)
   - Art. 8: Riservatezza e NDA (durata post-contratto, definizione di informazioni riservate, eccezioni)
   - Art. 9: Protezione dei Dati Personali (ruoli GDPR: titolare/responsabile, DPA se necessario)
   - Art. 10: Limitazione di Responsabilità e Garanzie (cap, esclusioni, forza maggiore dettagliata)
   - Art. 11: Inadempimento, Penali e Risoluzione (clausola risolutiva espressa, penali specifiche, diffida)
   - Art. 12: Recesso (termini di preavviso, conseguenze economiche, dati post-recesso)
   - Art. 13: Disposizioni Generali (comunicazioni, cessione, intero accordo, nullità parziale)
   - Art. 14: Legge Applicabile e Foro Competente (giurisdizione, mediazione obbligatoria se applicabile)

4. PAGAMENTO: La sezione payment_terms nel JSON deve contenere il testo completo e specifico per il pagamento con IBAN, causale, scadenze e interessi di mora. Non ripetere questo nell'Art. 4.

5. JSON STRUCTURE OBBLIGATORIA:
{
  "title": "Contratto di [tipo specifico]",
  "amount": 0.00,
  "billing_cycle": "monthly|quarterly|annual|one_time",
  "start_date": "YYYY-MM-DD",
  "end_date": "YYYY-MM-DD o null se open-ended",
  "payment_terms": "Testo completo con IBAN, scadenze, interessi mora...",
  "clauses": [
    {
      "number": 1,
      "title": "Titolo dell'articolo",
      "text": "Testo completo della clausola (minimo 80 parole, massimo 250 parole, specifico e non generico)"
    }
  ]
}
SYSTEM;

// ══════════════════════════════════════════════════════════════════════════════
// USER MESSAGE
// ══════════════════════════════════════════════════════════════════════════════
$userMessage = <<<MSG
NUMERO CONTRATTO: {$contractNum}
DATA ODIERNA: {$today}
DATA INIZIO CONTRATTO: {$todayISO}

COMMITTENTE (Cliente):
- Ragione Sociale: {$clientName}
- P.IVA/CIF: {$clientVat}
- Sede: {$clientAddr}
- Referente: {$clientPerson}
- Email: {$clientEmail}

DESCRIZIONE DEL CONTRATTO:
{$description}{$qaText}

Redigi il contratto completo seguendo TUTTE le regole del sistema. Ogni articolo deve essere sostanzialmente diverso dagli altri, specifico per questo contratto, e contenere obbligazioni concrete con relative conseguenze per inadempimento.
MSG;

// ══════════════════════════════════════════════════════════════════════════════
// API CALL
// ══════════════════════════════════════════════════════════════════════════════
$payload = [
    'model'       => $model,
    'temperature' => 0.25,
    'max_tokens'  => 4096,
    'messages'    => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user',   'content' => $userMessage],
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
    CURLOPT_TIMEOUT        => 150,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore di rete: ' . $curlErr]);
    exit;
}

if ($httpCode !== 200) {
    $decoded = json_decode($response, true);
    $msg = $decoded['error']['message'] ?? $response;
    http_response_code(500);
    echo json_encode(['error' => "OpenAI HTTP {$httpCode}: {$msg}"]);
    exit;
}

$decoded = json_decode($response, true);
$raw     = $decoded['choices'][0]['message']['content'] ?? '';

if (empty($raw)) {
    http_response_code(500);
    echo json_encode(['error' => 'Risposta vuota da OpenAI. Riprova.']);
    exit;
}

// Strip markdown code fences if any
$raw = trim($raw);
$raw = preg_replace('/^```json\s*/i', '', $raw);
$raw = preg_replace('/^```\s*/i', '', $raw);
$raw = preg_replace('/\s*```$/', '', $raw);

$data = json_decode($raw, true);

if (!is_array($data) || empty($data['clauses'])) {
    error_log('generate-contract: invalid JSON — ' . substr($raw, 0, 800));
    http_response_code(500);
    echo json_encode(['error' => 'Il modello non ha restituito un contratto valido. Riprova con una descrizione più dettagliata.']);
    exit;
}

// Remove duplicate clause numbers (safety)
$seen = [];
$data['clauses'] = array_values(array_filter($data['clauses'], function ($c) use (&$seen) {
    $n = (int)($c['number'] ?? 0);
    if (isset($seen[$n])) return false;
    $seen[$n] = true;
    return true;
}));

$data['contract_number'] = $contractNum;
$data['generated_at']    = date('c');

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
