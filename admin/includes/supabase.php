<?php
/**
 * IntuiFy — Supabase REST API Client
 * Lightweight PHP wrapper for Supabase PostgREST + Storage APIs.
 */

declare(strict_types=1);

class SupabaseClient
{
    private string $url;
    private string $key;
    private string $serviceKey;

    public function __construct(array $config)
    {
        $this->url = rtrim($config['supabase_url'], '/');
        $this->key = $config['supabase_anon_key'];
        $this->serviceKey = $config['supabase_service_key'];
    }

    // =========================================================================
    // REST API (PostgREST)
    // =========================================================================

    /**
     * SELECT rows from a table.
     * @param string $table Table name
     * @param array $params Query parameters (select, order, limit, filters)
     * @return array
     */
    public function select(string $table, array $params = []): array
    {
        $query = [];
        
        if (isset($params['select'])) {
            $query['select'] = $params['select'];
        }
        if (isset($params['order'])) {
            $query['order'] = $params['order'];
        }
        if (isset($params['limit'])) {
            $query['limit'] = $params['limit'];
        }
        if (isset($params['offset'])) {
            $query['offset'] = $params['offset'];
        }
        
        // Filters (e.g., ['status' => 'eq.active', 'id' => 'eq.xxx'])
        if (isset($params['filters']) && is_array($params['filters'])) {
            foreach ($params['filters'] as $col => $filter) {
                $query[$col] = $filter;
            }
        }

        $url = $this->url . '/rest/v1/' . $table;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $response = $this->request('GET', $url);
        return $response['data'] ?? [];
    }

    /**
     * SELECT a single row by ID.
     */
    public function find(string $table, string $id, string $select = '*'): ?array
    {
        $rows = $this->select($table, [
            'select' => $select,
            'filters' => ['id' => 'eq.' . $id],
            'limit' => 1,
        ]);
        return $rows[0] ?? null;
    }

    /**
     * INSERT a row.
     */
    public function insert(string $table, array $data): ?array
    {
        $url = $this->url . '/rest/v1/' . $table;
        $response = $this->request('POST', $url, $data, [
            'Prefer: return=representation',
        ]);
        return $response['data'][0] ?? null;
    }

    /**
     * UPDATE a row by ID.
     */
    public function update(string $table, string $id, array $data): ?array
    {
        $url = $this->url . '/rest/v1/' . $table . '?id=eq.' . $id;
        $response = $this->request('PATCH', $url, $data, [
            'Prefer: return=representation',
        ]);
        return $response['data'][0] ?? null;
    }

    /**
     * DELETE a row by ID.
     */
    public function delete(string $table, string $id): bool
    {
        $url = $this->url . '/rest/v1/' . $table . '?id=eq.' . $id;
        $response = $this->request('DELETE', $url);
        return $response['status'] >= 200 && $response['status'] < 300;
    }

    /**
     * COUNT rows in a table with optional filters.
     */
    public function count(string $table, array $filters = []): int
    {
        $query = $filters;
        $query['select'] = 'count';

        $url = $this->url . '/rest/v1/' . $table . '?' . http_build_query($query);
        $response = $this->request('GET', $url, null, [
            'Prefer: count=exact',
        ]);

        // PostgREST returns count in content-range header
        if (isset($response['headers']['content-range'])) {
            $parts = explode('/', $response['headers']['content-range']);
            return (int) ($parts[1] ?? 0);
        }

        return count($response['data'] ?? []);
    }

    /**
     * Call an RPC function.
     */
    public function rpc(string $function, array $params = []): mixed
    {
        $url = $this->url . '/rest/v1/rpc/' . $function;
        $response = $this->request('POST', $url, $params);
        return $response['data'] ?? null;
    }

    // =========================================================================
    // Storage API
    // =========================================================================

    /**
     * Upload a file to Supabase Storage.
     */
    public function uploadFile(string $bucket, string $path, string $filePath, string $mimeType = 'application/octet-stream'): ?string
    {
        $url = $this->url . '/storage/v1/object/' . $bucket . '/' . $path;
        
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            return null;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fileContent,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $this->serviceKey,
                'Authorization: Bearer ' . $this->serviceKey,
                'Content-Type: ' . $mimeType,
                'x-upsert: true',
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return $this->url . '/storage/v1/object/public/' . $bucket . '/' . $path;
        }

        error_log("Supabase Storage upload failed: HTTP $httpCode — $response");
        return null;
    }

    /**
     * Get public URL for a stored file.
     */
    public function getFileUrl(string $bucket, string $path): string
    {
        return $this->url . '/storage/v1/object/public/' . $bucket . '/' . $path;
    }

    // =========================================================================
    // Internal HTTP Client
    // =========================================================================

    private function request(string $method, string $url, ?array $data = null, array $extraHeaders = []): array
    {
        $headers = array_merge([
            'apikey: ' . $this->serviceKey,
            'Authorization: Bearer ' . $this->serviceKey,
            'Content-Type: application/json',
        ], $extraHeaders);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HEADER => true,
        ]);

        if ($data !== null && in_array($method, ['POST', 'PATCH', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Supabase cURL error: $error");
            return ['status' => 0, 'data' => null, 'error' => $error, 'headers' => []];
        }

        $headerStr = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        // Parse response headers
        $responseHeaders = [];
        foreach (explode("\r\n", $headerStr) as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $responseHeaders[strtolower(trim($key))] = trim($value);
            }
        }

        $decoded = json_decode($body, true);

        if ($httpCode >= 400) {
            $msg = $decoded['message'] ?? $decoded['error'] ?? $body;
            error_log("Supabase API error ($httpCode): $msg — URL: $url");
        }

        return [
            'status' => $httpCode,
            'data' => $decoded,
            'headers' => $responseHeaders,
        ];
    }
}

/**
 * Get singleton Supabase client instance.
 */
function getSupabase(): SupabaseClient
{
    static $client = null;
    if ($client === null) {
        $config = require dirname(__DIR__, 2) . '/config.php';
        $client = new SupabaseClient($config);
    }
    return $client;
}
