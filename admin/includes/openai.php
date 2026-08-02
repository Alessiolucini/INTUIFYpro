<?php
/**
 * IntuiFy — OpenAI API Client
 * Provides chat completion and vision capabilities for AI assistants.
 */

declare(strict_types=1);

class OpenAIClient
{
    private string $apiKey;
    private string $model;
    private string $visionModel;
    private ?string $lastError = null;

    public function __construct(array $config)
    {
        $this->apiKey       = $config['openai_api_key'] ?? '';
        $this->model        = $config['openai_model'] ?? 'gpt-4o';
        $this->visionModel  = $config['openai_vision_model'] ?? 'gpt-4o';
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Chat completion — text only.
     * 
     * @param string $systemPrompt System instructions
     * @param string $userMessage  User's message
     * @param float  $temperature  Creativity (0.0 = deterministic, 1.0 = creative)
     * @return string|null Response text or null on error
     */
    public function chat(string $systemPrompt, string $userMessage, float $temperature = 0.7): ?string
    {
        $payload = [
            'model' => $this->model,
            'temperature' => $temperature,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
        ];

        $response = $this->request('https://api.openai.com/v1/chat/completions', $payload);
        return $response['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Multi-turn chat completion.
     * 
     * @param string $systemPrompt System instructions
     * @param array  $messages     Array of ['role' => ..., 'content' => ...]
     * @param float  $temperature  Creativity
     * @return string|null Response text or null on error
     */
    public function chatMultiTurn(string $systemPrompt, array $messages, float $temperature = 0.7): ?string
    {
        $allMessages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages
        );

        $payload = [
            'model' => $this->model,
            'temperature' => $temperature,
            'messages' => $allMessages,
        ];

        $response = $this->request('https://api.openai.com/v1/chat/completions', $payload);
        return $response['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Vision — analyze an image (base64 or URL).
     * 
     * @param string $imageData  Base64-encoded image data OR a URL
     * @param string $prompt     What to extract/analyze
     * @param string $mimeType   Image MIME type (e.g., image/jpeg)
     * @return string|null Response text or null on error
     */
    public function vision(string $imageData, string $prompt, string $mimeType = 'image/jpeg'): ?string
    {
        // Determine if it's a URL or base64
        if (filter_var($imageData, FILTER_VALIDATE_URL)) {
            $imageContent = ['type' => 'image_url', 'image_url' => ['url' => $imageData]];
        } else {
            $dataUri = "data:{$mimeType};base64,{$imageData}";
            $imageContent = ['type' => 'image_url', 'image_url' => ['url' => $dataUri]];
        }

        $payload = [
            'model' => $this->visionModel,
            'temperature' => 0.2,
            'max_tokens' => 2000,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        $imageContent,
                    ],
                ],
            ],
        ];

        $response = $this->request('https://api.openai.com/v1/chat/completions', $payload);
        return $response['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Vision with JSON output — analyze an image and return structured data.
     */
    public function visionJson(string $imageData, string $prompt, string $mimeType = 'image/jpeg'): ?array
    {
        $jsonPrompt = $prompt . "\n\nRispondi SOLO con un JSON valido, senza markdown, senza ```json, senza commenti.";
        $response = $this->vision($imageData, $jsonPrompt, $mimeType);
        
        if (!$response) return null;
        
        // Clean up possible markdown wrapping
        $response = trim($response);
        $response = preg_replace('/^```json\s*/i', '', $response);
        $response = preg_replace('/\s*```$/', '', $response);
        
        $data = json_decode($response, true);
        return $data ?: null;
    }

    /**
     * Send request to OpenAI API.
     */
    private function request(string $url, array $payload, int $timeout = 60): ?array
    {
        $this->lastError = null;

        if (empty($this->apiKey)) {
            $this->lastError = 'Chiave API OpenAI non configurata. Imposta OPENAI_API_KEY in Dokploy.';
            error_log('OpenAI: API key not configured');
            return null;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->lastError = 'Errore di rete: ' . $error;
            error_log("OpenAI cURL error: {$error}");
            return null;
        }

        if ($httpCode !== 200) {
            $decoded = json_decode($response, true);
            $msg = $decoded['error']['message'] ?? $response;
            $this->lastError = "OpenAI HTTP {$httpCode}: " . $msg;
            error_log("OpenAI API error (HTTP {$httpCode}): {$response}");
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Generate a professional Italian business contract via AI.
     *
     * @param string $userDescription  Free-text description of the contract scope
     * @param array  $companyInfo      Company config fields (company_legal_name, company_vat, company_address, company_email, company_iban)
     * @param array  $clientInfo       Client data (company_name, vat_number, address, contact_person)
     * @param string $contractNumber   Pre-generated contract number (e.g. CTR-2026-001)
     * @return array|null Structured contract data or null on failure
     *   Keys: title, amount, currency, start_date, end_date, billing_cycle, payment_terms, clauses[]
     */
    public function generateContract(
        string $userDescription,
        array  $companyInfo,
        array  $clientInfo,
        string $contractNumber
    ): ?array {
        $companyName  = $companyInfo['company_legal_name'] ?? 'IntuiFy Ventures SL';
        $companyVat   = $companyInfo['company_vat'] ?? '';
        $companyAddr  = $companyInfo['company_address'] ?? '';
        $companyEmail = $companyInfo['company_email'] ?? '';
        $companyIban  = $companyInfo['company_iban'] ?? '';

        $clientName   = $clientInfo['company_name'] ?? '';
        $clientVat    = $clientInfo['vat_number'] ?? '';
        $clientAddr   = $clientInfo['address'] ?? '';
        $clientPerson = $clientInfo['contact_person'] ?? '';

        $today = date('d/m/Y');

        $systemPrompt = <<<SYSTEM
Sei un esperto legale specializzato nella redazione di contratti di servizi digitali e SaaS per aziende europee.
Generi contratti professionali, completi e vincolanti in italiano, adatti ad essere firmati e consegnati ai clienti.

AZIENDA FORNITRICE (Prestatore):
- Ragione Sociale: {$companyName}
- CIF/P.IVA: {$companyVat}
- Indirizzo: {$companyAddr}
- Email: {$companyEmail}
- IBAN per pagamenti: {$companyIban}

Regole assolute:
1. Rispondi SOLO con un oggetto JSON valido, senza markdown, senza ```json, senza testo prima o dopo.
2. Il contratto deve essere professionale, completo e legalmente solido.
3. Usa un linguaggio formale e preciso in italiano.
4. Include SEMPRE tutte le sezioni obbligatorie elencate sotto.
5. Personalizza le clausole in base alla descrizione fornita dall'utente.
6. L'IBAN da usare nelle condizioni di pagamento è quello del Prestatore indicato sopra.

Struttura JSON obbligatoria:
{
  "title": "string — titolo contratto (es. Contratto di Servizi SaaS)",
  "amount": number — importo numerico in EUR,
  "billing_cycle": "monthly|quarterly|semiannual|annual|one_time",
  "start_date": "YYYY-MM-DD",
  "end_date": "YYYY-MM-DD or null",
  "payment_terms": "string — paragrafo completo sulle modalità di pagamento, incluso IBAN, intestatario, causale suggerita, scadenza mensile",
  "clauses": [
    {
      "number": 1,
      "title": "Oggetto del Contratto",
      "text": "string — testo completo della clausola"
    },
    {
      "number": 2,
      "title": "Durata",
      "text": "..."
    },
    {
      "number": 3,
      "title": "Corrispettivo e Modalità di Pagamento",
      "text": "..."
    },
    {
      "number": 4,
      "title": "Obblighi del Prestatore",
      "text": "..."
    },
    {
      "number": 5,
      "title": "Obblighi del Cliente",
      "text": "..."
    },
    {
      "number": 6,
      "title": "Riservatezza e Protezione dei Dati",
      "text": "..."
    },
    {
      "number": 7,
      "title": "Limitazione di Responsabilità",
      "text": "..."
    },
    {
      "number": 8,
      "title": "Risoluzione Anticipata",
      "text": "..."
    },
    {
      "number": 9,
      "title": "Proprietà Intellettuale",
      "text": "..."
    },
    {
      "number": 10,
      "title": "Disposizioni Finali e Foro Competente",
      "text": "..."
    }
  ]
}
SYSTEM;

        $userMessage = <<<MSG
Genera un contratto professionale con i seguenti dati:

NUMERO CONTRATTO: {$contractNumber}
DATA ODIERNA: {$today}

CLIENTE (Committente):
- Ragione Sociale: {$clientName}
- P.IVA/CIF: {$clientVat}
- Indirizzo: {$clientAddr}
- Referente: {$clientPerson}

DESCRIZIONE DEL CONTRATTO (fornita dall'utente):
{$userDescription}

Genera il contratto completo rispettando esattamente la struttura JSON richiesta.
MSG;

        // Use request() directly to allow a longer timeout (120s)
        // Large contract generation can take 60-90s with GPT-4o
        @set_time_limit(180);

        $payload = [
            'model'       => $this->model,
            'temperature' => 0.3,
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userMessage],
            ],
        ];

        $response = $this->request('https://api.openai.com/v1/chat/completions', $payload, 120);
        if (!$response) {
            // lastError already set by request()
            return null;
        }

        $raw = $response['choices'][0]['message']['content'] ?? null;
        if (!$raw) {
            $this->lastError = 'Risposta vuota da OpenAI.';
            return null;
        }

        // Strip possible markdown wrapping
        $raw = trim($raw);
        $raw = preg_replace('/^```json\s*/i', '', $raw);
        $raw = preg_replace('/^```\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['clauses'])) {
            $this->lastError = 'Il modello non ha restituito JSON valido. Riprova.';
            error_log('OpenAI generateContract: invalid JSON — ' . substr($raw, 0, 500));
            return null;
        }

        return $data;
    }
}

/**
 * Factory: get a configured OpenAI client.
 */
function getOpenAI(): OpenAIClient
{
    static $client = null;
    if ($client === null) {
        $config = require dirname(__DIR__, 2) . '/config.php';
        $client = new OpenAIClient($config);
    }
    return $client;
}
