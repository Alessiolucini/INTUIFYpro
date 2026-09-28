<?php
/**
 * IntuiFy — Prometheus HTTP API client (Server Control Center)
 * Talks to Prometheus over the internal Docker network only. Never expose its URL to the browser.
 */

declare(strict_types=1);

/**
 * Thrown when Prometheus cannot answer. The UI must show "Monitoring unavailable",
 * never "server offline". `reason` is a safe code for the browser; details go to the log.
 */
final class MonitoringUnavailable extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $detail = '')
    {
        parent::__construct($detail !== '' ? $detail : $reason);
    }
}

class PrometheusClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct(string $baseUrl, int $timeout = 5)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = max(1, $timeout);
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '';
    }

    /**
     * Instant query → list of ['metric' => labels, 'value' => float|null].
     */
    public function query(string $promql): array
    {
        $data = $this->get('/api/v1/query', ['query' => $promql]);
        $out = [];
        foreach ($data['result'] ?? [] as $row) {
            $out[] = [
                'metric' => $row['metric'] ?? [],
                'value'  => self::toFloat($row['value'][1] ?? null),
            ];
        }
        return $out;
    }

    /**
     * First value of an instant query, or null when there is no sample.
     */
    public function scalar(string $promql): ?float
    {
        $rows = $this->query($promql);
        return $rows[0]['value'] ?? null;
    }

    /**
     * Range query → list of ['metric' => labels, 'values' => [[ts, float|null], …]].
     */
    public function queryRange(string $promql, int $start, int $end, int $step): array
    {
        $data = $this->get('/api/v1/query_range', [
            'query' => $promql,
            'start' => $start,
            'end'   => $end,
            'step'  => max(1, $step),
        ]);
        $out = [];
        foreach ($data['result'] ?? [] as $row) {
            $values = [];
            foreach ($row['values'] ?? [] as [$ts, $v]) {
                $values[] = [(int) $ts, self::toFloat($v)];
            }
            $out[] = ['metric' => $row['metric'] ?? [], 'values' => $values];
        }
        return $out;
    }

    /** Active alerts (firing + pending) as returned by /api/v1/alerts. */
    public function alerts(): array
    {
        return $this->get('/api/v1/alerts')['alerts'] ?? [];
    }

    /** Active scrape targets as returned by /api/v1/targets. */
    public function targets(): array
    {
        return $this->get('/api/v1/targets', ['state' => 'active'])['activeTargets'] ?? [];
    }

    private function get(string $path, array $params = []): array
    {
        if (!$this->isConfigured()) {
            throw new MonitoringUnavailable('not_configured', 'PROMETHEUS_URL is not set');
        }

        $url = $this->baseUrl . $path . ($params ? '?' . http_build_query($params) : '');
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min(3, $this->timeout),
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);

        if ($body === false || $error !== '') {
            throw new MonitoringUnavailable('unreachable', "Prometheus cURL error on {$path}: {$error}");
        }

        $json = json_decode((string) $body, true);
        if (!is_array($json) || ($json['status'] ?? '') !== 'success') {
            $detail = is_array($json) ? ($json['error'] ?? 'unknown error') : substr((string) $body, 0, 200);
            $reason = $httpCode === 400 || $httpCode === 422 ? 'query_failed' : 'bad_response';
            throw new MonitoringUnavailable($reason, "Prometheus HTTP {$httpCode} on {$path}: {$detail}");
        }

        return is_array($json['data'] ?? null) ? $json['data'] : [];
    }

    private static function toFloat(mixed $v): ?float
    {
        if (!is_numeric($v)) {
            return null; // "NaN", "+Inf", missing
        }
        $f = (float) $v;
        return is_finite($f) ? $f : null;
    }
}

/**
 * Singleton configured from config.php.
 */
function getPrometheus(): PrometheusClient
{
    static $client = null;
    if ($client === null) {
        $config = require dirname(__DIR__, 3) . '/config.php';
        $client = new PrometheusClient(
            (string) ($config['prometheus_url'] ?? ''),
            (int) ($config['prometheus_timeout'] ?? 5)
        );
    }
    return $client;
}
