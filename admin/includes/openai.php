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

    public function __construct(array $config)
    {
        $this->apiKey = $config['openai_api_key'] ?? '';
        $this->model = $config['openai_model'] ?? 'gpt-4o';
        $this->visionModel = $config['openai_vision_model'] ?? 'gpt-4o';
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
    private function request(string $url, array $payload): ?array
    {
        if (empty($this->apiKey)) {
            error_log('OpenAI: API key not configured');
            return null;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("OpenAI cURL error: {$error}");
            return null;
        }

        if ($httpCode !== 200) {
            error_log("OpenAI API error (HTTP {$httpCode}): {$response}");
            return null;
        }

        return json_decode($response, true);
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
