<?php
/**
 * IntuiFy Admin — Server Control Center API (read-only).
 *
 * GET /admin/api/server-control.php?view=overview|server|containers|domains|alerts
 * GET /admin/api/server-control.php?view=chart&metric=cpu|memory|network|disk_io|load&range=1h|6h|24h|7d|30d
 *
 * Always answers 200 with { server, monitoring: {available, reason?}, data }.
 * When Prometheus cannot be reached, `available` is false and the UI shows
 * "Monitoring unavailable" — never "server offline" (spec §64.9).
 * Internal URLs and raw errors stay in the server log (spec §41.10).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
requireAuthJson();
require_once dirname(__DIR__) . '/includes/monitoring/server-control.php';
require_once dirname(__DIR__) . '/includes/monitoring/cache.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$config = require dirname(__DIR__, 2) . '/config.php';
$view   = (string) ($_GET['view'] ?? 'overview');
$server = (string) ($_GET['server'] ?? 'primary');

$response = [
    'server' => [
        'id'          => 'primary',
        'name'        => (string) ($config['monitoring_server_name'] ?? 'INTUIFY SERVER'),
        'environment' => (string) ($config['monitoring_server_env'] ?? 'Production'),
    ],
    'monitoring' => ['available' => true],
    'data' => null,
];

if ($server !== 'primary') {
    // Multi-server is modelled in the DB (spec §34) but only the primary server is wired for now
    http_response_code(404);
    echo json_encode(['error' => 'Server sconosciuto.']);
    exit;
}

// Cache TTLs from spec §29
$p = getPrometheus();
try {
    $response['data'] = match ($view) {
        'overview'   => monitoringCache('overview', 8, fn() => sccOverview($p)),
        'server'     => monitoringCache('server', 15, fn() => sccServerDetail($p)),
        'containers' => monitoringCache('containers', 5, fn() => sccContainers($p)),
        'domains'    => monitoringCache('domains', 30, fn() => sccDomains($p)),
        'alerts'     => monitoringCache('alerts', 10, fn() => sccAlerts($p)),
        'chart'      => (function () use ($p) {
            $metric = (string) ($_GET['metric'] ?? 'cpu');
            $range  = (string) ($_GET['range'] ?? '24h');
            $ttl = in_array($range, ['7d', '30d'], true) ? 180 : 30;
            return monitoringCache("chart:{$metric}:{$range}", $ttl, fn() => sccChart($p, $metric, $range));
        })(),
        default => throw new InvalidArgumentException('view'),
    };
} catch (MonitoringUnavailable $e) {
    error_log('SCC [' . $view . '] ' . $e->getMessage());
    $response['monitoring'] = ['available' => false, 'reason' => $e->reason];
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Parametro non valido: ' . $e->getMessage()]);
    exit;
} catch (\Throwable $e) {
    error_log('SCC [' . $view . '] Unexpected: ' . $e->getMessage());
    $response['monitoring'] = ['available' => false, 'reason' => 'internal_error'];
}

echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
